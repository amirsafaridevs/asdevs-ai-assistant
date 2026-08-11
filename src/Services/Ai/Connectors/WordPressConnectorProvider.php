<?php
/**
 * WordPress AI Client connector adapter.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai\Connectors;

use ASDevs\AIAssistant\Services\Ai\AiProvider;
use ASDevs\AIAssistant\Services\Ai\AiUnavailable;
use ASDevs\AIAssistant\Services\Ai\ChatRequest;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes chat turns through a WordPress AI connector.
 *
 * Credentials and provider plugins are managed under Settings → Connectors.
 * This class only selects a registered provider and translates the assistant's
 * tool-calling conversation into the WordPress AI Client shapes.
 */
final class WordPressConnectorProvider implements AiProvider {

	/**
	 * WordPress AI provider id (e.g. anthropic, openai, google).
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Human label from the provider metadata.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Constructor.
	 *
	 * @param string $id    Provider id.
	 * @param string $label Provider label.
	 */
	public function __construct( string $id, string $label ) {
		$this->id    = $id;
		$this->label = $label;
	}

	/**
	 * Machine name of the provider.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Human label, shown in settings.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Text-generation models from the WordPress AI Client for this connector.
	 *
	 * Keys are provider model ids; values are display names. Empty when the
	 * connector is not ready or the directory cannot be listed.
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		if ( ! $this->is_configured() || ! class_exists( AiClient::class ) ) {
			return array();
		}

		try {
			$registry  = AiClient::defaultRegistry();
			$class     = $registry->getProviderClassName( $this->id );
			$directory = $class::modelMetadataDirectory();
			$listed    = $directory->listModelMetadata();
		} catch ( \Throwable ) {
			return array();
		}

		$models = array();

		foreach ( $listed as $meta ) {
			if ( ! $meta instanceof ModelMetadata || ! $this->is_text_generation_model( $meta ) ) {
				continue;
			}

			$id   = $meta->getId();
			$name = trim( $meta->getName() );

			if ( '' === $id ) {
				continue;
			}

			$models[ $id ] = '' !== $name ? $name : $id;
		}

		return $models;
	}

	/**
	 * No fixed default — empty means the AI Client picks a suitable model.
	 */
	public function default_model(): string {
		return '';
	}

	/**
	 * Reasoning display is left to whatever the connector returns.
	 *
	 * @return string[]
	 */
	public function reasoning_models(): array {
		return array();
	}

	/**
	 * Whether a model can power assistant chat (text in / text out).
	 *
	 * Image, speech, embedding, and similar specialized models are excluded so
	 * the composer picker stays usable across every provider.
	 *
	 * @param ModelMetadata $meta Model metadata from the AI Client.
	 */
	private function is_text_generation_model( ModelMetadata $meta ): bool {
		foreach ( $meta->getSupportedCapabilities() as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this WordPress connector is installed and has local credentials.
	 *
	 * Important: do not call AiClient::isProviderConfigured() / availability()
	 * here. Those methods issue a live HTTP probe (list-models / generate-text).
	 * Transient network failures, timeouts, and 429s then look like "connector
	 * not configured" and make the assistant flap between ready and not ready.
	 */
	public function is_configured(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! class_exists( AiClient::class ) ) {
			return false;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $this->id ) ) {
			return false;
		}

		return $this->has_local_credentials( $registry );
	}

	/**
	 * Whether credentials for this connector are present without a network call.
	 *
	 * @param \WordPress\AiClient\Providers\ProviderRegistry $registry AI client registry.
	 */
	private function has_local_credentials( $registry ): bool {
		if ( function_exists( 'wp_get_connector' ) ) {
			$connector = wp_get_connector( $this->id );

			if ( is_array( $connector ) ) {
				$auth   = isset( $connector['authentication'] ) && is_array( $connector['authentication'] )
					? $connector['authentication']
					: array();
				$method = isset( $auth['method'] ) ? (string) $auth['method'] : '';

				if ( 'none' === $method ) {
					return true;
				}

				if ( 'api_key' === $method && function_exists( '_wp_connectors_get_api_key_source' ) ) {
					$setting = isset( $auth['setting_name'] ) ? (string) $auth['setting_name'] : '';
					$env     = isset( $auth['env_var_name'] ) ? (string) $auth['env_var_name'] : '';
					$const   = isset( $auth['constant_name'] ) ? (string) $auth['constant_name'] : '';

					return '' !== $setting && 'none' !== _wp_connectors_get_api_key_source( $setting, $env, $const );
				}
			}
		}

		$request_auth = $registry->getProviderRequestAuthentication( $this->id );

		if ( $request_auth instanceof ApiKeyRequestAuthentication ) {
			return '' !== $request_auth->getApiKey();
		}

		// No connector metadata and no API-key auth object — cannot confirm credentials locally.
		return false;
	}

	/**
	 * Run one turn through the WordPress AI Client.
	 *
	 * The AI Client answers in one shot (no SSE). Events are still emitted in
	 * the shape the browser already understands so tool loops keep working.
	 *
	 * @param ChatRequest $request The request.
	 * @param callable    $emit    Event sink.
	 *
	 * @throws AiUnavailable When the service cannot answer.
	 */
	public function stream( ChatRequest $request, callable $emit ): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			throw new AiUnavailable(
				esc_html__( 'The assistant needs WordPress 7.0 or later with AI support enabled.', 'asdevs-ai-assistant' ),
				'wp_ai_client_prompt missing',
				false
			);
		}

		if ( ! $this->is_configured() ) {
			throw new AiUnavailable(
				esc_html__( 'This connector is not ready. Open Settings → Connectors, install a provider, and save an API key.', 'asdevs-ai-assistant' ),
				esc_html( 'connector not configured: ' . $this->id ),
				false
			);
		}

		$messages = $this->to_wp_messages( $request->messages() );

		if ( array() === $messages ) {
			throw new AiUnavailable(
				esc_html__( 'There is nothing to answer yet.', 'asdevs-ai-assistant' ),
				'empty messages',
				false
			);
		}

		$builder = wp_ai_client_prompt( $messages )
			->using_provider( $this->id )
			->using_system_instruction( $request->system() )
			->using_max_tokens( 16000 );

		$model = $request->model();

		if ( '' !== $model ) {
			// Prefer this provider's model id; if it cannot meet prompt
			// requirements the AI Client falls back to another candidate.
			$builder = $builder->using_model_preference( array( $this->id, $model ) );
		}

		$declarations = $this->function_declarations( $request->tools() );

		if ( array() !== $declarations ) {
			$builder = $builder->using_function_declarations( ...$declarations );
		}

		$temperature = $request->temperature();

		if ( null !== $temperature ) {
			$builder = $builder->using_temperature( $temperature );
		}

		$schema = $request->output_schema();

		if ( null !== $schema ) {
			// Structured output: the model answers with JSON matching the schema
			// instead of prose. Used by agents that declare an output type.
			$builder = $builder->as_output_schema( $schema );
		}

		$result = $builder->generate_text_result();

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 0 );

			if ( in_array( $status, array( 401, 403 ), true ) ) {
				throw new AiUnavailable(
					esc_html( $this->user_message_for_error( $result->get_error_code(), $status ) ),
					esc_html( $result->get_error_message() ),
					false
				);
			}

			throw new AiUnavailable(
				esc_html( $this->user_message_for_error( $result->get_error_code(), $status ) ),
				esc_html( $result->get_error_message() ),
				true
			);
		}

		$this->emit_result( $result->toMessage(), $result->getCandidates()[0]->getFinishReason()->value, $emit );
	}

	/**
	 * Send a short probe prompt to confirm the connector works.
	 *
	 * @return array{ok:bool, reply?:string, message:string, detail?:string}
	 */
	public function test_connection(): array {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'WordPress AI Client is not available. This plugin requires WordPress 7.0 or later.', 'asdevs-ai-assistant' ),
			);
		}

		if ( ! $this->is_configured() ) {
			return array(
				'ok'      => false,
				'message' => __( 'This connector is not configured. Open Settings → Connectors and save an API key first.', 'asdevs-ai-assistant' ),
			);
		}

		$result = wp_ai_client_prompt( 'Reply with exactly the word OK and nothing else.' )
			->using_provider( $this->id )
			->using_max_tokens( 16 )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'The connector did not respond. Check the API key under Settings → Connectors.', 'asdevs-ai-assistant' ),
				'detail'  => $result->get_error_message(),
			);
		}

		$reply = trim( (string) $result );

		return array(
			'ok'      => true,
			'reply'   => $reply,
			'message' => __( 'Connection works. The connector answered successfully.', 'asdevs-ai-assistant' ),
		);
	}

	/**
	 * Convert tool definitions into WordPress FunctionDeclaration objects.
	 *
	 * @param array<int, array<string, mixed>> $tools Tool definitions.
	 *
	 * @return FunctionDeclaration[]
	 */
	private function function_declarations( array $tools ): array {
		$declarations = array();

		foreach ( $tools as $tool ) {
			$name = isset( $tool['name'] ) ? (string) $tool['name'] : '';

			if ( '' === $name ) {
				continue;
			}

			$schema = $tool['input_schema'] ?? null;

			if ( is_object( $schema ) ) {
				$schema = json_decode( (string) wp_json_encode( $schema ), true );
			}

			if ( ! is_array( $schema ) || array() === $schema ) {
				$schema = array(
					'type'       => 'object',
					'properties' => (object) array(),
				);
			} else {
				$schema = $this->schema_maps_as_objects( $schema );
			}

			$declarations[] = new FunctionDeclaration(
				$name,
				isset( $tool['description'] ) ? (string) $tool['description'] : '',
				$schema
			);
		}

		return $declarations;
	}

	/**
	 * Keep JSON Schema keyword maps encoding as objects, not as arrays.
	 *
	 * PHP cannot tell an empty map from an empty list, so a tool that takes no
	 * arguments arrives as `properties => array()` and would be sent to the
	 * provider as `"properties": []`. Providers validate the schema and reject
	 * the whole request ("[] is not of type 'object'"), which surfaced as a 503
	 * on every chat turn that declared a no-argument tool.
	 *
	 * Only keywords whose value is a map of schemas are touched; `required`,
	 * `enum`, `anyOf` and friends stay lists.
	 *
	 * @param array<string, mixed> $schema A JSON Schema fragment.
	 *
	 * @return array<string, mixed>
	 */
	private function schema_maps_as_objects( array $schema ): array {
		$maps = array( 'properties', 'patternProperties', 'definitions', '$defs', 'dependentSchemas' );

		foreach ( $schema as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( in_array( $key, $maps, true ) ) {
				$schema[ $key ] = array() === $value
					? (object) array()
					: array_map(
						fn( $child ) => is_array( $child ) ? $this->schema_maps_as_objects( $child ) : $child,
						$value
					);

				continue;
			}

			$schema[ $key ] = $this->schema_maps_as_objects( $value );
		}

		return $schema;
	}

	/**
	 * Convert the assistant's content-block messages into AI Client messages.
	 *
	 * @param array<int, array<string, mixed>> $messages Conversation messages.
	 *
	 * @return Message[]
	 */
	private function to_wp_messages( array $messages ): array {
		$converted = array();

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			$role_name = isset( $message['role'] ) ? (string) $message['role'] : '';
			$role      = 'assistant' === $role_name ? MessageRoleEnum::model() : MessageRoleEnum::user();
			$parts     = array();

			foreach ( (array) ( $message['content'] ?? array() ) as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}

				$type = isset( $block['type'] ) ? (string) $block['type'] : '';

				if ( 'text' === $type ) {
					$text = isset( $block['text'] ) ? (string) $block['text'] : '';

					if ( '' !== $text ) {
						$parts[] = new MessagePart( $text );
					}

					continue;
				}

				if ( 'file' === $type || 'image' === $type ) {
					$file = $this->file_part_from_block( $block );

					if ( null !== $file ) {
						$parts[] = new MessagePart( $file );
					} else {
						$name = isset( $block['name'] ) ? (string) $block['name'] : '';
						$url  = isset( $block['url'] ) ? (string) $block['url'] : '';
						$note = __( 'Attached file', 'asdevs-ai-assistant' );

						if ( '' !== $name ) {
							$note .= ' "' . $name . '"';
						}

						if ( '' !== $url ) {
							$note .= ': ' . $url;
						}

						$parts[] = new MessagePart( $note );
					}

					continue;
				}

				if ( 'thinking' === $type ) {
					$text      = isset( $block['thinking'] ) ? (string) $block['thinking'] : '';
					$signature = isset( $block['signature'] ) ? (string) $block['signature'] : '';

					$parts[] = new MessagePart(
						$text,
						MessagePartChannelEnum::thought(),
						'' !== $signature ? $signature : null
					);

					continue;
				}

				if ( 'tool_use' === $type ) {
					$id   = isset( $block['id'] ) && '' !== (string) $block['id'] ? (string) $block['id'] : null;
					$name = isset( $block['name'] ) && '' !== (string) $block['name'] ? (string) $block['name'] : null;

					if ( null === $id && null === $name ) {
						continue;
					}

					$parts[] = new MessagePart(
						new FunctionCall( $id, $name, $block['input'] ?? array() )
					);

					continue;
				}

				if ( 'tool_result' === $type ) {
					$id = isset( $block['tool_use_id'] ) && '' !== (string) $block['tool_use_id']
						? (string) $block['tool_use_id']
						: null;

					if ( null === $id ) {
						continue;
					}

					$parts[] = new MessagePart(
						new FunctionResponse( $id, null, $block['content'] ?? '' )
					);
				}
			}

			if ( array() !== $parts ) {
				$converted[] = new Message( $role, $parts );
			}
		}

		return $converted;
	}

	/**
	 * Build an AI Client File from a browser-supplied content block.
	 *
	 * Prefer a readable local path so providers receive inline data (required
	 * when the media URL is only reachable on localhost).
	 *
	 * @param array<string, mixed> $block Content block.
	 */
	private function file_part_from_block( array $block ): ?File {
		$url  = isset( $block['url'] ) ? esc_url_raw( (string) $block['url'] ) : '';
		$mime = isset( $block['mime_type'] ) ? sanitize_mime_type( (string) $block['mime_type'] ) : '';

		if ( '' === $url ) {
			return null;
		}

		if ( ! $this->is_allowed_upload_url( $url ) ) {
			return null;
		}

		$mime_or_null = '' !== $mime ? $mime : null;
		$local        = $this->local_path_for_upload_url( $url );

		try {
			if ( null !== $local ) {
				return new File( $local, $mime_or_null );
			}

			return new File( $url, $mime_or_null );
		} catch ( \Throwable ) {
			return null;
		}
	}

	/**
	 * Whether a URL points at this site's uploads directory.
	 *
	 * @param string $url Candidate media URL.
	 */
	private function is_allowed_upload_url( string $url ): bool {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
			return false;
		}

		$base = trailingslashit( (string) $uploads['baseurl'] );

		return 0 === stripos( $url, $base );
	}

	/**
	 * Map an uploads URL to a readable filesystem path when possible.
	 *
	 * @param string $url Media URL from this site.
	 */
	private function local_path_for_upload_url( string $url ): ?string {
		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id > 0 ) {
			$path = get_attached_file( $attachment_id );

			if ( is_string( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}

		$baseurl = trailingslashit( (string) $uploads['baseurl'] );
		$basedir = wp_normalize_path( trailingslashit( (string) $uploads['basedir'] ) );

		if ( 0 !== stripos( $url, $baseurl ) ) {
			return null;
		}

		$relative = rawurldecode( substr( $url, strlen( $baseurl ) ) );
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		if ( '' === $relative || str_contains( $relative, '..' ) ) {
			return null;
		}

		$path = wp_normalize_path( $basedir . $relative );

		if ( 0 !== strpos( $path, $basedir ) || ! is_readable( $path ) ) {
			return null;
		}

		return $path;
	}

	/**
	 * Emit normalized stream events from a completed AI Client message.
	 *
	 * @param Message  $message Completed model message.
	 * @param string   $reason  Finish reason value.
	 * @param callable $emit    Event sink.
	 */
	private function emit_result( Message $message, string $reason, callable $emit ): void {
		foreach ( $message->getParts() as $part ) {
			$type = $part->getType();

			if ( $type->isText() ) {
				$text = (string) $part->getText();

				if ( $part->getChannel()->isThought() ) {
					if ( '' !== $text ) {
						$emit(
							array(
								'type' => 'thinking',
								'text' => $text,
							)
						);
					}

					$emit(
						array(
							'type'      => 'thinking_end',
							'kind'      => 'thinking',
							'thinking'  => $text,
							'signature' => (string) ( $part->getThoughtSignature() ?? '' ),
							'data'      => '',
						)
					);

					continue;
				}

				if ( '' !== $text ) {
					$emit(
						array(
							'type' => 'text',
							'text' => $text,
						)
					);
				}

				continue;
			}

			if ( $type->isFunctionCall() ) {
				$call = $part->getFunctionCall();

				if ( null === $call ) {
					continue;
				}

				$args = $call->getArgs();

				if ( is_string( $args ) ) {
					$decoded = json_decode( $args, true );
					$args    = is_array( $decoded ) ? $decoded : array();
				}

				if ( ! is_array( $args ) ) {
					$args = array();
				}

				$emit(
					array(
						'type'      => 'tool_call',
						'id'        => (string) ( $call->getId() ?? '' ),
						'name'      => (string) ( $call->getName() ?? '' ),
						'arguments' => $args,
					)
				);
			}
		}

		$emit(
			array(
				'type'   => 'done',
				'reason' => 'tool_calls' === $reason ? 'tool_use' : $reason,
			)
		);
	}

	/**
	 * Map an AI Client error into a short user-facing sentence.
	 *
	 * @param string|int $code   Error code.
	 * @param int        $status HTTP-ish status when present.
	 */
	private function user_message_for_error( $code, int $status ): string {
		if ( 401 === $status || 403 === $status || 'unauthorized' === $code || 'forbidden' === $code ) {
			return __( 'The assistant could not connect. Check the connector under Settings → Connectors when you have a moment.', 'asdevs-ai-assistant' );
		}

		if ( 429 === $status ) {
			return __( 'The assistant is a little busy right now. We can try again in a moment.', 'asdevs-ai-assistant' );
		}

		return __( 'Couldn’t reach the assistant just now. We can try again in a moment.', 'asdevs-ai-assistant' );
	}
}
