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
	 * Constructor.
	 *
	 * @param string                           $system   System instructions.
	 * @param array<int, array<string, mixed>> $messages Conversation messages.
	 * @param array<int, array<string, mixed>> $tools    Tool definitions.
	 */
	public function __construct( string $system, array $messages, array $tools = array() ) {
		$this->system   = $system;
		$this->messages = $messages;
		$this->tools    = $tools;
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
}
