<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed } from 'vue'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

type GeneralSettings = {
    site_name: string
    site_tagline: string | null
    site_description: string | null
    site_logo_light: string | null
    site_logo_dark: string | null
    site_favicon_ico: string | null
    site_favicon_png: string | null
    site_og_image: string | null
    site_copyright_text: string | null
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

const logoLightFile = ref<File | null>(null)
const logoDarkFile = ref<File | null>(null)
const faviconIcoFile = ref<File | null>(null)
const faviconPngFile = ref<File | null>(null)
const ogImageFile = ref<File | null>(null)

const languageOptions = computed(() =>
    props.languages.map((l) => ({
        value: l.code,
        label: l.name,
    })),
)

const currencyOptions = computed(() =>
    props.currencies.map((c) => ({
        value: c.code,
        label: `${c.code} - ${c.name}`,
    })),
)

const timezoneOptions = computed(() =>
    props.timezones.map((t) => ({
        value: t,
        label: t,
    })),
)

const positionOptions = [
    { value: 'before', label: 'Before amount' },
    { value: 'before_with_space', label: 'Before amount with space' },
    { value: 'after', label: 'After amount' },
    { value: 'after_with_space', label: 'After amount with space' },
]

function fileUrl(path: string | null): string | null {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://')) return path
    return '/storage/' + path
}

const saveSettings = () => {
    form.transform((data) => ({
        ...data,
        site_logo_light_file: logoLightFile.value,
        site_logo_dark_file: logoDarkFile.value,
        site_favicon_ico_file: faviconIcoFile.value,
        site_favicon_png_file: faviconPngFile.value,
        site_og_image_file: ogImageFile.value,
    })).post(route('admin.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('General Settings')" />

    <div class="mx-auto max-w-5xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('General Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Manage your site identity, branding, locale, currency, and timezone defaults.') }}</p>
        </div>

        <form class="space-y-6" @submit.prevent="saveSettings">
            <!-- Site Identity -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Site Identity') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('These values are shown to your visitors across the site, emails, and metadata.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Site name') }}
                        <input v-model="form.site_name" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.site_name" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_name }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Tagline') }}
                        <input v-model="form.site_tagline" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="md:col-span-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Description') }}
                        <textarea v-model="form.site_description" rows="2" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </label>

                    <!-- Logo Light -->
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Logo (light mode)') }}
                        <div v-if="form.site_logo_light" class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                            <div class="flex items-center gap-3">
                                <img :src="fileUrl(form.site_logo_light)!" alt="Logo light" class="h-10 max-w-[160px] object-contain">
                                <button type="button" @click="form.site_logo_light = ''; logoLightFile = null" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                            </div>
                        </div>
                        <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp" @input="logoLightFile = ($event.target as HTMLInputElement).files?.[0] || null" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400">
                        <span v-if="form.errors.site_logo_light_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_logo_light_file }}</span>
                    </label>

                    <!-- Logo Dark -->
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Logo (dark mode)') }}
                        <div v-if="form.site_logo_dark" class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                            <div class="flex items-center gap-3">
                                <img :src="fileUrl(form.site_logo_dark)!" alt="Logo dark" class="h-10 max-w-[160px] object-contain">
                                <button type="button" @click="form.site_logo_dark = ''; logoDarkFile = null" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                            </div>
                        </div>
                        <input type="file" accept="image/png,image/svg+xml,image/jpeg,image/webp" @input="logoDarkFile = ($event.target as HTMLInputElement).files?.[0] || null" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400">
                        <span v-if="form.errors.site_logo_dark_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_logo_dark_file }}</span>
                    </label>

                    <!-- Favicon ICO -->
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Favicon (ICO)') }}
                        <div v-if="form.site_favicon_ico" class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                            <div class="flex items-center gap-3">
                                <img :src="fileUrl(form.site_favicon_ico)!" alt="Favicon ICO" class="h-8 w-8">
                                <button type="button" @click="form.site_favicon_ico = ''; faviconIcoFile = null" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                            </div>
                        </div>
                        <input type="file" accept=".ico,image/x-icon" @input="faviconIcoFile = ($event.target as HTMLInputElement).files?.[0] || null" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400">
                        <span v-if="form.errors.site_favicon_ico_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_favicon_ico_file }}</span>
                    </label>

                    <!-- Favicon PNG -->
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Favicon (PNG)') }}
                        <div v-if="form.site_favicon_png" class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                            <div class="flex items-center gap-3">
                                <img :src="fileUrl(form.site_favicon_png)!" alt="Favicon PNG" class="h-8 w-8">
                                <button type="button" @click="form.site_favicon_png = ''; faviconPngFile = null" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                            </div>
                        </div>
                        <input type="file" accept="image/png" @input="faviconPngFile = ($event.target as HTMLInputElement).files?.[0] || null" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400">
                        <span v-if="form.errors.site_favicon_png_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_favicon_png_file }}</span>
                    </label>

                    <!-- OG Image -->
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('OG / Social image') }}
                        <div v-if="form.site_og_image" class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-surface-700 dark:bg-surface-800">
                            <div class="flex items-center gap-3">
                                <img :src="fileUrl(form.site_og_image)!" alt="OG image" class="h-12 max-w-[200px] rounded object-cover">
                                <button type="button" @click="form.site_og_image = ''; ogImageFile = null" class="text-xs font-medium text-danger-500 hover:underline">{{ t('Remove') }}</button>
                            </div>
                        </div>
                        <input type="file" accept="image/png,image/jpeg,image/webp" @input="ogImageFile = ($event.target as HTMLInputElement).files?.[0] || null" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400">
                        <span v-if="form.errors.site_og_image_file" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_og_image_file }}</span>
                    </label>

                    <!-- Copyright -->
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Copyright text') }}
                        <input v-model="form.site_copyright_text" type="text" :placeholder="'© {year} ' + (form.site_name || 'MySite') + '. All rights reserved.'" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>

            <!-- Site URL & Links -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Site URL & Links') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Configure the base URL and support / legal page links.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Site URL') }}
                        <input v-model="form.site_url" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <span v-if="form.errors.site_url" class="mt-1 block text-xs text-danger-600">{{ form.errors.site_url }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Support email') }}
                        <input v-model="form.site_support_email" type="email" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Support URL') }}
                        <input v-model="form.site_support_url" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Terms of Service URL') }}
                        <input v-model="form.site_terms_url" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Privacy Policy URL') }}
                        <input v-model="form.site_privacy_url" type="url" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>

            <!-- Localization -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Localization Defaults') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Choose the default language, currency display, and timezone for new visitors.') }}</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <AppSelect v-model="form.default_language" :label="t('Default language')" :options="languageOptions" :live-search="true" />

                    <AppSelect v-model="form.app_timezone" :label="t('Timezone')" :options="timezoneOptions" :live-search="true" />

                    <AppSelect v-model="form.default_currency" :label="t('Default currency')" :options="currencyOptions" :live-search="true" />

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency symbol') }}
                        <input v-model="form.currency_symbol" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <AppSelect v-model="form.currency_position" :label="t('Currency position')" :options="positionOptions" />

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Currency decimals') }}
                        <input v-model.number="form.currency_decimals" type="number" min="0" max="4" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg btn-primary shadow-lg transition-colors disabled:opacity-60">
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
