<?php
/**
 * The "never" list.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actions the assistant refuses regardless of who asks or how it is phrased.
 *
 * Section 16.3 of the product vision. These rules are enforced here, on the
 * server, and are deliberately not filterable: no wording, role play or
 * insistence in a conversation can reach past them.
 */
final class NeverRules {

	/**
	 * Route inspector.
	 *
	 * @var RouteInspector
	 */
	private RouteInspector $routes;

	/**
	 * Constructor.
	 *
	 * @param RouteInspector $routes Route inspector.
	 */
	public function __construct( RouteInspector $routes ) {
		$this->routes = $routes;
	}

	/**
	 * The reason this action is refused, or null when it is allowed to proceed.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	public function refusal_reason( ActionRequest $action, int $user_id ): ?string {
		if ( $action->is_read() ) {
			return null;
		}

		foreach ( $this->rules() as $rule ) {
			$reason = $rule( $action, $user_id );

			if ( null !== $reason ) {
				return $reason;
			}
		}

		return null;
	}

	/**
	 * Whether the action is refused.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	public function refuses( ActionRequest $action, int $user_id ): bool {
		return null !== $this->refusal_reason( $action, $user_id );
	}

	/**
	 * The rule set.
	 *
	 * @return callable[]
	 */
	private function rules(): array {
		return array(
			array( $this, 'rule_own_account' ),
			array( $this, 'rule_own_role' ),
			array( $this, 'rule_site_address' ),
			array( $this, 'rule_install_code' ),
			array( $this, 'rule_collection_wipe' ),
			array( $this, 'rule_bulk_mail' ),
			array( $this, 'rule_core_files' ),
		);
	}

	/**
	 * Never delete the account the user is signed in with.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_own_account( ActionRequest $action, int $user_id ): ?string {
		if ( ! $this->routes->is_user_route( $action ) || 'DELETE' !== $action->method() ) {
			return null;
		}

		if ( $this->targets_self( $action, $user_id ) ) {
			return __( 'Deleting your own account would sign you out with no way back. Do it from another administrator account if you really want to.', 'asdevs-ai-assistant' );
		}

		return null;
	}

	/**
	 * Never change the acting user's own role or capabilities.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_own_role( ActionRequest $action, int $user_id ): ?string {
		if ( ! $this->routes->is_user_route( $action ) ) {
			return null;
		}

		$touches_access = null !== $action->param( 'roles' ) || null !== $action->param( 'capabilities' );

		if ( $touches_access && $this->targets_self( $action, $user_id ) ) {
			return __( 'I do not change your own access level. Ask another administrator to do it.', 'asdevs-ai-assistant' );
		}

		return null;
	}

	/**
	 * Never change the site address.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_site_address( ActionRequest $action, int $user_id ): ?string {
		unset( $user_id );

		if ( ! $this->routes->is_settings_route( $action ) ) {
			return null;
		}

		foreach ( array( 'url', 'siteurl', 'home' ) as $key ) {
			if ( null !== $action->param( $key ) ) {
				return __( 'Changing the site address can cut off access to the whole site and cannot be undone from inside the admin. I will not change it; open Settings and change it yourself carefully.', 'asdevs-ai-assistant' );
			}
		}

		return null;
	}

	/**
	 * Never install plugins, themes or any other executable code.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_install_code( ActionRequest $action, int $user_id ): ?string {
		unset( $user_id );

		$installs_plugin = $this->routes->is_plugin_route( $action ) && 'POST' === $action->method() && ! $this->routes->is_single_object( $action );
		$installs_theme  = $this->routes->is_theme_route( $action ) && ! $action->is_read();

		if ( $installs_plugin || $installs_theme ) {
			return __( 'I do not install code on the site. I can open the install page for you instead.', 'asdevs-ai-assistant' );
		}

		return null;
	}

	/**
	 * Never delete a whole collection in one call.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_collection_wipe( ActionRequest $action, int $user_id ): ?string {
		unset( $user_id );

		if ( 'DELETE' !== $action->method() ) {
			return null;
		}

		if ( ! $this->routes->is_single_object( $action ) ) {
			return __( 'I do not delete a whole collection in one go. Tell me which specific items to remove.', 'asdevs-ai-assistant' );
		}

		return null;
	}

	/**
	 * Never send mail to the site's users in bulk.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_bulk_mail( ActionRequest $action, int $user_id ): ?string {
		unset( $user_id );

		if ( preg_match( '#(mail|newsletter|campaign|broadcast|bulk-?sms)#i', $action->route() ) ) {
			return __( 'I do not send messages to your site members. Use the plugin that owns that feature.', 'asdevs-ai-assistant' );
		}

		return null;
	}

	/**
	 * Never write to WordPress files.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function rule_core_files( ActionRequest $action, int $user_id ): ?string {
		unset( $user_id );

		if ( preg_match( '#(file-?editor|edit-?file|core/?(update|files)|wp-?config)#i', $action->route() ) ) {
			return __( 'I do not edit site files.', 'asdevs-ai-assistant' );
		}

		return null;
	}

	/**
	 * Whether the action targets the acting user's own account.
	 *
	 * @param ActionRequest $action  The action.
	 * @param int           $user_id The acting user.
	 */
	private function targets_self( ActionRequest $action, int $user_id ): bool {
		if ( preg_match( '#/wp/v2/users/me$#', $action->route() ) ) {
			return true;
		}

		$object_id = $this->routes->object_id( $action );

		return null !== $object_id && $object_id === $user_id;
	}
}
