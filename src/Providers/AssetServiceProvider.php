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
	 * Note: WordPress adds `?ver=` to this URL. Deferred agent chunks must not
	 * import `./main.js` (Vite `manualChunks` keeps shared code elsewhere), or
	 * the browser would load a second module instance and remount the widget.
	 * `main.ts` also refuses a second mount as a safety net.
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
	 *
	 * Includes a fixed launcher placeholder with the dual-ring spinner so the
	 * FAB is visible while the module bundle downloads and Vue mounts.
	 */
	public function render_root(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		$label = esc_attr__( 'Loading the assistant', 'asdevs-ai-assistant' );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup is static; label is escaped above.
		echo <<<HTML
<div id="asdevs-ai-assistant-root">
	<style>
		#asdevs-ai-assistant-root .asdevs-ai-launcher-boot{
			position:fixed;inset-block-end:24px;inset-inline-end:24px;z-index:99998;
			display:flex;align-items:center;justify-content:center;
			inline-size:52px;block-size:52px;padding:0;border:0;border-radius:0;
			background:transparent;color:#1f6b4f;cursor:wait;box-shadow:none;
		}
		#asdevs-ai-assistant-root .asdevs-ai-launcher-boot .asdevs-ai-svg-loader{display:block}
	</style>
	<button type="button" class="asdevs-ai-launcher-boot" aria-label="{$label}" aria-busy="true" disabled>
		<svg class="asdevs-ai-svg-loader" version="1.1" xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 80 80" xml:space="preserve" aria-hidden="true" focusable="false">
			<path fill="currentColor" d="M10,40c0,0,0-0.4,0-1.1c0-0.3,0-0.8,0-1.3c0-0.3,0-0.5,0-0.8c0-0.3,0.1-0.6,0.1-0.9c0.1-0.6,0.1-1.4,0.2-2.1c0.2-0.8,0.3-1.6,0.5-2.5c0.2-0.9,0.6-1.8,0.8-2.8c0.3-1,0.8-1.9,1.2-3c0.5-1,1.1-2,1.7-3.1c0.7-1,1.4-2.1,2.2-3.1c1.6-2.1,3.7-3.9,6-5.6c2.3-1.7,5-3,7.9-4.1c0.7-0.2,1.5-0.4,2.2-0.7c0.7-0.3,1.5-0.3,2.3-0.5c0.8-0.2,1.5-0.3,2.3-0.4l1.2-0.1l0.6-0.1l0.3,0l0.1,0l0.1,0l0,0c0.1,0-0.1,0,0.1,0c1.5,0,2.9-0.1,4.5,0.2c0.8,0.1,1.6,0.1,2.4,0.3c0.8,0.2,1.5,0.3,2.3,0.5c3,0.8,5.9,2,8.5,3.6c2.6,1.6,4.9,3.4,6.8,5.4c1,1,1.8,2.1,2.7,3.1c0.8,1.1,1.5,2.1,2.1,3.2c0.6,1.1,1.2,2.1,1.6,3.1c0.4,1,0.9,2,1.2,3c0.3,1,0.6,1.9,0.8,2.7c0.2,0.9,0.3,1.6,0.5,2.4c0.1,0.4,0.1,0.7,0.2,1c0,0.3,0.1,0.6,0.1,0.9c0.1,0.6,0.1,1,0.1,1.4C74,39.6,74,40,74,40c0.2,2.2-1.5,4.1-3.7,4.3s-4.1-1.5-4.3-3.7c0-0.1,0-0.2,0-0.3l0-0.4c0,0,0-0.3,0-0.9c0-0.3,0-0.7,0-1.1c0-0.2,0-0.5,0-0.7c0-0.2-0.1-0.5-0.1-0.8c-0.1-0.6-0.1-1.2-0.2-1.9c-0.1-0.7-0.3-1.4-0.4-2.2c-0.2-0.8-0.5-1.6-0.7-2.4c-0.3-0.8-0.7-1.7-1.1-2.6c-0.5-0.9-0.9-1.8-1.5-2.7c-0.6-0.9-1.2-1.8-1.9-2.7c-1.4-1.8-3.2-3.4-5.2-4.9c-2-1.5-4.4-2.7-6.9-3.6c-0.6-0.2-1.3-0.4-1.9-0.6c-0.7-0.2-1.3-0.3-1.9-0.4c-1.2-0.3-2.8-0.4-4.2-0.5l-2,0c-0.7,0-1.4,0.1-2.1,0.1c-0.7,0.1-1.4,0.1-2,0.3c-0.7,0.1-1.3,0.3-2,0.4c-2.6,0.7-5.2,1.7-7.5,3.1c-2.2,1.4-4.3,2.9-6,4.7c-0.9,0.8-1.6,1.8-2.4,2.7c-0.7,0.9-1.3,1.9-1.9,2.8c-0.5,1-1,1.9-1.4,2.8c-0.4,0.9-0.8,1.8-1,2.6c-0.3,0.9-0.5,1.6-0.7,2.4c-0.2,0.7-0.3,1.4-0.4,2.1c-0.1,0.3-0.1,0.6-0.2,0.9c0,0.3-0.1,0.6-0.1,0.8c0,0.5-0.1,0.9-0.1,1.3C10,39.6,10,40,10,40z"><animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 40 40" to="360 40 40" dur="0.8s" repeatCount="indefinite"/></path>
			<path fill="currentColor" d="M62,40.1c0,0,0,0.2-0.1,0.7c0,0.2,0,0.5-0.1,0.8c0,0.2,0,0.3,0,0.5c0,0.2-0.1,0.4-0.1,0.7c-0.1,0.5-0.2,1-0.3,1.6c-0.2,0.5-0.3,1.1-0.5,1.8c-0.2,0.6-0.5,1.3-0.7,1.9c-0.3,0.7-0.7,1.3-1,2.1c-0.4,0.7-0.9,1.4-1.4,2.1c-0.5,0.7-1.1,1.4-1.7,2c-1.2,1.3-2.7,2.5-4.4,3.6c-1.7,1-3.6,1.8-5.5,2.4c-2,0.5-4,0.7-6.2,0.7c-1.9-0.1-4.1-0.4-6-1.1c-1.9-0.7-3.7-1.5-5.2-2.6c-1.5-1.1-2.9-2.3-4-3.7c-0.6-0.6-1-1.4-1.5-2c-0.4-0.7-0.8-1.4-1.2-2c-0.3-0.7-0.6-1.3-0.8-2c-0.2-0.6-0.4-1.2-0.6-1.8c-0.1-0.6-0.3-1.1-0.4-1.6c-0.1-0.5-0.1-1-0.2-1.4c-0.1-0.9-0.1-1.5-0.1-2c0-0.5,0-0.7,0-0.7s0,0.2,0.1,0.7c0.1,0.5,0,1.1,0.2,2c0.1,0.4,0.2,0.9,0.3,1.4c0.1,0.5,0.3,1,0.5,1.6c0.2,0.6,0.4,1.1,0.7,1.8c0.3,0.6,0.6,1.2,0.9,1.9c0.4,0.6,0.8,1.3,1.2,1.9c0.5,0.6,1,1.3,1.6,1.8c1.1,1.2,2.5,2.3,4,3.2c1.5,0.9,3.2,1.6,5,2.1c1.8,0.5,3.6,0.6,5.6,0.6c1.8-0.1,3.7-0.4,5.4-1c1.7-0.6,3.3-1.4,4.7-2.4c1.4-1,2.6-2.1,3.6-3.3c0.5-0.6,0.9-1.2,1.3-1.8c0.4-0.6,0.7-1.2,1-1.8c0.3-0.6,0.6-1.2,0.8-1.8c0.2-0.6,0.4-1.1,0.5-1.7c0.1-0.5,0.2-1,0.3-1.5c0.1-0.4,0.1-0.8,0.1-1.2c0-0.2,0-0.4,0.1-0.5c0-0.2,0-0.4,0-0.5c0-0.3,0-0.6,0-0.8c0-0.5,0-0.7,0-0.7c0-1.1,0.9-2,2-2s2,0.9,2,2C62,40,62,40.1,62,40.1z"><animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 40 40" to="-360 40 40" dur="0.6s" repeatCount="indefinite"/></path>
		</svg>
	</button>
</div>
HTML;
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
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
