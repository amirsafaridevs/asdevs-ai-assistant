<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { __ } from '../api';
import type { TermsSection } from '../types';

defineProps<{
  version: string;
  sections: TermsSection[];
  busy: boolean;
  error: string;
}>();

const emit = defineEmits<{ (event: 'accept'): void }>();

const scroller = ref<HTMLElement | null>(null);
const reachedEnd = ref(false);

function checkScroll(): void {
  const el = scroller.value;

  if (!el) {
    return;
  }

  // Short copy (or tall viewport) never needs a scroll — unlock immediately.
  if (el.scrollHeight <= el.clientHeight + 4) {
    reachedEnd.value = true;

    return;
  }

  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 12) {
    reachedEnd.value = true;
  }
}

onMounted(async () => {
  await nextTick();
  checkScroll();
});
</script>

<template>
  <div class="asdevs-ai-terms">
    <div class="asdevs-ai-terms__intro">
      <p class="asdevs-ai-terms__eyebrow">{{ __('Before you begin') }}</p>
      <h3 class="asdevs-ai-terms__title">{{ __('Terms of use') }}</h3>
      <p class="asdevs-ai-terms__lead">
        {{ __('Please read these terms. Scroll to the end to continue.') }}
      </p>
      <p v-if="version" class="asdevs-ai-terms__version">{{ __('Version') }} {{ version }}</p>
    </div>

    <div
      ref="scroller"
      class="asdevs-ai-terms__scroll"
      tabindex="0"
      role="region"
      :aria-label="__('Terms of use')"
      @scroll="checkScroll"
    >
      <section v-for="(section, index) in sections" :key="index" class="asdevs-ai-terms__section">
        <h4 class="asdevs-ai-terms__heading">{{ section.heading }}</h4>
        <p class="asdevs-ai-terms__body">{{ section.body }}</p>
      </section>
    </div>

    <div class="asdevs-ai-terms__footer">
      <p v-if="error" class="asdevs-ai-terms__error" role="alert">{{ error }}</p>
      <p v-if="!reachedEnd" class="asdevs-ai-terms__hint">
        {{ __('Scroll to the bottom to enable acceptance.') }}
      </p>
      <button
        v-if="reachedEnd"
        type="button"
        class="asdevs-ai-btn asdevs-ai-btn--primary asdevs-ai-terms__accept"
        :disabled="busy"
        @click="emit('accept')"
      >
        {{ busy ? __('Saving…') : __('I agree') }}
      </button>
    </div>
  </div>
</template>
