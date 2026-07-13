<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSwitch from '@/Components/UI/AppSwitch.vue'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

const props = defineProps<{ settings: Record<string, boolean | number | string> }>()
const { t } = useTranslate()
const form = useForm({ ...props.settings })
const save = () => form.post(route('admin.support.settings.update'), { preserveScroll: true })

const labels: Record<string, string> = {
    notify_admin_new_ticket: 'Notify admin on new ticket',
    notify_user_reply: 'Notify user on reply',
    satisfaction_rating_enabled: 'Enable satisfaction rating',
    ai_reply_suggestion: 'Enable AI reply suggestions',
    max_attachments_per_reply: 'Max attachments per reply',
    max_attachment_size_mb: 'Max attachment size (MB)',
    auto_close_resolved_days: 'Auto-close resolved tickets after days',
    sla_first_response_hours: 'First response SLA (hours)',
    sla_resolution_hours: 'Resolution SLA (hours)',
    allowed_attachment_types: 'Allowed attachment types',
}

const label = (key: string) => t(labels[key] ?? key)
</script>

<template>
    <Head :title="t('Support Settings')" />

    <div class="mx-auto max-w-5xl px-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Configure support ticket rules and notifications.') }}</p>
            </div>
            <div class="flex gap-3">
                <Link :href="route('admin.support.tickets.index')" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"><i class="ti ti-arrow-left text-base"></i>{{ t('Back to Tickets') }}</Link>
                <button type="button" @click="save" :disabled="form.processing" class="btn-primary-admin inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium disabled:opacity-60"><i class="ti ti-device-floppy text-base"></i>{{ form.processing ? t('Saving...') : t('Save Settings') }}</button>
            </div>
        </div>

        <form @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="grid gap-5 md:grid-cols-2">
                <div v-for="key in ['notify_admin_new_ticket', 'notify_user_reply', 'satisfaction_rating_enabled', 'ai_reply_suggestion']" :key="key" class="flex items-center justify-between rounded-lg border border-gray-100 p-4 dark:border-surface-800">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label(key) }}</span>
                    <AppSwitch
                        :model-value="Boolean(form[key])"
                        @update:model-value="val => form[key] = val"
                    />
                </div>
                <label v-for="key in ['max_attachments_per_reply', 'max_attachment_size_mb', 'auto_close_resolved_days', 'sla_first_response_hours', 'sla_resolution_hours']" :key="key" class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label(key) }}</span>
                    <input v-model="form[key]" type="number" min="1" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                </label>
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label('allowed_attachment_types') }}</span>
                    <input v-model="form.allowed_attachment_types" :placeholder="t('jpg,png,pdf,docx')" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                </label>
            </div>
        </form>
    </div>
</template>
