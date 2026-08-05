<?php
/**
 * Result of assessing one action.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the server decided about an action before running it.
 */
final class RiskAssessment {

	/**
	 * Risk level.
	 *
	 * @var RiskLevel
	 */
	private RiskLevel $level;

	/**
	 * Whether the action is refused outright.
	 *
	 * @var bool
	 */
	private bool $blocked;

	/**
	 * Whether the change can be undone.
	 *
	 * @var bool
	 */
	private bool $reversible;

	/**
	 * Human sentence describing what will happen, in the user's language.
	 *
	 * @var string
	 */
	private string $summary;

	/**
	 * Number of items affected, when known.
	 *
	 * @var int|null
	 */
	private ?int $affected;

	/**
	 * Constructor.
	 *
	 * @param RiskLevel $level      Risk level.
	 * @param string    $summary    What will happen, in plain language.
	 * @param bool      $reversible Whether it can be undone.
	 * @param bool      $blocked    Whether it is refused outright.
	 * @param int|null  $affected   Items affected.
	 */
	public function __construct(
		RiskLevel $level,
		string $summary,
		bool $reversible = true,
		bool $blocked = false,
		?int $affected = null
	) {
		$this->level      = $level;
		$this->summary    = $summary;
		$this->reversible = $reversible;
		$this->blocked    = $blocked;
		$this->affected   = $affected;
	}

	/**
	 * A refused action.
	 *
	 * @param string $reason Why it is refused, in plain language.
	 */
	public static function refused( string $reason ): self {
		return new self( RiskLevel::CONFIRMED, $reason, false, true );
	}

	/**
	 * Risk level.
	 */
	public function level(): RiskLevel {
		return $this->level;
	}

	/**
	 * Whether the action is refused.
	 */
	public function is_blocked(): bool {
		return $this->blocked;
	}

	/**
	 * Whether the change can be undone.
	 */
	public function is_reversible(): bool {
		return $this->reversible;
	}

	/**
	 * Whether an explicit user confirmation is required first.
	 */
	public function needs_confirmation(): bool {
		return ! $this->blocked && ! $this->level->runs_unattended();
	}

	/**
	 * What will happen, in plain language.
	 */
	public function summary(): string {
		return $this->summary;
	}

	/**
	 * Items affected, when known.
	 */
	public function affected(): ?int {
		return $this->affected;
	}

	/**
	 * Representation for the client.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'level'      => $this->level->value,
			'blocked'    => $this->blocked,
			'reversible' => $this->reversible,
			'summary'    => $this->summary,
			'affected'   => $this->affected,
		);
	}
}
