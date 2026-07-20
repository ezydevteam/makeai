<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Pagination from '@/Components/UI/Pagination.vue'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

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

interface ContactSettingsForm {
    contact_form_enabled: boolean
    contact_subject_mode: string
    contact_subject_options: string
    contact_notification_email: string
    contact_success_message: string
    contact_auto_reply_enabled: boolean
    contact_auto_reply_subject: string
    contact_auto_reply_message: string
}

const props = defineProps<{
    messages: {
        data: ContactMessage[]
        links: PaginationLink[]
        from?: number | null
        to?: number | null
        total?: number | null
        current_page?: number | null
        last_page?: number | null
    }
    filters: { search?: string; status?: string }
    stats: { total: number; unread: number; replied: number }
    settings: ContactSettingsForm
    canManageSettings: boolean
    openSettings: boolean
}>()

const { t } = useTranslate()

const selected = ref<ContactMessage | null>(props.messages.data[0] ?? null)
const search = ref(props.filters.search ?? '')
const searchFocused = ref(false)
const status = ref(props.filters.status ?? '')
const deleteTarget = ref<ContactMessage | null>(null)
const isDeleting = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
const showSettingsModal = ref(props.openSettings)
const filterDebounce = ref<number | null>(null)

const hasActiveFilters = computed(() => Boolean(search.value || status.value))

const replyForm = useForm({
    subject: '',
    message: '',
})

const settingsForm = useForm<ContactSettingsForm>({
    ...props.settings,
})

const statusOptions = computed(() => [
    { value: '', label: t('All statuses') },
    { value: 'unread', label: t('Unread') },
    { value: 'read', label: t('Read') },
])

const subjectModeOptions = computed(() => [
    { value: 'text', label: t('Text input') },
    { value: 'dropdown', label: t('Dropdown') },
])

const filteredMessages = computed(() => props.messages.data)

const syncSelectedMessage = (messages: ContactMessage[]) => {
    if (messages.length === 0) {
        selected.value = null
        return
    }

    if (selected.value) {
        const nextSelected = messages.find((message) => message.id === selected.value?.id)
        if (nextSelected) {
            selected.value = nextSelected
            return
        }
    }

    selected.value = messages[0]
}

const syncReplySubject = () => {
    if (!selected.value) {
        replyForm.reset()
        return
    }

    replyForm.subject = `Re: ${selected.value.subject || t('Your message')}`
}

const buildFilterQuery = (includeSettings = false) => {
    const query: Record<string, string | number> = {}

    if (search.value.trim()) {
        query.search = search.value.trim()
    }

    if (status.value) {
        query.status = status.value
    }

    if (includeSettings) {
        query.settings = 1
    }

    return query
}

const applyFilters = () => {
    router.get(route('admin.contact.messages.index'), buildFilterQuery(showSettingsModal.value && props.openSettings), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['messages', 'filters', 'stats', 'settings', 'canManageSettings', 'openSettings'],
    })
}

const clearFilters = () => {
    search.value = ''
    status.value = ''
    applyFilters()
}

const openMessage = (message: ContactMessage) => {
    selected.value = message
    syncReplySubject()
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
    if (isDeleting.value) return
    deleteTarget.value = null
}

const remove = () => {
    if (!deleteTarget.value) return

    const deletingId = deleteTarget.value.id

    router.delete(route('admin.contact.messages.delete', deletingId), {
        preserveScroll: true,
        onStart: () => {
            isDeleting.value = true
        },
        onFinish: () => {
            isDeleting.value = false
        },
        onSuccess: () => {
            deleteTarget.value = null
        },
    })
}

const openSettingsModal = () => {
    if (!props.canManageSettings) {
        return
    }

    settingsForm.defaults({ ...props.settings })
    settingsForm.reset()
    showSettingsModal.value = true
}

const closeSettingsModal = () => {
    if (settingsForm.processing) {
        return
    }

    showSettingsModal.value = false
    settingsForm.reset()
    settingsForm.clearErrors()

    if (props.openSettings) {
        router.get(route('admin.contact.messages.index'), buildFilterQuery(false), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['messages', 'filters', 'stats', 'settings', 'canManageSettings', 'openSettings'],
        })
    }
}

const submitSettings = () => {
    settingsForm.post(route('admin.contact.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            closeSettingsModal()
        },
    })
}

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName
    const isTypingTarget = tagName === 'INPUT' || tagName === 'TEXTAREA' || target?.isContentEditable

    if (event.key === '/' && !showSettingsModal.value && !deleteTarget.value && !isTypingTarget) {
        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && document.activeElement === searchInput.value) {
        event.preventDefault()
        search.value = ''
        applyFilters()
        searchInput.value?.blur()
        return
    }

    if (event.key === 'Escape' && !showSettingsModal.value && !deleteTarget.value && hasActiveFilters.value) {
        event.preventDefault()
        clearFilters()
    }
}

watch(
    () => props.messages.data,
    (messages) => {
        syncSelectedMessage(messages)
        syncReplySubject()
    },
    { immediate: true },
)

watch(
    () => props.settings,
    (settings) => {
        settingsForm.defaults({ ...settings })
        settingsForm.reset()
    },
)

watch(
    () => props.openSettings,
    (value) => {
        showSettingsModal.value = value
    },
)

watch([search, status], () => {
    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }

    filterDebounce.value = window.setTimeout(() => {
        applyFilters()
    }, 250)
})

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)

    if (filterDebounce.value) {
        window.clearTimeout(filterDebounce.value)
    }
})
</script>

<template>
    <Head :title="t('Contact Messages')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Contact Messages') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review inbound messages, reply to senders, and keep the contact inbox organized from one place.') }}
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-3">
                <a
                    :href="route('admin.contact.messages.export')"
                    class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
                >
                    <i class="ti ti-file-export text-base"></i>
                    {{ t('Export') }}
                </a>
                <button
                    v-if="canManageSettings"
                    type="button"
                    class="grow btn-primary-admin inline-flex items-center gap-2"
                    @click="openSettingsModal"
                >
                    <i class="ti ti-settings text-base"></i>
                    {{ t('Settings') }}
                </button>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(360px,0.85fr)]">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800">
                    <div class="flex-1 w-full sm:max-w-xs relative">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                        <input
                            ref="searchInput"
                            v-model="search"
                            :placeholder="t('Search contact messages...')"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-700 transition focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @focus="searchFocused = true"
                            @blur="searchFocused = false"
                        >
                        <span
                            v-if="!search && !searchFocused"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                        >/</span>
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            @click="search = ''"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end shrink-0">
                        <div class="w-full sm:w-44">
                            <AppSelect
                                v-model="status"
                                :options="statusOptions"
                                :placeholder="t('All statuses')"
                            />
                        </div>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                            @click="clearFilters"
                        >
                            <i class="ti ti-rotate-clockwise text-base"></i>
                            {{ t('Reset') }}
                        </button>
                    </div>
                </div>

                <div class="">
                    <button
                        v-for="message in filteredMessages"
                        :key="message.id"
                        type="button"
                        class="block w-full px-5 py-4 text-left transition hover:bg-primary-50/60 dark:hover:bg-surface-800/70 border-b border-gray-100 last:border-b-0 last:mb-0 dark:border-surface-800"
                        :class="{ 'bg-primary-50/80 dark:bg-surface-800/80': selected?.id === message.id }"
                        @click="openMessage(message)"
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
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-danger-600 transition hover:bg-danger-50 dark:hover:bg-danger-900/10"
                                        @click.stop="confirmDelete(message)"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </Tooltip>
                            </div>
                        </div>
                    </button>

                    <div v-if="filteredMessages.length === 0" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                            <i class="ti ti-mail-off text-2xl"></i>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('No contact messages found') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Try another search term or change the current status filter.') }}</p>
                    </div>
                </div>

                <div v-if="messages.data.length && messages.total && messages.total > messages.data.length" class="border-t border-gray-100 px-4 py-4 dark:border-surface-800">
                    <Pagination
                        :links="messages.links"
                        :from="messages.from"
                        :to="messages.to"
                        :total="messages.total"
                    />
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
                            <span class="inline-flex rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-surface-900/20 dark:text-gray-500">
                                {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(selected.created_at)) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:border-surface-800 dark:bg-surface-800 dark:text-gray-300">
                        <p class="whitespace-pre-wrap">{{ selected.message }}</p>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="sendReply">
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
                            <RichEditor v-model="replyForm.message" variant="comment" />
                            <p v-if="replyForm.errors.message" class="mt-2 text-sm text-danger-600">{{ replyForm.errors.message }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="replyForm.processing"
                            class="btn-primary-admin inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <i class="ti ti-send text-base"></i>
                            {{ replyForm.processing ? t('Sending...') : t('Send Reply') }}
                        </button>
                    </form>
                </template>

                <div v-else class="flex min-h-[420px] flex-col items-center justify-center text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                        <i class="ti ti-mail-search text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('Select a message') }}</h3>
                    <p class="mt-2 max-w-xs text-sm text-gray-500 dark:text-gray-400">{{ t('Choose a message from the inbox to read the full content and send a reply.') }}</p>
                </div>
            </aside>
        </section>

        <AppModal
            :open="showSettingsModal && canManageSettings"
            max-width="max-w-4xl"
            :title="t('Contact Settings')"
            :subtitle="t('Configure the public contact form, routing emails, and the automatic reply experience for visitors.')"
            has-form
            @close="closeSettingsModal"
        >
            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5">
                        <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Form Setup') }}</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Control how the contact form is displayed and what options visitors can select.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <AppSelect
                                v-model="settingsForm.contact_subject_mode"
                                :options="subjectModeOptions"
                                :label="t('Subject field')"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Success message') }}</label>
                            <input
                                v-model="settingsForm.contact_success_message"
                                :placeholder="t('Enter the success message shown after submit')"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div v-if="settingsForm.contact_subject_mode === 'dropdown'" class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Dropdown subject options') }}</label>
                            <textarea
                                v-model="settingsForm.contact_subject_options"
                                rows="6"
                                :placeholder="t('Add one subject option per line')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('One option per line. These options are used only when the subject field is set to dropdown mode.') }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5">
                        <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Email Routing') }}</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Set the destination address and configure the automatic reply sent back to the visitor.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Notification email') }}</label>
                            <input
                                v-model="settingsForm.contact_notification_email"
                                :placeholder="t('Enter the inbox email for contact notifications')"
                                type="email"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Send auto reply') }}</h5>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Automatically send a confirmation email after a visitor submits the contact form.') }}</p>
                                </div>
                                <AppSwitch v-model="settingsForm.contact_auto_reply_enabled" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto reply subject') }}</label>
                            <input
                                v-model="settingsForm.contact_auto_reply_subject"
                                :placeholder="t('Enter the subject line for the reply email')"
                                type="text"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto reply message') }}</label>
                            <textarea
                                v-model="settingsForm.contact_auto_reply_message"
                                rows="8"
                                :placeholder="t('Write the auto reply email body sent to visitors')"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ t('Available variables: {name}, {email}, {subject}') }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <template #footer>
                <div class="flex items-center justify-between gap-3 w-full">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Changes apply to the public contact form and automatic replies.') }}
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="closeSettingsModal"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            class="btn-primary-admin inline-flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="settingsForm.processing"
                            @click="submitSettings"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ settingsForm.processing ? t('Saving...') : t('Save Settings') }}
                        </button>
                    </div>
                </div>
            </template>
        </AppModal>

        <ActionConfirmModal
            :open="!!deleteTarget"
            :title="t('Delete message')"
            :message="t('This message will be removed permanently from the contact inbox.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            :processing="isDeleting"
            @confirm="remove"
            @cancel="closeDeleteModal"
            @update:open="(value: boolean) => { if (!value) closeDeleteModal() }"
        />
    </div>
</template>
