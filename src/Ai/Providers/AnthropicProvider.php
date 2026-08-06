<?php
/**
 * Anthropic provider.
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
 * Talks to the Anthropic Messages API.
 */
final class AnthropicProvider extends StreamingHttpProvider {

	/**
	 * Endpoint.
	 */
	private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * API version header value.
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * Default model.
	 */
	private const DEFAULT_MODEL = 'claude-opus-5';

	/**
	 * Models that can reason before they answer.
	 *
	 * Adaptive thinking is the only supported form on these; older models take
	 * a fixed token budget instead, which this plugin does not use.
	 */
	private const REASONING_MODELS = array( 'claude-opus-5', 'claude-sonnet-5' );

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
		return 'anthropic';
	}

	/**
	 * Human label.
	 */
	public function label(): string {
		return 'Anthropic Claude';
	}

	/**
	 * Available models.
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			'claude-opus-5'    => 'Claude Opus 5',
			'claude-sonnet-5'  => 'Claude Sonnet 5',
			'claude-haiku-4-5' => 'Claude Haiku 4.5',
		);
	}

	/**
	 * The model used when the site has not chosen one.
	 */
	public function default_model(): string {
		return self::DEFAULT_MODEL;
	}

	/**
	 * Models that can show their reasoning.
	 *
	 * @return string[]
	 */
	public function reasoning_models(): array {
		return self::REASONING_MODELS;
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
			'model'      => $model,
			'max_tokens' => 16000,
			'stream'     => true,
			'system'     => $request->system(),
			'messages'   => $request->messages(),
		);

		// On these models reasoning is on unless it is turned off, so the choice
		// is always stated rather than left to the default. A summary is asked
		// for because the raw reasoning is never returned.
		if ( in_array( $model, self::REASONING_MODELS, true ) ) {
			$body['thinking'] = $this->settings->thinking()
				? array(
					'type'    => 'adaptive',
					'display' => 'summarized',
				)
				: array( 'type' => 'disabled' );
		}

		if ( array() !== $request->tools() ) {
			$body['tools'] = $request->tools();
		}

		$blocks = array();

		$this->post_sse(
			self::ENDPOINT,
			array(
				'x-api-key'         => $this->settings->key_for( $this->id() ),
				'anthropic-version' => self::API_VERSION,
			),
			$body,
			function ( array $event ) use ( &$blocks, $emit ): void {
				$this->handle_event( $event, $blocks, $emit );
			}
		);
	}

	/**
	 * Translate one streamed event into a normalized event.
	 *
	 * @param array<string, mixed>            $event  Decoded event.
	 * @param array<int, array<string, mixed>> $blocks Accumulated content blocks, by index.
	 * @param callable                        $emit   Event sink.
	 */
	private function handle_event( array $event, array &$blocks, callable $emit ): void {
		$type  = isset( $event['type'] ) ? (string) $event['type'] : '';
		$index = isset( $event['index'] ) ? (int) $event['index'] : 0;

		if ( 'content_block_start' === $type ) {
			$block = isset( $event['content_block'] ) && is_array( $event['content_block'] ) ? $event['content_block'] : array();

			$blocks[ $index ] = array(
				'type'      => isset( $block['type'] ) ? (string) $block['type'] : 'text',
				'id'        => isset( $block['id'] ) ? (string) $block['id'] : '',
				'name'      => isset( $block['name'] ) ? (string) $block['name'] : '',
				'json'      => '',
				'thinking'  => isset( $block['thinking'] ) ? (string) $block['thinking'] : '',
				'signature' => isset( $block['signature'] ) ? (string) $block['signature'] : '',
				'data'      => isset( $block['data'] ) ? (string) $block['data'] : '',
			);

			return;
		}

		if ( 'content_block_delta' === $type ) {
			$delta = isset( $event['delta'] ) && is_array( $event['delta'] ) ? $event['delta'] : array();
			$kind  = isset( $delta['type'] ) ? (string) $delta['type'] : '';

			if ( 'text_delta' === $kind && isset( $delta['text'] ) ) {
				$emit(
					array(
						'type' => 'text',
						'text' => (string) $delta['text'],
					)
				);

				return;
			}

			if ( 'input_json_delta' === $kind && isset( $blocks[ $index ] ) ) {
				$blocks[ $index ]['json'] .= (string) ( $delta['partial_json'] ?? '' );

				return;
			}

			if ( 'thinking_delta' === $kind && isset( $blocks[ $index ] ) ) {
				$text = (string) ( $delta['thinking'] ?? '' );

				$blocks[ $index ]['thinking'] .= $text;

				$emit(
					array(
						'type' => 'thinking',
						'text' => $text,
					)
				);

				return;
			}

			// The signature is what makes a reasoning block replayable on the
			// next turn; it is carried, never shown.
			if ( 'signature_delta' === $kind && isset( $blocks[ $index ] ) ) {
				$blocks[ $index ]['signature'] .= (string) ( $delta['signature'] ?? '' );
			}

			return;
		}

		if ( 'content_block_stop' === $type && isset( $blocks[ $index ] ) ) {
			$block = $blocks[ $index ];

			if ( 'thinking' === $block['type'] || 'redacted_thinking' === $block['type'] ) {
				$emit(
					array(
						'type'      => 'thinking_end',
						'kind'      => (string) $block['type'],
						'thinking'  => (string) $block['thinking'],
						'signature' => (string) $block['signature'],
						'data'      => (string) $block['data'],
					)
				);
			}

			if ( 'tool_use' === $block['type'] ) {
				$arguments = json_decode( '' === $block['json'] ? '{}' : $block['json'], true );

				$emit(
					array(
						'type'      => 'tool_call',
						'id'        => (string) $block['id'],
						'name'      => (string) $block['name'],
						'arguments' => is_array( $arguments ) ? $arguments : array(),
					)
				);
			}

			unset( $blocks[ $index ] );

			return;
		}

		if ( 'message_delta' === $type ) {
			$delta = isset( $event['delta'] ) && is_array( $event['delta'] ) ? $event['delta'] : array();

			if ( isset( $delta['stop_reason'] ) ) {
				$emit(
					array(
						'type'   => 'done',
						'reason' => (string) $delta['stop_reason'],
					)
				);
			}
		}
	}
}
