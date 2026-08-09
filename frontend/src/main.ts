import { createApp } from 'vue';
import App from './App.vue';
import './styles.css';

const mount = (): void => {
  const root = document.getElementById('asdevs-ai-assistant-root');

  if (!root) {
    return;
  }

  createApp(App).mount(root);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount, { once: true });
} else {
  mount();
}
