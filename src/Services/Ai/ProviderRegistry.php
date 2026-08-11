<?php
/**
 * WordPress AI connectors available to the assistant.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai;

use ASDevs\AIAssistant\Services\Ai\Connectors\WordPressConnectorProvider;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the connectors the site can use and hands out the selected one.
 *
 * Providers come from the WordPress AI Client registry (Settings → Connectors),
 * not from credentials stored by this plugin. Discovery waits until after
 * `init` so official provider plugins have registered themselves.
 */
final class ProviderRegistry {

	/**
	 * Providers keyed by id.
	 *
	 * @var array<string, AiProvider>
	 */
	private array $providers = array();

	/**
	 * Whether WordPress connectors have been discovered.
	 *
	 * @var bool
	 */
	private bool $discovered = false;

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
	 * Discover connectors registered with WordPress core AI.
	 *
	 * Provider plugins register on `init` (often priority 5) and core pushes
	 * stored API keys at priority 20. While `init` is still running,
	 * `did_action( 'init' )` is already truthy — discovering then can lock an
	 * empty list for the rest of the request. Wait until `init` has finished.
	 */
	public function discover(): void {
		if (
			$this->discovered
			|| ! did_action( 'init' )
			|| doing_action( 'init' )
			|| ! class_exists( AiClient::class )
		) {
			return;
		}

		$this->discovered = true;

		$registry = AiClient::defaultRegistry();

		foreach ( $registry->getRegisteredProviderIds() as $id ) {
			try {
				$class    = $registry->getProviderClassName( $id );
				$metadata = $class::metadata();
				$label    = $metadata->getName();

				$this->add(
					new WordPressConnectorProvider(
						$id,
						'' !== $label ? $label : ucwords( str_replace( array( '-', '_' ), ' ', $id ) )
					)
				);
			} catch ( \Throwable $error ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only breadcrumb when a connector fails to load.
					error_log( 'ASDevs AI Assistant connector discovery skipped "' . $id . '": ' . $error->getMessage() );
				}
			}
		}
	}

	/**
	 * Short technical explanation when {@see is_ready()} is false.
	 */
	public function not_ready_detail(): string {
		$this->discover();

		if ( ! class_exists( AiClient::class ) ) {
			return 'WordPress AI Client (AiClient) is not available.';
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return 'wp_ai_client_prompt() is missing; WordPress AI support is not loaded.';
		}

		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return 'wp_supports_ai() is false for this request (WP_AI_SUPPORT or wp_supports_ai filter).';
		}

		$ids = array_keys( $this->providers );

		if ( array() === $ids ) {
			return 'No AI provider plugins are registered with AiClient::defaultRegistry(). Install and activate a provider under Settings → Connectors.';
		}

		$preferred = $this->settings->provider();
		$bits      = array(
			'registered=' . implode( ',', $ids ),
			'preferred=' . ( '' !== $preferred ? $preferred : '(none)' ),
		);

		foreach ( $this->providers as $provider ) {
			$bits[] = $provider->id() . ':' . ( $provider->is_configured() ? 'credentials' : 'no-credentials' );
		}

		return implode( '; ', $bits );
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
		$this->discover();

		return $this->providers;
	}

	/**
	 * The provider the site is configured to use, when it is usable.
	 */
	public function selected(): ?AiProvider {
		$this->discover();

		$preferred = $this->settings->provider();

		if ( '' !== $preferred ) {
			$provider = $this->providers[ $preferred ] ?? null;

			if ( null !== $provider && $provider->is_configured() ) {
				return $provider;
			}
		}

		// If nothing was saved, use the first configured connector so setup
		// can be "install a connector and go".
		foreach ( $this->providers as $provider ) {
			if ( $provider->is_configured() ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Whether the assistant can answer at all.
	 */
	public function is_ready(): bool {
		return null !== $this->selected();
	}
}
