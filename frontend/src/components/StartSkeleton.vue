<script setup lang="ts">
import { __ } from '../api';
import DotsLoader from './DotsLoader.vue';

/**
 * Placeholder for the start chips while bootstrap is still arriving.
 *
 * Shared dots loader above chip-shaped bars so the panel does not jump
 * when suggestions land.
 */
</script>

<template>
  <div
    class="asdevs-ai-start-skel"
    role="status"
    :aria-label="__('Looking around your site')"
    aria-busy="true"
  >
    <DotsLoader class="asdevs-ai-start-skel__dots" :size="36" />

    <ul class="asdevs-ai-start-skel__list" aria-hidden="true">
      <li class="asdevs-ai-start-skel__chip">
        <span class="asdevs-ai-start-skel__bar"></span>
        <span class="asdevs-ai-start-skel__arrow"></span>
      </li>
      <li class="asdevs-ai-start-skel__chip">
        <span class="asdevs-ai-start-skel__bar"></span>
        <span class="asdevs-ai-start-skel__arrow"></span>
      </li>
      <li class="asdevs-ai-start-skel__chip">
        <span class="asdevs-ai-start-skel__bar"></span>
        <span class="asdevs-ai-start-skel__arrow"></span>
      </li>
    </ul>
  </div>
</template>
