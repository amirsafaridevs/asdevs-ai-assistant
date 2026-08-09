<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { __, sprintf } from '../api';
import { processAssistantMarkdown } from '../markdown';
import type { Bubble, MessageAttachment } from '../types';
import Timeline from './Timeline.vue';

const props = defineProps<{
  bubble: Bubble;
  /** True while this turn is still running and this bubble is the live one. */
  live?: boolean;
}>();

defineEmits<{ (event: 'retry'): void }>();

const showDetail = ref(false);
const track = ref<HTMLElement | null>(null);
const canPrev = ref(false);
const canNext = ref(false);
const needsCarousel = ref(false);

const processed = computed(() => {
  if (props.bubble.role !== 'assistant' || !props.bubble.text) {
    return { html: '', choices: [], choiceOnly: false };
  }

  return processAssistantMarkdown(props.bubble.text);
});

const html = computed(() => processed.value.html);

const choiceChip = computed(() => {
  const { choices, choiceOnly, html: rendered } = processed.value;

  if (choices.length === 0) {
    return '';
  }

  // Compact summary only when choices were stripped and little else remains,
  // or when the sheet will own the interaction (always show a short cue).
  if (!choiceOnly && rendered.trim() !== '') {
    return '';
  }

  if (choices.length === 1) {
    return choices[0].prompt;
  }

  return sprintf(
    /* translators: %s: number of questions waiting for an answer in the choice sheet. */
    __('%s questions for you'),
    String(choices.length)
  );
});

/** Finished work stays on the timeline; the live line owns whatever is still running. */
const timelineSteps = computed(() =>
  (props.bubble.steps ?? []).filter((step) => step.status !== 'running' || step.kind === 'thinking')
);

const status = computed(() => {
  if (!props.live) {
    return '';
  }

  if (props.bubble.statusHint) {
    return props.bubble.statusHint;
  }

  const running = props.bubble.steps?.find((step) => step.status === 'running');

  // Live reasoning already paints its own label on the timeline.
  if (running?.kind === 'thinking') {
    return '';
  }

  if (running) {
    return running.label;
  }

  return props.bubble.text.trim() !== '' ? __('Writing…') : __('Working…');
});

const showTyping = computed(() => props.live === true && !props.bubble.error);

const showAssistantBody = computed(
  () => props.bubble.role === 'assistant' && (html.value !== '' || choiceChip.value !== '')
);

const userAttachments = computed(() => props.bubble.attachments ?? []);

const showUserBody = computed(
  () => props.bubble.role === 'user' && (props.bubble.text.trim() !== '' || userAttachments.value.length > 0)
);

const userText = computed(() => props.bubble.text.trim());

function isImage(file: MessageAttachment): boolean {
  return file.type.startsWith('image/') || /\.(png|jpe?g|gif|webp|avif|svg)$/i.test(file.name);
}

function extension(file: MessageAttachment): string {
  const fromName = file.name.includes('.') ? file.name.split('.').pop() || '' : '';
  if (fromName && fromName.length <= 5) {
    return fromName.toUpperCase();
  }

  if (file.type === 'application/pdf') {
    return 'PDF';
  }

  if (file.type.includes('word') || file.type.includes('msword')) {
    return 'DOC';
  }

  if (file.type.includes('sheet') || file.type.includes('excel')) {
    return 'XLS';
  }

  if (file.type.startsWith('audio/')) {
    return 'AUD';
  }

  if (file.type.startsWith('video/')) {
    return 'VID';
  }

  if (file.type.startsWith('text/')) {
    return 'TXT';
  }

  return 'FILE';
}

function tileKind(file: MessageAttachment): string {
  if (isImage(file)) {
    return 'image';
  }

  const ext = extension(file).toLowerCase();

  if (ext === 'pdf' || file.type === 'application/pdf') {
    return 'pdf';
  }

  if (/^docx?$/.test(ext) || file.type.includes('word') || file.type.includes('msword')) {
    return 'doc';
  }

  if (/^xlsx?$|^csv$/.test(ext) || file.type.includes('sheet') || file.type.includes('excel')) {
    return 'sheet';
  }

  if (file.type.startsWith('audio/') || /^(mp3|wav|ogg|m4a)$/.test(ext)) {
    return 'audio';
  }

  if (file.type.startsWith('video/') || /^(mp4|webm|mov)$/.test(ext)) {
    return 'video';
  }

  if (/^(zip|rar|7z|gz|tar)$/.test(ext)) {
    return 'archive';
  }

  return 'file';
}

function shortName(file: MessageAttachment): string {
  const name = file.name.trim() || __('File');

  if (name.length <= 18) {
    return name;
  }

  const ext = name.includes('.') ? `.${name.split('.').pop()}` : '';
  const base = name.slice(0, Math.max(8, 16 - ext.length));

  return `${base}…${ext}`;
}

function updateCarousel(): void {
  const el = track.value;

  if (!el) {
    needsCarousel.value = false;
    canPrev.value = false;
    canNext.value = false;
    return;
  }

  const max = Math.max(0, el.scrollWidth - el.clientWidth);
  needsCarousel.value = max > 4;

  if (!needsCarousel.value) {
    canPrev.value = false;
    canNext.value = false;
    return;
  }

  // Works for LTR (scrollLeft ≥ 0) and Chromium RTL (scrollLeft ≤ 0).
  const abs = Math.abs(el.scrollLeft);
  canPrev.value = abs > 4;
  canNext.value = abs < max - 4;
}

function scrollByTile(dir: -1 | 1): void {
  const el = track.value;

  if (!el) {
    return;
  }

  const amount = Math.max(96, Math.floor(el.clientWidth * 0.7));
  const rtl = getComputedStyle(el).direction === 'rtl';
  el.scrollBy({ left: (rtl ? -dir : dir) * amount, behavior: 'smooth' });
}

let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
  void nextTick(updateCarousel);

  if (typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => updateCarousel());

    if (track.value) {
      resizeObserver.observe(track.value);
    }
  }
});

onBeforeUnmount(() => {
  resizeObserver?.disconnect();
  resizeObserver = null;
});

watch(
  userAttachments,
  async () => {
    await nextTick();

    if (track.value && resizeObserver) {
      resizeObserver.observe(track.value);
    }

    updateCarousel();
  },
  { deep: true }
);
</script>

<template>
  <div
    class="asdevs-ai-msg"
    :class="[
      `asdevs-ai-msg--${bubble.role}`,
      { 'asdevs-ai-msg--user-stack': bubble.role === 'user' && showUserBody },
    ]"
  >
    <Timeline v-if="timelineSteps.length" :steps="timelineSteps" />

    <div v-if="showAssistantBody" class="asdevs-ai-msg__text" :class="{ 'asdevs-ai-md': !!html }">
      <div v-if="html" class="asdevs-ai-md__body" v-html="html"></div>
      <p v-if="choiceChip" class="asdevs-ai-choice-chip">{{ choiceChip }}</p>
    </div>

    <template v-else-if="showUserBody">
      <div v-if="userAttachments.length" class="asdevs-ai-attach-rail" :class="{ 'has-nav': needsCarousel }">
        <button
          v-if="needsCarousel"
          type="button"
          class="asdevs-ai-attach-rail__nav is-prev"
          :disabled="!canPrev"
          :aria-label="__('Previous attachments')"
          @click="scrollByTile(-1)"
        >
          <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false">
            <path d="m14 6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>

        <div ref="track" class="asdevs-ai-attach-rail__track" @scroll.passive="updateCarousel">
          <a
            v-for="file in userAttachments"
            :key="file.url"
            class="asdevs-ai-attach-tile"
            :class="`is-${tileKind(file)}`"
            :href="file.url"
            :title="file.name"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="asdevs-ai-attach-tile__preview">
              <img v-if="isImage(file)" :src="file.url" alt="" loading="lazy" />
              <span v-else class="asdevs-ai-attach-tile__badge">{{ extension(file) }}</span>
            </span>
            <span class="asdevs-ai-attach-tile__name">{{ shortName(file) }}</span>
          </a>
        </div>

        <button
          v-if="needsCarousel"
          type="button"
          class="asdevs-ai-attach-rail__nav is-next"
          :disabled="!canNext"
          :aria-label="__('Next attachments')"
          @click="scrollByTile(1)"
        >
          <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false">
            <path d="m10 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>

      <div v-if="userText" class="asdevs-ai-msg__bubble">
        <p class="asdevs-ai-msg__text">{{ userText }}</p>
      </div>
    </template>

    <div v-if="bubble.error" class="asdevs-ai-notice" role="status">
      <p class="asdevs-ai-notice__message">{{ bubble.error.message }}</p>
      <div class="asdevs-ai-notice__actions">
        <button
          v-if="bubble.error.retryable"
          type="button"
          class="asdevs-ai-notice__action"
          @click="$emit('retry')"
        >
          {{ __('Try again') }}
        </button>
        <button
          v-if="bubble.error.detail"
          type="button"
          class="asdevs-ai-notice__action asdevs-ai-notice__action--quiet"
          :aria-expanded="showDetail"
          @click="showDetail = !showDetail"
        >
          {{ showDetail ? __('Hide details') : __('Details') }}
        </button>
      </div>
      <pre v-if="showDetail" class="asdevs-ai-notice__detail">{{ bubble.error.detail }}</pre>
    </div>

    <div v-if="showTyping" class="asdevs-ai-typing" role="status" :aria-label="status">
      <span class="asdevs-ai-typing__dots" aria-hidden="true">
        <i></i><i></i><i></i>
      </span>
      <span v-if="status" class="asdevs-ai-typing__label">{{ status }}</span>
    </div>
  </div>
</template>
