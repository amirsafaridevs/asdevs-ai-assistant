<?php
/**
 * Risk classification.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides the risk level of an action, on the server, before it runs.
 *
 * The model never influences this decision: it only proposes the action.
 */
final class RiskPolicy {

	/**
	 * Route inspector.
	 *
	 * @var RouteInspector
	 */
	private RouteInspector $routes;

	/**
	 * The never list.
	 *
	 * @var NeverRules
	 */
	private NeverRules $never;

	/**
	 * Repeated-write guard.
	 *
	 * @var BulkGuard
	 */
	private BulkGuard $bulk_guard;

	/**
	 * Constructor.
	 *
	 * @param RouteInspector $routes     Route inspector.
	 * @param NeverRules     $never      The never list.
	 * @param BulkGuard      $bulk_guard Repeated-write guard.
	 */
	public function __construct( RouteInspector $routes, NeverRules $never, BulkGuard $bulk_guard ) {
		$this->routes     = $routes;
		$this->never      = $never;
		$this->bulk_guard = $bulk_guard;
	}

	/**
	 * Assess a single action.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	public function assess( ActionRequest $action, int $user_id ): RiskAssessment {
		$refusal = $this->never->refusal_reason( $action, $user_id );

		if ( null !== $refusal ) {
			return RiskAssessment::refused( $refusal );
		}

		if ( $action->is_read() ) {
			return new RiskAssessment( RiskLevel::READ, __( 'Reads information only.', 'asdevs-ai-assistant' ) );
		}

		$assessment = $this->assess_write( $action, $user_id );

		if ( $assessment->level()->runs_unattended() && $this->bulk_guard->is_repeating( $action, $user_id ) ) {
			return new RiskAssessment(
				RiskLevel::CONFIRMED,
				__( 'This is part of a bulk change across several items.', 'asdevs-ai-assistant' ),
				$assessment->is_reversible()
			);
		}

		return $assessment;
	}

	/**
	 * Assess a batch of actions submitted together.
	 *
	 * More than one change in a single request is always a bulk change.
	 *
	 * @param ActionRequest[] $actions The actions.
	 * @param int             $user_id The acting user.
	 */
	public function assess_batch( array $actions, int $user_id ): RiskAssessment {
		$writes     = 0;
		$reversible = true;

		foreach ( $actions as $action ) {
			$assessment = $this->assess( $action, $user_id );

			if ( $assessment->is_blocked() ) {
				return $assessment;
			}

			if ( ! $action->is_read() ) {
				++$writes;
				$reversible = $reversible && $assessment->is_reversible();
			}
		}

		if ( $writes > 1 ) {
			return new RiskAssessment(
				RiskLevel::CONFIRMED,
				sprintf(
					/* translators: %s: number of items, already localized. */
					__( '%s items will be changed.', 'asdevs-ai-assistant' ),
					number_format_i18n( $writes )
				),
				$reversible,
				false,
				$writes
			);
		}

		if ( 1 === $writes ) {
			foreach ( $actions as $action ) {
				if ( ! $action->is_read() ) {
					return $this->assess( $action, $user_id );
				}
			}
		}

		return new RiskAssessment( RiskLevel::READ, __( 'Reads information only.', 'asdevs-ai-assistant' ) );
	}

	/**
	 * Classify a write action.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function assess_write( ActionRequest $action, int $user_id ): RiskAssessment {
		unset( $user_id );

		if ( 'DELETE' === $action->method() ) {
			return $this->assess_delete( $action );
		}

		if ( $this->routes->is_settings_route( $action ) ) {
			return new RiskAssessment(
				RiskLevel::CONFIRMED,
				__( 'Changes a site-wide setting.', 'asdevs-ai-assistant' )
			);
		}

		if ( $this->routes->is_user_route( $action ) && $this->touches_access( $action ) ) {
			return new RiskAssessment(
				RiskLevel::CONFIRMED,
				__( 'Changes what this person can do on the site.', 'asdevs-ai-assistant' )
			);
		}

		if ( $this->routes->is_plugin_route( $action ) && 'active' === $action->param( 'status' ) ) {
			return new RiskAssessment(
				RiskLevel::CONFIRMED,
				__( 'Activates a plugin, which starts running its code on the site.', 'asdevs-ai-assistant' )
			);
		}

		if ( $this->goes_public( $action ) ) {
			return new RiskAssessment(
				RiskLevel::CONFIRMED,
				__( 'Makes content visible on the public site.', 'asdevs-ai-assistant' )
			);
		}

		return new RiskAssessment(
			RiskLevel::REVERSIBLE,
			__( 'Changes something that can be changed back.', 'asdevs-ai-assistant' )
		);
	}

	/**
	 * Classify a delete action.
	 *
	 * @param ActionRequest $action The action.
	 */
	private function assess_delete( ActionRequest $action ): RiskAssessment {
		$forced = $this->is_truthy( $action->param( 'force' ) );

		if ( ! $forced && $this->routes->supports_trash( $action ) ) {
			return new RiskAssessment(
				RiskLevel::REVERSIBLE,
				__( 'Moves the item to the trash, where it can be restored.', 'asdevs-ai-assistant' ),
				true
			);
		}

		return new RiskAssessment(
			RiskLevel::CONFIRMED,
			__( 'Deletes the item permanently. This cannot be undone.', 'asdevs-ai-assistant' ),
			false
		);
	}

	/**
	 * Whether the action changes roles, capabilities or credentials.
	 *
	 * @param ActionRequest $action The action.
	 */
	private function touches_access( ActionRequest $action ): bool {
		foreach ( array( 'roles', 'capabilities', 'password', 'email' ) as $key ) {
			if ( null !== $action->param( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the action publishes content to the public site.
	 *
	 * @param ActionRequest $action The action.
	 */
	private function goes_public( ActionRequest $action ): bool {
		$status = $action->param( 'status' );

		if ( is_string( $status ) && in_array( $status, array( 'publish', 'future', 'private' ), true ) ) {
			return true;
		}

		return $this->is_truthy( $action->param( 'sticky' ) );
	}

	/**
	 * Loose truthiness matching REST boolean handling.
	 *
	 * @param mixed $value The value.
	 */
	private function is_truthy( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'yes' ), true );
	}
}
