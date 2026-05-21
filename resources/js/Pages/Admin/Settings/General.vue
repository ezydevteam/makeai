<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

type GeneralSettings = {
    app_name: string
    app_url: string | null
    default_language: string
    default_currency: string
    currency_symbol: string
    currency_position: 'before' | 'before_with_space' | 'after' | 'after_with_space'
    currency_decimals: number
    app_timezone: string
}

type LanguageOption = {
    code: string
    name: string
}

type CurrencyOption = {
    code: string
    name: string
    symbol: string | null
}

const props = defineProps<{
    settings: GeneralSettings
    languages: LanguageOption[]
    currencies: CurrencyOption[]
    timezones: string[]
}>()

const { t } = useTranslate()

const form = useForm({
    ...props.settings,
})

const saveSettings = () => {
    form.post(route('admin.settings.update'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('General Settings')" />

    <div class="mx-auto max-w-5xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('General Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Manage platform identity, locale, currency, and timezone defaults.') }}</p>
        </div>

        <form class="space-y-6" @submit.prevent="saveSettings">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Platform Identity') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('These values are shared across the admin panel, frontend, emails, and metadata.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Application name') }}
                        <input v-model="form.app_name" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.app_name" class="mt-1 block text-xs text-danger-600">{{ form.errors.app_name }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Application URL') }}
                        <input v-model="form.app_url" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.app_url" class="mt-1 block text-xs text-danger-600">{{ form.errors.app_url }}</span>
                    </label>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Localization Defaults') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Choose the default language, currency display, and timezone for new visitors.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Default language') }}
                        <select v-model="form.default_language" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <option v-for="language in languages" :key="language.code" :value="language.code">
                                {{ language.name }}
                            </option>
                        </select>
                        <span v-if="form.errors.default_language" class="mt-1 block text-xs text-danger-600">{{ form.errors.default_language }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Timezone') }}
                        <select v-model="form.app_timezone" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <option v-for="timezone in timezones" :key="timezone" :value="timezone">
                                {{ timezone }}
                            </option>
                        </select>
                        <span v-if="form.errors.app_timezone" class="mt-1 block text-xs text-danger-600">{{ form.errors.app_timezone }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Default currency') }}
                        <select v-model="form.default_currency" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <option v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                {{ currency.code }} - {{ currency.name }}
                            </option>
                        </select>
                        <span v-if="form.errors.default_currency" class="mt-1 block text-xs text-danger-600">{{ form.errors.default_currency }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency symbol') }}
                        <input v-model="form.currency_symbol" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.currency_symbol" class="mt-1 block text-xs text-danger-600">{{ form.errors.currency_symbol }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency position') }}
                        <select v-model="form.currency_position" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            <option value="before">{{ t('Before amount') }}</option>
                            <option value="before_with_space">{{ t('Before amount with space') }}</option>
                            <option value="after">{{ t('After amount') }}</option>
                            <option value="after_with_space">{{ t('After amount with space') }}</option>
                        </select>
                        <span v-if="form.errors.currency_position" class="mt-1 block text-xs text-danger-600">{{ form.errors.currency_position }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency decimals') }}
                        <input v-model.number="form.currency_decimals" type="number" min="0" max="4" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.currency_decimals" class="mt-1 block text-xs text-danger-600">{{ form.errors.currency_decimals }}</span>
                    </label>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-colors hover:bg-primary-500 disabled:opacity-60">
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>{{ form.processing ? t('Saving...') : t('Save General Settings') }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
