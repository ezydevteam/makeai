<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { sanitizeErrorMessage } from '@/Composables/useErrorSanitizer'

const props = defineProps<{
    sessionId: string | null
    uploadProgress?: number
    sourceType?: 'file' | 'url' | 'youtube' | 'collection'
}>()
const emit = defineEmits<{ complete: [] }>()
const { t } = useTranslate()

const progress = ref(0)
const stage = ref('uploading')
const status = ref('ingesting')
const error = ref<string | null>(null)
const sourceMeta = ref<Record<string, unknown> | null>(null)
let pollInterval: ReturnType<typeof setInterval> | null = null
let smoothInterval: ReturnType<typeof setInterval> | null = null
let targetProgress = 0

// Map each backend stage to a deterministic progress value
const stageProgressMap: Record<string, number> = {
    uploading: 15,
    extracting: 35,
    scraping: 35,
    fetching_captions: 25,
    transcribing: 40,
    chunking: 55,
    embedding: 75,
    ready: 100,
    failed: 0,
}

const stageLabel = computed(() => {
    let uploadLabel = t('Uploading...')
    let uploadIcon = 'ti ti-upload'

    if (props.sourceType === 'youtube') {
        uploadLabel = t('Fetching video...')
        uploadIcon = 'ti ti-brand-youtube'
    } else if (props.sourceType === 'url') {
        uploadLabel = t('Analyzing...')
        uploadIcon = 'ti ti-world-download'
    } else if (props.sourceType === 'collection') {
        uploadLabel = t('Connecting...')
        uploadIcon = 'ti ti-database'
    }

    const map: Record<string, { label: string; icon: string }> = {
        uploading: { label: uploadLabel, icon: uploadIcon },
        extracting: { label: t('Extracting text...'), icon: 'ti ti-file-text' },
        scraping: { label: t('Scraping page...'), icon: 'ti ti-world-download' },
        fetching_captions: { label: t('Fetching captions...'), icon: 'ti ti-closed-caption' },
        transcribing: { label: t('Transcribing audio...'), icon: 'ti ti-microphone' },
        chunking: { label: t('Chunking text...'), icon: 'ti ti-section' },
        embedding: { label: t('Generating embeddings...'), icon: 'ti ti-vector' },
        ready: { label: t('Ready!'), icon: 'ti ti-circle-check' },
        failed: { label: t('Failed'), icon: 'ti ti-alert-circle' },
    }
    return map[stage.value] || { label: stage.value, icon: 'ti ti-loader' }
})

onMounted(() => {
    startPolling()
    startSmoothAnimation()
})

onUnmounted(() => {
    stopPolling()
    stopSmoothAnimation()
})

// Watch for sessionId arriving asynchronously
watch(() => props.sessionId, (id) => {
    if (id && !pollInterval) {
        startPolling()
    }
})

// Watch upload progress from parent (real XHR progress)
watch(() => props.uploadProgress, (val) => {
    if (val !== undefined && val > 0 && !props.sessionId) {
        stage.value = 'uploading'
        // Map upload 0-100% to progress 0-15%
        const mapped = Math.round((val / 100) * 15)
        setTarget(mapped)
    }
})

function startPolling() {
    if (props.sessionId) {
        pollInterval = setInterval(pollStatus, 1500)
        pollStatus()
    }
}
function stopPolling() {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
}

/**
 * Smooth animation: ticks every 200ms, nudges progress toward targetProgress.
 * Progress ONLY goes up, never down.
 */
function startSmoothAnimation() {
    smoothInterval = setInterval(() => {
        if (progress.value < targetProgress) {
            progress.value = Math.min(progress.value + 1, targetProgress)
        }
    }, 200)
}
function stopSmoothAnimation() {
    if (smoothInterval) { clearInterval(smoothInterval); smoothInterval = null }
}

/**
 * Set a new target. Target can only increase (never decrease).
 */
function setTarget(val: number) {
    if (val > targetProgress) {
        targetProgress = val
    }
}

async function pollStatus() {
    try {
        const res = await fetch(`/tools/rag/sessions/${props.sessionId}/status`, {
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
        })
        if (!res.ok) return
        const data = await res.json()
        status.value = data.status
        sourceMeta.value = data.source_meta

        // Use the real ingest_stage from backend
        if (data.ingest_stage && data.ingest_stage !== stage.value) {
            stage.value = data.ingest_stage
            const stageTarget = stageProgressMap[data.ingest_stage] ?? 15
            setTarget(stageTarget)
        } else if (data.status === 'ready') {
            stage.value = 'ready'
            setTarget(100)
        } else if (data.status === 'failed') {
            stage.value = 'failed'
        }

        error.value = data.ingest_error ? sanitizeErrorMessage(data.ingest_error) : null

        if (data.status === 'ready') {
            progress.value = 100
            targetProgress = 100
            stopPolling()
            stopSmoothAnimation()
            setTimeout(() => emit('complete'), 600)
        } else if (data.status === 'failed') {
            stopPolling()
            stopSmoothAnimation()
        }
    } catch {}
}

function getCsrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}
</script>

<template>
    <div class="flex items-center justify-center h-full p-8">
        <div class="max-w-sm w-full text-center">
            <!-- Animated icon -->
            <div class="mb-6 relative mx-auto w-28 h-28">
                <!-- Ready state -->
                <div v-if="status === 'ready'" class="w-28 h-28 rounded-full bg-success-500/10 flex items-center justify-center animate-scale-in">
                    <i class="ti ti-circle-check text-5xl text-success-500"></i>
                </div>
                <!-- Failed state -->
                <div v-else-if="status === 'failed'" class="w-28 h-28 rounded-full bg-danger-500/10 flex items-center justify-center">
                    <i class="ti ti-alert-circle text-5xl text-danger-500"></i>
                </div>
                <!-- Progress ring -->
                <div v-else class="w-28 h-28 relative">
                    <svg class="w-28 h-28 -rotate-90" viewBox="0 0 112 112">
                        <circle
                            cx="56" cy="56" r="50"
                            fill="none"
                            stroke-width="6"
                            class="stroke-surface-200"
                        />
                        <circle
                            cx="56" cy="56" r="50"
                            fill="none"
                            stroke-width="6"
                            stroke-linecap="round"
                            class="stroke-primary-500"
                            :stroke-dasharray="2 * Math.PI * 50"
                            :stroke-dashoffset="2 * Math.PI * 50 * (1 - progress / 100)"
                            style="transition: stroke-dashoffset 0.4s ease-out"
                        />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <i :class="stageLabel.icon" class="text-2xl text-primary-500"></i>
                    </div>
                </div>
            </div>

            <!-- Stage label -->
            <h3 class="text-lg font-bold mb-1">{{ stageLabel.label }}</h3>

            <!-- Progress bar -->
            <div class="w-full rounded-full h-2.5 mb-1.5 overflow-hidden bg-surface-200 dark:bg-surface-700">
                <div
                    class="h-full rounded-full bg-primary-500"
                    :class="[
                        status === 'ready' ? '!bg-success-500' : status === 'failed' ? '!bg-danger-500' : ''
                    ]"
                    :style="{ width: progress + '%', transition: 'width 0.4s ease-out' }"
                ></div>
            </div>
            <p class="text-xs text-gray-400 mb-4 font-medium tabular-nums">{{ progress }}%</p>

            <!-- Source meta -->
            <div v-if="sourceMeta" class="text-sm text-gray-500 bg-surface-100 dark:bg-surface-800 rounded-xl px-4 py-2.5 mb-4 max-w-full truncate">
                <span v-if="sourceMeta.filename" class="font-medium">{{ sourceMeta.filename }}</span>
                <span v-if="sourceMeta.url" class="font-medium">{{ sourceMeta.url }}</span>
                <span v-if="sourceMeta.video_title" class="font-medium">{{ sourceMeta.video_title }}</span>
            </div>

            <!-- Error -->
            <div v-if="status === 'failed'" class="bg-danger-500/10 text-danger-500 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
                <i class="ti ti-alert-circle"></i>
                {{ error || t('Ingestion failed. Please try again.') }}
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes scale-in {
    0% { transform: scale(0.6); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.animate-scale-in {
    animation: scale-in 0.4s ease;
}
</style>
