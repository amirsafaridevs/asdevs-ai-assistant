<?php
/**
 * The conversation endpoint.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Ai\AiUnavailable;
use ASDevs\AIAssistant\Services\Ai\AiProvider;
use ASDevs\AIAssistant\Services\Ai\ChatRequest;
use ASDevs\AIAssistant\Services\Ai\ProviderRegistry;
use ASDevs\AIAssistant\Services\Ai\SystemPrompt;
use ASDevs\AIAssistant\Services\Ai\ToolCatalog;
use ASDevs\AIAssistant\Services\Skills\SkillStore;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams one assistant turn to the browser.
 *
 * The WordPress AI Client is reached from here, never from the browser. API
 * keys stay in Settings → Connectors. Answers are streamed as events so the
 * person sees progress rather than silence (section 11.3).
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
	 * Skills.
	 *
	 * @var SkillStore
	 */
	private SkillStore $skills;

	/**
	 * Constructor.
	 *
	 * @param ProviderRegistry $providers Providers.
	 * @param SystemPrompt     $prompt    Instructions.
	 * @param ToolCatalog      $tools     Tools.
	 * @param SkillStore       $skills    Skills.
	 */
	public function __construct( ProviderRegistry $providers, SystemPrompt $prompt, ToolCatalog $tools, SkillStore $skills ) {
		$this->providers = $providers;
		$this->prompt    = $prompt;
		$this->tools     = $tools;
		$this->skills    = $skills;
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
				'permission_callback' => array( $this, 'check_terms_permission' ),
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
				__( 'The assistant needs a configured WordPress AI connector before it can work. Open Settings → Connectors to connect a provider.', 'asdevs-ai-assistant' ),
				array(
					'status' => 409,
					'detail' => $this->providers->not_ready_detail(),
				)
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

		$mode   = $this->mode_from( $request );
		$skills = $this->skills_from( $request );
		$model  = $this->model_from( $request, $provider );

		$chat = new ChatRequest(
			$this->prompt->build( $this->array_param( $request, 'page' ), $mode, $skills ),
			$messages,
			$this->tools->definitions( $mode ),
			$model
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
	 * Active assistant mode from the browser: agent (default) or ask.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	private function mode_from( WP_REST_Request $request ): string {
		$mode = strtolower( sanitize_key( (string) $request->get_param( 'mode' ) ) );

		return 'ask' === $mode ? 'ask' : 'agent';
	}

	/**
	 * Preferred model id from the browser for the active connector.
	 *
	 * Empty / "auto" means the AI Client chooses. Unknown ids are ignored so a
	 * stale localStorage value cannot break chat after provider changes.
	 *
	 * @param WP_REST_Request $request  The request.
	 * @param AiProvider      $provider Active provider.
	 */
	private function model_from( WP_REST_Request $request, AiProvider $provider ): string {
		$raw = trim( (string) $request->get_param( 'model' ) );

		if ( '' === $raw || 'auto' === strtolower( $raw ) ) {
			return '';
		}

		// Model ids are provider-defined (dots, slashes, colons) — not WP keys.
		$model = sanitize_text_field( $raw );

		if ( '' === $model || strlen( $model ) > 191 ) {
			return '';
		}

		$known = $provider->models();

		if ( array() !== $known && ! isset( $known[ $model ] ) ) {
			return '';
		}

		return $model;
	}

	/**
	 * Active skill slugs from the browser (validated against stored skills).
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<int, string>
	 */
	private function skills_from( WP_REST_Request $request ): array {
		$raw = $request->get_param( 'skills' );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$slugs = array();

		foreach ( array_slice( $raw, 0, 10 ) as $value ) {
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}

			$slug = sanitize_title( (string) $value );

			if ( '' === $slug ) {
				continue;
			}

			$slugs[] = $slug;
		}

		$resolved = $this->skills->resolve_for_prompt( $slugs );

		return array_values(
			array_map(
				static fn( array $skill ): string => (string) $skill['slug'],
				$resolved
			)
		);
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

			$blocks = $this->sanitize_content_blocks( $content );

			if ( array() === $blocks ) {
				continue;
			}

			$messages[] = array(
				'role'    => $role,
				'content' => $blocks,
			);
		}

		return $messages;
	}

	/**
	 * Keep only content-block shapes the provider understands.
	 *
	 * @param array<mixed> $content Raw blocks from the browser.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_content_blocks( array $content ): array {
		$blocks = array();

		foreach ( $content as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$type = isset( $block['type'] ) ? (string) $block['type'] : '';

			if ( 'text' === $type ) {
				$text = isset( $block['text'] ) ? (string) $block['text'] : '';

				if ( '' !== $text ) {
					$blocks[] = array(
						'type' => 'text',
						'text' => $text,
					);
				}

				continue;
			}

			if ( 'file' === $type || 'image' === $type ) {
				$url  = isset( $block['url'] ) ? esc_url_raw( (string) $block['url'] ) : '';
				$mime = isset( $block['mime_type'] ) ? sanitize_mime_type( (string) $block['mime_type'] ) : '';
				$name = isset( $block['name'] ) ? sanitize_file_name( (string) $block['name'] ) : '';

				if ( '' === $url ) {
					continue;
				}

				$blocks[] = array(
					'type'      => 'file',
					'url'       => $url,
					'mime_type' => $mime,
					'name'      => $name,
				);

				continue;
			}

			// Opaque-to-this-layer blocks still need a stable shape for the next turn.
			if ( 'thinking' === $type ) {
				$blocks[] = array(
					'type'      => 'thinking',
					'thinking'  => isset( $block['thinking'] ) ? (string) $block['thinking'] : '',
					'signature' => isset( $block['signature'] ) ? (string) $block['signature'] : '',
				);

				continue;
			}

			if ( 'redacted_thinking' === $type ) {
				$data = isset( $block['data'] ) ? (string) $block['data'] : '';

				if ( '' !== $data ) {
					$blocks[] = array(
						'type' => 'redacted_thinking',
						'data' => $data,
					);
				}

				continue;
			}

			if ( 'tool_use' === $type ) {
				$id   = isset( $block['id'] ) ? (string) $block['id'] : '';
				$name = isset( $block['name'] ) ? sanitize_key( (string) $block['name'] ) : '';

				if ( '' === $id || '' === $name ) {
					continue;
				}

				$input = $block['input'] ?? array();

				$blocks[] = array(
					'type'  => 'tool_use',
					'id'    => sanitize_text_field( $id ),
					'name'  => $name,
					'input' => is_array( $input ) ? $input : array(),
				);

				continue;
			}

			if ( 'tool_result' === $type ) {
				$id = isset( $block['tool_use_id'] ) ? (string) $block['tool_use_id'] : '';

				if ( '' === $id ) {
					continue;
				}

				$result = $block['content'] ?? '';

				if ( ! is_string( $result ) ) {
					$encoded = wp_json_encode( $result );
					$result  = is_string( $encoded ) ? $encoded : '';
				}

				$blocks[] = array(
					'type'        => 'tool_result',
					'tool_use_id' => sanitize_text_field( $id ),
					'content'     => $result,
					'is_error'    => ! empty( $block['is_error'] ),
				);
			}
		}

		return $blocks;
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
