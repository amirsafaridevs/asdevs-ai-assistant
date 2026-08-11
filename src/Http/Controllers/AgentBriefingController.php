<?php
/**
 * What the browser-side agent needs to know before it runs.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Http\Controllers;

use ASDevs\AIAssistant\Services\Ai\SystemPrompt;
use ASDevs\AIAssistant\Services\Ai\ToolCatalog;
use ASDevs\AIAssistant\Services\Skills\SkillStore;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hands the agent its instructions and its tool schemas.
 *
 * The agent loop itself lives in the browser (OpenAI Agents SDK), but what the
 * assistant is told and which tools it may reach stay decided here, on the
 * server, where the site's context, memory, and skills already live. The
 * browser never authors the system prompt.
 */
final class AgentBriefingController extends Controller {

	/**
	 * Instructions.
	 *
	 * @var SystemPrompt
	 */
	private SystemPrompt $prompt;

	/**
	 * Tools.
	 *
	 * @var ToolCatalog
	 */
	private ToolCatalog $tools;

	/**
	 * Skills.
	 *
	 * @var SkillStore
	 */
	private SkillStore $skills;

	/**
	 * Constructor.
	 *
	 * @param SystemPrompt $prompt Instructions.
	 * @param ToolCatalog  $tools  Tools.
	 * @param SkillStore   $skills Skills.
	 */
	public function __construct( SystemPrompt $prompt, ToolCatalog $tools, SkillStore $skills ) {
		$this->prompt = $prompt;
		$this->tools  = $tools;
		$this->skills = $skills;
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/agent/briefing',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'check_terms_permission' ),
			)
		);
	}

	/**
	 * Build the briefing for one mode, page, and set of active skills.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$mode   = $this->mode_from( $request );
		$skills = $this->skills_from( $request );

		return new WP_REST_Response(
			array(
				'instructions' => $this->prompt->build( $this->array_param( $request, 'page' ), $mode, $skills ),
				'tools'        => $this->tools->definitions( $mode ),
				'skills'       => $skills,
				'mode'         => $mode,
			)
		);
	}

	/**
	 * Active assistant mode from the browser: agent (default) or ask.
	 *
	 * @param WP_REST_Request $request The request.
	 */
	private function mode_from( WP_REST_Request $request ): string {
		$mode = strtolower( sanitize_key( (string) $request->get_param( 'mode' ) ) );

		return 'ask' === $mode ? 'ask' : 'agent';
	}

	/**
	 * Active skill slugs from the browser, validated against stored skills.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array<int, string>
	 */
	private function skills_from( WP_REST_Request $request ): array {
		$raw = $request->get_param( 'skills' );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$slugs = array();

		foreach ( array_slice( $raw, 0, 10 ) as $value ) {
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}

			$slug = sanitize_title( (string) $value );

			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values(
			array_map(
				static fn( array $skill ): string => (string) $skill['slug'],
				$this->skills->resolve_for_prompt( $slugs )
			)
		);
	}
}
