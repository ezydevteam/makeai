<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, defineAsyncComponent } from 'vue'
import AppSelect from '@/Components/AppSelect.vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { sanitizeHtml } from '@/Composables/useSanitize'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))

defineOptions({ layout: UserDashboardLayout })

declare const route: (name: string, params?: unknown) => string

interface Reply {
    id: number
    author_type: 'user' | 'admin'
    author_name: string
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
const setFiles = (event: Event) => { form.attachments = Array.from((event.target as HTMLInputElement).files ?? []) }
const reply = () => {
    form.post(route('user.dashboard.support.tickets.reply', props.ticket.ticket_number), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    })
}
const resolveTicket = () => router.post(route('user.dashboard.support.tickets.resolve', props.ticket.ticket_number), {}, { preserveScroll: true })
const ratingOptions = [{ value: 5, label: '5 / 5' }, { value: 4, label: '4 / 5' }, { value: 3, label: '3 / 5' }, { value: 2, label: '2 / 5' }, { value: 1, label: '1 / 5' }]
const rateTicket = () => ratingForm.post(route('user.dashboard.support.tickets.rate', props.ticket.ticket_number), { preserveScroll: true })
</script>

<template>
    <Head :title="ticket.subject" />

    <div>
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <Link :href="route('user.dashboard.support.index')" class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ t('Back to support') }}</Link>
                <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ ticket.ticket_number }} · {{ ticket.department?.name }}</p>
            </div>
            <button v-if="!['resolved', 'closed'].includes(ticket.status)" type="button" @click="resolveTicket" class="rounded-lg border border-primary-500 px-4 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50">
                {{ t('Mark as Resolved') }}
            </button>
        </div>

        <div class="space-y-4">
            <article v-for="replyItem in ticket.replies" :key="replyItem.id" :class="[replyItem.author_type === 'admin' && new Date(replyItem.created_at) > new Date(userLastReadAt) ? 'border-l-4 border-l-primary-500 border-primary-300' : 'border-gray-200']" class="rounded-xl border bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="mb-3 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ replyItem.author_name }}</div>
                        <div class="text-xs text-gray-500">{{ new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(replyItem.created_at)) }}</div>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">{{ t(replyItem.author_type) }}</span>
                </div>
                <div class="prose prose-sm max-w-none dark:prose-invert" v-html="sanitizeHtml(replyItem.content)"></div>
                <div v-if="replyItem.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                    <a v-for="attachment in replyItem.attachments" :key="attachment.url" :href="attachment.url" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-primary-50">{{ attachment.name }}</a>
                </div>
            </article>
        </div>

        <form v-if="ticket.status !== 'closed'" @submit.prevent="reply" class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('Reply') }}</h2>
            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Message') }}</label>
                <RichEditor v-model="form.message" variant="comment" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Attachments') }}</label>
                <input type="file" multiple @change="setFiles" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
            </div>
            <p v-if="form.errors.message" class="mt-2 text-sm text-red-600">{{ form.errors.message }}</p>
            <div class="mt-4 flex justify-end">
                <button type="submit" :disabled="form.processing" class="rounded-lg btn-primary disabled:opacity-60">
                    {{ form.processing ? t('Sending...') : t('Send Reply') }}
                </button>
            </div>
        </form>

        <form v-if="ticket.status === 'resolved' && settings.satisfaction_rating_enabled && !ticket.satisfaction_rating" @submit.prevent="rateTicket" class="mt-6 rounded-xl border border-primary-200 bg-primary-50 p-5">
            <h2 class="text-lg font-bold text-gray-900">{{ t('Rate this support experience') }}</h2>
            <div class="mt-4">
                <AppSelect v-model="ratingForm.rating" :options="ratingOptions" :label="t('Rating')" />
            </div>
            <textarea v-model="ratingForm.comment" rows="3" class="mt-3 w-full rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm" :placeholder="t('Optional comment')"></textarea>
            <button type="submit" class="mt-3 rounded-lg btn-primary">{{ t('Submit Rating') }}</button>
        </form>
    </div>
</template>
