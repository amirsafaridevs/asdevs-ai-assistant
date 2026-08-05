<?php
/**
 * Shared streaming HTTP behaviour for providers.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Ai\Providers;

use ASDevs\AIAssistant\Ai\AiProvider;
use ASDevs\AIAssistant\Ai\AiUnavailable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to a server-sent-events endpoint and hands parsed events upward.
 *
 * The API key stays in this process. It is never part of any response the
 * browser receives (section 23.2).
 */
abstract class StreamingHttpProvider implements AiProvider {

	/**
	 * Seconds to wait for the service.
	 */
	protected const TIMEOUT = 120;

	/**
	 * POST a JSON body and parse the SSE response.
	 *
	 * @param string                $url     Endpoint.
	 * @param array<string, string> $headers Request headers.
	 * @param array<string, mixed>  $body    JSON body.
	 * @param callable              $on_data Receives each decoded SSE data payload.
	 *
	 * @throws AiUnavailable When the service cannot answer.
	 */
	protected function post_sse( string $url, array $headers, array $body, callable $on_data ): void {
		if ( ! function_exists( 'curl_init' ) ) {
			$this->post_blocking( $url, $headers, $body, $on_data );

			return;
		}

		$buffer = '';
		$status = 0;
		$error  = '';

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close -- The HTTP API cannot deliver a response incrementally, and the product requires progressive answers (section 11.3).
		$handle = curl_init( $url );

		curl_setopt( $handle, CURLOPT_POST, true );
		curl_setopt( $handle, CURLOPT_POSTFIELDS, (string) wp_json_encode( $body ) );
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $this->header_lines( $headers ) );
		curl_setopt( $handle, CURLOPT_TIMEOUT, self::TIMEOUT );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, false );

		if ( $this->skip_ssl_verify() ) {
			curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, 0 );
		} else {
			$bundle = $this->ca_bundle();

			if ( '' !== $bundle ) {
				curl_setopt( $handle, CURLOPT_CAINFO, $bundle );
			}
		}

		curl_setopt(
			$handle,
			CURLOPT_WRITEFUNCTION,
			static function ( $resource, string $chunk ) use ( &$buffer, &$status, $on_data, &$error ) {
				unset( $resource );

				$buffer .= $chunk;

				while ( false !== strpos( $buffer, "\n" ) ) {
					$position = strpos( $buffer, "\n" );
					$line     = rtrim( substr( $buffer, 0, $position ), "\r" );
					$buffer   = substr( $buffer, $position + 1 );

					if ( '' === $line || str_starts_with( $line, ':' ) ) {
						continue;
					}

					if ( ! str_starts_with( $line, 'data:' ) ) {
						continue;
					}

					$payload = trim( substr( $line, 5 ) );

					if ( '' === $payload || '[DONE]' === $payload ) {
						continue;
					}

					$decoded = json_decode( $payload, true );

					if ( is_array( $decoded ) ) {
						$on_data( $decoded );
					}
				}

				return strlen( $chunk );
			}
		);

		$ok     = curl_exec( $handle );
		$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		$error  = (string) curl_error( $handle );

		curl_close( $handle );
		// phpcs:enable

		if ( false === $ok && '' !== $error ) {
			throw new AiUnavailable(
				__( 'The AI service is not responding right now. Try again in a moment.', 'asdevs-ai-assistant' ),
				$error
			);
		}

		$this->assert_status( $status );
	}

	/**
	 * Fallback for hosts without cURL: one blocking request, delivered at once.
	 *
	 * @param string                $url     Endpoint.
	 * @param array<string, string> $headers Request headers.
	 * @param array<string, mixed>  $body    JSON body.
	 * @param callable              $on_data Receives each decoded SSE data payload.
	 *
	 * @throws AiUnavailable When the service cannot answer.
	 */
	private function post_blocking( string $url, array $headers, array $body, callable $on_data ): void {
		$body['stream'] = false;

		$response = wp_remote_post(
			$url,
			array(
				'headers'   => array_merge( $headers, array( 'Content-Type' => 'application/json' ) ),
				'body'      => (string) wp_json_encode( $body ),
				'timeout'   => self::TIMEOUT,
				'sslverify' => ! $this->skip_ssl_verify(),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new AiUnavailable(
				__( 'The AI service is not responding right now. Try again in a moment.', 'asdevs-ai-assistant' ),
				$response->get_error_message()
			);
		}

		$this->assert_status( (int) wp_remote_retrieve_response_code( $response ) );

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( is_array( $decoded ) ) {
			$on_data( $decoded );
		}
	}

	/**
	 * Whether certificate verification should be skipped.
	 *
	 * Local stacks (WAMP, XAMPP, MAMP) often ship without a usable CA store,
	 * which makes every call fail with "unable to get local issuer
	 * certificate". Define ASDEVS_AI_ASSISTANT_DISABLE_SSL_VERIFY in
	 * wp-config.php to trade verification away on such a machine. Filterable so
	 * a site can decide per request.
	 */
	private function skip_ssl_verify(): bool {
		$skip = defined( 'ASDEVS_AI_ASSISTANT_DISABLE_SSL_VERIFY' ) && ASDEVS_AI_ASSISTANT_DISABLE_SSL_VERIFY;

		/**
		 * Filters whether the plugin verifies the AI service certificate.
		 *
		 * @param bool $skip True to skip verification.
		 */
		return (bool) apply_filters( 'asdevs_ai_assistant_disable_ssl_verify', $skip );
	}

	/**
	 * Path to a CA bundle cURL can read, or an empty string when none is known.
	 */
	private function ca_bundle(): string {
		$bundle = ABSPATH . WPINC . '/certificates/ca-bundle.crt';

		/**
		 * Filters the CA bundle used for AI service calls.
		 *
		 * @param string $bundle Absolute path to a PEM bundle.
		 */
		$bundle = (string) apply_filters( 'asdevs_ai_assistant_ca_bundle', $bundle );

		return is_readable( $bundle ) ? $bundle : '';
	}

	/**
	 * Turn an HTTP status into a message the user can read.
	 *
	 * @param int $status HTTP status code.
	 *
	 * @throws AiUnavailable When the status is not a success.
	 */
	private function assert_status( int $status ): void {
		if ( $status >= 200 && $status < 300 ) {
			return;
		}

		if ( 401 === $status || 403 === $status ) {
			throw new AiUnavailable(
				__( 'The AI service refused the connection. Check the service settings once and I will carry on.', 'asdevs-ai-assistant' ),
				'HTTP ' . $status,
				false
			);
		}

		if ( 429 === $status ) {
			throw new AiUnavailable(
				__( 'The AI service is busy. Try again in a moment.', 'asdevs-ai-assistant' ),
				'HTTP ' . $status
			);
		}

		throw new AiUnavailable(
			__( 'The AI service is not responding right now. Try again in a moment.', 'asdevs-ai-assistant' ),
			'HTTP ' . $status
		);
	}

	/**
	 * Header map to header lines.
	 *
	 * @param array<string, string> $headers Header map.
	 *
	 * @return string[]
	 */
	private function header_lines( array $headers ): array {
		$lines = array( 'Content-Type: application/json', 'Accept: text/event-stream' );

		foreach ( $headers as $name => $value ) {
			$lines[] = $name . ': ' . $value;
		}

		return $lines;
	}
}
