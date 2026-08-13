<script setup lang="ts">
import { __ } from '../api';
import DotsLoader from './DotsLoader.vue';

/**
 * Panel placeholder while settings or skills are still arriving.
 *
 * Shared dots loader above content-shaped skeleton bars.
 */
withDefaults(
  defineProps<{
    variant?: 'settings' | 'skills';
    label?: string;
  }>(),
  {
    variant: 'settings',
    label: '',
  }
);
</script>

<template>
  <div
    class="asdevs-ai-panel-skel"
    :class="`asdevs-ai-panel-skel--${variant}`"
    role="status"
    :aria-label="label || __('Loading…')"
    aria-busy="true"
  >
    <DotsLoader class="asdevs-ai-panel-skel__dots" :size="36" />

    <div v-if="variant === 'settings'" class="asdevs-ai-panel-skel__settings" aria-hidden="true">
      <span class="asdevs-ai-panel-skel__note"></span>
      <span class="asdevs-ai-panel-skel__note asdevs-ai-panel-skel__note--short"></span>
      <span class="asdevs-ai-panel-skel__field-label"></span>
      <span class="asdevs-ai-panel-skel__select"></span>
      <div class="asdevs-ai-panel-skel__actions">
        <span class="asdevs-ai-panel-skel__btn"></span>
        <span class="asdevs-ai-panel-skel__btn asdevs-ai-panel-skel__btn--ghost"></span>
        <span class="asdevs-ai-panel-skel__link"></span>
      </div>
    </div>

    <ul v-else class="asdevs-ai-panel-skel__skills" aria-hidden="true">
      <li class="asdevs-ai-panel-skel__skill">
        <span class="asdevs-ai-panel-skel__skill-title"></span>
        <span class="asdevs-ai-panel-skel__skill-slug"></span>
        <span class="asdevs-ai-panel-skel__skill-desc"></span>
      </li>
      <li class="asdevs-ai-panel-skel__skill">
        <span class="asdevs-ai-panel-skel__skill-title"></span>
        <span class="asdevs-ai-panel-skel__skill-slug"></span>
        <span class="asdevs-ai-panel-skel__skill-desc"></span>
      </li>
      <li class="asdevs-ai-panel-skel__skill">
        <span class="asdevs-ai-panel-skel__skill-title"></span>
        <span class="asdevs-ai-panel-skel__skill-slug"></span>
      </li>
    </ul>
  </div>
</template>
