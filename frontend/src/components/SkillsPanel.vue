<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { __, sprintf } from '../api';
import { removeSkill, saveSkill, state } from '../assistant';
import type { Skill } from '../types';

type Screen = 'list' | 'form';

const screen = ref<Screen>('list');
const editingId = ref<number | null>(null);
const title = ref('');
const slug = ref('');
const description = ref('');
const whenToUse = ref('');
const keywords = ref('');
const prompt = ref('');
const busy = ref(false);
const error = ref('');
const slugTouched = ref(false);

const skills = computed(() => state.skills);
const isEditing = computed(() => editingId.value !== null);

watch(
  () => state.view,
  (view) => {
    if (view === 'skills') {
      screen.value = 'list';
      resetForm(false);
    }
  }
);

function slugify(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 64);
}

function resetForm(keepScreen = true): void {
  editingId.value = null;
  title.value = '';
  slug.value = '';
  description.value = '';
  whenToUse.value = '';
  keywords.value = '';
  prompt.value = '';
  slugTouched.value = false;
  error.value = '';

  if (!keepScreen) {
    screen.value = 'list';
  }
}

function startCreate(): void {
  resetForm();
  screen.value = 'form';
}

function startEdit(skill: Skill): void {
  editingId.value = skill.id;
  title.value = skill.title;
  slug.value = skill.slug;
  description.value = skill.description || '';
  whenToUse.value = skill.when_to_use || '';
  keywords.value = skill.keywords || '';
  prompt.value = skill.prompt;
  slugTouched.value = true;
  error.value = '';
  screen.value = 'form';
}

function cancelForm(): void {
  resetForm(false);
}

function onTitleInput(value: string): void {
  title.value = value;

  if (!slugTouched.value) {
    slug.value = slugify(value);
  }
}

function onSlugInput(value: string): void {
  slugTouched.value = true;
  slug.value = slugify(value.replace(/^\//, ''));
}

async function submit(): Promise<void> {
  if (busy.value) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await saveSkill({
      id: editingId.value ?? undefined,
      title: title.value.trim(),
      slug: slug.value.trim(),
      prompt: prompt.value.trim(),
      description: description.value.trim(),
      when_to_use: whenToUse.value.trim(),
      keywords: keywords.value.trim(),
    });
    resetForm(false);
  } catch (err) {
    error.value = (err as Error).message || __('Could not save the skill.');
  } finally {
    busy.value = false;
  }
}

async function destroy(skill: Skill): Promise<void> {
  if (busy.value) {
    return;
  }

  const confirmed = window.confirm(
    sprintf(__('Delete this skill? It will no longer be available as /%1$s.'), skill.slug)
  );

  if (!confirmed) {
    return;
  }

  busy.value = true;
  error.value = '';

  try {
    await removeSkill(skill.id);

    if (editingId.value === skill.id) {
      resetForm(false);
    }
  } catch (err) {
    error.value = (err as Error).message || __('Could not delete the skill.');
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="asdevs-ai-settings asdevs-ai-skills">
    <template v-if="screen === 'list'">
      <p v-if="state.skillsBusy && skills.length === 0" class="asdevs-ai-skills__status">{{ __('Loading…') }}</p>

      <p v-else-if="state.skillsError && skills.length === 0" class="asdevs-ai-note asdevs-ai-note--bad">
        {{ state.skillsError }}
      </p>

      <div v-else-if="skills.length === 0" class="asdevs-ai-skills__empty">
        <p class="asdevs-ai-skills__empty-title">{{ __('No skills yet') }}</p>
        <p class="asdevs-ai-skills__empty-text">
          {{ __('Create a reusable prompt you can activate with /slug or the + menu.') }}
        </p>
        <button type="button" class="asdevs-ai-btn asdevs-ai-btn--primary" @click="startCreate()">
          {{ __('Create your first skill') }}
        </button>
      </div>

      <template v-else>
        <ul class="asdevs-ai-skills__list">
          <li v-for="skill in skills" :key="skill.id" class="asdevs-ai-skills__item">
            <div class="asdevs-ai-skills__meta">
              <strong>{{ skill.title }}</strong>
              <code class="asdevs-ai-skills__slug">/{{ skill.slug }}</code>
              <span v-if="skill.description" class="asdevs-ai-skills__desc">{{ skill.description }}</span>
            </div>
            <div class="asdevs-ai-skills__actions">
              <button
                type="button"
                class="asdevs-ai-skills__icon-btn"
                :aria-label="__('Edit')"
                :title="__('Edit')"
                @click="startEdit(skill)"
              >
                <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">
                  <path
                    d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linejoin="round"
                  />
                  <path
                    d="m13.5 6.5 3 3"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                  />
                </svg>
              </button>
              <button
                type="button"
                class="asdevs-ai-skills__icon-btn asdevs-ai-skills__icon-btn--danger"
                :aria-label="__('Delete')"
                :title="__('Delete')"
                :disabled="busy"
                @click="destroy(skill)"
              >
                <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" focusable="false">
                  <path
                    d="M5 7h14M10 11v6M14 11v6M9 7l1-2h4l1 2m-8 0 1 12h8l1-12"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
          </li>
        </ul>

        <div class="asdevs-ai-skills__footer">
          <button type="button" class="asdevs-ai-btn asdevs-ai-btn--primary" @click="startCreate()">
            {{ __('New skill') }}
          </button>
        </div>
      </template>
    </template>

    <form v-else class="asdevs-ai-skills__form" @submit.prevent="submit()">
      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-skill-title">{{ __('Name') }}</label>
        <input
          id="asdevs-ai-skill-title"
          class="asdevs-ai-input asdevs-ai-skills__input"
          type="text"
          maxlength="120"
          :value="title"
          required
          autofocus
          @input="onTitleInput(($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-skill-slug">{{ __('Slug') }}</label>
        <div class="asdevs-ai-skills__slug-field">
          <span class="asdevs-ai-skills__slash" aria-hidden="true">/</span>
          <input
            id="asdevs-ai-skill-slug"
            class="asdevs-ai-input asdevs-ai-skills__input asdevs-ai-skills__slug-input"
            type="text"
            maxlength="64"
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            :value="slug"
            required
            @input="onSlugInput(($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-skill-desc">{{ __('Short description') }}</label>
        <input
          id="asdevs-ai-skill-desc"
          class="asdevs-ai-input asdevs-ai-skills__input"
          type="text"
          maxlength="400"
          :value="description"
          :placeholder="__('Optional')"
          @input="description = ($event.target as HTMLInputElement).value"
        />
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-skill-when">{{ __('When to use this') }}</label>
        <textarea
          id="asdevs-ai-skill-when"
          class="asdevs-ai-input asdevs-ai-skills__input"
          rows="2"
          maxlength="600"
          :value="whenToUse"
          :placeholder="__('So the assistant can pick this itself — e.g. “When the person asks for a product description or shop copy.”')"
          @input="whenToUse = ($event.target as HTMLTextAreaElement).value"
        ></textarea>
        <p class="asdevs-ai-hint">
          {{ __('Leave empty and the assistant only uses this skill when you pick it yourself.') }}
        </p>
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-skill-keywords">{{ __('Keywords') }}</label>
        <input
          id="asdevs-ai-skill-keywords"
          class="asdevs-ai-input asdevs-ai-skills__input"
          type="text"
          maxlength="400"
          :value="keywords"
          :placeholder="__('Optional, comma separated — words people use for this, in any language')"
          @input="keywords = ($event.target as HTMLInputElement).value"
        />
      </div>

      <div class="asdevs-ai-field">
        <label class="asdevs-ai-label" for="asdevs-ai-skill-prompt">{{ __('Prompt') }}</label>
        <textarea
          id="asdevs-ai-skill-prompt"
          class="asdevs-ai-input asdevs-ai-skills__input asdevs-ai-skills__prompt"
          rows="8"
          maxlength="8000"
          :value="prompt"
          required
          :placeholder="__('Instructions the assistant should follow while this skill is active…')"
          @input="prompt = ($event.target as HTMLTextAreaElement).value"
        ></textarea>
      </div>

      <p v-if="error" class="asdevs-ai-note asdevs-ai-note--bad">{{ error }}</p>

      <div class="asdevs-ai-settings__actions">
        <button type="submit" class="asdevs-ai-btn asdevs-ai-btn--primary" :disabled="busy">
          {{ busy ? __('Saving…') : isEditing ? __('Update skill') : __('Save skill') }}
        </button>
        <button type="button" class="asdevs-ai-btn" :disabled="busy" @click="cancelForm()">
          {{ __('Cancel') }}
        </button>
      </div>
    </form>
  </div>
</template>
