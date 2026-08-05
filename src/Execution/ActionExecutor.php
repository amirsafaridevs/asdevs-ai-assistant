<?php
/**
 * Runs assistant actions against the site.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Execution;

use ASDevs\AIAssistant\Security\ActionRequest;
use ASDevs\AIAssistant\Security\BulkGuard;
use ASDevs\AIAssistant\Security\ConfirmationTokens;
use ASDevs\AIAssistant\Security\RiskPolicy;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single place where the assistant is allowed to change the site.
 *
 * Every action goes through the never list, the risk policy and the
 * confirmation check, and is then dispatched through the site's own REST
 * layer so WordPress itself enforces the acting user's capabilities. There is
 * no path around this class.
 */
final class ActionExecutor {

	/**
	 * Risk policy.
	 *
	 * @var RiskPolicy
	 */
	private RiskPolicy $policy;

	/**
	 * Confirmation tokens.
	 *
	 * @var ConfirmationTokens
	 */
	private ConfirmationTokens $tokens;

	/**
	 * Repeated-write guard.
	 *
	 * @var BulkGuard
	 */
	private BulkGuard $bulk_guard;

	/**
	 * Panel link resolver.
	 *
	 * @var LinkResolver
	 */
	private LinkResolver $links;

	/**
	 * Constructor.
	 *
	 * @param RiskPolicy         $policy     Risk policy.
	 * @param ConfirmationTokens $tokens     Confirmation tokens.
	 * @param BulkGuard          $bulk_guard Repeated-write guard.
	 * @param LinkResolver       $links      Panel link resolver.
	 */
	public function __construct(
		RiskPolicy $policy,
		ConfirmationTokens $tokens,
		BulkGuard $bulk_guard,
		LinkResolver $links
	) {
		$this->policy     = $policy;
		$this->tokens     = $tokens;
		$this->bulk_guard = $bulk_guard;
		$this->links      = $links;
	}

	/**
	 * Run one action for the current user.
	 *
	 * @param ActionRequest $action       The action.
	 * @param string        $confirmation Confirmation token, when the user has confirmed.
	 *
	 * @return array<string, mixed> Outcome describing what happened.
	 */
	public function run( ActionRequest $action, string $confirmation = '' ): array {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return $this->refusal( __( 'You are signed out. Sign in again and I will continue.', 'asdevs-ai-assistant' ) );
		}

		if ( $this->is_own_namespace( $action ) ) {
			return $this->refusal( __( 'That is not something on your site I can work with.', 'asdevs-ai-assistant' ) );
		}

		$assessment = $this->policy->assess( $action, $user_id );

		if ( $assessment->is_blocked() ) {
			return $this->refusal( $assessment->summary() );
		}

		if ( $assessment->needs_confirmation() ) {
			$fingerprint = $action->fingerprint();

			if ( ! $this->tokens->redeem( $confirmation, $fingerprint, $user_id ) ) {
				return array(
					'status'       => 'confirmation_required',
					'assessment'   => $assessment->to_array(),
					'confirmation' => $this->tokens->issue( $fingerprint, $user_id ),
					'action'       => array(
						'method' => $action->method(),
						'route'  => $action->route(),
					),
				);
			}

			$this->bulk_guard->reset( $action, $user_id );
		}

		$outcome = $this->dispatch( $action );

		if ( 'ok' === $outcome['status'] && ! $assessment->needs_confirmation() ) {
			$this->bulk_guard->record( $action, $user_id );
		}

		$outcome['assessment'] = $assessment->to_array();

		return $outcome;
	}

	/**
	 * Dispatch the action through the site's REST layer.
	 *
	 * @param ActionRequest $action The action.
	 *
	 * @return array<string, mixed>
	 */
	private function dispatch( ActionRequest $action ): array {
		$request = new WP_REST_Request( $action->method(), $action->route() );

		foreach ( $action->params() as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $this->failure( $response->as_error() );
		}

		$data = $response->get_data();

		return array(
			'status' => 'ok',
			'code'   => $response->get_status(),
			'data'   => $data,
			'total'  => $this->total_from( $response->get_headers() ),
			'links'  => $this->links->for_result( $action, $data ),
		);
	}

	/**
	 * Turn a REST error into an outcome the assistant can explain.
	 *
	 * @param WP_Error $error The error.
	 *
	 * @return array<string, mixed>
	 */
	private function failure( WP_Error $error ): array {
		$code   = $error->get_error_code();
		$status = $error->get_error_data();
		$status = is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 0;

		$kind = 'unknown';

		if ( in_array( $status, array( 401, 403 ), true ) || str_contains( (string) $code, 'cannot' ) ) {
			$kind = 'forbidden';
		} elseif ( 404 === $status ) {
			$kind = 'not_found';
		} elseif ( 400 === $status || str_contains( (string) $code, 'invalid' ) ) {
			$kind = 'invalid';
		} elseif ( $status >= 500 ) {
			$kind = 'site_unavailable';
		}

		return array(
			'status'  => 'failed',
			'kind'    => $kind,
			'message' => $error->get_error_message(),
			'details' => array(
				'code'        => (string) $code,
				'http_status' => $status,
			),
		);
	}

	/**
	 * A refusal outcome.
	 *
	 * @param string $reason Why, in plain language.
	 *
	 * @return array<string, mixed>
	 */
	private function refusal( string $reason ): array {
		return array(
			'status'  => 'refused',
			'message' => $reason,
		);
	}

	/**
	 * Total item count reported by a collection response.
	 *
	 * @param array<string, mixed> $headers Response headers.
	 */
	private function total_from( array $headers ): ?int {
		foreach ( $headers as $name => $value ) {
			if ( 0 === strcasecmp( (string) $name, 'X-WP-Total' ) ) {
				return (int) $value;
			}
		}

		return null;
	}

	/**
	 * Whether the action points back at this plugin's own endpoints.
	 *
	 * @param ActionRequest $action The action.
	 */
	private function is_own_namespace( ActionRequest $action ): bool {
		return str_starts_with( $action->route(), '/asdevs-ai/' );
	}
}
