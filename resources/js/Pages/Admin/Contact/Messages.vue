<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

interface ContactMessage {
    id: number
    name: string
    email: string
    subject: string | null
    message: string
    ip_address: string | null
    is_read: boolean
    replied_at: string | null
    created_at: string
}

const props = defineProps<{
    messages: { data: ContactMessage[]; links: { url: string | null; label: string; active: boolean }[] }
    filters: { search?: string; status?: string }
    stats: { total: number; unread: number; replied: number }
}>()

const { t } = useTranslate()
const selected = ref<ContactMessage | null>(null)
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const replyForm = useForm({ subject: '', message: '' })

const applyFilters = () => {
    router.get(route('admin.contact.messages.index'), {
        search: search.value,
        status: status.value,
    }, { preserveState: true, replace: true })
}

const openMessage = (message: ContactMessage) => {
    selected.value = message
    replyForm.subject = `Re: ${message.subject || t('Your message')}`
    replyForm.message = ''

    if (!message.is_read) {
        router.post(route('admin.contact.messages.read', message.id), {}, { preserveScroll: true })
        message.is_read = true
    }
}

const sendReply = () => {
    if (!selected.value) return

    replyForm.post(route('admin.contact.messages.reply', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selected.value) selected.value.replied_at = new Date().toISOString()
            replyForm.reset('message')
        },
    })
}

const remove = (message: ContactMessage) => {
    if (!confirm(t('Delete this contact message?'))) return
    router.delete(route('admin.contact.messages.delete', message.id), { preserveScroll: true })
    if (selected.value?.id === message.id) selected.value = null
}
</script>

<template>
    <Head :title="t('Contact Messages')" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Contact Messages') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Review, reply, delete, and export contact form submissions.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.contact.settings.edit')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Settings') }}</Link>
                <a :href="route('admin.contact.messages.export')" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Export CSV') }}</a>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <div class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ t('Total') }}</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <div class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ t('Unread') }}</div>
                <div class="mt-2 text-3xl font-bold text-amber-600">{{ stats.unread }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <div class="text-xs font-bold uppercase tracking-widest text-gray-500">{{ t('Replied') }}</div>
                <div class="mt-2 text-3xl font-bold text-primary-600">{{ stats.replied }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px]">
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row dark:border-surface-800">
                    <input v-model="search" @keyup.enter="applyFilters" type="search" :placeholder="t('Search messages')" class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <select v-model="status" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        <option value="">{{ t('All') }}</option>
                        <option value="unread">{{ t('Unread') }}</option>
                        <option value="read">{{ t('Read') }}</option>
                    </select>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <button v-for="message in messages.data" :key="message.id" type="button" @click="openMessage(message)" class="block w-full px-5 py-4 text-left hover:bg-primary-50/50 dark:hover:bg-surface-800">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ message.name }}</span>
                                    <span v-if="!message.is_read" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{{ t('Unread') }}</span>
                                    <span v-if="message.replied_at" class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700">{{ t('Replied') }}</span>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">{{ message.email }}</div>
                                <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ message.subject || t('No subject') }}</div>
                                <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ message.message }}</p>
                            </div>
                            <button type="button" @click.stop="remove(message)" class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">{{ t('Delete') }}</button>
                        </div>
                    </button>

                    <div v-if="messages.data.length === 0" class="px-5 py-12 text-center text-sm text-gray-500">{{ t('No contact messages found.') }}</div>
                </div>
            </section>

            <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <template v-if="selected">
                    <div class="mb-5 border-b border-gray-100 pb-4 dark:border-surface-800">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ selected.subject || t('No subject') }}</h2>
                        <div class="mt-2 text-sm text-gray-500">{{ selected.name }} · {{ selected.email }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(selected.created_at)) }}</div>
                    </div>

                    <p class="whitespace-pre-wrap rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-surface-800 dark:text-gray-300">{{ selected.message }}</p>

                    <form @submit.prevent="sendReply" class="mt-5 space-y-4">
                        <input v-model="replyForm.subject" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        <textarea v-model="replyForm.message" rows="6" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white" :placeholder="t('Write a reply')"></textarea>
                        <p v-if="replyForm.errors.message" class="text-sm text-red-600">{{ replyForm.errors.message }}</p>
                        <button type="submit" :disabled="replyForm.processing" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">
                            {{ replyForm.processing ? t('Sending...') : t('Send Reply') }}
                        </button>
                    </form>
                </template>
                <div v-else class="py-16 text-center text-sm text-gray-500">{{ t('Select a message to view details.') }}</div>
            </aside>
        </div>
    </div>
</template>
