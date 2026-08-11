import { tool } from '@openai/agents';
import type { FunctionTool } from '@openai/agents';
import { api } from '../api';
import type { AgentMode } from '../api';
import type { Outcome, ToolSchema } from '../types';
import { compact, toolText } from './results';

/**
 * The site's tools, as the OpenAI Agents SDK understands them.
 *
 * Names, descriptions, and parameter schemas are not written here: they come
 * from the server's tool catalogue, so there is one place that decides what the
 * assistant may reach and what it is told about each tool. This file only
 * supplies the work each name performs in the browser.
 */

/** What a paused level-three change is waiting on. */
export interface ConfirmationRequest {
  callId: string;
  token: string;
  summary: string;
  reversible: boolean;
  affected: number | null;
  method: string;
  route: string;
  params: Record<string, unknown>;
}

export interface ToolContext {
  /** Agent (do work) or Ask (read-only answers) for the current turn. */
  mode: () => AgentMode;
  /** The assistant loaded a skill for itself; make it visible and sticky. */
  onSkillsLoaded: (slugs: string[]) => void;
  /** A change needs a person's yes before it can run. */
  onConfirmationRequired: (request: ConfirmationRequest) => void;
}

type Input = Record<string, unknown>;

const text = (value: unknown): string => toolText(value);

/**
 * Build the tool set for one run.
 *
 * @param schemas Tool definitions from the server briefing.
 * @param ctx     What the tools need from the surrounding session.
 */
export function createTools(schemas: ToolSchema[], ctx: ToolContext): FunctionTool<unknown>[] {
  // What a call_api approval decision already learned, keyed by call id, so the
  // work is done once whether or not a person had to be asked.
  const resolved = new Map<string, { outcome?: Outcome; request?: ConfirmationRequest }>();

  const run = async (name: string, input: Input, callId: string): Promise<string> => {
    switch (name) {
      case 'list_capabilities':
        return text(await api.capabilities());

      case 'describe_capability':
        return text(await api.describe(String(input.route ?? '')));

      case 'call_api':
        return text(await callApi(input, callId, ctx, resolved));

      case 'find_skills': {
        const limit = Number(input.limit);

        return text(
          await api.searchSkills(
            String(input.query ?? ''),
            Number.isFinite(limit) && limit > 0 ? limit : 5
          )
        );
      }

      case 'load_skill': {
        const raw = input.slugs;
        const slugs = Array.isArray(raw)
          ? raw.map((value) => String(value))
          : typeof raw === 'string'
            ? [raw]
            : [];

        if (slugs.length === 0) {
          return text({ error: 'Pass at least one skill slug.' });
        }

        const loaded = await api.loadSkills(slugs);

        ctx.onSkillsLoaded(loaded.loaded.map((item) => item.slug));

        return text(loaded);
      }

      case 'memory_list':
        return text(await api.memoryList());

      case 'memory_write':
      case 'memory_delete': {
        if (ctx.mode() === 'ask') {
          return text({
            error:
              'Ask mode is read-only. Memory cannot be changed here. Tell the person to switch to Agent mode if they want that.',
          });
        }

        if (name === 'memory_delete') {
          return text(await api.memoryDelete(String(input.id ?? '')));
        }

        const id = typeof input.id === 'string' && input.id !== '' ? input.id : undefined;

        return text(await api.memoryWrite({ content: String(input.content ?? ''), id }));
      }

      default:
        return text({ error: `Unknown tool: ${name}` });
    }
  };

  return schemas.map((schema) =>
    tool({
      name: schema.name,
      description: schema.description,
      // Server-authored JSON Schema, so strict mode (which needs Zod) is off.
      parameters: schema.input_schema as Record<string, unknown> as never,
      strict: false,
      needsApproval:
        schema.name === 'call_api'
          ? async (_context, input, callId) =>
              approvalNeeded(input as Input, callId ?? '', ctx, resolved)
          : false,
      execute: async (input, _context, details) =>
        run(schema.name, (input ?? {}) as Input, details?.toolCall?.callId ?? ''),
      errorFunction: (_context, error) => text({ error: (error as Error).message }),
    })
  ) as FunctionTool<unknown>[];
}

/**
 * Decide whether a change has to be put to the person first.
 *
 * The server is the judge, not the model: the call is attempted, and a level
 * three change comes back as a confirmation request with a one-use token
 * instead of a result. That token is kept out of the conversation so only a
 * real click can redeem it.
 */
async function approvalNeeded(
  input: Input,
  callId: string,
  ctx: ToolContext,
  resolved: Map<string, { outcome?: Outcome; request?: ConfirmationRequest }>
): Promise<boolean> {
  const method = String(input.method ?? 'GET').toUpperCase();
  const route = String(input.route ?? '');
  const params = (input.params as Record<string, unknown>) ?? {};

  if (ctx.mode() === 'ask' && method !== 'GET') {
    return false;
  }

  let outcome: Outcome;

  try {
    outcome = await api.execute({ method, route, params });
  } catch {
    // Let execute() surface the failure in the model's own language.
    return false;
  }

  if (outcome.status === 'confirmation_required' && outcome.confirmation) {
    const request: ConfirmationRequest = {
      callId,
      token: outcome.confirmation,
      summary: outcome.assessment?.summary ?? '',
      reversible: outcome.assessment?.reversible ?? true,
      affected: outcome.assessment?.affected ?? null,
      method,
      route,
      params,
    };

    resolved.set(callId, { request });
    ctx.onConfirmationRequired(request);

    return true;
  }

  resolved.set(callId, { outcome });

  return false;
}

/**
 * Run one REST call, reusing whatever the approval step already found out.
 */
async function callApi(
  input: Input,
  callId: string,
  ctx: ToolContext,
  resolved: Map<string, { outcome?: Outcome; request?: ConfirmationRequest }>
): Promise<Record<string, unknown>> {
  const method = String(input.method ?? 'GET').toUpperCase();
  const route = String(input.route ?? '');
  const params = (input.params as Record<string, unknown>) ?? {};

  if (ctx.mode() === 'ask' && method !== 'GET') {
    return {
      error:
        'Ask mode is read-only. Only GET is allowed. Explain what would change and tell the person to switch to Agent mode to do it.',
    };
  }

  const known = resolved.get(callId);
  resolved.delete(callId);

  if (known?.outcome) {
    return compact(known.outcome);
  }

  try {
    // Approved: redeem the one-use token from the confirmation round.
    const outcome = known?.request
      ? await api.execute({ method, route, params, confirmation: known.request.token })
      : await api.execute({ method, route, params });

    return compact(outcome);
  } catch (error) {
    return { error: (error as Error).message };
  }
}
