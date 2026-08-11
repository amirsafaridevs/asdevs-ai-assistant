<?php
/**
 * A single action the assistant wants to perform.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable description of one REST call the assistant intends to make.
 */
final class ActionRequest {

	/**
	 * HTTP method, upper case.
	 *
	 * @var string
	 */
	private string $method;

	/**
	 * REST route, e.g. /wp/v2/posts/12.
	 *
	 * @var string
	 */
	private string $route;

	/**
	 * Request parameters.
	 *
	 * @var array<string, mixed>
	 */
	private array $params;

	/**
	 * Constructor.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  REST route.
	 * @param array<string, mixed> $params Request parameters.
	 */
	public function __construct( string $method, string $route, array $params = array() ) {
		$this->method = strtoupper( trim( $method ) );
		$this->route  = '/' . ltrim( trim( $route ), '/' );
		$this->params = $params;
	}

	/**
	 * Build from an already sanitized request payload.
	 *
	 * @param array<string, mixed> $payload Payload with method, route and params keys.
	 */
	public static function from_payload( array $payload ): self {
		$params = isset( $payload['params'] ) && is_array( $payload['params'] ) ? $payload['params'] : array();

		return new self(
			isset( $payload['method'] ) ? (string) $payload['method'] : 'GET',
			isset( $payload['route'] ) ? (string) $payload['route'] : '/',
			$params
		);
	}

	/**
	 * HTTP method.
	 */
	public function method(): string {
		return $this->method;
	}

	/**
	 * REST route.
	 */
	public function route(): string {
		return $this->route;
	}

	/**
	 * All parameters.
	 *
	 * @return array<string, mixed>
	 */
	public function params(): array {
		return $this->params;
	}

	/**
	 * A single parameter.
	 *
	 * @param string $key     Parameter name.
	 * @param mixed  $default Fallback when absent.
	 *
	 * @return mixed
	 */
	public function param( string $key, $default = null ) {
		return $this->params[ $key ] ?? $default;
	}

	/**
	 * Whether the method only reads.
	 */
	public function is_read(): bool {
		return in_array( $this->method, array( 'GET', 'HEAD', 'OPTIONS' ), true );
	}

	/**
	 * The route with numeric and slug ids stripped, for pattern matching.
	 *
	 * /wp/v2/posts/12 becomes /wp/v2/posts.
	 */
	public function base_route(): string {
		return (string) preg_replace( '#/[^/]*\d[^/]*$#', '', $this->route );
	}

	/**
	 * A stable fingerprint of this action, used to bind confirmations.
	 */
	public function fingerprint(): string {
		$params = $this->params;
		ksort( $params );

		return hash( 'sha256', wp_json_encode( array( $this->method, $this->route, $params ) ) );
	}
}
