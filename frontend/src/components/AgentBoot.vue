<script setup lang="ts">
import { __ } from '../api';
import DotsLoader from './DotsLoader.vue';

/**
 * What stands in for the answer while the agent engine is still arriving.
 */
</script>

<template>
  <div class="asdevs-ai-boot" role="status" :aria-label="__('Preparing the assistant')" aria-busy="true">
    <DotsLoader class="asdevs-ai-boot__dots" :size="36" />
  </div>
</template>
