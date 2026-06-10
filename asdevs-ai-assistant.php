<?php
/**
 * Plugin Name: ASDevs AI Assistant
 * Plugin URI: https://amirsafaridev.github.io/
 * Description: A floating AI assistant for WordPress admin that guides users through settings, menus, and configuration. Read-only GPS for your WordPress site.
 * Version: 1.0.0
 * Author: Amir Safari
 * Author URI: https://amirsafaridev.github.io/
 * License: GPL-2.0-or-later
 * Text Domain: asdevs-ai-assistant
 * Requires PHP: 8.2
 * Requires at least: 6.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ASDEVS_AI_ASSISTANT_VERSION', '1.0.0');
define('ASDEVS_AI_ASSISTANT_FILE', __FILE__);
define('ASDEVS_AI_ASSISTANT_DIR', plugin_dir_path(__FILE__));
define('ASDEVS_AI_ASSISTANT_URL', plugin_dir_url(__FILE__));
define('ASDEVS_AI_ASSISTANT_SRC_DIR', ASDEVS_AI_ASSISTANT_DIR . 'src/');
define('ASDEVS_AI_ASSISTANT_ASSETS_DIR', ASDEVS_AI_ASSISTANT_DIR . 'assets/');
define('ASDEVS_AI_ASSISTANT_ASSETS_URL', ASDEVS_AI_ASSISTANT_URL . 'assets/');
define('ASDEVS_AI_ASSISTANT_DIST_URL', ASDEVS_AI_ASSISTANT_ASSETS_URL . 'dist/');

// Autoloader
require_once ASDEVS_AI_ASSISTANT_DIR . 'vendor/autoload.php';

// Bootstrap the plugin
\ASDevs\AIAssistant\App::instance()->boot();
