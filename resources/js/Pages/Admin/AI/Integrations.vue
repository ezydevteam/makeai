<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import type { SelectOption } from '@/Components/AppSelect.vue'
import { ref, computed } from 'vue'

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
    { key: 'image_media', label: 'Image & Media', icon: '🎨', description: 'Image generation, stock photos, editing tools' },
    { key: 'voice_audio', label: 'Voice & Audio', icon: '🎙️', description: 'TTS, STT, music generation, voice cloning' },
    { key: 'video', label: 'Video', icon: '🎬', description: 'Video generation and avatar creation' },
    { key: 'productivity', label: 'Productivity', icon: '⚡', description: 'Notion, Drive, WordPress, Slack, Zapier' },
    { key: 'utilities', label: 'Utilities', icon: '🔧', description: 'Search, translation, captcha, SMS, crypto' },
]

const activeTab = ref('image_media')
const searchQuery = ref('')

const defaultAiName = computed(() => {
    const cfg = props.configuredAiProviders[props.defaultAiProvider]
    return cfg ? `${cfg.name} (System Default)` : 'System Default AI Model'
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
                                    secret.configured ? '••••••••' : ''
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

const selectedProvider = (slug: string) => {
    const integration = props.integrations[slug]
    if (!integration) return null
    const selected = form.integrations[slug]?.provider
    if (selected === DEFAULT_AI_SENTINEL) return null
    const provs = integration.providers
    if (!provs) return null
    return provs[selected] ?? Object.values(provs)[0] ?? null
}

const selectedProviderFromProps = (slug: string) => {
    const integration = props.integrations[slug]
    if (!integration) return null
    const selected = integration.provider
    if (selected === DEFAULT_AI_SENTINEL) return null
    const provs = integration.providers
    if (!provs) return null
    return provs[selected] ?? Object.values(provs)[0] ?? null
}

const isDefaultAi = (slug: string) => {
    return form.integrations[slug]?.provider === DEFAULT_AI_SENTINEL
}

const isSecretConfigured = (slug: string, secretKey: string) => {
    const sp = selectedProviderFromProps(slug)
    if (!sp) return false
    return sp.secrets[secretKey]?.configured ?? false
}

const hasProviderSwitcher = (integration: IntegrationState, slug: string) => {
    return Object.keys(integration.providers).length > 0
}

const submit = () => {
    form.post(route('admin.ai.integrations.update'), {
        preserveScroll: true,
    })
}

const testConnection = async (slug: string) => {
    testing.value[slug] = true
    testResults.value[slug] = { success: false }

    try {
        const res = await fetch(route('admin.ai.integrations.test-connection', { integration: slug }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
        })
        const data = await res.json()
        testResults.value[slug] = data
    } catch {
        testResults.value[slug] = { success: false, error: 'Network error' }
    } finally {
        testing.value[slug] = false
    }
}

const enabledCount = computed(() =>
    Object.values(props.integrations).filter(i => i.enabled).length
)
</script>

<template>
    <Head :title="t('Integrations — AI Management')" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Header -->
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <Link
                        :href="aiIndexUrl"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition-colors hover:border-[#1F75FE]/30 hover:bg-[#EFF6FF] hover:text-[#1F75FE]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </Link>
                    <span class="rounded-full bg-[#EDE9FE] px-3 py-1 text-xs font-medium text-[#5B21B6]">
                        Integrations
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-[#111827]">{{ t('Integrations') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('Configure third-party service providers for image, voice, video, productivity, and utility tools.') }}
                    <span class="ml-2 rounded-full bg-[#DBEAFE] px-2 py-0.5 text-xs font-medium text-[#1F75FE]">
                        {{ enabledCount }} / {{ Object.keys(props.integrations).length }} enabled
                    </span>
                </p>
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-[#1F75FE] px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-[#1A65E0] hover:shadow-[0_0_20px_rgb(31_117_254_/_0.25)] hover:-translate-y-px active:translate-y-0 disabled:opacity-60"
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
                {{ form.processing ? t('Saving...') : t('Save All Changes') }}
            </button>
        </div>

        <!-- Info Banner -->
        <div class="mb-6 rounded-xl border border-[#DBEAFE] bg-[#EFF6FF] px-5 py-4 text-sm text-[#1A65E0] shadow-sm">
            <div class="flex items-start gap-2">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <span>{{ t('Each integration can use a dedicated provider or fall back to the system default AI model. Secret fields show •••••••• when already configured — only re-enter them to change the value.') }}</span>
            </div>
        </div>

        <!-- Search + Tab Bar -->
        <div class="mb-6 rounded-xl border border-gray-200 bg-white shadow-sm">
            <!-- Search -->
            <div class="border-b border-gray-200 px-5 py-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('Search integrations...')"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-4 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/10"
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

            <!-- Tabs -->
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
                    <span class="text-base">{{ tab.icon }}</span>
                    {{ t(tab.label) }}
                    <span
                        v-if="activeTab === tab.key"
                        class="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-primary-500"
                    />
                </button>
            </nav>
        </div>

        <!-- Integration Cards Grid -->
        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <section
                v-for="[slug, integration] in filteredIntegrations"
                :key="slug"
                class="group rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-all hover:border-primary-500/40 hover:shadow-md hover:shadow-primary-500/5"
            >
                <!-- Header Row -->
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-500/8 text-lg">
                            🔌
                        </div>
                        <div>
                            <h2 class="text-[15px] font-semibold text-[#111827]">{{ integration.name }}</h2>
                            <p class="mt-0.5 font-mono text-xs text-gray-400">{{ integration.service }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            v-if="integration.doc_url"
                            :href="integration.doc_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            :title="t('View API documentation')"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 shadow-sm transition-colors hover:border-gray-300 hover:text-gray-600"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </a>

                        <button
                            type="button"
                            :class="form.integrations[slug].enabled ? 'bg-[#1F75FE]' : 'bg-gray-200'"
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

                <!-- Config Row (Provider + Timeout + Credits) -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div v-if="hasProviderSwitcher(integration, slug)" class="md:col-span-2">
                        <AppSelect
                            v-model="form.integrations[slug].provider"
                            :label="t('Provider')"
                            :options="providerOptions[slug]"
                        />
                    </div>
                    <div v-else class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-[#374151]">{{ t('Provider') }}</label>
                        <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">—</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-[#374151]">{{ t('Timeout (s)') }}</label>
                        <input
                            v-model="form.integrations[slug].timeout"
                            type="number"
                            min="5"
                            max="180"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                        />
                    </div>

                    <div class="md:col-span-3">
                        <label class="mb-1.5 block text-sm font-medium text-[#374151]">{{ t('Fixed Credit Cost') }}</label>
                        <input
                            v-model="form.integrations[slug].fixed_credit_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                        />
                    </div>
                </div>

                <!-- Default AI Model Fallback Info -->
                <div
                    v-if="isDefaultAi(slug)"
                    class="mt-5 rounded-lg border border-[#EDE9FE] bg-[#F5F3FF] px-4 py-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#8B5CF6]/12 text-sm">
                            ✨
                        </div>
                        <div>
                            <p class="text-sm font-medium text-[#5B21B6]">
                                {{ t('Using System Default AI Model') }}
                            </p>
                            <p class="mt-0.5 text-xs text-[#7C3AED]/70">
                                {{ t('This integration will use :provider as the fallback LLM. No dedicated API credentials are needed.', { provider: defaultAiName }) }}
                            </p>
                            <p class="mt-1.5 text-xs text-[#7C3AED]/60">
                                {{ t('Manage API keys in') }}
                                <Link :href="aiIndexUrl" class="underline hover:text-[#5B21B6]">
                                    {{ t('Providers & Keys') }}
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Provider Credentials -->
                <div v-else class="mt-5 rounded-lg border border-gray-100 bg-gray-50/70 p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 v-if="selectedProvider(slug)" class="text-sm font-semibold text-[#111827]">
                                {{ selectedProvider(slug)!.name }}
                                <span v-if="Object.keys(selectedProvider(slug)!.secrets).length + Object.keys(selectedProvider(slug)!.options).length === 0" class="font-normal text-gray-500"> — {{ t('No credentials required') }}</span>
                                <span v-else class="font-normal text-gray-500"> — {{ t('Credentials') }}</span>
                            </h3>
                        </div>

                        <button
                            type="button"
                            :disabled="testing[slug]"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-100 disabled:opacity-60"
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
                        :class="testResults[slug].success ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'"
                        class="mb-4 rounded-lg border px-4 py-3 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <span v-if="testResults[slug].success">✓</span>
                            <span v-else>✗</span>
                            <span>{{ testResults[slug].success ? testResults[slug].message : (testResults[slug].error || t('Connection failed')) }}</span>
                        </div>
                    </div>

                    <!-- Credential fields -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <template v-if="selectedProvider(slug)">
                            <div
                                v-for="(_, secretKey) in selectedProvider(slug)!.secrets"
                                :key="secretKey"
                            >
                                <label class="mb-1.5 block text-sm font-medium capitalize text-[#374151]">
                                    {{ String(secretKey).replaceAll('_', ' ') }}
                                </label>
                                <input
                                    v-model="form.integrations[slug].providers[form.integrations[slug].provider].secrets[secretKey]"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="isSecretConfigured(slug, secretKey) ? t('•••••••• (already saved)') : t('Enter key...')"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                                />
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ isSecretConfigured(slug, secretKey) ? t('Encrypted value saved. Leave blank to keep it.') : t('Not configured yet.') }}
                                </p>
                            </div>

                            <div
                                v-for="(_, optionKey) in selectedProvider(slug)!.options"
                                :key="optionKey"
                            >
                                <label class="mb-1.5 block text-sm font-medium capitalize text-[#374151]">
                                    {{ String(optionKey).replaceAll('_', ' ') }}
                                </label>
                                <input
                                    v-model="form.integrations[slug].providers[form.integrations[slug].provider].options[optionKey]"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                                />
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- Empty state -->
            <div
                v-if="filteredIntegrations.length === 0"
                class="col-span-full rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm"
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
