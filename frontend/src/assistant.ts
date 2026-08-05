import { reactive } from 'vue';
import { __, api, boot, streamChat } from './api';
import type {
  Block,
  Bubble,
  Bootstrap,
  ConversationSummary,
  Message,
  Outcome,
  PendingConfirmation,
  Suggestion,
} from './types';

/** How many times one request may go around the tool loop before stopping. */
const MAX_STEPS = 12;

/** How much of a tool result the model is given back. */
const MAX_RESULT_CHARS = 6000;

interface State {
  open: boolean;
  ready: boolean;
  loading: boolean;
  busy: boolean;
  activity: string;
  bubbles: Bubble[];
  messages: Message[];
  suggestions: Suggestion[];
  conversations: ConversationSummary[];
  pending: PendingConfirmation | null;
  settingsUrl: string;
  canConfigure: boolean;
  conversationId: string;
  title: string;
  showHistory: boolean;
  lastPrompt: string;
}

export const state = reactive<State>({
  open: false,
  ready: false,
  loading: true,
  busy: false,
  activity: '',
  bubbles: [],
  messages: [],
  suggestions: [],
  conversations: [],
  pending: null,
  settingsUrl: '',
  canConfigure: false,
  conversationId: newId(),
  title: '',
  showHistory: false,
  lastPrompt: '',
});

let controller: AbortController | null = null;

function newId(): string {
  return `c${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;
}

function bubble(role: 'user' | 'assistant', text = ''): Bubble {
  const entry: Bubble = { id: newId(), role, text, error: null };
  state.bubbles.push(entry);

  return entry;
}

export async function load(): Promise<void> {
  state.loading = true;

  try {
    const data: Bootstrap = await api.bootstrap();

    state.ready = data.ready;
    state.suggestions = data.suggestions;
    state.conversations = data.conversations;
    state.settingsUrl = data.settings_url;
    state.canConfigure = data.can_configure;
  } catch {
    state.ready = false;
  } finally {
    state.loading = false;
  }
}

export function open(): void {
  state.open = true;

  if (state.loading) {
    void load();
  }
}

export function close(): void {
  state.open = false;
}

export function cancel(): void {
  controller?.abort();
  controller = null;
  state.busy = false;
  state.activity = '';
}

export function startNew(): void {
  cancel();
  state.bubbles = [];
  state.messages = [];
  state.pending = null;
  state.conversationId = newId();
  state.title = '';
  state.showHistory = false;
  void load();
}

export async function openConversation(id: string): Promise<void> {
  cancel();

  const stored = await api.conversation(id);

  state.conversationId = stored.id;
  state.title = stored.title;
  state.messages = stored.messages ?? [];
  state.bubbles = rebuild(state.messages);
  state.showHistory = false;
  state.pending = null;
}

export async function removeConversation(id: string): Promise<void> {
  await api.deleteConversation(id);

  state.conversations = state.conversations.filter((conversation) => conversation.id !== id);

  if (id === state.conversationId) {
    startNew();
  }
}

export async function removeAllConversations(): Promise<void> {
  await api.clearConversations();

  state.conversations = [];
  startNew();
}

/** Rebuild the visible conversation from what was stored. */
function rebuild(messages: Message[]): Bubble[] {
  const bubbles: Bubble[] = [];

  for (const message of messages) {
    const text = message.content
      .filter((block): block is Extract<Block, { type: 'text' }> => block.type === 'text')
      .map((block) => block.text)
      .join('');

    if (text.trim() !== '') {
      bubbles.push({ id: newId(), role: message.role, text, error: null });
    }
  }

  return bubbles;
}

export async function send(text: string): Promise<void> {
  const trimmed = text.trim();

  if (trimmed === '' || state.busy) {
    return;
  }

  state.lastPrompt = trimmed;
  state.pending = null;
  bubble('user', trimmed);
  state.messages.push({ role: 'user', content: [{ type: 'text', text: trimmed }] });

  if (state.title === '') {
    state.title = trimmed.slice(0, 60);
  }

  await run();
}

/** Retry the last thing that was asked, without making them type it again. */
export async function retry(): Promise<void> {
  if (state.busy || state.lastPrompt === '') {
    return;
  }

  await run();
}

async function run(): Promise<void> {
  state.busy = true;
  controller = new AbortController();

  try {
    for (let step = 0; step < MAX_STEPS; step += 1) {
      const answer = bubble('assistant');
      const calls: Array<{ id: string; name: string; input: Record<string, unknown> }> = [];
      let failed = false;

      state.activity = __('Working on it…');

      await streamChat(
        state.messages,
        (event) => {
          if (event.type === 'text' && event.text) {
            state.activity = '';
            answer.text += event.text;
          }

          if (event.type === 'tool_call' && event.id && event.name) {
            calls.push({ id: event.id, name: event.name, input: event.arguments ?? {} });
            state.activity = activityFor(event.name, event.arguments ?? {});
          }

          if (event.type === 'error') {
            failed = true;
            answer.error = {
              message: event.message ?? __('Something went wrong.'),
              detail: event.detail ?? '',
              retryable: event.retryable !== false,
            };
          }
        },
        controller.signal
      );

      if (answer.text.trim() === '' && !answer.error && calls.length === 0) {
        state.bubbles = state.bubbles.filter((entry) => entry.id !== answer.id);
      }

      if (failed || calls.length === 0) {
        break;
      }

      const assistantBlocks: Block[] = [];

      if (answer.text.trim() !== '') {
        assistantBlocks.push({ type: 'text', text: answer.text });
      }

      for (const call of calls) {
        assistantBlocks.push({ type: 'tool_use', id: call.id, name: call.name, input: call.input });
      }

      state.messages.push({ role: 'assistant', content: assistantBlocks });

      const results: Block[] = [];
      let paused = false;

      for (let index = 0; index < calls.length; index += 1) {
        const outcome = await runTool(calls[index], answer);

        if (outcome === 'paused' && state.pending) {
          // Every call this turn still owes an answer, even the ones we never
          // reached — they are handed back once the person decides.
          state.pending.collected = results;
          state.pending.skipped = calls.slice(index + 1).map((call) => call.id);
          paused = true;
          break;
        }

        if (outcome !== 'paused') {
          results.push(outcome);
        }
      }

      if (paused) {
        break;
      }

      state.messages.push({ role: 'user', content: results });
    }
  } catch (error) {
    if ((error as Error).name !== 'AbortError') {
      const answer = bubble('assistant');
      answer.error = {
        message: __('The assistant stopped unexpectedly.'),
        detail: (error as Error).message,
        retryable: true,
      };
    }
  } finally {
    state.busy = false;
    state.activity = '';
    controller = null;
    await persist();
  }
}

/** Say what is happening in the user's terms, never in the site's. */
function activityFor(name: string, input: Record<string, unknown>): string {
  switch (name) {
    case 'list_capabilities':
      return __('Looking at what your site can do…');
    case 'describe_capability':
      return __('Checking the details…');
    case 'read_site':
      return __('Reading from your site…');
    case 'change_site':
      return input.method === 'DELETE' ? __('Removing…') : __('Making the change…');
    default:
      return __('Working on it…');
  }
}

type ToolOutcome = Block | 'paused';

async function runTool(
  call: { id: string; name: string; input: Record<string, unknown> },
  answer: Bubble
): Promise<ToolOutcome> {
  try {
    if (call.name === 'list_capabilities') {
      return result(call.id, await api.capabilities());
    }

    if (call.name === 'describe_capability') {
      return result(call.id, await api.describe(String(call.input.route ?? '')));
    }

    if (call.name === 'open_admin_page') {
      const path = String(call.input.path ?? '');
      const label = String(call.input.label ?? __('Open in the panel'));

      answer.links = [...(answer.links ?? []), { label, href: boot.adminUrl + path.replace(/^\/+/, '') }];

      return result(call.id, { opened: true, note: 'The link was offered to the person.' });
    }

    if (call.name === 'read_site') {
      const outcome = await api.execute({
        method: 'GET',
        route: String(call.input.route ?? ''),
        params: (call.input.params as Record<string, unknown>) ?? {},
      });

      applyOutcome(outcome, answer);

      return result(call.id, compact(outcome), outcome.status !== 'ok');
    }

    if (call.name === 'change_site') {
      const method = String(call.input.method ?? 'POST');
      const route = String(call.input.route ?? '');
      const params = (call.input.params as Record<string, unknown>) ?? {};
      const outcome = await api.execute({ method, route, params });

      if (outcome.status === 'confirmation_required' && outcome.confirmation) {
        // The token stays here, out of the conversation, so only a real click
        // can redeem it.
        state.pending = {
          toolUseId: call.id,
          token: outcome.confirmation,
          summary: outcome.assessment?.summary ?? '',
          reversible: outcome.assessment?.reversible ?? true,
          affected: outcome.assessment?.affected ?? null,
          method,
          route,
          params,
          collected: [],
          skipped: [],
        };

        return 'paused';
      }

      applyOutcome(outcome, answer);

      return result(call.id, compact(outcome), outcome.status !== 'ok');
    }

    return result(call.id, { error: 'Unknown tool.' }, true);
  } catch (error) {
    return result(call.id, { error: (error as Error).message }, true);
  }
}

/** The person said yes to a level three change. */
export async function confirmPending(): Promise<void> {
  const pending = state.pending;

  if (!pending || state.busy) {
    return;
  }

  state.pending = null;
  state.busy = true;

  const answer = state.bubbles[state.bubbles.length - 1] ?? bubble('assistant');

  try {
    const outcome = await api.execute({
      method: pending.method,
      route: pending.route,
      params: pending.params,
      confirmation: pending.token,
    });

    applyOutcome(outcome, answer);

    state.messages.push({
      role: 'user',
      content: settle(pending, result(pending.toolUseId, compact(outcome), outcome.status !== 'ok')),
    });
  } finally {
    state.busy = false;
  }

  await run();
}

/** The person said no. The assistant is told plainly, and does not insist. */
export async function declinePending(): Promise<void> {
  const pending = state.pending;

  if (!pending) {
    return;
  }

  state.pending = null;

  state.messages.push({
    role: 'user',
    content: settle(
      pending,
      result(pending.toolUseId, { declined: true, note: 'The person declined this change. Do not ask again.' }, false)
    ),
  });

  await run();
}

/** Close out every call from the paused turn, in the order they were made. */
function settle(pending: PendingConfirmation, decided: Block): Block[] {
  return [
    ...pending.collected,
    decided,
    ...pending.skipped.map((id) =>
      result(id, { skipped: true, note: 'Not run: the turn stopped for a confirmation.' }, false)
    ),
  ];
}

function result(id: string, payload: unknown, isError = false): Block {
  let content = typeof payload === 'string' ? payload : JSON.stringify(payload);

  if (content.length > MAX_RESULT_CHARS) {
    content = `${content.slice(0, MAX_RESULT_CHARS)}\n…(truncated)`;
  }

  return { type: 'tool_result', tool_use_id: id, content, is_error: isError };
}

/** Keep the model's view of a result small and relevant. */
function compact(outcome: Outcome): Record<string, unknown> {
  const shrink = (value: unknown): unknown => {
    if (Array.isArray(value)) {
      return value.slice(0, 20).map(shrink);
    }

    if (value && typeof value === 'object') {
      const source = value as Record<string, unknown>;
      const keep = ['id', 'title', 'name', 'slug', 'status', 'date', 'link', 'email', 'roles', 'count', 'total'];
      const kept: Record<string, unknown> = {};

      for (const key of keep) {
        if (key in source) {
          const field = source[key];
          kept[key] =
            field && typeof field === 'object' && 'rendered' in (field as Record<string, unknown>)
              ? (field as { rendered: unknown }).rendered
              : field;
        }
      }

      return Object.keys(kept).length > 0 ? kept : source;
    }

    return value;
  };

  return {
    status: outcome.status,
    message: outcome.message,
    kind: outcome.kind,
    total: outcome.total ?? null,
    data: shrink(outcome.data),
    assessment: outcome.assessment,
  };
}

/** Turn a result into something worth looking at, and a way into the panel. */
function applyOutcome(outcome: Outcome, answer: Bubble): void {
  if (outcome.links) {
    const labels: Record<string, string> = {
      edit: __('Open in the editor'),
      view: __('View on the site'),
      preview: __('Preview'),
    };

    answer.links = [
      ...(answer.links ?? []),
      ...Object.entries(outcome.links).map(([key, href]) => ({ label: labels[key] ?? key, href })),
    ];
  }

  if (Array.isArray(outcome.data) && outcome.data.length > 0) {
    answer.rows = outcome.data.slice(0, 10).map((item) => {
      const row: Record<string, string> = {};
      const source = (item ?? {}) as Record<string, unknown>;

      for (const key of ['title', 'name', 'status', 'date', 'email']) {
        const field = source[key];

        if (field === undefined || field === null) {
          continue;
        }

        row[key] =
          typeof field === 'object' && 'rendered' in (field as Record<string, unknown>)
            ? String((field as { rendered: unknown }).rendered)
            : String(field);
      }

      return row;
    });

    answer.total = outcome.total ?? outcome.data.length;
  }
}

async function persist(): Promise<void> {
  if (state.messages.length === 0) {
    return;
  }

  try {
    await api.saveConversation({
      id: state.conversationId,
      title: state.title,
      messages: state.messages,
      unfinished: state.pending !== null,
    });
  } catch {
    // History is a convenience; failing to store it must not break the answer.
  }
}
