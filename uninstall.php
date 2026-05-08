<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

wp_clear_scheduled_hook('pfe_logs_cleanup_cron');

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}popup_form_engine_logs");

foreach ([
    'pfe_general',
    'pfe_forms',
    'pfe_newsletter',
    'pfe_cf7_whitelist',
    'pfe_pdf_templates',
    'pfe_pdf_newsletter',
    'pfe_pdf_forms',
    'pfe_pdf_email_templates',
    'pfe_pdf_file_mappings',
    'pfe_branding',
    'pfe_logs_retention',
    'pfe_db_version',
] as $opt) {
    delete_option($opt);
}
