<?php
/**
 * A request to the model.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider-independent description of one turn.
 */
final class ChatRequest {

	/**
	 * System instructions.
	 *
	 * @var string
	 */
	private string $system;

	/**
	 * Conversation messages.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $messages;

	/**
	 * Tool definitions.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $tools;

	/**
	 * Explicit model id for the active provider, or empty for auto-select.
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * JSON schema the answer must match, or null for free-form text.
	 *
	 * Set when an agent asks for structured output (the planner does). The
	 * provider passes it to the model so the reply is machine-readable.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $output_schema;

	/**
	 * Sampling temperature, or null to leave the provider default alone.
	 *
	 * @var float|null
	 */
	private ?float $temperature;

	/**
	 * Constructor.
	 *
	 * @param string                           $system        System instructions.
	 * @param array<int, array<string, mixed>> $messages      Conversation messages.
	 * @param array<int, array<string, mixed>> $tools         Tool definitions.
	 * @param string                           $model         Provider model id, or empty for auto.
	 * @param array<string, mixed>|null        $output_schema JSON schema for the answer, or null.
	 * @param float|null                       $temperature   Sampling temperature, or null.
	 */
	public function __construct(
		string $system,
		array $messages,
		array $tools = array(),
		string $model = '',
		?array $output_schema = null,
		?float $temperature = null
	) {
		$this->system        = $system;
		$this->messages      = $messages;
		$this->tools         = $tools;
		$this->model         = $model;
		$this->output_schema = $output_schema;
		$this->temperature   = $temperature;
	}

	/**
	 * System instructions.
	 */
	public function system(): string {
		return $this->system;
	}

	/**
	 * Conversation messages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function messages(): array {
		return $this->messages;
	}

	/**
	 * Tool definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function tools(): array {
		return $this->tools;
	}

	/**
	 * Explicit model id, or empty when the AI Client should choose.
	 */
	public function model(): string {
		return $this->model;
	}

	/**
	 * JSON schema the answer must match, or null for free-form text.
	 *
	 * @return array<string, mixed>|null
	 */
	public function output_schema(): ?array {
		return $this->output_schema;
	}

	/**
	 * Sampling temperature, or null for the provider default.
	 */
	public function temperature(): ?float {
		return $this->temperature;
	}
}
