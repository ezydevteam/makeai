<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface EmbeddingModel {
    value: string
    label: string
    provider: string
}

interface FallbackEmbedding {
    provider: string
    model: string
    label: string
}

const props = defineProps<{
    settings: {
        max_file_mb: number
        max_pages: number
        top_k: number
        chunk_size: number
        chunk_overlap: number
        embedding_model: string
        system_prompt: string
        ephemeral_retention_days: number
        chunks_per_credit: number
        youtube_whisper_fallback: boolean
        search_mode: string
        chunking_mode: string
        map_reduce_batch_size: number
    }
    embeddingModels: EmbeddingModel[]
    fallbackEmbedding: FallbackEmbedding
}>()

const form = useForm({
    max_file_mb: props.settings.max_file_mb,
    max_pages: props.settings.max_pages,
    top_k: props.settings.top_k,
    chunk_size: props.settings.chunk_size,
    chunk_overlap: props.settings.chunk_overlap,
    embedding_model: props.settings.embedding_model,
    system_prompt: props.settings.system_prompt,
    ephemeral_retention_days: props.settings.ephemeral_retention_days,
    chunks_per_credit: props.settings.chunks_per_credit,
    youtube_whisper_fallback: props.settings.youtube_whisper_fallback,
    search_mode: props.settings.search_mode,
    chunking_mode: props.settings.chunking_mode,
    map_reduce_batch_size: props.settings.map_reduce_batch_size,
})

const chunkingModeOptions = [
    { value: 'fixed', label: 'Fixed-size (character window)' },
    { value: 'semantic', label: 'Semantic (topic boundary detection)' },
]

const searchModeOptions = [
    { value: 'vector', label: 'Vector — semantic similarity only' },
    { value: 'hybrid', label: 'Hybrid — BM25 keyword + semantic fusion' },
]

const embeddingModelOptions = [
    { value: '', label: `Default — ${props.fallbackEmbedding.label}` },
    ...props.embeddingModels,
]

const saveSettings = () => {
    form.post(route('admin.ai.rag.update'), { preserveScroll: true })
}

const { t } = useTranslate()
</script>

<template>
    <Head :title="t('RAG Settings — Admin')" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('RAG Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Configure retrieval-augmented generation defaults for the Document AI tools suite.') }}</p>
            </div>
            <button
                type="button"
                :disabled="form.processing"
                @click="saveSettings"
                class="inline-flex items-center justify-center rounded-lg btn-primary px-5 py-2.5 text-sm font-semibold disabled:opacity-60"
            >
                {{ form.processing ? t('Saving...') : t('Save Settings') }}
            </button>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">{{ t('Ingestion & Chunking') }}</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Max File Size (MB)') }}</label>
                        <input type="number" v-model="form.max_file_mb" min="1" max="500"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Maximum upload size per file. Larger files are rejected.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Max PDF Pages') }}</label>
                        <input type="number" v-model="form.max_pages" min="1" max="5000"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Documents exceeding this page count are rejected.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Chunk Size (chars)') }}</label>
                        <input type="number" v-model="form.chunk_size" min="100" max="8000"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Text split into chunks of this size before embedding.') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Chunk Overlap (chars)') }}</label>
                        <input type="number" v-model="form.chunk_overlap" min="0" max="2000"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Number of overlapping characters between adjacent chunks.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Chunking Mode') }}</label>
                        <AppSelect
                            v-model="form.chunking_mode"
                            :options="chunkingModeOptions"
                            :placeholder="t('Select mode...')"
                        />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Fixed-size uses character window. Semantic detects topic shifts.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Map-Reduce Batch Size') }}</label>
                        <input type="number" v-model="form.map_reduce_batch_size" min="1" max="50"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Chunks processed per batch during summarization.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Retrieval & Search -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mt-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">{{ t('Retrieval & Search') }}</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Top-K Chunks') }}</label>
                        <input type="number" v-model="form.top_k" min="1" max="50"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Number of relevant chunks retrieved per query.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Search Mode') }}</label>
                        <AppSelect
                            v-model="form.search_mode"
                            :options="searchModeOptions"
                            :placeholder="t('Select mode...')"
                        />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Hybrid combines keyword matching with semantic search for better results.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Embedding Model') }}</label>
                        <AppSelect
                            v-model="form.embedding_model"
                            :options="embeddingModelOptions"
                            :placeholder="t('Use default provider model...')"
                            live-search
                        />
                        <p v-if="form.errors.embedding_model" class="text-xs text-red-500 mt-1">{{ form.errors.embedding_model }}</p>
                        <p class="text-xs text-gray-400 mt-1.5">
                            {{ t('When "Default" is selected, the embedding model from global AI Provider settings is used.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credits & Retention -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mt-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">{{ t('Credits & Retention') }}</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Chunks Per Credit') }}</label>
                        <input type="number" v-model="form.chunks_per_credit" min="1" max="1000"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Number of chunks ingested per credit charged.') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Ephemeral Retention (days)') }}</label>
                        <input type="number" v-model="form.ephemeral_retention_days" min="1" max="90"
                            class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none" />
                        <p class="text-xs text-gray-400 mt-1.5">{{ t('Auto-delete unsaved RAG sessions after this many days.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- YouTube & System Prompt -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mt-6 mb-6">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">{{ t('YouTube & Grounding') }}</h2>
            </div>
            <div class="p-6 space-y-6">
                <div class="flex items-start gap-3 p-4 bg-gray-50/50 rounded-xl border border-gray-100">
                    <button type="button"
                        @click="form.youtube_whisper_fallback = !form.youtube_whisper_fallback"
                        :class="form.youtube_whisper_fallback ? 'bg-[#1F75FE]' : 'bg-gray-300'"
                        class="relative w-11 h-6 rounded-full transition-colors shrink-0 mt-0.5 cursor-pointer">
                        <span
                            class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                            :class="form.youtube_whisper_fallback ? 'translate-x-[22px] left-0.5' : 'left-0.5'" />
                    </button>
                    <div>
                        <span class="block text-sm font-medium text-gray-800">{{ t('YouTube Whisper Fallback') }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">{{ t('When captions are unavailable, transcribe using OpenAI Whisper. Charges credits per audio minute.') }}</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ t('Grounding System Prompt') }}</label>
                    <textarea v-model="form.system_prompt" rows="5" maxlength="4000"
                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 transition-colors outline-none resize-y font-mono"></textarea>
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-xs text-gray-400">{{ t('The {context} placeholder is replaced with retrieved document chunks before each AI call. This prompt is never exposed to end users.') }}</p>
                        <span class="text-xs" :class="form.system_prompt.length > 3800 ? 'text-red-500' : 'text-gray-400'">{{ form.system_prompt.length }}/4000</span>
                    </div>
                    <div class="mt-3 p-3 rounded-lg bg-sky-50 border border-sky-100 text-xs text-sky-700 space-y-1.5">
                        <p class="font-semibold text-sky-800">{{ t('How this works') }}</p>
                        <p>{{ t('When a user asks a question about their uploaded document, the system finds the most relevant text chunks, inserts them where {context} appears in this prompt, and sends it to the AI. The AI then answers based ONLY on those chunks.') }}</p>
                        <p>{{ t('If the answer isn\'t in the document chunks, the AI will honestly say so instead of guessing. This is called "grounding" — keeping the AI\'s answers anchored to real content.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save -->
    </div>
</template>
