<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { __ } from '../api';
import type { ChoiceQuestion } from '../markdown';

const props = defineProps<{
  questions: ChoiceQuestion[];
}>();

const emit = defineEmits<{
  (event: 'submit', answers: Array<{ id: string; prompt: string; answer: string }>): void;
  (event: 'cancel'): void;
}>();

const index = ref(0);
const selected = ref('');
const answers = ref<Record<string, string>>({});

watch(
  () => props.questions,
  () => {
    index.value = 0;
    selected.value = '';
    answers.value = {};
  },
  { deep: true }
);

const current = computed(() => props.questions[index.value] ?? null);
const total = computed(() => props.questions.length);
const isLast = computed(() => index.value >= total.value - 1);
const progress = computed(() =>
  total.value > 0 ? `${Math.min(index.value + 1, total.value)} / ${total.value}` : ''
);

function choose(option: string): void {
  selected.value = option;
}

function goNext(): void {
  if (!current.value || selected.value === '') {
    return;
  }

  answers.value = {
    ...answers.value,
    [current.value.id]: selected.value,
  };

  if (!isLast.value) {
    index.value += 1;
    selected.value = answers.value[props.questions[index.value]?.id] ?? '';
    return;
  }

  emit(
    'submit',
    props.questions.map((question) => ({
      id: question.id,
      prompt: question.prompt,
      answer: answers.value[question.id] ?? selected.value,
    }))
  );
}
</script>

<template>
  <div v-if="current" class="asdevs-ai-choice" role="group" :aria-label="__('Answer the question')">
    <div class="asdevs-ai-choice__head">
      <p class="asdevs-ai-choice__progress">{{ progress }}</p>
      <button type="button" class="asdevs-ai-link" @click="$emit('cancel')">{{ __('Skip') }}</button>
    </div>

    <p class="asdevs-ai-choice__prompt">{{ current.prompt }}</p>

    <ul class="asdevs-ai-choice__options">
      <li v-for="option in current.options" :key="option">
        <button
          type="button"
          class="asdevs-ai-choice__option"
          :class="{ 'is-selected': selected === option }"
          @click="choose(option)"
        >
          {{ option }}
        </button>
      </li>
    </ul>

    <div class="asdevs-ai-choice__actions">
      <button
        type="button"
        class="asdevs-ai-btn asdevs-ai-btn--primary"
        :disabled="selected === ''"
        @click="goNext()"
      >
        {{ isLast ? __('Submit answers') : __('Next') }}
      </button>
    </div>
  </div>
</template>
