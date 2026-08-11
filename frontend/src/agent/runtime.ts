import OpenAI from 'openai';
import { setDefaultOpenAIClient, setOpenAIAPI, setTracingDisabled } from '@openai/agents';
import { boot } from '../api';

/**
 * Point the OpenAI Agents SDK at this site instead of at OpenAI.
 *
 * The agent loop runs in the browser, but it never reaches a model vendor
 * directly. Every request goes to the plugin's own OpenAI-shaped endpoint,
 * which answers using whichever WordPress AI connector the site configured.
 * The API key stays in WordPress; the browser authenticates with the ordinary
 * REST nonce, exactly as every other call in this plugin does.
 *
 * The key below is a placeholder. The SDK requires a non-empty string, and our
 * endpoint ignores it — it trusts the signed-in WordPress user, not a token.
 */
let configured = false;

export function configureRuntime(): void {
  if (configured) {
    return;
  }

  configured = true;

  setDefaultOpenAIClient(
    new OpenAI({
      apiKey: 'wordpress-connector',
      baseURL: `${boot.restUrl}/openai/v1`,
      // Safe here precisely because there is no real key to leak.
      dangerouslyAllowBrowser: true,
      defaultHeaders: { 'X-WP-Nonce': boot.nonce },
      fetch: (input: RequestInfo | URL, init?: RequestInit) =>
        fetch(input, { ...init, credentials: 'same-origin' }),
    })
  );

  // Chat Completions, not Responses: it is the protocol the WordPress AI
  // Client can be translated into for every provider, not just OpenAI.
  setOpenAIAPI('chat_completions');

  // Tracing would post run data to OpenAI. Nothing about this site's content
  // should leave the server the person chose.
  setTracingDisabled(true);
}
