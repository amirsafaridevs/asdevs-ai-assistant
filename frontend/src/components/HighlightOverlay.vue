<template>
  <!-- This component bridges the highlight service with the Vue app.
       Highlights are managed by adding/removing the .asdevs-highlight CSS class
       via highlightService.ts. This component listens for clear events. -->
  <div v-if="false">
    <!-- Placeholder - actual highlights are managed by highlightService.ts -->
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { clearHighlight } from '../services/highlightService';

function handleClearHighlight(): void {
  clearHighlight();
}

onMounted(() => {
  document.addEventListener('asdevs:clearHighlight', handleClearHighlight);
});

onUnmounted(() => {
  document.removeEventListener('asdevs:clearHighlight', handleClearHighlight);
  clearHighlight();
});
</script>
