<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { sanitizeHtml } from '@/Composables/useSanitize'
import { badgeClass } from '@/Composables/useBadge'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { useToastr } from '@/Composables/useToastr'
import { mediaUrl } from '@/lib/media'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

interface Option {
    id: number
    name: string
}

interface Reply {
    id: number
    author_type: string
    author_name: string
    author_avatar?: string | null
    content: string
    is_internal_note: boolean
    is_ai_draft: boolean
    attachments?: { name: string; url: string }[]
    created_at: string
}

interface Canned {
    id: number
    title: string
    content: string
    department_id?: number | null
}

const props = defineProps<{
    ticket: {
        ticket_number: string
        subject: string
        status: string
        priority: string
        department_id: number
        assigned_to?: number | null
        user?: { ulid?: string; name: string; email: string }
        department?: { id: number; name: string } | null
        assigned_admin?: { id: number; name: string } | null
        satisfaction_rating?: number | null
        satisfaction_comment?: string | null
        replies: Reply[]
    }
    departments: Option[]
    admins: Option[]
    cannedResponses: Canned[]
    settings: { ai_reply_suggestion: boolean }
}>()

const { t } = useTranslate()
const toast = useToastr()

const aiLoading = ref(false)

const form = useForm({
    message: '',
    is_internal_note: false,
    is_ai_draft: false,
    attachments: [] as File[],
})

const stateForm = useForm({
    status: props.ticket.status,
    priority: props.ticket.priority,
    department_id: props.ticket.department_id,
    assigned_to: props.ticket.assigned_to ?? null as number | null,
})

const statusOptions = computed(() => [
    { value: 'open', label: t('Open') },
    { value: 'in_progress', label: t('In Progress') },
    { value: 'waiting_user', label: t('Waiting User') },
    { value: 'resolved', label: t('Resolved') },
    { value: 'closed', label: t('Closed') },
])

const priorityOptions = computed(() => [
    { value: 'low', label: t('Low') },
    { value: 'medium', label: t('Medium') },
    { value: 'high', label: t('High') },
    { value: 'urgent', label: t('Urgent') },
])

const departmentOptions = computed(() => props.departments.map((item) => ({
    value: item.id,
    label: item.name,
})))

const adminOptions = computed(() => [
    { value: null, label: t('Unassigned') },
    ...props.admins.map((item) => ({
        value: item.id,
        label: item.name,
    })),
])

const cannedResponseOptions = computed(() => [
    { value: '', label: t('Insert canned response') },
    ...props.cannedResponses.map((item) => ({
        value: String(item.id),
        label: item.title,
    })),
])

const ticketStatusLabel = (value: string) => {
    const labels: Record<string, string> = {
        open: 'Open',
        in_progress: 'In Progress',
        waiting_user: 'Waiting User',
        resolved: 'Resolved',
        closed: 'Closed',
    }

    return t(labels[value] ?? value)
}

const ticketPriorityLabel = (value: string) => {
    const labels: Record<string, string> = {
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        urgent: 'Urgent',
    }

    return t(labels[value] ?? value)
}

const replyTypeLabel = (reply: Reply) => {
    if (reply.is_ai_draft) return t('AI reply')
    if (reply.is_internal_note) return t('Internal note')

    const labels: Record<string, string> = {
        admin: 'Support agent',
        user: 'Customer',
    }

    return t(labels[reply.author_type] ?? reply.author_type)
}

const getAvatarUrl = (avatar: string) => mediaUrl(avatar)

const replyTypeClass = (reply: Reply) => {
    if (reply.is_ai_draft) return 'bg-violet-100 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'
    if (reply.is_internal_note) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300'

    return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-300'
}

const replyCardClass = (reply: Reply) => {
    if (reply.is_ai_draft) return 'border-violet-200 bg-violet-50/70 dark:border-violet-900/40 dark:bg-violet-950/20'
    if (reply.is_internal_note) return 'border-amber-200 bg-amber-50/80 dark:border-amber-900/40 dark:bg-amber-950/20'

    return 'border-gray-200 bg-white dark:border-surface-800 dark:bg-surface-900'
}

const setFiles = (event: Event) => {
    form.attachments = Array.from((event.target as HTMLInputElement).files ?? [])
}

const sendReply = () => form.post(route('admin.support.tickets.reply', props.ticket.ticket_number), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => form.reset('message', 'attachments'),
})

const updateState = () => stateForm.post(route('admin.support.tickets.state', props.ticket.ticket_number), { preserveScroll: true })

const trackCannedUsage = (id: number) => {
    fetch(route('admin.support.canned-responses.track', id), {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
    })
}

const insertCanned = (id: string | number | null | (string | number)[]) => {
    if (Array.isArray(id)) {
        id = id[0] ?? null
    }
    if (!id) {
        return
    }

    const selectedResponse = props.cannedResponses.find((item) => item.id === Number(id))
    if (!selectedResponse) {
        return
    }

    form.message = `${form.message}\n${selectedResponse.content}`.trim()
    trackCannedUsage(selectedResponse.id)
}

const suggestReply = async () => {
    if (aiLoading.value) return
    aiLoading.value = true

    try {
        const response = await fetch(route('admin.support.tickets.suggest-reply', props.ticket.ticket_number), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        })

        if (!response.ok) {
            throw new Error()
        }

        const data = await response.json()

        if (data.success) {
            form.message = data.data.content
            form.is_ai_draft = true
            toast.success(t('AI reply draft generated successfully.'))
        } else {
            toast.error(data.message || t('Unable to generate AI reply. Please make sure your AI settings and API credentials are configured correctly.'))
        }
    } catch (error: any) {
        console.error(error)
        toast.error(t('Unable to generate AI reply. Please make sure your AI settings and API credentials are configured correctly.'))
    } finally {
        aiLoading.value = false
    }
}
</script>

<template>
    <Head :title="ticket.subject" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ ticket.ticket_number }} ·
                    <Link
                        v-if="ticket.user?.ulid"
                        :href="route('admin.users.show', ticket.user.ulid)"
                        class="font-medium text-gray-700 transition hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-300"
                    >
                        {{ ticket.user?.name || t('Unknown user') }}
                    </Link>
                    <span v-else>{{ ticket.user?.name || t('Unknown user') }}</span>
                </p>
            </div>

            <div class="shrink-0">
                <Link
                    :href="route('admin.support.tickets.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ t('Conversation') }}
                            </h2>
                            <div class="inline-flex gap-2">
                                <span :class="badgeClass(ticket.priority)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                    {{ ticketPriorityLabel(ticket.priority) }}
                                </span>
                                <span :class="badgeClass(ticket.status)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                    {{ ticketStatusLabel(ticket.status) }}
                                </span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Review the full customer and support timeline for this ticket.') }}</p>
                    </div>

                    <div v-if="ticket.replies.length > 0" class="space-y-4 p-5">
                        <article
                            v-for="reply in ticket.replies"
                            :key="reply.id"
                            class="rounded-2xl border p-5 shadow-sm"
                            :class="replyCardClass(reply)"
                        >
                            <div class="mb-3 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800 flex items-center justify-center border border-gray-100 dark:border-surface-700">
                                        <img
                                            v-if="reply.author_avatar"
                                            :src="getAvatarUrl(reply.author_avatar)"
                                            :alt="reply.author_name"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">
                                            {{ reply.author_name.charAt(0) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ reply.author_name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(reply.created_at)) }}
                                        </div>
                                    </div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="replyTypeClass(reply)">
                                    {{ replyTypeLabel(reply) }}
                                </span>
                            </div>

                            <div class="ticket-content prose prose-sm max-w-none dark:prose-invert" v-html="sanitizeHtml(reply.content)"></div>

                            <div v-if="reply.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                                <a
                                    v-for="attachment in reply.attachments"
                                    :key="attachment.url"
                                    :href="attachment.url"
                                    class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                                >
                                    <i class="ti ti-paperclip text-sm"></i>
                                    {{ attachment.name }}
                                </a>
                            </div>
                        </article>
                    </div>

                    <div v-else class="p-6 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                            <i class="ti ti-message-off text-2xl"></i>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ t('No conversation yet') }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Replies and customer updates will appear here once the ticket conversation starts.') }}</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ form.is_internal_note ? t('Internal Note') : t('Reply') }}</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Send a response, keep internal notes, or draft a quick reply using saved responses.') }}</p>
                            </div>

                            <div class="flex w-full flex-col gap-3 lg:w-auto lg:min-w-[24rem] lg:flex-row lg:justify-end">
                                <div class="w-full lg:w-64">
                                    <AppSelect
                                        :model-value="''"
                                        :options="cannedResponseOptions"
                                        :placeholder="t('Insert canned response')"
                                        @update:model-value="insertCanned"
                                    />
                                </div>
                                <button
                                    v-if="settings.ai_reply_suggestion"
                                    type="button"
                                    :disabled="aiLoading"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-60 dark:border-violet-900/40 dark:bg-violet-900/20 dark:text-violet-300 dark:hover:bg-violet-900/30"
                                    @click="suggestReply"
                                >
                                    <i v-if="aiLoading" class="ti ti-loader animate-spin text-base"></i>
                                    <i v-else class="ti ti-sparkles text-base"></i>
                                    {{ aiLoading ? t('Generating...') : t('AI Reply') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-4 p-5" @submit.prevent="sendReply">
                        <RichEditor v-model="form.message" variant="comment" />
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="form.is_internal_note" type="checkbox" class="rounded border-gray-300">
                            {{ t('Internal note') }}
                        </label>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <input
                                type="file"
                                multiple
                                class="rounded-xl border border-gray-200 bg-gray-50 p-1.5 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-primary-100 file:px-2 file:py-1 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-200 dark:border-surface-700 dark:bg-surface-800 dark:text-white dark:file:bg-primary-900/30 dark:file:text-primary-200"
                                @change="setFiles"
                            >

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="shrink-0 btn-primary-admin inline-flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <i class="ti ti-send text-base"></i>
                                {{ form.processing ? t('Sending...') : t('Send Reply') }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Ticket Controls') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Update the ticket state, routing, and support ownership.') }}</p>
                    </div>

                    <form class="space-y-4 p-5" @submit.prevent="updateState">
                        <div>
                            <AppSelect
                                v-model="stateForm.status"
                                :options="statusOptions"
                                :label="t('Status')"
                            />
                        </div>

                        <div>
                            <AppSelect
                                v-model="stateForm.priority"
                                :options="priorityOptions"
                                :label="t('Priority')"
                            />
                        </div>

                        <div>
                            <AppSelect
                                v-model="stateForm.department_id"
                                :options="departmentOptions"
                                :label="t('Department')"
                            />
                        </div>

                        <div>
                            <AppSelect
                                v-model="stateForm.assigned_to"
                                :options="adminOptions"
                                :label="t('Assigned agent')"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="stateForm.processing"
                            class="btn-primary-admin inline-flex w-full items-center justify-center gap-2 rounded-lg px-5 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <i class="ti ti-device-floppy text-base"></i>
                            {{ stateForm.processing ? t('Saving...') : t('Update Ticket') }}
                        </button>
                    </form>
                </section>

                <section
                    v-if="ticket.satisfaction_rating"
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Customer Satisfaction') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Feedback the customer left after this ticket was resolved.') }}</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <div class="flex items-center gap-1">
                            <i
                                v-for="star in 5"
                                :key="star"
                                class="ti text-lg"
                                :class="star <= (ticket.satisfaction_rating ?? 0) ? 'ti-star-filled text-amber-400' : 'ti-star text-gray-300 dark:text-surface-600'"
                            ></i>
                            <span class="ml-2 text-sm font-medium text-gray-600 dark:text-gray-300">{{ ticket.satisfaction_rating }}/5</span>
                        </div>
                        <p v-if="ticket.satisfaction_comment" class="rounded-xl bg-gray-50 p-3 text-sm text-gray-700 dark:bg-surface-800 dark:text-gray-200">
                            {{ ticket.satisfaction_comment }}
                        </p>
                        <p v-else class="text-sm italic text-gray-400 dark:text-gray-500">{{ t('No written feedback provided.') }}</p>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</template>
