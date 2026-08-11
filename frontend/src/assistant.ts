import { reactive } from 'vue';
// Types only: erased at build time, so the SDK stays out of the main bundle.
import type { Agent, RunState, RunToolApprovalItem } from '@openai/agents';
import { __, api, boot, currentPage, sprintf } from './api';
import type { AgentMode } from './api';
import { fromAgentInput, restoreAttachments, toAgentInput } from './agent/history';
import { compact, toolText } from './agent/results';
import type { ConfirmationRequest, ToolContext } from './agent/tools';
import { extractChoices } from './markdown';
import type {
  ActiveConversation,
  Block,
  Bubble,
  Bootstrap,
  ChoiceSession,
  Message,
  MessageAttachment,
  ModelInfo,
  PendingConfirmation,
  ServiceSettings,
  Skill,
  Step,
  Suggestion,
  TermsSection,
} from './types';

/** How many times one request may go around the tool loop before stopping. */
const MAX_STEPS = 12;

/**
 * The agent engine, fetched the first time it is actually needed.
 *
 * The OpenAI Agents SDK is by far the heaviest thing this widget carries, and
 * most admin pages are loaded without anyone opening the assistant. Deferring
 * it keeps those page loads light; opening the window starts the download, so
 * by the time a message is typed it is almost always already here.
 */
type Engine = typeof import('./agent/engine');

let engine: Engine | null = null;
let engineRequest: Promise<Engine> | null = null;

function loadEngine(): Promise<Engine> {
  if (!engineRequest) {
    engineRequest = import('./agent/engine')
      .then((loaded) => {
        engine = loaded;

        return loaded;
      })
      .catch((error: unknown) => {
        // A failed chunk must not poison every later attempt.
        engineRequest = null;

        throw error;
      });
  }

  return engineRequest;
}

type View = 'chat' | 'settings' | 'skills';

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
  pending: PendingConfirmation | null;
  choices: ChoiceSession | null;
  settingsUrl: string;
  canConfigure: boolean;
  conversationId: string;
  title: string;
  lastPrompt: string;
  /** Agent (do work) or Ask (read-only answers) for the current turn. */
  mode: AgentMode;
  /** Active connector id from the last bootstrap/settings load. */
  provider: string;
  /** Text-generation models for the active connector. */
  models: ModelInfo[];
  /** Preferred model id, or "auto" for connector default. Persisted in localStorage. */
  model: string;
  /** The connector preference, edited in place inside the window. */
  settings: ServiceSettings | null;
  settingsBusy: boolean;
  settingsSaved: boolean;
  settingsError: string;
  /** Reusable skill prompts defined on this site. */
  skills: Skill[];
  skillsBusy: boolean;
  skillsError: string;
  /** Sticky skill slugs active until the person clears them. */
  activeSkills: string[];
  /** Of those, the ones the assistant picked for itself rather than being handed. */
  autoSkills: string[];
  /** Current terms document version from the server. */
  termsVersion: string;
  /** Whether the signed-in user has accepted the current terms version. */
  termsAccepted: boolean;
  termsSections: TermsSection[];
  termsBusy: boolean;
  termsError: string;
}

const bootTerms = boot.terms;

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
  pending: null,
  choices: null,
  settingsUrl: '',
  canConfigure: false,
  conversationId: newId(),
  title: '',
  lastPrompt: '',
  mode: 'agent',
  provider: '',
  models: [],
  model: 'auto',
  settings: null,
  settingsBusy: false,
  settingsSaved: false,
  settingsError: '',
  skills: [],
  skillsBusy: false,
  skillsError: '',
  activeSkills: [],
  autoSkills: [],
  termsVersion: bootTerms?.version ?? '',
  termsAccepted: bootTerms?.accepted === true,
  termsSections: Array.isArray(bootTerms?.sections) ? bootTerms.sections : [],
  termsBusy: false,
  termsError: '',
});

let controller: AbortController | null = null;

/** Only restore the server chat once per page load, never mid-session. */
let hydrated = false;

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
  if (!state.termsAccepted) {
    state.loading = false;
    state.ready = false;

    return;
  }

  state.loading = true;
  state.loadError = '';

  try {
    const data: Bootstrap = await api.bootstrap();

    if (data.terms) {
      applyTerms(data.terms);
    }

    if (!state.termsAccepted) {
      return;
    }

    state.ready = data.ready;
    state.readyDetail = data.ready ? '' : (data.ready_detail ?? '');
    state.suggestions = data.suggestions;
    state.settingsUrl = data.settings_url;
    state.canConfigure = data.can_configure;
    applyModels(data.provider ?? '', data.models ?? []);

    // Restore the active chat exactly once. Re-opening the launcher must keep
    // whatever is already in memory (or what was hydrated at bootstrap).
    if (!hydrated) {
      hydrated = true;

      if (data.conversation && (data.conversation.messages?.length ?? 0) > 0) {
        applyConversation(data.conversation);
      }
    }

    void loadSkills();
  } catch (error) {
    // A failed bootstrap is not the same as a missing connector — keep any
    // earlier ready value and show the real error instead of the setup screen.
    state.loadError = (error as Error).message || __('Could not reach the assistant.');
  } finally {
    state.loading = false;
  }
}

function applyTerms(terms: { version: string; accepted: boolean; sections?: TermsSection[] }): void {
  state.termsVersion = terms.version ?? '';
  state.termsAccepted = terms.accepted === true;

  if (Array.isArray(terms.sections) && terms.sections.length > 0) {
    state.termsSections = terms.sections;
  }
}

export async function acceptTerms(): Promise<void> {
  if (state.termsBusy || state.termsAccepted) {
    return;
  }

  state.termsBusy = true;
  state.termsError = '';

  try {
    const result = await api.acceptTerms(state.termsVersion);
    state.termsAccepted = result.accepted === true;
    state.termsVersion = result.version || state.termsVersion;
    state.termsSections = [];
    state.view = 'chat';
    await load();
  } catch (error) {
    state.termsError = (error as Error).message || __('Could not save your acceptance.');
  } finally {
    state.termsBusy = false;
  }
}

export function open(): void {
  state.open = true;

  if (!state.termsAccepted) {
    return;
  }

  // Fetch the agent engine while they are still reading or typing, so sending
  // the first message rarely has to wait for it.
  void loadEngine().catch(() => {
    // Nothing to say yet — the run itself reports a failure it cannot recover.
  });

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
  paused = null;
  approvals = [];

  for (const item of state.bubbles) {
    closeOpenSteps(item, 'skipped');
  }
}

export function show(view: View): void {
  if (!state.termsAccepted) {
    return;
  }

  state.view = state.view === view && view !== 'chat' ? 'chat' : view;

  if (state.view === 'settings') {
    void loadSettings();
  }

  if (state.view === 'skills') {
    void loadSkills();
  }
}

export async function loadSkills(): Promise<void> {
  if (!state.termsAccepted || state.skillsBusy) {
    return;
  }

  state.skillsBusy = true;
  state.skillsError = '';

  try {
    const data = await api.skills();
    state.skills = Array.isArray(data.items) ? data.items : [];
    // Drop sticky selections that no longer exist.
    const known = new Set(state.skills.map((item) => item.slug));
    state.activeSkills = state.activeSkills.filter((slug) => known.has(slug));
    state.autoSkills = state.autoSkills.filter((slug) => known.has(slug));
  } catch (error) {
    state.skillsError = (error as Error).message || __('Could not load skills.');
  } finally {
    state.skillsBusy = false;
  }
}

export function toggleSkill(slug: string): void {
  const clean = slug.trim().toLowerCase();

  if (clean === '') {
    return;
  }

  if (state.activeSkills.includes(clean)) {
    state.activeSkills = state.activeSkills.filter((item) => item !== clean);
    return;
  }

  if (state.skills.some((item) => item.slug === clean)) {
    state.activeSkills = [...state.activeSkills, clean];
  }
}

export function clearSkill(slug: string): void {
  state.activeSkills = state.activeSkills.filter((item) => item !== slug);
  state.autoSkills = state.autoSkills.filter((item) => item !== slug);
}

export function clearActiveSkills(): void {
  state.activeSkills = [];
  state.autoSkills = [];
}

/**
 * Make the skills the assistant loaded for itself sticky and visible.
 *
 * The person did not ask for these, so they show up as chips they can take
 * back off — the assistant choosing a skill is a suggestion they can overrule,
 * not something that happens behind their back.
 */
function activateLoadedSkills(slugs: string[]): void {
  for (const slug of slugs) {
    const clean = slug.trim().toLowerCase();

    if (clean === '' || state.activeSkills.includes(clean)) {
      continue;
    }

    state.activeSkills = [...state.activeSkills, clean];

    if (!state.autoSkills.includes(clean)) {
      state.autoSkills = [...state.autoSkills, clean];
    }
  }
}

export async function saveSkill(payload: {
  id?: number;
  title: string;
  slug?: string;
  prompt: string;
  description?: string;
  when_to_use?: string;
  keywords?: string;
}): Promise<Skill> {
  const body = {
    title: payload.title,
    slug: payload.slug,
    prompt: payload.prompt,
    description: payload.description,
    when_to_use: payload.when_to_use,
    keywords: payload.keywords,
  };

  const result =
    payload.id && payload.id > 0
      ? await api.updateSkill(payload.id, body)
      : await api.createSkill(body);

  await loadSkills();

  return result.item;
}

export async function removeSkill(id: number): Promise<void> {
  await api.deleteSkill(id);
  await loadSkills();
}

export async function startNew(): Promise<void> {
  cancel();
  resetLocal();
  state.view = 'chat';

  try {
    await api.clearConversation();
  } catch {
    // Starting fresh must still work offline of the save endpoint.
  }
}

/** Wipe the in-memory chat without touching the server. */
function resetLocal(): void {
  // Skills the person pinned stay pinned; the ones the assistant chose belonged
  // to the task that just ended.
  if (state.autoSkills.length > 0) {
    const auto = new Set(state.autoSkills);

    state.activeSkills = state.activeSkills.filter((slug) => !auto.has(slug));
    state.autoSkills = [];
  }

  state.bubbles = [];
  state.messages = [];
  state.pending = null;
  state.choices = null;
  paused = null;
  approvals = [];
  state.conversationId = newId();
  state.title = '';
  state.lastPrompt = '';
}

/** Put a stored conversation back on screen and into the model trail. */
function applyConversation(stored: ActiveConversation): void {
  state.conversationId = stored.id || newId();
  state.title = stored.title ?? '';
  state.messages = Array.isArray(stored.messages) ? stored.messages : [];
  state.bubbles = rebuild(state.messages);
  state.pending = isPending(stored.pending) ? stored.pending : null;
  state.choices = isChoices(stored.choices) ? stored.choices : null;
  state.view = 'chat';

  if (state.pending) {
    markWaitingForDecision();
  }
}

function isPending(value: unknown): value is PendingConfirmation {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const pending = value as Partial<PendingConfirmation>;

  return (
    typeof pending.toolUseId === 'string' &&
    pending.toolUseId !== '' &&
    typeof pending.token === 'string' &&
    pending.token !== '' &&
    typeof pending.method === 'string' &&
    typeof pending.route === 'string'
  );
}

function isChoices(value: unknown): value is ChoiceSession {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const choices = value as Partial<ChoiceSession>;

  return Array.isArray(choices.questions) && choices.questions.length > 0;
}

/** After restore, the paused tool step should look like it is waiting. */
function markWaitingForDecision(): void {
  const pending = state.pending;

  if (!pending) {
    return;
  }

  for (let index = state.bubbles.length - 1; index >= 0; index -= 1) {
    const item = state.bubbles[index];

    if (item.role !== 'assistant') {
      continue;
    }

    for (const step of item.steps ?? []) {
      if (step.kind === 'tool' && step.status === 'running') {
        step.label = __('Waiting for you to decide');
      }
    }

    break;
  }
}

/* -------------------------------------------------------------------------
 * The WordPress connector preference, edited where it is used.
 * ---------------------------------------------------------------------- */

const MODEL_STORAGE_PREFIX = 'asdevs-ai-model:';

function modelStorageKey(provider: string): string {
  return `${MODEL_STORAGE_PREFIX}${provider || 'none'}`;
}

function readStoredModel(provider: string): string {
  try {
    const value = window.localStorage.getItem(modelStorageKey(provider));

    return value && value.trim() !== '' ? value.trim() : 'auto';
  } catch {
    return 'auto';
  }
}

function writeStoredModel(provider: string, model: string): void {
  try {
    window.localStorage.setItem(modelStorageKey(provider), model);
  } catch {
    // Private mode / disabled storage — keep the in-memory choice only.
  }
}

/** Sync the composer model list and restore the last pick for this connector. */
function applyModels(provider: string, models: ModelInfo[]): void {
  state.provider = provider;
  state.models = Array.isArray(models) ? models : [];

  const stored = readStoredModel(provider);
  const valid =
    stored === 'auto' || state.models.some((item) => item.id === stored);

  state.model = valid ? stored : 'auto';
}

/** Remember which model the person prefers for the active connector. */
export function chooseModel(model: string): void {
  const next = model.trim() === '' ? 'auto' : model.trim();
  const valid = next === 'auto' || state.models.some((item) => item.id === next);

  state.model = valid ? next : 'auto';
  writeStoredModel(state.provider, state.model);
}

export async function loadSettings(): Promise<void> {
  if (!state.canConfigure || state.settingsBusy) {
    return;
  }

  state.settingsBusy = true;
  state.settingsError = '';

  try {
    state.settings = await api.settings();
    applyModels(state.settings.provider, state.settings.models ?? []);
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
    applyModels(state.settings.provider, state.settings.models ?? []);

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

/** Rebuild the visible conversation from the full model trail. */
function rebuild(messages: Message[]): Bubble[] {
  const bubbles: Bubble[] = [];
  let currentAssistant: Bubble | null = null;
  const openTools = new Map<string, Step>();

  for (const message of messages) {
    if (message.role === 'user') {
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

      const toolResults = message.content.filter(
        (block): block is Extract<Block, { type: 'tool_result' }> => block.type === 'tool_result'
      );

      if (text.trim() !== '' || attachments.length > 0) {
        currentAssistant = null;
        openTools.clear();
        bubbles.push({
          id: newId(),
          role: 'user',
          text,
          attachments: attachments.length ? attachments : undefined,
          steps: [],
          error: null,
        });
      }

      for (const result of toolResults) {
        const step = openTools.get(result.tool_use_id);

        if (!step) {
          continue;
        }

        endStep(step, result.is_error ? 'failed' : 'done');
        openTools.delete(result.tool_use_id);
      }

      continue;
    }

    // Assistant turn — keep one on-screen bubble for the whole tool loop.
    if (!currentAssistant) {
      currentAssistant = {
        id: newId(),
        role: 'assistant',
        text: '',
        steps: [],
        error: null,
      };
      bubbles.push(currentAssistant);
    }

    const answer = currentAssistant;

    for (const block of message.content) {
      if (block.type === 'text') {
        if (answer.text !== '' && block.text.trim() !== '') {
          answer.text += '\n\n';
        }

        answer.text += block.text;
        continue;
      }

      if (block.type === 'thinking') {
        const step = beginStep(answer, 'thinking', __('Thinking it through'), block.thinking);
        endStep(step, 'done');
        continue;
      }

      if (block.type === 'tool_use') {
        const step = beginStep(answer, 'tool', labelFor(block.name, block.input), detailFor(block.name, block.input));
        openTools.set(block.id, step);
      }
    }
  }

  // Tools without a result yet stay running (e.g. waiting on confirmation).
  return bubbles;
}

export async function send(
  text: string,
  attachments: MessageAttachment[] = [],
  mode: AgentMode = 'agent',
  model = 'auto'
): Promise<void> {
  const files = attachments.filter((item) => item.url.trim() !== '');
  const prepared = applySlashSkills(text.trim());
  const trimmed = prepared.text;

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
  state.mode = mode === 'ask' ? 'ask' : 'agent';

  const preferred = model.trim() === '' ? 'auto' : model.trim();
  const valid = preferred === 'auto' || state.models.some((item) => item.id === preferred);
  state.model = valid ? preferred : 'auto';
  writeStoredModel(state.provider, state.model);

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

/**
 * Activate sticky skills from leading /slug tokens and strip them from the message.
 */
function applySlashSkills(text: string): { text: string } {
  if (text === '') {
    return { text };
  }

  const known = new Map(state.skills.map((item) => [item.slug, item]));
  let rest = text;

  for (;;) {
    const match = rest.match(/^\/([a-z0-9][a-z0-9-]*)(?:\s+|$)/i);

    if (!match) {
      break;
    }

    const slug = match[1].toLowerCase();

    if (!known.has(slug)) {
      break;
    }

    if (!state.activeSkills.includes(slug)) {
      state.activeSkills = [...state.activeSkills, slug];
    }

    rest = rest.slice(match[0].length).trimStart();
  }

  return { text: rest };
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

/** What the tools need from the session they are running inside. */
const toolContext: ToolContext = {
  mode: () => state.mode,
  onSkillsLoaded: (slugs) => activateLoadedSkills(slugs),
  onConfirmationRequired: (request: ConfirmationRequest) => {
    // The token stays here, out of the conversation, so only a real click can
    // redeem it. `collected` and `skipped` stay empty now that the SDK's run
    // state remembers the rest of the turn; they are kept for conversations
    // saved by earlier versions.
    state.pending = {
      toolUseId: request.callId,
      token: request.token,
      summary: request.summary,
      reversible: request.reversible,
      affected: request.affected,
      method: request.method,
      route: request.route,
      params: request.params,
      collected: [],
      skipped: [],
    };
  },
};

/** The run that stopped for a confirmation, kept so it can carry on in place. */
let paused: RunState<unknown, Agent<unknown, 'text'>> | null = null;

/** The calls that run is waiting on a decision for. */
let approvals: RunToolApprovalItem[] = [];

/**
 * Plan first, when the request is big enough to be worth planning.
 *
 * The plan is context for the working agent, not orders — what is actually on
 * the site always wins over what was guessed before looking.
 */
async function instructionsFor(
  sdk: Engine,
  base: string,
  answer: Bubble,
  signal: AbortSignal
): Promise<string> {
  if (!sdk.worthPlanning(state.lastPrompt, state.mode)) {
    return base;
  }

  const step = beginStep(answer, 'thinking', __('Working out what you need'));
  const plan = await sdk.planFor(state.lastPrompt, base, state.model === 'auto' ? '' : state.model, signal);

  if (!plan) {
    // Nothing useful came back; do not leave an empty line on the timeline.
    answer.steps = (answer.steps ?? []).filter((entry) => entry.id !== step.id);

    return base;
  }

  const context = sdk.planAsContext(plan);

  step.detail = context;
  endStep(step, 'done');

  return `${base}\n\n--- Plan for this request ---\n${context}`;
}

/**
 * One assistant turn, driven by the OpenAI Agents SDK.
 *
 * The loop, the tool calls, and the turn limit are the SDK's. What the model is
 * told and which tools exist still come from the server, and the model itself
 * is still reached through this site's own connector — the SDK talks to the
 * plugin's OpenAI-shaped endpoint, never to a vendor.
 *
 * @param resume A run that stopped for a confirmation and may now carry on.
 */
async function run(resume: RunState<unknown, Agent<unknown, 'text'>> | null = null): Promise<void> {
  state.busy = true;
  controller = new AbortController();

  const signal = controller.signal;
  const answer = resume ? lastAssistant() : bubble('assistant');
  const openTools = new Map<string, Step>();

  // Text after a tool call is a new paragraph, not a continuation of the last.
  let gapPending = false;

  try {
    // Usually already here, because opening the window started the download.
    // When it is not, the bubble shows what is being prepared rather than an
    // empty pause.
    answer.booting = engine === null;

    const sdk = engine ?? (await loadEngine());

    answer.booting = false;

    sdk.configureRuntime();

    const briefing = await api.briefing({
      mode: state.mode,
      page: currentPage(),
      skills: [...state.activeSkills],
    });

    const agent = new sdk.Agent({
      name: 'Assistant',
      instructions: resume ? briefing.instructions : await instructionsFor(sdk, briefing.instructions, answer, signal),
      model: state.model === '' ? 'auto' : state.model,
      tools: sdk.createTools(briefing.tools, toolContext),
    });

    const result = await sdk.run(agent, resume ?? toAgentInput(state.messages), {
      stream: true,
      maxTurns: MAX_STEPS,
      signal,
    });

    for await (const event of result) {
      if (event.type === 'raw_model_stream_event') {
        if (event.data.type !== 'output_text_delta') {
          continue;
        }

        answer.statusHint = null;

        if (gapPending && answer.text !== '') {
          answer.text += '\n\n';
        }

        gapPending = false;
        answer.text += event.data.delta;

        continue;
      }

      if (event.type !== 'run_item_stream_event') {
        continue;
      }

      const item = event.item;

      if (item.type === 'reasoning_item') {
        // Chat Completions puts the thought on rawContent, not content.
        const raw = item.rawItem.rawContent ?? [];
        const thought =
          raw.length > 0
            ? raw.map((part) => part.text).join('')
            : item.rawItem.content.map((part) => part.text).join('');

        if (thought.trim() !== '') {
          endStep(beginStep(answer, 'thinking', __('Thinking it through'), thought), 'done');
        }

        continue;
      }

      if (item.type === 'tool_call_item' && item.rawItem.type === 'function_call') {
        const call = item.rawItem;
        const input = parseArguments(call.arguments);

        openTools.set(call.callId, beginStep(answer, 'tool', labelFor(call.name, input), detailFor(call.name, input)));
        gapPending = true;

        continue;
      }

      if (item.type === 'tool_approval_item') {
        // Waiting is not the same as working.
        for (const step of openTools.values()) {
          if (step.status === 'running') {
            step.label = __('Waiting for you to decide');
          }
        }

        continue;
      }

      if (item.type === 'tool_call_output_item' && item.rawItem.type === 'function_call_result') {
        const step = openTools.get(item.rawItem.callId);

        if (step) {
          endStep(step, failed(item.rawItem.status, item.output) ? 'failed' : 'done');
          openTools.delete(item.rawItem.callId);
        }
      }
    }

    await result.completed;

    state.messages = restoreAttachments(fromAgentInput(result.history), state.messages);

    const waiting = result.interruptions ?? [];

    if (waiting.length > 0) {
      paused = result.state as RunState<unknown, Agent<unknown, 'text'>>;
      approvals = waiting;
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
    answer.booting = false;

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

/** Tool arguments arrive as a JSON string; a broken one is not worth throwing over. */
function parseArguments(raw: string): Record<string, unknown> {
  try {
    const parsed: unknown = JSON.parse(raw || '{}');

    return parsed && typeof parsed === 'object' ? (parsed as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

/** Whether a tool answer should read as a failure on the timeline. */
function failed(status: string, output: unknown): boolean {
  if (status === 'incomplete') {
    return true;
  }

  const text = typeof output === 'string' ? output : JSON.stringify(output ?? '');

  return text.includes('"error"') || text.includes('"status":"error"');
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
    case 'call_api':
      if (input.method === 'GET') {
        return subject ? sprintf(__('Reading %1$s'), subject) : __('Reading from your site');
      }

      if (input.method === 'DELETE') {
        return subject ? sprintf(__('Removing %1$s'), subject) : __('Removing');
      }

      return subject ? sprintf(__('Updating %1$s'), subject) : __('Making the change');
    case 'find_skills':
      return __('Looking for a skill that fits');
    case 'load_skill':
      return __('Following a skill from this site');
    case 'memory_list':
      return __('Checking remembered notes');
    case 'memory_write':
      return __('Saving a note');
    case 'memory_delete':
      return __('Forgetting a note');
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

  if (name === 'call_api' && route !== '') {
    const line = `${String(input.method ?? 'GET')} ${route}`;

    return hint ? `${line}\n${hint}` : line;
  }

  if (name === 'find_skills' && typeof input.query === 'string') {
    return input.query.slice(0, 120);
  }

  if (name === 'load_skill' && Array.isArray(input.slugs)) {
    return input.slugs.map((slug) => `/${String(slug)}`).join(' · ');
  }

  if (name === 'memory_write' && typeof input.content === 'string') {
    return input.content.slice(0, 120);
  }

  if (name === 'memory_delete' && typeof input.id === 'string') {
    return input.id;
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

/** The person said yes to a level three change. */
export async function confirmPending(): Promise<void> {
  const pending = state.pending;

  if (!pending || state.busy) {
    return;
  }

  state.pending = null;

  if (paused && approvals.length > 0) {
    const resume = paused;
    const items = approvals;

    paused = null;
    approvals = [];

    for (const item of items) {
      resume.approve(item);
    }

    await run(resume);

    return;
  }

  // Restored after a reload, so there is no run left to carry on. Redeem the
  // token by hand and let the assistant pick the thread back up from there.
  await settleByHand(pending, true);
}

/** The person said no. The assistant is told plainly, and does not insist. */
export async function declinePending(): Promise<void> {
  const pending = state.pending;

  if (!pending) {
    return;
  }

  state.pending = null;

  if (paused && approvals.length > 0) {
    const resume = paused;
    const items = approvals;

    paused = null;
    approvals = [];

    const answer = lastAssistant();

    closeOpenSteps(answer, 'skipped');
    endStep(beginStep(answer, 'note', __('You declined that change')), 'skipped');

    for (const item of items) {
      resume.reject(item);
    }

    await run(resume);

    return;
  }

  await settleByHand(pending, false);
}

/**
 * Close out a confirmation the browser no longer has a running turn for.
 *
 * This is the reload path: the decision is recorded straight into the stored
 * conversation, and the next turn starts from it.
 */
async function settleByHand(pending: PendingConfirmation, approved: boolean): Promise<void> {
  const answer = lastAssistant();

  if (!approved) {
    closeOpenSteps(answer, 'skipped');
    endStep(beginStep(answer, 'note', __('You declined that change')), 'skipped');

    state.messages.push({
      role: 'user',
      content: [
        toolResult(
          pending.toolUseId,
          { declined: true, note: 'The person declined this change. Do not ask again.' },
          false
        ),
      ],
    });

    await run();

    return;
  }

  state.busy = true;

  closeOpenSteps(answer);

  const line = beginStep(
    answer,
    'tool',
    labelFor('call_api', { method: pending.method, route: pending.route }),
    `${pending.method} ${pending.route}`
  );

  try {
    const outcome = await api.execute({
      method: pending.method,
      route: pending.route,
      params: pending.params,
      confirmation: pending.token,
    });

    endStep(line, outcome.status === 'ok' ? 'done' : 'failed');

    state.messages.push({
      role: 'user',
      content: [toolResult(pending.toolUseId, compact(outcome), outcome.status !== 'ok')],
    });
  } catch (error) {
    endStep(line, 'failed');

    state.messages.push({
      role: 'user',
      content: [toolResult(pending.toolUseId, { error: (error as Error).message }, true)],
    });
  } finally {
    state.busy = false;
  }

  await run();
}

function toolResult(id: string, payload: unknown, isError: boolean): Block {
  return { type: 'tool_result', tool_use_id: id, content: toolText(payload), is_error: isError };
}

async function persist(): Promise<void> {
  if (state.messages.length === 0 && !state.pending) {
    return;
  }

  try {
    await api.saveConversation({
      id: state.conversationId,
      title: state.title,
      messages: state.messages,
      pending: state.pending,
      choices: state.choices,
      unfinished: state.pending !== null,
    });
  } catch {
    // Persistence must not break the answer the person is looking at.
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

  const text = answers
    .map((item) => `${item.prompt}\n${item.answer}`)
    .join('\n\n');

  await send(text);
}

/** The person dismissed the choice sheet. */
export function cancelChoices(): void {
  state.choices = null;
  void persist();
}
