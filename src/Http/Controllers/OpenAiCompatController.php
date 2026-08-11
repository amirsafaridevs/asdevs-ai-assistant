<?php
/**
 * An OpenAI-shaped chat endpoint backed by the site's own AI connector.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Ai\AiProvider;
use ASDevs\AIAssistant\Services\Ai\AiUnavailable;
use ASDevs\AIAssistant\Services\Ai\ChatRequest;
use ASDevs\AIAssistant\Services\Ai\ProviderRegistry;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speaks the OpenAI Chat Completions protocol so the OpenAI Agents SDK can run
 * in the browser without ever seeing an API key.
 *
 * The SDK owns the agent loop client-side; this endpoint is the only thing it
 * talks to. Requests arrive in OpenAI's shape, are translated into the
 * provider-independent ChatRequest, and answered by whichever WordPress AI
 * connector the site chose under Settings → Connectors. Anthropic, Google, and
 * OpenAI all work, because the SDK never reaches a vendor directly.
 *
 * Credentials stay server-side exactly as before (section 14.1): the browser
 * authenticates with the ordinary REST nonce, not with a model API key.
 */
final class OpenAiCompatController extends Controller {

	/**
	 * How many messages one request may carry.
	 */
	private const MAX_MESSAGES = 200;

	/**
	 * Providers.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Constructor.
	 *
	 * @param ProviderRegistry $providers Providers.
	 */
	public function __construct( ProviderRegistry $providers ) {
		$this->providers = $providers;
	}

	/**
	 * Register routes.
	 *
	 * The path mirrors OpenAI's so the SDK client only needs a base URL.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/openai/v1/chat/completions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
			)
		);
	}

	/**
	 * Answer one chat completion, streaming or not.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|null Null once a stream has been written.
	 */
	public function handle( WP_REST_Request $request ): ?WP_REST_Response {
		$provider = $this->providers->selected();

		if ( null === $provider ) {
			return $this->error(
				'asdevs_ai_not_configured',
				__( 'The assistant needs a configured WordPress AI connector before it can work. Open Settings → Connectors to connect a provider.', 'asdevs-ai-assistant' ),
				409,
				$this->providers->not_ready_detail()
			);
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$raw_messages = isset( $body['messages'] ) && is_array( $body['messages'] ) ? $body['messages'] : array();
		$conversation = $this->conversation_from( $raw_messages );

		if ( array() === $conversation['messages'] ) {
			return $this->error(
				'asdevs_ai_empty',
				__( 'There is nothing to answer yet.', 'asdevs-ai-assistant' ),
				400
			);
		}

		$chat = new ChatRequest(
			$conversation['system'],
			$conversation['messages'],
			$this->tools_from( $body['tools'] ?? null ),
			$this->model_from( $body['model'] ?? '', $provider ),
			$this->output_schema_from( $body['response_format'] ?? null ),
			$this->temperature_from( $body['temperature'] ?? null )
		);

		// The AI Client answers in one shot, so collect first and shape after.
		// That also means a provider failure can still become a clean HTTP
		// error instead of a half-written stream.
		$events = array();

		try {
			$provider->stream(
				$chat,
				static function ( array $event ) use ( &$events ): void {
					$events[] = $event;
				}
			);
		} catch ( AiUnavailable $error ) {
			return $this->error(
				'asdevs_ai_unavailable',
				$error->getMessage(),
				$error->is_retryable() ? 503 : 502,
				$error->detail()
			);
		}

		$answer = $this->answer_from( $events );
		$model  = $this->reported_model( $body['model'] ?? '', $provider );

		if ( ! empty( $body['stream'] ) ) {
			$this->stream_answer( $answer, $model );

			return null;
		}

		return new WP_REST_Response( $this->completion_body( $answer, $model ) );
	}

	/**
	 * Split OpenAI messages into system instructions and internal blocks.
	 *
	 * Consecutive `tool` replies are merged into a single user message, which is
	 * the shape the connector expects for function responses.
	 *
	 * @param array<mixed> $raw Messages in OpenAI shape.
	 *
	 * @return array{system:string, messages:array<int, array<string, mixed>>}
	 */
	private function conversation_from( array $raw ): array {
		$system   = array();
		$messages = array();

		foreach ( array_slice( $raw, 0, self::MAX_MESSAGES ) as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role = isset( $message['role'] ) ? (string) $message['role'] : '';

			if ( 'system' === $role || 'developer' === $role ) {
				$text = $this->text_of( $message['content'] ?? '' );

				if ( '' !== $text ) {
					$system[] = $text;
				}

				continue;
			}

			if ( 'tool' === $role ) {
				$block = $this->tool_result_block( $message );

				if ( null === $block ) {
					continue;
				}

				// Keep every result for one round in one message so ids stay
				// adjacent to the calls they answer.
				$last = count( $messages ) - 1;

				if ( $last >= 0 && 'user' === $messages[ $last ]['role'] && $this->is_tool_result_message( $messages[ $last ] ) ) {
					$messages[ $last ]['content'][] = $block;
				} else {
					$messages[] = array(
						'role'    => 'user',
						'content' => array( $block ),
					);
				}

				continue;
			}

			if ( 'assistant' === $role ) {
				$blocks = $this->assistant_blocks( $message );

				if ( array() !== $blocks ) {
					$messages[] = array(
						'role'    => 'assistant',
						'content' => $blocks,
					);
				}

				continue;
			}

			if ( 'user' === $role ) {
				$blocks = $this->user_blocks( $message['content'] ?? '' );

				if ( array() !== $blocks ) {
					$messages[] = array(
						'role'    => 'user',
						'content' => $blocks,
					);
				}
			}
		}

		return array(
			'system'   => implode( "\n\n", $system ),
			'messages' => $messages,
		);
	}

	/**
	 * Whether a built message carries only function responses.
	 *
	 * @param array<string, mixed> $message Internal message.
	 */
	private function is_tool_result_message( array $message ): bool {
		foreach ( (array) $message['content'] as $block ) {
			if ( ! is_array( $block ) || 'tool_result' !== ( $block['type'] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert one OpenAI `tool` message into a function-response block.
	 *
	 * @param array<string, mixed> $message The message.
	 *
	 * @return array<string, mixed>|null
	 */
	private function tool_result_block( array $message ): ?array {
		$id = isset( $message['tool_call_id'] ) ? sanitize_text_field( (string) $message['tool_call_id'] ) : '';

		if ( '' === $id ) {
			return null;
		}

		$content = $message['content'] ?? '';

		if ( ! is_string( $content ) ) {
			$encoded = wp_json_encode( $content );
			$content = is_string( $encoded ) ? $encoded : '';
		}

		return array(
			'type'        => 'tool_result',
			'tool_use_id' => $id,
			'content'     => $content,
			'is_error'    => false,
		);
	}

	/**
	 * Convert one OpenAI assistant message into internal content blocks.
	 *
	 * Reasoning arrives on `reasoning`, the field third-party providers use on
	 * Chat Completions and the one the Agents SDK reads and writes.
	 *
	 * It is deliberately not replayed to the provider unless it still carries a
	 * signature. Chat Completions has nowhere to keep one, so a replayed thought
	 * would reach the model unsigned — which providers like Anthropic reject.
	 * The thought is still shown to the person and still stored with the
	 * conversation; it just does not go back to the model as a thinking block.
	 *
	 * @param array<string, mixed> $message The message.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function assistant_blocks( array $message ): array {
		$blocks    = array();
		$reasoning = isset( $message['reasoning'] ) ? (string) $message['reasoning'] : '';
		$signature = isset( $message['reasoning_signature'] ) ? (string) $message['reasoning_signature'] : '';

		if ( '' !== $reasoning && '' !== $signature ) {
			$blocks[] = array(
				'type'      => 'thinking',
				'thinking'  => $reasoning,
				'signature' => $signature,
			);
		}

		$text = $this->text_of( $message['content'] ?? '' );

		if ( '' !== $text ) {
			$blocks[] = array(
				'type' => 'text',
				'text' => $text,
			);
		}

		$calls = isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] )
			? $message['tool_calls']
			: array();

		foreach ( $calls as $call ) {
			if ( ! is_array( $call ) ) {
				continue;
			}

			$function = isset( $call['function'] ) && is_array( $call['function'] ) ? $call['function'] : array();
			$name     = isset( $function['name'] ) ? sanitize_key( (string) $function['name'] ) : '';
			$id       = isset( $call['id'] ) ? sanitize_text_field( (string) $call['id'] ) : '';

			if ( '' === $name || '' === $id ) {
				continue;
			}

			$arguments = $function['arguments'] ?? array();

			if ( is_string( $arguments ) ) {
				$decoded   = json_decode( $arguments, true );
				$arguments = is_array( $decoded ) ? $decoded : array();
			}

			$blocks[] = array(
				'type'  => 'tool_use',
				'id'    => $id,
				'name'  => $name,
				'input' => is_array( $arguments ) ? $arguments : array(),
			);
		}

		return $blocks;
	}

	/**
	 * Convert OpenAI user content into internal blocks.
	 *
	 * @param mixed $content String, or an array of content parts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function user_blocks( $content ): array {
		if ( is_string( $content ) ) {
			$text = trim( $content );

			return '' === $text
				? array()
				: array(
					array(
						'type' => 'text',
						'text' => $text,
					),
				);
		}

		if ( ! is_array( $content ) ) {
			return array();
		}

		$blocks = array();

		foreach ( $content as $part ) {
			if ( is_string( $part ) ) {
				if ( '' !== trim( $part ) ) {
					$blocks[] = array(
						'type' => 'text',
						'text' => $part,
					);
				}

				continue;
			}

			if ( ! is_array( $part ) ) {
				continue;
			}

			$type = isset( $part['type'] ) ? (string) $part['type'] : '';

			if ( 'text' === $type || 'input_text' === $type ) {
				$text = isset( $part['text'] ) ? (string) $part['text'] : '';

				if ( '' !== trim( $text ) ) {
					$blocks[] = array(
						'type' => 'text',
						'text' => $text,
					);
				}

				continue;
			}

			if ( 'image_url' === $type || 'input_image' === $type ) {
				$url = $part['image_url'] ?? ( $part['url'] ?? '' );

				if ( is_array( $url ) ) {
					$url = $url['url'] ?? '';
				}

				$url = esc_url_raw( (string) $url );

				if ( '' === $url ) {
					continue;
				}

				$blocks[] = array(
					'type'      => 'file',
					'url'       => $url,
					'mime_type' => '',
					'name'      => '',
				);
			}
		}

		return $blocks;
	}

	/**
	 * Flatten OpenAI content (string or parts) into plain text.
	 *
	 * @param mixed $content The content.
	 */
	private function text_of( $content ): string {
		if ( is_string( $content ) ) {
			return trim( $content );
		}

		if ( ! is_array( $content ) ) {
			return '';
		}

		$text = array();

		foreach ( $content as $part ) {
			if ( is_string( $part ) ) {
				$text[] = $part;

				continue;
			}

			if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$text[] = $part['text'];
			}
		}

		return trim( implode( '', $text ) );
	}

	/**
	 * Convert OpenAI tool declarations into internal tool definitions.
	 *
	 * @param mixed $raw The `tools` field.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function tools_from( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$tools = array();

		foreach ( $raw as $tool ) {
			if ( ! is_array( $tool ) ) {
				continue;
			}

			$function = isset( $tool['function'] ) && is_array( $tool['function'] ) ? $tool['function'] : $tool;
			$name     = isset( $function['name'] ) ? sanitize_key( (string) $function['name'] ) : '';

			if ( '' === $name ) {
				continue;
			}

			$schema = $function['parameters'] ?? null;

			$tools[] = array(
				'name'         => $name,
				'description'  => isset( $function['description'] ) ? (string) $function['description'] : '',
				'input_schema' => is_array( $schema ) ? $schema : array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			);
		}

		return $tools;
	}

	/**
	 * The JSON schema an agent asked its answer to match, if any.
	 *
	 * @param mixed $raw The `response_format` field.
	 *
	 * @return array<string, mixed>|null
	 */
	private function output_schema_from( $raw ): ?array {
		if ( ! is_array( $raw ) || 'json_schema' !== ( $raw['type'] ?? '' ) ) {
			return null;
		}

		$wrapper = isset( $raw['json_schema'] ) && is_array( $raw['json_schema'] ) ? $raw['json_schema'] : array();
		$schema  = $wrapper['schema'] ?? null;

		return is_array( $schema ) ? $schema : null;
	}

	/**
	 * Sampling temperature within the range every provider accepts.
	 *
	 * @param mixed $raw The `temperature` field.
	 */
	private function temperature_from( $raw ): ?float {
		if ( ! is_numeric( $raw ) ) {
			return null;
		}

		return max( 0.0, min( 2.0, (float) $raw ) );
	}

	/**
	 * Validate the requested model against the active connector.
	 *
	 * Empty means the AI Client picks. Unknown ids are dropped so a stale
	 * client-side value cannot break chat after the provider changes.
	 *
	 * @param mixed      $raw      The `model` field.
	 * @param AiProvider $provider Active provider.
	 */
	private function model_from( $raw, AiProvider $provider ): string {
		$model = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $model || 'auto' === strtolower( $model ) ) {
			return '';
		}

		// Model ids are provider-defined (dots, slashes, colons) — not WP keys.
		$model = sanitize_text_field( $model );

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
	 * What to report back as the answering model.
	 *
	 * @param mixed      $raw      The requested model.
	 * @param AiProvider $provider Active provider.
	 */
	private function reported_model( $raw, AiProvider $provider ): string {
		$model = $this->model_from( $raw, $provider );

		return '' !== $model ? $model : $provider->id();
	}

	/**
	 * Fold provider events into the parts of one assistant answer.
	 *
	 * @param array<int, array<string, mixed>> $events Provider events.
	 *
	 * @return array{content:string, reasoning:string, signature:string, calls:array<int, array<string, mixed>>}
	 */
	private function answer_from( array $events ): array {
		$content   = '';
		$reasoning = '';
		$signature = '';
		$calls     = array();

		foreach ( $events as $event ) {
			$type = isset( $event['type'] ) ? (string) $event['type'] : '';

			if ( 'text' === $type ) {
				$content .= (string) ( $event['text'] ?? '' );

				continue;
			}

			if ( 'thinking_end' === $type ) {
				$reasoning .= (string) ( $event['thinking'] ?? '' );
				$candidate  = (string) ( $event['signature'] ?? '' );

				if ( '' !== $candidate ) {
					$signature = $candidate;
				}

				continue;
			}

			if ( 'tool_call' === $type ) {
				$id   = (string) ( $event['id'] ?? '' );
				$name = (string) ( $event['name'] ?? '' );

				if ( '' === $name ) {
					continue;
				}

				// Always an object, even with no arguments: an empty PHP array
				// would encode as `[]`, and the client parses this back into
				// the tool's input.
				$arguments = wp_json_encode( (object) (array) ( $event['arguments'] ?? array() ) );

				$calls[] = array(
					'id'       => '' !== $id ? $id : 'call_' . wp_generate_uuid4(),
					'type'     => 'function',
					'function' => array(
						'name'      => $name,
						'arguments' => is_string( $arguments ) ? $arguments : '{}',
					),
				);
			}
		}

		return array(
			'content'   => $content,
			'reasoning' => $reasoning,
			'signature' => $signature,
			'calls'     => $calls,
		);
	}

	/**
	 * A non-streaming chat completion body.
	 *
	 * @param array{content:string, reasoning:string, signature:string, calls:array<int, array<string, mixed>>} $answer The answer.
	 * @param string                                                                                            $model  Reported model id.
	 *
	 * @return array<string, mixed>
	 */
	private function completion_body( array $answer, string $model ): array {
		$message = array(
			'role'    => 'assistant',
			'content' => '' !== $answer['content'] ? $answer['content'] : null,
		);

		if ( '' !== $answer['reasoning'] ) {
			$message['reasoning']           = $answer['reasoning'];
			$message['reasoning_signature'] = $answer['signature'];
		}

		if ( array() !== $answer['calls'] ) {
			$message['tool_calls'] = $answer['calls'];
		}

		return array(
			'id'      => 'chatcmpl-' . wp_generate_uuid4(),
			'object'  => 'chat.completion',
			'created' => time(),
			'model'   => $model,
			'choices' => array(
				array(
					'index'         => 0,
					'message'       => $message,
					'finish_reason' => array() !== $answer['calls'] ? 'tool_calls' : 'stop',
				),
			),
		);
	}

	/**
	 * Write the answer as OpenAI streaming chunks and end the request.
	 *
	 * @param array{content:string, reasoning:string, signature:string, calls:array<int, array<string, mixed>>} $answer The answer.
	 * @param string                                                                                            $model  Reported model id.
	 */
	private function stream_answer( array $answer, string $model ): void {
		$this->open_stream();

		$id      = 'chatcmpl-' . wp_generate_uuid4();
		$created = time();

		$chunk = function ( array $delta, ?string $finish = null ) use ( $id, $created, $model ): void {
			$this->send(
				array(
					'id'      => $id,
					'object'  => 'chat.completion.chunk',
					'created' => $created,
					'model'   => $model,
					'choices' => array(
						array(
							'index'         => 0,
							// The closing chunk carries an empty delta, which
							// must still be `{}` and not `[]`.
							'delta'         => (object) $delta,
							'finish_reason' => $finish,
						),
					),
				)
			);
		};

		$chunk( array( 'role' => 'assistant' ) );

		if ( '' !== $answer['reasoning'] ) {
			$chunk(
				array(
					'reasoning'           => $answer['reasoning'],
					'reasoning_signature' => $answer['signature'],
				)
			);
		}

		if ( '' !== $answer['content'] ) {
			$chunk( array( 'content' => $answer['content'] ) );
		}

		foreach ( $answer['calls'] as $index => $call ) {
			$chunk(
				array(
					'tool_calls' => array(
						array(
							'index'    => $index,
							'id'       => $call['id'],
							'type'     => 'function',
							'function' => $call['function'],
						),
					),
				)
			);
		}

		$chunk( array(), array() !== $answer['calls'] ? 'tool_calls' : 'stop' );

		echo "data: [DONE]\n\n";
		flush();

		exit;
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
	 * Write one chunk to the stream.
	 *
	 * @param array<string, mixed> $payload The chunk.
	 */
	private function send( array $payload ): void {
		echo 'data: ' . wp_json_encode( $payload ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded for a text/event-stream body, not HTML.

		flush();
	}

	/**
	 * An error the OpenAI client understands.
	 *
	 * The envelope has to sit at the top level of the body: that is where the
	 * OpenAI client looks for it. A WP_Error would nest it under `data`, and the
	 * client would then report the useless "503 status code (no body)" instead
	 * of the sentence written here.
	 *
	 * @param string $code    Error code.
	 * @param string $message Human message.
	 * @param int    $status  HTTP status.
	 * @param string $detail  Technical detail.
	 */
	private function error( string $code, string $message, int $status, string $detail = '' ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'error' => array(
					'message' => $message,
					'type'    => $code,
					'code'    => $code,
					'detail'  => $detail,
				),
			),
			$status
		);
	}
}
