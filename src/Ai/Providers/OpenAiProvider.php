<?php
/**
 * OpenAI-compatible provider.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Ai\Providers;

use ASDevs\AIAssistant\Ai\ChatRequest;
use ASDevs\AIAssistant\Ai\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to any service that speaks the OpenAI chat completions shape.
 *
 * The assistant's own message format follows the content-block shape, so this
 * provider translates in both directions. Nothing outside this class knows
 * either wire format.
 */
final class OpenAiProvider extends StreamingHttpProvider {

	/**
	 * Endpoint.
	 */
	private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Default model.
	 */
	private const DEFAULT_MODEL = 'gpt-4.1';

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
	 * Machine name.
	 */
	public function id(): string {
		return 'openai';
	}

	/**
	 * Human label.
	 */
	public function label(): string {
		return 'OpenAI';
	}

	/**
	 * Available models.
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			'gpt-4.1'      => 'GPT-4.1',
			'gpt-4.1-mini' => 'GPT-4.1 mini',
			'gpt-4o'       => 'GPT-4o',
		);
	}

	/**
	 * Whether a key is stored.
	 */
	public function is_configured(): bool {
		return '' !== $this->settings->key_for( $this->id() );
	}

	/**
	 * Stream one turn.
	 *
	 * @param ChatRequest $request The request.
	 * @param callable    $emit    Event sink.
	 */
	public function stream( ChatRequest $request, callable $emit ): void {
		$model = $this->settings->model();
		$model = '' !== $model && isset( $this->models()[ $model ] ) ? $model : self::DEFAULT_MODEL;

		$body = array(
			'model'    => $model,
			'stream'   => true,
			'messages' => $this->translate_messages( $request ),
		);

		if ( array() !== $request->tools() ) {
			$body['tools'] = array_map(
				static fn( array $tool ) => array(
					'type'     => 'function',
					'function' => array(
						'name'        => $tool['name'] ?? '',
						'description' => $tool['description'] ?? '',
						'parameters'  => $tool['input_schema'] ?? array( 'type' => 'object' ),
					),
				),
				$request->tools()
			);
		}

		$calls = array();

		$this->post_sse(
			self::ENDPOINT,
			array( 'Authorization' => 'Bearer ' . $this->settings->key_for( $this->id() ) ),
			$body,
			function ( array $event ) use ( &$calls, $emit ): void {
				$this->handle_event( $event, $calls, $emit );
			}
		);
	}

	/**
	 * Turn content-block messages into chat completion messages.
	 *
	 * @param ChatRequest $request The request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function translate_messages( ChatRequest $request ): array {
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $request->system(),
			),
		);

		foreach ( $request->messages() as $message ) {
			$role   = isset( $message['role'] ) ? (string) $message['role'] : 'user';
			$blocks = isset( $message['content'] ) && is_array( $message['content'] ) ? $message['content'] : array();

			if ( is_string( $message['content'] ?? null ) ) {
				$messages[] = array(
					'role'    => $role,
					'content' => (string) $message['content'],
				);

				continue;
			}

			$text       = '';
			$tool_calls = array();

			foreach ( $blocks as $block ) {
				$type = isset( $block['type'] ) ? (string) $block['type'] : '';

				if ( 'text' === $type ) {
					$text .= (string) ( $block['text'] ?? '' );
					continue;
				}

				if ( 'tool_use' === $type ) {
					$tool_calls[] = array(
						'id'       => (string) ( $block['id'] ?? '' ),
						'type'     => 'function',
						'function' => array(
							'name'      => (string) ( $block['name'] ?? '' ),
							'arguments' => (string) wp_json_encode( $block['input'] ?? array() ),
						),
					);

					continue;
				}

				if ( 'tool_result' === $type ) {
					$messages[] = array(
						'role'         => 'tool',
						'tool_call_id' => (string) ( $block['tool_use_id'] ?? '' ),
						'content'      => is_string( $block['content'] ?? null )
							? (string) $block['content']
							: (string) wp_json_encode( $block['content'] ?? '' ),
					);
				}
			}

			if ( '' !== $text || array() !== $tool_calls ) {
				$entry = array(
					'role'    => $role,
					'content' => '' === $text ? null : $text,
				);

				if ( array() !== $tool_calls ) {
					$entry['tool_calls'] = $tool_calls;
				}

				$messages[] = $entry;
			}
		}

		return $messages;
	}

	/**
	 * Translate one streamed chunk into normalized events.
	 *
	 * @param array<string, mixed>             $event Decoded chunk.
	 * @param array<int, array<string, mixed>> $calls Accumulated tool calls, by index.
	 * @param callable                         $emit  Event sink.
	 */
	private function handle_event( array $event, array &$calls, callable $emit ): void {
		$choice = isset( $event['choices'][0] ) && is_array( $event['choices'][0] ) ? $event['choices'][0] : array();
		$delta  = isset( $choice['delta'] ) && is_array( $choice['delta'] ) ? $choice['delta'] : array();

		if ( isset( $delta['content'] ) && is_string( $delta['content'] ) && '' !== $delta['content'] ) {
			$emit(
				array(
					'type' => 'text',
					'text' => $delta['content'],
				)
			);
		}

		foreach ( (array) ( $delta['tool_calls'] ?? array() ) as $call ) {
			$index = isset( $call['index'] ) ? (int) $call['index'] : 0;

			if ( ! isset( $calls[ $index ] ) ) {
				$calls[ $index ] = array(
					'id'   => '',
					'name' => '',
					'json' => '',
				);
			}

			if ( isset( $call['id'] ) ) {
				$calls[ $index ]['id'] = (string) $call['id'];
			}

			if ( isset( $call['function']['name'] ) ) {
				$calls[ $index ]['name'] .= (string) $call['function']['name'];
			}

			if ( isset( $call['function']['arguments'] ) ) {
				$calls[ $index ]['json'] .= (string) $call['function']['arguments'];
			}
		}

		$finish = isset( $choice['finish_reason'] ) ? (string) $choice['finish_reason'] : '';

		if ( '' === $finish ) {
			return;
		}

		foreach ( $calls as $call ) {
			$arguments = json_decode( '' === $call['json'] ? '{}' : $call['json'], true );

			$emit(
				array(
					'type'      => 'tool_call',
					'id'        => (string) $call['id'],
					'name'      => (string) $call['name'],
					'arguments' => is_array( $arguments ) ? $arguments : array(),
				)
			);
		}

		$calls = array();

		$emit(
			array(
				'type'   => 'done',
				'reason' => 'tool_calls' === $finish ? 'tool_use' : $finish,
			)
		);
	}
}
