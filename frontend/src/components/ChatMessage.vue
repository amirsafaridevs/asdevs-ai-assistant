<template>
  <div class="asdevs-message" :class="message.role">
    <!-- Assistant Avatar -->
    <div v-if="message.role === 'assistant'" class="asdevs-avatar asdevs-avatar-sm">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2L9.5 9.5L2 12L9.5 14.5L12 22L14.5 14.5L22 12L14.5 9.5L12 2Z" fill="white" />
      </svg>
    </div>

    <div class="asdevs-bubble">
      <!-- Message Content (rendered as markdown-like HTML) -->
      <div v-html="renderedContent"></div>

      <!-- Navigation Card -->
      <div v-if="message.navigationCard" class="asdevs-nav-card">
        <div class="asdevs-nav-card-title">{{ message.navigationCard.title }}</div>
        <div class="asdevs-nav-card-path">{{ message.navigationCard.path }}</div>
        <button class="asdevs-nav-cta" @click="handleNavigate">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
          Take Me There
        </button>
      </div>

      <!-- Transition Card (before redirect) -->
      <div v-if="message.role === 'assistant' && isTransitionMessage" class="asdevs-transition-card">
        <div class="asdevs-transition-label">Taking you to:</div>
        <div class="asdevs-transition-dest">{{ transitionDestination }}</div>
      </div>
    </div>

    <!-- User Avatar -->
    <div v-if="message.role === 'user'" class="asdevs-avatar asdevs-avatar-sm asdevs-avatar-user">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v1.2c0 .66.54 1.2 1.2 1.2h16.8c.66 0 1.2-.54 1.2-1.2v-1.2c0-3.2-6.4-4.8-9.6-4.8z" />
      </svg>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import type { ChatMessage as ChatMessageType } from '../stores/chatStore';
import { useNavigationStore } from '../stores/navigationStore';

// Configure marked for safe rendering
marked.setOptions({
  breaks: true,       // Convert \n to <br>
  gfm: true,          // GitHub Flavored Markdown (tables, strikethrough, etc.)
});

const props = defineProps<{
  message: ChatMessageType;
}>();

const emit = defineEmits<{
  navigate: [url: string];
}>();

const navStore = useNavigationStore();

const renderedContent = computed(() => {
  const content = props.message.content || '';
  if (!content.trim()) return '';

  try {
    // Parse markdown and add target="_blank" to links
    let html = marked.parse(content) as string;
    // Make all links open in new tab
    html = html.replace(/<a /g, '<a target="_blank" rel="noopener noreferrer" ');
    return html;
  } catch {
    // Fallback: escape HTML and preserve line breaks
    return content.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/\n/g, '<br>');
  }
});

const isTransitionMessage = computed(() => {
  const text = props.message.content || '';
  return (
    props.message.role === 'assistant' &&
    (text.includes('Taking you to') || text.includes('Navigating to'))
  );
});

const transitionDestination = computed(() => {
  if (props.message.navigationCard) {
    return props.message.navigationCard.path;
  }
  return '';
});

function handleNavigate(): void {
  if (props.message.navigationCard?.url) {
    navStore.performRedirect(props.message.navigationCard.url);
    emit('navigate', props.message.navigationCard.url);
  }
}
</script>

<style scoped>
.asdevs-avatar-sm {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  flex-shrink: 0;
}

.asdevs-avatar-user {
  background: linear-gradient(135deg, #5856D6, #AF52DE);
}

.asdevs-avatar-sm svg {
  width: 14px;
  height: 14px;
}

/* ===== Markdown Content Styles ===== */
.asdevs-bubble :deep(h1) {
  font-size: 1.4em;
  font-weight: 700;
  margin: 16px 0 8px;
  line-height: 1.3;
  color: inherit;
}

.asdevs-bubble :deep(h2) {
  font-size: 1.25em;
  font-weight: 700;
  margin: 14px 0 6px;
  line-height: 1.3;
  color: inherit;
}

.asdevs-bubble :deep(h3) {
  font-size: 1.1em;
  font-weight: 600;
  margin: 12px 0 6px;
  line-height: 1.3;
  color: inherit;
}

.asdevs-bubble :deep(h4),
.asdevs-bubble :deep(h5),
.asdevs-bubble :deep(h6) {
  font-size: 1em;
  font-weight: 600;
  margin: 10px 0 4px;
  line-height: 1.3;
  color: inherit;
}

.asdevs-bubble :deep(p) {
  margin: 4px 0;
  line-height: 1.55;
}

.asdevs-bubble :deep(p:first-child) {
  margin-top: 0;
}

.asdevs-bubble :deep(p:last-child) {
  margin-bottom: 0;
}

/* Unordered & Ordered Lists */
.asdevs-bubble :deep(ul),
.asdevs-bubble :deep(ol) {
  margin: 6px 0;
  padding-left: 20px;
}

.asdevs-bubble :deep(li) {
  margin: 2px 0;
  line-height: 1.5;
}

.asdevs-bubble :deep(ul ul),
.asdevs-bubble :deep(ol ol),
.asdevs-bubble :deep(ul ol),
.asdevs-bubble :deep(ol ul) {
  margin: 2px 0;
}

/* Blockquote */
.asdevs-bubble :deep(blockquote) {
  margin: 8px 0;
  padding: 6px 12px;
  border-left: 3px solid var(--asdevs-primary, #007AFF);
  background: rgba(0, 122, 255, 0.04);
  border-radius: 0 6px 6px 0;
  color: rgba(0, 0, 0, 0.7);
}

.asdevs-bubble :deep(blockquote p) {
  margin: 4px 0;
}

/* Code */
.asdevs-bubble :deep(code) {
  background: rgba(0, 0, 0, 0.06);
  border-radius: 4px;
  padding: 2px 5px;
  font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
  font-size: 0.88em;
  word-break: break-word;
}

.asdevs-bubble :deep(pre) {
  background: rgba(0, 0, 0, 0.04);
  border-radius: 8px;
  padding: 10px 12px;
  overflow-x: auto;
  font-size: 13px;
  margin: 8px 0;
  line-height: 1.5;
}

.asdevs-bubble :deep(pre code) {
  background: none;
  padding: 0;
  font-size: inherit;
  border-radius: 0;
}

/* Links */
.asdevs-bubble :deep(a) {
  color: var(--asdevs-primary, #007AFF);
  text-decoration: none;
}

.asdevs-bubble :deep(a:hover) {
  text-decoration: underline;
}

/* Tables */
.asdevs-bubble :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 8px 0;
  font-size: 0.92em;
}

.asdevs-bubble :deep(th),
.asdevs-bubble :deep(td) {
  border: 1px solid rgba(0, 0, 0, 0.1);
  padding: 6px 10px;
  text-align: left;
}

.asdevs-bubble :deep(th) {
  background: rgba(0, 0, 0, 0.04);
  font-weight: 600;
}

.asdevs-bubble :deep(tr:nth-child(even)) {
  background: rgba(0, 0, 0, 0.02);
}

/* Horizontal Rule */
.asdevs-bubble :deep(hr) {
  border: none;
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  margin: 12px 0;
}

/* Images */
.asdevs-bubble :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 8px 0;
}

/* Bold & Italic */
.asdevs-bubble :deep(strong) {
  font-weight: 600;
}

.asdevs-bubble :deep(em) {
  font-style: italic;
}

/* Strikethrough */
.asdevs-bubble :deep(del) {
  text-decoration: line-through;
  opacity: 0.7;
}
</style>
