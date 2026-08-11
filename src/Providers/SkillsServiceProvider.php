<?php
/**
 * Skill services.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Providers;

use ASDevs\AIAssistant\Core\ServiceProvider;
use ASDevs\AIAssistant\Services\Skills\SkillPostType;
use ASDevs\AIAssistant\Services\Skills\SkillStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the skill CPT and store.
 */
final class SkillsServiceProvider extends ServiceProvider {

	/**
	 * Bind services.
	 */
	public function register(): void {
		$this->container->singleton( SkillPostType::class, static fn() => new SkillPostType() );
		$this->container->singleton( SkillStore::class, static fn() => new SkillStore() );
	}

	/**
	 * Wire to WordPress.
	 */
	public function boot(): void {
		add_action(
			'init',
			function (): void {
				$this->container->get( SkillPostType::class )->register();
			}
		);
	}
}
