<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { __, boot } from './api';
import {
  cancel,
  close,
  confirmPending,
  declinePending,
  load,
  open,
  openConversation,
  removeAllConversations,
  removeConversation,
  retry,
  send,
  show,
  startNew,
  state,
} from './assistant';
import ConfirmCard from './components/ConfirmCard.vue';
import MessageBubble from './components/MessageBubble.vue';
import SettingsPanel from './components/SettingsPanel.vue';

const draft = ref('');
const log = ref<HTMLElement | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);

const empty = computed(() => state.bubbles.length === 0);
const lastSteps = computed(() => state.bubbles[state.bubbles.length - 1]?.steps?.length ?? 0);

void load();

watch(
  () => [state.bubbles.length, lastSteps.value, state.bubbles[state.bubbles.length - 1]?.text],
  async () => {
    await nextTick();
    log.value?.scrollTo({ top: log.value.scrollHeight, behavior: 'smooth' });
  }
);

watch(
  () => [state.open, state.view],
  async () => {
    if (state.open && state.view === 'chat') {
      await nextTick();
      input.value?.focus();
    }
  }
);

async function submit(): Promise<void> {
  const text = draft.value;
  draft.value = '';
  await send(text);
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    void submit();
  }
}

function onEscape(): void {
  if (state.busy) {
    cancel();

    return;
  }

  if (state.view !== 'chat') {
    show('chat');

    return;
  }

  close();
}
</script>

<template>
  <div class="asdevs-ai" :dir="boot.isRtl ? 'rtl' : 'ltr'">
    <!-- One small, steady point of entry. No badge, no dot, no animation. -->
    <button
      v-if="!state.open"
      type="button"
      class="asdevs-ai-launcher"
      :aria-label="__('Open the assistant')"
      @click="open()"
    >
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
        <path
          d="M12 3.5c1 2.7 1.8 3.5 4.5 4.5-2.7 1-3.5 1.8-4.5 4.5-1-2.7-1.8-3.5-4.5-4.5 2.7-1 3.5-1.8 4.5-4.5Z"
          fill="currentColor"
        />
        <path
          d="M17.5 13.5c.55 1.5 1 1.95 2.5 2.5-1.5.55-1.95 1-2.5 2.5-.55-1.5-1-1.95-2.5-2.5 1.5-.55 1.95-1 2.5-2.5Z"
          fill="currentColor"
          opacity=".55"
        />
      </svg>
    </button>

    <section
      v-if="state.open"
      class="asdevs-ai-panel"
      role="dialog"
      :aria-label="__('Assistant')"
      @keydown.esc="onEscape"
    >
      <header class="asdevs-ai-head">
        <h2 class="asdevs-ai-head__title">
          <span class="asdevs-ai-head__dot" aria-hidden="true"></span>
          {{ state.view === 'settings' ? __('Settings') : state.view === 'history' ? __('History') : __('Assistant') }}
        </h2>

        <nav class="asdevs-ai-head__tools">
          <button
            v-if="state.view !== 'chat'"
            type="button"
            class="asdevs-ai-icon"
            :aria-label="__('Back to the conversation')"
            :title="__('Back to the conversation')"
            @click="show('chat')"
          >
            ←
          </button>
          <template v-else>
            <button
              type="button"
              class="asdevs-ai-icon"
              :aria-label="__('New conversation')"
              :title="__('New conversation')"
              @click="startNew()"
            >
              +
            </button>
            <button
              type="button"
              class="asdevs-ai-icon"
              :aria-label="__('History')"
              :title="__('History')"
              @click="show('history')"
            >
              ⟲
            </button>
            <button
              v-if="state.canConfigure"
              type="button"
              class="asdevs-ai-icon"
              :aria-label="__('Settings')"
              :title="__('Settings')"
              @click="show('settings')"
            >
              ⚙
            </button>
          </template>
          <button type="button" class="asdevs-ai-icon" :aria-label="__('Close')" :title="__('Close')" @click="close()">
            ×
          </button>
        </nav>
      </header>

      <SettingsPanel v-if="state.view === 'settings'" />

      <div v-else-if="state.view === 'history'" class="asdevs-ai-history">
        <p v-if="!state.conversations.length" class="asdevs-ai-note">{{ __('No earlier conversations.') }}</p>
        <template v-else>
          <ul class="asdevs-ai-history__list">
            <li v-for="conversation in state.conversations" :key="conversation.id">
              <button type="button" class="asdevs-ai-history__open" @click="openConversation(conversation.id)">
                {{ conversation.title || __('Untitled') }}
              </button>
              <button
                type="button"
                class="asdevs-ai-icon"
                :aria-label="__('Delete this conversation')"
                @click="removeConversation(conversation.id)"
              >
                ×
              </button>
            </li>
          </ul>
          <button type="button" class="asdevs-ai-link" @click="removeAllConversations()">
            {{ __('Delete every conversation') }}
          </button>
        </template>
      </div>

      <!-- Setup is asked for once, and only inside the window. -->
      <div v-else-if="!state.loading && !state.ready" class="asdevs-ai-setup">
        <p>{{ __('The assistant needs an AI service to work. Set it up once and you will not need to come back.') }}</p>
        <button
          v-if="state.canConfigure"
          type="button"
          class="asdevs-ai-btn asdevs-ai-btn--primary"
          @click="show('settings')"
        >
          {{ __('Set up the AI service') }}
        </button>
        <p v-else class="asdevs-ai-note">{{ __('Ask an administrator of this site to set it up.') }}</p>
      </div>

      <template v-else>
        <div ref="log" class="asdevs-ai-log" role="log" aria-live="polite" aria-relevant="additions text">
          <div v-if="empty" class="asdevs-ai-start">
            <p class="asdevs-ai-start__question">{{ __('What would you like to do on your site today?') }}</p>
            <ul class="asdevs-ai-start__list">
              <li v-for="suggestion in state.suggestions" :key="suggestion.prompt">
                <button type="button" class="asdevs-ai-chip" @click="send(suggestion.prompt)">
                  {{ suggestion.label }}
                </button>
              </li>
            </ul>
          </div>

          <MessageBubble v-for="item in state.bubbles" :key="item.id" :bubble="item" @retry="retry()" />

          <ConfirmCard
            v-if="state.pending"
            :pending="state.pending"
            @confirm="confirmPending()"
            @decline="declinePending()"
          />
        </div>

        <form class="asdevs-ai-composer" @submit.prevent="submit()">
          <label class="screen-reader-text" for="asdevs-ai-input">{{ __('Message the assistant') }}</label>
          <textarea
            id="asdevs-ai-input"
            ref="input"
            v-model="draft"
            class="asdevs-ai-composer__input"
            rows="1"
            :placeholder="__('Tell me what you need')"
            @keydown="onKeydown"
          ></textarea>

          <button
            v-if="state.busy"
            type="button"
            class="asdevs-ai-send asdevs-ai-send--stop"
            :aria-label="__('Stop')"
            :title="__('Stop')"
            @click="cancel()"
          >
            <span aria-hidden="true"></span>
          </button>
          <button
            v-else
            type="submit"
            class="asdevs-ai-send"
            :disabled="!draft.trim()"
            :aria-label="__('Send')"
            :title="__('Send')"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
              <path d="M4 12h13M11 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </form>
      </template>
    </section>
  </div>
</template>
