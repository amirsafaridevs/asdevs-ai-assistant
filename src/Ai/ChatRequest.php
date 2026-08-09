<?php
/**
 * A request to the model.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Ai;

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
	 * Constructor.
	 *
	 * @param string                           $system   System instructions.
	 * @param array<int, array<string, mixed>> $messages Conversation messages.
	 * @param array<int, array<string, mixed>> $tools    Tool definitions.
	 * @param string                           $model    Provider model id, or empty for auto.
	 */
	public function __construct( string $system, array $messages, array $tools = array(), string $model = '' ) {
		$this->system   = $system;
		$this->messages = $messages;
		$this->tools    = $tools;
		$this->model    = $model;
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
}
