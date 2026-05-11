<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// ── Always remove the cron event (must never be left orphaned) ────────────────
wp_clear_scheduled_hook('pfe_logs_cleanup_cron');

// ── Conservative default: preserve all data unless the admin opted in ─────────
$general    = get_option('pfe_general', []);
$deleteData = is_array($general) && !empty($general['delete_data_on_uninstall']);

if (!$deleteData) {
    // Data is preserved. Reinstalling the plugin will restore everything.
    return;
}

// ── Full wipe — only runs when admin enabled "Borrar datos al desinstalar" ────
global $wpdb;

// 1. Explicitly delete all known options (covers both single-site and multisite).
$options = [
    // Active settings
    'pfe_general',
    'pfe_newsletter',
    'pfe_branding',
    'pfe_forms',
    'pfe_pdf_forms',
    'pfe_pdf_email_templates',
    'pfe_pdf_templates',
    'pfe_pdf_file_mappings',
    'pfe_logs_retention',
    'pfe_db_version',
    // Legacy options (may exist in older installs)
    'pfe_pdf_newsletter',
    'pfe_cf7_whitelist',
];
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}

// 2. Catch any pfe_ option added by future versions or missed above.
//    '\_' in MySQL LIKE escapes the underscore to match it literally.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pfe\_%'");

// 3. Transients — rate limiter writes _transient_pfe_rl_{ip} entries.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_pfe\_%'
        OR option_name LIKE '\_transient\_timeout\_pfe\_%'"
);

// 4. Drop the logs table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}popup_form_engine_logs");

// 5. User meta (defensive — plugin does not write usermeta, but clean up if any).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pfe\_%'");
