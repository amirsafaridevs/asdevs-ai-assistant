<template>
  <div class="asdevs-chat" ref="chatContainer">
    <!-- Empty State -->
    <div v-if="messages.length === 0 && !loading" class="asdevs-empty-state">
      <div class="asdevs-empty-logo">
        <svg width="28" height="28" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L9.5 9.5L2 12L9.5 14.5L12 22L14.5 14.5L22 12L14.5 9.5L12 2Z" fill="white" />
        </svg>
      </div>
      <div class="asdevs-empty-title">Ask anything about your WordPress site</div>
      <div class="asdevs-empty-subtitle">
        I can help you find settings, understand plugins, and navigate your admin panel.
      </div>
      <div class="asdevs-suggestions">
        <button
          v-for="suggestion in suggestions"
          :key="suggestion"
          class="asdevs-suggestion-card"
          @click="sendSuggestion(suggestion)"
        >
          {{ suggestion }}
        </button>
      </div>
    </div>

    <!-- Messages -->
    <template v-for="msg in messages" :key="msg.id">
      <ChatMessage :message="msg" />
    </template>

    <!-- Typing Indicator — only show when waiting for the first token (no streaming message yet) -->
    <TypingIndicator v-if="loading && messages.length > 0 && messages[messages.length - 1]?.role !== 'assistant'" />

    <!-- Auto-scroll anchor -->
    <div ref="scrollAnchor"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useChatStore } from '../stores/chatStore';
import ChatMessage from './ChatMessage.vue';
import TypingIndicator from './TypingIndicator.vue';

const chatStore = useChatStore();

// Filter out tool messages - they are internal only, not shown to the user
const messages = computed(() => chatStore.messages.filter(m => m.role !== 'tool'));
const loading = computed(() => chatStore.loading);

const chatContainer = ref<HTMLElement | null>(null);
const scrollAnchor = ref<HTMLElement | null>(null);

const suggestions = [
  'How do I change my site logo?',
  'Where are WooCommerce settings?',
  'How do I disable comments?',
  'Where can I edit checkout fields?',
  'How do I create a new page?',
  'Where is the Elementor settings page?',
];

const emit = defineEmits<{
  send: [message: string];
}>();

function sendSuggestion(text: string): void {
  emit('send', text);
}

// Auto-scroll to bottom when new messages arrive
watch(
  () => messages.value.length,
  () => {
    nextTick(() => {
      scrollAnchor.value?.scrollIntoView({ behavior: 'smooth' });
    });
  }
);

watch(
  () => loading.value,
  (val) => {
    if (val) {
      nextTick(() => {
        scrollAnchor.value?.scrollIntoView({ behavior: 'smooth' });
      });
    }
  }
);

// Auto-scroll during streaming — follows token-by-token content updates
watch(
  () => messages.value[messages.value.length - 1]?.content,
  () => {
    if (loading.value) {
      nextTick(() => {
        scrollAnchor.value?.scrollIntoView({ behavior: 'instant' as ScrollBehavior });
      });
    }
  }
);

onMounted(() => {
  nextTick(() => {
    scrollAnchor.value?.scrollIntoView();
  });
});
</script>
