<script setup lang="ts">
import { __ } from '../api';

/**
 * What stands in for the answer while the agent engine is still arriving.
 *
 * It is shaped like the reply it is about to be replaced by — a short line, a
 * long one, a short one — so the panel does not jump when the real text lands.
 * A single light sweep passes across it; nothing spins, nothing counts up, and
 * nothing claims progress it cannot measure.
 */
</script>

<template>
  <div class="asdevs-ai-boot" role="status" :aria-label="__('Preparing the assistant')">
    <div class="asdevs-ai-boot__lines" aria-hidden="true">
      <span class="asdevs-ai-boot__line"></span>
      <span class="asdevs-ai-boot__line"></span>
      <span class="asdevs-ai-boot__line"></span>
    </div>

    <p class="asdevs-ai-boot__label">{{ __('Preparing the assistant') }}</p>
  </div>
</template>
