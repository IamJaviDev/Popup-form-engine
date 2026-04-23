<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class PdfHandler {

    public function __construct(
        private Settings         $settings,
        private NewsletterClient $newsletter,
        private Logger           $logger,
        private Security         $security
    ) {}

    public function handle(array $params, string $ip, string $userAgent): array {
        $name    = sanitize_text_field($params['name'] ?? '');
        $email   = sanitize_email($params['email'] ?? '');
        $country = sanitize_text_field($params['country'] ?? '');
        $tel     = sanitize_text_field($params['tel'] ?? '');
        $pdfUrl  = esc_url_raw($params['pdfUrl'] ?? '');
        $pageUrl = sanitize_text_field($params['pageUrl'] ?? '');
        $consent = isset($params['pfe_newsletter_consent']) && $params['pfe_newsletter_consent'] === '1';

        $pdfFormSlug   = sanitize_key($params['pdf_form_slug'] ?? '') ?: 'default';
        $pdfFormConfig = $this->settings->getPdfFormBySlug($pdfFormSlug);

        // Validate required fields declared in the PDF form config (skip email — checked below).
        if ($pdfFormConfig !== null) {
            foreach ((array) ($pdfFormConfig['fields'] ?? []) as $field) {
                if (empty($field['required'])) continue;
                $fieldName = sanitize_key($field['name'] ?? '');
                if ($fieldName === '' || $fieldName === 'email') continue;
                $val = sanitize_text_field($params[$fieldName] ?? '');
                if ($val === '') {
                    return ['status' => 400, 'message' => sprintf(
                        __('El campo "%s" es obligatorio.', 'popup-form-engine'),
                        esc_html($field['label'] ?? $fieldName)
                    )];
                }
            }
        }

        if (!is_email($email)) {
            return ['status' => 400, 'message' => __('Email no válido.', 'popup-form-engine')];
        }
        if (empty($pdfUrl) || !$this->security->validatePdfUrl($pdfUrl)) {
            return ['status' => 400, 'message' => __('URL de PDF no válida.', 'popup-form-engine')];
        }

        // ── Callback ("Llámame") ────────────────────────────────────────────────────
        $callbackEnabled   = !empty($pdfFormConfig['callback_enabled']);
        $callbackRequested = $callbackEnabled && ($params['pfe_callback_requested'] ?? '') === '1';
        $callbackDay       = sanitize_text_field($params['pfe_callback_day']  ?? '');
        $callbackTime      = sanitize_text_field($params['pfe_callback_time'] ?? '');

        if ($callbackRequested) {
            if ($callbackDay === '') {
                return ['status' => 400, 'message' => __('El día de llamada es obligatorio.', 'popup-form-engine')];
            }
            if ($callbackTime === '') {
                return ['status' => 400, 'message' => __('La hora de llamada es obligatoria.', 'popup-form-engine')];
            }
        }

        // PAGE_SLUG_TEMPLATE mode (legacy): the email template is chosen from the
        // URL slug of the page where the click happened, not from the PDF filename.
        // Many templates contain hardcoded PDF download links and branded content;
        // the {{ pdf }} placeholder replacement below is safe even when absent.
        $cleanPath = trim((string) parse_url($pageUrl, PHP_URL_PATH), '/');
        $segments  = $cleanPath !== '' ? explode('/', $cleanPath) : [];
        $pageSlug  = end($segments) ?: '';

        $templatePath = $this->settings->resolveTemplatePath($pageSlug);
        if (!file_exists($templatePath)) {
            $this->logger->insert([
                'ip' => $ip, 'user_agent' => $userAgent, 'flow_type' => 'pdf',
                'email' => $email, 'consent_status' => $consent ? 'true' : 'false',
                'status' => 'error', 'error_message' => 'Template not found: ' . $templatePath,
            ]);
            return ['status' => 500, 'message' => __('No se encontró la plantilla de email.', 'popup-form-engine')];
        }

        $pdfFilename = basename((string) parse_url($pdfUrl, PHP_URL_PATH));
        $title       = ucfirst(str_replace('-', ' ', (string) preg_replace('/\.pdf$/i', '', $pdfFilename)));
        $html        = (string) file_get_contents($templatePath);
        $html        = str_replace(['{{ nombre }}','{{ title }}','{{ pdf }}','{{ country }}','{{ tel }}'],
                                   [esc_html($name), esc_html($title), $pdfUrl, esc_html($country), esc_html($tel)],
                                   $html);

        $general   = $this->settings->getGeneral();
        $fromEmail = sanitize_email($general['from_email'] ?? get_option('admin_email'));
        $fromName  = sanitize_text_field($general['from_name'] ?? get_bloginfo('name'));
        $subject   = apply_filters('pfe_pdf_email_subject', __('Tu enlace para descargar la guía', 'popup-form-engine'), $pageSlug);
        $headers    = ['Content-Type: text/html; charset=UTF-8'];

        $ctFilter   = function (): string { return 'text/html'; };
        $fromFilter = fn() => $fromEmail;
        $nameFilter = fn() => $fromName;
        add_filter('wp_mail_content_type', $ctFilter);
        add_filter('wp_mail_from',         $fromFilter);
        add_filter('wp_mail_from_name',    $nameFilter);
        $sent = wp_mail($email, $subject, $html, $headers);
        remove_filter('wp_mail_content_type', $ctFilter);
        remove_filter('wp_mail_from',         $fromFilter);
        remove_filter('wp_mail_from_name',    $nameFilter);

        if (!$sent) {
            $this->logger->insert([
                'ip' => $ip, 'user_agent' => $userAgent, 'flow_type' => 'pdf',
                'email' => $email, 'consent_status' => $consent ? 'true' : 'false',
                'status' => 'error', 'error_message' => 'wp_mail failed',
            ]);
            return ['status' => 500, 'message' => __('Error al enviar el email. Inténtalo de nuevo.', 'popup-form-engine')];
        }

        // ── Callback email (additional, does not block PDF delivery) ───────────────
        $callbackEmailSent = false;
        if ($callbackRequested && !empty($pdfFormConfig['callback_email_recipients'])) {
            $callbackEmailSent = $this->sendPdfCallbackEmail(
                $pdfFormConfig, $pdfFormSlug, $name, $email, $callbackDay, $callbackTime, $fromEmail, $fromName
            );
        }

        $pdfNewsletter      = $this->settings->getPdfNewsletter();
        $newsletterSent     = false;
        $newsletterResponse = 'no_aplica';
        $consentStatus      = 'no_aplica';

        if (!empty($pdfNewsletter['enabled'])) {
            $consentStatus = $consent ? 'true' : 'false';
            if ($consent) {
                $payload = apply_filters('pfe_newsletter_payload', [
                    'email' => $email, 'name' => $name, 'phone' => $tel, 'consent' => true, 'guia' => true,
                ], 'pdf');
                $result             = $this->newsletter->send($payload);
                $newsletterSent     = $result['sent'];
                $newsletterResponse = $result['response'];
            }
        }

        do_action('pfe_after_pdf_submit', $email, $pageSlug, $consent);

        $logPayload = ['name' => $name, 'country' => $country, 'tel' => $tel, 'pdfUrl' => $pdfUrl];
        if ($callbackEnabled) {
            $logPayload['_callback_requested'] = $callbackRequested ? 'si' : 'no';
            if ($callbackRequested) {
                $logPayload['_callback_day']        = $callbackDay;
                $logPayload['_callback_time']        = $callbackTime;
                $logPayload['_callback_email_sent']  = $callbackEmailSent ? 'si' : 'no';
            }
        }

        $this->logger->insert([
            'ip' => $ip, 'user_agent' => $userAgent, 'flow_type' => 'pdf',
            'form_identifier' => $pageSlug, 'email' => $email,
            'payload' => $logPayload,
            'consent_status' => $consentStatus,
            'newsletter_sent' => $newsletterSent ? 1 : 0,
            'newsletter_response' => $newsletterResponse,
            'email_sent' => 1, 'status' => 'ok',
        ]);

        $successMsg = !empty($pdfFormConfig['success_message'])
            ? wp_kses_post($pdfFormConfig['success_message'])
            : __('El enlace fue enviado a tu email.', 'popup-form-engine');
        return ['status' => 200, 'message' => $successMsg];
    }

    private function sendPdfCallbackEmail(
        array  $pdfFormConfig,
        string $pdfFormSlug,
        string $name,
        string $email,
        string $day,
        string $time,
        string $fromEmail,
        string $fromName
    ): bool {
        $recipientsRaw = trim((string) ($pdfFormConfig['callback_email_recipients'] ?? ''));
        $recipients    = array_filter(array_map('sanitize_email', explode("\n", $recipientsRaw)));
        if (empty($recipients)) return false;

        $subject = sprintf(
            __('Solicitud de llamada desde formulario PDF: %s', 'popup-form-engine'),
            $pdfFormSlug
        );

        $body  = '<strong>' . esc_html__('Solicitud de llamada (formulario PDF)', 'popup-form-engine') . '</strong><br><br>';
        $body .= esc_html__('Formulario', 'popup-form-engine') . ': ' . esc_html($pdfFormSlug) . '<br>';
        $body .= 'Email: ' . esc_html($email) . '<br>';
        if ($name !== '') $body .= esc_html__('Nombre', 'popup-form-engine') . ': ' . esc_html($name) . '<br>';
        $body .= esc_html__('Día solicitado',  'popup-form-engine') . ': ' . esc_html($day)  . '<br>';
        $body .= esc_html__('Hora solicitada', 'popup-form-engine') . ': ' . esc_html($time) . '<br>';

        $headers    = ['Content-Type: text/html; charset=UTF-8'];
        $ctFilter   = function (): string { return 'text/html'; };
        $fromFilter = fn() => $fromEmail;
        $nameFilter = fn() => $fromName;
        add_filter('wp_mail_content_type', $ctFilter);
        add_filter('wp_mail_from',         $fromFilter);
        add_filter('wp_mail_from_name',    $nameFilter);
        $sent = wp_mail(implode(',', $recipients), $subject, $body, $headers);
        remove_filter('wp_mail_content_type', $ctFilter);
        remove_filter('wp_mail_from',         $fromFilter);
        remove_filter('wp_mail_from_name',    $nameFilter);

        return (bool) $sent;
    }
}
