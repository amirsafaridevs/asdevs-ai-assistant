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
  startNew,
  state,
} from './assistant';
import ConfirmCard from './components/ConfirmCard.vue';
import MessageBubble from './components/MessageBubble.vue';

const draft = ref('');
const log = ref<HTMLElement | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);

const empty = computed(() => state.bubbles.length === 0);

void load();

watch(
  () => [state.bubbles.length, state.activity, state.bubbles[state.bubbles.length - 1]?.text],
  async () => {
    await nextTick();
    log.value?.scrollTo({ top: log.value.scrollHeight, behavior: 'smooth' });
  }
);

watch(
  () => state.open,
  async (isOpen) => {
    if (isOpen) {
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
      <span aria-hidden="true">◍</span>
    </button>

    <section
      v-if="state.open"
      class="asdevs-ai-panel"
      role="dialog"
      :aria-label="__('Assistant')"
      @keydown.esc="onEscape"
    >
      <header class="asdevs-ai-panel__head">
        <h2 class="asdevs-ai-panel__title">{{ __('Assistant') }}</h2>
        <div class="asdevs-ai-panel__tools">
          <button type="button" class="asdevs-ai-link" @click="startNew()">{{ __('New') }}</button>
          <button
            type="button"
            class="asdevs-ai-link"
            :aria-expanded="state.showHistory"
            @click="state.showHistory = !state.showHistory"
          >
            {{ __('History') }}
          </button>
          <button type="button" class="asdevs-ai-icon" :aria-label="__('Close')" @click="close()">×</button>
        </div>
      </header>

      <div v-if="state.showHistory" class="asdevs-ai-history">
        <p v-if="!state.conversations.length" class="asdevs-ai-msg__note">{{ __('No earlier conversations.') }}</p>
        <ul v-else class="asdevs-ai-history__list">
          <li v-for="conversation in state.conversations" :key="conversation.id">
            <button type="button" class="asdevs-ai-link" @click="openConversation(conversation.id)">
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
        <button
          v-if="state.conversations.length"
          type="button"
          class="asdevs-ai-link"
          @click="removeAllConversations()"
        >
          {{ __('Delete every conversation') }}
        </button>
      </div>

      <!-- The one case where setup is asked for, and only inside the window. -->
      <div v-else-if="!state.loading && !state.ready" class="asdevs-ai-setup">
        <p>{{ __('The assistant needs an AI service to work. Set it up once and you will not need to come back.') }}</p>
        <a v-if="state.canConfigure" class="asdevs-ai-btn asdevs-ai-btn--primary" :href="state.settingsUrl">
          {{ __('Set up the AI service') }}
        </a>
        <p v-else class="asdevs-ai-msg__note">{{ __('Ask an administrator of this site to set it up.') }}</p>
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

          <MessageBubble
            v-for="item in state.bubbles"
            :key="item.id"
            :bubble="item"
            @retry="retry()"
          />

          <ConfirmCard
            v-if="state.pending"
            :pending="state.pending"
            @confirm="confirmPending()"
            @decline="declinePending()"
          />

          <p v-if="state.busy && state.activity" class="asdevs-ai-activity">
            <span>{{ state.activity }}</span>
            <button type="button" class="asdevs-ai-link" @click="cancel()">{{ __('Stop') }}</button>
          </p>
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
          <button type="submit" class="asdevs-ai-btn asdevs-ai-btn--primary" :disabled="state.busy || !draft.trim()">
            {{ __('Send') }}
          </button>
        </form>
      </template>
    </section>
  </div>
</template>
