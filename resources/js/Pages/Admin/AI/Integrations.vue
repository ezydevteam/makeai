<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import type { SelectOption } from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

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

type IntegrationTabKey = 'image_media' | 'voice_audio' | 'video' | 'productivity' | 'utilities'

const props = defineProps<{
    integrations: Record<string, IntegrationState>
    defaultAiProvider: string
    configuredAiProviders: Record<string, ConfiguredAiProvider>
}>()

const { t } = useTranslate()
const toast = useToastr()
const aiIndexUrl = route('admin.ai.index')

const DEFAULT_AI_SENTINEL = '__default_ai__'

const activeTab = ref<IntegrationTabKey>('image_media')
const searchQuery = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const testing = ref<Record<string, boolean>>({})
const testResults = ref<Record<string, { success: boolean; message?: string; error?: string }>>({})

const tabs = computed(() => [
    {
        key: 'image_media' as const,
        label: t('Image & Media'),
        icon: 'ti ti-photo',
        description: t('Image generation, stock photos, and editing tools'),
    },
    {
        key: 'voice_audio' as const,
        label: t('Voice & Audio'),
        icon: 'ti ti-wave-sine',
        description: t('Text-to-speech, speech-to-text, and audio tools'),
    },
    {
        key: 'video' as const,
        label: t('Video'),
        icon: 'ti ti-video',
        description: t('Video generation and avatar creation services'),
    },
    {
        key: 'productivity' as const,
        label: t('Productivity'),
        icon: 'ti ti-bolt',
        description: t('Workspace, publishing, and workflow integrations'),
    },
    {
        key: 'utilities' as const,
        label: t('Utilities'),
        icon: 'ti ti-tool',
        description: t('Search, translation, captcha, SMS, and support services'),
    },
])

const defaultAiName = computed(() => {
    const cfg = props.configuredAiProviders[props.defaultAiProvider]
    return cfg ? `${cfg.name} (${t('System Default')})` : t('System Default AI Model')
})

const tabCounts = computed<Record<IntegrationTabKey, number>>(() => {
    const counts: Record<IntegrationTabKey, number> = {
        image_media: 0,
        voice_audio: 0,
        video: 0,
        productivity: 0,
        utilities: 0,
    }

    for (const integration of Object.values(props.integrations)) {
        const tab = integration.tab as IntegrationTabKey | ''
        if (tab && tab in counts) {
            counts[tab as IntegrationTabKey] += 1
        } else {
            counts.utilities += 1
        }
    }

    return counts
})

const tabIntegrations = computed(() => {
    return Object.entries(props.integrations)
        .filter(([, integration]) => integration.tab === activeTab.value || (!integration.tab && activeTab.value === 'utilities'))
        .sort(([, a], [, b]) => a.name.localeCompare(b.name))
})

const filteredIntegrations = computed(() => {
    if (!searchQuery.value.trim()) {
        return tabIntegrations.value
    }

    const query = searchQuery.value.toLowerCase()

    return tabIntegrations.value.filter(([slug, integration]) => {
        return integration.name.toLowerCase().includes(query)
            || integration.service.toLowerCase().includes(query)
            || slug.toLowerCase().includes(query)
            || Object.values(integration.providers).some((provider) => provider.name.toLowerCase().includes(query))
    })
})

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
                                    secret.masked ?? '',
                                ]),
                            ),
                        },
                    ]),
                ),
            },
        ]),
    ),
})

const providerOptions = computed<Record<string, SelectOption[]>>(() => {
    const result: Record<string, SelectOption[]> = {}

    for (const [slug, integration] of Object.entries(props.integrations)) {
        const options: SelectOption[] = []

        if (integration.ai_fallback) {
            options.push({
                value: DEFAULT_AI_SENTINEL,
                label: `✨ ${defaultAiName.value}`,
            })
        }

        for (const [providerSlug, provider] of Object.entries(integration.providers)) {
            options.push({
                value: providerSlug,
                label: provider.name,
            })
        }

        result[slug] = options
    }

    return result
})

const getProvider = (slug: string, fromForm = true) => {
    const integration = props.integrations[slug]
    if (!integration) {
        return null
    }

    const selected = fromForm
        ? (form.integrations[slug]?.provider ?? integration.provider)
        : integration.provider

    if (selected === DEFAULT_AI_SENTINEL) {
        return null
    }

    return integration.providers[selected] ?? Object.values(integration.providers)[0] ?? null
}

const isDefaultAi = (slug: string) => form.integrations[slug]?.provider === DEFAULT_AI_SENTINEL

const isSecretConfigured = (slug: string, secretKey: string) => {
    const provider = getProvider(slug, false)
    return provider?.secrets[secretKey]?.configured ?? false
}

const maskedSecretValue = (slug: string, secretKey: string) => {
    const provider = getProvider(slug, false)
    return provider?.secrets[secretKey]?.masked ?? ''
}

const clearMaskedSecretOnFocus = (slug: string, providerSlug: string, secretKey: string) => {
    const maskedValue = maskedSecretValue(slug, secretKey)
    if (!maskedValue) {
        return
    }

    const currentValue = form.integrations[slug]?.providers[providerSlug]?.secrets[secretKey]
    if (currentValue === maskedValue) {
        form.integrations[slug].providers[providerSlug].secrets[secretKey] = ''
    }
}

const hasProviderSwitcher = (integration: IntegrationState) => Object.keys(integration.providers).length > 0

const hasMissingCredentialsForEnable = (slug: string) => {
    if (isDefaultAi(slug)) {
        return (props.configuredAiProviders[props.defaultAiProvider]?.key_count ?? 0) <= 0
    }

    const integration = props.integrations[slug]
    const providerSlug = form.integrations[slug]?.provider

    if (!integration || !providerSlug || providerSlug === DEFAULT_AI_SENTINEL) {
        return false
    }

    const provider = integration.providers[providerSlug]
    const formProvider = form.integrations[slug]?.providers[providerSlug]

    if (!provider || !formProvider) {
        return false
    }

    return Object.keys(provider.secrets).some((secretKey) => {
        const currentValue = String(formProvider.secrets[secretKey] ?? '').trim()
        const configured = provider.secrets[secretKey]?.configured ?? false
        return !configured && currentValue.length === 0
    })
}

const toggleIntegrationEnabled = (slug: string) => {
    const nextValue = !form.integrations[slug].enabled

    if (!nextValue) {
        form.integrations[slug].enabled = false
        return
    }

    if (hasMissingCredentialsForEnable(slug)) {
        toast.error(t('Add the required API credentials before enabling this integration.'))
        return
    }

    form.integrations[slug].enabled = true
}

const submit = () => {
    const hasBlockedIntegration = Object.entries(form.integrations).some(([slug, integration]) => {
        return integration.enabled && hasMissingCredentialsForEnable(slug)
    })

    if (hasBlockedIntegration) {
        toast.error(t('Add the required API credentials before enabling this integration.'))
        return
    }

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
                                    ]),
                                ),
                            },
                        ]),
                    ),
                },
            ]),
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

const clearSearch = () => {
    searchQuery.value = ''
}

const focusSearch = () => {
    searchInput.value?.focus()
    searchInput.value?.select()
}

const handleGlobalKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const isTextInputTarget = Boolean(
        target && (
            target.tagName === 'INPUT'
            || target.tagName === 'TEXTAREA'
            || target.tagName === 'SELECT'
            || target.isContentEditable
        ),
    )

    if (event.key === '/' && !isTextInputTarget) {
        event.preventDefault()
        focusSearch()
        return
    }

    if (event.key === 'Escape' && searchQuery.value) {
        clearSearch()
        if (document.activeElement === searchInput.value) {
            searchInput.value?.blur()
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGlobalKeydown)
})
</script>

<template>
    <Head :title="t('Integrations')" />

    <div class="py-6">
        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Integrations') }}</h1>
                    </div>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Configure third-party service providers for image, voice, video, productivity, and utility tools from one unified admin workspace.') }}
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="form.processing"
                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-60"
                    @click="submit"
                >
                    <svg
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <i v-else class="ti ti-device-floppy text-base"></i>
                    <span>{{ form.processing ? t('Saving...') : t('Save Changes') }}</span>
                </button>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Integration Library') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Browse integrations by category, filter locally, and manage provider-specific credentials in place.') }}</p>
                        </div>

                        <div class="w-full xl:max-w-md">
                            <div class="relative">
                                <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                                <input
                                    ref="searchInput"
                                    v-model="searchQuery"
                                    type="text"
                                    :placeholder="t('Search integrations...')"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-10 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                />
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    :aria-label="t('Clear search')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                                    @click="clearSearch"
                                >
                                    <i class="ti ti-x text-base"></i>
                                </button>
                                <span
                                    v-else
                                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:border-surface-700 dark:bg-surface-900"
                                >
                                    /
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                            :class="activeTab === tab.key
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700'"
                            @click="activeTab = tab.key"
                        >
                            <i :class="tab.icon" class="text-base"></i>
                            <span>{{ tab.label }}</span>
                            <span
                                :class="activeTab === tab.key
                                    ? 'bg-white/80 text-primary-700 dark:bg-primary-950/50 dark:text-primary-200'
                                    : 'bg-white text-gray-500 dark:bg-surface-700 dark:text-gray-300'"
                                class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold"
                            >
                                {{ tabCounts[tab.key] }}
                            </span>
                        </button>
                    </div>

                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ tabs.find((tab) => tab.key === activeTab)?.description }}
                    </p>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <section
                            v-for="[slug, integration] in filteredIntegrations"
                            :key="slug"
                            class="rounded-xl border border-gray-200 bg-white shadow-sm transition-all hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-surface-950/40"
                        >
                            <div class="border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                            <i class="ti ti-plug text-lg"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-[15px] font-semibold text-gray-900 dark:text-white">{{ integration.name }}</h3>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ tabs.find((tab) => tab.key === integration.tab)?.description ?? t('Utility integration') }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <a
                                            v-if="integration.doc_url"
                                            :href="integration.doc_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :title="t('View API documentation')"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 shadow-sm transition-colors hover:border-gray-300 hover:text-gray-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300"
                                        >
                                            <i class="ti ti-book-2 text-base"></i>
                                        </a>

                                        <button
                                            type="button"
                                            :title="t('Toggle active')"
                                            :class="form.integrations[slug].enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-surface-700'"
                                            class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                                            @click="toggleIntegrationEnabled(slug)"
                                        >
                                            <span
                                                :class="form.integrations[slug].enabled ? 'translate-x-5' : 'translate-x-0'"
                                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 p-6">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div v-if="hasProviderSwitcher(integration)" class="md:col-span-2">
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
                                    class="rounded-xl border border-violet-100 bg-violet-50/80 px-4 py-4 dark:border-violet-900/30 dark:bg-violet-900/10"
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

                                <div v-else class="rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <div>
                                            <h4 v-if="getProvider(slug)" class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ getProvider(slug)!.name }}
                                                <span v-if="Object.keys(getProvider(slug)!.secrets).length + Object.keys(getProvider(slug)!.options).length === 0" class="font-normal text-gray-500">
                                                    — {{ t('No credentials required') }}
                                                </span>
                                                <span v-else class="font-normal text-gray-500">
                                                    — {{ t('Credentials') }}
                                                </span>
                                            </h4>
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
                                            <i v-else class="ti ti-plug-connected text-sm"></i>
                                            <span>{{ testing[slug] ? t('Testing...') : t('Test Connection') }}</span>
                                        </button>
                                    </div>

                                    <div
                                        v-if="testResults[slug]"
                                        :class="testResults[slug].success
                                            ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/30 dark:bg-green-900/10 dark:text-green-200'
                                            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/30 dark:bg-red-900/10 dark:text-red-200'"
                                        class="mb-4 rounded-lg border px-4 py-3 text-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <i :class="testResults[slug].success ? 'ti ti-circle-check text-base' : 'ti ti-alert-circle text-base'"></i>
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
                            </div>
                        </section>

                        <div
                            v-if="filteredIntegrations.length === 0"
                            class="col-span-full rounded-xl border border-dashed border-gray-200 px-6 py-12 text-center dark:border-surface-700"
                        >
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                <i class="ti ti-search text-xl"></i>
                            </div>
                            <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ searchQuery ? t('No integrations match your search') : t('No integrations in this category') }}
                            </p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ searchQuery ? t('Try adjusting your search terms.') : t('Integrations for this tab have not been configured yet.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
