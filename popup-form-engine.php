<?php
declare(strict_types=1);

/**
 * Plugin Name: Popup Form Engine
 * Plugin URI:  https://github.com/
 * Description: Multi-site, multi-form popup engine (PDF, CF7, generic). v2.0
 * Version:     2.0.0
 * Author:      Don Javier
 * Text Domain: popup-form-engine
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

defined('ABSPATH') || exit;

define('PFE_VERSION',     '2.0.0');
define('PFE_FILE',        __FILE__);
define('PFE_DIR',         plugin_dir_path(__FILE__));
define('PFE_URL',         plugin_dir_url(__FILE__));
define('PFE_TEXT_DOMAIN', 'popup-form-engine');

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'PopupFormEngine\\')) {
        return;
    }
    $relative = substr($class, strlen('PopupFormEngine\\'));
    // CamelCase → kebab-case: insert hyphen between (lowercase|digit) and uppercase
    $kebab = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $relative));
    $file  = PFE_DIR . 'includes/class-pfe-' . $kebab . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once PFE_DIR . 'admin/class-pfe-admin.php';
require_once PFE_DIR . 'admin/class-pfe-admin-page.php';

register_activation_hook(__FILE__, function (): void {
    (new PopupFormEngine\Installer())->run();
});

register_deactivation_hook(__FILE__, function (): void {
    // data is preserved on deactivate
});

add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('popup-form-engine', false, dirname(plugin_basename(__FILE__)) . '/languages');
    PopupFormEngine\Plugin::getInstance();
});
