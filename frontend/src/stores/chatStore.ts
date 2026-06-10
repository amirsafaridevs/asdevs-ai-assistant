import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export interface ChatMessage {
  id: string;
  role: 'user' | 'assistant' | 'system' | 'tool';
  content: string;
  timestamp: number;
  toolCalls?: ToolCall[];
  toolCallId?: string;
  navigationCard?: NavigationCardData;
}

export interface ToolCall {
  id?: string;
  type?: 'function';
  function?: {
    name: string;
    arguments: string;
  };
  name?: string;
  arguments?: Record<string, any> | string;
  result?: any;
}

export interface NavigationCardData {
  title: string;
  path: string;
  url: string;
}

export interface SessionData {
  sessionId: string;
  messages: ChatMessage[];
  taskState: Record<string, any>;
  navigationState: Record<string, any>;
}

const STORAGE_KEY = 'asdevs-ai-assistant-session';

function generateId(): string {
  return 'msg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
}

function generateSessionId(): string {
  return 'sess_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
}

export const useChatStore = defineStore('chat', () => {
  const sessionId = ref<string>(generateSessionId());
  const messages = ref<ChatMessage[]>([]);
  const loading = ref(false);

  const lastMessage = computed(() => {
    return messages.value.length > 0
      ? messages.value[messages.value.length - 1]
      : null;
  });

  function addMessage(role: ChatMessage['role'], content: string, extras?: Partial<ChatMessage>): ChatMessage {
    const msg: ChatMessage = {
      id: generateId(),
      role,
      content,
      timestamp: Date.now(),
      ...extras,
    };
    messages.value.push(msg);
    persistToStorage();
    return msg;
  }

  function updateMessage(id: string, updates: Partial<ChatMessage>): void {
    const idx = messages.value.findIndex((m) => m.id === id);
    if (idx !== -1) {
      messages.value[idx] = { ...messages.value[idx], ...updates };
      persistToStorage();
    }
  }

  function removeMessage(id: string): void {
    messages.value = messages.value.filter((m) => m.id !== id);
    persistToStorage();
  }

  function clearAll(): void {
    messages.value = [];
    sessionId.value = generateSessionId();
    persistToStorage();
  }

  function newChat(): void {
    clearAll();
  }

  function persistToStorage(): void {
    const data: SessionData = {
      sessionId: sessionId.value,
      messages: messages.value,
      taskState: {},
      navigationState: {},
    };
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
      // Storage full or unavailable – silently ignore
    }
  }

  function restoreFromStorage(): void {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const data: SessionData = JSON.parse(raw);
      sessionId.value = data.sessionId || generateSessionId();
      messages.value = data.messages || [];
    } catch {
      // Corrupted data – start fresh
    }
  }

  function getMessagesForAI(): Array<{ role: string; content: string }> {
    return messages.value.map((m) => ({
      role: m.role,
      content: m.content,
    }));
  }

  return {
    sessionId,
    messages,
    loading,
    lastMessage,
    addMessage,
    updateMessage,
    removeMessage,
    clearAll,
    newChat,
    persistToStorage,
    restoreFromStorage,
    getMessagesForAI,
  };
});
