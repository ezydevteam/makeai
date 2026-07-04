<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import DOMPurify from 'dompurify'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()
const page = usePage()

// Sanitize AI output before it reaches v-html (strips scripts / event handlers).
function renderContent(text: string): string {
    return DOMPurify.sanitize((text ?? '').replace(/\n/g, '<br>'), {
        FORBID_TAGS: ['style', 'form', 'input', 'button'],
        FORBID_ATTR: ['style'],
    })
}

interface Output {
  ulid: string
  format: string
  content: string
  word_count: number
  is_saved: boolean
  format_label: string
  format_icon: string
  metadata: Record<string, any> | null
}

const props = defineProps<{
  job: {
    ulid: string
    id: number
    status: string
    source_type: string
    source_url: string | null
    source_title: string | null
    source_label: string
    transcript: string | null
    word_count: number | null
    duration_seconds: number | null
    formats_requested: string[]
    formats_completed: string[]
    progress_percent: number
    error_message: string | null
    is_youtube: boolean
    outputs: Output[]
    credits_deducted: number
    created_at: string
  }
}>()

const job = ref(props.job)
const activeFormat = ref<string>(job.value.formats_completed[0] || job.value.formats_requested[0])
const showTranscript = ref(false)
const copied = ref<string | null>(null)
const regenerating = ref<string | null>(null)

const currentOutput = computed(() => {
  return job.value.outputs.find((o: Output) => o.format === activeFormat.value)
})

const videoId = computed(() => {
  if (!job.value.source_url) return null
  const m = job.value.source_url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/)
  return m ? m[1] : null
})

const renderOutputs = computed(() => {
  return job.value.outputs.filter((o: Output) => job.value.formats_completed.includes(o.format))
})

function formatDuration(seconds: number | null): string {
  if (!seconds) return ''
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  return h > 0 ? `${h}h ${m}m ${s}s` : `${m}m ${s}s`
}

function copyContent(content: string, label: string) {
  navigator.clipboard.writeText(content)
  copied.value = label
  setTimeout(() => { copied.value = null }, 2000)
}

async function regenerateFormat(format: string) {
  regenerating.value = format
  try {
    const resp = await fetch(`/content-repurposer/${job.value.ulid}/regenerate/${format}`, { method: 'POST' })
    const data = await resp.json()
    if (data.success) {
      const idx = job.value.outputs.findIndex((o: Output) => o.format === format)
      if (idx !== -1) {
        job.value.outputs[idx].content = data.content
      }
    }
  } finally {
    regenerating.value = null
  }
}

async function saveToBlog() {
  const output = currentOutput.value
  if (!output) return
  try {
    await fetch(`/content-repurposer/outputs/${output.ulid}/save-blog`, { method: 'POST' })
    output.is_saved = true
  } catch {}
}

function parseTweets(content: string): string[] {
  return content
    .split(/\n(?=\d+\/)/)
    .map((t: string) => t.trim())
    .filter(Boolean)
}

function parseNewsletterContent(content: string) {
  const lines = content.split('\n')
  let subjects: string[] = []
  let body = content
  const subjectMatch = content.match(/Subject [A-C]:\s*(.+)/gi)
  if (subjectMatch) {
    subjects = subjectMatch.map((s: string) => s.replace(/Subject [A-C]:\s*/i, '').trim())
    body = content.replace(/Subject [A-C]:.+\n/gi, '').trim()
  }
  return { subjects, body }
}

function parseTikTokSections(content: string) {
  const hookMatch = content.match(/HOOK[:\s]*(.+?)(?=\nBODY|\nCTA|$)/is)
  const bodyMatch = content.match(/BODY[:\s]*(.+?)(?=\nCTA|$)/is)
  const ctaMatch = content.match(/CTA[:\s]*(.+)/is)
  return {
    hook: hookMatch ? hookMatch[1].trim() : '',
    body: bodyMatch ? bodyMatch[1].trim() : '',
    cta: ctaMatch ? ctaMatch[1].trim() : '',
  }
}

// Poll progress for queued/processing jobs
let pollInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  if (job.value.status === 'queued' || job.value.status === 'transcribing' || job.value.status === 'generating') {
    pollInterval = setInterval(async () => {
      try {
        const resp = await fetch(`/content-repurposer/${job.value.ulid}/status`)
        const data = await resp.json()
        job.value.status = data.status
        job.value.progress_percent = data.progress_percent
        job.value.formats_completed = data.formats_completed
        if (data.status === 'completed' || data.status === 'partial' || data.status === 'failed') {
          if (pollInterval) clearInterval(pollInterval)
          if (data.status !== 'failed') {
            window.location.reload()
          }
        }
      } catch {}
    }, 3000)
  }
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<template>
  <Head :title="job.source_title || t('Repurpose Results')" />

  <div class="mx-auto max-w-7xl px-6 py-8">
    <!-- Back link -->
    <a
      :href="'/content-repurposer'"
      class="mb-6 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
    >
      <i class="ti ti-arrow-left" />
      {{ t('Back to Repurposer') }}
    </a>

    <!-- Processing state -->
    <div v-if="job.status === 'queued' || job.status === 'transcribing' || job.status === 'generating'" class="rounded-2xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-surface-700 dark:bg-surface-900">
      <i class="ti ti-loader-2 mb-4 animate-spin text-4xl text-primary-500" />
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">
        {{ job.status === 'transcribing' ? t('Transcribing...') : t('Generating content...') }}
      </h2>
      <div class="mx-auto mt-4 h-2 w-64 overflow-hidden rounded-full bg-gray-200 dark:bg-surface-700">
        <div
          class="h-full rounded-full bg-primary-500 transition-all duration-500"
          :style="{ width: job.progress_percent + '%' }"
        />
      </div>
      <p class="mt-2 text-sm text-gray-500">{{ job.progress_percent }}%</p>
    </div>

    <!-- Failed state -->
    <div v-else-if="job.status === 'failed'" class="rounded-2xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-800 dark:bg-red-900/20">
      <i class="ti ti-exclamation-circle mb-3 text-4xl text-red-500" />
      <h2 class="text-lg font-bold text-red-700 dark:text-red-400">{{ t('Repurpose Failed') }}</h2>
      <p v-if="job.error_message" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ job.error_message }}</p>
      <a
        :href="'/content-repurposer'"
        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600"
      >
        {{ t('Try Again') }}
      </a>
    </div>

    <!-- Results -->
    <div v-else class="grid gap-6 lg:grid-cols-[1fr_300px]">
      <!-- Main content -->
      <div class="space-y-6">
        <!-- Source info card -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
          <div class="flex items-start gap-4">
            <img
              v-if="videoId"
              :src="`https://img.youtube.com/vi/${videoId}/mqdefault.jpg`"
              class="h-20 w-36 shrink-0 rounded-lg object-cover"
              alt="Video thumbnail"
            />
            <i v-else-if="job.source_type === 'file_upload'" class="ti ti-file-music shrink-0 text-4xl text-blue-500" />
            <i v-else class="ti ti-typography shrink-0 text-4xl text-purple-500" />
            <div class="min-w-0">
              <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ job.source_title || job.source_label }}</h2>
              <div class="mt-1 flex flex-wrap gap-3 text-xs text-gray-500">
                <span v-if="job.word_count">{{ job.word_count.toLocaleString() }} {{ t('words') }}</span>
                <span v-if="job.duration_seconds">{{ formatDuration(job.duration_seconds) }}</span>
                <span>{{ job.credits_deducted }} {{ t('credits') }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Partial warning -->
        <div
          v-if="job.status === 'partial' && job.formats_completed.length < job.formats_requested.length"
          class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-400"
        >
          {{ t('Some formats failed to generate. You can retry individual formats below.') }}
        </div>

        <!-- Format tabs -->
        <div class="flex flex-wrap gap-2">
          <button
            v-for="fmt in job.formats_requested"
            :key="fmt"
            @click="activeFormat = fmt"
            :class="[
              'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition',
              activeFormat === fmt
                ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300'
                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400',
            ]"
          >
            <i :class="['ti', currentOutput?.format_icon || 'ti-file', 'text-base']" />
            {{ currentOutput?.format_label || fmt }}
            <span
              v-if="job.formats_completed.includes(fmt)"
              class="ml-1 rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] text-green-700 dark:bg-green-900/30 dark:text-green-400"
            >
              ✓
            </span>
          </button>
        </div>

        <!-- Format content -->
        <div v-if="currentOutput" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
          <!-- Blog Post -->
          <template v-if="activeFormat === 'blog_post'">
            <div class="mb-4 flex items-center justify-between">
              <span class="text-xs text-gray-500">
                {{ currentOutput.word_count?.toLocaleString() }} {{ t('words') }}
                ·
                {{ currentOutput.metadata?.reading_time }} {{ t('min read') }}
              </span>
              <div class="flex gap-2">
                <button
                  class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                  @click="copyContent(currentOutput.content, 'blog_md')"
                >
                  {{ copied === 'blog_md' ? t('Copied!') : t('Copy Markdown') }}
                </button>
                <button
                  class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                  @click="copyContent(currentOutput.content, 'blog_html')"
                >
                  {{ copied === 'blog_html' ? t('Copied!') : t('Copy HTML') }}
                </button>
                <button
                  v-if="!currentOutput.is_saved"
                  class="rounded-lg bg-primary-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-600"
                  @click="saveToBlog"
                >
                  {{ t('Save to Blog') }}
                </button>
                <span v-else class="text-xs text-green-600">{{ t('Saved') }} ✓</span>
              </div>
            </div>
            <div class="prose prose-sm max-w-none dark:prose-invert" v-html="renderContent(currentOutput.content)" />
          </template>

          <!-- Twitter Thread -->
          <template v-else-if="activeFormat === 'twitter_thread'">
            <div class="mb-4 flex items-center justify-between">
              <span class="text-xs text-gray-500">{{ currentOutput.metadata?.tweet_count || parseTweets(currentOutput.content).length }} {{ t('tweets') }}</span>
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'thread')"
              >
                {{ copied === 'thread' ? t('Copied!') : t('Copy Thread') }}
              </button>
            </div>
            <div class="space-y-4">
              <div
                v-for="(tweet, i) in parseTweets(currentOutput.content)"
                :key="i"
                class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800"
              >
                <p class="text-sm text-gray-900 dark:text-white">{{ tweet }}</p>
                <div class="mt-2 flex items-center justify-between text-[11px] text-gray-400">
                  <span>{{ i + 1 }}/{{ parseTweets(currentOutput.content).length }}</span>
                  <span>{{ tweet.length }}/280</span>
                </div>
              </div>
            </div>
          </template>

          <!-- LinkedIn Article -->
          <template v-else-if="activeFormat === 'linkedin_article'">
            <div class="mb-4 flex justify-end">
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'linkedin')"
              >
                {{ copied === 'linkedin' ? t('Copied!') : t('Copy') }}
              </button>
            </div>
            <div class="prose prose-sm max-w-none dark:prose-invert" v-text="currentOutput.content" />
          </template>

          <!-- Email Newsletter -->
          <template v-else-if="activeFormat === 'email_newsletter'">
            <div class="mb-4 flex justify-end">
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'newsletter')"
              >
                {{ copied === 'newsletter' ? t('Copied!') : t('Copy All') }}
              </button>
            </div>
            <div v-if="parseNewsletterContent(currentOutput.content).subjects.length" class="mb-6 space-y-2">
              <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Subject Lines') }}</h3>
              <div
                v-for="(subj, i) in parseNewsletterContent(currentOutput.content).subjects"
                :key="i"
                class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800"
              >
                {{ subj }}
              </div>
            </div>
            <div class="prose prose-sm max-w-none dark:prose-invert" v-text="parseNewsletterContent(currentOutput.content).body" />
          </template>

          <!-- TikTok Script -->
          <template v-else-if="activeFormat === 'tiktok_script'">
            <div class="mb-4 flex justify-end">
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'tiktok')"
              >
                {{ copied === 'tiktok' ? t('Copied!') : t('Copy Script') }}
              </button>
            </div>
            <div class="space-y-4">
              <div class="rounded-xl border-l-4 border-red-500 bg-red-50 p-4 dark:bg-red-900/10">
                <p class="text-xs font-semibold text-red-600 uppercase">{{ t('Hook (3s)') }}</p>
                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ parseTikTokSections(currentOutput.content).hook }}</p>
              </div>
              <div class="rounded-xl border-l-4 border-blue-500 bg-blue-50 p-4 dark:bg-blue-900/10">
                <p class="text-xs font-semibold text-blue-600 uppercase">{{ t('Body') }}</p>
                <p class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-white">{{ parseTikTokSections(currentOutput.content).body }}</p>
              </div>
              <div class="rounded-xl border-l-4 border-green-500 bg-green-50 p-4 dark:bg-green-900/10">
                <p class="text-xs font-semibold text-green-600 uppercase">{{ t('CTA') }}</p>
                <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ parseTikTokSections(currentOutput.content).cta }}</p>
              </div>
            </div>
          </template>

          <!-- Podcast Show Notes -->
          <template v-else-if="activeFormat === 'podcast_show_notes'">
            <div class="mb-4 flex justify-end">
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'show_notes')"
              >
                {{ copied === 'show_notes' ? t('Copied!') : t('Copy') }}
              </button>
            </div>
            <div class="prose prose-sm max-w-none dark:prose-invert" v-text="currentOutput.content" />
          </template>

          <!-- Key Quotes -->
          <template v-else-if="activeFormat === 'key_quotes'">
            <div class="mb-4 flex justify-end">
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'quotes')"
              >
                {{ copied === 'quotes' ? t('Copied!') : t('Copy All') }}
              </button>
            </div>
            <div class="space-y-3">
              <div
                v-for="(quote, i) in currentOutput.content.split('\n').filter(l => l.trim())"
                :key="i"
                class="group rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800"
              >
                <p class="text-sm italic text-gray-900 dark:text-white">"{{ quote.replace(/^["']|["']$/g, '') }}"</p>
                <button
                  class="mt-2 text-[11px] text-gray-400 opacity-0 transition group-hover:opacity-100 hover:text-primary-500"
                  @click="copyContent(quote, 'quote_' + i)"
                >
                  {{ copied === 'quote_' + i ? t('Copied!') : t('Copy Quote') }}
                </button>
              </div>
            </div>
          </template>

          <!-- Chapter Markers -->
          <template v-else-if="activeFormat === 'chapter_markers'">
            <div class="mb-4 flex justify-end">
              <button
                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400"
                @click="copyContent(currentOutput.content, 'chapters')"
              >
                {{ copied === 'chapters' ? t('Copied!') : t('Copy for YouTube Description') }}
              </button>
            </div>
            <div class="space-y-2">
              <div
                v-for="(line, i) in currentOutput.content.split('\n').filter(l => l.trim())"
                :key="i"
                class="flex items-center gap-3 rounded-lg bg-gray-50 px-4 py-2 text-sm dark:bg-surface-800"
              >
                <code class="rounded bg-gray-200 px-2 py-0.5 text-xs font-mono dark:bg-surface-700">{{ line.split(' - ')[0] }}</code>
                <span class="text-gray-900 dark:text-white">{{ line.replace(/^\S+\s*-\s*/, '') }}</span>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Right sidebar -->
      <div class="space-y-4">
        <!-- Transcript -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900">
          <button
            class="flex w-full items-center justify-between px-5 py-3 text-sm font-semibold text-gray-900 dark:text-white"
            @click="showTranscript = !showTranscript"
          >
            {{ t('Transcript') }}
            <i :class="['ti text-gray-400 transition', showTranscript ? 'ti-chevron-up' : 'ti-chevron-down']" />
          </button>
          <div v-if="showTranscript && job.transcript" class="border-t border-gray-100 px-5 py-4 dark:border-surface-700">
            <p class="max-h-96 overflow-y-auto text-xs leading-relaxed text-gray-600 dark:text-gray-400">
              {{ job.transcript }}
            </p>
          </div>
        </div>

        <!-- Regenerate -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900">
          <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">{{ t('Regenerate Format') }}</h3>
          <div class="space-y-2">
            <button
              v-for="fmt in job.formats_requested"
              :key="fmt"
              :disabled="regenerating === fmt"
              class="flex w-full items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-surface-700 dark:text-gray-400 dark:hover:bg-surface-800"
              @click="regenerateFormat(fmt)"
            >
              <i v-if="regenerating === fmt" class="ti ti-loader-2 animate-spin text-primary-500" />
              <i v-else class="ti ti-refresh text-gray-400" />
              {{ t('Regenerate :format', { format: renderOutputs.find(o => o.format === fmt)?.format_label || fmt }) }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
