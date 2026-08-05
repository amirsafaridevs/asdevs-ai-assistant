<?php
/**
 * The conversation endpoint.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Rest;

use ASDevs\AIAssistant\Ai\AiUnavailable;
use ASDevs\AIAssistant\Ai\ChatRequest;
use ASDevs\AIAssistant\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Ai\SystemPrompt;
use ASDevs\AIAssistant\Ai\ToolCatalog;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams one assistant turn to the browser.
 *
 * The AI service is reached from here, never from the browser, so the key
 * stays on the server (section 27.3). Answers are streamed as they arrive so
 * the person sees progress rather than silence (section 11.3).
 */
final class ChatController extends Controller {

	/**
	 * Providers.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Instructions.
	 *
	 * @var SystemPrompt
	 */
	private SystemPrompt $prompt;

	/**
	 * Tools.
	 *
	 * @var ToolCatalog
	 */
	private ToolCatalog $tools;

	/**
	 * Constructor.
	 *
	 * @param ProviderRegistry $providers Providers.
	 * @param SystemPrompt     $prompt    Instructions.
	 * @param ToolCatalog      $tools     Tools.
	 */
	public function __construct( ProviderRegistry $providers, SystemPrompt $prompt, ToolCatalog $tools ) {
		$this->providers = $providers;
		$this->prompt    = $prompt;
		$this->tools     = $tools;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_Error|null Null once the stream has been written.
	 */
	public function handle( WP_REST_Request $request ) {
		$provider = $this->providers->selected();

		if ( null === $provider ) {
			return new WP_Error(
				'asdevs_ai_not_configured',
				__( 'The assistant needs an AI service before it can work.', 'asdevs-ai-assistant' ),
				array( 'status' => 409 )
			);
		}

		$messages = $this->messages_from( $request );

		if ( array() === $messages ) {
			return new WP_Error(
				'asdevs_ai_empty',
				__( 'There is nothing to answer yet.', 'asdevs-ai-assistant' ),
				array( 'status' => 400 )
			);
		}

		$chat = new ChatRequest(
			$this->prompt->build( $this->array_param( $request, 'page' ) ),
			$messages,
			$this->tools->definitions()
		);

		$this->open_stream();

		try {
			$provider->stream(
				$chat,
				function ( array $event ): void {
					$this->emit( $event );
				}
			);
		} catch ( AiUnavailable $error ) {
			$this->emit(
				array(
					'type'      => 'error',
					'message'   => $error->getMessage(),
					'detail'    => $error->detail(),
					'retryable' => $error->is_retryable(),
				)
			);
		}

		$this->emit( array( 'type' => 'end' ) );

		exit;
	}

	/**
	 * Sanitize the conversation coming from the browser.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function messages_from( WP_REST_Request $request ): array {
		$messages = array();

		foreach ( $this->array_param( $request, 'messages' ) as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = isset( $message['role'] ) ? (string) $message['role'] : '';

			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) {
				continue;
			}

			$content = $message['content'] ?? '';

			if ( is_string( $content ) ) {
				$content = array(
					array(
						'type' => 'text',
						'text' => $content,
					),
				);
			}

			if ( ! is_array( $content ) || array() === $content ) {
				continue;
			}

			$messages[] = array(
				'role'    => $role,
				'content' => array_values( $content ),
			);
		}

		return $messages;
	}

	/**
	 * Start a server-sent event stream.
	 */
	private function open_stream(): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
			header( 'Connection: keep-alive' );
			header( 'X-Accel-Buffering: no' );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
	}

	/**
	 * Write one event to the stream.
	 *
	 * @param array<string, mixed> $event The event.
	 */
	private function emit( array $event ): void {
		echo 'data: ' . wp_json_encode( $event ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded for a text/event-stream body, not HTML.

		flush();
	}
}
