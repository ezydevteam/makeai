<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { computed } from 'vue'

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

    <div class="mx-auto max-w-5xl px-6 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('GDPR & Cookie Consent') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ t('Configure the cookie consent banner, EU geolocation targeting, and GDPR compliance settings.') }}</p>
        </div>

        <form class="space-y-6" @submit.prevent="saveSettings">
            <!-- Enable + Scope -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Status & Scope') }}</h2>
                </div>

                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.enabled" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Enable GDPR Cookie Consent') }}</span>
                            <p class="text-xs text-gray-500">{{ t('Show the granular cookie consent banner to visitors.') }}</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.eu_only" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('EU / EEA Countries Only') }}</span>
                            <p class="text-xs text-gray-500">{{ t('Only show the consent banner to visitors from EU/EEA countries. Uses Cloudflare CF-IPCountry header or IPInfo if configured.') }}</p>
                        </div>
                    </label>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ t('Banner Position') }}</label>
                        <div class="flex gap-3">
                            <button
                                v-for="opt in positionOptions"
                                :key="opt.value"
                                type="button"
                                @click="form.banner_position = opt.value"
                                class="px-4 py-2 rounded-lg text-sm font-medium border-2 transition-colors"
                                :class="form.banner_position === opt.value
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-400 dark:bg-primary-900/20 dark:text-primary-400'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400'"
                            >{{ opt.label }}</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Banner Text -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Banner Content') }}</h2>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                        {{ t('Title') }}
                        <input v-model="form.banner_title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                        {{ t('Description') }}
                        <textarea v-model="form.banner_description" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Accept All Button') }}
                        <input v-model="form.banner_accept_all_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Customize Button') }}
                        <input v-model="form.banner_customize_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Necessary Only Button') }}
                        <input v-model="form.banner_necessary_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Save Preferences Button') }}
                        <input v-model="form.banner_save_text" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>

            <!-- Colors -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Banner Colors') }}</h2>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Background Color') }}
                        <div class="mt-2 flex items-center gap-3">
                            <input v-model="form.banner_bg_color" type="color" class="w-10 h-10 rounded border border-gray-200 cursor-pointer p-0.5">
                            <input v-model="form.banner_bg_color" type="text" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Text Color') }}
                        <div class="mt-2 flex items-center gap-3">
                            <input v-model="form.banner_text_color" type="color" class="w-10 h-10 rounded border border-gray-200 cursor-pointer p-0.5">
                            <input v-model="form.banner_text_color" type="text" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Button Color') }}
                        <div class="mt-2 flex items-center gap-3">
                            <input v-model="form.banner_button_color" type="color" class="w-10 h-10 rounded border border-gray-200 cursor-pointer p-0.5">
                            <input v-model="form.banner_button_color" type="text" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Button Text Color') }}
                        <div class="mt-2 flex items-center gap-3">
                            <input v-model="form.banner_button_text_color" type="color" class="w-10 h-10 rounded border border-gray-200 cursor-pointer p-0.5">
                            <input v-model="form.banner_button_text_color" type="text" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </div>
                    </label>
                </div>

                <!-- Preview -->
                <div class="mt-5 rounded-xl p-5" :style="{ backgroundColor: form.banner_bg_color, color: form.banner_text_color }">
                    <p class="text-sm font-bold mb-2">{{ form.banner_title || t('Cookie Preferences') }}</p>
                    <p class="text-xs mb-4">{{ form.banner_description || t('We use cookies to enhance your experience.') }}</p>
                    <div class="flex gap-2">
                        <span class="px-3 py-1.5 rounded-lg text-xs font-bold" :style="{ backgroundColor: form.banner_button_color, color: form.banner_button_text_color }">{{ form.banner_accept_all_text || t('Accept All') }}</span>
                        <span class="px-3 py-1.5 rounded-lg text-xs font-bold border" :style="{ borderColor: form.banner_text_color, color: form.banner_text_color }">{{ form.banner_customize_text || t('Customize') }}</span>
                    </div>
                </div>
            </section>

            <!-- Policy Links -->
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Policy Links') }}</h2>
                </div>

                <div class="space-y-5">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input v-model="form.show_policy_links" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show Privacy & Cookie Policy links on banner') }}</span>
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Privacy Policy URL') }}
                        <input v-model="form.privacy_policy_url" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Cookie Policy URL') }}
                        <input v-model="form.cookie_policy_url" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-700 shadow-lg disabled:opacity-60 transition-colors">
                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>{{ t('Save GDPR Settings') }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
