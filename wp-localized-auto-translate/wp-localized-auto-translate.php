<?php
/**
 * Plugin Name: WP Localized Auto Translate
 * Description: Auto-translates frontend content based on browser locale using LLM providers with locale-specific style guidance.
 * Version: 1.0.0
 * Author: Codex
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: wp-localized-auto-translate
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WPLAT_VERSION', '1.0.0');
define('WPLAT_PLUGIN_FILE', __FILE__);
define('WPLAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPLAT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WPLAT_PLUGIN_DIR . 'includes/class-wplat-plugin.php';

\WPLAT\Plugin::instance()->boot();
