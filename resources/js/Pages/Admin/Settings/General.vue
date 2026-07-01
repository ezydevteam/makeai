<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import { computed } from 'vue'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

type GeneralSettings = {
    site_name: string
    site_tagline: string | null
    site_description: string | null
    site_support_email: string | null
    site_support_url: string | null
    site_terms_url: string | null
    site_privacy_url: string | null
    site_url: string | null
    default_language: string
    default_currency: string
    currency_symbol: string
    currency_position: 'before' | 'before_with_space' | 'after' | 'after_with_space'
    currency_decimals: number
    app_timezone: string
}

type LanguageOption = { code: string; name: string }
type CurrencyOption = { code: string; name: string; symbol: string | null }
const props = defineProps<{
    settings: GeneralSettings
    languages: LanguageOption[]
    currencies: CurrencyOption[]
    timezones: string[]
}>()

const { t } = useTranslate()

const form = useForm({ ...props.settings })
const formErrors = computed(() => form.errors as Record<string, string | undefined>)

const languageOptions = computed(() =>
    props.languages.map((language) => ({
        value: language.code,
        label: language.name,
    })),
)

const currencyOptions = computed(() =>
    props.currencies.map((currency) => ({
        value: currency.code,
        label: `${currency.code} - ${currency.name}`,
    })),
)

const timezoneOptions = computed(() =>
    props.timezones.map((timezone) => ({
        value: timezone,
        label: timezone,
    })),
)

const positionOptions = computed(() => [
    { value: 'before', label: t('Before amount') },
    { value: 'before_with_space', label: t('Before amount with space') },
    { value: 'after', label: t('After amount') },
    { value: 'after_with_space', label: t('After amount with space') },
])

const saveSettings = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('General Settings')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('General Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Manage site identity, brand assets, support links, locale defaults, and pricing display from one unified admin surface.') }}</p>
            </div>
            <button type="button" :disabled="form.processing" class="rounded-lg btn-primary disabled:opacity-60" @click="saveSettings">
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <form class="space-y-6" @submit.prevent="saveSettings">
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-5">
                    <h2 class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ t('Site Identity') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('These values define the public brand across navigation, emails, SEO surfaces, and footer areas.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Site name') }}
                        <input v-model="form.site_name" type="text" :placeholder="t('Enter site name')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.site_name" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_name }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Tagline') }}
                        <input v-model="form.site_tagline" type="text" :placeholder="t('Enter site tagline')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                        {{ t('Description') }}
                        <textarea v-model="form.site_description" rows="3" :placeholder="t('Describe your site for visitors and search previews')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-5">
                    <h2 class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ t('Site URL & Links') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Keep your canonical address, support entry points, and policy destinations easy to maintain from a single panel.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Site URL') }}
                        <input v-model="form.site_url" type="url" :placeholder="t('https://example.com')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.site_url" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_url }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Support email') }}
                        <input v-model="form.site_support_email" type="email" :placeholder="t('support@example.com')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Support URL') }}
                        <input v-model="form.site_support_url" type="url" :placeholder="t('https://example.com/support')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Terms of Service URL') }}
                        <input v-model="form.site_terms_url" type="url" :placeholder="t('https://example.com/terms')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                        {{ t('Privacy Policy URL') }}
                        <input v-model="form.site_privacy_url" type="url" :placeholder="t('https://example.com/privacy')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Language, Currency & Timezone') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Set sensible defaults for first-time visitors and keep price formatting aligned across the product.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <AppSelect v-model="form.default_language" :label="t('Default language')" :options="languageOptions" :live-search="true" />

                    <AppSelect v-model="form.app_timezone" :label="t('Timezone')" :options="timezoneOptions" :live-search="true" />

                    <AppSelect v-model="form.default_currency" :label="t('Default currency')" :options="currencyOptions" :live-search="true" />

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency symbol') }}
                        <input v-model="form.currency_symbol" type="text" :placeholder="t('Enter currency symbol')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <AppSelect v-model="form.currency_position" :label="t('Currency position')" :options="positionOptions" />

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency decimals') }}
                        <input v-model.number="form.currency_decimals" type="number" min="0" max="4" :placeholder="t('Enter decimal places')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>
        </form>
    </div>
</template>
