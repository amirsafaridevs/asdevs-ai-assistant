import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';

// The widget ships as one script and one stylesheet, both enqueued the
// WordPress way. Names are stable so the PHP side can reference them.
export default defineConfig({
  plugins: [vue()],
  // A library build gets no environment injected, but Vue's runtime still
  // reads these flags. Without them the bundle throws on `process` before it
  // can mount anything.
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    __VUE_OPTIONS_API__: 'false',
    __VUE_PROD_DEVTOOLS__: 'false',
    __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false',
  },
  build: {
    outDir: resolve(__dirname, '../assets/dist'),
    emptyOutDir: true,
    cssCodeSplit: false,
    sourcemap: false,
    lib: {
      entry: resolve(__dirname, 'src/main.ts'),
      formats: ['iife'],
      name: 'AsdevsAiAssistant',
      fileName: () => 'main.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'main.css',
        entryFileNames: 'main.js',
      },
    },
  },
});
