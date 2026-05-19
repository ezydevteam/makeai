<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

const props = defineProps<{ settings: Record<string, any> }>()
const { t } = useTranslate()
const form = useForm({ ...props.settings })

const submit = () => form.post(route('admin.contact.settings.update'), { preserveScroll: true })
</script>

<template>
    <Head :title="t('Contact Settings')" />

    <div class="max-w-4xl mx-auto px-6 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Contact Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Configure contact form fields, notifications, and auto replies.') }}</p>
            </div>
            <Link :href="route('admin.contact.messages.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Messages') }}</Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Form') }}</h2>
                <div class="space-y-4">
                    <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                        <span>{{ t('Enable contact form') }}</span>
                        <input v-model="form.contact_form_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Subject field') }}
                        <select v-model="form.contact_subject_mode" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                            <option value="text">{{ t('Text input') }}</option>
                            <option value="dropdown">{{ t('Dropdown') }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Dropdown subject options') }}
                        <textarea v-model="form.contact_subject_options" rows="5" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                        <span class="mt-1 block text-xs text-gray-400">{{ t('One option per line.') }}</span>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Success message') }}
                        <input v-model="form.contact_success_message" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    </label>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Email') }}</h2>
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Notification email') }}
                        <input v-model="form.contact_notification_email" type="email" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    </label>
                    <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                        <span>{{ t('Send auto reply') }}</span>
                        <input v-model="form.contact_auto_reply_enabled" type="checkbox" class="rounded border-gray-300 text-primary-600">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Auto reply subject') }}
                        <input v-model="form.contact_auto_reply_subject" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Auto reply message') }}
                        <textarea v-model="form.contact_auto_reply_message" rows="7" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                        <span class="mt-1 block text-xs text-gray-400">{{ t('Available variables: {name}, {email}, {subject}') }}</span>
                    </label>
                </div>
            </section>

            <button type="submit" :disabled="form.processing" class="rounded-lg bg-primary-600 px-5 py-3 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">
                {{ form.processing ? t('Saving...') : t('Save Settings') }}
            </button>
        </form>
    </div>
</template>
