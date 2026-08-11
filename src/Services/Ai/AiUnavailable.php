<?php
/**
 * The AI service could not answer.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries a message the user can actually read.
 *
 * Section 14.2 and 20.4: no status codes, no provider names, no raw error
 * text reaches the conversation.
 */
final class AiUnavailable extends \RuntimeException {

	/**
	 * Technical detail, available on request but never shown by default.
	 *
	 * @var string
	 */
	private string $detail;

	/**
	 * Whether trying again shortly is likely to help.
	 *
	 * @var bool
	 */
	private bool $retryable;

	/**
	 * Constructor.
	 *
	 * @param string $message   Message for the user.
	 * @param string $detail    Technical detail for support.
	 * @param bool   $retryable Whether retrying may help.
	 */
	public function __construct( string $message, string $detail = '', bool $retryable = true ) {
		parent::__construct( $message );

		$this->detail    = $detail;
		$this->retryable = $retryable;
	}

	/**
	 * Technical detail for support.
	 */
	public function detail(): string {
		return $this->detail;
	}

	/**
	 * Whether retrying may help.
	 */
	public function is_retryable(): bool {
		return $this->retryable;
	}
}
