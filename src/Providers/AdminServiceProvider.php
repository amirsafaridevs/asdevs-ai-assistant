<?php
/**
 * Admin screens.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Admin\SettingsPage;
use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\Settings;
use ASDevs\AIAssistant\Core\Container;
use ASDevs\AIAssistant\Core\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the top-level AI Assistant admin screen.
 */
final class AdminServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton(
			SettingsPage::class,
			static fn( Container $container ) => new SettingsPage(
				$container->get( Settings::class ),
				$container->get( ProviderRegistry::class )
			)
		);
	}

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		if ( ! is_admin() ) {
			return;
		}

		$page = $this->container->get( SettingsPage::class );

		add_action( 'admin_menu', array( $page, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $page, 'enqueue_assets' ) );
		add_action( 'admin_post_asdevs_ai_assistant_save_settings', array( $page, 'handle_save' ) );
	}
}
