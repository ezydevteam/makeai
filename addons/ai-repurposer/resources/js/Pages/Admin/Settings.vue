<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
  settings: Record<string, any>
}>()

const form = useForm({
  enabled: props.settings.enabled ?? true,
  ai_model: props.settings.ai_model ?? '',
  transcription_provider: props.settings.transcription_provider ?? 'whisper',
  credits_per_repurpose: props.settings.credits_per_repurpose ?? 15,
  credits_per_bulk_item: props.settings.credits_per_bulk_item ?? 12,
  max_file_size_mb: props.settings.max_file_size_mb ?? 100,
  max_bulk_items: props.settings.max_bulk_items ?? 10,
  default_formats: props.settings.default_formats ?? 'blog_post,twitter_thread,linkedin_article,email_newsletter',
  twitter_thread_length: props.settings.twitter_thread_length ?? 10,
  blog_post_min_words: props.settings.blog_post_min_words ?? 800,
  auto_save_blog: props.settings.auto_save_blog ?? false,
})

const formatOptions = [
  { key: 'blog_post', label: t('Blog Post') },
  { key: 'twitter_thread', label: t('X Thread') },
  { key: 'linkedin_article', label: t('LinkedIn Article') },
  { key: 'email_newsletter', label: t('Email Newsletter') },
  { key: 'tiktok_script', label: t('TikTok Script') },
  { key: 'podcast_show_notes', label: t('Podcast Show Notes') },
  { key: 'key_quotes', label: t('Key Quotes') },
  { key: 'chapter_markers', label: t('Chapter Markers') },
]

const selectedFormats = ref((props.settings.default_formats ?? 'blog_post,twitter_thread,linkedin_article,email_newsletter').split(',').filter(Boolean))

function toggleFormat(key: string) {
  const idx = selectedFormats.value.indexOf(key)
  if (idx === -1) {
    selectedFormats.value.push(key)
  } else {
    selectedFormats.value.splice(idx, 1)
  }
  form.default_formats = selectedFormats.value.join(',')
}

function save() {
  form.put('/admin/content-repurposer/settings', { preserveScroll: true })
}
</script>

<template>
  <Head :title="t('Content Repurposer Settings')" />

  <div class="space-y-8 p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Content Repurposer Settings') }}</h1>
      <button class="btn-primary rounded-xl px-6 py-2.5 text-sm font-medium disabled:opacity-60" :disabled="form.processing" @click="save">
        <i v-if="form.processing" class="ti ti-loader-2 mr-2 animate-spin" />
        {{ form.processing ? t('Saving...') : t('Save') }}
      </button>
    </div>

    <!-- General -->
    <section class="card space-y-4 p-5">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('General') }}</h3>
      <label class="flex items-center gap-2">
        <input v-model="form.enabled" type="checkbox" class="rounded border-gray-300" />
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Enable Content Repurposer') }}</span>
      </label>
      <label class="flex items-center gap-2">
        <input v-model="form.auto_save_blog" type="checkbox" class="rounded border-gray-300" />
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Auto-save blog posts to core blog') }}</span>
      </label>

      <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Formats') }}</label>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="fmt in formatOptions"
            :key="fmt.key"
            type="button"
            @click="toggleFormat(fmt.key)"
            :class="[
              'rounded-full border px-3 py-1.5 text-xs font-medium transition',
              selectedFormats.includes(fmt.key)
                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300'
                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400',
            ]"
          >
            {{ fmt.label }}
          </button>
        </div>
      </div>
    </section>

    <!-- AI -->
    <section class="card space-y-4 p-5">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('AI Configuration') }}</h3>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('AI Model') }}</label>
        <input
          v-model="form.ai_model"
          type="text"
          class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
          :placeholder="t('Default: gpt-4o-mini')"
        />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Transcription Provider') }}</label>
        <select
          v-model="form.transcription_provider"
          class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
        >
          <option value="whisper">OpenAI Whisper</option>
          <option value="assemblyai">AssemblyAI</option>
        </select>
      </div>
    </section>

    <!-- Credits -->
    <section class="card space-y-4 p-5">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Credits') }}</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per Single Repurpose') }}</label>
          <input v-model.number="form.credits_per_repurpose" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per Bulk Item') }}</label>
          <input v-model.number="form.credits_per_bulk_item" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
        </div>
      </div>
    </section>

    <!-- Limits -->
    <section class="card space-y-4 p-5">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Limits') }}</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max File Size (MB)') }}</label>
          <input v-model.number="form.max_file_size_mb" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Bulk Items') }}</label>
          <input v-model.number="form.max_bulk_items" type="number" min="1" max="100" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Twitter Thread Length') }}</label>
          <input v-model.number="form.twitter_thread_length" type="number" min="1" max="50" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Blog Post Min Words') }}</label>
          <input v-model.number="form.blog_post_min_words" type="number" min="100" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
        </div>
      </div>
    </section>
  </div>
</template>
