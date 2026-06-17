/// <reference types="vite/client" />

declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

interface Window {
  asdevsAiAssistant: {
    apiUrl: string;
    nonce: string;
    adminUrl: string;
    siteName: string;
    siteUrl: string;
    currentPage: string;
    isAdmin: boolean;
    userId: number;
    aiProvider: string;
    aiModel: string;
    isConfigured: boolean;
  };
}
