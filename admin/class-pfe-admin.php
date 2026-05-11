<?php
defined('ABSPATH') || exit;

use PopupFormEngine\Settings;

class PFE_Admin {

    private PFE_AdminPage $adminPage;

    public function __construct(Settings $settings) {
        $this->adminPage = new PFE_AdminPage($settings);
        add_action('admin_menu',                   [$this, 'registerMenu']);
        add_action('admin_post_pfe_export_csv',    [$this, 'handleExportCsv']);
        add_action('admin_post_pfe_clean_logs',    [$this, 'handleCleanLogs']);
        add_action('wp_ajax_pfe_logs_purge',        [$this, 'handleLogsPurge']);
        add_action('wp_ajax_pfe_newsletter_test',    [$this, 'handleNewsletterTest']);
        add_action('wp_ajax_pfe_template_test_send', [$this, 'handleTemplateTestSend']);
    }

    public function registerMenu(): void {
        $hook = add_menu_page(
            'Popup Form Engine',
            'Popup Forms',
            'manage_options',
            'popup-form-engine',
            [$this, 'renderPage'],
            'dashicons-forms',
            60
        );
        add_action("load-{$hook}", [$this->adminPage, 'handleSave']);
    }

    public function renderPage(): void {
        $this->adminPage->render();
    }

    /**
     * GET admin-post.php?action=pfe_export_csv
     * Streams a CSV download of the filtered log entries.
     */
    public function handleExportCsv(): void {
        check_admin_referer('pfe_export_csv');
        if (!current_user_can('manage_options')) wp_die();

        $filters = array_filter([
            'flow_type'      => sanitize_key($_GET['flow_type']           ?? ''),
            'consent_status' => sanitize_key($_GET['consent_status']      ?? ''),
            'date_from'      => sanitize_text_field($_GET['date_from']    ?? ''),
            'date_to'        => sanitize_text_field($_GET['date_to']      ?? ''),
            'search_email'   => sanitize_text_field($_GET['search_email'] ?? ''),
        ], fn($v) => $v !== '');

        (new \PopupFormEngine\Logger())->exportCsv($filters);
    }

    /**
     * wp_ajax_pfe_template_test_send — renders a PDF email template with dummy data and sends it.
     */
    public function handleTemplateTestSend(): void {
        check_ajax_referer('pfe_template_test', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'forbidden'], 403);
        }

        $recipient = sanitize_email(wp_unslash($_POST['recipient'] ?? ''));
        if (empty($recipient) || !is_email($recipient)) {
            wp_send_json_error(['message' => __('Email destinatario no válido.', 'popup-form-engine')]);
        }

        $subject  = sanitize_text_field(wp_unslash($_POST['subject']  ?? ''));
        $htmlBody = (string) wp_unslash($_POST['html_body'] ?? '');

        if (empty($htmlBody)) {
            wp_send_json_error(['message' => __('El HTML del template está vacío.', 'popup-form-engine')]);
        }
        if (empty($subject)) {
            $subject = __('Prueba de template (Popup Form Engine)', 'popup-form-engine');
        }

        $settings = new \PopupFormEngine\Settings();
        $branding = $settings->getBranding();
        $general  = $settings->getGeneral();

        // Find a real PDF in uploads as sample, or fall back to a placeholder URL.
        $uploadDir = wp_upload_dir();
        $samplePdf = trailingslashit($uploadDir['baseurl']) . 'ejemplo.pdf';
        $firstPdf  = glob(trailingslashit($uploadDir['basedir']) . '*.pdf');
        if (!empty($firstPdf)) {
            $samplePdf = trailingslashit($uploadDir['baseurl']) . basename($firstPdf[0]);
        }

        // Dummy values for common placeholders.
        $defaults = [
            'nombre'   => 'Juan Palomo',
            'name'     => 'Juan Palomo',
            'email'    => $recipient,
            'telefono' => '+34 600 000 000',
            'tel'      => '+34 600 000 000',
            'phone'    => '+34 600 000 000',
            'title'    => 'Guía de ejemplo',
            'pdf'      => $samplePdf,
        ];

        // Branding placeholders (use configured values if available).
        foreach (['empresa', 'logo', 'color_primario', 'color_secundario', 'web', 'telefono_empresa', 'email_empresa', 'aviso_legal'] as $bKey) {
            $defaults[$bKey] = $branding[$bKey] ?? '';
        }

        $finalHtml    = $htmlBody;
        $finalSubject = $subject;

        // Replace known placeholders.
        foreach ($defaults as $key => $value) {
            $pattern      = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/';
            $finalHtml    = preg_replace($pattern, (string) $value, $finalHtml);
            $finalSubject = preg_replace($pattern, (string) $value, $finalSubject);
        }

        // Heuristic fill for any remaining unknown {{ placeholder }} in body.
        $finalHtml = preg_replace_callback(
            '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            static function (array $m): string {
                $name = strtolower($m[1]);
                if (str_contains($name, 'email') || str_contains($name, 'mail')) return 'ejemplo@dominio.com';
                if (str_contains($name, 'tel')   || str_contains($name, 'phone') || str_contains($name, 'movil')) return '+34 600 000 000';
                if (str_contains($name, 'dni')   || str_contains($name, 'nif'))  return '12345678X';
                if (str_contains($name, 'fecha') || str_contains($name, 'date')) return date_i18n(get_option('date_format'));
                return '[Ejemplo]';
            },
            $finalHtml
        );

        // Clear unresolved placeholders from subject (mirrors PdfHandler behaviour).
        $finalSubject = (string) preg_replace('/\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}/', '', $finalSubject);

        $fromEmail = sanitize_email($general['from_email'] ?? get_option('admin_email'));
        $fromName  = sanitize_text_field($general['from_name'] ?? get_bloginfo('name'));

        $ctFilter   = static function (): string { return 'text/html'; };
        $fromFilter = static function () use ($fromEmail): string { return $fromEmail; };
        $nameFilter = static function () use ($fromName):  string { return $fromName; };

        add_filter('wp_mail_content_type', $ctFilter);
        add_filter('wp_mail_from',         $fromFilter);
        add_filter('wp_mail_from_name',    $nameFilter);

        $sent = wp_mail($recipient, '[PRUEBA] ' . $finalSubject, $finalHtml, ['Content-Type: text/html; charset=UTF-8']);

        remove_filter('wp_mail_content_type', $ctFilter);
        remove_filter('wp_mail_from',         $fromFilter);
        remove_filter('wp_mail_from_name',    $nameFilter);

        if ($sent) {
            wp_send_json_success([
                'message'   => sprintf(__('Email de prueba enviado a %s', 'popup-form-engine'), $recipient),
                'recipient' => $recipient,
            ]);
        } else {
            wp_send_json_error(['message' => __('wp_mail() devolvió false. Revisa la configuración de envío del servidor.', 'popup-form-engine')]);
        }
    }

    /**
     * wp_ajax_pfe_newsletter_test — sends a test payload to the configured backend.
     */
    public function handleNewsletterTest(): void {
        check_ajax_referer('pfe_newsletter_test', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'forbidden'], 403);

        $settings = new \PopupFormEngine\Settings();
        $client   = new \PopupFormEngine\NewsletterClient($settings);
        $result   = $client->testConnection();

        wp_send_json_success($result);
    }

    /**
     * wp_ajax_pfe_logs_purge — deletes logs older than N days, returns JSON.
     */
    public function handleLogsPurge(): void {
        check_ajax_referer('pfe_logs_purge', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error([], 403);

        $days    = max(1, (int) ($_POST['days'] ?? 90));
        $deleted = (new \PopupFormEngine\Logger())->deleteBefore($days);
        wp_send_json_success(['deleted' => $deleted]);
    }

    /**
     * POST admin-post.php  action=pfe_clean_logs
     * Deletes log rows older than N days and redirects back with count.
     */
    public function handleCleanLogs(): void {
        check_admin_referer('pfe_clean_logs');
        if (!current_user_can('manage_options')) wp_die();

        $days    = max(1, (int) ($_POST['clean_days'] ?? 30));
        $deleted = (new \PopupFormEngine\Logger())->deleteBefore($days);

        wp_safe_redirect(add_query_arg([
            'page'    => 'popup-form-engine',
            'tab'     => 'logs',
            'cleaned' => $deleted,
        ], admin_url('admin.php')));
        exit;
    }
}
