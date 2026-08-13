<script setup lang="ts">
import { computed, ref } from 'vue';
import { __ } from '../api';
import { chooseProvider, state, testConnector } from '../assistant';
import { usePanelHold } from '../usePanelHold';
import DotsLoader from './DotsLoader.vue';
import PanelSkeleton from './PanelSkeleton.vue';

type Toast = {
  ok: boolean;
  title: string;
};

const settings = computed(() => state.settings);
const testingId = ref('');
const toast = ref<Toast | null>(null);
const holdDone = usePanelHold();

/** Skeleton until the min hold ends, and while the first fetch has no payload yet. */
const showSkeleton = computed(() => !holdDone.value || (state.settingsBusy && !settings.value));

/** Dim the list while saving or testing — settings payload is already on screen. */
const listBusy = computed(
  () => Boolean(settings.value) && (state.settingsBusy || testingId.value !== '')
);

async function selectProvider(id: string): Promise<void> {
  if (!settings.value || listBusy.value || id === settings.value.provider) {
    return;
  }

  toast.value = null;
  await chooseProvider(id);

  if (!settings.value) {
    return;
  }

  if (state.settingsError) {
    toast.value = {
      ok: false,
      title: state.settingsError,
    };
    return;
  }

  if (state.settingsSaved) {
    toast.value = {
      ok: settings.value.ready,
      title: settings.value.ready ? __('Settings saved') : __('Saved — one step left'),
    };
  }
}

async function runTest(providerId: string, event: Event): Promise<void> {
  event.preventDefault();
  event.stopPropagation();

  if (!settings.value || listBusy.value || testingId.value) {
    return;
  }

  testingId.value = providerId;
  toast.value = null;

  try {
    const result = await testConnector(providerId);
    toast.value = {
      ok: result.ok,
      title: result.ok ? __('Connection works') : result.message,
    };
  } catch (error) {
    toast.value = {
      ok: false,
      title: (error as Error).message,
    };
  } finally {
    testingId.value = '';
  }
}
</script>

<template>
  <!--
    Credentials live in Settings → Connectors. Here the site only picks which
    WordPress AI connector the assistant should prefer, and can probe it.
  -->
  <div class="asdevs-ai-settings">
    <PanelSkeleton
      v-if="showSkeleton"
      variant="settings"
      :label="__('Loading settings')"
    />

    <div v-else-if="settings" class="asdevs-ai-settings__body">
      <header class="asdevs-ai-settings__head">
        <h3 class="asdevs-ai-settings__title">{{ __('AI connector') }}</h3>
      </header>

      <div
        v-if="toast"
        class="asdevs-ai-settings__toast"
        :class="toast.ok ? 'asdevs-ai-settings__toast--success' : 'asdevs-ai-settings__toast--warning'"
        role="status"
        aria-live="polite"
      >
        <span class="asdevs-ai-settings__toast-icon" aria-hidden="true">
          <svg v-if="toast.ok" viewBox="0 0 24 24" fill="none">
            <path
              d="M20 7 10.5 16.5 5 11"
              stroke="currentColor"
              stroke-width="2.2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none">
            <path d="M12 8v5.25M12 16.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            <path
              d="M10.29 4.86 2.82 17.5A1.8 1.8 0 0 0 4.36 20.2h15.28a1.8 1.8 0 0 0 1.54-2.7L13.71 4.86a1.8 1.8 0 0 0-3.42 0Z"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linejoin="round"
            />
          </svg>
        </span>
        <p class="asdevs-ai-settings__toast-title">{{ toast.title }}</p>
      </div>

      <div v-if="settings.providers.length === 0" class="asdevs-ai-settings__empty">
        <p>{{ __('No AI connectors are active. Add one under Settings → Connectors, then come back.') }}</p>
        <a class="asdevs-ai-btn asdevs-ai-btn--primary" :href="settings.connectors_url">
          {{ __('Open Connectors') }}
        </a>
      </div>

      <template v-else>
        <div
          class="asdevs-ai-settings__providers"
          role="radiogroup"
          :aria-label="__('AI connector')"
          :aria-busy="listBusy"
          :class="{ 'is-busy': listBusy }"
        >
          <div v-if="listBusy" class="asdevs-ai-settings__providers-overlay">
            <DotsLoader :size="36" />
            <span class="asdevs-ai-sr">{{ __('Working…') }}</span>
          </div>

          <label
            v-for="item in settings.providers"
            :key="item.id"
            class="asdevs-ai-settings__provider"
            :for="`asdevs-ai-panel-provider-${item.id}`"
          >
            <input
              :id="`asdevs-ai-panel-provider-${item.id}`"
              type="radio"
              name="asdevs-ai-panel-provider"
              :value="item.id"
              :checked="settings.provider === item.id"
              :disabled="listBusy"
              @change="selectProvider(item.id)"
            />
            <span class="asdevs-ai-settings__provider-face">
              <span class="asdevs-ai-settings__provider-radio" aria-hidden="true"></span>
              <span class="asdevs-ai-settings__provider-name">{{ item.label }}</span>
              <button
                type="button"
                class="asdevs-ai-settings__test"
                :disabled="listBusy || !item.configured"
                @click="runTest(item.id, $event)"
              >
                {{ testingId === item.id ? __('Testing…') : __('Test') }}
              </button>
              <span
                class="asdevs-ai-settings__provider-status"
                :class="item.configured ? 'is-ready' : 'is-pending'"
              >
                <span class="asdevs-ai-settings__provider-status-dot" aria-hidden="true"></span>
                {{ item.configured ? __('Ready') : __('Needs key') }}
              </span>
            </span>
          </label>
        </div>

        <div class="asdevs-ai-settings__actions">
          <a class="asdevs-ai-link" :href="settings.connectors_url">
            {{ __('Manage keys in Connectors') }}
          </a>
        </div>
      </template>
    </div>

    <p v-else class="asdevs-ai-note asdevs-ai-note--bad">
      {{ state.settingsError || __('Settings are unavailable.') }}
    </p>
  </div>
</template>
