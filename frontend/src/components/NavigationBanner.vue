<template>
  <Transition name="banner">
    <div v-if="visible" class="asdevs-nav-banner">
      <div class="asdevs-nav-banner-content">
        <svg class="asdevs-nav-banner-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M5 12h14M12 5l7 7-7 7" />
        </svg>
        <div>
          <div class="asdevs-nav-banner-label">Navigating to</div>
          <div class="asdevs-nav-banner-dest">{{ destination }}</div>
        </div>
      </div>
      <div class="asdevs-nav-banner-loading">
        <span class="asdevs-nav-dot"></span>
        <span class="asdevs-nav-dot"></span>
        <span class="asdevs-nav-dot"></span>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useNavigationStore } from '../stores/navigationStore';

const navStore = useNavigationStore();

const visible = computed(() => navStore.redirectPending);
const destination = computed(() => {
  const url = navStore.targetUrl || '';
  // Extract a readable path from the URL
  try {
    const u = new URL(url);
    const params = new URLSearchParams(u.search);
    const page = params.get('page') || '';
    if (page) {
      return page.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }
    return u.pathname.split('/').pop()?.replace('.php', '') || url;
  } catch {
    return url;
  }
});
</script>

<style scoped>
.asdevs-nav-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: var(--asdevs-primary-light, rgba(0, 122, 255, 0.1));
  border-bottom: 1px solid rgba(0, 122, 255, 0.15);
  flex-shrink: 0;
}

.asdevs-nav-banner-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.asdevs-nav-banner-icon {
  color: var(--asdevs-primary, #007AFF);
  flex-shrink: 0;
}

.asdevs-nav-banner-label {
  font-size: 11px;
  color: var(--asdevs-text-secondary, #6B7280);
  font-weight: 500;
}

.asdevs-nav-banner-dest {
  font-size: 13px;
  font-weight: 600;
  color: var(--asdevs-primary, #007AFF);
}

.asdevs-nav-banner-loading {
  display: flex;
  gap: 3px;
}

.asdevs-nav-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--asdevs-primary, #007AFF);
  animation: asdevs-dot-pulse 1.2s ease-in-out infinite;
}

.asdevs-nav-dot:nth-child(2) { animation-delay: 0.2s; }
.asdevs-nav-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes asdevs-dot-pulse {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 1; }
}

.banner-enter-active,
.banner-leave-active {
  transition: all 200ms ease;
}
.banner-enter-from,
.banner-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
