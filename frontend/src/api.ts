import type {
  AgentBriefing,
  BootData,
  Bootstrap,
  ConnectorTestResult,
  Message,
  Outcome,
  ServiceSettings,
  Skill,
  SkillMatch,
  LoadedSkills,
} from './types';

declare global {
  interface Window {
    asdevsAiAssistant?: BootData;
    wp?: {
      i18n?: {
        __: (text: string, domain?: string) => string;
        sprintf: (format: string, ...args: unknown[]) => string;
      };
    };
  }
}

export type AgentMode = 'agent' | 'ask';

/** What the skill editor sends when saving. */
export interface SkillInput {
  title: string;
  slug?: string;
  prompt: string;
  description?: string;
  when_to_use?: string;
  keywords?: string;
}

export const boot: BootData = window.asdevsAiAssistant ?? {
  restUrl: '',
  siteRest: '',
  nonce: '',
  adminUrl: '',
  locale: 'en_US',
  isRtl: false,
  page: {},
  terms: { version: '', accepted: false, sections: [] },
};

/** Translate through WordPress so every string ships translatable. */
export const __ = (text: string): string => window.wp?.i18n?.__?.(text, 'asdevs-ai-assistant') ?? text;

/** Numbers and names belong inside the sentence, not glued on either side of it. */
export const sprintf = (format: string, ...args: unknown[]): string =>
  window.wp?.i18n?.sprintf?.(format, ...args) ??
  args.reduce<string>((text, value, index) => text.replace(`%${index + 1}$s`, String(value)), format);

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(`${boot.restUrl}${path}`, {
    credentials: 'same-origin',
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': boot.nonce,
      ...(init.headers ?? {}),
    },
  });

  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new Error((body as { message?: string }).message ?? __('Something went wrong.'));
  }

  return (await response.json()) as T;
}

export const api = {
  bootstrap: () => request<Bootstrap>('/bootstrap'),

  capabilities: () => request<unknown>('/capabilities'),

  describe: (route: string) => request<unknown>(`/capabilities/describe?route=${encodeURIComponent(route)}`),

  execute: (payload: { method: string; route: string; params?: Record<string, unknown>; confirmation?: string }) =>
    request<Outcome>('/execute', { method: 'POST', body: JSON.stringify(payload) }),

  settings: () => request<ServiceSettings>('/settings'),

  saveSettings: (payload: { provider: string }) =>
    request<ServiceSettings>('/settings', { method: 'POST', body: JSON.stringify(payload) }),

  testConnector: (provider: string) =>
    request<ConnectorTestResult>('/settings/test', { method: 'POST', body: JSON.stringify({ provider }) }),

  conversation: () =>
    request<{
      id: string;
      title: string;
      messages: Message[];
      pending?: unknown;
      choices?: unknown;
      unfinished?: boolean;
      updated_at?: number;
    }>('/conversation'),

  saveConversation: (payload: {
    id: string;
    title: string;
    messages: Message[];
    pending?: unknown;
    choices?: unknown;
    unfinished: boolean;
  }) => request<{ saved: boolean }>('/conversation', { method: 'POST', body: JSON.stringify(payload) }),

  clearConversation: () => request<{ deleted: boolean }>('/conversation', { method: 'DELETE' }),

  acceptTerms: (version: string) =>
    request<{ accepted: boolean; version: string }>('/terms/accept', {
      method: 'POST',
      body: JSON.stringify({ version }),
    }),

  memoryList: () => request<{ items: Array<Record<string, unknown>> }>('/memory'),

  memoryWrite: (payload: { content: string; id?: string }) =>
    request<{ item: Record<string, unknown> }>('/memory', { method: 'POST', body: JSON.stringify(payload) }),

  memoryDelete: (id: string) =>
    request<{ deleted: boolean; id: string }>(`/memory/${encodeURIComponent(id)}`, { method: 'DELETE' }),

  /**
   * What the assistant is told and which tools it may reach.
   *
   * Decided on the server, where the site context, memory, and skills live —
   * the browser runs the agent loop but never authors its instructions.
   */
  briefing: (payload: { mode: AgentMode; page: BootData['page']; skills: string[] }) =>
    request<AgentBriefing>('/agent/briefing', { method: 'POST', body: JSON.stringify(payload) }),

  skills: () => request<{ items: Skill[] }>('/skills'),

  searchSkills: (query: string, limit = 5) =>
    request<{ query: string; matches: SkillMatch[]; total: number }>(
      `/skills/search?q=${encodeURIComponent(query)}&limit=${limit}`
    ),

  loadSkills: (slugs: string[]) =>
    request<LoadedSkills>('/skills/load', { method: 'POST', body: JSON.stringify({ slugs }) }),

  createSkill: (payload: SkillInput) =>
    request<{ item: Skill }>('/skills', { method: 'POST', body: JSON.stringify(payload) }),

  updateSkill: (id: number, payload: SkillInput) =>
    request<{ item: Skill }>(`/skills/${id}`, { method: 'PUT', body: JSON.stringify(payload) }),

  deleteSkill: (id: number) =>
    request<{ deleted: boolean; id: number }>(`/skills/${id}`, { method: 'DELETE' }),
};

/** Page context merged with the live browser title for the model. */
export function currentPage(): BootData['page'] {
  const title = typeof document !== 'undefined' ? document.title : '';

  return {
    ...boot.page,
    title: boot.page.title || title,
    document_title: title,
  };
}
