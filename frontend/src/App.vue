<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { __, boot } from './api';
import {
  cancel,
  cancelChoices,
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
  submitChoices,
} from './assistant';
import ChoiceSheet from './components/ChoiceSheet.vue';
import Composer from './components/Composer.vue';
import ConfirmCard from './components/ConfirmCard.vue';
import LogoMark from './components/LogoMark.vue';
import MessageBubble from './components/MessageBubble.vue';
import SettingsPanel from './components/SettingsPanel.vue';

const draft = ref('');
const log = ref<HTMLElement | null>(null);
const composer = ref<InstanceType<typeof Composer> | null>(null);

const empty = computed(() => state.bubbles.length === 0);
const lastSteps = computed(() => state.bubbles[state.bubbles.length - 1]?.steps?.length ?? 0);
const liveBubbleId = computed(() => {
  if (!state.busy) {
    return null;
  }

  for (let index = state.bubbles.length - 1; index >= 0; index -= 1) {
    if (state.bubbles[index].role === 'assistant') {
      return state.bubbles[index].id;
    }
  }

  return null;
});

void load();

watch(
  () => [state.bubbles.length, lastSteps.value, state.bubbles[state.bubbles.length - 1]?.text, state.busy],
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
      composer.value?.focus();
    }
  }
);

async function submit(payload: {
  text: string;
  attachments?: Array<{ name: string; type: string; url?: string; size?: number; status: string }>;
}): Promise<void> {
  draft.value = '';
  await send(
    payload.text,
    (payload.attachments ?? [])
      .filter((item) => item.url && item.status !== 'failed')
      .map((item) => ({
        name: item.name,
        type: item.type,
        url: item.url as string,
        size: item.size,
      }))
  );
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
    <button
      v-if="!state.open"
      type="button"
      class="asdevs-ai-launcher"
      :aria-label="__('Open the assistant')"
      @click="open()"
    >
      <LogoMark :size="28" bold />
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
          <span class="asdevs-ai-head__mark" aria-hidden="true">
            <LogoMark :size="18" />
          </span>
          {{ state.view === 'settings' ? __('Settings') : state.view === 'history' ? __('History') : __('Assistant') }}
        </h2>

        <nav class="asdevs-ai-head__tools">
          <button
            v-if="state.view !== 'chat'"
            type="button"
            class="asdevs-ai-icon asdevs-ai-icon--back"
            :aria-label="__('Back to the conversation')"
            :title="__('Back to the conversation')"
            @click="show('chat')"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
              <path d="M15 6 9 12l6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <template v-else>
            <button
              type="button"
              class="asdevs-ai-icon"
              :aria-label="__('New conversation')"
              :title="__('New conversation')"
              @click="startNew()"
            >
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                <path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              </svg>
            </button>
            <button
              type="button"
              class="asdevs-ai-icon"
              :aria-label="__('History')"
              :title="__('History')"
              @click="show('history')"
            >
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                <path d="M4.5 12a7.5 7.5 0 1 0 2.1-5.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <path d="M4.5 5.5v4h4M12 8v4.5l3 1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <button
              v-if="state.canConfigure"
              type="button"
              class="asdevs-ai-icon"
              :aria-label="__('Settings')"
              :title="__('Settings')"
              @click="show('settings')"
            >
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8" />
                <path
                  d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.7.9 1.2 1.6 1.3H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </template>
          <button type="button" class="asdevs-ai-icon" :aria-label="__('Close')" :title="__('Close')" @click="close()">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
              <path d="m7 7 10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
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
                <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">
                  <path d="m7 7 10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
              </button>
            </li>
          </ul>
          <button type="button" class="asdevs-ai-link" @click="removeAllConversations()">
            {{ __('Delete every conversation') }}
          </button>
        </template>
      </div>

      <div v-else-if="state.loadError && !state.loading" class="asdevs-ai-setup">
        <p>{{ __('The assistant could not load its settings for this page.') }}</p>
        <p class="asdevs-ai-note">{{ state.loadError }}</p>
        <button type="button" class="asdevs-ai-btn asdevs-ai-btn--primary" @click="load()">
          {{ __('Try again') }}
        </button>
      </div>

      <div v-else-if="!state.loading && !state.ready" class="asdevs-ai-setup">
        <p>{{ __('The assistant needs a WordPress AI connector to work. Connect a provider under Settings → Connectors, then choose it here.') }}</p>
        <p v-if="state.readyDetail" class="asdevs-ai-note">{{ state.readyDetail }}</p>
        <button
          v-if="state.canConfigure"
          type="button"
          class="asdevs-ai-btn asdevs-ai-btn--primary"
          @click="show('settings')"
        >
          {{ __('Choose a connector') }}
        </button>
        <p v-else class="asdevs-ai-note">{{ __('Ask an administrator of this site to set it up.') }}</p>
      </div>

      <template v-else>
        <div ref="log" class="asdevs-ai-log" role="log" aria-live="polite" aria-relevant="additions text">
          <div v-if="empty" class="asdevs-ai-start">
            <p class="asdevs-ai-start__eyebrow">{{ __('Assistant') }}</p>
            <p class="asdevs-ai-start__question">{{ __('What would you like to do on your site today?') }}</p>
            <ul class="asdevs-ai-start__list">
              <li v-for="suggestion in state.suggestions" :key="suggestion.prompt">
                <button type="button" class="asdevs-ai-chip" @click="send(suggestion.prompt)">
                  <span class="asdevs-ai-chip__label">{{ suggestion.label }}</span>
                  <svg class="asdevs-ai-chip__arrow" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                    <path d="M5 12h12M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
              </li>
            </ul>
          </div>

          <MessageBubble
            v-for="item in state.bubbles"
            :key="item.id"
            :bubble="item"
            :live="item.id === liveBubbleId"
            @retry="retry()"
          />

          <ConfirmCard
            v-if="state.pending"
            :pending="state.pending"
            @confirm="confirmPending()"
            @decline="declinePending()"
          />
        </div>

        <div class="asdevs-ai-dock">
          <ChoiceSheet
            v-if="state.choices"
            :questions="state.choices.questions"
            @submit="submitChoices"
            @cancel="cancelChoices()"
          />
          <Composer v-else ref="composer" v-model="draft" :busy="state.busy" @submit="submit" />
        </div>
      </template>
    </section>
  </div>
</template>
