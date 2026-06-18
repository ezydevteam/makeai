<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppSelect, { type SelectOption } from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface ProviderModel {
    slug: string
    name: string
}

const props = defineProps<{
    providerStats: Record<string, {
        name: string
        key_count: number
        model_count: number
    }>
    providerModels: Record<string, ProviderModel[]>
    globalSettings: {
        default_provider: string
        default_model: string
        fallback_provider: string
        fallback_model: string
        max_tokens: number
        show_tool_credit_costs: boolean
    }
}>()

const { t } = useTranslate()
const settingsModalOpen = ref(false)

const form = useForm({
    default_provider: props.globalSettings.default_provider,
    default_model: props.globalSettings.default_model,
    fallback_provider: props.globalSettings.fallback_provider || '',
    fallback_model: props.globalSettings.fallback_model || '',
    max_tokens: props.globalSettings.max_tokens,
    show_tool_credit_costs: props.globalSettings.show_tool_credit_costs,
})

const availableModels = computed<ProviderModel[]>(() => props.providerModels[form.default_provider] ?? [])
const fallbackModels = computed<ProviderModel[]>(() => {
    if (!form.fallback_provider) return []
    return props.providerModels[form.fallback_provider] ?? []
})

const providerEntries = computed(() => Object.entries(props.providerStats))
const providerOptions = computed<SelectOption[]>(() => providerEntries.value.map(([slug, stat]) => ({
    value: slug,
    label: stat.name,
})))
const availableModelOptions = computed<SelectOption[]>(() => availableModels.value.map((model) => ({
    value: model.slug,
    label: model.name,
})))
const fallbackProviderOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('None (no fallback)') },
    ...providerOptions.value,
])
const fallbackModelOptions = computed<SelectOption[]>(() => fallbackModels.value.map((model) => ({
    value: model.slug,
    label: model.name,
})))

watch(() => form.default_provider, (newProvider) => {
    const models = props.providerModels[newProvider] ?? []
    if (models.length > 0 && !models.some((model) => model.slug === form.default_model)) {
        form.default_model = models[0].slug
    }
})

watch(() => form.fallback_provider, (newProvider) => {
    if (!newProvider) {
        form.fallback_model = ''
        return
    }

    const models = props.providerModels[newProvider] ?? []
    if (models.length > 0 && !models.some((model) => model.slug === form.fallback_model)) {
        form.fallback_model = models[0].slug
    }
})

const saveSettings = () => {
    form.post(route('admin.ai.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            settingsModalOpen.value = false
        },
    })
}

const providerIcons: Record<string, string> = {
    openai: 'O',
    anthropic: 'A',
    google: 'G',
    xai: 'X',
    deepseek: 'D',
    openrouter: 'R',
    groq: 'Q',
    mistral: 'M',
    ollama: 'L',
    bedrock: 'B',
    cohere: 'C',
    eleven: 'E',
    jina: 'J',
    voyageai: 'V',
    perplexity: 'P',
    together: 'T',
    replicate: 'R2',
}

const providerColorClasses: Record<string, string> = {
    openai: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300',
    anthropic: 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300',
    google: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
    xai: 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-200',
    deepseek: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300',
    openrouter: 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300',
    groq: 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-300',
    mistral: 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/20 dark:text-cyan-300',
    ollama: 'bg-teal-50 text-teal-700 dark:bg-teal-900/20 dark:text-teal-300',
    bedrock: 'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-300',
    cohere: 'bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-300',
    eleven: 'bg-pink-50 text-pink-700 dark:bg-pink-900/20 dark:text-pink-300',
    jina: 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-900/20 dark:text-fuchsia-300',
    voyageai: 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300',
    perplexity: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-300',
    together: 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-300',
    replicate: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300',
}
</script>

<template>
    <Head :title="t('AI Management — Admin')" />

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="settingsModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                @click.self="settingsModalOpen = false"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <section
                        v-if="settingsModalOpen"
                        class="flex max-h-[90vh] w-full sm:max-w-4xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Global AI Settings') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Control the default model routing, fallback path, and output budget used across the AI system.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800"
                                @click="settingsModalOpen = false"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-5">
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div>
                                    <AppSelect v-model="form.default_provider" :options="providerOptions" :label="t('Default Provider')" />
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Used when no specific provider is requested by a tool or workflow.') }}</p>
                                    <p v-if="form.errors.default_provider" class="mt-1 text-xs text-danger-600">{{ form.errors.default_provider }}</p>
                                </div>

                                <div>
                                    <AppSelect
                                        v-model="form.default_model"
                                        :options="availableModelOptions"
                                        :label="t('Default Model')"
                                        :placeholder="t('No models configured for this provider')"
                                        :disabled="availableModelOptions.length === 0"
                                    />
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Used by tools set to inherit the global model selection.') }}</p>
                                    <p v-if="form.errors.default_model" class="mt-1 text-xs text-danger-600">{{ form.errors.default_model }}</p>
                                </div>

                                <label class="block">
                                    <span class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Global Max Tokens') }}</span>
                                    <input v-model="form.max_tokens" type="number" min="1" max="128000" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Acts as the system-wide output ceiling for AI requests.') }}</p>
                                    <p v-if="form.errors.max_tokens" class="mt-1 text-xs text-danger-600">{{ form.errors.max_tokens }}</p>
                                </label>
                            </div>

                            <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-5 dark:border-amber-900/30 dark:bg-amber-900/10">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                        <i class="ti ti-repeat text-lg"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Fallback Provider & Model') }}</h3>
                                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ t('Retry failed requests with a secondary provider when the primary route hits quota, rate-limit, or provider-level server errors.') }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <AppSelect
                                            v-model="form.fallback_provider"
                                            :options="fallbackProviderOptions"
                                            :label="t('Fallback Provider')"
                                        />
                                        <p v-if="form.errors.fallback_provider" class="mt-1 text-xs text-danger-600">{{ form.errors.fallback_provider }}</p>
                                    </div>

                                    <div>
                                        <AppSelect
                                            v-model="form.fallback_model"
                                            :options="fallbackModelOptions"
                                            :label="t('Fallback Model')"
                                            :placeholder="!form.fallback_provider ? t('Select a provider first') : t('No models configured')"
                                            :disabled="!form.fallback_provider || fallbackModelOptions.length === 0"
                                        />
                                        <p v-if="form.errors.fallback_model" class="mt-1 text-xs text-danger-600">{{ form.errors.fallback_model }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Credit Costs On Tool Pages') }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Display estimated cost, user balance, and the final credits used after generation.') }}</div>
                                    </div>
                                    <button type="button" role="switch" :aria-checked="form.show_tool_credit_costs" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.show_tool_credit_costs ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="form.show_tool_credit_costs = !form.show_tool_credit_costs">
                                        <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.show_tool_credit_costs ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                    </button>
                                </div>
                                <p v-if="form.errors.show_tool_credit_costs" class="mt-2 text-xs text-danger-600">{{ form.errors.show_tool_credit_costs }}</p>
                            </div>
                        </div>

                        <div class="shrink-0 border-t border-gray-100 bg-gray-50/80 px-5 py-3 dark:border-surface-800 dark:bg-surface-950">
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                                    @click="settingsModalOpen = false"
                                >
                                    {{ t('Cancel') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="form.processing"
                                    class="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-600 disabled:opacity-60"
                                    @click="saveSettings"
                                >
                                    <i v-if="form.processing" class="ti ti-loader-2 me-2 animate-spin text-sm"></i>
                                    {{ form.processing ? t('Saving...') : t('Save Settings') }}
                                </button>
                            </div>
                        </div>
                    </section>
                </Transition>
            </div>
        </Transition>
    </Teleport>

    <div class="mx-auto max-w-7xl px-6 py-8">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Providers') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage providers, model routing, fallback behavior, and the global AI experience from one place.') }}</p>
            </div>
            <button type="button" @click="settingsModalOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-white text-gray-600 hover:text-gray-700 border border-gray-200 shadow-card px-5 py-2.5 text-sm font-semibold hover:border-gray-300 hover:dark:border-gray-200 dark:text-gray-200 dark:border-surface-700 dark:bg-surface-900">
                <i class="ti ti-settings text-base"></i>
                <span>{{ t('Global Settings') }}</span>
            </button>
        </section>

        <div class="mt-6 space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        <Link
                            v-for="[slug, stat] in providerEntries"
                            :key="slug"
                            :href="route('admin.ai.provider', { slug })"
                            class="group rounded-2xl border border-gray-200 bg-gray-50/70 p-5 transition hover:-translate-y-0.5 hover:border-primary-300 hover:bg-white hover:shadow-md dark:border-surface-700 dark:bg-surface-800/60 dark:hover:border-primary-900/40 dark:hover:bg-surface-800"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl text-sm font-bold" :class="providerColorClasses[slug] ?? 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-200'">
                                    {{ providerIcons[slug] ?? slug.charAt(0).toUpperCase() }}
                                </span>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide" :class="stat.key_count > 0 ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-200 text-gray-600 dark:bg-surface-700 dark:text-gray-300'">
                                    {{ stat.key_count > 0 ? t('Connected') : t('No Key') }}
                                </span>
                            </div>

                            <div class="mt-4">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ stat.name }}</h3>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ t(':count model(s) ready for this provider.', { count: stat.model_count }) }}</p>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-4 text-xs dark:border-surface-700">
                                <span class="font-semibold text-gray-600 dark:text-gray-300">{{ t(':count key(s)', { count: stat.key_count }) }}</span>
                                <span class="font-semibold text-primary-600 dark:text-primary-300">{{ t('Manage') }}</span>
                            </div>
                        </Link>
                    </div>
                </div>
        </div>
    </div>
</template>
