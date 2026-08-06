export interface BootData {
  restUrl: string;
  siteRest: string;
  nonce: string;
  adminUrl: string;
  locale: string;
  isRtl: boolean;
  page: PageContext;
}

export interface PageContext {
  screen?: string;
  base?: string;
  post_type?: string;
  taxonomy?: string;
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
  can_configure: boolean;
  settings_url: string;
  site: Record<string, unknown>;
  user: { display_name: string; roles: string[] };
  suggestions: Suggestion[];
  conversations: ConversationSummary[];
}

export interface ConversationSummary {
  id: string;
  title: string;
  updated_at: number;
  unfinished: boolean;
}

export type Block =
  | { type: 'text'; text: string }
  | { type: 'thinking'; thinking: string; signature: string }
  | { type: 'redacted_thinking'; data: string }
  | { type: 'tool_use'; id: string; name: string; input: Record<string, unknown> }
  | { type: 'tool_result'; tool_use_id: string; content: string; is_error?: boolean };

export interface Message {
  role: 'user' | 'assistant';
  content: Block[];
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
  /** Everything that happened before the answer, in order. */
  steps?: Step[];
  /** Rows to show as a table rather than prose. */
  rows?: Array<Record<string, string>>;
  total?: number | null;
  links?: Array<{ label: string; href: string }>;
  error?: { message: string; detail: string; retryable: boolean } | null;
}

export interface ProviderInfo {
  id: string;
  label: string;
  models: Record<string, string>;
  default_model: string;
  reasoning_models: string[];
  configured: boolean;
}

export interface ServiceSettings {
  provider: string;
  model: string;
  thinking: boolean;
  has_key: boolean;
  ready: boolean;
  providers: ProviderInfo[];
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
  links?: Record<string, string>;
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

export interface StreamEvent {
  type: 'text' | 'thinking' | 'thinking_end' | 'tool_call' | 'done' | 'error' | 'end';
  text?: string;
  id?: string;
  name?: string;
  arguments?: Record<string, unknown>;
  reason?: string;
  message?: string;
  detail?: string;
  retryable?: boolean;
  /** Reasoning blocks, which go back to the service untouched on the next turn. */
  kind?: 'thinking' | 'redacted_thinking';
  thinking?: string;
  signature?: string;
  data?: string;
}
