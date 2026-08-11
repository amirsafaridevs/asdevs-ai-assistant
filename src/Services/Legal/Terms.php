<?php
/**
 * Terms of use presented before the assistant opens.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Legal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned terms text and per-user acceptance.
 *
 * Bump VERSION whenever the wording changes so people are asked again.
 */
final class Terms {

	/**
	 * Terms document version. Independent of the plugin version.
	 */
	public const VERSION = '1.0';

	/**
	 * User meta key for acceptance.
	 */
	public const META_KEY = 'asdevs_ai_assistant_terms';

	/**
	 * Current terms version.
	 */
	public function version(): string {
		return self::VERSION;
	}

	/**
	 * Whether the user has accepted the current terms version.
	 *
	 * @param int $user_id The user.
	 */
	public function has_accepted( int $user_id ): bool {
		return $this->accepted_version( $user_id ) === self::VERSION;
	}

	/**
	 * Version the user last accepted, or empty when never accepted.
	 *
	 * @param int $user_id The user.
	 */
	public function accepted_version( int $user_id ): string {
		$stored = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $stored ) || ! isset( $stored['version'] ) ) {
			return '';
		}

		return sanitize_text_field( (string) $stored['version'] );
	}

	/**
	 * Record acceptance of the current terms.
	 *
	 * @param int    $user_id The user.
	 * @param string $version Version the browser claims to have read.
	 *
	 * @return bool False when the version is stale or unknown.
	 */
	public function accept( int $user_id, string $version ): bool {
		if ( self::VERSION !== $version ) {
			return false;
		}

		update_user_meta(
			$user_id,
			self::META_KEY,
			array(
				'version'     => self::VERSION,
				'accepted_at' => time(),
			)
		);

		return true;
	}

	/**
	 * Drop stored acceptance for a user.
	 *
	 * @param int $user_id The user.
	 */
	public function clear( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Payload for the widget boot script and bootstrap response.
	 *
	 * @param int $user_id The user.
	 *
	 * @return array{version: string, accepted: bool, sections: list<array{heading: string, body: string}>}
	 */
	public function for_user( int $user_id ): array {
		$accepted = $this->has_accepted( $user_id );

		return array(
			'version'  => self::VERSION,
			'accepted' => $accepted,
			// Skip shipping the full text once the person has already agreed.
			'sections' => $accepted ? array() : $this->sections(),
		);
	}

	/**
	 * Concise legal sections for the gate screen.
	 *
	 * @return list<array{heading: string, body: string}>
	 */
	public function sections(): array {
		return array(
			array(
				'heading' => __( 'Who this is for', 'asdevs-ai-assistant' ),
				'body'    => __( 'ASDevs AI Assistant is an optional tool for WordPress administrators. By using it, you confirm that you are authorised to manage this site and to connect it to an AI service.', 'asdevs-ai-assistant' ),
			),
			array(
				'heading' => __( 'What the assistant can do', 'asdevs-ai-assistant' ),
				'body'    => __( 'The assistant can read information about your site and, when you ask it to, take actions that WordPress allows for your account (for example creating or editing content). Destructive or hard-to-reverse changes may ask for your confirmation first. You remain responsible for reviewing outcomes before publishing or relying on them.', 'asdevs-ai-assistant' ),
			),
			array(
				'heading' => __( 'AI providers and your data', 'asdevs-ai-assistant' ),
				'body'    => __( 'Prompts, site context, and related content may be sent to the AI connector you choose under WordPress Settings → Connectors. That service processes the data under its own terms and privacy policy. Do not send secrets, passwords, personal data of others, or confidential material unless you are sure that provider may receive it.', 'asdevs-ai-assistant' ),
			),
			array(
				'heading' => __( 'Accuracy and limits', 'asdevs-ai-assistant' ),
				'body'    => __( 'AI output can be incomplete or wrong. The assistant does not replace your judgment as a site administrator. Use it at your own risk; the plugin authors are not liable for loss arising from actions you approve or content the model generates.', 'asdevs-ai-assistant' ),
			),
			array(
				'heading' => __( 'Storage on this site', 'asdevs-ai-assistant' ),
				'body'    => __( 'Your active conversation and this acceptance are stored in this WordPress database for your user account so the assistant can continue a session and remember that you agreed. Uninstalling the plugin removes that data.', 'asdevs-ai-assistant' ),
			),
			array(
				'heading' => __( 'Updates to these terms', 'asdevs-ai-assistant' ),
				'body'    => __( 'If these terms change in a later plugin update, you will be asked to review and accept the new version before using the assistant again. Continuing after acceptance means you agree to the version shown.', 'asdevs-ai-assistant' ),
			),
		);
	}
}
