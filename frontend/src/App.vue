<template>
  <div class="asdevs-root">
    <!-- Floating Button -->
    <AssistantButton
      :is-open="isPanelOpen"
      :has-notification="hasNotification"
      @toggle="togglePanel"
    />

    <!-- Assistant Panel -->
    <Transition name="panel">
      <AssistantPanel
        v-if="isPanelOpen"
        @close="closePanel"
      />
    </Transition>

    <!-- Highlight Overlay (teleported to body) -->
    <HighlightOverlay />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import AssistantButton from './components/AssistantButton.vue';
import AssistantPanel from './components/AssistantPanel.vue';
import HighlightOverlay from './components/HighlightOverlay.vue';
import { useChatStore } from './stores/chatStore';
import { useNavigationStore } from './stores/navigationStore';
import { useContextStore } from './stores/contextStore';
import { agent } from './agent/agent';

const chatStore = useChatStore();
const navigationStore = useNavigationStore();
const contextStore = useContextStore();

const isPanelOpen = ref(false);
const hasNotification = ref(false);

onMounted(async () => {
  // Restore state from localStorage
  chatStore.restoreFromStorage();
  navigationStore.restoreFromStorage();

  // Fetch WordPress context
  await contextStore.fetchContext();

  // Check if we arrived after a navigation redirect
  if (navigationStore.postNavigation) {
    // Auto-open the panel
    isPanelOpen.value = true;

    // Handle arrival (clears redirectPending)
    if (navigationStore.redirectPending) {
      navigationStore.handleRedirectArrival();
    }

    // Resume the agent — it will scan the new page and highlight
    await agent.continueAfterNavigation();
  } else if (navigationStore.redirectPending || navigationStore.currentTask) {
    // Legacy: pending redirect but not post-navigation
    isPanelOpen.value = true;
    if (navigationStore.redirectPending) {
      navigationStore.handleRedirectArrival();
    }
  }
});

function togglePanel(): void {
  isPanelOpen.value = !isPanelOpen.value;
  if (isPanelOpen.value) {
    hasNotification.value = false;
  }
}

function closePanel(): void {
  isPanelOpen.value = false;
}
</script>

<style scoped>
.asdevs-root {
  position: relative;
}

.panel-enter-active {
  transition: all 250ms cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.panel-leave-active {
  transition: all 200ms ease-in;
}
.panel-enter-from {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}
.panel-leave-to {
  opacity: 0;
  transform: translateY(10px) scale(0.97);
}
</style>
