<?php
/**
 * The tools the assistant may call.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A deliberately small tool surface.
 *
 * There is no per-feature tool: the assistant discovers what the site can do
 * and then reads or changes it through the site's own interface. That is what
 * lets a plugin nobody here has seen work on day one (section 13).
 */
final class ToolCatalog {

	/**
	 * Tool definitions, in the neutral shape providers translate from.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function definitions(): array {
		return array(
			array(
				'name'         => 'list_capabilities',
				'description'  => 'List what this specific site can do right now, discovered live from the site itself. Call this first when you do not already know whether the site supports something. Only capabilities the signed-in person is allowed to reach are listed.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			),
			array(
				'name'         => 'describe_capability',
				'description'  => 'Show the exact parameters one capability accepts, so a change can be made correctly instead of guessed. Use the "base" or a path from list_capabilities.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'route' => array(
							'type'        => 'string',
							'description' => 'A capability path, for example /wp/v2/posts.',
						),
					),
					'required'   => array( 'route' ),
				),
			),
			array(
				'name'         => 'read_site',
				'description'  => 'Read information from the site. Never changes anything. Use query parameters to narrow results and keep them small.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array(
							'type'        => 'string',
							'description' => 'A capability path, for example /wp/v2/posts or /wp/v2/posts/12.',
						),
						'params' => array(
							'type'        => 'object',
							'description' => 'Query parameters, for example {"status":"draft","per_page":10}.',
						),
					),
					'required'   => array( 'route' ),
				),
			),
			array(
				'name'         => 'change_site',
				'description'  => 'Create, update or delete something on the site. The server decides on its own whether the change needs the person to confirm first, and will say so; when it does, tell them plainly what will happen and wait. Never claim a change was made before this tool reports it succeeded.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'method' => array(
							'type'        => 'string',
							'enum'        => array( 'POST', 'PUT', 'PATCH', 'DELETE' ),
							'description' => 'POST to create, POST or PATCH to update, DELETE to remove.',
						),
						'route'  => array(
							'type'        => 'string',
							'description' => 'A capability path, for example /wp/v2/posts or /wp/v2/posts/12.',
						),
						'params' => array(
							'type'        => 'object',
							'description' => 'The fields to send.',
						),
					),
					'required'   => array( 'method', 'route' ),
				),
			),
			array(
				'name'         => 'open_admin_page',
				'description'  => 'Offer the person a link to a page of their admin panel. Use this as the alternative route whenever something cannot be done here, so "I cannot do that" is never the whole answer.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'path'  => array(
							'type'        => 'string',
							'description' => 'An admin path, for example options-general.php or edit.php?post_type=page.',
						),
						'label' => array(
							'type'        => 'string',
							'description' => 'Short label for the link, in the language of the conversation.',
						),
					),
					'required'   => array( 'path', 'label' ),
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
			array(
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
			),
			array(
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
			),
			array(
				'name'         => 'read_current_page',
				'description'  => 'Read the visible text of the admin page where the assistant panel is open (page title, headings, main content). Never changes anything.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => (object) array(),
				),
			),
			array(
				'name'         => 'highlight_on_page',
				'description'  => 'Visually highlight an element on the current admin page so the person can see it. Provide a CSS selector and/or visible text to match.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'selector' => array(
							'type'        => 'string',
							'description' => 'CSS selector for the target element, for example "#title" or ".wp-heading-inline".',
						),
						'text'     => array(
							'type'        => 'string',
							'description' => 'Visible text contained by the element to highlight.',
						),
					),
				),
			),
		);
	}

	/**
	 * Tool names that only read.
	 *
	 * @return string[]
	 */
	public function read_only(): array {
		return array(
			'list_capabilities',
			'describe_capability',
			'read_site',
			'open_admin_page',
			'memory_list',
			'read_current_page',
			'highlight_on_page',
		);
	}
}
