<script setup lang="ts">
import { __ } from '../api';
import type { PendingConfirmation } from '../types';

defineProps<{ pending: PendingConfirmation }>();
defineEmits<{ (event: 'confirm'): void; (event: 'decline'): void }>();
</script>

<template>
  <!--
    A confirmation says three things: what will happen, how much it touches,
    and whether it can be undone.
  -->
  <div class="asdevs-ai-confirm" role="group" :aria-label="__('Confirm this change')">
    <p class="asdevs-ai-confirm__summary">{{ pending.summary }}</p>
    <p class="asdevs-ai-confirm__meta">
      <span v-if="pending.affected">{{ __('Items affected:') }} {{ pending.affected }}</span>
      <span>{{ pending.reversible ? __('This can be undone.') : __('This cannot be undone.') }}</span>
    </p>
    <div class="asdevs-ai-confirm__actions">
      <button type="button" class="asdevs-ai-btn asdevs-ai-btn--primary" @click="$emit('confirm')">
        {{ __('Yes, do it') }}
      </button>
      <button type="button" class="asdevs-ai-btn" @click="$emit('decline')">{{ __('No') }}</button>
    </div>
  </div>
</template>
