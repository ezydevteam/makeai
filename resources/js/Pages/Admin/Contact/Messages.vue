<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import Pagination from '@/Components/Pagination.vue'

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

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    messages: { data: ContactMessage[]; links: PaginationLink[] }
    filters: { search?: string; status?: string }
    stats: { total: number; unread: number; replied: number }
}>()

const { t } = useTranslate()

const selected = ref<ContactMessage | null>(props.messages.data[0] ?? null)
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const deleteTarget = ref<ContactMessage | null>(null)

const replyForm = useForm({
    subject: '',
    message: '',
})

const statusOptions = computed(() => [
    { value: '', label: t('All statuses') },
    { value: 'unread', label: t('Unread') },
    { value: 'read', label: t('Read') },
])

const filteredMessages = computed(() => {
    const query = search.value.trim().toLowerCase()

    return props.messages.data.filter((message) => {
        const matchesSearch = !query
            || message.name.toLowerCase().includes(query)
            || message.email.toLowerCase().includes(query)
            || (message.subject ?? '').toLowerCase().includes(query)
            || message.message.toLowerCase().includes(query)

        const matchesStatus = !status.value
            || (status.value === 'unread' && !message.is_read)
            || (status.value === 'read' && message.is_read)

        return matchesSearch && matchesStatus
    })
})

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
            if (selected.value) {
                selected.value.replied_at = new Date().toISOString()
            }
            replyForm.reset('message')
        },
    })
}

const confirmDelete = (message: ContactMessage) => {
    deleteTarget.value = message
}

const closeDeleteModal = () => {
    if (router.processing) return
    deleteTarget.value = null
}

const remove = () => {
    if (!deleteTarget.value) return

    const deletingId = deleteTarget.value.id

    router.delete(route('admin.contact.messages.delete', deletingId), {
        preserveScroll: true,
        onSuccess: () => {
            if (selected.value?.id === deletingId) {
                selected.value = props.messages.data.find((message) => message.id !== deletingId) ?? null
            }
            deleteTarget.value = null
        },
    })
}

if (selected.value) {
    replyForm.subject = `Re: ${selected.value.subject || t('Your message')}`
}
</script>

<template>
    <Head :title="t('Contact Messages')" />

    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-6 py-8">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Contact Messages') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review inbound messages, reply to senders, and keep the contact inbox organized from one place.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('admin.contact.settings.edit')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-primary-300 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-settings text-base" aria-hidden="true"></i>
                    <span>{{ t('Settings') }}</span>
                </Link>
                <a
                    :href="route('admin.contact.messages.export')"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-primary-700"
                >
                    <i class="ti ti-file-export text-base" aria-hidden="true"></i>
                    <span>{{ t('Export CSV') }}</span>
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Total messages') }}</p>
                        <p class="mt-3 text-3xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-secondary-50 text-secondary-600 dark:bg-secondary-900/20 dark:text-secondary-300">
                        <i class="ti ti-inbox text-xl" aria-hidden="true"></i>
                    </span>
                </div>
            </article>

            <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Unread') }}</p>
                        <p class="mt-3 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ stats.unread }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-300">
                        <i class="ti ti-mail-opened text-xl" aria-hidden="true"></i>
                    </span>
                </div>
            </article>

            <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Replied') }}</p>
                        <p class="mt-3 text-3xl font-bold text-primary-600 dark:text-primary-400">{{ stats.replied }}</p>
                    </div>
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300">
                        <i class="ti ti-send text-xl" aria-hidden="true"></i>
                    </span>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-surface-800">
                    <div class="flex flex-1 items-center gap-3">
                        <div class="relative flex-1">
                            <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400" aria-hidden="true"></i>
                            <input
                                v-model="search"
                                @keyup.enter="applyFilters"
                                :placeholder="t('Search by name, email, or subject')"
                                type="search"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-3 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <div class="w-44 shrink-0">
                            <AppSelect
                                v-model="status"
                                :options="statusOptions"
                                :placeholder="t('All statuses')"
                            />
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <button
                        v-for="message in filteredMessages"
                        :key="message.id"
                        type="button"
                        @click="openMessage(message)"
                        class="block w-full px-5 py-4 text-left transition hover:bg-primary-50/60 dark:hover:bg-surface-800/70"
                        :class="{ 'bg-primary-50/80 dark:bg-surface-800/80': selected?.id === message.id }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate font-semibold text-gray-900 dark:text-white">{{ message.name }}</span>
                                    <span v-if="!message.is_read" class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">{{ t('Unread') }}</span>
                                    <span v-if="message.replied_at" class="inline-flex rounded-full bg-primary-100 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/20 dark:text-primary-300">{{ t('Replied') }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="truncate">{{ message.email }}</span>
                                    <span v-if="message.ip_address" class="hidden h-1 w-1 rounded-full bg-gray-300 sm:block"></span>
                                    <span v-if="message.ip_address" class="hidden sm:inline">{{ message.ip_address }}</span>
                                </div>
                                <p class="mt-3 truncate text-sm font-medium text-gray-700 dark:text-gray-200">{{ message.subject || t('No subject') }}</p>
                                <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ message.message }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <Tooltip :content="t('Delete message')" placement="top">
                                    <button
                                        type="button"
                                        @click.stop="confirmDelete(message)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-danger-600 transition hover:bg-danger-50 dark:hover:bg-danger-900/10"
                                    >
                                        <i class="ti ti-trash text-base" aria-hidden="true"></i>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>
                    </button>

                    <div v-if="filteredMessages.length === 0" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                            <i class="ti ti-mail-off text-2xl" aria-hidden="true"></i>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('No contact messages found') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Try another search term or change the current status filter.') }}</p>
                    </div>
                </div>

                <div v-if="messages.links?.length" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                    <Pagination :links="messages.links" />
                </div>
            </div>

            <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <template v-if="selected">
                    <div class="border-b border-gray-100 pb-5 dark:border-surface-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ selected.subject || t('No subject') }}</h2>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ selected.name }} · {{ selected.email }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-secondary-50 px-2.5 py-1 text-xs font-medium text-secondary-700 dark:bg-secondary-900/20 dark:text-secondary-300">
                                {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(selected.created_at)) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:border-surface-800 dark:bg-surface-800 dark:text-gray-300">
                        <p class="whitespace-pre-wrap">{{ selected.message }}</p>
                    </div>

                    <form @submit.prevent="sendReply" class="mt-5 space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Reply subject') }}</label>
                            <input
                                v-model="replyForm.subject"
                                :placeholder="t('Enter reply subject')"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Reply message') }}</label>
                            <textarea
                                v-model="replyForm.message"
                                rows="7"
                                :placeholder="t('Write a clear reply to the sender')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                            <p v-if="replyForm.errors.message" class="mt-2 text-sm text-danger-600">{{ replyForm.errors.message }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="replyForm.processing"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <i class="ti ti-send text-base" aria-hidden="true"></i>
                            <span>{{ replyForm.processing ? t('Sending...') : t('Send Reply') }}</span>
                        </button>
                    </form>
                </template>

                <div v-else class="flex min-h-[420px] flex-col items-center justify-center text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                        <i class="ti ti-mail-search text-2xl" aria-hidden="true"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('Select a message') }}</h3>
                    <p class="mt-2 max-w-xs text-sm text-gray-500 dark:text-gray-400">{{ t('Choose a message from the inbox to read the full content and send a reply.') }}</p>
                </div>
            </aside>
        </section>

        <ActionConfirmModal
            :open="!!deleteTarget"
            :title="t('Delete message')"
            :message="t('This message will be removed permanently from the contact inbox.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            @confirm="remove"
            @update:open="(value) => { if (!value) closeDeleteModal() }"
        />
    </div>
</template>
