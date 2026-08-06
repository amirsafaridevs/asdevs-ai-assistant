<script setup lang="ts">
import { computed } from 'vue';
import { __ } from '../api';
import { chooseProvider, saveSettings, state } from '../assistant';

const settings = computed(() => state.settings);

const provider = computed(() =>
  settings.value?.providers.find((item) => item.id === settings.value?.provider) ?? null
);

/** The model actually in use, which may be the provider's own default. */
const effectiveModel = computed(() => settings.value?.model || provider.value?.default_model || '');

const canReason = computed(() => (provider.value?.reasoning_models ?? []).includes(effectiveModel.value));
</script>

<template>
  <!--
    Setup belongs where the assistant is, not on a page somewhere else. The
    stored key never comes back down the wire — only whether there is one.
  -->
  <div class="asdevs-ai-settings">
    <p v-if="state.settingsBusy && !settings" class="asdevs-ai-note">{{ __('Loading…') }}</p>

    <form v-else-if="settings" @submit.prevent="saveSettings()">
      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-provider">{{ __('AI service') }}</label>
        <select
          id="asdevs-ai-provider"
          class="asdevs-ai-input"
          :value="settings.provider"
          @change="chooseProvider(($event.target as HTMLSelectElement).value)"
        >
          <option v-for="item in settings.providers" :key="item.id" :value="item.id">{{ item.label }}</option>
        </select>
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-model">{{ __('Model') }}</label>
        <select id="asdevs-ai-model" v-model="settings.model" class="asdevs-ai-input">
          <option value="">{{ __('Recommended default') }}</option>
          <option v-for="(label, id) in provider?.models ?? {}" :key="id" :value="id">{{ label }}</option>
        </select>
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-key">{{ __('API key') }}</label>
        <input
          id="asdevs-ai-key"
          v-model="state.settingsKey"
          class="asdevs-ai-input"
          type="password"
          autocomplete="off"
          spellcheck="false"
          :placeholder="settings.has_key ? __('A key is saved. Leave blank to keep it.') : __('Paste the key here')"
        />
        <p class="asdevs-ai-note">{{ __('The key stays on this site. It is never sent back to the browser.') }}</p>
      </div>

      <label v-if="canReason" class="asdevs-ai-toggle">
        <input v-model="settings.thinking" type="checkbox" />
        <span>
          <span class="asdevs-ai-toggle__label">{{ __('Show its reasoning') }}</span>
          <span class="asdevs-ai-note">{{ __('Slower and costs a little more, but you see how it got there.') }}</span>
        </span>
      </label>

      <p v-if="state.settingsError" class="asdevs-ai-note asdevs-ai-note--bad">{{ state.settingsError }}</p>
      <p v-else-if="state.settingsSaved" class="asdevs-ai-note asdevs-ai-note--good">
        {{ settings.ready ? __('Saved. The assistant is ready.') : __('Saved, but a key is still needed.') }}
      </p>

      <div class="asdevs-ai-settings__actions">
        <button type="submit" class="asdevs-ai-btn asdevs-ai-btn--primary" :disabled="state.settingsBusy">
          {{ state.settingsBusy ? __('Saving…') : __('Save') }}
        </button>
      </div>
    </form>

    <p v-else class="asdevs-ai-note asdevs-ai-note--bad">{{ state.settingsError || __('Settings are unavailable.') }}</p>
  </div>
</template>
