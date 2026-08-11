<?php
/**
 * The refusals that no wording can talk past.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Tests;

use ASDevs\AIAssistant\Services\Security\ActionRequest;
use ASDevs\AIAssistant\Services\Security\NeverRules;
use ASDevs\AIAssistant\Services\Security\RouteInspector;
use PHPUnit\Framework\TestCase;

/**
 * Covers section 16.3 of the product vision.
 */
final class NeverRulesTest extends TestCase {

	/**
	 * Rules under test.
	 *
	 * @var NeverRules
	 */
	private NeverRules $rules;

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

		$this->rules = new NeverRules( new RouteInspector() );
	}

	/**
	 * Whether an action is refused.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $params Parameters.
	 */
	private function refuses( string $method, string $route, array $params = array() ): bool {
		return $this->rules->refuses( new ActionRequest( $method, $route, $params ), self::USER_ID );
	}

	/**
	 * Signing yourself out with no way back is not something to offer.
	 */
	public function test_deleting_your_own_account_is_refused(): void {
		$this->assertTrue( $this->refuses( 'DELETE', '/wp/v2/users/7' ) );
		$this->assertTrue( $this->refuses( 'DELETE', '/wp/v2/users/me' ) );
	}

	/**
	 * Deleting somebody else's account is allowed to be considered.
	 */
	public function test_deleting_another_account_is_not_refused(): void {
		$this->assertFalse( $this->refuses( 'DELETE', '/wp/v2/users/9' ) );
	}

	/**
	 * Nobody raises their own access through the assistant.
	 */
	public function test_changing_your_own_access_is_refused(): void {
		$this->assertTrue( $this->refuses( 'POST', '/wp/v2/users/7', array( 'roles' => array( 'administrator' ) ) ) );
		$this->assertTrue( $this->refuses( 'POST', '/wp/v2/users/me', array( 'roles' => array( 'administrator' ) ) ) );
	}

	/**
	 * Changing your own display name is ordinary work.
	 */
	public function test_changing_your_own_name_is_not_refused(): void {
		$this->assertFalse( $this->refuses( 'POST', '/wp/v2/users/7', array( 'name' => 'Maryam' ) ) );
	}

	/**
	 * The site address can take the whole site offline.
	 */
	public function test_changing_the_site_address_is_refused(): void {
		$this->assertTrue( $this->refuses( 'POST', '/wp/v2/settings', array( 'url' => 'https://example.test' ) ) );
	}

	/**
	 * The site title is a normal setting, judged by the risk policy instead.
	 */
	public function test_changing_the_site_title_is_not_refused(): void {
		$this->assertFalse( $this->refuses( 'POST', '/wp/v2/settings', array( 'title' => 'Maryam Shop' ) ) );
	}

	/**
	 * Installing code is out of scope for version one.
	 */
	public function test_installing_code_is_refused(): void {
		$this->assertTrue( $this->refuses( 'POST', '/wp/v2/plugins', array( 'slug' => 'wordpress-seo' ) ) );
		$this->assertTrue( $this->refuses( 'POST', '/wp/v2/themes', array( 'slug' => 'twentytwentyfive' ) ) );
	}

	/**
	 * Activating an already installed plugin is not an install.
	 */
	public function test_activating_an_installed_plugin_is_not_refused(): void {
		$this->assertFalse( $this->refuses( 'PUT', '/wp/v2/plugins/hello', array( 'status' => 'active' ) ) );
	}

	/**
	 * "Delete everything" is never one call.
	 */
	public function test_wiping_a_collection_is_refused(): void {
		$this->assertTrue( $this->refuses( 'DELETE', '/wp/v2/posts' ) );
		$this->assertTrue( $this->refuses( 'DELETE', '/wp/v2/users' ) );
	}

	/**
	 * Messaging the site's members is not the assistant's job.
	 */
	public function test_bulk_messaging_is_refused(): void {
		$this->assertTrue( $this->refuses( 'POST', '/newsletter/v1/campaigns/3/send' ) );
	}

	/**
	 * Site files are never written.
	 */
	public function test_writing_site_files_is_refused(): void {
		$this->assertTrue( $this->refuses( 'POST', '/some-plugin/v1/file-editor' ) );
	}

	/**
	 * Reading is never refused by this layer.
	 */
	public function test_reading_is_never_refused(): void {
		$this->assertFalse( $this->refuses( 'GET', '/wp/v2/users/7' ) );
		$this->assertFalse( $this->refuses( 'GET', '/wp/v2/settings' ) );
	}
}
