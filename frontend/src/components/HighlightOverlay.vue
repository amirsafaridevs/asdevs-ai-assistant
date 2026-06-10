<template>
  <!-- This component is a bridge between the highlight service and the Vue app.
       The actual DOM manipulation is done by the highlight service for performance,
       but this component listens for events and can trigger highlights. -->
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
