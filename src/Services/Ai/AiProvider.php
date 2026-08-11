<?php
/**
 * The AI provider contract.
 *
 * @package ASDevs\AIAssistant
 */

declare( strict_types=1 );

namespace ASDevs\AIAssistant\Services\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Any model service can sit behind this interface.
 *
 * Section 14.1: the product depends on no particular AI service, and section
 * 28.3 requires the AI layer to be replaceable without touching the rest of
 * the product. The default implementations use WordPress core connectors via
 * the AI Client (`wp_ai_client_prompt()`).
 */
interface AiProvider {

	/**
	 * Machine name of the provider.
	 */
	public function id(): string;

	/**
	 * Human label, shown only in settings.
	 */
	public function label(): string;

	/**
	 * Model identifiers this provider offers, keyed by id.
	 *
	 * @return array<string, string>
	 */
	public function models(): array;

	/**
	 * The model used when the site has not chosen one.
	 */
	public function default_model(): string;

	/**
	 * Model identifiers that can show their reasoning as they work.
	 *
	 * @return string[]
	 */
	public function reasoning_models(): array;

	/**
	 * Whether the provider is configured well enough to answer.
	 */
	public function is_configured(): bool;

	/**
	 * Stream one turn.
	 *
	 * The callback receives normalized events:
	 * - array{type:'text', text:string}
	 * - array{type:'thinking', text:string}
	 * - array{type:'thinking_end', thinking:string, signature:string, data:string}
	 * - array{type:'tool_call', id:string, name:string, arguments:array}
	 * - array{type:'done', reason:string}
	 *
	 * @param ChatRequest $request The request.
	 * @param callable    $emit    Event sink.
	 *
	 * @throws AiUnavailable When the service cannot answer.
	 */
	public function stream( ChatRequest $request, callable $emit ): void;
}
