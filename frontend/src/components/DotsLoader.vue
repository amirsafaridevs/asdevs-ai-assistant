<script setup lang="ts">
/**
 * Shared three-dot indeterminate loader.
 *
 * Soft opacity pulse — the same pattern used on start chips, settings,
 * skills, and engine boot. Colour comes from `currentColor`.
 */
withDefaults(
  defineProps<{
    size?: number;
  }>(),
  {
    size: 36,
  }
);
</script>

<template>
  <svg
    class="asdevs-ai-dots"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 200 200"
    :width="size"
    :height="size"
    aria-hidden="true"
    focusable="false"
  >
    <circle fill="currentColor" stroke="currentColor" stroke-width="15" r="15" cx="40" cy="100">
      <animate
        attributeName="opacity"
        calcMode="spline"
        dur="2s"
        values="1;0;1"
        keySplines=".5 0 .5 1;.5 0 .5 1"
        repeatCount="indefinite"
        begin="-0.4s"
      />
    </circle>
    <circle fill="currentColor" stroke="currentColor" stroke-width="15" r="15" cx="100" cy="100">
      <animate
        attributeName="opacity"
        calcMode="spline"
        dur="2s"
        values="1;0;1"
        keySplines=".5 0 .5 1;.5 0 .5 1"
        repeatCount="indefinite"
        begin="-0.2s"
      />
    </circle>
    <circle fill="currentColor" stroke="currentColor" stroke-width="15" r="15" cx="160" cy="100">
      <animate
        attributeName="opacity"
        calcMode="spline"
        dur="2s"
        values="1;0;1"
        keySplines=".5 0 .5 1;.5 0 .5 1"
        repeatCount="indefinite"
        begin="0s"
      />
    </circle>
  </svg>
</template>
