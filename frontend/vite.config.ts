import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';

// The widget ships as an ES module plus one stylesheet, both enqueued the
// WordPress way. Names are stable so the PHP side can reference them.
//
// The output is a module rather than an IIFE for one reason: an IIFE cannot be
// code-split, so the OpenAI Agents SDK would be inlined into the script every
// admin page loads. As a module, the SDK lives in agent-*.js and is fetched
// only when someone opens the assistant.
//
// Shared app modules (api, i18n helpers, …) must NOT stay inside main.js when
// the agent chunk needs them. WordPress loads main.js?ver=…; a chunk that
// `import`s ./main.js would load a second module instance and remount the UI.
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
    target: 'es2020',
    rollupOptions: {
      input: resolve(__dirname, 'src/main.ts'),
      output: {
        format: 'es',
        assetFileNames: 'main.css',
        entryFileNames: 'main.js',
        chunkFileNames: 'agent-[hash].js',
        manualChunks(id) {
          const normalized = id.replace(/\\/g, '/');

          // Keep the OpenAI stack in the deferred agent chunk graph.
          if (
            normalized.includes('/node_modules/@openai/') ||
            normalized.includes('/node_modules/openai/')
          ) {
            return 'agent-vendor';
          }

          // Anything the deferred engine also needs must live outside main.js so
          // chunks never `import "./main.js"` (see file header).
          if (
            normalized.includes('/frontend/src/api.ts') ||
            normalized.includes('/frontend/src/types.ts') ||
            normalized.includes('/frontend/src/markdown.ts') ||
            normalized.includes('/frontend/src/agent/')
          ) {
            return 'shared';
          }

          return undefined;
        },
      },
    },
  },
});
