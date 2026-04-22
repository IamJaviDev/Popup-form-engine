<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class RestController {

    public function __construct(
        private Settings         $settings,
        private Security         $security,
        private RateLimiter      $rateLimiter,
        private PdfHandler       $pdfHandler,
        private FormHandler      $formHandler,
        private Logger           $logger,
        private FormRenderer     $formRenderer
    ) {}

    public function register(): void {
        $ns          = 'popup-form-engine/v1';
        $originCheck = [$this->security, 'isValidOrigin'];
        register_rest_route($ns, '/submit-pdf',  ['methods' => 'POST', 'callback' => [$this, 'submitPdf'],  'permission_callback' => $originCheck]);
        register_rest_route($ns, '/submit-form', ['methods' => 'POST', 'callback' => [$this, 'submitForm'], 'permission_callback' => $originCheck]);
        register_rest_route($ns, '/form-config',     ['methods' => 'GET',  'callback' => [$this, 'formConfig'],    'permission_callback' => '__return_true']);
        register_rest_route($ns, '/pdf-form-config', ['methods' => 'GET',  'callback' => [$this, 'pdfFormConfig'], 'permission_callback' => '__return_true']);
    }

    public function submitPdf(\WP_REST_Request $request): \WP_REST_Response {
        $params = (array) $request->get_params();
        if ($this->security->isHoneypot($params)) {
            $this->logBot($params, 'pdf', 'honeypot');
            return new \WP_REST_Response(['message' => __('Petición bloqueada.', 'popup-form-engine')], 400);
        }
        if ($this->security->isTimeTrap($params)) {
            $this->logBot($params, 'pdf', 'invalid');
            return new \WP_REST_Response(['message' => __('Petición bloqueada.', 'popup-form-engine')], 400);
        }
        $ip  = $this->security->getClientIp();
        $cfg = $this->settings->getGeneral();
        if (!$this->rateLimiter->isAllowed($ip, (int) ($cfg['rate_limit'] ?? 5))) {
            return new \WP_REST_Response(['message' => __('Demasiados intentos. Inténtalo más tarde.', 'popup-form-engine')], 429);
        }
        $this->rateLimiter->increment($ip);
        $result = $this->pdfHandler->handle($params, $ip, $request->get_header('user-agent') ?? '');
        return new \WP_REST_Response(['message' => $result['message']], $result['status']);
    }

    public function submitForm(\WP_REST_Request $request): \WP_REST_Response {
        $params = (array) $request->get_params();
        if ($this->security->isHoneypot($params)) {
            $this->logBot($params, 'generic', 'honeypot');
            return new \WP_REST_Response(['message' => __('Petición bloqueada.', 'popup-form-engine')], 400);
        }
        if ($this->security->isTimeTrap($params)) {
            $this->logBot($params, 'generic', 'invalid');
            return new \WP_REST_Response(['message' => __('Petición bloqueada.', 'popup-form-engine')], 400);
        }
        $ip  = $this->security->getClientIp();
        $cfg = $this->settings->getGeneral();
        if (!$this->rateLimiter->isAllowed($ip, (int) ($cfg['rate_limit'] ?? 5))) {
            return new \WP_REST_Response(['message' => __('Demasiados intentos. Inténtalo más tarde.', 'popup-form-engine')], 429);
        }
        $this->rateLimiter->increment($ip);
        $slug   = sanitize_key($params['form_slug'] ?? '');
        $result = $this->formHandler->handle($params, $slug, $ip, $request->get_header('user-agent') ?? '');
        return new \WP_REST_Response(['message' => $result['message']], $result['status']);
    }

    public function formConfig(\WP_REST_Request $request): \WP_REST_Response {
        // No nonce required: this is a public read-only endpoint that returns already-published
        // form metadata (title, subtitle, rendered field HTML). No user data is read or written.
        // Rate limiting is handled by the slug-not-found 404 path; no sensitive data is exposed.
        $slug = sanitize_key($request->get_param('slug') ?? '');
        $form = $this->settings->getFormBySlug($slug);
        if ($form === null) {
            return new \WP_REST_Response(['message' => __('Formulario no encontrado.', 'popup-form-engine')], 404);
        }
        $formHtml = ($form['mode'] ?? 'visual') === 'html'
            ? $this->formRenderer->renderHtmlMode($form, $form['html_content'] ?? '')
            : $this->formRenderer->renderVisual($form);
        return new \WP_REST_Response([
            'title'          => wp_kses_post($form['title'] ?? ''),
            'subtitle'       => wp_kses_post($form['subtitle'] ?? ''),
            'formHtml'       => $formHtml,
            'successMessage' => wp_kses_post($form['success_message'] ?? ''),
            'submitText'     => esc_html($form['submit_button_text'] ?? __('Enviar', 'popup-form-engine')),
        ], 200);
    }

    public function pdfFormConfig(\WP_REST_Request $request): \WP_REST_Response {
        $slug = sanitize_key($request->get_param('slug') ?? '');
        if ($slug === '') $slug = 'default';
        $form = $this->settings->getPdfFormBySlug($slug);
        if ($form === null) {
            return new \WP_REST_Response(['message' => __('Formulario no encontrado.', 'popup-form-engine')], 404);
        }
        $formHtml = $this->formRenderer->renderVisual($form);
        return new \WP_REST_Response([
            'title'          => wp_kses_post($form['title'] ?? ''),
            'formHtml'       => $formHtml,
            'successMessage' => wp_kses_post($form['success_message'] ?? ''),
        ], 200);
    }

    private function logBot(array $params, string $flowType, string $status): void {
        $ip = $this->security->getClientIp();
        $this->logger->insert([
            'ip' => $ip, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'flow_type' => $flowType, 'email' => '',
            'consent_status' => 'no_aplica', 'newsletter_sent' => 0,
            'newsletter_response' => 'no_aplica', 'email_sent' => 0,
            'status' => $status,
        ]);
    }
}
