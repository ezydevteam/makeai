<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import type { SelectOption } from '@/Components/AppSelect.vue'
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'

defineOptions({ layout: AdminLayout })

interface SecretState {
    configured: boolean
    masked: string | null
}

interface ProviderState {
    name: string
    secrets: Record<string, SecretState>
    options: Record<string, string>
}

interface IntegrationState {
    name: string
    service: string
    enabled: boolean
    provider: string
    timeout: number
    fixed_credit_cost: string
    doc_url: string | null
    tab: string
    ai_fallback: boolean
    providers: Record<string, ProviderState>
}

interface ConfiguredAiProvider {
    name: string
    key_count: number
}

const props = defineProps<{
    integrations: Record<string, IntegrationState>
    defaultAiProvider: string
    configuredAiProviders: Record<string, ConfiguredAiProvider>
}>()

const { t } = useTranslate()
const aiIndexUrl = route('admin.ai.index')

const DEFAULT_AI_SENTINEL = '__default_ai__'

const tabs = [
    { key: 'image_media', label: 'Image & Media', icon: 'ti ti-photo', description: 'Image generation, stock photos, and editing tools' },
    { key: 'voice_audio', label: 'Voice & Audio', icon: 'ti ti-wave-sine', description: 'Text-to-speech, speech-to-text, and audio tools' },
    { key: 'video', label: 'Video', icon: 'ti ti-video', description: 'Video generation and avatar creation services' },
    { key: 'productivity', label: 'Productivity', icon: 'ti ti-bolt', description: 'Workspace, publishing, and workflow integrations' },
    { key: 'utilities', label: 'Utilities', icon: 'ti ti-tool', description: 'Search, translation, captcha, SMS, and support services' },
]

const activeTab = ref('image_media')
const searchQuery = ref('')

const defaultAiName = computed(() => {
    const cfg = props.configuredAiProviders[props.defaultAiProvider]
    return cfg ? `${cfg.name} (${t('System Default')})` : t('System Default AI Model')
})

const tabIntegrations = computed(() => {
    return Object.entries(props.integrations)
        .filter(([, integration]) => integration.tab === activeTab.value || (!integration.tab && activeTab.value === 'utilities'))
        .sort(([, a], [, b]) => a.name.localeCompare(b.name))
})

const filteredIntegrations = computed(() => {
    if (!searchQuery.value.trim()) return tabIntegrations.value
    const query = searchQuery.value.toLowerCase()
    return tabIntegrations.value.filter(([slug, integration]) => {
        return integration.name.toLowerCase().includes(query) ||
            integration.service.toLowerCase().includes(query) ||
            slug.toLowerCase().includes(query) ||
            Object.values(integration.providers).some(p => p.name.toLowerCase().includes(query))
    })
})

const testing = ref<Record<string, boolean>>({})
const testResults = ref<Record<string, { success: boolean; message?: string; error?: string }>>({})

const form = useForm({
    integrations: Object.fromEntries(
        Object.entries(props.integrations).map(([slug, integration]) => [
            slug,
            {
                enabled: integration.enabled,
                provider: integration.provider,
                timeout: integration.timeout,
                fixed_credit_cost: integration.fixed_credit_cost,
                providers: Object.fromEntries(
                    Object.entries(integration.providers).map(([providerSlug, provider]) => [
                        providerSlug,
                        {
                            options: { ...provider.options },
                            secrets: Object.fromEntries(
                                Object.entries(provider.secrets).map(([secretKey, secret]) => [
                                    secretKey,
                                    secret.masked ?? ''
                                ])
                            ),
                        },
                    ])
                ),
            },
        ])
    ),
})

const providerOptions = computed<Record<string, SelectOption[]>>(() => {
    const result: Record<string, SelectOption[]> = {}
    for (const [slug, integration] of Object.entries(props.integrations)) {
        const options: SelectOption[] = []
        if (integration.ai_fallback) {
            options.push({ value: DEFAULT_AI_SENTINEL, label: `✨ ${defaultAiName.value}` })
        }
        for (const [providerSlug, provider] of Object.entries(integration.providers)) {
            options.push({ value: providerSlug, label: provider.name })
        }
        result[slug] = options
    }
    return result
})

const getProvider = (slug: string, fromForm = true) => {
    const integration = props.integrations[slug]
    if (!integration) return null
    const selected = fromForm
        ? (form.integrations[slug]?.provider ?? integration.provider)
        : integration.provider
    if (selected === DEFAULT_AI_SENTINEL) return null
    const provs = integration.providers
    if (!provs) return null
    return provs[selected] ?? Object.values(provs)[0] ?? null
}

const isDefaultAi = (slug: string) => {
    return form.integrations[slug]?.provider === DEFAULT_AI_SENTINEL
}

const isSecretConfigured = (slug: string, secretKey: string) => {
    const sp = getProvider(slug, false)
    if (!sp) return false
    return sp.secrets[secretKey]?.configured ?? false
}

const maskedSecretValue = (slug: string, secretKey: string) => {
    const sp = getProvider(slug, false)
    if (!sp) return ''
    return sp.secrets[secretKey]?.masked ?? ''
}

const clearMaskedSecretOnFocus = (slug: string, providerSlug: string, secretKey: string) => {
    const maskedValue = maskedSecretValue(slug, secretKey)
    if (!maskedValue) return

    const currentValue = form.integrations[slug]?.providers[providerSlug]?.secrets[secretKey]
    if (currentValue === maskedValue) {
        form.integrations[slug].providers[providerSlug].secrets[secretKey] = ''
    }
}

const hasProviderSwitcher = (integration: IntegrationState, slug: string) => {
    return Object.keys(integration.providers).length > 0
}

const submit = () => {
    form.transform((data) => ({
        ...data,
        integrations: Object.fromEntries(
            Object.entries(data.integrations).map(([slug, integration]) => [
                slug,
                {
                    ...integration,
                    providers: Object.fromEntries(
                        Object.entries(integration.providers).map(([providerSlug, provider]) => [
                            providerSlug,
                            {
                                ...provider,
                                secrets: Object.fromEntries(
                                    Object.entries(provider.secrets).map(([secretKey, value]) => [
                                        secretKey,
                                        value === maskedSecretValue(slug, secretKey) ? '' : value,
                                    ])
                                ),
                            },
                        ])
                    ),
                },
            ])
        ),
    })).post(route('admin.ai.integrations.update'), {
        preserveScroll: true,
    })
}

const testConnection = async (slug: string) => {
    testing.value[slug] = true
    testResults.value[slug] = { success: false }

    const integration = props.integrations[slug]
    const providerSlug = form.integrations[slug]?.provider
    const providerData = providerSlug && providerSlug !== DEFAULT_AI_SENTINEL
        ? form.integrations[slug]?.providers[providerSlug]
        : null

    const credentials: Record<string, string> = {}
    if (providerData && integration?.providers[providerSlug]) {
        const originalSecrets = integration.providers[providerSlug].secrets
        for (const [key, value] of Object.entries(providerData.secrets)) {
            if (value && value !== (originalSecrets[key]?.masked ?? '')) {
                credentials[key] = value
            }
        }
    }

    try {
        const res = await fetch(route('admin.ai.integrations.test-connection', { integration: slug }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                provider: providerSlug && providerSlug !== DEFAULT_AI_SENTINEL ? providerSlug : null,
                credentials,
            }),
        })
        const data = await res.json()
        testResults.value[slug] = data
    } catch {
        testResults.value[slug] = { success: false, error: t('Network error') }
    } finally {
        testing.value[slug] = false
    }
}

const tabNavRef = ref<HTMLElement | null>(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

const updateScrollIndicators = () => {
    const el = tabNavRef.value
    if (!el) return
    canScrollLeft.value = el.scrollLeft > 0
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 1
}

onMounted(() => {
    nextTick(updateScrollIndicators)
})

onBeforeUnmount(() => {
    tabNavRef.value?.removeEventListener('scroll', updateScrollIndicators)
})

const onTabNavScroll = () => {
    updateScrollIndicators()
}

watch(activeTab, () => {
    nextTick(updateScrollIndicators)
})
</script>

<template>
    <Head :title="t('Integrations — AI Management')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Integrations') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Configure third-party service providers for image, voice, video, productivity, and utility tools from one unified admin workspace.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="form.processing"
                    class="rounded-lg btn-primary disabled:opacity-60"
                    @click="submit"
                >
                    <svg
                        v-if="form.processing"
                        class="me-2 h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    {{ form.processing ? t('Saving...') : (!form.isDirty ? t('No Changes') : t('Save Changes')) }}
                </button>
            </div>
        </div>

        <div class="mb-6 rounded-2xl border border-sky-100 bg-sky-50/80 px-5 py-4 text-sm text-sky-700 shadow-sm dark:border-sky-900/30 dark:bg-sky-900/10 dark:text-sky-200">
            <div class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <span>{{ t('Each integration can use a dedicated provider or fall back to the system default AI model.') }}</span>
            </div>
        </div>

        <div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('Search integrations...')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <nav class="flex gap-0 overflow-x-auto px-2" aria-label="Tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    :class="[
                        'relative flex items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-medium transition-colors',
                        activeTab === tab.key
                            ? 'text-primary-500'
                            : 'text-gray-500 hover:text-gray-700'
                    ]"
                >
                    <i :class="[tab.icon, 'text-base']"></i>
                    <span>{{ t(tab.label) }}</span>
                    <span
                        v-if="activeTab === tab.key"
                        class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary-500"
                    />
                </button>
            </nav>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <section
                v-for="[slug, integration] in filteredIntegrations"
                :key="slug"
                class="group rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition-all hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-gray-900"
            >
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                            <i class="ti ti-plug text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-[15px] font-semibold text-gray-900 dark:text-white">{{ integration.name }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t(tabs.find((tab) => tab.key === integration.tab)?.description ?? 'Utility integration') }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            v-if="integration.doc_url"
                            :href="integration.doc_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            :title="t('View API documentation')"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 shadow-sm transition-colors hover:border-gray-300 hover:text-gray-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </a>

                        <button
                            type="button"
                            :title="t('Toggle active')"
                            :class="form.integrations[slug].enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'"
                            class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                            @click="form.integrations[slug].enabled = !form.integrations[slug].enabled"
                        >
                            <span
                                :class="form.integrations[slug].enabled ? 'translate-x-5' : 'translate-x-0'"
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                            />
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div v-if="hasProviderSwitcher(integration, slug)" class="md:col-span-2">
                        <AppSelect
                            v-model="form.integrations[slug].provider"
                            :label="t('Provider')"
                            :options="providerOptions[slug]"
                        />
                    </div>
                    <div v-else class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Provider') }}</label>
                        <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">—</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Timeout (s)') }}</label>
                        <input
                            v-model="form.integrations[slug].timeout"
                            type="number"
                            min="5"
                            max="180"
                            :placeholder="t('30')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </div>

                    <div class="md:col-span-3">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Fixed Credit Cost') }}</label>
                        <input
                            v-model="form.integrations[slug].fixed_credit_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            :placeholder="t('0.00')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        />
                    </div>
                </div>

                <div
                    v-if="isDefaultAi(slug)"
                    class="mt-5 rounded-xl border border-violet-100 bg-violet-50/80 px-4 py-4 dark:border-violet-900/30 dark:bg-violet-900/10"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-sm text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                            <i class="ti ti-sparkles"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-violet-700 dark:text-violet-300">
                                {{ t('Using System Default AI Model') }}
                            </p>
                            <p class="mt-0.5 text-xs text-violet-600/80 dark:text-violet-200/80">
                                {{ t('This integration will use :provider as the fallback LLM. No dedicated API credentials are needed.', { provider: defaultAiName }) }}
                            </p>
                            <p class="mt-1.5 text-xs text-violet-600/70 dark:text-violet-200/70">
                                {{ t('Manage API keys in') }}
                                <Link :href="aiIndexUrl" class="underline hover:text-violet-700 dark:hover:text-violet-200">
                                    {{ t('Providers & Keys') }}
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-5 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 v-if="getProvider(slug)" class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ getProvider(slug)!.name }}
                                <span v-if="Object.keys(getProvider(slug)!.secrets).length + Object.keys(getProvider(slug)!.options).length === 0" class="font-normal text-gray-500"> — {{ t('No credentials required') }}</span>
                                <span v-else class="font-normal text-gray-500"> — {{ t('Credentials') }}</span>
                            </h3>
                        </div>

                        <button
                            type="button"
                            :disabled="testing[slug]"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-100 disabled:opacity-60 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200"
                            @click="testConnection(slug)"
                        >
                            <svg
                                v-if="testing[slug]"
                                class="h-3.5 w-3.5 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.916 4.794l1.757-1.757a4.5 4.5 0 10-6.364-6.364l-1.757 1.757" />
                            </svg>
                            {{ testing[slug] ? t('Testing...') : t('Test Connection') }}
                        </button>
                    </div>

                    <!-- Test Result -->
                    <div
                        v-if="testResults[slug]"
                        :class="testResults[slug].success ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/30 dark:bg-green-900/10 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/30 dark:bg-red-900/10 dark:text-red-200'"
                        class="mb-4 rounded-lg border px-4 py-3 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <span v-if="testResults[slug].success">✓</span>
                            <span v-else>✗</span>
                            <span>{{ testResults[slug].success ? testResults[slug].message : (testResults[slug].error || t('Connection failed')) }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <template v-if="getProvider(slug)">
                            <div
                                v-for="(_, secretKey) in getProvider(slug)!.secrets"
                                :key="secretKey"
                            >
                                <label class="mb-1.5 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                                    {{ String(secretKey).replaceAll('_', ' ') }}
                                </label>
                                <input
                                    v-model="form.integrations[slug].providers[form.integrations[slug].provider].secrets[secretKey]"
                                    type="text"
                                    autocomplete="new-password"
                                    :placeholder="t('Enter key...')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                    @focus="clearMaskedSecretOnFocus(slug, form.integrations[slug].provider, secretKey)"
                                />
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ isSecretConfigured(slug, secretKey) ? t('Encrypted value saved. Leave blank to keep it.') : t('Not configured yet.') }}
                                </p>
                            </div>

                            <div
                                v-for="(_, optionKey) in getProvider(slug)!.options"
                                :key="optionKey"
                            >
                                <label class="mb-1.5 block text-sm font-medium capitalize text-gray-700 dark:text-gray-300">
                                    {{ String(optionKey).replaceAll('_', ' ') }}
                                </label>
                                <input
                                    v-model="form.integrations[slug].providers[form.integrations[slug].provider].options[optionKey]"
                                    type="text"
                                    :placeholder="t('Enter value...')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <div
                v-if="filteredIntegrations.length === 0"
                class="col-span-full rounded-2xl border border-gray-200 bg-white p-12 text-center shadow-sm dark:border-surface-800 dark:bg-gray-900"
            >
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <h3 class="text-[15px] font-semibold text-[#111827]">
                    {{ searchQuery ? t('No integrations match your search') : t('No integrations in this category') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    {{ searchQuery ? t('Try adjusting your search terms.') : t('Integrations for this tab have not been configured yet.') }}
                </p>
            </div>
        </div>
    </div>
</template>
>
mplate>
>
