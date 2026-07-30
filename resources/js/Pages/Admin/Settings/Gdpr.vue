<script setup lang="ts">
import { computed } from 'vue'
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
    { value: 'bottom', icon: 'ti-layout-bottombar', label: t('Bottom Banner'), description: t('Full-width bar pinned to the bottom, inline with the page — no dimmed backdrop.') },
    { value: 'top', icon: 'ti-layout-navbar', label: t('Top Banner'), description: t('Floating card at the top of the page — no dimmed backdrop.') },
    { value: 'center', icon: 'ti-layout-distribute-horizontal', label: t('Center Modal'), description: t('Centred modal over a dimmed backdrop that blocks the page.') },
]

const consentModeOptions = [
    { value: 'disabled', icon: 'ti-shield-off', label: t('Disabled'), description: t('No cookie consent banner is shown to anyone.') },
    { value: 'eu', icon: 'ti-flag', label: t('EU / EEA Visitors Only'), description: t('Show the banner only to visitors your geolocation source places in the EU or EEA.') },
    { value: 'global', icon: 'ti-world', label: t('All Visitors'), description: t('Show the banner to every visitor, regardless of country.') },
]

// The two stored booleans (enabled + eu_only) collapse into one three-way choice.
// Turning consent off leaves eu_only untouched so the scope is remembered if it
// is switched back on.
const consentMode = computed<string>({
    get: () => (!form.enabled ? 'disabled' : form.eu_only ? 'eu' : 'global'),
    set: (value) => {
        form.enabled = value !== 'disabled'
        if (value === 'eu') form.eu_only = true
        if (value === 'global') form.eu_only = false
    },
})

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

                        <div class="space-y-5">
                            <div>
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cookie Consent Mode') }}</span>
                                <div class="grid gap-3 md:grid-cols-3" role="radiogroup">
                                    <label
                                        v-for="opt in consentModeOptions"
                                        :key="opt.value"
                                        class="flex cursor-pointer flex-col gap-1.5 rounded-xl border p-4 transition-colors"
                                        :class="consentMode === opt.value
                                            ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-900/20 dark:ring-primary-400'
                                            : 'border-gray-200 bg-white hover:border-gray-300 dark:border-surface-700 dark:bg-surface-800'"
                                    >
                                        <input v-model="consentMode" type="radio" :value="opt.value" class="sr-only">
                                        <span class="flex items-center gap-2">
                                            <i :class="['ti', opt.icon, 'text-base', consentMode === opt.value ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500']"></i>
                                            <span class="text-sm font-semibold" :class="consentMode === opt.value ? 'text-primary-700 dark:text-primary-300' : 'text-gray-700 dark:text-gray-300'">{{ opt.label }}</span>
                                            <i v-if="consentMode === opt.value" class="ti ti-circle-check ml-auto text-base text-primary-600 dark:text-primary-400"></i>
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ opt.description }}</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Banner Position') }}</span>
                                <div class="grid gap-3 md:grid-cols-3" role="radiogroup">
                                    <label
                                        v-for="opt in positionOptions"
                                        :key="opt.value"
                                        class="flex cursor-pointer flex-col gap-1.5 rounded-xl border p-4 transition-colors"
                                        :class="form.banner_position === opt.value
                                            ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-900/20 dark:ring-primary-400'
                                            : 'border-gray-200 bg-white hover:border-gray-300 dark:border-surface-700 dark:bg-surface-800'"
                                    >
                                        <input v-model="form.banner_position" type="radio" :value="opt.value" class="sr-only">
                                        <span class="flex items-center gap-2">
                                            <i :class="['ti', opt.icon, 'text-base', form.banner_position === opt.value ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500']"></i>
                                            <span class="text-sm font-semibold" :class="form.banner_position === opt.value ? 'text-primary-700 dark:text-primary-300' : 'text-gray-700 dark:text-gray-300'">{{ opt.label }}</span>
                                        </span>
                                    </label>
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
                                <input v-model="form.banner_title" type="text" :placeholder="t('Enter banner title')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                                {{ t('Description') }}
                                <textarea v-model="form.banner_description" rows="4" :placeholder="t('Explain how cookies are used on your site')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500"></textarea>
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Accept All Button') }}
                                <input v-model="form.banner_accept_all_text" type="text" :placeholder="t('Accept All')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Customize Button') }}
                                <input v-model="form.banner_customize_text" type="text" :placeholder="t('Customize')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Necessary Only Button') }}
                                <input v-model="form.banner_necessary_text" type="text" :placeholder="t('Necessary Only')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Save Preferences Button') }}
                                <input v-model="form.banner_save_text" type="text" :placeholder="t('Save Preferences')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
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
                                <input v-model="form.privacy_policy_url" type="text" :placeholder="t('https://example.com/privacy')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
                            </label>

                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Cookie Policy URL') }}
                                <input v-model="form.cookie_policy_url" type="text" :placeholder="t('https://example.com/cookies')" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-normal text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:placeholder:text-gray-500">
                            </label>
                        </div>
                    </section>
                </div>
            </div>
        </form>
    </div>
</template>
