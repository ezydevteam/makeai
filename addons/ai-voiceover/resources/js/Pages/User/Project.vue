<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()
const toast = useToastr()
const page = usePage()
const voiceover = (page.props.voiceover as any) || {}

interface Voice {
  id: number
  provider_voice_id: string
  name: string
  gender: string | null
  language: string
  accent: string | null
  preview_url: string | null
  is_cloned: boolean
}

interface Segment {
  speaker: string
  text: string
  voice_id: string | null
  provider: string | null
}

interface Episode {
  id: number
  ulid: string
  title: string
  status: string
  duration_label: string
  duration_seconds: number | null
  file_url: string | null
  waveform_url: string | null
  transcript_vtt: string | null
  segments: Segment[] | null
  share_enabled: boolean
  share_token: string | null
  is_published: boolean
  can_retry: boolean
  error_message: string | null
  provider: string | null
  voice_id: string | null
  episode_number: number | null
  season_number: number | null
  created_at: string
}

interface MusicTrack {
  id: number
  name: string
  duration_seconds: number | null
}

interface Project {
  id: number
  ulid: string
  title: string
  type: 'voiceover' | 'podcast'
  description: string | null
  cover_art_url: string | null
  rss_url: string
  rss_enabled: boolean
  podcast_author: string | null
  podcast_category: string | null
  podcast_language: string
  podcast_explicit: boolean
  total_duration: number
  episode_count: number
  episodes_count: number
}

const props = defineProps<{
  project: Project
  episodes: { data: Episode[]; current_page: number; last_page: number }
  voices: Voice[]
  musicLibrary: MusicTrack[]
  configuredProviders: string[]
  defaultProvider: string
}>()

const creditsPerKChars = voiceover.creditsPerKChars || 5
const maxScriptChars = voiceover.maxScriptChars || 5000
const musicMaxSizeMb = voiceover.musicMaxSizeMb || 10

// Episode creation state
const showCreator = ref(false)
const speakerMode = ref<'single' | 'multi'>('single')
const segments = ref<Segment[]>([{ speaker: 'Speaker A', text: '', voice_id: null, provider: null }])
const selectedProvider = ref(props.defaultProvider)
const selectedMusicId = ref<number | null>(null)
const musicVolume = ref(0.3)
const musicFile = ref<File | null>(null)
const showMusicUpload = ref(false)

// Auto-split state
const autoSplitScript = ref('')
const showAutoSplit = ref(false)

// Episode form
const episodeForm = useForm({
  title: '',
  script: '',
  segments: null as Segment[] | null,
  provider: '',
  voice_id: '',
  music_track_id: null as number | null,
  music_volume: 0.3,
  episode_number: null as number | null,
  season_number: null as number | null,
})

// Playing state
const playingEpisodeUlid = ref<string | null>(null)
const showTranscript = ref<string | null>(null)

// Polling
const pollingEpisodes = ref<Set<string>>(new Set())
let pollInterval: ReturnType<typeof setInterval> | null = null

const filteredVoices = computed(() => props.voices)

const scriptCharCount = computed(() => episodeForm.script?.length || 0)
const estimatedCredits = computed(() => {
  let chars = scriptCharCount.value
  if (speakerMode.value === 'multi' && !autoSplitScript.value) {
    chars = segments.value.reduce((sum, s) => sum + (s.text?.length || 0), 0)
  }
  return Math.ceil(chars / 1000) * creditsPerKChars
})

const rssUrl = computed(() => props.project.rss_url)

// Provider helpers
const isElevenLabs = computed(() => selectedProvider.value === 'elevenlabs')
const isOpenAi = computed(() => selectedProvider.value === 'openai')

function addSegment() {
  const label = String.fromCharCode(65 + segments.value.length)
  segments.value.push({ speaker: `Speaker ${label}`, text: '', voice_id: null, provider: null })
}

function removeSegment(index: number) {
  if (segments.value.length > 1) {
    segments.value.splice(index, 1)
  }
}

function selectVoiceForSegment(index: number, voiceId: string) {
  segments.value[index].voice_id = voiceId
  segments.value[index].provider = selectedProvider.value
}

function selectVoice(voiceId: string) {
  episodeForm.voice_id = voiceId
}

function autoDetectSpeakers() {
  if (!autoSplitScript.value.trim()) return

  axios.post(route('addon.vo.user.auto-split'), { script: autoSplitScript.value })
    .then(res => {
      if (res.data.segments?.length) {
        segments.value = res.data.segments.map((s: any) => ({
          speaker: s.speaker || 'Speaker',
          text: s.text || '',
          voice_id: s.voice_id || null,
          provider: s.provider || null,
        }))
        autoSplitScript.value = ''
        showAutoSplit.value = false
        toast.success(t('Speakers detected!'))
      } else {
        toast.error(t('No speakers detected in script.'))
      }
    })
    .catch(() => toast.error(t('Failed to auto-detect speakers.')))
}

function generateEpisode() {
  const payload: Record<string, any> = {
    title: episodeForm.title,
    provider: selectedProvider.value,
    voice_id: episodeForm.voice_id,
    music_track_id: selectedMusicId.value,
    music_volume: musicVolume.value,
    episode_number: episodeForm.episode_number,
    season_number: episodeForm.season_number,
  }

  if (speakerMode.value === 'single') {
    payload.script = episodeForm.script
    payload.segments = null
  } else {
    payload.segments = segments.value.filter(s => s.text.trim())
  }

  episodeForm.transform(() => payload).post(
    route('addon.vo.user.episodes.store', { project: props.project.ulid }),
    {
      preserveScroll: true,
      onSuccess: () => {
        episodeForm.reset()
        segments.value = [{ speaker: 'Speaker A', text: '', voice_id: null, provider: null }]
        showCreator.value = false
        router.reload({ only: ['episodes'] })
      },
    },
  )
}

function startPolling(ulid: string) {
  pollingEpisodes.value = new Set([...pollingEpisodes.value, ulid])
  if (!pollInterval) {
    pollInterval = setInterval(pollStatuses, 3000)
  }
}

function pollStatuses() {
  const ulids = [...pollingEpisodes.value]
  if (!ulids.length) {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
    return
  }

  ulids.forEach(ulid => {
    axios.get(route('addon.vo.api.episodes.status', { episode: ulid }))
      .then(res => {
        if (res.data.status === 'completed' || res.data.status === 'failed') {
          pollingEpisodes.value.delete(ulid)
          router.reload({ only: ['episodes'] })
          if (res.data.status === 'completed') {
            toast.success(t('Episode generated!'))
          } else {
            toast.error(t('Generation failed.'))
          }
        }
      })
      .catch(() => {
        pollingEpisodes.value.delete(ulid)
      })
  })

  if (!pollingEpisodes.value.size && pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

function transcribe(episode: Episode) {
  router.post(
    route('addon.vo.user.episodes.transcribe', { episode: episode.ulid }),
    {},
    { preserveScroll: true, onSuccess: () => toast.success(t('Transcription queued.')) },
  )
}

function toggleShare(episode: Episode) {
  router.post(
    route('addon.vo.user.episodes.share', { episode: episode.ulid }),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        if (episode.share_enabled && episode.share_token) {
          navigator.clipboard?.writeText(route('addon.vo.public.player', { token: episode.share_token }))
          toast.success(t('Share link copied!'))
        }
      },
    },
  )
}

function publishEpisode(episode: Episode) {
  router.post(
    route('addon.vo.user.episodes.publish', { episode: episode.ulid }),
    {},
    { preserveScroll: true, onSuccess: () => toast.success(t('Episode published to RSS.')) },
  )
}

function unpublishEpisode(episode: Episode) {
  router.post(
    route('addon.vo.user.episodes.unpublish', { episode: episode.ulid }),
    {},
    { preserveScroll: true, onSuccess: () => toast.success(t('Episode unpublished.')) },
  )
}

function deleteEpisode(episode: Episode) {
  if (!confirm(t('Delete this episode? This cannot be undone.'))) return
  router.delete(
    route('addon.vo.user.episodes.destroy', { episode: episode.ulid }),
    { preserveScroll: true, onSuccess: () => toast.success(t('Episode deleted.')) },
  )
}

function copyRssUrl() {
  navigator.clipboard?.writeText(rssUrl.value)
  toast.success(t('RSS URL copied!'))
}

function uploadMusic() {
  if (!musicFile.value) return
  const form = useForm({ music: musicFile.value, name: musicFile.value.name })
  form.post(route('addon.vo.user.music.upload'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      musicFile.value = null
      showMusicUpload.value = false
      router.reload({ only: ['musicLibrary'] })
    },
  })
}

function onMusicFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const maxSize = musicMaxSizeMb * 1024 * 1024
  if (file.size > maxSize) {
    toast.error(t('File exceeds max size of :size MB.', { size: String(musicMaxSizeMb) }))
    return
  }
  musicFile.value = file
}

function playPreview(voice: Voice) {
  if (voice.preview_url) {
    new Audio(voice.preview_url).play()
  }
}

function stopPlaying() {
  playingEpisodeUlid.value = null
}

function getSegmentCharCount(episode: Episode): string {
  if (!episode.segments?.length) return ''
  const total = episode.segments.reduce((s, seg) => s + (seg.text?.length || 0), 0)
  return `${total} chars`
}

// Polling on mount for existing queued/processing episodes
onMounted(() => {
  props.episodes.data.forEach(ep => {
    if (ep.status === 'queued' || ep.status === 'processing') {
      startPolling(ep.ulid)
    }
  })
})

onUnmounted(() => {
  if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
})

// Project edit form
const showProjectEdit = ref(false)
const projectEditForm = useForm({
  title: props.project.title,
  description: props.project.description || '',
  podcast_author: props.project.podcast_author || '',
  podcast_category: props.project.podcast_category || '',
  podcast_language: props.project.podcast_language || 'en',
  podcast_explicit: props.project.podcast_explicit,
  rss_enabled: props.project.rss_enabled,
  cover_art: null as File | null,
})

function updateProject() {
  projectEditForm.put(
    route('addon.vo.user.projects.update', { project: props.project.ulid }),
    { forceFormData: true, preserveScroll: true, onSuccess: () => { showProjectEdit.value = false; toast.success(t('Project updated.')) } },
  )
}
</script>

<template>
  <Head :title="props.project.title" />

  <div class="mx-auto max-w-7xl px-6 py-8">
    <!-- Project header -->
    <div class="mb-8 rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-gray-900">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="flex h-20 w-20 items-center justify-center rounded-xl bg-gradient-to-br from-primary-50 to-purple-50 dark:from-primary-900/20 dark:to-purple-900/20">
            <i :class="props.project.type === 'podcast' ? 'ti ti-podcast' : 'ti ti-microphone'" class="text-3xl text-primary-500"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ props.project.title }}</h1>
              <span class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                {{ props.project.type === 'podcast' ? t('Podcast') : t('Voiceover') }}
              </span>
            </div>
            <p v-if="props.project.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ props.project.description }}</p>
            <div class="mt-2 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
              <span>{{ props.episodes.data.length }} {{ t('episodes') }}</span>
            </div>
            <!-- RSS badge -->
            <div v-if="props.project.type === 'podcast'" class="mt-3 flex items-center gap-2">
              <span v-if="props.project.rss_enabled" class="inline-flex items-center gap-1 rounded-lg border border-green-200 bg-green-50 px-2 py-1 text-xs text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                <i class="ti ti-rss"></i> RSS {{ t('Enabled') }}
              </span>
              <button @click="copyRssUrl" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <i class="ti ti-copy"></i> {{ t('Copy RSS URL') }}
              </button>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button @click="showCreator = !showCreator" class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium">
            <i class="ti ti-plus"></i>
            {{ t('New Episode') }}
          </button>
          <button
            @click="showProjectEdit = true"
            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
          >
            <i class="ti ti-pencil"></i>
            {{ t('Edit') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Create Episode panel -->
    <div v-if="showCreator" class="mb-8 rounded-2xl border border-primary-200 bg-primary-50/50 p-6 dark:border-primary-800 dark:bg-primary-900/10">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Create Episode') }}</h2>
        <button @click="showCreator = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-800">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <form @submit.prevent="generateEpisode" class="space-y-5">
        <!-- Title -->
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Episode Title') }}</label>
          <input v-model="episodeForm.title" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Episode title...')" />
        </div>

        <!-- Speaker mode toggle -->
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Speaker Mode') }}</label>
          <div class="flex gap-2">
            <button type="button" @click="speakerMode = 'single'" :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', speakerMode === 'single' ? 'bg-primary-500 text-white' : 'border border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300']">
              <i class="ti ti-user mr-1"></i> {{ t('Single Voice') }}
            </button>
            <button type="button" @click="speakerMode = 'multi'" :class="['rounded-lg px-4 py-2 text-sm font-medium transition-colors', speakerMode === 'multi' ? 'bg-primary-500 text-white' : 'border border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300']">
              <i class="ti ti-users mr-1"></i> {{ t('Multi-Speaker') }}
            </button>
          </div>
        </div>

        <!-- Single voice mode -->
        <template v-if="speakerMode === 'single'">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ t('Script') }}
              <span class="ml-1 text-xs text-gray-400">{{ scriptCharCount }}/{{ maxScriptChars }}</span>
            </label>
            <textarea v-model="episodeForm.script" rows="6" :maxlength="maxScriptChars" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Enter your script here...')"></textarea>
            <div class="mt-1 flex justify-between text-xs">
              <span :class="scriptCharCount > maxScriptChars * 0.9 ? 'text-red-500' : 'text-gray-400'">{{ t(':count/:max characters', { count: String(scriptCharCount), max: String(maxScriptChars) }) }}</span>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Provider') }}</label>
            <select v-model="selectedProvider" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
              <option v-for="p in props.configuredProviders" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>

          <!-- Voice grid -->
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Voice') }}</label>
            <div class="grid max-h-60 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
              <button
                v-for="voice in filteredVoices"
                :key="voice.id"
                type="button"
                @click="selectVoice(voice.provider_voice_id)"
                :class="[
                  'rounded-xl border p-3 text-left transition-colors',
                  episodeForm.voice_id === voice.provider_voice_id
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700',
                ]"
              >
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ voice.name }}</span>
                  <span v-if="voice.gender" class="rounded-full bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ voice.gender }}</span>
                </div>
                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                  <span>{{ voice.language }}</span>
                  <span v-if="voice.accent">· {{ voice.accent }}</span>
                </div>
                <button
                  v-if="voice.preview_url"
                  type="button"
                  @click.stop.prevent="playPreview(voice)"
                  class="mt-2 inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400"
                >
                  <i class="ti ti-player-play-filled"></i> {{ t('Preview') }}
                </button>
                <span v-else class="mt-2 block text-xs text-gray-400">{{ t('No preview') }}</span>
              </button>
            </div>
          </div>

          <!-- OpenAI speed -->
          <div v-if="isOpenAi">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Speed') }}</label>
            <input type="range" min="0.5" max="2" step="0.1" value="1" class="w-full" />
          </div>

          <!-- ElevenLabs stability/similarity -->
          <template v-if="isElevenLabs">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Stability') }}</label>
              <input type="range" min="0" max="1" step="0.05" value="0.5" class="w-full" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Similarity Boost') }}</label>
              <input type="range" min="0" max="1" step="0.05" value="0.75" class="w-full" />
            </div>
          </template>
        </template>

        <!-- Multi-speaker mode -->
        <template v-if="speakerMode === 'multi'">
          <div class="flex items-center gap-3">
            <button type="button" @click="showAutoSplit = !showAutoSplit" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
              <i class="ti ti-wand"></i> {{ t('Auto-detect Speakers') }}
            </button>
            <button type="button" @click="addSegment" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">
              <i class="ti ti-plus"></i> {{ t('Add Speaker') }}
            </button>
          </div>

          <!-- Auto-split panel -->
          <div v-if="showAutoSplit">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Paste full script') }}</label>
            <textarea v-model="autoSplitScript" rows="5" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Speaker A: Hello, welcome!\nSpeaker B: Thanks for having me...')"></textarea>
            <button type="button" @click="autoDetectSpeakers" class="mt-2 btn-primary inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm">
              <i class="ti ti-wand"></i> {{ t('Detect Speakers') }}
            </button>
          </div>

          <!-- Segment list -->
          <div class="space-y-4">
            <div v-for="(seg, i) in segments" :key="i" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
              <div class="mb-2 flex items-center justify-between">
                <input v-model="seg.speaker" type="text" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Speaker name')" />
                <button v-if="segments.length > 1" type="button" @click="removeSegment(i)" class="rounded p-1 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                  <i class="ti ti-trash"></i>
                </button>
              </div>

              <div class="mb-2">
                <select v-model="seg.provider" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                  <option v-for="p in props.configuredProviders" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>

              <div class="mb-2 flex flex-wrap gap-1">
                <button
                  v-for="voice in filteredVoices"
                  :key="voice.id"
                  type="button"
                  @click="selectVoiceForSegment(i, voice.provider_voice_id)"
                  :class="[
                    'rounded-lg px-2 py-1 text-xs transition-colors',
                    seg.voice_id === voice.provider_voice_id
                      ? 'bg-primary-500 text-white'
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400',
                  ]"
                >
                  {{ voice.name }}
                </button>
              </div>

              <textarea v-model="seg.text" rows="2" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" :placeholder="t('Segment text...')"></textarea>
            </div>
          </div>
        </template>

        <!-- Background music -->
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background Music') }}</label>
          <select v-model="selectedMusicId" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            <option :value="null">{{ t('None') }}</option>
            <option v-for="track in props.musicLibrary" :key="track.id" :value="track.id">{{ track.name }}</option>
          </select>

          <button type="button" @click="showMusicUpload = true" class="mt-2 text-xs text-primary-600 hover:underline dark:text-primary-400">
            {{ t('Upload new music') }}
          </button>

          <div v-if="selectedMusicId" class="mt-2">
            <label class="mb-1 block text-xs text-gray-500">{{ t('Music Volume') }}: {{ Math.round(musicVolume * 100) }}%</label>
            <input v-model.number="musicVolume" type="range" min="0" max="1" step="0.05" class="w-full" />
          </div>

          <!-- Music upload -->
          <div v-if="showMusicUpload" class="mt-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <input type="file" accept="audio/mp3,audio/wav,audio/ogg" @change="onMusicFileChange" />
            <button
              v-if="musicFile"
              type="button"
              @click="uploadMusic"
              class="mt-2 inline-flex items-center gap-1 rounded-lg bg-primary-500 px-3 py-1.5 text-xs text-white"
            >
              {{ t('Upload') }}
            </button>
          </div>
        </div>

        <!-- Credit cost -->
        <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/30">
          <i class="ti ti-coin text-amber-500"></i>
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t(':credits credits', { credits: String(estimatedCredits) }) }}</span>
        </div>

        <div v-if="episodeForm.errors.title" class="text-sm text-red-500">{{ episodeForm.errors.title }}</div>

        <button
          type="submit"
          :disabled="episodeForm.processing"
          class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-medium"
        >
          <i v-if="episodeForm.processing" class="ti ti-loader animate-spin"></i>
          {{ episodeForm.processing ? t('Generating...') : t('Generate Audio') }}
        </button>
      </form>
    </div>

    <!-- Episode list -->
    <div class="space-y-3">
      <div
        v-for="episode in props.episodes.data"
        :key="episode.ulid"
        class="rounded-2xl border border-gray-200 bg-white p-4 transition-colors dark:border-surface-800 dark:bg-gray-900"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <!-- Status icon -->
            <span v-if="episode.status === 'processing' || episode.status === 'queued'" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
              <i class="ti ti-loader animate-spin text-amber-600"></i>
            </span>
            <span v-else-if="episode.status === 'completed'" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
              <i class="ti ti-check text-green-600"></i>
            </span>
            <span v-else-if="episode.status === 'failed'" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
              <i class="ti ti-exclamation-mark text-red-600"></i>
            </span>
            <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
              <i class="ti ti-pencil text-gray-400"></i>
            </span>

            <div>
              <h3 class="font-medium text-gray-900 dark:text-white">{{ episode.title }}</h3>
              <div class="flex items-center gap-2 text-xs text-gray-500">
                <span v-if="episode.segments?.length">{{ episode.segments.length }} {{ t('speakers') }}</span>
                <span>{{ episode.duration_label }}</span>
                <span :class="[
                  'rounded-full px-1.5 py-0.5 text-xs font-medium',
                  episode.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' :
                  episode.status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' :
                  episode.status === 'processing' || episode.status === 'queued' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400' :
                  'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                ]">{{ episode.status }}</span>
                <span v-if="episode.is_published" class="rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">{{ t('Published') }}</span>
              </div>
              <div v-if="episode.error_message" class="mt-1 text-xs text-red-500">{{ episode.error_message }}</div>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="episode.status === 'completed'" class="flex items-center gap-1">
            <button
              @click="playingEpisodeUlid === episode.ulid ? stopPlaying() : (playingEpisodeUlid = episode.ulid)"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
              :title="playingEpisodeUlid === episode.ulid ? t('Stop') : t('Play')"
            >
              <i :class="playingEpisodeUlid === episode.ulid ? 'ti ti-player-stop-filled' : 'ti ti-player-play-filled'"></i>
            </button>

            <a
              v-if="episode.file_url"
              :href="route('addon.vo.user.episodes.download', { episode: episode.ulid })"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
              :title="t('Download')"
            >
              <i class="ti ti-download"></i>
            </a>

            <button
              @click="showTranscript === episode.ulid ? (showTranscript = null) : (showTranscript = episode.ulid)"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
              :title="t('Transcript')"
            >
              <i class="ti ti-file-text"></i>
            </button>

            <button
              @click="transcribe(episode)"
              v-if="!episode.transcript_vtt"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
              :title="t('Transcribe')"
            >
              <i class="ti ti-microphone-2"></i>
            </button>

            <button
              @click="toggleShare(episode)"
              :class="[
                'rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800',
                episode.share_enabled ? 'text-primary-500' : 'text-gray-500',
              ]"
              :title="t('Share')"
            >
              <i class="ti ti-share"></i>
            </button>

            <button
              v-if="props.project.type === 'podcast' && !episode.is_published"
              @click="publishEpisode(episode)"
              class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
              :title="t('Publish')"
            >
              <i class="ti ti-rss"></i>
            </button>

            <button
              v-if="episode.is_published"
              @click="unpublishEpisode(episode)"
              class="rounded-lg p-2 text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20"
              :title="t('Unpublish')"
            >
              <i class="ti ti-rss-off"></i>
            </button>

            <button
              @click="deleteEpisode(episode)"
              class="rounded-lg p-2 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
              :title="t('Delete')"
            >
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </div>

        <!-- Audio player -->
        <div v-if="playingEpisodeUlid === episode.ulid && episode.file_url" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
          <div v-if="episode.waveform_url" class="mb-3 overflow-hidden rounded-lg">
            <img :src="episode.waveform_url" alt="waveform" class="h-10 w-full object-cover" />
          </div>
          <audio :src="episode.file_url" controls autoplay class="w-full"></audio>
        </div>

        <!-- Transcript -->
        <div v-if="showTranscript === episode.ulid && episode.transcript_vtt" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
          <pre class="whitespace-pre-wrap text-xs text-gray-600 dark:text-gray-400">{{ episode.transcript_vtt }}</pre>
        </div>
      </div>

      <!-- Empty episode state -->
      <div v-if="props.episodes.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center dark:border-gray-700 dark:bg-gray-800/50">
        <i class="ti ti-headphones text-4xl text-gray-400 dark:text-gray-500"></i>
        <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('No episodes yet') }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create your first episode above.') }}</p>
      </div>
    </div>
  </div>

  <!-- Edit Project Modal -->
  <Teleport to="body">
    <div v-if="showProjectEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showProjectEdit = false">
      <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-surface-800 dark:bg-gray-900">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Edit Project') }}</h2>
          <button @click="showProjectEdit = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
            <i class="ti ti-x"></i>
          </button>
        </div>

        <form @submit.prevent="updateProject" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</label>
            <input v-model="projectEditForm.title" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
            <textarea v-model="projectEditForm.description" rows="3" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
          </div>

          <template v-if="props.project.type === 'podcast'">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Author') }}</label>
              <input v-model="projectEditForm.podcast_author" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Category') }}</label>
              <input v-model="projectEditForm.podcast_category" type="text" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>
            <label class="flex items-center gap-2">
              <input v-model="projectEditForm.rss_enabled" type="checkbox" class="rounded border-gray-300" />
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Enable RSS Feed') }}</span>
            </label>
          </template>

          <div class="flex justify-end gap-3">
            <button type="button" @click="showProjectEdit = false" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
              {{ t('Cancel') }}
            </button>
            <button type="submit" :disabled="projectEditForm.processing" class="btn-primary rounded-xl px-4 py-2.5 text-sm font-medium">
              {{ projectEditForm.processing ? t('Saving...') : t('Save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
