<template>
  <div class="asdevs-panel" role="dialog" aria-label="AI Assistant" aria-modal="true">
    <!-- New Chat Confirmation Overlay -->
    <Transition name="confirm">
      <div v-if="showNewChatConfirm" class="asdevs-confirm-overlay">
        <div class="asdevs-confirm-card">
          <div class="asdevs-confirm-title">Start a fresh session?</div>
          <div class="asdevs-confirm-actions">
            <button class="asdevs-btn-secondary" @click="showNewChatConfirm = false">Cancel</button>
            <button class="asdevs-btn-danger" @click="confirmNewChat">New Chat</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Navigation Banner -->
    <NavigationBanner />

    <!-- Header -->
    <header class="asdevs-header">
      <div class="asdevs-avatar">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L9.5 9.5L2 12L9.5 14.5L12 22L14.5 14.5L22 12L14.5 9.5L12 2Z" fill="white" />
        </svg>
      </div>
      <div class="asdevs-header-info">
        <div class="asdevs-header-title">ASDevs AI Assistant</div>
        <div class="asdevs-header-status" :style="{ color: statusColor }">{{ statusText }}</div>
      </div>
      <div class="asdevs-header-actions">
        <button
          class="asdevs-header-btn"
          @click="showNewChatConfirm = true"
          title="New Chat"
          aria-label="Start new chat"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M12 5v14M5 12h14" />
          </svg>
        </button>
        <button
          class="asdevs-header-btn"
          @click="$emit('close')"
          title="Close"
          aria-label="Close assistant"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M18 6L6 18M6 6l12 12" />
          </svg>
        </button>
      </div>
    </header>



    <!-- Chat Window -->
    <ChatWindow @send="handleSend" />

    <!-- Input Area -->
    <div class="asdevs-input-area">
      <!-- Loading state: context is still being fetched via AJAX -->
      <div v-if="contextStore.loading" class="asdevs-input-loading">
        <span class="asdevs-spinner"></span>
        <span>Loading WordPress context…</span>
      </div>
      <template v-else>
        <textarea
          ref="inputEl"
          class="asdevs-input"
          v-model="userInput"
          @keydown.enter.exact.prevent="handleSendMessage"
          @keydown.enter.shift.exact="userInput += '\n'"
          placeholder="Ask me anything about WordPress..."
          rows="1"
          :disabled="chatStore.loading"
          aria-label="Type your message"
        ></textarea>
        <button
          class="asdevs-send-btn"
          @click="handleSendMessage"
          :disabled="!userInput.trim() || chatStore.loading"
          aria-label="Send message"
        >
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
          </svg>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { useChatStore } from '../stores/chatStore';
import { useContextStore } from '../stores/contextStore';
import { useNavigationStore } from '../stores/navigationStore';
import { agent } from '../agent/agent';
import ChatWindow from './ChatWindow.vue';
import NavigationBanner from './NavigationBanner.vue';

const chatStore = useChatStore();
const contextStore = useContextStore();
const navStore = useNavigationStore();

const userInput = ref('');
const inputEl = ref<HTMLTextAreaElement | null>(null);
const showNewChatConfirm = ref(false);

defineEmits<{
  close: [];
}>();

const statusText = computed(() => {
  if (chatStore.loading) return 'Thinking...';
  if (navStore.redirectPending) return 'Navigating...';
  if (contextStore.loading) return 'Loading context...';
  return 'Ready to help';
});

const statusColor = computed(() => {
  if (chatStore.loading || navStore.redirectPending) return 'var(--asdevs-warning, #FF9F0A)';
  return 'var(--asdevs-success, #34C759)';
});

const contextChips = computed(() => {
  const chips: Array<{ label: string; value?: string; tooltip?: string; icon?: string }> = [];

  if (contextStore.theme) {
    chips.push({
      label: 'Theme',
      value: contextStore.theme.name,
      tooltip: `${contextStore.theme.name} v${contextStore.theme.version}`,
    });
  }

  chips.push({
    label: 'Plugins',
    value: `${contextStore.activeCount} active`,
    tooltip: `${contextStore.activeCount} active plugins out of ${contextStore.totalPlugins} total`,
  });

  if (contextStore.currentPage?.pageTitle) {
    chips.push({
      label: 'Page',
      value: truncate(contextStore.currentPage.pageTitle, 20),
      tooltip: contextStore.currentPage.pageTitle,
    });
  }

  return chips;
});

function truncate(text: string, max: number): string {
  return text.length > max ? text.substring(0, max) + '...' : text;
}

async function handleSendMessage(): Promise<void> {
  const text = userInput.value.trim();
  if (!text || chatStore.loading) return;

  userInput.value = '';

  // Clear any existing navigation state for fresh queries
  if (!navStore.redirectPending) {
    // Keep redirect state if we're in the middle of one
  }

  await agent.processMessage(text);

  nextTick(() => {
    inputEl.value?.focus();
  });
}

function handleSend(message: string): void {
  userInput.value = message;
  handleSendMessage();
}

function confirmNewChat(): void {
  showNewChatConfirm.value = false;
  chatStore.newChat();
  navStore.clearNavigation();
  userInput.value = '';

  nextTick(() => {
    inputEl.value?.focus();
  });
}

onMounted(() => {
  nextTick(() => {
    inputEl.value?.focus();
  });
});
</script>

<style scoped>
.confirm-enter-active,
.confirm-leave-active {
  transition: opacity 200ms ease;
}
.confirm-enter-from,
.confirm-leave-to {
  opacity: 0;
}
</style>
