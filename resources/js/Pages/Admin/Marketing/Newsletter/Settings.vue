<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppColorPicker from '@/Components/UI/AppColorPicker.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface NewsletterSettings {
    newsletter_driver?: string | null
    mailchimp_server_prefix?: string | null
    mailchimp_list_id?: string | null
    mailchimp_double_optin?: boolean | null
    mailchimp_tags?: string | null
    newsletter_double_optin?: boolean | null
    newsletter_enable_popup?: boolean | null
    newsletter_popup_trigger?: string | null
    newsletter_popup_trigger_value?: string | number | null
    newsletter_popup_title?: string | null
    newsletter_popup_description?: string | null
    newsletter_popup_placeholder?: string | null
    newsletter_popup_submit_text?: string | null
    newsletter_popup_success_message?: string | null
    newsletter_popup_bg_color?: string | null
    newsletter_popup_show_mobile?: boolean | null
    newsletter_popup_cookie_duration?: string | number | null
    newsletter_popup_hide_for_logged_in?: boolean | null
}

const props = defineProps<{
    settings: NewsletterSettings
    configuredSecrets: Record<string, boolean>
}>()

const { t } = useTranslate()

const driverOptions = [
    { value: 'internal', label: t('Internal only') },
    { value: 'mailchimp', label: t('Mailchimp only') },
    { value: 'both', label: t('Internal + Mailchimp') },
]

const popupTriggerOptions = [
    { value: 'time_delay', label: t('Time delay') },
    { value: 'scroll_depth', label: t('Scroll depth') },
    { value: 'exit_intent', label: t('Exit intent') },
    { value: 'page_views', label: t('Page views') },
    { value: 'first_visit', label: t('First visit only') },
]

const settingsForm = useForm({
    newsletter_driver: props.settings.newsletter_driver || 'internal',
    mailchimp_api_key: '',
    mailchimp_server_prefix: props.settings.mailchimp_server_prefix || '',
    mailchimp_list_id: props.settings.mailchimp_list_id || '',
    mailchimp_double_optin: props.settings.mailchimp_double_optin ?? false,
    mailchimp_tags: props.settings.mailchimp_tags || '',
    newsletter_double_optin: props.settings.newsletter_double_optin ?? false,
    newsletter_enable_popup: props.settings.newsletter_enable_popup ?? false,
    newsletter_popup_trigger: props.settings.newsletter_popup_trigger || 'time_delay',
    newsletter_popup_trigger_value: String(props.settings.newsletter_popup_trigger_value || '5'),
    newsletter_popup_title: props.settings.newsletter_popup_title || t('Subscribe to our Newsletter'),
    newsletter_popup_description: props.settings.newsletter_popup_description || t('Get the latest updates delivered directly to your inbox.'),
    newsletter_popup_placeholder: props.settings.newsletter_popup_placeholder || t('Enter your email address'),
    newsletter_popup_submit_text: props.settings.newsletter_popup_submit_text || t('Subscribe'),
    newsletter_popup_success_message: props.settings.newsletter_popup_success_message || t('Thanks for subscribing!'),
    newsletter_popup_bg_color: props.settings.newsletter_popup_bg_color || '#ffffff',
    newsletter_popup_show_mobile: props.settings.newsletter_popup_show_mobile ?? true,
    newsletter_popup_cookie_duration: Number(props.settings.newsletter_popup_cookie_duration || 30),
    newsletter_popup_hide_for_logged_in: props.settings.newsletter_popup_hide_for_logged_in ?? true,
})

const usesExternalDriver = computed(() => settingsForm.newsletter_driver !== 'internal')

const saveSettings = () => {
    settingsForm.post(route('admin.newsletter.settings.save'))
}
</script>

<template>
    <Head :title="t('Newsletter Settings')" />

    <div class="mx-auto max-w-5xl w-full space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Newsletter Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Configure drivers, double opt-in settings, integrations, and email subscription capture popups.') }}
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <button
                    type="button"
                    class="shrink-0 btn-primary-admin"
                    :disabled="settingsForm.processing"
                    @click="saveSettings"
                >
                    <i class="ti ti-device-floppy"></i>
                    {{ t('Save Settings') }}
                </button>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Driver & Integration -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">{{ t('Driver Configuration') }}</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <AppSelect v-model="settingsForm.newsletter_driver" :options="driverOptions" :label="t('Newsletter driver')" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Choose how new subscribers are stored and synced.') }}</p>
                    </div>

                    <label v-if="usesExternalDriver" class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Mailchimp API key') }}</span>
                        <input
                            v-model="settingsForm.mailchimp_api_key"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="configuredSecrets.mailchimp_api_key ? t('Stored securely - leave blank to keep') : t('e.g. 1234567890abcdef-us21')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                    </label>

                    <label v-if="usesExternalDriver" class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Server prefix') }}</span>
                        <input v-model="settingsForm.mailchimp_server_prefix" type="text" :placeholder="t('e.g. us21')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label v-if="usesExternalDriver" class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Audience / List ID') }}</span>
                        <input v-model="settingsForm.mailchimp_list_id" type="text" :placeholder="t('e.g. 1a2b3c4d5e')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label v-if="usesExternalDriver" class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default tags') }}</span>
                        <input v-model="settingsForm.mailchimp_tags" type="text" :placeholder="t('website_signup, ai_user')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Comma-separated tags to apply to new subscribers.') }}</p>
                    </label>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-6 dark:border-surface-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">{{ t('Opt-in rules') }}</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Double opt-in') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Require subscribers to confirm subscription via email.') }}</p>
                            </div>
                            <AppSwitch v-model="settingsForm.newsletter_double_optin" />
                        </div>

                        <div v-if="usesExternalDriver" class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Mailchimp double opt-in') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Require Mailchimp subscription confirmation.') }}</p>
                            </div>
                            <AppSwitch v-model="settingsForm.mailchimp_double_optin" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Popup Configuration -->
            <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800 mb-6">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Enable newsletter popup') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Show a popup to encourage visitors to subscribe.') }}</p>
                    </div>
                    <AppSwitch v-model="settingsForm.newsletter_enable_popup" />
                </div>

                <div v-if="settingsForm.newsletter_enable_popup" class="grid gap-6 md:grid-cols-2">
                    <div>
                        <AppSelect v-model="settingsForm.newsletter_popup_trigger" :options="popupTriggerOptions" :label="t('Trigger type')" />
                    </div>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Trigger value') }}</span>
                        <input v-model="settingsForm.newsletter_popup_trigger_value" type="text" :placeholder="t('e.g. 5')" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</span>
                        <input v-model="settingsForm.newsletter_popup_title" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Success message') }}</span>
                        <input v-model="settingsForm.newsletter_popup_success_message" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</span>
                        <textarea v-model="settingsForm.newsletter_popup_description" rows="3" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Input placeholder') }}</span>
                        <input v-model="settingsForm.newsletter_popup_placeholder" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Submit button text') }}</span>
                        <input v-model="settingsForm.newsletter_popup_submit_text" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Cookie duration (days)') }}</span>
                        <input v-model="settingsForm.newsletter_popup_cookie_duration" type="number" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </label>

                    <div class="block">
                        <AppColorPicker v-model="settingsForm.newsletter_popup_bg_color" :label="t('Background color')" />
                    </div>

                    <div class="md:col-span-2 mt-2 border-t border-gray-100 pt-6 dark:border-surface-800">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">{{ t('Popup Visibility') }}</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Show on mobile') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Allow popup to display on mobile screens.') }}</p>
                                </div>
                                <AppSwitch v-model="settingsForm.newsletter_popup_show_mobile" />
                            </div>

                            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Hide for logged-in') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Do not show the popup to authenticated users.') }}</p>
                                </div>
                                <AppSwitch v-model="settingsForm.newsletter_popup_hide_for_logged_in" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
