<?php
/**
 * Plugin Name:       ASDevs AI Assistant
 * Description:       A colleague inside your WordPress admin. It discovers what your site can actually do and does it for you.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            amirsafaridevs
 * Author URI:        https://profiles.wordpress.org/amirsafaridevs/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       asdevs-ai-assistant
 * Domain Path:       /languages
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the plugin version.
 *
 * build-release.sh asserts this matches the Version header above.
 */
const VERSION = '1.0.0';

define( 'ASDEVS_AI_ASSISTANT_VERSION', VERSION );
define( 'ASDEVS_AI_ASSISTANT_FILE', __FILE__ );
define( 'ASDEVS_AI_ASSISTANT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASDEVS_AI_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );
define( 'ASDEVS_AI_ASSISTANT_SLUG', 'asdevs-ai-assistant' );

$asdevs_ai_assistant_autoloader = ASDEVS_AI_ASSISTANT_DIR . 'vendor/autoload.php';

if ( ! is_readable( $asdevs_ai_assistant_autoloader ) ) {
	return;
}

require_once $asdevs_ai_assistant_autoloader;

/**
 * Boot the plugin.
 *
 * The main file is a bootstrap only: no product logic lives here.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		global $wp_version;

		if ( version_compare( (string) $wp_version, '7.0', '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'ASDevs AI Assistant requires WordPress 7.0 or later so it can use core AI connectors.', 'asdevs-ai-assistant' );
					echo '</p></div>';
				}
			);

			return;
		}

		Core\Plugin::instance()->boot();
	},
	5
);
