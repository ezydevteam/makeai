<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect, { type SelectOption } from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type SocialSchedulerSettings = {
    enabled: boolean
    approval_required: boolean
    max_accounts_per_user: number
    max_media_mb: number
    ai_model: string
    best_time_model: string
    rss_poll_interval_minutes: number
    analytics_pull_enabled: boolean
    carousel_max_slides: number
    first_comment_enabled: boolean
    provider: string
}

type SchedulerModel = {
    slug: string
    name: string
}

type SchedulerProvider = {
    name: string
    models: SchedulerModel[]
}

const { t } = useTranslate()

const props = defineProps<{
    settings: SocialSchedulerSettings
    providers: Record<string, SchedulerProvider>
}>()

const providerSlugs = computed(() => Object.keys(props.providers))
const initialProvider = providerSlugs.value.includes(props.settings.provider)
    ? props.settings.provider
    : providerSlugs.value[0] ?? ''

const form = useForm<SocialSchedulerSettings>({
    ...props.settings,
    provider: initialProvider,
    ai_model: props.settings.ai_model || '',
    best_time_model: props.settings.best_time_model || '',
})

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
        const models = props.providers[provider]?.models ?? []

        if (models.length === 0) {
            form.ai_model = ''
            form.best_time_model = ''
            return
        }

        if (!models.some((model) => model.slug === form.ai_model)) {
            form.ai_model = models[0].slug
        }

        if (!models.some((model) => model.slug === form.best_time_model)) {
            form.best_time_model = models[0].slug
        }
    },
    { immediate: true },
)

const save = () => {
    form.put(route('addon.social.admin.settings'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Social Scheduler Settings')" />

    <div class="mx-auto max-w-7xl px-6 py-6">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Social Scheduler Settings') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Control scheduling access, AI caption generation, RSS polling, and analytics pulls from one unified settings page.') }}
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
                        {{ t('Enable the addon and define the default usage limits.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Social Scheduler') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Hide the scheduling tools while keeping all saved settings intact.') }}
                            </p>
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
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Require Approval') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Send posts to the approval queue before publishing.') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.approval_required"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.approval_required ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.approval_required = !form.approval_required"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.approval_required ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Max Connected Accounts Per User') }}
                        </span>
                        <input
                            v-model.number="form.max_accounts_per_user"
                            type="number"
                            min="1"
                            max="50"
                            autocomplete="off"
                            :placeholder="t('10')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Max Media Upload Size (MB)') }}
                        </span>
                        <input
                            v-model.number="form.max_media_mb"
                            type="number"
                            min="1"
                            max="500"
                            autocomplete="off"
                            :placeholder="t('50')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Max Carousel Slides') }}
                        </span>
                        <input
                            v-model.number="form.carousel_max_slides"
                            type="number"
                            min="2"
                            max="20"
                            autocomplete="off"
                            :placeholder="t('10')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable First Comment') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Automatically post the first Instagram comment when supported.') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.first_comment_enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.first_comment_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.first_comment_enabled = !form.first_comment_enabled"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.first_comment_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('AI Configuration') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Only configured AI providers are shown. Captions and best-time analysis should use models from the selected provider.') }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('AI Provider') }}
                        </span>
                        <AppSelect
                            v-model="form.provider"
                            :options="providerOptions"
                            :placeholder="providerOptions.length === 0 ? t('No configured providers') : t('Select a provider')"
                            :disabled="providerOptions.length === 0"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Configured providers with active models are loaded from the global AI settings.') }}
                        </span>
                    </div>

                    <div class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Caption Model') }}
                        </span>
                        <AppSelect
                            v-model="form.ai_model"
                            :options="modelOptions"
                            :placeholder="modelOptions.length === 0 ? t('No models configured') : t('Select a model')"
                            :disabled="modelOptions.length === 0"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Used for AI caption generation.') }}
                        </span>
                    </div>

                    <div class="block md:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Best Time Model') }}
                        </span>
                        <AppSelect
                            v-model="form.best_time_model"
                            :options="modelOptions"
                            :placeholder="modelOptions.length === 0 ? t('No models configured') : t('Select a model')"
                            :disabled="modelOptions.length === 0"
                        />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            {{ t('Used when analyzing the best time to publish scheduled content.') }}
                        </span>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('RSS & Analytics') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Configure RSS polling and analytics refresh behavior.') }}
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('RSS Poll Interval (minutes)') }}
                        </span>
                        <input
                            v-model.number="form.rss_poll_interval_minutes"
                            type="number"
                            min="5"
                            max="1440"
                            autocomplete="off"
                            :placeholder="t('60')"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </label>

                    <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Pull Analytics Daily') }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ t('Refresh engagement analytics automatically in the background.') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.analytics_pull_enabled"
                            class="relative inline-flex h-6 w-11 rounded-full transition"
                            :class="form.analytics_pull_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                            @click="form.analytics_pull_enabled = !form.analytics_pull_enabled"
                        >
                            <span
                                class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                :class="form.analytics_pull_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                            />
                        </button>
                    </label>
                </div>
            </section>
        </form>
    </div>
</template>
