<?php
declare(strict_types=1);

namespace PopupFormEngine;

defined('ABSPATH') || exit;

class Installer {

    public function run(): void {
        $this->createTable();
        $this->setDefaults();
        $this->migratePdfTemplates();
    }

    private function createTable(): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'popup_form_engine_logs';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            ip VARCHAR(45) NOT NULL,
            user_agent TEXT,
            flow_type VARCHAR(20) NOT NULL,
            form_identifier VARCHAR(191) DEFAULT NULL,
            email VARCHAR(191) DEFAULT NULL,
            payload_json LONGTEXT DEFAULT NULL,
            consent_status VARCHAR(20) NOT NULL DEFAULT 'no_aplica',
            newsletter_sent TINYINT(1) NOT NULL DEFAULT 0,
            newsletter_response TEXT DEFAULT NULL,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL,
            error_message TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_created_at (created_at),
            INDEX idx_flow_type (flow_type),
            INDEX idx_email (email(191)),
            INDEX idx_consent_status (consent_status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('pfe_db_version', PFE_VERSION);
    }

    private function setDefaults(): void {
        if (!get_option('pfe_general')) {
            update_option('pfe_general', [
                'from_email'      => get_option('admin_email'),
                'from_name'       => get_bloginfo('name'),
                'internal_emails' => '',
                'rate_limit'      => 5,
            ]);
        }
        if (!get_option('pfe_newsletter')) {
            update_option('pfe_newsletter', [
                'enabled'  => false,
                'protocol' => 'https',
                'port'     => 63443,
                'path'     => '/newsletter/subscribe',
                'timeout'  => 10,
            ]);
        }
        if (get_option('pfe_forms') === false) {
            update_option('pfe_forms', []);
        }
        if (get_option('pfe_pdf_templates') === false) {
            update_option('pfe_pdf_templates', []);
        }
        if (!get_option('pfe_branding')) {
            update_option('pfe_branding', [
                'empresa' => '', 'logo' => '', 'color_primario' => '', 'color_secundario' => '',
                'web' => '', 'telefono_empresa' => '', 'email_empresa' => '', 'aviso_legal' => '',
            ]);
        }
    }

    /**
     * Imports .html files from /templates/ into pfe_pdf_email_templates option.
     * Enriches existing page-slug mappings with template_slug derived from template_file.
     * Idempotent: skips if pfe_pdf_email_templates already has content.
     */
    private function migratePdfTemplates(): void {
        $existing = get_option('pfe_pdf_email_templates');
        if (is_array($existing) && !empty($existing)) {
            // Already migrated; only ensure pfe_pdf_file_mappings exists.
            if (get_option('pfe_pdf_file_mappings') === false) {
                update_option('pfe_pdf_file_mappings', []);
            }
            return;
        }

        // Import only the neutral boilerplate — legacy RO-DES templates stay out of new installs.
        // Existing installs that already ran the old migration keep their data untouched
        // thanks to the idempotency guard at the top of this method.
        $boilerplatePath = PFE_DIR . 'templates/_boilerplate.html';
        $templates       = [];
        if (file_exists($boilerplatePath)) {
            $templates[] = [
                'slug'      => 'boilerplate',
                'name'      => 'Plantilla base',
                'subject'   => 'Tu guía',
                'html_body' => (string) file_get_contents($boilerplatePath),
            ];
        }
        update_option('pfe_pdf_email_templates', $templates);

        // Enrich existing page-slug mappings with template_slug
        $mappings = (array) get_option('pfe_pdf_templates', []);
        $enriched = false;
        foreach ($mappings as &$m) {
            if (empty($m['template_slug']) && !empty($m['template_file'])) {
                $m['template_slug'] = basename((string) $m['template_file'], '.html');
                $enriched = true;
            }
        }
        unset($m);
        if ($enriched) {
            update_option('pfe_pdf_templates', $mappings);
        }

        // Initialize filename mappings
        if (get_option('pfe_pdf_file_mappings') === false) {
            update_option('pfe_pdf_file_mappings', []);
        }

        // Remove legacy option no longer used by the plugin.
        delete_option('pfe_pdf_newsletter');
    }

}
