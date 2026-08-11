<?php
/**
 * The tools the assistant may call.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A deliberately small tool surface.
 *
 * There is no per-feature tool: the assistant discovers what the site can do
 * and then calls those REST routes through one generic API tool. That is what
 * lets a plugin nobody here has seen work on day one (section 13).
 *
 * Ask mode receives a read-only subset so the model cannot propose writes.
 */
final class ToolCatalog {

	/**
	 * Tool definitions, in the neutral shape providers translate from.
	 *
	 * @param string $mode `agent` (full tools) or `ask` (read-only).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function definitions( string $mode = 'agent' ): array {
		$mode = 'ask' === $mode ? 'ask' : 'agent';

		$tools = array(
			array(
				'name'         => 'list_capabilities',
				'description'  => 'List the REST APIs this site exposes right now, discovered live from WordPress, plugins, and themes. Each entry has base path, methods, and often a short description of what that API is for. Call this before claiming the site can or cannot do something. Only routes the signed-in person is allowed to reach are listed.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			),
			array(
				'name'         => 'describe_capability',
				'description'  => 'Show what one REST route does and the exact parameters it accepts (descriptions, types, enums, defaults, min/max, array item types). Use a path from list_capabilities before call_api whenever you are unsure of the shape or meaning of the API. Do not invent fields.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'route' => array(
							'type'        => 'string',
							'description' => 'A REST path, for example /wp/v2/posts.',
						),
					),
					'required'   => array( 'route' ),
				),
			),
			$this->call_api_tool( $mode ),
			array(
				'name'         => 'find_skills',
				'description'  => 'Search the skills this site defines — reusable instructions written by the people who run it, describing how they want particular jobs done here. Pass what the person is actually trying to do, in their own words and their own language. Returns matching skills with a slug, a title, and when each one applies; it does NOT return the instructions themselves. Use it at the start of a task when the skill list in your briefing suggests something relevant might exist, then load what fits.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'What the person wants done, in their words. Include the subject and the action, for example "write a product description" or "publish the weekly newsletter".',
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'How many matches to return. Default 5.',
						),
					),
					'required'   => array( 'query' ),
				),
			),
			array(
				'name'         => 'load_skill',
				'description'  => 'Load the full instructions for one or more skills by slug, and keep them active for the rest of this conversation. Use after find_skills, or straight from a slug in your briefing when the match is obvious. Load only what the task genuinely needs — at most a few — and follow what comes back for the work that follows. The person sees which skills are active and can switch them off.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'slugs' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Skill slugs to load, without the leading slash.',
						),
					),
					'required'   => array( 'slugs' ),
				),
			),
			array(
				'name'         => 'memory_list',
				'description'  => 'List every persistent memory row stored for this site (id, content, timestamps). Use when you need the full set or after writing/deleting.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			),
		);

		if ( 'ask' === $mode ) {
			return $tools;
		}

		$tools[] = array(
			'name'         => 'memory_write',
			'description'  => 'Create or update a persistent memory note. Omit id to create; include id to update an existing row. Keep content short and factual.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'content' => array(
						'type'        => 'string',
						'description' => 'The note to remember.',
					),
					'id'      => array(
						'type'        => 'string',
						'description' => 'Existing memory id when updating.',
					),
				),
				'required'   => array( 'content' ),
			),
		);

		$tools[] = array(
			'name'         => 'memory_delete',
			'description'  => 'Delete one persistent memory row by id.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'string',
						'description' => 'Memory id to delete.',
					),
				),
				'required'   => array( 'id' ),
			),
		);

		return $tools;
	}

	/**
	 * call_api definition — full methods in Agent, GET-only in Ask.
	 *
	 * @param string $mode agent|ask.
	 *
	 * @return array<string, mixed>
	 */
	private function call_api_tool( string $mode ): array {
		if ( 'ask' === $mode ) {
			return array(
				'name'         => 'call_api',
				'description'  => 'Read any site REST API discovered via list_capabilities. Ask mode is read-only: method must be GET. Pass the route path and any query parameters that route needs. Returns the API response. Never claim site data you have not read.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'method' => array(
							'type'        => 'string',
							'enum'        => array( 'GET' ),
							'description' => 'HTTP method — GET only in Ask mode.',
						),
						'route'  => array(
							'type'        => 'string',
							'description' => 'A REST path from list_capabilities, for example /wp/v2/posts or /wp/v2/posts/12.',
						),
						'params' => array(
							'type'        => 'object',
							'description' => 'Query parameters matching what describe_capability shows for that route.',
						),
					),
					'required'   => array( 'method', 'route' ),
				),
			);
		}

		return array(
			'name'         => 'call_api',
			'description'  => 'Call any site REST API discovered via list_capabilities. Pass the HTTP method, the route path, and any query/body parameters that route needs. Returns the API response. Use GET to read; POST/PUT/PATCH/DELETE to create, update, or remove. The server may require confirmation for risky changes — when it does, tell the person plainly what will happen and wait. Never claim a change succeeded before this tool reports it.',
			'input_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'method' => array(
						'type'        => 'string',
						'enum'        => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
						'description' => 'HTTP method for the route.',
					),
					'route'  => array(
						'type'        => 'string',
						'description' => 'A REST path from list_capabilities, for example /wp/v2/posts or /wp/v2/posts/12.',
					),
					'params' => array(
						'type'        => 'object',
						'description' => 'Query parameters (for GET) or body fields (for writes), matching what describe_capability shows for that route.',
					),
				),
				'required'   => array( 'method', 'route' ),
			),
		);
	}
}
