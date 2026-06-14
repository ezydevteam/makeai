<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()
const toast = useToastr()
const page = usePage()

interface SyncStatus {
  last_synced_at: string | null
  voice_count: number
}

const props = defineProps<{
  voiceSyncStatus: Record<string, SyncStatus>
}>()

const form = useForm({
  enabled: true,
  default_provider: 'openai',
  default_voice_id: 'alloy',
  speed_default: '1.0',
  stability_default: '0.5',
  similarity_boost_default: '0.75',
  podcast_enabled: true,
  podcast_base_url: '',
  credits_per_1k_chars: 5,
  credits_stt: 10,
  max_script_chars: 5000,
  max_file_size_mb: 25,
  background_music_max_size_mb: 10,
  auto_transcribe: false,
  ffmpeg_path: '/usr/bin/ffmpeg',
  auto_delete_days: 0,
  elevenlabs_api_key: '',
  openai_api_key: '',
  murf_api_key: '',
  playht_api_key: '',
  playht_user_id: '',
  show_to: 'logged_in',
})

function submit() {
  form.put(route('addon.vo.admin.settings.update'), {
    preserveScroll: true,
    onSuccess: () => toast.success(t('Settings saved.')),
  })
}

function syncVoices() {
  form.post(route('addon.vo.admin.sync-voices'), {
    preserveScroll: true,
    onSuccess: () => toast.success(t('Voice sync dispatched.')),
  })
}
</script>

<template>
  <Head :title="t('Voiceover Settings')" />

  <div class="mx-auto max-w-4xl px-6 py-8">
    <h1 class="mb-8 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Voiceover Studio — Settings') }}</h1>

    <form @submit.prevent="submit" class="space-y-8">
      <!-- General -->
      <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('General') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="flex items-center gap-2">
            <input v-model="form.enabled" type="checkbox" class="rounded border-gray-300" />
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Enable Voiceover Studio') }}</span>
          </label>
          <label class="flex items-center gap-2">
            <input v-model="form.podcast_enabled" type="checkbox" class="rounded border-gray-300" />
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Enable Podcast RSS feeds') }}</span>
          </label>
          <label class="flex items-center gap-2">
            <input v-model="form.auto_transcribe" type="checkbox" class="rounded border-gray-300" />
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Auto-transcribe generated audio') }}</span>
          </label>
        </div>

        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Access for') }}</label>
          <select v-model="form.show_to" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            <option value="all">{{ t('All') }}</option>
            <option value="logged_in">{{ t('Logged In') }}</option>
            <option value="pro">{{ t('Pro Users') }}</option>
          </select>
        </div>

        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Podcast base URL') }}</label>
          <input v-model="form.podcast_base_url" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Leave empty to use site URL')" />
        </div>
      </section>

      <!-- AI Provider Settings -->
      <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('AI Provider Settings') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default TTS Provider') }}</label>
            <select v-model="form.default_provider" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
              <option value="elevenlabs">ElevenLabs</option>
              <option value="openai">OpenAI</option>
              <option value="murf">Murf</option>
              <option value="playht">PlayHT</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Voice ID') }}</label>
            <input v-model="form.default_voice_id" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Speed (OpenAI)') }}</label>
            <input v-model="form.speed_default" type="number" step="0.1" min="0.25" max="4" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Stability (ElevenLabs)') }}</label>
            <input v-model="form.stability_default" type="number" step="0.05" min="0" max="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default Similarity Boost (ElevenLabs)') }}</label>
            <input v-model="form.similarity_boost_default" type="number" step="0.05" min="0" max="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
      </section>

      <!-- Credits -->
      <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Credits & Limits') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per 1,000 chars TTS') }}</label>
            <input v-model.number="form.credits_per_1k_chars" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per STT transcription') }}</label>
            <input v-model.number="form.credits_stt" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max script characters') }}</label>
            <input v-model.number="form.max_script_chars" type="number" min="100" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max audio file size for STT (MB)') }}</label>
            <input v-model.number="form.max_file_size_mb" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max background music size (MB)') }}</label>
            <input v-model.number="form.background_music_max_size_mb" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
      </section>

      <!-- Storage -->
      <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Storage & Cleanup') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('ffmpeg binary path') }}</label>
            <input v-model="form.ffmpeg_path" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-delete audio after N days (0=never)') }}</label>
            <input v-model.number="form.auto_delete_days" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
      </section>

      <!-- Provider API Keys -->
      <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Provider API Keys') }}</h2>
        <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">{{ t('These are fallback keys. Provider keys from Admin → Integrations settings are checked first.') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">ElevenLabs API Key</label>
            <input v-model="form.elevenlabs_api_key" type="password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">OpenAI API Key</label>
            <input v-model="form.openai_api_key" type="password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Murf API Key</label>
            <input v-model="form.murf_api_key" type="password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">PlayHT API Key</label>
            <input v-model="form.playht_api_key" type="password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">PlayHT User ID</label>
            <input v-model="form.playht_user_id" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
      </section>

      <!-- Voice sync -->
      <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Voice Library') }}</h2>
        <div class="space-y-3">
          <div v-for="(status, provider) in props.voiceSyncStatus" :key="provider" class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800/30">
            <div>
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ provider }}</span>
              <span class="ml-2 text-xs text-gray-500">
                {{ status.voice_count }} {{ t('voices') }}
                <template v-if="status.last_synced_at"> · {{ t('Last synced: :time', { time: status.last_synced_at }) }}</template>
                <template v-else> · {{ t('Never synced') }}</template>
              </span>
            </div>
            <button type="button" @click="syncVoices" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700">
              <i class="ti ti-refresh"></i> {{ t('Sync Voices') }}
            </button>
          </div>
        </div>
      </section>

      <!-- Save -->
      <div class="flex justify-end">
        <button type="submit" :disabled="form.processing" class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-3 text-sm font-medium">
          <i v-if="form.processing" class="ti ti-loader animate-spin"></i>
          {{ form.processing ? t('Saving...') : t('Save Settings') }}
        </button>
      </div>
    </form>
  </div>
</template>
