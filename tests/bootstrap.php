<?php
/**
 * Test bootstrap.
 *
 * The boundary layer is deliberately free of WordPress internals so it can be
 * tested directly. The few WordPress functions it does touch are stubbed here.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMPTY_TRASH_DAYS', 30 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ASDEVS_AI_ASSISTANT_VERSION', '1.0.0' );

$GLOBALS['asdevs_ai_test_transients'] = array();
$GLOBALS['asdevs_ai_test_post_types'] = array();
$GLOBALS['asdevs_ai_test_taxonomies'] = array();

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Test doubles for WordPress core.

/**
 * Stands in for the core post type object.
 */
class WP_Post_Type { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	public string $rest_base = '';

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public string $rest_namespace = 'wp/v2';
}

/**
 * Stands in for the core taxonomy object.
 */
class WP_Taxonomy { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	public string $rest_base = '';

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public string $rest_namespace = 'wp/v2';
}

/**
 * Register a fake post type for a test.
 *
 * @param string $name      Post type name.
 * @param string $rest_base REST base.
 */
function asdevs_ai_test_register_post_type( string $name, string $rest_base ): void {
	$type            = new WP_Post_Type();
	$type->name      = $name;
	$type->rest_base = $rest_base;

	$GLOBALS['asdevs_ai_test_post_types'][ $name ] = $type;
}

/**
 * Reset state between tests.
 */
function asdevs_ai_test_reset(): void {
	$GLOBALS['asdevs_ai_test_transients'] = array();
	$GLOBALS['asdevs_ai_test_post_types'] = array();
	$GLOBALS['asdevs_ai_test_taxonomies'] = array();
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- These stand in for WordPress itself.

/**
 * Stub.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 */
function __( string $text, string $domain = 'default' ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
	unset( $domain );

	return $text;
}

/**
 * Stub.
 *
 * @param string $text Text.
 */
function esc_html( string $text ): string {
	return $text;
}

/**
 * Stub.
 *
 * @param string $value Value.
 */
function untrailingslashit( string $value ): string {
	return rtrim( $value, '/\\' );
}

/**
 * Stub.
 *
 * @param mixed $data Data.
 */
function wp_json_encode( $data ) {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}

/**
 * Stub.
 *
 * @param int|float $number Number.
 */
function number_format_i18n( $number ): string {
	return (string) $number;
}

/**
 * Stub.
 *
 * @param array<string, mixed> $args   Query.
 * @param string               $output Output type.
 */
function get_post_types( array $args = array(), string $output = 'names' ): array {
	unset( $args, $output );

	return $GLOBALS['asdevs_ai_test_post_types'];
}

/**
 * Stub.
 *
 * @param array<string, mixed> $args   Query.
 * @param string               $output Output type.
 */
function get_taxonomies( array $args = array(), string $output = 'names' ): array {
	unset( $args, $output );

	return $GLOBALS['asdevs_ai_test_taxonomies'];
}

/**
 * Stub.
 *
 * @param string $key Key.
 *
 * @return mixed
 */
function get_transient( string $key ) {
	return $GLOBALS['asdevs_ai_test_transients'][ $key ] ?? false;
}

/**
 * Stub.
 *
 * @param string $key        Key.
 * @param mixed  $value      Value.
 * @param int    $expiration Seconds.
 */
function set_transient( string $key, $value, int $expiration = 0 ): bool {
	unset( $expiration );

	$GLOBALS['asdevs_ai_test_transients'][ $key ] = $value;

	return true;
}

/**
 * Stub.
 *
 * @param string $key Key.
 */
function delete_transient( string $key ): bool {
	unset( $GLOBALS['asdevs_ai_test_transients'][ $key ] );

	return true;
}

/**
 * Stub.
 *
 * @param int  $length              Length.
 * @param bool $special_chars       Unused.
 * @param bool $extra_special_chars Unused.
 */
function wp_generate_password( int $length = 12, bool $special_chars = true, bool $extra_special_chars = false ): string {
	unset( $special_chars, $extra_special_chars );

	return substr( bin2hex( random_bytes( $length ) ), 0, $length );
}

/**
 * Stub.
 *
 * @param string $hook_name Hook.
 * @param mixed  $value     Value.
 *
 * @return mixed
 */
function apply_filters( string $hook_name, $value ) {
	unset( $hook_name );

	return $value;
}

/**
 * Strip tags the way core does, for the parts that only need the text.
 *
 * @param string $text Raw text.
 */
function wp_strip_all_tags( string $text ): string {
	$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );

	return trim( strip_tags( $text ) );
}

// phpcs:enable

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'ASDevs\\AIAssistant\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$path = __DIR__ . '/../src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
