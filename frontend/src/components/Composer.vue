<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { __, boot } from '../api';
import { cancel } from '../assistant';

export type AgentMode = 'agent' | 'ask';
export type ModelChoice = 'auto';

export interface ComposerAttachment {
  id: string;
  file: File;
  name: string;
  size: number;
  type: string;
  previewUrl?: string;
  status: 'ready' | 'uploading' | 'uploaded' | 'failed';
  url?: string;
  error?: string;
}

const props = defineProps<{
  modelValue: string;
  busy: boolean;
}>();

const emit = defineEmits<{
  (event: 'update:modelValue', value: string): void;
  (event: 'submit', payload: { text: string; attachments: ComposerAttachment[]; agent: AgentMode; model: ModelChoice }): void;
  (event: 'focus-ready', el: HTMLTextAreaElement | null): void;
}>();

const input = ref<HTMLTextAreaElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const root = ref<HTMLElement | null>(null);

const attachments = ref<ComposerAttachment[]>([]);
const agent = ref<AgentMode>('agent');
const model = ref<ModelChoice>('auto');
const agentOpen = ref(false);
const listening = ref(false);
const voiceSupported = ref(false);
const voiceError = ref('');

let recognition: SpeechRecognition | null = null;
let baseDraft = '';

const LINE_HEIGHT = 22;
const MAX_LINES = 4;

const canSend = computed(
  () => props.modelValue.trim().length > 0 || attachments.value.some((item) => item.status !== 'failed')
);

const agentLabel = computed(() => (agent.value === 'agent' ? __('Agent') : __('Ask')));

watch(
  () => props.modelValue,
  async () => {
    await nextTick();
    resize();
  }
);

watch(
  () => input.value,
  (el) => emit('focus-ready', el),
  { immediate: true }
);

onMounted(() => {
  voiceSupported.value = speechCtor() !== null;
  document.addEventListener('pointerdown', onDocPointer);
});

onBeforeUnmount(() => {
  stopVoice();
  clearPreviews();
  document.removeEventListener('pointerdown', onDocPointer);
});

function onDocPointer(event: PointerEvent): void {
  if (!root.value?.contains(event.target as Node)) {
    agentOpen.value = false;
  }
}

function resize(): void {
  const el = input.value;

  if (!el) {
    return;
  }

  el.style.height = 'auto';
  const max = LINE_HEIGHT * MAX_LINES;
  el.style.height = `${Math.min(el.scrollHeight, max)}px`;
  el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    void submit();
  }
}

function pickFiles(): void {
  fileInput.value?.click();
}

function onFiles(event: Event): void {
  const inputEl = event.target as HTMLInputElement;
  const files = Array.from(inputEl.files ?? []);
  inputEl.value = '';

  for (const file of files) {
    if (attachments.value.length >= 6) {
      break;
    }

    const item: ComposerAttachment = {
      id: `f${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`,
      file,
      name: file.name,
      size: file.size,
      type: file.type || 'application/octet-stream',
      status: 'ready',
    };

    if (file.type.startsWith('image/')) {
      item.previewUrl = URL.createObjectURL(file);
    }

    attachments.value.push(item);
  }
}

function removeAttachment(id: string): void {
  const index = attachments.value.findIndex((item) => item.id === id);

  if (index < 0) {
    return;
  }

  const [removed] = attachments.value.splice(index, 1);

  if (removed?.previewUrl) {
    URL.revokeObjectURL(removed.previewUrl);
  }
}

function clearPreviews(): void {
  for (const item of attachments.value) {
    if (item.previewUrl) {
      URL.revokeObjectURL(item.previewUrl);
    }
  }
}

function clearAttachments(): void {
  clearPreviews();
  attachments.value = [];
}

function formatSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function readTextFile(file: File): Promise<string | null> {
  if (file.size > 120_000) {
    return null;
  }

  const textual =
    file.type.startsWith('text/') ||
    /\.(txt|md|csv|json|xml|html|css|js|ts|php|log)$/i.test(file.name);

  if (!textual) {
    return null;
  }

  try {
    return await file.text();
  } catch {
    return null;
  }
}

async function uploadToMedia(file: File): Promise<{ url: string; mimeType: string }> {
  const body = new FormData();
  body.append('file', file);

  const response = await fetch(`${boot.siteRest}wp/v2/media`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': boot.nonce,
    },
    credentials: 'same-origin',
    body,
  });

  if (!response.ok) {
    const payload = (await response.json().catch(() => null)) as { message?: string } | null;
    throw new Error(payload?.message || __('The file could not be uploaded.'));
  }

  const data = (await response.json()) as {
    source_url?: string;
    link?: string;
    mime_type?: string;
  };
  const url = data.source_url || data.link;

  if (!url) {
    throw new Error(__('The file could not be uploaded.'));
  }

  return {
    url,
    mimeType: data.mime_type || file.type || 'application/octet-stream',
  };
}

function shouldUpload(file: File): boolean {
  if (file.size > 12 * 1024 * 1024) {
    return false;
  }

  return /^(image|audio|video|application\/pdf|application\/msword|application\/vnd\.)/.test(file.type);
}

async function prepareAttachments(): Promise<ComposerAttachment[]> {
  const prepared: ComposerAttachment[] = [];

  for (const item of attachments.value) {
    const next = { ...item };

    if (next.status === 'uploaded' && next.url) {
      prepared.push(next);
      continue;
    }

    const textual = await readTextFile(next.file);

    if (textual !== null && !next.type.startsWith('image/')) {
      next.status = 'ready';
      prepared.push(next);
      continue;
    }

    if (!shouldUpload(next.file)) {
      next.status = 'ready';
      prepared.push(next);
      continue;
    }

    next.status = 'uploading';
    attachments.value = attachments.value.map((row) => (row.id === next.id ? { ...next } : row));

    try {
      const uploaded = await uploadToMedia(next.file);
      next.url = uploaded.url;
      next.type = uploaded.mimeType || next.type;
      next.status = 'uploaded';
    } catch (error) {
      if (textual !== null) {
        next.status = 'ready';
        next.error = undefined;
      } else {
        next.status = 'failed';
        next.error = (error as Error).message;
      }
    }

    attachments.value = attachments.value.map((row) => (row.id === next.id ? { ...next } : row));
    prepared.push(next);
  }

  return prepared;
}

async function buildPayload(
  text: string,
  files: ComposerAttachment[]
): Promise<{ text: string; attachments: ComposerAttachment[] }> {
  const parts: string[] = [];
  const attachments: ComposerAttachment[] = [];

  if (text.trim()) {
    parts.push(text.trim());
  }

  for (const file of files) {
    if (file.status === 'failed') {
      continue;
    }

    // Uploaded media (images, PDFs, …) goes to the model as a native file part.
    if (file.url) {
      attachments.push(file);
      continue;
    }

    const content = await readTextFile(file.file);

    if (content !== null) {
      parts.push(`Attached file "${file.name}":\n\`\`\`\n${content.slice(0, 40_000)}\n\`\`\``);
      continue;
    }

    parts.push(`[Attached file: ${file.name} (${formatSize(file.size)}) — content unavailable]`);
  }

  return { text: parts.join('\n\n'), attachments };
}

async function submit(): Promise<void> {
  if (props.busy || !canSend.value) {
    return;
  }

  stopVoice();
  agentOpen.value = false;

  const text = props.modelValue;
  const files = await prepareAttachments();

  if (files.some((file) => file.status === 'failed') && !text.trim() && !files.some((file) => file.status !== 'failed')) {
    return;
  }

  const payload = await buildPayload(text, files);

  if (!payload.text.trim() && payload.attachments.length === 0) {
    return;
  }

  emit('submit', {
    text: payload.text,
    attachments: payload.attachments,
    agent: agent.value,
    model: model.value,
  });

  emit('update:modelValue', '');
  clearAttachments();
  await nextTick();
  resize();
}

function speechCtor(): (new () => SpeechRecognition) | null {
  const win = window as Window &
    typeof globalThis & {
      SpeechRecognition?: new () => SpeechRecognition;
      webkitSpeechRecognition?: new () => SpeechRecognition;
    };

  return win.SpeechRecognition ?? win.webkitSpeechRecognition ?? null;
}

function toggleVoice(): void {
  if (!voiceSupported.value) {
    voiceError.value = __('Speech input is not supported in this browser.');
    return;
  }

  if (listening.value) {
    stopVoice();
    return;
  }

  startVoice();
}

function startVoice(): void {
  const Ctor = speechCtor();

  if (!Ctor) {
    return;
  }

  voiceError.value = '';
  baseDraft = props.modelValue;
  recognition = new Ctor();
  recognition.lang = document.documentElement.lang || boot.locale.replace('_', '-') || 'en-US';
  recognition.continuous = true;
  recognition.interimResults = true;

  recognition.onresult = (event: SpeechRecognitionEvent) => {
    let interim = '';
    let finalText = '';

    for (let i = event.resultIndex; i < event.results.length; i += 1) {
      const result = event.results[i];
      const chunk = result[0]?.transcript ?? '';

      if (result.isFinal) {
        finalText += chunk;
      } else {
        interim += chunk;
      }
    }

    if (finalText) {
      baseDraft = `${baseDraft}${baseDraft && !baseDraft.endsWith(' ') ? ' ' : ''}${finalText.trim()} `;
    }

    const next = `${baseDraft}${interim}`.replace(/\s+$/g, (match) => (interim ? match : '')).trimStart();
    emit('update:modelValue', next);
  };

  recognition.onerror = () => {
    voiceError.value = __('Could not hear anything. Check the microphone and try again.');
    stopVoice();
  };

  recognition.onend = () => {
    listening.value = false;
    recognition = null;
  };

  listening.value = true;
  recognition.start();
}

function stopVoice(): void {
  recognition?.stop();
  recognition = null;
  listening.value = false;
}

function chooseAgent(value: AgentMode): void {
  agent.value = value;
  agentOpen.value = false;
}

defineExpose({
  focus: () => input.value?.focus(),
  el: input,
});
</script>

<template>
  <form ref="root" class="asdevs-ai-composer" @submit.prevent="submit()">
    <div v-if="attachments.length" class="asdevs-ai-composer__files">
      <div v-for="item in attachments" :key="item.id" class="asdevs-ai-file" :class="`is-${item.status}`">
        <img v-if="item.previewUrl" :src="item.previewUrl" alt="" class="asdevs-ai-file__thumb" />
        <div class="asdevs-ai-file__meta">
          <span class="asdevs-ai-file__name">{{ item.name }}</span>
          <span class="asdevs-ai-file__size">
            <template v-if="item.status === 'uploading'">{{ __('Uploading…') }}</template>
            <template v-else-if="item.status === 'failed'">{{ item.error || __('Upload failed') }}</template>
            <template v-else>{{ formatSize(item.size) }}</template>
          </span>
        </div>
        <button
          type="button"
          class="asdevs-ai-file__remove"
          :aria-label="__('Remove file')"
          @click="removeAttachment(item.id)"
        >
          <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
            <path d="m7 7 10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </div>

    <label class="screen-reader-text" for="asdevs-ai-input">{{ __('Message the assistant') }}</label>
    <textarea
      id="asdevs-ai-input"
      ref="input"
      class="asdevs-ai-composer__input"
      rows="1"
      :value="modelValue"
      :placeholder="__('Tell me what you need')"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
      @keydown="onKeydown"
    ></textarea>

    <div class="asdevs-ai-composer__bar">
      <div class="asdevs-ai-composer__left">
        <div class="asdevs-ai-menu">
          <button
            type="button"
            class="asdevs-ai-pill"
            :aria-expanded="agentOpen"
            :aria-label="__('Choose mode')"
            @click="agentOpen = !agentOpen"
          >
            <span class="asdevs-ai-pill__glyph" aria-hidden="true">∞</span>
            <span>{{ agentLabel }}</span>
            <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false">
              <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <div v-if="agentOpen" class="asdevs-ai-menu__list" role="listbox">
            <button type="button" class="asdevs-ai-menu__item" :class="{ 'is-active': agent === 'agent' }" @click="chooseAgent('agent')">
              {{ __('Agent') }}
            </button>
            <button type="button" class="asdevs-ai-menu__item" :class="{ 'is-active': agent === 'ask' }" @click="chooseAgent('ask')">
              {{ __('Ask') }}
            </button>
          </div>
        </div>

        <span class="asdevs-ai-pill asdevs-ai-pill--ghost asdevs-ai-pill--static">
          {{ __('Auto') }}
        </span>
      </div>

      <div class="asdevs-ai-composer__right">
        <span v-if="listening" class="asdevs-ai-composer__pulse" aria-hidden="true"></span>

        <input
          ref="fileInput"
          class="asdevs-ai-composer__file-input"
          type="file"
          accept="image/*,audio/*,video/*,.pdf,.txt,.md,.csv,.json,.xml,.html,.css,.js,.ts,.php,.log,.doc,.docx"
          multiple
          @change="onFiles"
        />

        <button
          type="button"
          class="asdevs-ai-tool"
          :aria-label="__('Attach a file')"
          :title="__('Attach a file')"
          @click="pickFiles()"
        >
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
            <path
              d="M8.5 12.5 14 7a3.2 3.2 0 0 1 4.5 4.5l-7.1 7.1a4.5 4.5 0 0 1-6.4-6.4L12 5.2"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>

        <button
          type="button"
          class="asdevs-ai-tool"
          :class="{ 'is-active': listening, 'is-disabled': !voiceSupported }"
          :aria-label="listening ? __('Stop listening') : __('Speak')"
          :title="listening ? __('Stop listening') : __('Speak')"
          :aria-pressed="listening"
          @click="toggleVoice()"
        >
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
            <path
              d="M12 3a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3Z"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
            />
            <path
              d="M6.5 11.5a5.5 5.5 0 0 0 11 0M12 17v3.5"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linecap="round"
            />
          </svg>
        </button>

        <button
          v-if="busy"
          type="button"
          class="asdevs-ai-send asdevs-ai-send--stop"
          :aria-label="__('Stop')"
          :title="__('Stop')"
          @click="cancel()"
        >
          <span aria-hidden="true"></span>
        </button>
        <button
          v-else
          type="submit"
          class="asdevs-ai-send"
          :disabled="!canSend"
          :aria-label="__('Send')"
          :title="__('Send')"
        >
          <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">
            <path d="M12 19V5M6 11l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>
    </div>

    <p v-if="voiceError" class="asdevs-ai-composer__hint asdevs-ai-note--bad">{{ voiceError }}</p>
  </form>
</template>
