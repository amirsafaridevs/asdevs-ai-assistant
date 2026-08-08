import { reactive } from 'vue';
import { __, api, boot, sprintf, streamChat } from './api';
import { extractChoices } from './markdown';
import { highlightOnPage, readCurrentPage } from './pageTools';
import type {
  Block,
  Bubble,
  Bootstrap,
  ChoiceSession,
  ConversationSummary,
  Message,
  MessageAttachment,
  Outcome,
  PendingConfirmation,
  ServiceSettings,
  Step,
  Suggestion,
} from './types';

/** How many times one request may go around the tool loop before stopping. */
const MAX_STEPS = 12;

/** How many times a single stream may be attempted before showing an error. */
const MAX_STREAM_ATTEMPTS = 3;

/** How much of a tool result the model is given back. */
const MAX_RESULT_CHARS = 6000;

type View = 'chat' | 'history' | 'settings';

interface State {
  open: boolean;
  ready: boolean;
  /** Why the server reported not ready, when known. */
  readyDetail: string;
  /** Bootstrap/network failure — distinct from a genuinely missing connector. */
  loadError: string;
  loading: boolean;
  busy: boolean;
  view: View;
  bubbles: Bubble[];
  messages: Message[];
  suggestions: Suggestion[];
  conversations: ConversationSummary[];
  pending: PendingConfirmation | null;
  choices: ChoiceSession | null;
  settingsUrl: string;
  canConfigure: boolean;
  conversationId: string;
  title: string;
  lastPrompt: string;
  /** The connector preference, edited in place inside the window. */
  settings: ServiceSettings | null;
  settingsBusy: boolean;
  settingsSaved: boolean;
  settingsError: string;
}

export const state = reactive<State>({
  open: false,
  ready: false,
  readyDetail: '',
  loadError: '',
  loading: true,
  busy: false,
  view: 'chat',
  bubbles: [],
  messages: [],
  suggestions: [],
  conversations: [],
  pending: null,
  choices: null,
  settingsUrl: '',
  canConfigure: false,
  conversationId: newId(),
  title: '',
  lastPrompt: '',
  settings: null,
  settingsBusy: false,
  settingsSaved: false,
  settingsError: '',
});

let controller: AbortController | null = null;

function newId(): string {
  return `c${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`;
}

/**
 * Add a bubble and hand back the tracked copy.
 *
 * The object that comes back must be the one the view is watching, or writing
 * to it as the answer streams in changes nothing on screen.
 */
function bubble(role: 'user' | 'assistant', text = '', attachments: MessageAttachment[] = []): Bubble {
  state.bubbles.push({
    id: newId(),
    role,
    text,
    attachments: attachments.length ? attachments : undefined,
    steps: [],
    error: null,
  });

  return state.bubbles[state.bubbles.length - 1];
}

function lastAssistant(): Bubble {
  const last = state.bubbles[state.bubbles.length - 1];

  return last && last.role === 'assistant' ? last : bubble('assistant');
}

/** Open a line on the timeline and hand back the tracked copy. */
function beginStep(answer: Bubble, kind: Step['kind'], label: string, detail = ''): Step {
  if (!answer.steps) {
    answer.steps = [];
  }

  // Read the list back off the bubble: the tracked copy is the one the view
  // is watching, and the only one worth writing to.
  const steps = answer.steps;

  steps.push({ id: newId(), kind, label, detail, status: 'running', startedAt: Date.now(), endedAt: null });

  return steps[steps.length - 1];
}

function endStep(step: Step, status: Step['status']): Step {
  step.status = status;
  step.endedAt = Date.now();

  return step;
}

/** Nothing should keep spinning once the turn has moved on. */
function closeOpenSteps(answer: Bubble, status: Step['status'] = 'done'): void {
  for (const step of answer.steps ?? []) {
    if (step.status === 'running') {
      endStep(step, status);
    }
  }
}

export async function load(): Promise<void> {
  state.loading = true;
  state.loadError = '';

  try {
    const data: Bootstrap = await api.bootstrap();

    state.ready = data.ready;
    state.readyDetail = data.ready ? '' : (data.ready_detail ?? '');
    state.suggestions = data.suggestions;
    state.conversations = data.conversations;
    state.settingsUrl = data.settings_url;
    state.canConfigure = data.can_configure;
  } catch (error) {
    // A failed bootstrap is not the same as a missing connector — keep any
    // earlier ready value and show the real error instead of the setup screen.
    state.loadError = (error as Error).message || __('Could not reach the assistant.');
  } finally {
    state.loading = false;
  }
}

export function open(): void {
  state.open = true;

  // Retry when the first bootstrap failed or the connector still looked missing —
  // credentials may have been saved since the page loaded.
  if (state.loading || state.loadError !== '' || !state.ready) {
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

  for (const item of state.bubbles) {
    closeOpenSteps(item, 'skipped');
  }
}

export function show(view: View): void {
  state.view = state.view === view && view !== 'chat' ? 'chat' : view;

  if (state.view === 'settings') {
    void loadSettings();
  }
}

export function startNew(): void {
  cancel();
  state.bubbles = [];
  state.messages = [];
  state.pending = null;
  state.choices = null;
  state.conversationId = newId();
  state.title = '';
  state.view = 'chat';
  void load();
}

export async function openConversation(id: string): Promise<void> {
  cancel();

  const stored = await api.conversation(id);

  state.conversationId = stored.id;
  state.title = stored.title;
  state.messages = stored.messages ?? [];
  state.bubbles = rebuild(state.messages);
  state.view = 'chat';
  state.pending = null;
  state.choices = null;
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

/* -------------------------------------------------------------------------
 * The WordPress connector preference, edited where it is used.
 * ---------------------------------------------------------------------- */

export async function loadSettings(): Promise<void> {
  if (!state.canConfigure || state.settingsBusy) {
    return;
  }

  state.settingsBusy = true;
  state.settingsError = '';

  try {
    state.settings = await api.settings();
  } catch (error) {
    state.settingsError = (error as Error).message;
  } finally {
    state.settingsBusy = false;
  }
}

/** Remember which WordPress connector the assistant should prefer. */
export function chooseProvider(id: string): void {
  if (!state.settings) {
    return;
  }

  state.settings.provider = id;
  state.settingsSaved = false;
}

export async function saveSettings(): Promise<void> {
  if (!state.settings || state.settingsBusy) {
    return;
  }

  state.settingsBusy = true;
  state.settingsError = '';
  state.settingsSaved = false;

  try {
    state.settings = await api.saveSettings({
      provider: state.settings.provider,
    });

    state.settingsSaved = true;
    state.ready = state.settings.ready;
    state.readyDetail = state.settings.ready ? '' : state.readyDetail;
    state.loadError = '';

    void load();
  } catch (error) {
    state.settingsError = (error as Error).message;
  } finally {
    state.settingsBusy = false;
  }
}

/** Probe the preferred connector with a short prompt. */
export async function testConnector(provider: string): Promise<{ ok: boolean; message: string }> {
  return api.testConnector(provider);
}

/** Rebuild the visible conversation from what was stored. */
function rebuild(messages: Message[]): Bubble[] {
  const bubbles: Bubble[] = [];

  for (const message of messages) {
    const text = message.content
      .filter((block): block is Extract<Block, { type: 'text' }> => block.type === 'text')
      .map((block) => block.text)
      .join('');

    const attachments = message.content
      .filter((block): block is Extract<Block, { type: 'file' }> => block.type === 'file' && !!block.url)
      .map(
        (block): MessageAttachment => ({
          name: block.name || __('Attached file'),
          type: block.mime_type || 'application/octet-stream',
          url: block.url,
        })
      );

    if (text.trim() !== '' || attachments.length > 0) {
      bubbles.push({
        id: newId(),
        role: message.role,
        text,
        attachments: attachments.length ? attachments : undefined,
        steps: [],
        error: null,
      });
    }
  }

  return bubbles;
}

export async function send(text: string, attachments: MessageAttachment[] = []): Promise<void> {
  const trimmed = text.trim();
  const files = attachments.filter((item) => item.url.trim() !== '');

  if ((trimmed === '' && files.length === 0) || state.busy) {
    return;
  }

  const content: Block[] = [];

  if (trimmed !== '') {
    content.push({ type: 'text', text: trimmed });
  }

  for (const file of files) {
    content.push({
      type: 'file',
      url: file.url,
      mime_type: file.type || 'application/octet-stream',
      name: file.name,
    });
  }

  state.view = 'chat';
  state.lastPrompt = trimmed || files[0]?.name || '';
  state.pending = null;
  state.choices = null;
  bubble('user', trimmed, files);
  state.messages.push({ role: 'user', content });

  if (state.title === '') {
    state.title = (trimmed || files[0]?.name || '').slice(0, 60);
  }

  await run();
}

/** Retry the last thing that was asked, without making them type it again. */
export async function retry(): Promise<void> {
  if (state.busy || state.lastPrompt === '') {
    return;
  }

  // Drop the failed reply so the soft notice does not linger under the new try.
  const last = state.bubbles[state.bubbles.length - 1];

  if (last?.role === 'assistant' && last.error) {
    state.bubbles = state.bubbles.filter((entry) => entry.id !== last.id);
  }

  await run();
}

/** Wait without blocking abort — used between quiet reconnect attempts. */
async function wait(ms: number, signal: AbortSignal): Promise<void> {
  if (signal.aborted) {
    throw new DOMException('Aborted', 'AbortError');
  }

  await new Promise<void>((resolve, reject) => {
    const timer = window.setTimeout(() => {
      signal.removeEventListener('abort', onAbort);
      resolve();
    }, ms);

    const onAbort = (): void => {
      window.clearTimeout(timer);
      reject(new DOMException('Aborted', 'AbortError'));
    };

    signal.addEventListener('abort', onAbort, { once: true });
  });
}

async function run(): Promise<void> {
  state.busy = true;
  controller = new AbortController();

  // One answer per turn, however many rounds of tool use it takes to get there.
  const answer = bubble('assistant');

  try {
    for (let step = 0; step < MAX_STEPS; step += 1) {
      const calls: Array<{ id: string; name: string; input: Record<string, unknown> }> = [];
      const thoughts: Block[] = [];
      let said = '';
      let failed = false;

      for (let attempt = 1; attempt <= MAX_STREAM_ATTEMPTS; attempt += 1) {
        const attemptCalls: Array<{ id: string; name: string; input: Record<string, unknown> }> = [];
        const attemptThoughts: Block[] = [];
        const attemptOpen: { thinking: Step | null } = { thinking: null };
        let attemptSaid = '';
        let attemptFailed = false;
        let attemptError: { message: string; detail: string; retryable: boolean } | null = null;
        let gotContent = false;

        if (attempt > 1) {
          answer.statusHint = __('Still connecting…');
        }

        try {
          await streamChat(
            state.messages,
            (event) => {
              if (event.type === 'error') {
                attemptFailed = true;
                attemptError = {
                  message: event.message ?? __('Something went wrong.'),
                  detail: event.detail ?? '',
                  retryable: event.retryable !== false,
                };
                return;
              }

              if (answer.statusHint) {
                answer.statusHint = null;
              }

              if (event.type === 'thinking' && event.text) {
                gotContent = true;
                attemptOpen.thinking =
                  attemptOpen.thinking ?? beginStep(answer, 'thinking', __('Thinking it through'));
                attemptOpen.thinking.detail += event.text;
              }

              if (event.type === 'thinking_end') {
                if (attemptOpen.thinking) {
                  endStep(attemptOpen.thinking, 'done');
                  attemptOpen.thinking = null;
                }

                // Reasoning goes back to the service exactly as it arrived; the
                // signature is what makes the next turn accept it.
                attemptThoughts.push(
                  event.kind === 'redacted_thinking'
                    ? { type: 'redacted_thinking', data: event.data ?? '' }
                    : { type: 'thinking', thinking: event.thinking ?? '', signature: event.signature ?? '' }
                );
              }

              if (event.type === 'text' && event.text) {
                gotContent = true;

                if (attemptSaid === '' && answer.text !== '') {
                  answer.text += '\n\n';
                }

                attemptSaid += event.text;
                answer.text += event.text;
              }

              if (event.type === 'tool_call' && event.id && event.name) {
                gotContent = true;
                attemptCalls.push({ id: event.id, name: event.name, input: event.arguments ?? {} });
              }
            },
            controller.signal
          );
        } catch (error) {
          if ((error as Error).name === 'AbortError') {
            throw error;
          }

          attemptFailed = true;
          attemptError = {
            message: __('Something interrupted the reply. We can try again.'),
            detail: (error as Error).message,
            retryable: true,
          };
        }

        if (attemptOpen.thinking) {
          endStep(attemptOpen.thinking, 'done');
        }

        if (!attemptFailed) {
          calls.push(...attemptCalls);
          thoughts.push(...attemptThoughts);
          said = attemptSaid;
          answer.statusHint = null;
          break;
        }

        const canRetry =
          attemptError?.retryable !== false && !gotContent && attempt < MAX_STREAM_ATTEMPTS;

        if (!canRetry) {
          failed = true;
          answer.error = attemptError;
          answer.statusHint = null;
          break;
        }

        answer.statusHint = __('Still connecting…');
        await wait(500 * attempt, controller.signal);
      }

      if (failed || calls.length === 0) {
        break;
      }

      const assistantBlocks: Block[] = [...thoughts];

      if (said.trim() !== '') {
        assistantBlocks.push({ type: 'text', text: said });
      }

      for (const call of calls) {
        assistantBlocks.push({ type: 'tool_use', id: call.id, name: call.name, input: call.input });
      }

      state.messages.push({ role: 'assistant', content: assistantBlocks });

      const results: Block[] = [];
      let paused = false;

      for (let index = 0; index < calls.length; index += 1) {
        const call = calls[index];
        const line = beginStep(answer, 'tool', labelFor(call.name, call.input), detailFor(call.name, call.input));
        const outcome = await runTool(call, answer);

        if (outcome === 'paused' && state.pending) {
          // The step stays open: the work is not finished, it is waiting.
          line.label = __('Waiting for you to decide');

          // Every call this turn still owes an answer, even the ones we never
          // reached — they are handed back once the person decides.
          state.pending.collected = results;
          state.pending.skipped = calls.slice(index + 1).map((skipped) => skipped.id);
          paused = true;
          break;
        }

        if (outcome !== 'paused') {
          endStep(line, outcome.type === 'tool_result' && outcome.is_error ? 'failed' : 'done');
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
      answer.error = {
        message: __('Something interrupted the reply. We can try again.'),
        detail: (error as Error).message,
        retryable: true,
      };
    }
  } finally {
    state.busy = false;
    controller = null;
    answer.statusHint = null;

    // A step left open would spin forever — unless the turn is genuinely
    // waiting on a decision.
    if (!state.pending) {
      closeOpenSteps(answer, answer.error ? 'failed' : 'done');
    }

    if (answer.text.trim() === '' && !answer.error && (answer.steps ?? []).length === 0) {
      state.bubbles = state.bubbles.filter((entry) => entry.id !== answer.id);
    } else if (!state.pending && !answer.error) {
      maybeOpenChoices(answer);
    }

    await persist();
  }
}

/** Name the work in the person's terms, never in the site's. */
function labelFor(name: string, input: Record<string, unknown>): string {
  const route = typeof input.route === 'string' ? input.route : '';
  const subject = subjectFor(route);

  switch (name) {
    case 'list_capabilities':
      return __('Looking at what your site can do');
    case 'describe_capability':
      return subject ? sprintf(__('Checking %1$s'), subject) : __('Checking the details');
    case 'read_site':
      return subject ? sprintf(__('Reading %1$s'), subject) : __('Reading from your site');
    case 'change_site':
      if (input.method === 'DELETE') {
        return subject ? sprintf(__('Removing %1$s'), subject) : __('Removing');
      }

      return subject ? sprintf(__('Updating %1$s'), subject) : __('Making the change');
    case 'open_admin_page':
      return __('Finding the right screen');
    case 'memory_list':
      return __('Checking remembered notes');
    case 'memory_write':
      return __('Saving a note');
    case 'memory_delete':
      return __('Forgetting a note');
    case 'read_current_page':
      return __('Reading this screen');
    case 'highlight_on_page':
      return __('Highlighting on the page');
    default:
      return __('Working on it');
  }
}

/**
 * Turn a REST path into a short object name the person can recognize.
 *
 * /wp/v2/posts      → posts
 * /wp/v2/posts/12   → a post
 * /wp/v2/users      → users
 */
function subjectFor(route: string): string {
  const trimmed = route.trim();

  if (trimmed === '') {
    return '';
  }

  const parts = trimmed
    .replace(/^\/+/, '')
    .replace(/\/+$/, '')
    .split('/')
    .filter(Boolean)
    .filter((part) => part !== 'wp-json');

  // Drop the namespace + version: wp/v2/… or namespace/v1/…
  let rest = parts;

  if (rest.length >= 2 && /^v\d/.test(rest[1])) {
    rest = rest.slice(2);
  } else if (rest[0] === 'wp' && rest.length >= 3) {
    rest = rest.slice(2);
  }

  if (rest.length === 0) {
    return '';
  }

  const resource = rest[0].toLowerCase();
  const hasId = rest.length > 1 && (/^\d+$/.test(rest[1]) || rest[1].includes('='));

  const known: Record<string, { one: string; many: string }> = {
    posts: { one: __('a post'), many: __('posts') },
    pages: { one: __('a page'), many: __('pages') },
    users: { one: __('a user'), many: __('users') },
    media: { one: __('a media item'), many: __('media') },
    comments: { one: __('a comment'), many: __('comments') },
    categories: { one: __('a category'), many: __('categories') },
    tags: { one: __('a tag'), many: __('tags') },
    settings: { one: __('settings'), many: __('settings') },
    plugins: { one: __('a plugin'), many: __('plugins') },
    themes: { one: __('a theme'), many: __('themes') },
    types: { one: __('a post type'), many: __('post types') },
    statuses: { one: __('a status'), many: __('statuses') },
    taxonomies: { one: __('a taxonomy'), many: __('taxonomies') },
    search: { one: __('search results'), many: __('search results') },
    blocks: { one: __('a block'), many: __('blocks') },
    'block-types': { one: __('a block type'), many: __('block types') },
    pattern: { one: __('a pattern'), many: __('patterns') },
    menu: { one: __('a menu'), many: __('menus') },
    menus: { one: __('a menu'), many: __('menus') },
    'menu-items': { one: __('a menu item'), many: __('menu items') },
    widgets: { one: __('a widget'), many: __('widgets') },
    'sidebars': { one: __('a sidebar'), many: __('sidebars') },
    'site-editor': { one: __('the site editor'), many: __('the site editor') },
    'application-passwords': { one: __('an application password'), many: __('application passwords') },
  };

  if (known[resource]) {
    return hasId ? known[resource].one : known[resource].many;
  }

  const plain = resource.replace(/[-_]+/g, ' ');

  // translators: %1$s is a WordPress resource name, e.g. "product".
  return hasId ? sprintf(__('a %1$s'), plain.replace(/s$/, '') || plain) : plain;
}

/** The one line of substance behind a step, for anyone who wants to look. */
function detailFor(name: string, input: Record<string, unknown>): string {
  const route = typeof input.route === 'string' ? input.route : '';
  const params = input.params && typeof input.params === 'object' ? (input.params as Record<string, unknown>) : null;
  const hint = paramsHint(params);

  if (name === 'read_site' && route !== '') {
    return hint ? `GET ${route}\n${hint}` : `GET ${route}`;
  }

  if (name === 'change_site' && route !== '') {
    const line = `${String(input.method ?? 'POST')} ${route}`;

    return hint ? `${line}\n${hint}` : line;
  }

  if (name === 'open_admin_page' && typeof input.path === 'string') {
    return input.path;
  }

  if (name === 'memory_write' && typeof input.content === 'string') {
    return input.content.slice(0, 120);
  }

  if (name === 'memory_delete' && typeof input.id === 'string') {
    return input.id;
  }

  if (name === 'highlight_on_page') {
    const bits = [input.selector, input.text].filter((value) => typeof value === 'string' && value !== '');

    return bits.join(' · ');
  }

  return route;
}

/** A compact list of meaningful query fields, when present. */
function paramsHint(params: Record<string, unknown> | null): string {
  if (!params) {
    return '';
  }

  const keep = ['search', 'status', 'per_page', 'page', 'orderby', 'order', 'type', 'parent', 'author', 'slug'];
  const bits: string[] = [];

  for (const key of keep) {
    const value = params[key];

    if (value === undefined || value === null || value === '') {
      continue;
    }

    bits.push(`${key}=${String(value)}`);
  }

  return bits.join(' · ');
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

      appendLinks(answer, [{ label, href: boot.adminUrl + path.replace(/^\/+/, '') }]);

      return result(call.id, { opened: true, note: 'The link was offered to the person.' });
    }

    if (call.name === 'read_site') {
      const outcome = await api.execute({
        method: 'GET',
        route: String(call.input.route ?? ''),
        params: (call.input.params as Record<string, unknown>) ?? {},
      });

      // Reads stay for the model only — do not auto-paint tables/links in the bubble.
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

      // After a real change, offer one set of panel links (deduped).
      applyOutcomeLinks(outcome, answer);

      return result(call.id, compact(outcome), outcome.status !== 'ok');
    }

    if (call.name === 'memory_list') {
      return result(call.id, await api.memoryList());
    }

    if (call.name === 'memory_write') {
      const content = String(call.input.content ?? '');
      const id = typeof call.input.id === 'string' && call.input.id !== '' ? call.input.id : undefined;

      return result(call.id, await api.memoryWrite({ content, id }));
    }

    if (call.name === 'memory_delete') {
      return result(call.id, await api.memoryDelete(String(call.input.id ?? '')));
    }

    if (call.name === 'read_current_page') {
      return result(call.id, readCurrentPage());
    }

    if (call.name === 'highlight_on_page') {
      const outcome = highlightOnPage({
        selector: typeof call.input.selector === 'string' ? call.input.selector : undefined,
        text: typeof call.input.text === 'string' ? call.input.text : undefined,
      });

      return result(call.id, outcome, outcome.ok === false);
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

  const answer = lastAssistant();

  closeOpenSteps(answer);

  const line = beginStep(
    answer,
    'tool',
    labelFor('change_site', { method: pending.method, route: pending.route }),
    `${pending.method} ${pending.route}`
  );

  try {
    const outcome = await api.execute({
      method: pending.method,
      route: pending.route,
      params: pending.params,
      confirmation: pending.token,
    });

    applyOutcomeLinks(outcome, answer);
    endStep(line, outcome.status === 'ok' ? 'done' : 'failed');

    state.messages.push({
      role: 'user',
      content: settle(pending, result(pending.toolUseId, compact(outcome), outcome.status !== 'ok')),
    });
  } catch (error) {
    endStep(line, 'failed');

    state.messages.push({
      role: 'user',
      content: settle(pending, result(pending.toolUseId, { error: (error as Error).message }, true)),
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

  const answer = lastAssistant();

  closeOpenSteps(answer, 'skipped');
  endStep(beginStep(answer, 'note', __('You declined that change')), 'skipped');

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

/** Keep the model's view of a result small and relevant — but never strip display URLs. */
function compact(outcome: Outcome): Record<string, unknown> {
  return {
    status: outcome.status,
    message: outcome.message,
    kind: outcome.kind,
    total: outcome.total ?? null,
    data: shrinkPayload(outcome.data),
    assessment: outcome.assessment,
  };
}

function shrinkPayload(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.slice(0, 20).map(shrinkPayload);
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  const source = value as Record<string, unknown>;
  const isMedia =
    typeof source.source_url === 'string' ||
    source.media_type !== undefined ||
    source.mime_type !== undefined ||
    (typeof source.type === 'string' && source.type === 'attachment');

  const keep = isMedia
    ? [
        'id',
        'title',
        'alt_text',
        'caption',
        'description',
        'slug',
        'status',
        'date',
        'link',
        'source_url',
        'mime_type',
        'media_type',
        'media_details',
      ]
    : ['id', 'title', 'name', 'slug', 'status', 'date', 'link', 'email', 'roles', 'count', 'total', 'excerpt'];

  const kept: Record<string, unknown> = {};

  for (const key of keep) {
    if (!(key in source)) {
      continue;
    }

    const field = source[key];

    if (key === 'media_details') {
      kept[key] = shrinkMediaDetails(field);
      continue;
    }

    kept[key] = unwrapRendered(field);
  }

  return Object.keys(kept).length > 0 ? kept : source;
}

function unwrapRendered(field: unknown): unknown {
  if (field && typeof field === 'object' && 'rendered' in (field as Record<string, unknown>)) {
    return (field as { rendered: unknown }).rendered;
  }

  return field;
}

/** Keep width/height and usable image URLs only. */
function shrinkMediaDetails(field: unknown): unknown {
  if (!field || typeof field !== 'object') {
    return field;
  }

  const details = field as Record<string, unknown>;
  const out: Record<string, unknown> = {};

  for (const key of ['width', 'height', 'file'] as const) {
    if (key in details) {
      out[key] = details[key];
    }
  }

  const sizes = details.sizes;

  if (sizes && typeof sizes === 'object') {
    const shrunk: Record<string, unknown> = {};

    for (const [name, size] of Object.entries(sizes as Record<string, unknown>)) {
      if (!size || typeof size !== 'object') {
        continue;
      }

      const row = size as Record<string, unknown>;

      if (typeof row.source_url === 'string') {
        shrunk[name] = {
          source_url: row.source_url,
          width: row.width,
          height: row.height,
        };
      }
    }

    if (Object.keys(shrunk).length > 0) {
      out.sizes = shrunk;
    }
  }

  return out;
}

/**
 * After a write, offer panel deep-links once — never for list reads, never as tables.
 * Deduplicate by href so repeated tool calls do not spam "Open in the editor".
 */
function applyOutcomeLinks(outcome: Outcome, answer: Bubble): void {
  if (!outcome.links) {
    return;
  }

  const labels: Record<string, string> = {
    edit: __('Open in the editor'),
    view: __('View on the site'),
    preview: __('Preview'),
    file: __('View file'),
  };

  appendLinks(
    answer,
    Object.entries(outcome.links).map(([key, href]) => ({ label: labels[key] ?? key, href }))
  );
}

function appendLinks(answer: Bubble, links: Array<{ label: string; href: string }>): void {
  const existing = answer.links ?? [];
  const seen = new Set(existing.map((link) => link.href));
  const next = [...existing];

  for (const link of links) {
    if (!link.href || seen.has(link.href)) {
      continue;
    }

    seen.add(link.href);
    next.push(link);
  }

  answer.links = next;
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

/** When the model asked multi-choice questions, replace the composer. */
function maybeOpenChoices(answer: Bubble): void {
  const questions = extractChoices(answer.text).filter((question) => question.options.length > 0);

  if (questions.length === 0) {
    return;
  }

  state.choices = { questions };
}

/** The person finished the choice sheet — send answers into the chat. */
export async function submitChoices(
  answers: Array<{ id: string; prompt: string; answer: string }>
): Promise<void> {
  state.choices = null;

  const lines = answers.map((item) => `- ${item.prompt}: ${item.answer}`);
  const text = `${__('My answers:')}\n${lines.join('\n')}`;

  await send(text);
}

/** The person dismissed the choice sheet. */
export function cancelChoices(): void {
  state.choices = null;
}
