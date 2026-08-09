<script setup lang="ts">
withDefaults(
  defineProps<{
    size?: number;
    /** Larger, higher-contrast sparks for the floating launcher. */
    bold?: boolean;
  }>(),
  { size: 20, bold: false }
);
</script>

<template>
  <!--
    Solid spark mark — filled for clarity at small sizes. currentColor so
    headers and the launcher can tint it without redrawing.
  -->
  <svg
    class="asdevs-ai-logo"
    :class="{ 'asdevs-ai-logo--bold': bold }"
    :width="size"
    :height="size"
    viewBox="0 0 32 32"
    fill="none"
    aria-hidden="true"
    focusable="false"
  >
    <g :transform="bold ? 'translate(16 15.2) scale(1.32) translate(-16 -15.2)' : undefined">
      <path
        d="M16 3.2c1.15 3.05 2.1 4.05 5.15 5.2-3.05 1.15-4 2.1-5.15 5.15-1.15-3.05-2.1-4-5.15-5.15 3.05-1.15 4-2.15 5.15-5.2Z"
        fill="currentColor"
      />
      <path
        d="M24.2 14.4c.7 1.85 1.3 2.45 3.15 3.15-1.85.7-2.45 1.3-3.15 3.15-.7-1.85-1.3-2.45-3.15-3.15 1.85-.7 2.45-1.3 3.15-3.15Z"
        fill="currentColor"
        :opacity="bold ? 1 : 0.88"
      />
      <path
        d="M9.1 18.6c.55 1.45 1 1.9 2.45 2.45-1.45.55-1.9 1-2.45 2.45-.55-1.45-1-1.9-2.45-2.45 1.45-.55 1.9-1 2.45-2.45Z"
        fill="currentColor"
        :opacity="bold ? 0.92 : 0.74"
      />
      <path
        d="M11.4 7.2c.3.8.55 1.05 1.35 1.35-.8.3-1.05.55-1.35 1.35-.3-.8-.55-1.05-1.35-1.35.8-.3 1.05-.55 1.35-1.35Z"
        fill="currentColor"
        :opacity="bold ? 0.85 : 0.62"
      />
    </g>
  </svg>
</template>
