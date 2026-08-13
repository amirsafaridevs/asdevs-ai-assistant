import { createApp } from 'vue';
import App from './App.vue';
import './styles.css';

/**
 * Mount once on the WordPress-enqueued copy of this module.
 *
 * Deferred agent chunks import `./main.js` by relative path. WordPress loads the
 * same file as `main.js?ver=…`. Browsers treat those as two module instances, so
 * this file's body runs again when the engine loads. Only the enqueued URL has
 * `?ver=`; skip auto-mount for the bare import so the open panel is not replaced
 * by a fresh app (`open: false`).
 */
const isWordPressEntry = /[?&]ver=/.test(import.meta.url);

const mount = (): void => {
  const root = document.getElementById('asdevs-ai-assistant-root');

  if (!root || root.dataset.asdevsAiMounted === '1') {
    return;
  }

  root.dataset.asdevsAiMounted = '1';
  createApp(App).mount(root);
};

if (isWordPressEntry) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount, { once: true });
  } else {
    mount();
  }
}
