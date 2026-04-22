<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class FormHandler {

    /** Fields injected by the engine; never user data. */
    private const RESERVED = [
        '_pfe_hp', '_pfe_ts', 'form_slug', 'pfe_newsletter_consent',
        'pfe_callback_requested', 'pfe_callback_day', 'pfe_callback_time',
    ];

    public function __construct(
        private Settings         $settings,
        private NewsletterClient $newsletter,
        private Logger           $logger
    ) {}

    public function handle(array $params, string $formSlug, string $ip, string $userAgent): array {
        $form = $this->settings->getFormBySlug($formSlug);
        if ($form === null) {
            return ['status' => 404, 'message' => __('Formulario no encontrado.', 'popup-form-engine')];
        }

        $consent = isset($params['pfe_newsletter_consent']) && $params['pfe_newsletter_consent'] === '1';
        $mode    = $form['mode'] ?? 'visual';

        // ── Collect and validate field data ────────────────────────────────────────

        if ($mode === 'html') {
            // HTML mode: collect every submitted param except reserved engine fields.
            // sanitize_text_field() on the key preserves readable names (unlike sanitize_key
            // which lowercases and strips accents, mutilating field names in logs/payload).
            // Values: arrays (multi-checkbox, multi-select) are joined; plain values use
            // sanitize_textarea_field() so textarea newlines survive.
            $data = [];
            foreach ($params as $key => $val) {
                if (in_array($key, self::RESERVED, true)) continue;
                $cleanKey = sanitize_text_field((string) $key);
                if ($cleanKey === '') continue;
                if (is_array($val)) {
                    $data[$cleanKey] = implode(', ', array_map('sanitize_text_field', $val));
                } else {
                    $data[$cleanKey] = sanitize_textarea_field((string) $val);
                }
            }
        } else {
            // Visual mode: iterate declared fields, sanitize each, check required.
            $data = [];
            foreach ((array) ($form['fields'] ?? []) as $field) {
                $name = sanitize_key($field['name'] ?? '');
                $type = sanitize_key($field['type'] ?? 'text');
                if ($name === '') continue;
                $raw         = $params[$name] ?? '';
                $data[$name] = $type === 'textarea'
                    ? sanitize_textarea_field($raw)
                    : sanitize_text_field($raw);
                if (!empty($field['required']) && $data[$name] === '') {
                    return ['status' => 400, 'message' => sprintf(
                        __('El campo "%s" es obligatorio.', 'popup-form-engine'),
                        esc_html($field['label'] ?? $name)
                    )];
                }
            }
        }

        // Email is mandatory in both modes.
        $email = sanitize_email($data['email'] ?? '');
        if (empty($email) || !is_email($email)) {
            return ['status' => 400, 'message' => __('Email no válido o ausente.', 'popup-form-engine')];
        }

        // ── Callback ("Llámame") ────────────────────────────────────────────────────

        // Server-side guard: only process callback if the form has it enabled.
        $callbackEnabled   = !empty($form['callback_enabled']);
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

        // ── Email headers (used by callback email) ─────────────────────────────────

        $general   = $this->settings->getGeneral();
        $fromEmail = sanitize_email($general['from_email'] ?? get_option('admin_email'));
        $fromName  = sanitize_text_field($general['from_name'] ?? get_bloginfo('name'));
        $headers   = ['Content-Type: text/html; charset=UTF-8', "From: {$fromName} <{$fromEmail}>"];

        // ── Callback email ─────────────────────────────────────────────────────────
        // Independent from internal email: fires whenever callback was requested and
        // recipients are configured, regardless of send_internal_email setting.

        $callbackEmailSent = false;
        if ($callbackRequested && !empty($form['callback_email_recipients'])) {
            $callbackEmailSent = $this->sendCallbackEmail($form, $formSlug, $data, $email, $callbackDay, $callbackTime, $headers);
        }

        // ── Newsletter ──────────────────────────────────────────────────────────────

        $nlSent        = false;
        $nlResponse    = 'no_aplica';
        $consentStatus = 'no_aplica';

        // Both per-form and global newsletter must be enabled to process subscription.
        $formNlEnabled   = !empty($form['newsletter_enabled']);
        $globalNlEnabled = $this->settings->isNewsletterEnabled();

        if ($formNlEnabled && $globalNlEnabled) {
            $consentStatus = $consent ? 'true' : 'false';
            if ($consent) {
                // Normalise field aliases: name/nombre, phone/tel/telefono
                $nlName  = $data['name']  ?? $data['nombre']    ?? '';
                $nlPhone = $data['phone'] ?? $data['tel']        ?? $data['telefono'] ?? '';

                $payload = apply_filters('pfe_newsletter_payload', [
                    'email'   => $email,
                    'name'    => $nlName,
                    'phone'   => $nlPhone,
                    'consent' => true,
                    'guia'    => true,
                    // source_domain is added by NewsletterClient::send()
                ], 'generic');

                $result     = $this->newsletter->send($payload);
                $nlSent     = $result['sent'];
                $nlResponse = $result['response'];
            }
        }

        do_action('pfe_after_generic_submit', $email, $formSlug, $data, $consent);

        // Build log payload — callback metadata uses _ prefix to separate from form fields.
        $logPayload = $data;
        if ($callbackEnabled) {
            $logPayload['_callback_requested'] = $callbackRequested ? 'si' : 'no';
            if ($callbackRequested) {
                $logPayload['_callback_day']        = $callbackDay;
                $logPayload['_callback_time']        = $callbackTime;
                $logPayload['_callback_email_sent']  = $callbackEmailSent ? 'si' : 'no';
            }
        }

        $errorMessage = null;
        if ($callbackRequested && !$callbackEmailSent) {
            $errorMessage = 'callback_email_failed';
        }

        $this->logger->insert([
            'ip'                  => $ip,
            'user_agent'          => $userAgent,
            'flow_type'           => 'generic',
            'form_identifier'     => $formSlug,
            'email'               => $email,
            'payload'             => $logPayload,
            'consent_status'      => $consentStatus,
            'newsletter_sent'     => $nlSent ? 1 : 0,
            'newsletter_response' => $nlResponse,
            'status'              => 'ok',
            'error_message'       => $errorMessage,
        ]);

        $successMsg = wp_kses_post($form['success_message'] ?? __('¡Mensaje enviado correctamente!', 'popup-form-engine'));
        return ['status' => 200, 'message' => $successMsg];
    }

    private function sendCallbackEmail(
        array  $form,
        string $formSlug,
        array  $data,
        string $email,
        string $day,
        string $time,
        array  $headers
    ): bool {
        $recipientsRaw = trim((string) ($form['callback_email_recipients'] ?? ''));
        $recipients    = array_filter(array_map('sanitize_email', explode("\n", $recipientsRaw)));
        if (empty($recipients)) return false;

        $subject = sprintf(
            __('Solicitud de llamada desde formulario: %s', 'popup-form-engine'),
            $formSlug
        );

        $name  = $data['name']  ?? $data['nombre']   ?? '';
        $phone = $data['phone'] ?? $data['tel']       ?? $data['telefono'] ?? '';

        $body  = '<strong>' . esc_html__('Solicitud de llamada', 'popup-form-engine') . '</strong><br><br>';
        $body .= esc_html__('Formulario', 'popup-form-engine') . ': ' . esc_html($formSlug) . '<br>';
        $body .= 'Email: ' . esc_html($email) . '<br>';
        if ($name  !== '') $body .= esc_html__('Nombre',    'popup-form-engine') . ': ' . esc_html($name)  . '<br>';
        if ($phone !== '') $body .= esc_html__('Teléfono',  'popup-form-engine') . ': ' . esc_html($phone) . '<br>';
        $body .= esc_html__('Día solicitado',  'popup-form-engine') . ': ' . esc_html($day)  . '<br>';
        $body .= esc_html__('Hora solicitada', 'popup-form-engine') . ': ' . esc_html($time) . '<br>';

        $skip = ['email', 'name', 'nombre', 'phone', 'tel', 'telefono'];
        $extra = array_filter($data, fn($k) => !in_array($k, $skip, true), ARRAY_FILTER_USE_KEY);
        if (!empty($extra)) {
            $body .= '<br><strong>' . esc_html__('Otros datos del envío', 'popup-form-engine') . ':</strong><br>';
            foreach ($extra as $k => $v) {
                $body .= esc_html($k) . ': ' . esc_html((string) $v) . '<br>';
            }
        }

        $ctFilter = function (): string { return 'text/html'; };
        add_filter('wp_mail_content_type', $ctFilter);
        $sent = wp_mail(implode(',', $recipients), $subject, $body, $headers);
        remove_filter('wp_mail_content_type', $ctFilter);

        return (bool) $sent;
    }
}
