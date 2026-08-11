export interface BootData {
  restUrl: string;
  siteRest: string;
  nonce: string;
  adminUrl: string;
  locale: string;
  isRtl: boolean;
  page: PageContext;
  terms?: TermsState;
}

export interface TermsSection {
  heading: string;
  body: string;
}

export interface TermsState {
  version: string;
  accepted: boolean;
  sections: TermsSection[];
}

export interface PageContext {
  screen?: string;
  base?: string;
  post_type?: string;
  taxonomy?: string;
  title?: string;
  document_title?: string;
  description?: string;
  focus?: {
    type: string;
    id: number;
    title: string;
    route: string;
    status?: string;
    post_type?: string;
  };
}

export interface Suggestion {
  label: string;
  prompt: string;
}

export interface Bootstrap {
  ready: boolean;
  /** Present when ready is false — why the server thinks no connector is usable. */
  ready_detail?: string;
  can_configure: boolean;
  settings_url: string;
  /** Active connector id when ready. */
  provider?: string;
  /** Text-generation models for the active connector. */
  models?: ModelInfo[];
  site: Record<string, unknown>;
  user: { display_name: string; roles: string[] };
  suggestions: Suggestion[];
  conversation: ActiveConversation | null;
  terms?: TermsState;
}

export type Block =
  | { type: 'text'; text: string }
  | { type: 'file'; url: string; mime_type: string; name?: string }
  | { type: 'thinking'; thinking: string; signature: string }
  | { type: 'redacted_thinking'; data: string }
  | { type: 'tool_use'; id: string; name: string; input: Record<string, unknown> }
  | { type: 'tool_result'; tool_use_id: string; content: string; is_error?: boolean };

export interface Message {
  role: 'user' | 'assistant';
  content: Block[];
}

/** One tool the server allows, exactly as the model is told about it. */
export interface ToolSchema {
  name: string;
  description: string;
  input_schema: Record<string, unknown>;
}

/** Instructions and tools for one run, decided on the server. */
export interface AgentBriefing {
  instructions: string;
  tools: ToolSchema[];
  skills: string[];
  mode: string;
}

/** A file shown on a user bubble (and sent to the model as a file part). */
export interface MessageAttachment {
  name: string;
  type: string;
  url: string;
  size?: number;
}

/** One thing the assistant did, in the order it happened. */
export interface Step {
  id: string;
  kind: 'thinking' | 'tool' | 'note';
  label: string;
  /** The reasoning itself, or what a tool was asked to do. */
  detail: string;
  status: 'running' | 'done' | 'failed' | 'skipped';
  startedAt: number;
  endedAt: number | null;
}

/** What the person actually sees in the window. */
export interface Bubble {
  id: string;
  role: 'user' | 'assistant';
  text: string;
  /** Uploaded files shown on a user bubble (thumbnails / chips). */
  attachments?: MessageAttachment[];
  /** Everything that happened before the answer, in order. */
  steps?: Step[];
  /** Soft live status (e.g. reconnecting) shown instead of the default typing label. */
  statusHint?: string | null;
  /** True while the agent engine is still being fetched for this turn. */
  booting?: boolean;
  error?: { message: string; detail: string; retryable: boolean } | null;
}

export interface ProviderInfo {
  id: string;
  label: string;
  configured: boolean;
}

/** One chat model exposed by the active WordPress AI connector. */
export interface ModelInfo {
  id: string;
  label: string;
}

export interface ServiceSettings {
  provider: string;
  ready: boolean;
  providers: ProviderInfo[];
  models?: ModelInfo[];
  connectors_url: string;
}

/** A reusable instruction prompt the person can activate with /slug. */
export interface Skill {
  id: number;
  title: string;
  slug: string;
  prompt: string;
  description: string;
  /** Written for the assistant: when it should reach for this skill on its own. */
  when_to_use?: string;
  /** Extra search terms and synonyms, comma separated. */
  keywords?: string;
  created_at?: number;
  updated_at?: number;
}

/** One hit from the assistant's own skill search — metadata, never the prompt. */
export interface SkillMatch {
  slug: string;
  title: string;
  description: string;
  when_to_use: string;
  score: number;
  matched_on: string[];
}

/** What came back when the assistant loaded skills for itself. */
export interface LoadedSkills {
  loaded: Array<{ slug: string; title: string; description: string; prompt: string }>;
  missing: string[];
  skipped: string[];
}

export interface ConnectorTestResult {
  ok: boolean;
  message: string;
  reply?: string;
  detail?: string;
}

export interface Assessment {
  level: number;
  blocked: boolean;
  reversible: boolean;
  summary: string;
  affected: number | null;
}

export interface Outcome {
  status: 'ok' | 'failed' | 'refused' | 'confirmation_required';
  code?: number;
  data?: unknown;
  total?: number | null;
  message?: string;
  kind?: string;
  details?: Record<string, unknown>;
  assessment?: Assessment;
  confirmation?: string;
  action?: { method: string; route: string };
}

export interface PendingConfirmation {
  toolUseId: string;
  token: string;
  summary: string;
  reversible: boolean;
  affected: number | null;
  method: string;
  route: string;
  params: Record<string, unknown>;
  /** Results of the calls that already ran in this same turn. */
  collected: Block[];
  /** Calls from this turn that were not reached, which still owe a result. */
  skipped: string[];
}

export interface ChoiceSession {
  questions: Array<{ id: string; prompt: string; options: string[] }>;
}

export interface ActiveConversation {
  id: string;
  title: string;
  messages: Message[];
  pending?: PendingConfirmation | null;
  choices?: ChoiceSession | null;
  unfinished?: boolean;
  updated_at?: number;
}

