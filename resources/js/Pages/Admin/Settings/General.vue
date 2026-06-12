<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import { computed, ref } from 'vue'

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
type BrandAssetField =
    | 'site_logo_light'
    | 'site_logo_dark'
    | 'site_favicon_ico'
    | 'site_favicon_png'
    | 'site_og_image'
type BrandAssetErrorField =
    | 'site_logo_light_file'
    | 'site_logo_dark_file'
    | 'site_favicon_ico_file'
    | 'site_favicon_png_file'
    | 'site_og_image_file'

const props = defineProps<{
    settings: GeneralSettings
    languages: LanguageOption[]
    currencies: CurrencyOption[]
    timezones: string[]
}>()

const { t } = useTranslate()

const form = useForm({ ...props.settings })
const formErrors = computed(() => form.errors as Record<string, string | undefined>)

const logoLightFile = ref<File | null>(null)
const logoDarkFile = ref<File | null>(null)
const faviconIcoFile = ref<File | null>(null)
const faviconPngFile = ref<File | null>(null)
const ogImageFile = ref<File | null>(null)

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

const brandAssetCards = computed(() => [
    {
        key: 'site_logo_light' as BrandAssetField,
        title: t('Logo Light'),
        description: t('Used on light surfaces, emails, and default navigation states.'),
        accept: 'image/png,image/svg+xml,image/jpeg,image/webp',
        previewClass: 'h-10 max-w-[160px] object-contain',
        alt: t('Site logo for light mode'),
        fileRef: logoLightFile,
        errorKey: 'site_logo_light_file' as BrandAssetErrorField,
    },
    {
        key: 'site_logo_dark' as BrandAssetField,
        title: t('Logo Dark'),
        description: t('Used on dark surfaces, sidebars, and dark-mode headers.'),
        accept: 'image/png,image/svg+xml,image/jpeg,image/webp',
        previewClass: 'h-10 max-w-[160px] object-contain',
        alt: t('Site logo for dark mode'),
        fileRef: logoDarkFile,
        errorKey: 'site_logo_dark_file' as BrandAssetErrorField,
    },
    {
        key: 'site_favicon_ico' as BrandAssetField,
        title: t('Favicon ICO'),
        description: t('Classic browser icon for broad desktop and legacy support.'),
        accept: '.ico,image/x-icon',
        previewClass: 'h-8 w-8 object-contain',
        alt: t('Favicon ICO preview'),
        fileRef: faviconIcoFile,
        errorKey: 'site_favicon_ico_file' as BrandAssetErrorField,
    },
    {
        key: 'site_favicon_png' as BrandAssetField,
        title: t('Favicon PNG'),
        description: t('Preferred high-resolution favicon for modern browsers and devices.'),
        accept: 'image/png',
        previewClass: 'h-8 w-8 object-contain',
        alt: t('Favicon PNG preview'),
        fileRef: faviconPngFile,
        errorKey: 'site_favicon_png_file' as BrandAssetErrorField,
    },
    {
        key: 'site_og_image' as BrandAssetField,
        title: t('OG Image'),
        description: t('Shown when your links are shared across social platforms and chat apps.'),
        accept: 'image/png,image/jpeg,image/webp',
        previewClass: 'h-12 max-w-[200px] rounded-lg object-cover',
        alt: t('Open Graph image preview'),
        fileRef: ogImageFile,
        errorKey: 'site_og_image_file' as BrandAssetErrorField,
    },
])

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

    <div class="mx-auto max-w-7xl px-6 py-8">
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
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <div class="space-y-6">
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

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                                {{ t('Copyright text') }}
                                <input
                                    v-model="form.site_copyright_text"
                                    type="text"
                                    :placeholder="t('Example: :copy :year :name. All rights reserved.', { copy: '©', year: '{year}', name: form.site_name || t('Your Site') })"
                                    class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                >
                            </label>

                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                        <div class="mb-5">
                            <h2 class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ t('Logos & Social Images') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Upload a complete asset set so the storefront, admin shell, browser tabs, and social cards stay visually consistent.') }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <article v-for="asset in brandAssetCards" :key="asset.key" class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60" :class="{ 'md:col-span-2': asset.key === 'site_og_image' }">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ asset.title }}</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ asset.description }}</p>
                                    </div>
                                </div>

                                <div v-if="form[asset.key]" class="mt-4 rounded-xl border border-dashed border-gray-200 bg-white p-3 dark:border-surface-700 dark:bg-surface-900/80">
                                    <div class="flex items-center gap-3">
                                        <img :src="fileUrl(form[asset.key])!" :alt="asset.alt" :class="asset.previewClass">
                                        <button type="button" class="text-xs font-medium text-danger-500 hover:underline" @click="form[asset.key] = ''; asset.fileRef.value = null">
                                            {{ t('Remove') }}
                                        </button>
                                    </div>
                                </div>

                                <input
                                    type="file"
                                    :accept="asset.accept"
                                    class="mt-4 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white file:mr-3 file:border-0 file:bg-primary-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary-600 dark:file:bg-primary-900/30 dark:file:text-primary-400"
                                    @input="asset.fileRef.value = ($event.target as HTMLInputElement).files?.[0] || null"
                                >
                                <span v-if="formErrors[asset.errorKey]" class="mt-1 block text-xs text-danger-600">{{ formErrors[asset.errorKey] }}</span>
                            </article>
                        </div>
                    </section>

                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                        <div class="mb-5">
                            <h2 class="mt-2 text-lg font-bold text-gray-900 dark:text-white">{{ t('Site URL & Links') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Keep your canonical address, support entry points, and policy destinations easy to maintain from a single panel.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-5">
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

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
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

                        <div class="grid grid-cols-1 gap-5">
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
                </div>
            </div>
        </form>
    </div>
</template>
