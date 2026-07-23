<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, defineAsyncComponent, ref } from 'vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { sanitizeHtml } from '@/Composables/useSanitize'
import { mediaUrl } from '@/lib/media'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))

defineOptions({ layout: UserDashboardLayout })

declare const route: (name: string, params?: unknown) => string

interface Reply {
    id: number
    author_type: 'user' | 'admin'
    author_name: string
    author_avatar?: string | null
    content: string
    attachments?: { name: string; url: string }[]
    created_at: string
}

const props = defineProps<{
    ticket: {
        ticket_number: string
        subject: string
        status: string
        priority: string
        satisfaction_rating?: number | null
        department?: { name: string }
        replies: Reply[]
    }
    userLastReadAt: string
    settings: { satisfaction_rating_enabled: boolean }
}>()

const { t } = useTranslate()
const form = useForm({ message: '', attachments: [] as File[] })
const ratingForm = useForm({ rating: props.ticket.satisfaction_rating ?? 5, comment: '' })
const attachmentInputRef = ref<HTMLInputElement | null>(null)
const setFiles = (event: Event) => { form.attachments = Array.from((event.target as HTMLInputElement).files ?? []) }
const openAttachmentPicker = () => {
    attachmentInputRef.value?.click()
}
const replyTypeLabel = (reply: Reply) => {
    if (reply.author_type === 'user') {
        return t('You')
    }

    if (reply.author_type === 'admin') {
        return t('Support agent')
    }

    return t(reply.author_type)
}
const getAvatarUrl = (avatar: string) => mediaUrl(avatar)
const reply = () => {
    form.post(route('user.dashboard.support.tickets.reply', props.ticket.ticket_number), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
const resolveTicket = () => router.post(route('user.dashboard.support.tickets.resolve', props.ticket.ticket_number), {}, { preserveScroll: true })
const ratingOptions = [
    { value: 5, label: '5 out of 5' },
    { value: 4, label: '4 out of 5' },
    { value: 3, label: '3 out of 5' },
    { value: 2, label: '2 out of 5' },
    { value: 1, label: '1 out of 5' }
]
const rateTicket = () => ratingForm.post(route('user.dashboard.support.tickets.rate', props.ticket.ticket_number), { preserveScroll: true })
</script>

<template>
    <Head :title="ticket.subject" />

    <div>
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ ticket.ticket_number }} · {{ ticket.department?.name }}</p>
            </div>
            <div class="shrink-0 flex flex-wrap items-center gap-3">
                <Link :href="route('user.dashboard.support.index')" class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.25 text-sm font-semibold text-gray-700 transition hover:!border-gray-200 hover:!bg-gray-50 hover:text-gray-700 dark:border-surface-700 dark:bg-surface-900/30 dark:text-gray-200 dark:hover:!border-gray-800 dark:hover:!bg-surface-900/30 dark:hover:!text-gray-300">
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>
                <button v-if="!['resolved', 'closed'].includes(ticket.status)" type="button" @click="resolveTicket" class="inline-flex items-center justify-center gap-2 rounded-full border border-green-500 px-5 py-2.25 text-sm font-semibold text-green-600 transition hover:!bg-green-600 hover:!text-white dark:border-green-900/80 dark:text-green-300">
                    <i class="ti ti-circle-check text-base"></i>
                    {{ t('Mark as Resolved') }}
                </button>
            </div>
        </div>

        <div class="space-y-4">
            <article v-for="replyItem in ticket.replies" :key="replyItem.id" :class="[replyItem.author_type === 'admin' && new Date(replyItem.created_at) > new Date(userLastReadAt) ? 'border-l-4 border-l-primary-500 border-primary-300 dark:border-l-primary-400 dark:border-primary-800' : 'border-gray-200 dark:border-surface-800']" class="rounded-2xl border bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:bg-surface-900">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-surface-800 flex items-center justify-center border border-gray-100 dark:border-surface-700">
                            <img
                                v-if="replyItem.author_avatar"
                                :src="getAvatarUrl(replyItem.author_avatar)"
                                :alt="replyItem.author_name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">
                                {{ replyItem.author_name.charAt(0) }}
                            </span>
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ replyItem.author_name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(replyItem.created_at)) }}</div>
                        </div>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 shadow-sm dark:bg-surface-200 dark:text-gray-500">{{ replyTypeLabel(replyItem) }}</span>
                </div>
                <div class="ticket-content reply-content prose prose-sm max-w-none prose-headings:text-gray-900 prose-p:text-gray-700 prose-li:text-gray-700 prose-strong:text-gray-900 prose-a:text-primary-600 prose-blockquote:text-gray-600 prose-hr:border-gray-200 dark:!text-gray-300 dark:prose-invert dark:prose-headings:text-white dark:prose-p:text-gray-200 dark:prose-li:text-gray-200 dark:prose-strong:text-white dark:prose-a:text-primary-300 dark:prose-blockquote:text-gray-300 dark:prose-hr:border-surface-700" v-html="sanitizeHtml(replyItem.content)"></div>
                <div v-if="replyItem.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                    <a v-for="attachment in replyItem.attachments" :key="attachment.url" :href="attachment.url" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300">
                        <i class="ti ti-download mr-1"></i>
                        {{ attachment.name }}
                    </a>
                </div>
            </article>
        </div>

        <form v-if="ticket.status !== 'closed'" @submit.prevent="reply" class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Reply') }}</h2>
            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Message') }}</label>
                <RichEditor v-model="form.message" variant="comment" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Attachments') }}</label>
                <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-2 py-1 dark:border-surface-700 dark:bg-surface-800">
                    <button type="button" @click="openAttachmentPicker" class="inline-flex items-center justify-center rounded-full border border-primary-200 bg-primary-50 px-4 py-1 text-sm font-semibold text-primary-600 transition hover:bg-primary-100 dark:border-primary-900/40 dark:bg-primary-900/30 dark:text-primary-400 dark:hover:bg-primary-500/25">
                        {{ t('Choose files') }}
                    </button>
                    <span class="min-w-0 flex-1 truncate text-sm text-gray-500 dark:text-gray-400">
                        {{ form.attachments.length ? t(':count file(s) selected', { count: String(form.attachments.length) }) : t('No files chosen') }}
                    </span>
                    <input ref="attachmentInputRef" type="file" multiple @change="setFiles" class="hidden">
                </div>
            </div>
            <p v-if="form.errors.message" class="mt-2 text-sm text-red-600">{{ form.errors.message }}</p>
            <div class="mt-4 flex justify-end">
                <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold btn-primary disabled:opacity-60">
                    <i class="ti ti-send"></i>
                    {{ form.processing ? t('Sending...') : t('Send Reply') }}
                </button>
            </div>
        </form>

        <form v-if="ticket.status === 'resolved' && settings.satisfaction_rating_enabled && !ticket.satisfaction_rating" @submit.prevent="rateTicket" class="mt-6 rounded-2xl border border-primary-200 bg-primary-50 p-5 dark:border-primary-900/40 dark:bg-primary-950/20">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Rate this support experience') }}</h2>
            <div class="mt-4">
                <AppSelect v-model="ratingForm.rating" :options="ratingOptions" :label="t('Rating')" />
            </div>
            <textarea v-model="ratingForm.comment" rows="3" class="mt-3 w-full rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-primary-900/40 dark:bg-surface-900 dark:text-white dark:placeholder:text-gray-500" :placeholder="t('Optional comment')"></textarea>
            <button type="submit" class="mt-3 inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold btn-primary">{{ t('Submit Rating') }}</button>
        </form>
    </div>
</template>

<style scoped>
.reply-content {
    color: rgb(55 65 81);
}

:global(.dark) .reply-content {
    color: rgb(229 231 235);
}

.reply-content :deep(*) {
    color: inherit !important;
}

.reply-content :deep(a) {
    color: rgb(37 99 235) !important;
}

:global(.dark) .reply-content :deep(a) {
    color: rgb(147 197 253) !important;
}

.reply-content :deep(blockquote) {
    border-inline-start: 3px solid rgb(229 231 235);
    padding-inline-start: 0.875rem;
    color: inherit !important;
}

:global(.dark) .reply-content :deep(blockquote) {
    border-inline-start-color: rgb(51 65 85);
}

.reply-content :deep(pre),
.reply-content :deep(code) {
    color: inherit !important;
}
</style>
