<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

interface GdprSettings {
    enabled: boolean
    eu_only: boolean
    banner_position: string
    banner_title: string
    banner_description: string
    banner_accept_all_text: string
    banner_customize_text: string
    banner_necessary_text: string
    banner_save_text: string
    banner_bg_color: string
    banner_text_color: string
    banner_button_color: string
    banner_button_text_color: string
    show_policy_links: boolean
    privacy_policy_url: string
    cookie_policy_url: string
}

const props = defineProps<{
    gdpr: GdprSettings
}>()

const { t } = useTranslate()
const form = useForm({ ...props.gdpr })

const positionOptions = [
    { value: 'bottom', label: t('Bottom Banner') },
    { value: 'top', label: t('Top Banner') },
    { value: 'center', label: t('Center Modal') },
]

const saveSettings = () => {
    form.post(route('admin.gdpr.settings.update'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('GDPR Settings')" />

    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('GDPR & Cookie Consent') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure banner visibility, consent copy, colors, and policy links from one unified compliance settings page.') }}</p>
            </div>
            <button type="button" :disabled="form.processing" class="shrink-0 btn-primary-admin disabled:opacity-60" @click="saveSettings">
                <i class="ti ti-device-floppy"></i>
                {{ form.processing ? t('Saving...') : t('Save Changes') }}
            </button>
        </div>

        <form class="space-y-6" @submit.prevent="saveSettings">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Status & Scope') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control whether cookie consent is active, which visitors see it, and how it enters the page.') }}</p>
                        </div>

                        <div class="space-y-4">
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable Global GDPR Cookie Consent') }}</span>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Show the granular cookie consent banner to visitors.') }}</p>
                                </div>
                                <AppSwitch v-model="form.enabled" />
                            </label>

                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('EU / EEA Countries Only') }}</span>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Only show the banner to EU and EEA visitors using your configured geolocation source.') }}</p>
                                </div>
                                <AppSwitch v-model="form.eu_only" />
                            </label>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Banner Position') }}</label>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <button
                                        v-for="opt in positionOptions"
                                        :key="opt.value"
                                        type="button"
                                        class="rounded-xl border px-4 py-3 text-sm font-medium transition-colors"
                                        :class="form.banner_position === opt.value
                                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-900/20 dark:text-primary-400'
                                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                                        @click="form.banner_position = opt.value"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Banner Content') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Customize the consent message and action labels shown to visitors.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                                {{ t('Title') }}
                                <input v-model="form.banner_title" type="text" :placeholder="t('Enter banner title')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                                {{ t('Description') }}
                                <textarea v-model="form.banner_description" rows="4" :placeholder="t('Explain how cookies are used on your site')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Accept All Button') }}
                                <input v-model="form.banner_accept_all_text" type="text" :placeholder="t('Accept All')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Customize Button') }}
                                <input v-model="form.banner_customize_text" type="text" :placeholder="t('Customize')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Necessary Only Button') }}
                                <input v-model="form.banner_necessary_text" type="text" :placeholder="t('Necessary Only')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Save Preferences Button') }}
                                <input v-model="form.banner_save_text" type="text" :placeholder="t('Save Preferences')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>
                        </div>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Banner Colors') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Tune the banner surface, text, and primary button colors to match your branding.') }}</p>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <AppColorPicker v-model="form.banner_bg_color" :label="t('Background Color')" />
                            <AppColorPicker v-model="form.banner_text_color" :label="t('Text Color')" />
                            <AppColorPicker v-model="form.banner_button_color" :label="t('Button Color')" />
                            <AppColorPicker v-model="form.banner_button_text_color" :label="t('Button Text Color')" />
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Policy Links') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose whether policy links appear in the banner and define where they point.') }}</p>
                        </div>

                        <div class="space-y-5">
                            <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/60">
                                <div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Privacy & Cookie Policy links on banner') }}</span>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Add direct links so visitors can review your policies before accepting preferences.') }}</p>
                                </div>
                                <AppSwitch v-model="form.show_policy_links" />
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Privacy Policy URL') }}
                                <input v-model="form.privacy_policy_url" type="text" :placeholder="t('https://example.com/privacy')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Cookie Policy URL') }}
                                <input v-model="form.cookie_policy_url" type="text" :placeholder="t('https://example.com/cookies')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                            </label>
                        </div>
                    </section>
                </div>
            </div>
        </form>
    </div>
</template>
