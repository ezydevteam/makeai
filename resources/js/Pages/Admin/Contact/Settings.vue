<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

interface ContactSettings {
    contact_subject_mode: string
    contact_success_message: string
    contact_subject_options: string
    contact_notification_email: string
    contact_auto_reply_enabled: boolean
    contact_auto_reply_subject: string
    contact_auto_reply_message: string
}

const props = defineProps<{ settings: ContactSettings }>()
const { t } = useTranslate()

const form = useForm({
    contact_subject_mode: props.settings.contact_subject_mode,
    contact_success_message: props.settings.contact_success_message,
    contact_subject_options: props.settings.contact_subject_options,
    contact_notification_email: props.settings.contact_notification_email,
    contact_auto_reply_enabled: props.settings.contact_auto_reply_enabled,
    contact_auto_reply_subject: props.settings.contact_auto_reply_subject,
    contact_auto_reply_message: props.settings.contact_auto_reply_message,
})

const subjectModeOptions = computed(() => [
    { value: 'text', label: t('Text input') },
    { value: 'dropdown', label: t('Dropdown') },
])

const submit = () => {
    form.post(route('admin.contact.settings.update'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Contact Settings')" />

    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Contact Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Configure the public contact form, routing emails, and the automatic reply experience for visitors.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('admin.contact.messages.index')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-arrow-left text-base" aria-hidden="true"></i>
                    <span>{{ t('Back') }}</span>
                </Link>
                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="btn-primary inline-flex items-center gap-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <i class="ti ti-device-floppy text-base" aria-hidden="true"></i>
                    <span>{{ form.processing ? t('Saving...') : t('Save Settings') }}</span>
                </button>
            </div>
        </section>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Form Setup') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control how the contact form is displayed and what options visitors can select.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <AppSelect
                                v-model="form.contact_subject_mode"
                                :options="subjectModeOptions"
                                :label="t('Subject field')"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Success message') }}</label>
                            <input
                                v-model="form.contact_success_message"
                                :placeholder="t('Enter the success message shown after submit')"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div v-if="form.contact_subject_mode === 'dropdown'" class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Dropdown subject options') }}</label>
                            <textarea
                                v-model="form.contact_subject_options"
                                rows="6"
                                :placeholder="t('Add one subject option per line')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('One option per line. These options are used only when the subject field is set to dropdown mode.') }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Email Routing') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Set the destination address and configure the automatic reply sent back to the visitor.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Notification email') }}</label>
                            <input
                                v-model="form.contact_notification_email"
                                :placeholder="t('Enter the inbox email for contact notifications')"
                                type="email"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Send auto reply') }}</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Automatically send a confirmation email after a visitor submits the contact form.') }}</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="Boolean(form.contact_auto_reply_enabled)"
                                    @click="form.contact_auto_reply_enabled = !form.contact_auto_reply_enabled"
                                    class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                                    :class="form.contact_auto_reply_enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                                >
                                    <span
                                        class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition"
                                        :class="form.contact_auto_reply_enabled ? 'translate-x-5' : 'translate-x-0.5'"
                                    ></span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto reply subject') }}</label>
                            <input
                                v-model="form.contact_auto_reply_subject"
                                :placeholder="t('Enter the subject line for the reply email')"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto reply message') }}</label>
                            <textarea
                                v-model="form.contact_auto_reply_message"
                                rows="8"
                                :placeholder="t('Write the auto reply email body sent to visitors')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Available variables: {name}, {email}, {subject}') }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </form>
    </div>
</template>
