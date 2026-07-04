<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect, { type SelectOption } from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type TranscriptionProvider = 'whisper' | 'assemblyai'

interface RepurposerSettings {
    enabled: boolean
    ai_model: string
    transcription_provider: TranscriptionProvider
    credits_per_repurpose: number
    credits_per_bulk_item: number
    max_file_size_mb: number
    max_bulk_items: number
    default_formats: string
    twitter_thread_length: number
    blog_post_min_words: number
    auto_save_blog: boolean
}

interface SettingsProps {
    settings: Partial<RepurposerSettings>
}

interface FormatOption {
    key: string
    label: string
    description: string
}

const { t } = useTranslate()

const props = defineProps<SettingsProps>()

const defaultFormats = props.settings.default_formats ?? 'blog_post,twitter_thread,linkedin_article,email_newsletter'

const form = useForm<RepurposerSettings>({
    enabled: props.settings.enabled ?? true,
    ai_model: props.settings.ai_model ?? '',
    transcription_provider: props.settings.transcription_provider ?? 'whisper',
    credits_per_repurpose: props.settings.credits_per_repurpose ?? 15,
    credits_per_bulk_item: props.settings.credits_per_bulk_item ?? 12,
    max_file_size_mb: props.settings.max_file_size_mb ?? 100,
    max_bulk_items: props.settings.max_bulk_items ?? 10,
    default_formats: defaultFormats,
    twitter_thread_length: props.settings.twitter_thread_length ?? 10,
    blog_post_min_words: props.settings.blog_post_min_words ?? 800,
    auto_save_blog: props.settings.auto_save_blog ?? false,
})

const formatOptions: FormatOption[] = [
    { key: 'blog_post', label: t('Blog Post'), description: t('Long-form article output.') },
    { key: 'twitter_thread', label: t('X Thread'), description: t('Short, threaded social posts.') },
    { key: 'linkedin_article', label: t('LinkedIn Article'), description: t('Professional platform-ready rewrite.') },
    { key: 'email_newsletter', label: t('Email Newsletter'), description: t('Audience-friendly newsletter draft.') },
    { key: 'tiktok_script', label: t('TikTok Script'), description: t('Short-form video narration.') },
    { key: 'podcast_show_notes', label: t('Podcast Show Notes'), description: t('Episode summary and highlights.') },
    { key: 'key_quotes', label: t('Key Quotes'), description: t('Extract the strongest takeaways.') },
    { key: 'chapter_markers', label: t('Chapter Markers'), description: t('Segment the source into chapters.') },
]

const selectedFormats = ref<string[]>(defaultFormats.split(',').filter(Boolean))

const aiModelOptions: SelectOption[] = [
    { value: 'gpt-4o-mini', label: t('GPT-4o Mini') },
    { value: 'gpt-4o', label: t('GPT-4o') },
    { value: 'gpt-4-turbo', label: t('GPT-4 Turbo') },
    { value: 'gpt-4', label: t('GPT-4') },
    { value: 'claude-3-5-sonnet', label: t('Claude 3.5 Sonnet') },
    { value: 'claude-3-5-haiku', label: t('Claude 3.5 Haiku') },
    { value: 'claude-sonnet-4-5', label: t('Claude Sonnet 4.5') },
    { value: 'claude-haiku-4-5', label: t('Claude Haiku 4.5') },
    { value: 'gemini-2.5-flash', label: t('Gemini 2.5 Flash') },
    { value: 'gemini-2.5-pro', label: t('Gemini 2.5 Pro') },
    { value: 'gemini-2.0-flash', label: t('Gemini 2.0 Flash') },
    { value: 'gemini-1.5-pro', label: t('Gemini 1.5 Pro') },
    { value: 'gemini-1.5-flash', label: t('Gemini 1.5 Flash') },
]

const transcriptionProviderOptions: SelectOption[] = [
    { value: 'whisper', label: t('OpenAI Whisper') },
    { value: 'assemblyai', label: t('AssemblyAI') },
]

const generalSummary = computed(() => [
    {
        label: t('Enabled'),
        value: form.enabled ? t('Yes') : t('No'),
    },
    {
        label: t('Auto-save blog'),
        value: form.auto_save_blog ? t('Enabled') : t('Disabled'),
    },
    {
        label: t('Default formats'),
        value: selectedFormats.value.length.toString(),
    },
])

function toggleFormat(key: string) {
    const index = selectedFormats.value.indexOf(key)

    if (index === -1) {
        selectedFormats.value.push(key)
    } else {
        selectedFormats.value.splice(index, 1)
    }

    form.default_formats = selectedFormats.value.join(',')
}

function save() {
    form.put('/admin/content-repurposer/settings', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Content Repurposer Settings')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Content Repurposer Settings') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control output formats, AI configuration, credits, and limits from one unified admin page.') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    :disabled="form.processing"
                    @click="save"
                >
                    <i v-if="form.processing" class="ti ti-loader-2 mr-2 animate-spin"></i>
                    {{ form.processing ? t('Saving...') : t('Save Changes') }}
                </button>
            </div>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('General') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Enable the addon and choose the default content formats.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Content Repurposer') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Hide the public repurposer form while keeping saved settings.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.enabled = !form.enabled"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto-save blog posts') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Automatically publish repurposed blog posts to the core blog.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.auto_save_blog"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.auto_save_blog ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.auto_save_blog = !form.auto_save_blog"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.auto_save_blog ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>
                </div>

                <div class="mt-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Default Formats') }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Choose the formats created when repurposing content.') }}
                            </p>
                        </div>
                        <span class="rounded-full bg-primary-100 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                            {{ selectedFormats.length }} {{ t('selected') }}
                        </span>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <button
                            v-for="fmt in formatOptions"
                            :key="fmt.key"
                            type="button"
                            @click="toggleFormat(fmt.key)"
                            class="rounded-xl border p-4 text-left transition"
                            :class="selectedFormats.includes(fmt.key)
                                ? 'border-primary-300 bg-primary-50 text-primary-900 dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-200'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-primary-200 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:bg-surface-700'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">
                                        {{ fmt.label }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ fmt.description }}
                                    </p>
                                </div>
                                <span
                                    class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px] font-bold"
                                    :class="selectedFormats.includes(fmt.key)
                                        ? 'border-primary-500 bg-primary-500 text-white'
                                        : 'border-gray-300 text-gray-400 dark:border-surface-600'"
                                >
                                    {{ selectedFormats.includes(fmt.key) ? '✓' : '+' }}
                                </span>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('AI Configuration') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Set the primary repurposing model and transcription provider.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('AI Model') }}</label>
                        <AppSelect
                            v-model="form.ai_model"
                            :options="aiModelOptions"
                            :placeholder="t('Select AI model')"
                        />
                    </div>

                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Transcription Provider') }}</label>
                        <AppSelect
                            v-model="form.transcription_provider"
                            :options="transcriptionProviderOptions"
                            :placeholder="t('Select transcription provider')"
                        />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Credits') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Set the credit cost for single and bulk repurposing jobs.') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per Single Repurpose') }}</label>
                        <input
                            v-model.number="form.credits_per_repurpose"
                            type="number"
                            min="1"
                            autocomplete="off"
                            :placeholder="t('15')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Credits per Bulk Item') }}</label>
                        <input
                            v-model.number="form.credits_per_bulk_item"
                            type="number"
                            min="1"
                            autocomplete="off"
                            :placeholder="t('12')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Limits') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Control file size, bulk size, and content generation boundaries.') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max File Size (MB)') }}</label>
                        <input
                            v-model.number="form.max_file_size_mb"
                            type="number"
                            min="1"
                            autocomplete="off"
                            :placeholder="t('100')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Bulk Items') }}</label>
                        <input
                            v-model.number="form.max_bulk_items"
                            type="number"
                            min="1"
                            max="100"
                            autocomplete="off"
                            :placeholder="t('10')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Twitter Thread Length') }}</label>
                        <input
                            v-model.number="form.twitter_thread_length"
                            type="number"
                            min="1"
                            max="50"
                            autocomplete="off"
                            :placeholder="t('10')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>

                    <div class="block">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Blog Post Min Words') }}</label>
                        <input
                            v-model.number="form.blog_post_min_words"
                            type="number"
                            min="100"
                            autocomplete="off"
                            :placeholder="t('800')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </div>
                </div>
            </section>
        </form>
    </div>
</template>
