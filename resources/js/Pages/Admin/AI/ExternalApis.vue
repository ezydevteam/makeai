<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

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
    providers: Record<string, ProviderState>
}

const props = defineProps<{
    integrations: Record<string, IntegrationState>
}>()

const { t } = useTranslate()
const aiIndexUrl = route('admin.ai.index')

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
                                Object.keys(provider.secrets).map((secretKey) => [secretKey, ''])
                            ),
                        },
                    ])
                ),
            },
        ])
    ),
})

const providerEntries = (integration: IntegrationState) => Object.entries(integration.providers)

const selectedProvider = (slug: string) => {
    const selected = form.integrations[slug].provider

    return props.integrations[slug].providers[selected] ?? Object.values(props.integrations[slug].providers)[0]
}

const submit = () => {
    form.post(route('admin.ai.external-apis.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('External APIs — AI Management')" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <Link
                        :href="aiIndexUrl"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </Link>
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-medium text-violet-700">
                        {{ t('Part 15.12') }}
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ t('External API Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ t('Configure non-LLM providers used by plagiarism, search, transcript, media, and analysis tools.') }}
                </p>
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-500 disabled:opacity-60"
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
                {{ form.processing ? t('Saving...') : t('Save External APIs') }}
            </button>
        </div>

        <div class="mb-6 rounded-xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm text-blue-800 shadow-sm">
            {{ t('Secret fields are write-only. Leave them blank to keep the currently encrypted value.') }}
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <section
                v-for="(integration, slug) in integrations"
                :key="slug"
                class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-colors hover:border-primary-200"
            >
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-gray-900">{{ integration.name }}</h2>
                            <span
                                :class="form.integrations[slug].enabled ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600'"
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                            >
                                {{ form.integrations[slug].enabled ? t('Enabled') : t('Disabled') }}
                            </span>
                        </div>
                        <p class="font-mono text-xs text-gray-500">{{ integration.service }}</p>
                    </div>

                    <button
                        type="button"
                        :class="form.integrations[slug].enabled ? 'bg-primary-500' : 'bg-gray-200'"
                        class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                        @click="form.integrations[slug].enabled = !form.integrations[slug].enabled"
                    >
                        <span
                            :class="form.integrations[slug].enabled ? 'translate-x-5' : 'translate-x-0'"
                            class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                        />
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('Provider') }}</label>
                        <select
                            v-model="form.integrations[slug].provider"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                        >
                            <option
                                v-for="[providerSlug, provider] in providerEntries(integration)"
                                :key="providerSlug"
                                :value="providerSlug"
                            >
                                {{ provider.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('Timeout') }}</label>
                        <input
                            v-model="form.integrations[slug].timeout"
                            type="number"
                            min="5"
                            max="180"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                        />
                    </div>

                    <div class="md:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700">{{ t('Fixed Credit Cost') }}</label>
                        <input
                            v-model="form.integrations[slug].fixed_credit_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                        />
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">{{ selectedProvider(String(slug)).name }}</h3>
                            <p class="text-xs text-gray-500">{{ t('Provider credentials and options') }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div
                            v-for="(_, secretKey) in selectedProvider(String(slug)).secrets"
                            :key="secretKey"
                        >
                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                {{ String(secretKey).replaceAll('_', ' ') }}
                            </label>
                            <input
                                v-model="form.integrations[slug].providers[form.integrations[slug].provider].secrets[secretKey]"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="selectedProvider(String(slug)).secrets[secretKey].configured ? selectedProvider(String(slug)).secrets[secretKey].masked || t('Configured') : t('Not configured')"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ selectedProvider(String(slug)).secrets[secretKey].configured ? t('Encrypted value exists.') : t('No encrypted value saved yet.') }}
                            </p>
                        </div>

                        <div
                            v-for="(_, optionKey) in selectedProvider(String(slug)).options"
                            :key="optionKey"
                        >
                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                {{ String(optionKey).replaceAll('_', ' ') }}
                            </label>
                            <input
                                v-model="form.integrations[slug].providers[form.integrations[slug].provider].options[optionKey]"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 transition-colors focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-100"
                            />
                        </div>

                        <div
                            v-if="Object.keys(selectedProvider(String(slug)).secrets).length === 0 && Object.keys(selectedProvider(String(slug)).options).length === 0"
                            class="md:col-span-2 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-500"
                        >
                            {{ t('This provider does not require admin credentials.') }}
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="mt-8 flex justify-end">
            <button
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary-500 disabled:opacity-60"
                @click="submit"
            >
                {{ form.processing ? t('Saving...') : t('Save External APIs') }}
            </button>
        </div>
    </div>
</template>
