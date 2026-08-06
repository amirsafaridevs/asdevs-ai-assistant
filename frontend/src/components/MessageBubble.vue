<script setup lang="ts">
import { ref } from 'vue';
import { __, sprintf } from '../api';
import type { Bubble } from '../types';
import Timeline from './Timeline.vue';

defineProps<{ bubble: Bubble }>();
defineEmits<{ (event: 'retry'): void }>();

const showDetail = ref(false);
</script>

<template>
  <div class="asdevs-ai-msg" :class="`asdevs-ai-msg--${bubble.role}`">
    <Timeline v-if="bubble.steps && bubble.steps.length" :steps="bubble.steps" />

    <p v-if="bubble.text" class="asdevs-ai-msg__text">{{ bubble.text }}</p>

    <div v-if="bubble.rows && bubble.rows.length" class="asdevs-ai-table-wrap">
      <table class="asdevs-ai-table">
        <thead>
          <tr>
            <th v-for="key in Object.keys(bubble.rows[0])" :key="key" scope="col">{{ key }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in bubble.rows" :key="index">
            <td v-for="key in Object.keys(bubble.rows[0])" :key="key">{{ row[key] }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="bubble.total && bubble.total > bubble.rows.length" class="asdevs-ai-note">
        <!-- translators: 1: number of rows shown, 2: total number of items. -->
        {{ sprintf(__('Showing %1$s of %2$s.'), bubble.rows.length, bubble.total) }}
      </p>
    </div>

    <p v-if="bubble.links && bubble.links.length" class="asdevs-ai-msg__links">
      <a v-for="link in bubble.links" :key="link.href" :href="link.href" class="asdevs-ai-link">{{ link.label }}</a>
    </p>

    <div v-if="bubble.error" class="asdevs-ai-error">
      <p class="asdevs-ai-error__message">{{ bubble.error.message }}</p>
      <p class="asdevs-ai-msg__links">
        <button v-if="bubble.error.retryable" type="button" class="asdevs-ai-link" @click="$emit('retry')">
          {{ __('Try again') }}
        </button>
        <button
          v-if="bubble.error.detail"
          type="button"
          class="asdevs-ai-link"
          :aria-expanded="showDetail"
          @click="showDetail = !showDetail"
        >
          {{ __('Technical details') }}
        </button>
      </p>
      <pre v-if="showDetail" class="asdevs-ai-detail">{{ bubble.error.detail }}</pre>
    </div>
  </div>
</template>
