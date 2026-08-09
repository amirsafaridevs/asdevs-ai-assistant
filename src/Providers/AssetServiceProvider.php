<?php
/**
 * Front-end assets.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Context\PageContext;
use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Legal\Terms;
use ASDevs\AIAssistant\Rest\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Puts the assistant on every admin screen, and nowhere else.
 *
 * The widget is the only visible change activation makes: no welcome page, no
 * notice, no redirect (section 7.1).
 */
final class AssetServiceProvider extends ServiceProvider {

	/**
	 * Script and style handle.
	 */
	private const HANDLE = 'asdevs-ai-assistant';

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer', array( $this, 'render_root' ) );
	}

	/**
	 * Enqueue the widget.
	 */
	public function enqueue(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$script = ASDEVS_AI_ASSISTANT_DIR . 'assets/dist/main.js';
		$style  = ASDEVS_AI_ASSISTANT_DIR . 'assets/dist/main.css';

		if ( ! is_readable( $script ) ) {
			return;
		}

		$version = (string) filemtime( $script );

		wp_enqueue_script(
			self::HANDLE,
			ASDEVS_AI_ASSISTANT_URL . 'assets/dist/main.js',
			array( 'wp-i18n' ),
			$version,
			true
		);

		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				self::HANDLE,
				ASDEVS_AI_ASSISTANT_URL . 'assets/dist/main.css',
				array(),
				(string) filemtime( $style )
			);
		}

		wp_set_script_translations( self::HANDLE, 'asdevs-ai-assistant', ASDEVS_AI_ASSISTANT_DIR . 'languages' );

		wp_add_inline_script(
			self::HANDLE,
			'window.asdevsAiAssistant = ' . wp_json_encode( $this->boot_data() ) . ';',
			'before'
		);
	}

	/**
	 * The root element the widget mounts into.
	 */
	public function render_root(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		echo '<div id="asdevs-ai-assistant-root"></div>';
	}

	/**
	 * Data the widget needs before its first request.
	 *
	 * @return array<string, mixed>
	 */
	private function boot_data(): array {
		return array(
			'restUrl'  => esc_url_raw( rest_url( Controller::NAMESPACE ) ),
			'siteRest' => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => esc_url_raw( admin_url() ),
			'locale'   => get_user_locale(),
			'isRtl'    => is_rtl(),
			'page'     => $this->container->get( PageContext::class )->current(),
			'terms'    => $this->container->get( Terms::class )->for_user( get_current_user_id() ),
		);
	}

	/**
	 * Whether the widget belongs on this screen.
	 */
	private function should_load(): bool {
		if ( ! is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		/**
		 * Filter whether the assistant appears on the current screen.
		 *
		 * @param bool $should_load Whether to load the widget.
		 */
		return (bool) apply_filters( 'asdevs_ai_assistant_should_load', true );
	}
}
