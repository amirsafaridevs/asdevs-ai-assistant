import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';

// The widget ships as an ES module plus one stylesheet, both enqueued the
// WordPress way. Names are stable so the PHP side can reference them.
//
// The output is a module rather than an IIFE for one reason: an IIFE cannot be
// code-split, so the OpenAI Agents SDK would be inlined into the script every
// admin page loads. As a module, the SDK lives in agent-engine.js and is
// fetched only when someone opens the assistant.
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
        // One deferred chunk, under a stable name so the version stamp and any
        // cache rule on the PHP side keep working.
        chunkFileNames: 'agent-engine.js',
      },
    },
  },
});
