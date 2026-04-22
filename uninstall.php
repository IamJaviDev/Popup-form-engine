<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}popup_form_engine_logs");

foreach ([
    'pfe_general',
    'pfe_forms',
    'pfe_newsletter',
    'pfe_cf7_whitelist',
    'pfe_pdf_templates',
    'pfe_pdf_newsletter',
    'pfe_db_version',
] as $opt) {
    delete_option($opt);
}
