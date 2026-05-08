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
        add_action('wp_ajax_pfe_newsletter_test',   [$this, 'handleNewsletterTest']);
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
