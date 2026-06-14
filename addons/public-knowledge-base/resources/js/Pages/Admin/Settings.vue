<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppSelect from '@/Components/AppSelect.vue'
import AppColorPicker from '@/Components/AppColorPicker.vue'

const { t } = useTranslate()

const origin = window.location.origin

const props = defineProps<{
  settings: Record<string, any>
}>()

const defaults = {
  enabled: true,
  public_slug: 'help',
  page_title: 'Help Center',
  page_description: '',
  show_vote_buttons: true,
  allow_guest_search: true,
  widget_enabled: false,
  widget_accent_color: '#10b981',
  ai_model: '',
  embedding_model: '',
  top_k: 5,
  max_answer_tokens: 512,
  provider: '',
}

const form = ref({
  ...defaults,
  ...Object.fromEntries(
    Object.entries(props.settings).filter(([, v]) => v !== undefined)
  ),
})
const processing = ref(false)

function save() {
  processing.value = true
  router.put('/admin/kb/settings', form.value, {
    onFinish: () => { processing.value = false },
  })
}
</script>

<template>
  <AdminLayout :title="t('KB Settings')">
    <div class="card mb-6">
      <h2 class="text-lg font-bold mb-4">{{ t('General') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="flex items-center gap-2">
          <input v-model="form.enabled" type="checkbox" />
          {{ t('Enable Public KB') }}
        </label>
        <div>
          <label class="label">{{ t('Public URL Slug') }}</label>
          <div class="flex items-center gap-1 text-sm text-gray-500 mb-1">/ <input v-model="form.public_slug" class="input" /></div>
        </div>
        <div>
          <label class="label">{{ t('Page Title') }}</label>
          <input v-model="form.page_title" class="input w-full" />
        </div>
        <div>
          <label class="label">{{ t('Page Meta Description') }}</label>
          <textarea v-model="form.page_description" class="input w-full" rows="2" />
        </div>
        <label class="flex items-center gap-2">
          <input v-model="form.show_vote_buttons" type="checkbox" />
          {{ t('Show Vote Buttons') }}
        </label>
        <label class="flex items-center gap-2">
          <input v-model="form.allow_guest_search" type="checkbox" />
          {{ t('Allow Guest Search') }}
        </label>
      </div>
    </div>

    <div class="card mb-6">
      <h2 class="text-lg font-bold mb-4">{{ t('AI Configuration') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="label">{{ t('AI Provider') }}</label>
          <input v-model="form.provider" class="input w-full" placeholder="openai" />
        </div>
        <div>
          <label class="label">{{ t('AI Model') }}</label>
          <input v-model="form.ai_model" class="input w-full" placeholder="gpt-4o-mini" />
        </div>
        <div>
          <label class="label">{{ t('Embedding Model') }}</label>
          <AppSelect v-model="form.embedding_model" :options="[
            { value: '', label: t('Default') },
            { value: 'text-embedding-3-small', label: 'text-embedding-3-small' },
            { value: 'text-embedding-3-large', label: 'text-embedding-3-large' },
            { value: 'text-embedding-ada-002', label: 'text-embedding-ada-002' },
          ]" />
        </div>
        <div>
          <label class="label">{{ t('Top-K Chunks') }} (1–20)</label>
          <input v-model.number="form.top_k" type="range" min="1" max="20" class="w-full" />
          <span class="text-sm">{{ form.top_k }}</span>
        </div>
        <div>
          <label class="label">{{ t('Max Answer Tokens') }} (64–4096)</label>
          <input v-model.number="form.max_answer_tokens" type="number" min="64" max="4096" class="input w-full" />
        </div>
      </div>
      <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded text-sm">
        {{ t('Changing the embedding model requires re-indexing all articles. Run: php artisan kb:ingest-all') }}
      </div>
    </div>

    <div class="card mb-6">
      <h2 class="text-lg font-bold mb-4">{{ t('Embeddable Widget') }}</h2>
      <div class="space-y-3">
        <label class="flex items-center gap-2">
          <input v-model="form.widget_enabled" type="checkbox" />
          {{ t('Enable Widget') }}
        </label>
        <div>
          <label class="label">{{ t('Widget Accent Color') }}</label>
          <AppColorPicker v-model="form.widget_accent_color" />
        </div>
        <div>
          <label class="label">{{ t('Install Code') }}</label>
          <pre class="bg-gray-100 dark:bg-gray-700 p-3 rounded text-xs overflow-x-auto"><code>&lt;script src="{{ origin }}/addons/kb-widget.js"
        data-kb-url="{{ origin }}/api/kb-widget"
        data-accent="{{ form.widget_accent_color }}"&gt;&lt;/script&gt;</code></pre>
        </div>
      </div>
    </div>

    <button @click="save" :disabled="processing" class="btn btn-primary">
      {{ processing ? t('Saving...') : t('Save Settings') }}
    </button>
  </AdminLayout>
</template>
