<?php
/**
 * Terms of use services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Legal\Terms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers terms acceptance storage.
 */
final class LegalServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( Terms::class, static fn() => new Terms() );
	}

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		add_action(
			'delete_user',
			function ( $user_id ): void {
				$this->container->get( Terms::class )->clear( (int) $user_id );
			}
		);
	}
}
