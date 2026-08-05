<?php
/**
 * AI service settings.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores which AI service to use and its credentials.
 *
 * The key is written but never read back out to the browser: settings
 * responses report only whether a key is present (section 23.2).
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
				'provider' => 'anthropic',
				'model'    => '',
				'keys'     => array(),
			)
		);

		return $this->memo;
	}

	/**
	 * The selected provider id.
	 */
	public function provider(): string {
		return (string) $this->all()['provider'];
	}

	/**
	 * The selected model, or an empty string for the provider default.
	 */
	public function model(): string {
		return (string) $this->all()['model'];
	}

	/**
	 * The stored key for a provider.
	 *
	 * @param string $provider Provider id.
	 */
	public function key_for( string $provider ): string {
		$keys = $this->all()['keys'];

		return is_array( $keys ) && isset( $keys[ $provider ] ) ? (string) $keys[ $provider ] : '';
	}

	/**
	 * Save settings.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id, may be empty.
	 * @param string $key      API key; an empty string keeps the stored one.
	 */
	public function save( string $provider, string $model, string $key ): void {
		$settings = $this->all();

		$settings['provider'] = sanitize_key( $provider );
		$settings['model']    = sanitize_text_field( $model );

		if ( '' !== $key ) {
			$keys = is_array( $settings['keys'] ) ? $settings['keys'] : array();

			$keys[ $settings['provider'] ] = $key;
			$settings['keys']              = $keys;
		}

		update_option( self::OPTION, $settings, false );

		$this->memo = null;
	}

	/**
	 * Remove the stored key for a provider.
	 *
	 * @param string $provider Provider id.
	 */
	public function forget_key( string $provider ): void {
		$settings = $this->all();
		$keys     = is_array( $settings['keys'] ) ? $settings['keys'] : array();

		unset( $keys[ sanitize_key( $provider ) ] );

		$settings['keys'] = $keys;

		update_option( self::OPTION, $settings, false );

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
