import { onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

/** How long a panel skeleton stays up even when the request is already done. */
export const PANEL_SKELETON_MIN_MS = 3000;

/**
 * Keep a panel skeleton visible for at least {@link PANEL_SKELETON_MIN_MS}.
 *
 * Panels remount when the view opens, so each visit gets a fresh hold.
 */
export function usePanelHold(): Ref<boolean> {
  const holdDone = ref(false);
  let timer: ReturnType<typeof setTimeout> | null = null;

  onMounted(() => {
    timer = setTimeout(() => {
      holdDone.value = true;
      timer = null;
    }, PANEL_SKELETON_MIN_MS);
  });

  onBeforeUnmount(() => {
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
  });

  return holdDone;
}
