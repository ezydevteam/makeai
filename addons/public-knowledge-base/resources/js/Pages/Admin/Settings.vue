<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect, { type SelectOption } from '@/Components/AppSelect.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

interface KbModel {
    slug: string
    name: string
}

interface KbProvider {
    name: string
    models: KbModel[]
}

interface KbSettings {
    enabled: boolean
    public_slug: string
    page_title: string
    page_description: string
    show_vote_buttons: boolean
    allow_guest_search: boolean
    widget_enabled: boolean
    widget_accent_color: string
    ai_model: string
    embedding_model: string
    top_k: number
    max_answer_tokens: number
    provider: string
}

const props = defineProps<{
    settings: KbSettings
    providers: Record<string, KbProvider>
}>()

const origin = typeof window === 'undefined' ? '' : window.location.origin

const providerSlugs = computed(() => Object.keys(props.providers))

const initialProvider = providerSlugs.value.includes(props.settings.provider)
    ? props.settings.provider
    : providerSlugs.value[0] ?? ''

const initialModel = (() => {
    const providerModels = props.providers[initialProvider]?.models ?? []
    if (providerModels.some((model) => model.slug === props.settings.ai_model)) {
        return props.settings.ai_model
    }

    return providerModels[0]?.slug ?? ''
})()

const form = useForm<KbSettings>({
    ...props.settings,
    provider: initialProvider,
    ai_model: initialModel,
})

const save = () => form.put(route('addon.kb.admin.settings'), { preserveScroll: true })

const providerOptions = computed<SelectOption[]>(() => providerSlugs.value.map((slug) => ({
    value: slug,
    label: props.providers[slug]?.name ?? slug,
})))

const modelOptions = computed<SelectOption[]>(() => {
    const provider = props.providers[form.provider]
    if (!provider) {
        return []
    }

    return provider.models.map((model) => ({
        value: model.slug,
        label: model.name,
    }))
})

watch(
    () => form.provider,
    (provider) => {
        const providerModels = props.providers[provider]?.models ?? []
        if (providerModels.length === 0) {
            form.ai_model = ''
            return
        }

        const currentModelExists = providerModels.some((model) => model.slug === form.ai_model)
        if (!currentModelExists) {
            form.ai_model = providerModels[0].slug
        }
    },
    { immediate: true },
)
</script>

<template>
    <Head :title="t('KB Settings')" />

    <div class="mx-auto max-w-7xl px-6 py-6">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('KB Settings') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control public access, AI settings, and widget behavior from one unified settings page.') }}
                </p>
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="rounded-lg btn-primary disabled:opacity-60"
                @click="save"
            >
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <form class="space-y-5" @submit.prevent="save">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('General') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Set the public help center slug, title, and search visibility.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Public KB') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the knowledge base hidden while you prepare content.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.enabled = !form.enabled"
                        >
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.enabled ? 'translate-x-5' : 'translate-x-0.5'" />
                        </button>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Public URL Slug') }}</span>
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <span class="text-gray-400">/</span>
                            <input
                                v-model="form.public_slug"
                                type="text"
                                autocomplete="off"
                                :placeholder="t('help')"
                                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"
                            />
                        </div>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Page Title') }}</span>
                        <input
                            v-model="form.page_title"
                            type="text"
                            autocomplete="off"
                            :placeholder="t('Help Center')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Page Meta Description') }}</span>
                        <textarea
                            v-model="form.page_description"
                            rows="3"
                            autocomplete="off"
                            :placeholder="t('Describe the help center for search engines and social previews')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        ></textarea>
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Vote Buttons') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Let visitors vote on helpful or unhelpful articles.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.show_vote_buttons"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.show_vote_buttons ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.show_vote_buttons = !form.show_vote_buttons"
                        >
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.show_vote_buttons ? 'translate-x-5' : 'translate-x-0.5'" />
                        </button>
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Allow Guest Search') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Let anonymous visitors search the knowledge base.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.allow_guest_search"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.allow_guest_search ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.allow_guest_search = !form.allow_guest_search"
                        >
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.allow_guest_search ? 'translate-x-5' : 'translate-x-0.5'" />
                        </button>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('AI Configuration') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Choose only providers with active API keys and select a model from the same provider.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('AI Provider') }}</span>
                        <AppSelect
                            v-model="form.provider"
                            :options="providerOptions"
                            :placeholder="providerOptions.length === 0 ? t('No configured providers') : t('Select a provider')"
                            :disabled="providerOptions.length === 0"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Only providers with active API keys and active models are shown.') }}
                        </span>
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('AI Model') }}</span>
                        <AppSelect
                            v-model="form.ai_model"
                            :options="modelOptions"
                            :placeholder="modelOptions.length === 0 ? t('No models configured') : t('Select a model')"
                            :disabled="modelOptions.length === 0"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            {{ t('The model list updates to the selected provider automatically.') }}
                        </span>
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Embedding Model') }}</span>
                        <AppSelect
                            v-model="form.embedding_model"
                            :options="[
                                { value: '', label: t('Default') },
                                { value: 'text-embedding-3-small', label: 'text-embedding-3-small' },
                                { value: 'text-embedding-3-large', label: 'text-embedding-3-large' },
                                { value: 'text-embedding-ada-002', label: 'text-embedding-ada-002' },
                            ]"
                            :placeholder="t('Default')"
                        />
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Top-K Chunks') }}</span>
                        <input
                            v-model.number="form.top_k"
                            type="number"
                            min="1"
                            max="20"
                            autocomplete="off"
                            :placeholder="t('5')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Higher values return more context, but can slow responses.') }}</span>
                    </div>

                    <div class="block md:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Max Answer Tokens') }}</span>
                        <input
                            v-model.number="form.max_answer_tokens"
                            type="number"
                            min="64"
                            max="4096"
                            autocomplete="off"
                            :placeholder="t('512')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Changing the embedding model requires re-indexing all articles.') }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Widget') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Enable the embeddable widget and copy the installation snippet.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Widget') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show the floating KB launcher on public pages.') }}</p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.widget_enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.widget_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.widget_enabled = !form.widget_enabled"
                        >
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.widget_enabled ? 'translate-x-5' : 'translate-x-0.5'" />
                        </button>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Widget Accent Color') }}</span>
                        <input
                            v-model="form.widget_accent_color"
                            type="text"
                            autocomplete="off"
                            :placeholder="t('#10b981')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </label>

                    <div class="lg:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Install Code') }}</label>
                        <pre class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200"><code>&lt;script src="{{ origin }}/addons/kb-widget.js"
        data-kb-url="{{ origin }}/api/kb-widget"
        data-accent="{{ form.widget_accent_color }}"&gt;&lt;/script&gt;</code></pre>
                    </div>
                </div>
            </section>
        </form>
    </div>
</template>
