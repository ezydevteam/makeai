<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()
const page = usePage()
const repurposer = (page.props.repurposer as any) || {}
const jobs = (page.props.jobs as any) || { data: [] }

const sourceType = ref<'youtube_url' | 'file_upload' | 'text_paste'>('youtube_url')
const showBulk = ref(false)
const selectedFormats = ref<string[]>([...repurposer.defaultFormats])
const videoId = ref<string | null>(null)

const formatOptions = computed(() => {
  return Object.entries(repurposer.availableFormats || {}).map(([key, val]: [string, any]) => ({
    key,
    ...val,
  }))
})

const form = useForm({
  source_type: 'youtube_url',
  source_url: '',
  file: null as File | null,
  text: '',
  title: '',
  formats: [] as string[],
})

const bulkForm = useForm({
  urls: [] as string[],
  formats: [] as string[],
  title_prefix: '',
})

const creditCost = computed(() => repurposer.creditCost || 15)
const bulkCreditCost = computed(() => repurposer.bulkCreditCost || 12)

function toggleFormat(key: string) {
  const idx = selectedFormats.value.indexOf(key)
  if (idx === -1) {
    selectedFormats.value.push(key)
  } else {
    selectedFormats.value.splice(idx, 1)
  }
}

function selectAllFormats() {
  selectedFormats.value = formatOptions.value.map((f: any) => f.key)
}

function deselectAllFormats() {
  selectedFormats.value = []
}

function onYouTubeUrlInput(url: string) {
  const patterns = [
    /youtube\.com\/watch\?v=([^&\s]+)/,
    /youtu\.be\/([^?\s]+)/,
    /youtube\.com\/shorts\/([^?\s]+)/,
    /youtube\.com\/embed\/([^?\s]+)/,
  ]
  for (const p of patterns) {
    const m = url.match(p)
    if (m) {
      videoId.value = m[1]
      return
    }
  }
  videoId.value = null
}

function handleFileUpload(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files?.[0]) {
    form.file = target.files[0]
  }
}

function submit() {
  form.source_type = sourceType.value
  form.formats = selectedFormats.value
  form.post('/content-repurposer')
}

function submitBulk() {
  const urlLines = document.querySelector<HTMLTextAreaElement>('#bulk-urls')?.value ?? ''
  bulkForm.urls = urlLines.split('\n').map((l: string) => l.trim()).filter(Boolean)
  bulkForm.formats = selectedFormats.value
  bulkForm.post('/content-repurposer/bulk')
}

function statusBadgeClass(status: string) {
  return {
    queued: 'badge badge-gray',
    transcribing: 'badge badge-blue',
    generating: 'badge badge-blue',
    completed: 'badge badge-green',
    failed: 'badge badge-red',
    partial: 'badge badge-amber',
  }[status] || 'badge badge-gray'
}

function formatBadge(format: string) {
  const labels: Record<string, string> = {
    blog_post: t('Blog'), twitter_thread: t('X'), linkedin_article: t('LinkedIn'),
    email_newsletter: t('Email'), tiktok_script: t('TikTok'),
    podcast_show_notes: t('Show Notes'), key_quotes: t('Quotes'), chapter_markers: t('Chapters'),
  }
  return labels[format] || format
}
</script>

<template>
  <Head :title="t('Content Repurposer')" />

  <div class="mx-auto max-w-7xl px-6 py-8">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Content Repurposer') }}</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ t('Turn videos into blog posts, threads, newsletters, scripts, and more — all at once.') }}
      </p>
    </div>

    <!-- Source type tabs -->
    <div class="mb-6 flex gap-2">
      <button
        v-for="tab in [
          { key: 'youtube_url', label: t('🎬 YouTube URL'), icon: 'ti-brand-youtube' },
          { key: 'file_upload', label: t('📁 Upload File'), icon: 'ti-upload' },
          { key: 'text_paste', label: t('📝 Paste Text'), icon: 'ti-typography' },
        ]"
        :key="tab.key"
        @click="sourceType = tab.key as any"
        :class="[
          'inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition',
          sourceType === tab.key
            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300'
            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400',
        ]"
      >
        <i :class="['ti', tab.icon, 'text-base']" />
        {{ tab.label }}
      </button>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
      <!-- YouTube URL -->
      <div v-if="sourceType === 'youtube_url'" class="space-y-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ t('YouTube URL') }}
          </label>
          <input
            v-model="form.source_url"
            type="url"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            :placeholder="t('https://www.youtube.com/watch?v=...')"
            @input="onYouTubeUrlInput(form.source_url)"
          />
        </div>

        <!-- Thumbnail preview -->
        <div v-if="videoId" class="flex items-start gap-4 rounded-xl bg-gray-50 p-4 dark:bg-surface-800">
          <img
            :src="`https://img.youtube.com/vi/${videoId}/mqdefault.jpg`"
            class="h-20 w-36 rounded-lg object-cover"
            alt="Video thumbnail"
          />
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">
              {{ t('Title (optional)') }}
            </label>
            <input
              v-model="form.title"
              type="text"
              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
              :placeholder="t('Custom title override')"
            />
          </div>
        </div>
      </div>

      <!-- File Upload -->
      <div v-if="sourceType === 'file_upload'" class="space-y-4">
        <div
          class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-10 dark:border-surface-600 dark:bg-surface-800/50"
        >
          <i class="ti ti-cloud-upload mb-3 text-3xl text-gray-400" />
          <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">{{ t('Drag & drop a video or audio file') }}</p>
          <p class="mb-4 text-xs text-gray-400">{{ t('MP3, MP4, M4A, WAV, WebM, OGG — max :size MB', { size: repurposer.maxFileMb }) }}</p>
          <input
            type="file"
            accept=".mp3,.mp4,.m4a,.wav,.webm,.ogg"
            class="block text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100 dark:text-gray-400 dark:file:bg-primary-900/20 dark:file:text-primary-300"
            @change="handleFileUpload"
          />
          <p v-if="form.file" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            {{ form.file.name }} ({{ (form.file.size / 1048576).toFixed(1) }} MB)
          </p>
        </div>
      </div>

      <!-- Paste Text -->
      <div v-if="sourceType === 'text_paste'" class="space-y-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ t('Title') }}
          </label>
          <input
            v-model="form.title"
            type="text"
            required
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            :placeholder="t('Give this content a title')"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ t('Content to repurpose') }}
          </label>
          <textarea
            v-model="form.text"
            rows="8"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            :placeholder="t('Paste your transcript, article, or any text to repurpose...')"
          />
          <p class="mt-1 text-xs text-gray-400">{{ form.text.length }} / 20000</p>
        </div>
      </div>

      <!-- Format selector -->
      <div class="mt-6 border-t border-gray-100 pt-6 dark:border-surface-700">
        <div class="mb-3 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Choose formats') }}</h3>
          <div class="flex gap-2">
            <button
              type="button"
              class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
              @click="selectAllFormats"
            >
              {{ t('Select All') }}
            </button>
            <button
              type="button"
              class="text-xs font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
              @click="deselectAllFormats"
            >
              {{ t('Deselect All') }}
            </button>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <button
            v-for="fmt in formatOptions"
            :key="fmt.key"
            type="button"
            @click="toggleFormat(fmt.key)"
            :class="[
              'flex flex-col items-center gap-2 rounded-xl border p-4 text-left transition-all',
              selectedFormats.includes(fmt.key)
                ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500/20 dark:bg-primary-900/20'
                : 'border-gray-200 hover:border-gray-300 dark:border-surface-700 dark:hover:border-surface-600',
            ]"
          >
            <i :class="['ti', fmt.icon, 'text-xl', selectedFormats.includes(fmt.key) ? 'text-primary-500' : 'text-gray-400']" />
            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ fmt.label }}</span>
            <span v-if="fmt.min_words > 0" class="text-[10px] text-gray-400">~{{ fmt.min_words }}+ {{ t('words') }}</span>
          </button>
        </div>
      </div>

      <!-- Submit -->
      <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-4 dark:border-surface-700">
        <span class="text-sm text-gray-500">
          {{ t(':count credits', { count: creditCost }) }}
          ·
          {{ selectedFormats.length }} {{ t('formats selected') }}
        </span>
        <div class="flex gap-3">
          <button
            v-if="sourceType === 'youtube_url'"
            type="button"
            class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400"
            @click="showBulk = !showBulk"
          >
            {{ showBulk ? t('Single Mode') : t('Bulk Mode') }}
          </button>
          <button
            type="button"
            class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-medium disabled:opacity-60"
            :disabled="form.processing || selectedFormats.length === 0"
            @click="submit"
          >
            <i v-if="form.processing" class="ti ti-loader-2 animate-spin" />
            {{ form.processing ? t('Processing...') : t('Repurpose →') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Bulk mode -->
    <div v-if="showBulk" class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
      <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">{{ t('Bulk Repurpose') }}</h3>
      <div class="space-y-4">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ t('YouTube URLs (one per line, max :max)', { max: repurposer.maxBulkItems }) }}
          </label>
          <textarea
            id="bulk-urls"
            rows="5"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            :placeholder="t('https://www.youtube.com/watch?v=...\nhttps://www.youtube.com/watch?v=...')"
          />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ t('Title prefix (optional)') }}
          </label>
          <input
            v-model="bulkForm.title_prefix"
            type="text"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
            :placeholder="t('e.g. Podcast Episode')"
          />
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">
            {{ t(':count credits per URL', { count: bulkCreditCost }) }}
            ·
            {{ selectedFormats.length }} {{ t('formats each') }}
          </span>
          <button
            type="button"
            class="btn-primary inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-medium disabled:opacity-60"
            :disabled="bulkForm.processing || selectedFormats.length === 0"
            @click="submitBulk"
          >
            <i v-if="bulkForm.processing" class="ti ti-loader-2 animate-spin" />
            {{ bulkForm.processing ? t('Processing...') : t('Process All') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Job History -->
    <div class="mt-10">
      <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Job History') }}</h2>

      <div v-if="jobs.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center dark:border-surface-700 dark:bg-surface-800/50">
        <i class="ti ti-refresh mb-3 text-4xl text-gray-300 dark:text-gray-600" />
        <p class="text-lg font-semibold text-gray-500 dark:text-gray-400">{{ t('No repurpose jobs yet') }}</p>
        <p class="mt-1 text-sm text-gray-400">{{ t('Paste a YouTube URL, upload a file, or paste text to get started.') }}</p>
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-gray-200 dark:border-surface-700">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-surface-800">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-gray-500">{{ t('Source') }}</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500">{{ t('Type') }}</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500">{{ t('Status') }}</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500">{{ t('Formats') }}</th>
              <th class="px-4 py-3 text-left font-medium text-gray-500">{{ t('Date') }}</th>
              <th class="px-4 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
            <tr v-for="job in jobs.data" :key="job.ulid" class="bg-white hover:bg-gray-50 dark:bg-surface-900 dark:hover:bg-surface-800/50">
              <td class="px-4 py-3">
                <span class="font-medium text-gray-900 dark:text-white">{{ job.source_label }}</span>
              </td>
              <td class="px-4 py-3">
                <i
                  :class="[
                    'ti text-base',
                    job.source_type === 'youtube_url' ? 'ti-brand-youtube text-red-500' : job.source_type === 'file_upload' ? 'ti-upload text-blue-500' : 'ti-typography text-purple-500',
                  ]"
                />
              </td>
              <td class="px-4 py-3">
                <span :class="statusBadgeClass(job.status)">{{ t(job.status) }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="fmt in job.formats_requested"
                    :key="fmt"
                    :class="[
                      'rounded-full px-2 py-0.5 text-[10px] font-medium',
                      (job.formats_completed || []).includes(fmt) ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
                    ]"
                  >
                    {{ formatBadge(fmt) }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-500">
                {{ new Date(job.created_at).toLocaleDateString() }}
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  v-if="job.status === 'completed' || job.status === 'partial'"
                  :href="'/content-repurposer/' + job.ulid"
                  class="text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                >
                  {{ t('View Results →') }}
                </Link>
                <span v-else-if="job.status === 'queued' || job.status === 'transcribing' || job.status === 'generating'" class="text-sm text-gray-400">
                  {{ job.progress_percent }}%
                </span>
                <span v-else class="text-sm text-red-400">{{ t('Failed') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
