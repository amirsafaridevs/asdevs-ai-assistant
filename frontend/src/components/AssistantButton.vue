<template>
  <button
    class="asdevs-floating-btn"
    :class="{ idle: !isOpen && !hasNotification }"
    @click="$emit('toggle')"
    :aria-label="isOpen ? 'Close AI Assistant' : 'Open AI Assistant'"
    :title="isOpen ? 'Close Assistant' : 'AI Assistant'"
  >
    <!-- AI Sparkle Icon -->
    <svg v-if="!isOpen" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 2L9.5 9.5L2 12L9.5 14.5L12 22L14.5 14.5L22 12L14.5 9.5L12 2Z" />
      <path d="M12 5.5L10.5 10.5L5.5 12L10.5 13.5L12 18.5L13.5 13.5L18.5 12L13.5 10.5L12 5.5Z" fill="rgba(0,0,0,0.1)" />
    </svg>
    <!-- Close Icon -->
    <svg v-else viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none" />
    </svg>
    <!-- Notification Badge -->
    <span v-if="hasNotification && !isOpen" class="asdevs-badge"></span>
  </button>
</template>

<script setup lang="ts">
defineProps<{
  isOpen: boolean;
  hasNotification: boolean;
}>();

defineEmits<{
  toggle: [];
}>();
</script>
