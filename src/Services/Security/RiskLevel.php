<?php
/**
 * Risk levels.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three risk levels of section 16.1 of the product vision.
 */
enum RiskLevel: int {

	/**
	 * Reads only. Runs without asking.
	 */
	case READ = 1;

	/**
	 * Reversible change. Runs without asking, reported explicitly.
	 */
	case REVERSIBLE = 2;

	/**
	 * Risky or irreversible. Always requires explicit confirmation.
	 */
	case CONFIRMED = 3;

	/**
	 * Whether this level may run without an explicit user confirmation.
	 */
	public function runs_unattended(): bool {
		return self::CONFIRMED !== $this;
	}
}
