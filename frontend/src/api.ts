import type {
  BootData,
  Bootstrap,
  ConversationSummary,
  Message,
  Outcome,
  ServiceSettings,
  StreamEvent,
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

export const boot: BootData = window.asdevsAiAssistant ?? {
  restUrl: '',
  siteRest: '',
  nonce: '',
  adminUrl: '',
  locale: 'en_US',
  isRtl: false,
  page: {},
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

  saveSettings: (payload: { provider: string; model: string; key: string; thinking: boolean }) =>
    request<ServiceSettings>('/settings', { method: 'POST', body: JSON.stringify(payload) }),

  conversations: () => request<ConversationSummary[]>('/conversations'),

  conversation: (id: string) => request<{ id: string; title: string; messages: Message[] }>(`/conversations/${id}`),

  saveConversation: (payload: { id: string; title: string; messages: Message[]; unfinished: boolean }) =>
    request<{ saved: boolean }>('/conversations', { method: 'POST', body: JSON.stringify(payload) }),

  deleteConversation: (id: string) => request<{ deleted: boolean }>(`/conversations/${id}`, { method: 'DELETE' }),

  clearConversations: () => request<{ deleted: boolean }>('/conversations', { method: 'DELETE' }),
};

/**
 * Stream one assistant turn.
 *
 * The AI service is never contacted from here — this talks to the site, which
 * holds the key.
 */
export async function streamChat(
  messages: Message[],
  onEvent: (event: StreamEvent) => void,
  signal: AbortSignal
): Promise<void> {
  const response = await fetch(`${boot.restUrl}/chat`, {
    method: 'POST',
    credentials: 'same-origin',
    signal,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': boot.nonce,
    },
    body: JSON.stringify({ messages, page: boot.page }),
  });

  if (!response.ok || !response.body) {
    const body = await response.json().catch(() => ({}));

    onEvent({
      type: 'error',
      message: (body as { message?: string }).message ?? __('The assistant could not be reached.'),
      detail: `HTTP ${response.status}`,
      retryable: response.status !== 409,
    });

    return;
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  for (;;) {
    const { done, value } = await reader.read();

    if (done) {
      break;
    }

    buffer += decoder.decode(value, { stream: true });

    let boundary = buffer.indexOf('\n\n');

    while (boundary !== -1) {
      const chunk = buffer.slice(0, boundary).trim();
      buffer = buffer.slice(boundary + 2);
      boundary = buffer.indexOf('\n\n');

      if (!chunk.startsWith('data:')) {
        continue;
      }

      try {
        onEvent(JSON.parse(chunk.slice(5).trim()) as StreamEvent);
      } catch {
        // A partial frame is not worth interrupting the answer for.
      }
    }
  }
}
