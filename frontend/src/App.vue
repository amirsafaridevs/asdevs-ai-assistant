<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { __, boot } from './api';
import {
  acceptTerms,
  cancel,
  cancelChoices,
  chooseModel,
  clearSkill,
  close,
  confirmPending,
  declinePending,
  load,
  open,
  retry,
  send,
  show,
  startNew,
  state,
  submitChoices,
  toggleSkill,
} from './assistant';
import ChoiceSheet from './components/ChoiceSheet.vue';
import Composer from './components/Composer.vue';
import ConfirmCard from './components/ConfirmCard.vue';
import LogoMark from './components/LogoMark.vue';
import MessageBubble from './components/MessageBubble.vue';
import SettingsPanel from './components/SettingsPanel.vue';
import SkillsPanel from './components/SkillsPanel.vue';
import TermsGate from './components/TermsGate.vue';

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

const headerTitle = computed(() => {
  if (!state.termsAccepted) {
    return __('Terms of use');
  }

  if (state.view === 'settings') {
    return __('Settings');
  }

  if (state.view === 'skills') {
    return __('Skills');
  }

  return __('Assistant');
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
  () => [state.open, state.view, state.termsAccepted],
  async () => {
    if (state.open && state.termsAccepted && state.view === 'chat') {
      await nextTick();
      composer.value?.focus();
    }
  }
);

async function submit(payload: {
  text: string;
  attachments?: Array<{ name: string; type: string; url?: string; size?: number; status: string }>;
  agent?: 'agent' | 'ask';
  model?: string;
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
      })),
    payload.agent ?? 'agent',
    payload.model ?? 'auto'
  );
}

function onEscape(): void {
  if (!state.termsAccepted) {
    close();

    return;
  }

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
      :aria-label="state.termsAccepted ? __('Assistant') : __('Terms of use')"
      @keydown.esc="onEscape"
    >
      <header class="asdevs-ai-head">
        <h2 class="asdevs-ai-head__title">
          <span class="asdevs-ai-head__mark" aria-hidden="true">
            <LogoMark :size="18" />
          </span>
          {{ headerTitle }}
        </h2>

        <nav class="asdevs-ai-head__tools">
          <template v-if="state.termsAccepted">
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
                :aria-label="__('Skills')"
                :title="__('Skills')"
                @click="show('skills')"
              >
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                  <path
                    d="M7 3.5h7.2L18.5 8v12.5a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-16a1 1 0 0 1 1-1Z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M14 3.5V8h4.5M9 12h6M9 15.5h6"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
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
          </template>
          <button type="button" class="asdevs-ai-icon" :aria-label="__('Close')" :title="__('Close')" @click="close()">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
              <path d="m7 7 10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </nav>
      </header>

      <TermsGate
        v-if="!state.termsAccepted"
        :version="state.termsVersion"
        :sections="state.termsSections"
        :busy="state.termsBusy"
        :error="state.termsError"
        @accept="acceptTerms()"
      />

      <template v-else>
        <SettingsPanel v-if="state.view === 'settings'" />
        <SkillsPanel v-else-if="state.view === 'skills'" />

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
            <Composer
              v-else
              ref="composer"
              v-model="draft"
              :busy="state.busy"
              :skills="state.skills"
              :active-skills="state.activeSkills"
              :models="state.models"
              :model="state.model"
              @submit="submit"
              @choose-model="chooseModel"
              @toggle-skill="toggleSkill"
              @clear-skill="clearSkill"
            />
          </div>
        </template>
      </template>
    </section>
  </div>
</template>
