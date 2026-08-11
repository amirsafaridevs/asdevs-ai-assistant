<?php
/**
 * The risk policy is the promise the product makes about trust.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Tests;

use ASDevs\AIAssistant\Services\Security\ActionRequest;
use ASDevs\AIAssistant\Services\Security\BulkGuard;
use ASDevs\AIAssistant\Services\Security\NeverRules;
use ASDevs\AIAssistant\Services\Security\RiskLevel;
use ASDevs\AIAssistant\Services\Security\RiskPolicy;
use ASDevs\AIAssistant\Services\Security\RouteInspector;
use PHPUnit\Framework\TestCase;

/**
 * Covers section 16.1 of the product vision.
 */
final class RiskPolicyTest extends TestCase {

	/**
	 * Policy under test.
	 *
	 * @var RiskPolicy
	 */
	private RiskPolicy $policy;

	/**
	 * The acting user.
	 */
	private const USER_ID = 7;

	/**
	 * Set up.
	 */
	protected function setUp(): void {
		asdevs_ai_test_reset();
		asdevs_ai_test_register_post_type( 'post', 'posts' );

		$routes = new RouteInspector();

		$this->policy = new RiskPolicy( $routes, new NeverRules( $routes ), new BulkGuard() );
	}

	/**
	 * Assess one action.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $params Parameters.
	 */
	private function assess( string $method, string $route, array $params = array() ) {
		return $this->policy->assess( new ActionRequest( $method, $route, $params ), self::USER_ID );
	}

	/**
	 * Reading never asks.
	 */
	public function test_reading_is_level_one(): void {
		$assessment = $this->assess( 'GET', '/wp/v2/posts' );

		$this->assertSame( RiskLevel::READ, $assessment->level() );
		$this->assertFalse( $assessment->needs_confirmation() );
	}

	/**
	 * A draft is a reversible change, so it runs and is reported.
	 */
	public function test_creating_a_draft_is_level_two(): void {
		$assessment = $this->assess( 'POST', '/wp/v2/posts', array( 'title' => 'Coffee' ) );

		$this->assertSame( RiskLevel::REVERSIBLE, $assessment->level() );
		$this->assertFalse( $assessment->needs_confirmation() );
	}

	/**
	 * Publishing reaches the public site, so it always asks.
	 */
	public function test_publishing_is_level_three(): void {
		$assessment = $this->assess( 'POST', '/wp/v2/posts/12', array( 'status' => 'publish' ) );

		$this->assertTrue( $assessment->needs_confirmation() );
	}

	/**
	 * Scheduling reaches the public site too.
	 */
	public function test_scheduling_is_level_three(): void {
		$this->assertTrue( $this->assess( 'POST', '/wp/v2/posts/12', array( 'status' => 'future' ) )->needs_confirmation() );
	}

	/**
	 * The trash is reversible; permanent deletion is not.
	 */
	public function test_trash_and_permanent_delete_differ(): void {
		$trashed = $this->assess( 'DELETE', '/wp/v2/posts/12' );
		$forced  = $this->assess( 'DELETE', '/wp/v2/posts/12', array( 'force' => true ) );

		$this->assertFalse( $trashed->needs_confirmation() );
		$this->assertTrue( $trashed->is_reversible() );

		$this->assertTrue( $forced->needs_confirmation() );
		$this->assertFalse( $forced->is_reversible() );
	}

	/**
	 * Deleting a user cannot be undone, so it always asks.
	 */
	public function test_deleting_a_user_always_asks(): void {
		$this->assertTrue( $this->assess( 'DELETE', '/wp/v2/users/9' )->needs_confirmation() );
	}

	/**
	 * Changing a role always asks, even for an administrator.
	 */
	public function test_changing_a_role_always_asks(): void {
		$assessment = $this->assess( 'POST', '/wp/v2/users/9', array( 'roles' => array( 'editor' ) ) );

		$this->assertTrue( $assessment->needs_confirmation() );
	}

	/**
	 * Site-wide settings always ask.
	 */
	public function test_settings_always_ask(): void {
		$this->assertTrue( $this->assess( 'POST', '/wp/v2/settings', array( 'title' => 'Shop' ) )->needs_confirmation() );
	}

	/**
	 * Deactivating a plugin is reversible; activating one runs new code.
	 */
	public function test_plugin_activation_asks_and_deactivation_does_not(): void {
		$off = $this->assess( 'PUT', '/wp/v2/plugins/hello', array( 'status' => 'inactive' ) );
		$on  = $this->assess( 'PUT', '/wp/v2/plugins/hello', array( 'status' => 'active' ) );

		$this->assertFalse( $off->needs_confirmation() );
		$this->assertTrue( $on->needs_confirmation() );
	}

	/**
	 * Several changes in one request are a bulk change.
	 */
	public function test_a_batch_of_changes_asks_once(): void {
		$assessment = $this->policy->assess_batch(
			array(
				new ActionRequest( 'POST', '/wp/v2/posts/1', array( 'categories' => array( 2 ) ) ),
				new ActionRequest( 'POST', '/wp/v2/posts/2', array( 'categories' => array( 2 ) ) ),
			),
			self::USER_ID
		);

		$this->assertTrue( $assessment->needs_confirmation() );
		$this->assertSame( 2, $assessment->affected() );
	}

	/**
	 * A loop of small changes becomes a bulk change and starts asking.
	 */
	public function test_repeated_small_changes_start_asking(): void {
		$guard  = new BulkGuard();
		$routes = new RouteInspector();
		$policy = new RiskPolicy( $routes, new NeverRules( $routes ), $guard );

		for ( $index = 1; $index <= 3; $index++ ) {
			$action     = new ActionRequest( 'POST', '/wp/v2/posts/' . $index, array( 'categories' => array( 2 ) ) );
			$assessment = $policy->assess( $action, self::USER_ID );

			$this->assertFalse( $assessment->needs_confirmation(), 'Change ' . $index . ' should still run freely.' );

			$guard->record( $action, self::USER_ID );
		}

		$fourth = new ActionRequest( 'POST', '/wp/v2/posts/4', array( 'categories' => array( 2 ) ) );

		$this->assertTrue( $policy->assess( $fourth, self::USER_ID )->needs_confirmation() );
	}
}
