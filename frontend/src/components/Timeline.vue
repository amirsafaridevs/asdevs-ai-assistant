<script setup lang="ts">
import { computed, ref } from 'vue';
import { __, sprintf } from '../api';
import type { Step } from '../types';

const props = defineProps<{ steps: Step[] }>();

/** How much live reasoning to show before it is asked for in full. */
const PREVIEW_CHARS = 220;

/** Which steps the person has asked to see the inside of. */
const opened = ref<Record<string, boolean>>({});

const running = computed(() => props.steps.some((step) => step.status === 'running'));

function toggle(step: Step): void {
  opened.value = { ...opened.value, [step.id]: !opened.value[step.id] };
}

function hasDetail(step: Step): boolean {
  return step.detail.trim() !== '';
}

/** Reasoning shows itself as it arrives; anything else waits to be asked. */
function isOpen(step: Step): boolean {
  return opened.value[step.id] === true || (step.kind === 'thinking' && step.status === 'running');
}

function detail(step: Step): string {
  if (opened.value[step.id] || step.detail.length <= PREVIEW_CHARS) {
    return step.detail;
  }

  return `…${step.detail.slice(-PREVIEW_CHARS)}`;
}

function elapsed(step: Step): string {
  if (step.endedAt === null) {
    return '';
  }

  const seconds = (step.endedAt - step.startedAt) / 1000;

  // translators: %1$s is a number of seconds, e.g. 1.4.
  return seconds < 1 ? '' : sprintf(__('%1$ss'), seconds.toFixed(1));
}
</script>

<template>
  <!--
    What is happening, in the order it happened. It stays after the answer
    arrives, so the person can go back and see how it was reached.
  -->
  <ol class="asdevs-ai-steps" :class="{ 'is-live': running }" :aria-label="__('What the assistant did')">
    <li
      v-for="step in steps"
      :key="step.id"
      class="asdevs-ai-step"
      :class="[`is-${step.status}`, `is-${step.kind}`]"
    >
      <span class="asdevs-ai-step__mark" aria-hidden="true"></span>

      <div class="asdevs-ai-step__body">
        <div class="asdevs-ai-step__head">
          <button
            v-if="hasDetail(step)"
            type="button"
            class="asdevs-ai-step__label asdevs-ai-step__label--button"
            :aria-expanded="isOpen(step)"
            @click="toggle(step)"
          >
            {{ step.label }}
          </button>
          <span v-else class="asdevs-ai-step__label">{{ step.label }}</span>

          <span v-if="elapsed(step)" class="asdevs-ai-step__time">{{ elapsed(step) }}</span>
        </div>

        <p v-if="hasDetail(step) && isOpen(step)" class="asdevs-ai-step__detail">{{ detail(step) }}</p>
      </div>
    </li>
  </ol>
</template>
