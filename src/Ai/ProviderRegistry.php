<?php
/**
 * Known AI providers.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the providers the site can use and hands out the selected one.
 */
final class ProviderRegistry {

	/**
	 * Providers keyed by id.
	 *
	 * @var array<string, AiProvider>
	 */
	private array $providers = array();

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Add a provider.
	 *
	 * @param AiProvider $provider The provider.
	 */
	public function add( AiProvider $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	/**
	 * Every registered provider.
	 *
	 * @return array<string, AiProvider>
	 */
	public function all(): array {
		return $this->providers;
	}

	/**
	 * The provider the site is configured to use, when it is usable.
	 */
	public function selected(): ?AiProvider {
		$provider = $this->providers[ $this->settings->provider() ] ?? null;

		if ( null === $provider || ! $provider->is_configured() ) {
			return null;
		}

		return $provider;
	}

	/**
	 * Whether the assistant can answer at all.
	 */
	public function is_ready(): bool {
		return null !== $this->selected();
	}
}
