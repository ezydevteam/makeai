<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import type { SelectOption } from '@/Components/UI/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

interface SecretState {
    configured: boolean
    masked: string | null
}

interface IntegrationSettingField {
    key: string
    type: 'select' | 'boolean'
    label: string
    help?: string
    default?: string | boolean
    options?: { value: string; label: string }[]
    value: string | boolean | null
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
    description: string | null
    settings: IntegrationSettingField[]
    provider: string
    timeout: number
    fixed_credit_cost: string
    group: string
    billable: boolean
    doc_url: string | null
    ai_fallback: boolean
    providers: Record<string, ProviderState>
}

interface ConfiguredAiProvider {
    name: string
    key_count: number
}

const props = withDefaults(defineProps<{
    integrations: Record<string, IntegrationState>
    title: string
    description: string
    updateRouteName: string
    testRouteName: string
    defaultAiProvider?: string
    configuredAiProviders?: Record<string, ConfiguredAiProvider>
}>(), {
    defaultAiProvider: '',
    configuredAiProviders: () => ({}),
})

const { t } = useTranslate()
const toast = useToastr()
const aiIndexUrl = route('admin.ai.index')

const DEFAULT_AI_SENTINEL = '__default_ai__'

const testing = ref<Record<string, boolean>>({})
const testResults = ref<Record<string, { success: boolean; message?: string; error?: string }>>({})

const defaultAiName = computed(() => {
    const cfg = props.configuredAiProviders[props.defaultAiProvider]
    return cfg ? `${cfg.name} (${t('System Default')})` : t('System Default AI Model')
})

const sortedIntegrations = computed(() =>
    Object.entries(props.integrations).sort(([, a], [, b]) => a.name.localeCompare(b.name)),
)

const form = useForm({
    integrations: Object.fromEntries(
        Object.entries(props.integrations).map(([slug, integration]) => [
            slug,
            {
                enabled: integration.enabled,
                provider: integration.provider,
                timeout: integration.timeout,
                fixed_credit_cost: integration.fixed_credit_cost,
                settings: Object.fromEntries(
                    (integration.settings ?? []).map((field) => [
                        field.key,
                        field.value ?? field.default ?? (field.type === 'boolean' ? false : ''),
                    ]),
                ) as Record<string, any>,
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
    form.integrations[slug].enabled = !form.integrations[slug].enabled
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
    })).post(route(props.updateRouteName), {
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
        const res = await fetch(route(props.testRouteName, { integration: slug }), {
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

        testResults.value[slug] = await res.json()
    } catch {
        testResults.value[slug] = { success: false, error: t('Network error') }
    } finally {
        testing.value[slug] = false
    }
}
</script>

<template>
    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ description }}</p>
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="shrink-0 btn-primary-admin inline-flex items-center justify-center gap-2 disabled:opacity-60"
                @click="submit"
            >
                <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <i v-else class="ti ti-device-floppy text-base"></i>
                <span>{{ form.processing ? t('Saving...') : t('Save Changes') }}</span>
            </button>
        </section>

        <div class="columns-1 md:columns-2 gap-6">
            <section
                v-for="[slug, integration] in sortedIntegrations"
                :key="slug"
                class="break-inside-avoid inline-block w-full mb-5 rounded-2xl border border-gray-200 bg-white shadow-sm transition-all hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-surface-950/40"
            >
                <div class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                                <i class="ti ti-plug text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-[15px] font-semibold text-gray-900 dark:text-white">{{ integration.name }}</h3>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ integration.description || t('Utility integration') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <AppSwitch
                                :model-value="form.integrations[slug].enabled"
                                @update:model-value="toggleIntegrationEnabled(slug)"
                            />
                        </div>
                    </div>
                </div>

                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="max-h-0 opacity-0 overflow-hidden"
                    enter-to-class="max-h-[1000px] opacity-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="max-h-[1000px] opacity-100"
                    leave-to-class="max-h-0 opacity-0 overflow-hidden"
                >
                    <div v-show="form.integrations[slug].enabled" class="border-t border-gray-100 dark:border-surface-850 space-y-5 p-6 overflow-hidden">
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

                            <div v-if="integration.billable" class="md:col-span-3">
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

                        <!-- Integration-level behavioral settings (e.g. moderation mode) -->
                        <div v-if="integration.settings.length" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div v-for="field in integration.settings" :key="field.key" :class="{ 'md:col-span-2': field.type === 'boolean' }">
                                <template v-if="field.type === 'select'">
                                    <AppSelect
                                        v-model="form.integrations[slug].settings[field.key]"
                                        :label="t(field.label)"
                                        :options="(field.options ?? []).map((o) => ({ value: o.value, label: t(o.label) }))"
                                    />
                                    <p v-if="field.help" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ t(field.help) }}</p>
                                </template>
                                <template v-else-if="field.type === 'boolean'">
                                    <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-surface-700 dark:bg-surface-800">
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t(field.label) }}</p>
                                            <p v-if="field.help" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ t(field.help) }}</p>
                                        </div>
                                        <AppSwitch v-model="form.integrations[slug].settings[field.key]" />
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div
                            v-if="isDefaultAi(slug)"
                            class="rounded-2xl border border-violet-100 bg-violet-50/80 px-4 py-4 dark:border-violet-900/30 dark:bg-violet-900/10 font-sans"
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

                        <div v-else class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
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
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 shadow-sm transition-all hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
                                    @click="testConnection(slug)"
                                >
                                    <svg v-if="testing[slug]" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                    </svg>
                                    <i v-else class="ti ti-plug-connected text-sm"></i>
                                    <span :title="t('Test connection')">{{ testing[slug] ? t('Testing...') : t('Test') }}</span>
                                </button>
                            </div>

                            <div
                                v-if="testResults[slug]"
                                :class="testResults[slug].success
                                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-900/30 dark:bg-green-900/10 dark:text-green-200'
                                    : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/30 dark:bg-red-900/10 dark:text-red-200'"
                                class="mb-4 rounded-xl border px-4 py-3 text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <i :class="testResults[slug].success ? 'ti ti-circle-check text-base' : 'ti ti-alert-circle text-base'"></i>
                                    <span>{{ testResults[slug].success ? testResults[slug].message : (testResults[slug].error || t('Connection failed')) }}</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <template v-if="getProvider(slug)">
                                    <div
                                        v-for="(_, secretKey, index) in getProvider(slug)!.secrets"
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
                                        <div class="mt-1 flex flex-wrap items-center justify-between gap-2 text-xs">
                                            <span class="text-gray-400">
                                                {{ isSecretConfigured(slug, secretKey) ? t('Encrypted value saved. Leave blank to keep it.') : t('Not configured yet.') }}
                                            </span>
                                            <a
                                                v-if="index === 0 && integration.doc_url"
                                                :href="integration.doc_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium inline-flex items-center gap-1"
                                            >
                                                <span>{{ t('View Documentation') }}</span>
                                                <i class="ti ti-external-link text-[10px]"></i>
                                            </a>
                                        </div>
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
                </Transition>
            </section>

            <div
                v-if="sortedIntegrations.length === 0"
                class="col-span-full rounded-2xl border border-dashed border-gray-200 px-6 py-12 text-center dark:border-surface-700"
            >
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                    <i class="ti ti-plug text-xl"></i>
                </div>
                <p class="mt-4 text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ t('Nothing configured yet') }}
                </p>
            </div>
        </div>
    </div>
</template>
