/**
 * Everything the agent loop needs, in one lazily loaded chunk.
 *
 * The OpenAI Agents SDK and its dependencies are the largest thing this widget
 * ships, and most admin page loads never open the assistant at all. Nothing
 * here is imported statically: `assistant.ts` reaches this module through a
 * dynamic import, so the SDK is fetched when the window opens rather than on
 * every wp-admin page view.
 *
 * Keep this barrel to re-exports. Anything added here lands in the deferred
 * chunk, and anything imported from here by other modules pulls the SDK back
 * into the main bundle.
 */

export { Agent, RunState, run } from '@openai/agents';
export { configureRuntime } from './runtime';
export { createTools } from './tools';
export { planAsContext, planFor, worthPlanning } from './planner';
