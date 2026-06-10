import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useChatStore } from './chatStore';

const STORAGE_KEY = 'asdevs-ai-assistant-session';

export interface NavigationState {
  currentTask: string | null;
  targetPage: string | null;
  targetSelector: string | null;
  step: number;
  redirectPending: boolean;
  targetUrl: string | null;
  postNavigation: boolean;
}

export const useNavigationStore = defineStore('navigation', () => {
  const currentTask = ref<string | null>(null);
  const targetPage = ref<string | null>(null);
  const targetSelector = ref<string | null>(null);
  const step = ref<number>(0);
  const redirectPending = ref(false);
  const targetUrl = ref<string | null>(null);
  const postNavigation = ref(false);

  function setNavigationTask(task: string, url: string, selector?: string): void {
    currentTask.value = task;
    targetPage.value = url;
    targetUrl.value = url;
    targetSelector.value = selector || null;
    step.value = 1;
    redirectPending.value = true;
    persistNavigation();
  }

  function prepareRedirect(url: string, task?: string, selector?: string): void {
    if (task) currentTask.value = task;
    targetUrl.value = url;
    targetPage.value = url;
    targetSelector.value = selector || null;
    redirectPending.value = true;
    postNavigation.value = true; // Signal that agent should continue after reload
    step.value += 1;
    persistNavigation();
  }

  function handleRedirectArrival(): void {
    redirectPending.value = false;
    // Keep postNavigation true — it's cleared after agent resumes
    persistNavigation();
  }

  function clearPostNavigation(): void {
    postNavigation.value = false;
    persistNavigation();
  }

  function clearNavigation(): void {
    currentTask.value = null;
    targetPage.value = null;
    targetSelector.value = null;
    step.value = 0;
    redirectPending.value = false;
    targetUrl.value = null;
    postNavigation.value = false;
    persistNavigation();
  }

  function highlightTarget(): void {
    if (targetSelector.value) {
      // Trigger highlight event – handled by HighlightOverlay component
      window.dispatchEvent(
        new CustomEvent('asdevs:highlight', {
          detail: {
            selector: targetSelector.value,
            message: currentTask.value || 'This is the setting you need.',
          },
        })
      );
    }
  }

  function performRedirect(url: string): void {
    // Save state before navigation
    persistNavigation();

    // Use window.location for the redirect
    window.location.href = url;
  }

  function persistNavigation(): void {
    try {
      const existingRaw = localStorage.getItem(STORAGE_KEY);
      let existing: any = {};
      if (existingRaw) {
        existing = JSON.parse(existingRaw);
      }

      existing.navigationState = {
        currentTask: currentTask.value,
        targetPage: targetPage.value,
        targetSelector: targetSelector.value,
        step: step.value,
        redirectPending: redirectPending.value,
        targetUrl: targetUrl.value,
        postNavigation: postNavigation.value,
      };

      localStorage.setItem(STORAGE_KEY, JSON.stringify(existing));
    } catch {
      // Silent fail
    }
  }

  function restoreFromStorage(): void {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const data = JSON.parse(raw);
      const nav = data.navigationState;
      if (!nav) return;

      currentTask.value = nav.currentTask || null;
      targetPage.value = nav.targetPage || null;
      targetSelector.value = nav.targetSelector || null;
      step.value = nav.step || 0;
      redirectPending.value = nav.redirectPending || false;
      targetUrl.value = nav.targetUrl || null;
      postNavigation.value = nav.postNavigation || false;
    } catch {
      // Corrupted data
    }
  }

  function getState(): NavigationState {
    return {
      currentTask: currentTask.value,
      targetPage: targetPage.value,
      targetSelector: targetSelector.value,
      step: step.value,
      redirectPending: redirectPending.value,
      targetUrl: targetUrl.value,
      postNavigation: postNavigation.value,
    };
  }

  return {
    currentTask,
    targetPage,
    targetSelector,
    step,
    redirectPending,
    targetUrl,
    postNavigation,
    setNavigationTask,
    prepareRedirect,
    handleRedirectArrival,
    clearPostNavigation,
    clearNavigation,
    highlightTarget,
    performRedirect,
    persistNavigation,
    restoreFromStorage,
    getState,
  };
});
