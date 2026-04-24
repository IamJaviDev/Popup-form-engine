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
        if (!get_option('pfe_pdf_newsletter')) {
            update_option('pfe_pdf_newsletter', [
                'enabled'     => false,
                'label'       => 'Quiero recibir novedades por email',
                'pre_checked' => false,
                'required'    => false,
                'position'    => 'before_submit',
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
        if (!get_option('pfe_forms')) {
            update_option('pfe_forms', []);
        }
        if (!get_option('pfe_pdf_templates')) {
            update_option('pfe_pdf_templates', $this->defaultPdfTemplates());
        }
        if (!get_option('pfe_branding')) {
            update_option('pfe_branding', [
                'empresa' => '', 'logo' => '', 'color_primario' => '', 'color_secundario' => '',
                'web' => '', 'telefono_empresa' => '', 'email_empresa' => '', 'aviso_legal' => '',
            ]);
        }
    }

    /**
     * Default PDF template mappings — PAGE_SLUG_TEMPLATE / legacy mode.
     *
     * Resolution: the last path segment of pageUrl (the page slug) is matched
     * against slug_contains. First match wins.
     *
     * Origin legend:
     *   [legacy]  — mapping existed in Tota-form-detecterv2/enviar-enlace-email.php
     *   [new]     — mapping added during migration (no legacy equivalent)
     *
     * All legacy templates may contain hardcoded PDF links and RO-DES branding.
     * They are NOT multisite-friendly in their current form.
     *
     * NOTE: baja-de-un-vehiculo-robado was present in legacy templates/ but had
     * no mapping in the legacy PHP; the mapping here is a migration decision [new].
     */
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

        // Import all .html files from /templates/
        $templDir  = PFE_DIR . 'templates/';
        $htmlFiles = glob($templDir . '*.html') ?: [];
        $templates = [];
        foreach ($htmlFiles as $file) {
            $slug        = basename($file, '.html');
            $name        = ucfirst(str_replace('-', ' ', $slug));
            $templates[] = [
                'slug'      => $slug,
                'name'      => $name,
                'subject'   => 'Tu guía: ' . $name,
                'html_body' => (string) file_get_contents($file),
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
    }

    private function defaultPdfTemplates(): array {
        return [
            // [legacy]
            ['slug_contains' => 'altas-y-rehabilitaciones-de-vehiculos',            'template_file' => 'alta-y-rehabilitacion.html'],
            // [legacy]
            ['slug_contains' => 'certificado-de-destruccion',                       'template_file' => 'certificado-de-destruccion.html'],
            // [legacy]
            ['slug_contains' => 'impuesto-de-circulacion',                          'template_file' => 'impuesto-de-circulacion.html'],
            // [legacy]
            ['slug_contains' => 'baja-de-vehiculos-por-exportacion',                'template_file' => 'baja-por-exportacion.html'],
            // [legacy]
            ['slug_contains' => 'cambio-de-titularidad-de-un-vehiculo',             'template_file' => 'cambio-de-titularidad.html'],
            // [legacy]
            ['slug_contains' => 'cambio-de-domicilio',                              'template_file' => 'cambio-domicilio.html'],
            // [legacy]
            ['slug_contains' => 'baja-temporal-de-vehiculos',                       'template_file' => 'baja-temporal.html'],
            // [legacy]
            ['slug_contains' => 'multas-trafico',                                   'template_file' => 'multas-de-trafico.html'],
            // [legacy] — slug: notificaciones-de-sanciones-por-sms-o-email-dev
            ['slug_contains' => 'notificaciones-de-sanciones-por-sms-o-email-dev', 'template_file' => 'dev.html'],
            // [legacy]
            ['slug_contains' => 'duplicados-y-renovaciones',                        'template_file' => 'duplicados-y-renovaciones.html'],
            // [legacy]
            ['slug_contains' => 'informes-de-vehiculos-en-trafico',                 'template_file' => 'informe-vehiculos.html'],
            // [legacy] — slug: tresta-consultar-multas-de-trafico-en-internet
            ['slug_contains' => 'tresta-consultar-multas-de-trafico-en-internet',   'template_file' => 'testra.html'],
            // [new] — template existed in legacy but had no mapping in the PHP
            ['slug_contains' => 'baja-de-un-vehiculo-robado',                       'template_file' => 'baja-de-un-vehiculo-robado.html'],
        ];
    }
}
