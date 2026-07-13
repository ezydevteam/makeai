<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppModal from '@/Components/UI/AppModal.vue'

const props = defineProps<{
    output: string
    reasoning?: string
    isReasoning?: boolean
    outputType?: string
    loading?: boolean
    usage?: Record<string, unknown> | null
    savedDocument?: Record<string, unknown> | null
    showCreditCosts?: boolean
    canSave?: boolean
    slug: string
    defaultTitle?: string
}>()

const emit = defineEmits<{
    documentSaved: [document: Record<string, unknown>]
}>()

const copied = ref(false)
const saving = ref(false)
const saveMessage = ref('')
const copiedItem = ref<number | null>(null)
const showSaveModal = ref(false)
const saveTitle = ref('')
const showReasoning = ref(false)
const { t } = useTranslate()

const reasoningVerbs = [
    t('Thinking'),
    t('Analyzing prompt'),
    t('Structuring outline'),
    t('Generating response'),
    t('Polishing content')
]
const reasoningIndex = ref(0)
const currentReasoningVerb = computed(() => reasoningVerbs[reasoningIndex.value])
let reasoningInterval: ReturnType<typeof setInterval> | null = null

watch(() => props.loading, (isLoading) => {
    if (isLoading) {
        reasoningIndex.value = 0
        if (reasoningInterval) clearInterval(reasoningInterval)
        reasoningInterval = setInterval(() => {
            reasoningIndex.value = (reasoningIndex.value + 1) % reasoningVerbs.length
        }, 1800)
    } else {
        if (reasoningInterval) {
            clearInterval(reasoningInterval)
            reasoningInterval = null
        }
    }
}, { immediate: true })

onUnmounted(() => {
    if (reasoningInterval) clearInterval(reasoningInterval)
})

const reasoningVisible = computed(() => showReasoning.value || (props.isReasoning && props.reasoning))

const outputKind = computed(() => (props.outputType || 'markdown').toLowerCase())
const words = computed(() => props.output.trim() ? props.output.trim().split(/\s+/).length : 0)
const readingTime = computed(() => Math.max(1, Math.ceil(words.value / 220)))
const isMedia = computed(() => ['image', 'audio', 'video'].includes(outputKind.value))

const escapeHtml = (value: string): string => value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

const parseMediaUrl = (value: string): string => {
    const trimmed = value.trim()
    if (!trimmed) return ''

    try {
        const parsed = JSON.parse(trimmed)
        if (typeof parsed === 'string') return parsed
        return parsed.url || parsed.image_url || parsed.audio_url || parsed.video_url || parsed.src || ''
    } catch {
        const match = trimmed.match(/https?:\/\/[^\s"')]+|data:[^\s"')]+|\/storage\/[^\s"')]+/)
        return match?.[0] || trimmed
    }
}

const mediaUrl = computed(() => parseMediaUrl(props.output))

const listItems = computed(() => {
    const raw = props.output.trim()
    if (!raw) return []

    try {
        const parsed = JSON.parse(raw)
        if (Array.isArray(parsed)) {
            return parsed.map((item) => typeof item === 'string' ? item : JSON.stringify(item))
        }
    } catch {
        // Plain text list output is expected from most LLM tools.
    }

    return raw
        .split('\n')
        .map((line) => line.replace(/^\s*(?:[-*]|\d+[.)])\s*/, '').trim())
        .filter(Boolean)
}
)

const prettyJson = computed(() => {
    try {
        return JSON.stringify(JSON.parse(props.output), null, 2)
    } catch {
        return props.output
    }
})

const highlightedJson = computed(() => escapeHtml(prettyJson.value)
    .replace(/("(?:\\u[\da-fA-F]{4}|\\[^u]|[^\\"])*"\s*:)/g, '<span class="text-primary-300">$1</span>')
    .replace(/(:\s*)("(?:\\u[\da-fA-F]{4}|\\[^u]|[^\\"])*")/g, '$1<span class="text-success-300">$2</span>')
    .replace(/\b(true|false|null)\b/g, '<span class="text-accent-300">$1</span>')
    .replace(/\b(-?\d+(?:\.\d+)?)\b/g, '<span class="text-warning-300">$1</span>'))

const renderedMarkdown = computed(() => {
    const safe = escapeHtml(props.output)

    return safe
        .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
        .replace(/^### (.*)$/gm, '<h3>$1</h3>')
        .replace(/^## (.*)$/gm, '<h2>$1</h2>')
        .replace(/^# (.*)$/gm, '<h1>$1</h1>')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\n/g, '<br>')
})

const copyOutput = async () => {
    await navigator.clipboard.writeText(props.output)
    copied.value = true
    setTimeout(() => copied.value = false, 1800)
}

const copyListItem = async (item: string, index: number) => {
    await navigator.clipboard.writeText(item)
    copiedItem.value = index
    setTimeout(() => copiedItem.value = null, 1800)
}

const fileExtension = computed(() => {
    if (outputKind.value === 'html') return 'html'
    if (outputKind.value === 'code') return 'txt'
    if (outputKind.value === 'json') return 'json'
    if (outputKind.value === 'markdown') return 'md'
    return 'txt'
})

const downloadOutput = () => {
    const blob = new Blob([props.output], { type: 'text/plain' })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `${props.slug}-output.${fileExtension.value}`
    anchor.click()
    URL.revokeObjectURL(url)
}

const openSaveModal = () => {
    saveTitle.value = props.defaultTitle || `${props.slug} ${t('Output')}`
    saveMessage.value = ''
    showSaveModal.value = true
}

const saveDocument = async () => {
    saving.value = true
    saveMessage.value = ''

    try {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        const response = await fetch('/api/v1/documents', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.pop() || '') : '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                slug: props.slug,
                content: props.output,
                title: saveTitle.value || props.defaultTitle || `${props.slug} output`,
            }),
        })

        const data = await response.json()
        if (!response.ok) throw new Error(data.message || t('Save failed.'))
        saveMessage.value = data.message || t('Saved.')
        if (data.data) emit('documentSaved', data.data)
        showSaveModal.value = false
    } catch (error) {
        saveMessage.value = error instanceof Error ? error.message : t('Save failed.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="h-full min-h-[400px] overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-sm dark:border-white/5 dark:bg-white/[0.03] dark:shadow-none flex flex-col">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3 pb-3 border-b border-gray-200 dark:border-white/5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                <i class="ti ti-align-left text-primary-600 dark:text-primary-400"></i> {{ t('Output Result') }}
            </h3>
            <div class="flex flex-wrap items-center gap-2">
                <button v-if="output" type="button" @click="copyOutput" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:!text-gray-400 hover:text-gray-900 dark:hover:!text-white bg-gray-100 dark:!bg-white/5 hover:bg-gray-200 dark:hover:!bg-white/10 rounded-lg transition-all flex items-center gap-1.5">
                    <i :class="copied ? 'ti ti-check text-success-500' : 'ti ti-copy'"></i>
                    {{ copied ? t('Copied') : t('Copy') }}
                </button>
                <button v-if="output && canSave && !savedDocument" type="button" :disabled="saving" @click="openSaveModal" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:!text-gray-400 hover:text-gray-900 dark:hover:!text-white bg-gray-100 dark:!bg-white/5 hover:bg-gray-200 dark:hover:!bg-white/10 rounded-lg transition-all flex items-center gap-1.5 disabled:opacity-50">
                    <i class="ti ti-device-floppy"></i>
                    {{ t('Save') }}
                </button>
                <a v-if="output && isMedia && mediaUrl" :href="mediaUrl" :download="`${slug}-output`" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:!text-gray-400 hover:text-gray-900 dark:hover:!text-white bg-gray-100 dark:!bg-white/5 hover:bg-gray-200 dark:hover:!bg-white/10 rounded-lg transition-all flex items-center gap-1.5">
                    <i class="ti ti-download"></i>
                    {{ t('Download') }}
                </a>
                <button v-else-if="output" type="button" @click="downloadOutput" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:!text-gray-400 hover:text-gray-900 dark:hover:!text-white bg-gray-100 dark:!bg-white/5 hover:bg-gray-200 dark:hover:!bg-white/10 rounded-lg transition-all flex items-center gap-1.5">
                    <i class="ti ti-download"></i>
                    {{ t('Export') }}
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <!-- Reasoning block -->
            <div v-if="reasoning || isReasoning" class="mb-4">
                <button
                    type="button"
                    class="flex items-center gap-2 text-xs font-medium text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 transition-colors w-full px-3 py-2 rounded-lg bg-amber-50 dark:bg-white/[0.02] border border-amber-200 dark:border-white/5 hover:bg-amber-100 dark:hover:bg-white/[0.04]"
                    @click="showReasoning = !showReasoning"
                >
                    <svg v-if="isReasoning" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    <i v-else class="ti ti-brain"></i>
                    <span>{{ isReasoning ? t('Thinking...') : t('Reasoning') }}</span>
                    <i :class="showReasoning ? 'ti-chevron-up' : 'ti-chevron-down'" class="text-xs ml-auto"></i>
                </button>
                <div v-if="reasoningVisible" class="mt-2 rounded-xl border border-amber-500/10 bg-amber-500/[0.03] p-4 text-xs text-amber-700 dark:text-amber-200/80 leading-relaxed whitespace-pre-wrap max-h-60 overflow-y-auto font-mono">
                    {{ reasoning }}
                </div>
            </div>

            <pre v-if="output && outputKind === 'text'" class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap font-mono bg-gray-50 dark:bg-black/20 rounded-xl p-4 overflow-x-auto">{{ output }}</pre>

            <div v-else-if="output && outputKind === 'markdown'" class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300 leading-relaxed" v-html="renderedMarkdown"></div>

            <div v-else-if="output && outputKind === 'html'" class="rounded-xl overflow-hidden border border-gray-300 dark:border-white/10 bg-white">
                <iframe :srcdoc="output" sandbox="" class="w-full min-h-[420px] bg-white"></iframe>
            </div>

            <div v-else-if="output && outputKind === 'code'" class="rounded-xl overflow-hidden border border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-black/20">
                <div class="flex items-center justify-between gap-3 px-4 py-2 border-b border-gray-200 dark:border-white/10 text-xs text-gray-500">
                    <span>{{ t('Code output') }}</span>
                    <button type="button" class="text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white" @click="copyOutput">{{ copied ? t('Copied') : t('Copy code') }}</button>
                </div>
                <pre class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-wrap font-mono p-4 overflow-x-auto">{{ output }}</pre>
            </div>

            <ul v-else-if="output && outputKind === 'list'" class="space-y-3">
                <li v-for="(item, index) in listItems" :key="`${index}-${item}`" class="flex items-start justify-between gap-3 rounded-xl border border-gray-200 dark:border-white/5 bg-gray-50 dark:bg-white/[0.02] px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                    <span class="leading-relaxed">{{ item }}</span>
                    <button type="button" class="shrink-0 text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="copyListItem(item, index)">
                        {{ copiedItem === index ? t('Copied') : t('Copy') }}
                    </button>
                </li>
            </ul>

            <div v-else-if="output && outputKind === 'image'" class="space-y-4">
                <img :src="mediaUrl" :alt="t('Generated image')" class="max-h-[520px] w-full rounded-xl border border-gray-200 dark:border-white/10 object-contain bg-gray-100 dark:bg-black/20" />
                <a :href="mediaUrl" target="_blank" rel="noopener" class="text-sm text-primary-600 dark:text-primary-300 hover:text-primary-700 dark:hover:text-primary-200 break-all">{{ mediaUrl }}</a>
            </div>

            <div v-else-if="output && outputKind === 'audio'" class="space-y-4 rounded-xl border border-gray-200 dark:border-white/5 bg-gray-50 dark:bg-white/[0.02] p-4">
                <audio :src="mediaUrl" controls class="w-full"></audio>
                <a :href="mediaUrl" target="_blank" rel="noopener" class="text-sm text-primary-600 dark:text-primary-300 hover:text-primary-700 dark:hover:text-primary-200 break-all">{{ mediaUrl }}</a>
            </div>

            <div v-else-if="output && outputKind === 'video'" class="space-y-4">
                <video :src="mediaUrl" controls class="max-h-[520px] w-full rounded-xl border border-gray-200 dark:border-white/10 bg-black"></video>
                <a :href="mediaUrl" target="_blank" rel="noopener" class="text-sm text-primary-600 dark:text-primary-300 hover:text-primary-700 dark:hover:text-primary-200 break-all">{{ mediaUrl }}</a>
            </div>

            <pre v-else-if="output && outputKind === 'json'" class="text-sm leading-relaxed whitespace-pre-wrap font-mono bg-gray-50 dark:bg-black/20 rounded-xl p-4 overflow-x-auto text-gray-800 dark:text-gray-200" v-html="highlightedJson"></pre>

            <div v-else-if="output" class="prose dark:prose-invert prose-sm max-w-none text-gray-700 dark:text-gray-300 leading-relaxed" v-html="renderedMarkdown"></div>

            <div v-else-if="loading" class="flex items-center justify-center h-full py-16">
                <div class="text-center">
                    <div class="relative w-16 h-16 mx-auto mb-4">
                        <div class="absolute inset-0 border-2 border-primary-500/20 rounded-full"></div>
                        <div class="absolute inset-0 border-2 border-transparent border-t-primary-500 rounded-full animate-spin"></div>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm animate-pulse select-none">{{ currentReasoningVerb }}...</p>
                </div>
            </div>

            <div v-else class="flex items-center justify-center h-full py-16 opacity-50">
                <div class="text-center">
                    <i class="ti ti-edit text-5xl text-gray-400 dark:text-gray-600 mb-4 block"></i>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">{{ t('Your generated content will appear here') }}</p>
                    <p class="text-gray-400 dark:text-gray-500 text-xs mt-2">{{ t('Fill the form and click Generate') }}</p>
                </div>
            </div>
        </div>

        <div v-if="output" class="mt-4 pt-4 border-t border-gray-200 dark:border-white/5 flex flex-wrap items-center gap-4 text-xs text-gray-500">
            <span>{{ t(':count words', { count: words }) }}</span>
            <span>{{ t(':count min read', { count: readingTime }) }}</span>
            <span v-if="showCreditCosts && usage?.credits_used !== undefined">{{ t('Used :count credits', { count: Number(usage.credits_used) }) }}</span>
            <span v-if="showCreditCosts && usage?.input_tokens !== undefined && usage?.output_tokens !== undefined">
                {{ t(':count tokens', { count: Number(usage.input_tokens || 0) + Number(usage.output_tokens || 0) }) }}
            </span>
            <span v-if="savedDocument" class="text-primary-600 dark:text-primary-300">{{ t('Saved to documents') }}</span>
            <span v-if="saveMessage" class="text-primary-600 dark:text-primary-300">{{ saveMessage }}</span>
        </div>

        <AppModal
            :open="showSaveModal"
            max-width="max-w-md"
            :title="t('Save to Documents')"
            has-form
            :confirm-text="t('Save')"
            :confirm-loading="saving"
            :confirm-disabled="!saveTitle.trim()"
            @close="showSaveModal = false"
            @submit="saveDocument"
        >
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">{{ t('Document title') }}</label>
            <input
                v-model="saveTitle"
                type="text"
                class="w-full rounded-xl border border-gray-300 dark:border-white/10 bg-gray-50 dark:bg-white/[0.03] px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:border-primary-500 focus:ring-primary-500/20"
                :placeholder="t('Document title')"
                required
            />

            <p v-if="saveMessage" class="mt-3 text-xs text-danger-500 dark:text-danger-400">{{ saveMessage }}</p>
        </AppModal>
    </div>
</template>
