<?php
/**
 * AI connector preference.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores which WordPress AI connector the assistant should use.
 *
 * API keys live in Settings → Connectors (WordPress core). This option only
 * records the site's preferred connector id.
 */
final class Settings {

	/**
	 * Option name.
	 */
	private const OPTION = 'asdevs_ai_assistant_service';

	/**
	 * Stored settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $memo = null;

	/**
	 * All settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->memo ) {
			return $this->memo;
		}

		$stored = get_option( self::OPTION, array() );

		$this->memo = wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'provider' => '',
			)
		);

		// Drop legacy fields (keys, model, thinking) from the in-memory view.
		$this->memo = array(
			'provider' => sanitize_key( (string) $this->memo['provider'] ),
		);

		return $this->memo;
	}

	/**
	 * The selected WordPress connector id.
	 */
	public function provider(): string {
		return (string) $this->all()['provider'];
	}

	/**
	 * Save the preferred connector.
	 *
	 * @param string $provider Connector / provider id from the WordPress AI Client registry.
	 */
	public function save( string $provider ): void {
		update_option(
			self::OPTION,
			array(
				'provider' => sanitize_key( $provider ),
			),
			false
		);

		$this->memo = null;
	}

	/**
	 * Delete every stored setting.
	 */
	public function delete(): void {
		delete_option( self::OPTION );

		$this->memo = null;
	}
}
