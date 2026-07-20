<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface ProviderModel {
    slug: string
    name: string
}

interface CreditPreviewRow {
    id: number
    name: string
    provider: string
    type: string
    current: number
    derived: number
    auto: boolean
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
        user_daily_credit_limit: number
        user_monthly_credit_limit: number
        global_daily_ai_budget_usd: number
        credit_alert_threshold: number
        guest_daily_credit_limit: number
        public_tool_max_output_chars: number
        ai_max_input_chars: number
    }
    creditPricing: {
        ai_credit_markup: number
        ai_credit_min_per_1k: number
        credit_price_per_unit: number
    }
}>()

const { t } = useTranslate()
const page = usePage()
// Billing is only available on an Extended license with subscriptions on. When it's
// off (Regular license), credits act as a resetting usage quota, not a wallet — so
// the per-user / guest limits below are the primary caps.
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const settingsModalOpen = ref(false)
const recalcModalOpen = ref(false)
const recalcLoading = ref(false)
const recalcApplying = ref(false)
const recalcRows = ref<CreditPreviewRow[]>([])
const recalcMarkup = ref(props.creditPricing.ai_credit_markup)
const recalcPricePerCredit = ref(props.creditPricing.credit_price_per_unit)

const form = useForm({
    default_provider: props.globalSettings.default_provider,
    default_model: props.globalSettings.default_model,
    fallback_provider: props.globalSettings.fallback_provider || '',
    fallback_model: props.globalSettings.fallback_model || '',
    max_tokens: props.globalSettings.max_tokens,
    user_daily_credit_limit: props.globalSettings.user_daily_credit_limit,
    user_monthly_credit_limit: props.globalSettings.user_monthly_credit_limit,
    global_daily_ai_budget_usd: props.globalSettings.global_daily_ai_budget_usd,
    credit_alert_threshold: props.globalSettings.credit_alert_threshold,
    guest_daily_credit_limit: props.globalSettings.guest_daily_credit_limit,
    public_tool_max_output_chars: props.globalSettings.public_tool_max_output_chars,
    ai_max_input_chars: props.globalSettings.ai_max_input_chars,
    ai_credit_markup: props.creditPricing.ai_credit_markup,
    ai_credit_min_per_1k: props.creditPricing.ai_credit_min_per_1k,
    credit_price_per_unit: props.creditPricing.credit_price_per_unit,
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

const openRecalcModal = async () => {
    recalcModalOpen.value = true
    recalcLoading.value = true

    try {
        const response = await fetch(route('admin.ai.credits.preview'), {
            headers: { Accept: 'application/json' },
        })
        const data = await response.json()
        recalcRows.value = data.rows ?? []
        recalcMarkup.value = data.markup ?? recalcMarkup.value
        recalcPricePerCredit.value = data.price_per_credit ?? recalcPricePerCredit.value
    } finally {
        recalcLoading.value = false
    }
}

const applyRecalculate = () => {
    recalcApplying.value = true
    router.post(route('admin.ai.credits.recalculate'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            recalcModalOpen.value = false
        },
        onFinish: () => {
            recalcApplying.value = false
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
    <Head :title="t('AI Management')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Providers') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage providers, model routing, fallback behavior, and the global AI experience from one place.') }}</p>
            </div>
            <div class="shrink-0 flex gap-3">
                <button
                    type="button"
                    class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
                    @click="openRecalcModal"
                >
                    <i class="ti ti-refresh text-base"></i>
                    {{ t('Recalculate Credits') }}
                </button>
                <button
                    type="button"
                    class="grow inline-flex items-center justify-center gap-2 btn-primary-admin"
                    @click="settingsModalOpen = true"
                >
                    <i class="ti ti-settings text-base"></i>
                    {{ t('Global Settings') }}
                </button>
            </div>
        </section>

        <div class="space-y-6">
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

    <AppModal
        :open="settingsModalOpen"
        max-width="max-w-4xl"
        :title="t('Global AI Settings')"
        :subtitle="t('Control the default model routing, fallback path, and output budget used across the AI system.')"
        has-form
        :confirm-text="t('Save Settings')"
        :confirm-loading="form.processing"
        confirm-loading-text="Saving..."
        @close="settingsModalOpen = false"
        @submit="saveSettings"
    >
        <div
            v-if="!isProAvailable"
            class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-100 bg-amber-50/70 p-4 dark:border-amber-900/30 dark:bg-amber-900/10"
        >
            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                <i class="ti ti-info-circle text-base"></i>
            </span>
            <div class="min-w-0 text-sm text-amber-800 dark:text-amber-200">
                <p class="font-semibold">{{ t('Billing is disabled on your license') }}</p>
                <p class="mt-0.5 text-xs leading-5 text-amber-700 dark:text-amber-300/90">{{ t('Credits work as a resetting usage allowance (they are not sold or purchased). Control how much visitors and users can generate with the Guest and Per-User limits in Spend Controls below.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 rounded-2xl border border-gray-100 bg-gray-50/70 p-5 dark:border-gray-900/30 dark:bg-gray-900/10">
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
                        :placeholder="t('Select a fallback provider')"
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


        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 dark:border-emerald-900/30 dark:bg-emerald-900/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    <i class="ti ti-shield-dollar text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Spend Controls') }}</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ t('Protect your provider bill with per-user credit caps and a global daily budget. Set 0 to disable a limit.') }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Guest Daily Credit Limit') }}</span>
                    <input v-model="form.guest_daily_credit_limit" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Credits an anonymous visitor (per IP) can use per day on public tools. 0 = no guest limit.') }}</p>
                    <p v-if="form.errors.guest_daily_credit_limit" class="mt-1 text-xs text-danger-600">{{ form.errors.guest_daily_credit_limit }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Guest Preview Output Limit (chars)') }}</span>
                    <input v-model="form.public_tool_max_output_chars" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Guests on free tools see a truncated preview with a sign-in prompt.') }}</p>
                    <p v-if="form.errors.public_tool_max_output_chars" class="mt-1 text-xs text-danger-600">{{ form.errors.public_tool_max_output_chars }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Per-User Daily Credit Limit') }}</span>
                    <input v-model="form.user_daily_credit_limit" type="number" min="0" step="any" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Logged-in user daily credit limit.') }}</p>
                    <p v-if="form.errors.user_daily_credit_limit" class="mt-1 text-xs text-danger-600">{{ form.errors.user_daily_credit_limit }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Per-User Monthly Credit Limit') }}</span>
                    <input v-model="form.user_monthly_credit_limit" type="number" min="0" step="any" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Logged-in user monthly credit limit.') }}</p>
                    <p v-if="form.errors.user_monthly_credit_limit" class="mt-1 text-xs text-danger-600">{{ form.errors.user_monthly_credit_limit }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Global Daily AI Budget (USD)') }}</span>
                    <input v-model="form.global_daily_ai_budget_usd" type="number" min="0" step="any" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('All AI requests pause for the day once this is reached.') }}</p>
                    <p v-if="form.errors.global_daily_ai_budget_usd" class="mt-1 text-xs text-danger-600">{{ form.errors.global_daily_ai_budget_usd }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Low-Credit Alert Threshold') }}</span>
                    <input v-model="form.credit_alert_threshold" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Notify users when their balance drops to this many credits.') }}</p>
                    <p v-if="form.errors.credit_alert_threshold" class="mt-1 text-xs text-danger-600">{{ form.errors.credit_alert_threshold }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Global Max Tokens') }}</span>
                    <input v-model="form.max_tokens" type="number" min="1" max="128000" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Acts as the system-wide output ceiling for AI requests.') }}</p>
                    <p v-if="form.errors.max_tokens" class="mt-1 text-xs text-danger-600">{{ form.errors.max_tokens }}</p>
                </label>

                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Max Input Size (chars)') }}</span>
                    <input v-model="form.ai_max_input_chars" type="number" min="1000" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Rejects oversized tool inputs before they inflate token costs.') }}</p>
                    <p v-if="form.errors.ai_max_input_chars" class="mt-1 text-xs text-danger-600">{{ form.errors.ai_max_input_chars }}</p>
                </label>
            </div>
        </div>

        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-5 dark:border-sky-900/30 dark:bg-sky-900/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                    <i class="ti ti-coin text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ t('Credit Economics') }}</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ isProAvailable ? t('Model credits are derived automatically from real provider cost, so you never sell AI below cost.') : t('Model credits are derived from real provider cost × your multiplier — this sets how many credits each generation consumes from users\' allowances.') }}</p>
                </div>
            </div>

            <label class="mt-4 block rounded-xl border border-sky-100 bg-white px-4 py-3 dark:border-sky-900/30 dark:bg-surface-900">
                <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ isProAvailable ? t('Price per credit') : t('Credit value') }}</span>
                <input v-model="form.credit_price_per_unit" type="number" step="0.0001" min="0.0001" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ isProAvailable ? t('The USD value of one credit. Also used for credit top-up pricing (synced with Credit Settings).') : t('Internal factor for computing model credit costs.') }}</p>
                <p v-if="form.errors.credit_price_per_unit" class="mt-1 text-xs text-danger-600">{{ form.errors.credit_price_per_unit }}</p>
            </label>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ isProAvailable ? t('AI Markup (× provider cost)') : t('Credit multiplier (× provider cost)') }}</span>
                    <input v-model="form.ai_credit_markup" type="number" step="0.1" min="0.1" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ isProAvailable ? t('Each model charges this multiple of its raw API cost. e.g. 3 = charge 3× cost.') : t('Sets each model\'s credit cost relative to its real API cost. Higher = users\' allowances are used up faster.') }}</p>
                    <p v-if="form.errors.ai_credit_markup" class="mt-1 text-xs text-danger-600">{{ form.errors.ai_credit_markup }}</p>
                </label>

                <div>
                    <label class="block">
                        <span class="mb-2 block text-xs font-semibold text-gray-700 dark:text-gray-300">{{ t('Minimum credits / 1K') }}</span>
                        <input v-model="form.ai_credit_min_per_1k" type="number" min="0" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t('Floor so a priced model never rounds to 0 credits.') }}</p>
                        <p v-if="form.errors.ai_credit_min_per_1k" class="mt-1 text-xs text-danger-600">{{ form.errors.ai_credit_min_per_1k }}</p>
                    </label>
                </div>
            </div>

            <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ isProAvailable ? t('New markup applies to new generations after you Recalculate model credits.') : t('Changes apply to new generations after you Recalculate model credits.') }}</p>
        </div>
    </AppModal>

    <AppModal
        :open="recalcModalOpen"
        max-width="max-w-3xl"
        :title="t('Recalculate Model Credits')"
        :subtitle="t('Preview how each model\'s credits_per_1k would change, then apply the new values to auto-priced models.')"
        :confirm-text="t('Apply to auto-priced models')"
        :confirm-loading="recalcApplying"
        confirm-loading-text="Applying..."
        @close="recalcModalOpen = false"
        @confirm="applyRecalculate"
    >
        <div v-if="recalcLoading" class="flex items-center justify-center py-10 text-sm text-gray-500 dark:text-gray-400">
            <i class="ti ti-loader-2 mr-2 animate-spin text-lg"></i>
            {{ t('Loading preview...') }}
        </div>

        <div v-else>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                {{ isProAvailable ? t('Using markup ×:markup at $:price per credit.', { markup: recalcMarkup, price: recalcPricePerCredit }) : t('Using multiplier ×:markup.', { markup: recalcMarkup }) }}
            </p>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-surface-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-surface-700">
                <thead class="bg-gray-50 dark:bg-surface-800/60">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Provider') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Model') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Current') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Derived') }}</th>
                        <th class="px-4 py-2.5 text-center text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ t('Mode') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-surface-800 dark:bg-surface-900">
                    <tr v-for="row in recalcRows" :key="row.id">
                        <td class="whitespace-nowrap px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ row.provider }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 font-medium text-gray-900 dark:text-white">{{ row.name }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right text-gray-700 dark:text-gray-300">{{ row.current }}</td>
                        <td
                            class="whitespace-nowrap px-4 py-2.5 text-right font-semibold"
                            :class="row.current !== row.derived ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-300'"
                        >
                            {{ row.derived }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-center">
                            <span
                                v-if="row.auto"
                                class="rounded-full bg-sky-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-900/30 dark:text-sky-300"
                            >{{ t('Auto') }}</span>
                            <span
                                v-else
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                            >{{ t('Manual') }}</span>
                        </td>
                    </tr>
                    <tr v-if="!recalcLoading && recalcRows.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('No chat models found.') }}</td>
                    </tr>
                </tbody>
            </table>
            </div>

            <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ t('Rows marked "Manual" were hand-edited and will not change.') }}</p>
        </div>
    </AppModal>
</template>
