<?php
/**
 * Front-end assets.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Services\Context\PageContext;
use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Services\Legal\Terms;
use ASDevs\AIAssistant\Http\Controllers\Controller;

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
		add_filter( 'script_loader_tag', array( $this, 'as_module' ), 10, 2 );
	}

	/**
	 * Serve the widget as an ES module.
	 *
	 * The bundle is a module so the agent engine can live in a separate chunk
	 * that is only fetched when someone opens the assistant. A classic script
	 * cannot be code-split, and cannot use the dynamic import that defers it.
	 *
	 * Only the tag that loads the file may become a module. WordPress hands this
	 * filter the translations and the inline boot data in the same string, and
	 * those must stay classic scripts: module scope is not global, so
	 * `window.asdevsAiAssistant` would never be set.
	 *
	 * Modules are deferred by the browser, so the inline data still runs first.
	 *
	 * @param string $tag    The script tag, plus any inline scripts around it.
	 * @param string $handle Script handle.
	 */
	public function as_module( string $tag, string $handle ): string {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		$pattern = '#<script\b[^>]*\bsrc=[\'"][^\'"]*assets/dist/main\.js[^\'"]*[\'"][^>]*>#i';

		return (string) preg_replace_callback(
			$pattern,
			static function ( array $matches ): string {
				$element = $matches[0];

				if ( false !== stripos( $element, ' type="module"' ) ) {
					return $element;
				}

				$typed = preg_replace( '#\stype=([\'"])[^\'"]*\1#i', ' type="module"', $element, 1, $count );

				if ( $count > 0 && is_string( $typed ) ) {
					return $typed;
				}

				return (string) preg_replace( '#^<script\b#i', '<script type="module"', $element, 1 );
			},
			$tag,
			1
		);
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
