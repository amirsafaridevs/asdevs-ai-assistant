<script setup lang="ts">
import { computed, ref } from 'vue';
import { __ } from '../api';
import { chooseProvider, saveSettings, state, testConnector } from '../assistant';

const settings = computed(() => state.settings);
const testing = ref(false);
const testMessage = ref('');
const testOk = ref<boolean | null>(null);

const selected = computed(() =>
  settings.value?.providers.find((item) => item.id === settings.value?.provider) ?? null
);

async function runTest(): Promise<void> {
  if (!settings.value || testing.value) {
    return;
  }

  testing.value = true;
  testMessage.value = '';
  testOk.value = null;

  try {
    const result = await testConnector(settings.value.provider);
    testOk.value = result.ok;
    testMessage.value = result.message;
  } catch (error) {
    testOk.value = false;
    testMessage.value = (error as Error).message;
  } finally {
    testing.value = false;
  }
}
</script>

<template>
  <!--
    Credentials live in Settings → Connectors. Here the site only picks which
    WordPress AI connector the assistant should prefer, and can probe it.
  -->
  <div class="asdevs-ai-settings">
    <p v-if="state.settingsBusy && !settings" class="asdevs-ai-note">{{ __('Loading…') }}</p>

    <form v-else-if="settings" @submit.prevent="saveSettings()">
      <p class="asdevs-ai-note">
        {{ __('API keys are managed in WordPress under Settings → Connectors. Choose which connector this assistant should use.') }}
      </p>

      <p v-if="settings.providers.length === 0" class="asdevs-ai-note asdevs-ai-note--bad">
        {{ __('No AI provider plugins are active yet. Install one from Connectors first.') }}
      </p>

      <div v-else class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-provider">{{ __('WordPress connector') }}</label>
        <select
          id="asdevs-ai-provider"
          class="asdevs-ai-input"
          :value="settings.provider"
          @change="chooseProvider(($event.target as HTMLSelectElement).value); testMessage = ''; testOk = null"
        >
          <option v-for="item in settings.providers" :key="item.id" :value="item.id">
            {{ item.configured ? item.label : `${item.label} (${__('not configured')})` }}
          </option>
        </select>
      </div>

      <p v-if="selected && !selected.configured" class="asdevs-ai-note asdevs-ai-note--bad">
        {{ __('This connector still needs an API key.') }}
        <a class="asdevs-ai-link" :href="settings.connectors_url">{{ __('Open Connectors') }}</a>
      </p>

      <p v-if="state.settingsError" class="asdevs-ai-note asdevs-ai-note--bad">{{ state.settingsError }}</p>
      <p v-else-if="state.settingsSaved" class="asdevs-ai-note asdevs-ai-note--good">
        {{ settings.ready ? __('Saved. The assistant is ready.') : __('Saved, but the connector still needs a key.') }}
      </p>

      <p
        v-if="testMessage"
        class="asdevs-ai-note"
        :class="testOk ? 'asdevs-ai-note--good' : 'asdevs-ai-note--bad'"
      >
        {{ testMessage }}
      </p>

      <div class="asdevs-ai-settings__actions">
        <button
          type="submit"
          class="asdevs-ai-btn asdevs-ai-btn--primary"
          :disabled="state.settingsBusy || settings.providers.length === 0"
        >
          {{ state.settingsBusy ? __('Saving…') : __('Save') }}
        </button>
        <button
          type="button"
          class="asdevs-ai-btn"
          :disabled="testing || !settings.provider || !selected?.configured"
          @click="runTest()"
        >
          {{ testing ? __('Testing…') : __('Test connection') }}
        </button>
        <a class="asdevs-ai-link" :href="settings.connectors_url">{{ __('Manage connectors') }}</a>
      </div>
    </form>

    <p v-else class="asdevs-ai-note asdevs-ai-note--bad">{{ state.settingsError || __('Settings are unavailable.') }}</p>
  </div>
</template>
