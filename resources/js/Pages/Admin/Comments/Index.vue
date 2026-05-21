<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface CommentItem {
    id: number
    content: string
    status: 'pending' | 'approved' | 'spam'
    guest_name: string | null
    guest_email: string | null
    likes_count: number
    reports_count: number
    created_at: string
    user: { name: string; email: string } | null
    commentable: { title?: string; name?: string; slug?: string } | null
}

const props = defineProps<{
    comments: {
        data: CommentItem[]
        links: Array<{ url: string | null; label: string; active: boolean }>
    }
    filters: { status?: string }
    pendingCount: number
    settings: {
        comments_enabled: boolean
        comments_auto_approve_users: boolean
        comments_allow_guests: boolean
        comments_require_approval: boolean
        comments_notify_admin: boolean
        comments_poll_seconds: number
        comments_akismet_configured: boolean
    }
}>()

const { t } = useTranslate()

const settingsForm = useForm({
    comments_enabled: props.settings.comments_enabled,
    comments_auto_approve_users: props.settings.comments_auto_approve_users,
    comments_allow_guests: props.settings.comments_allow_guests,
    comments_require_approval: props.settings.comments_require_approval,
    comments_notify_admin: props.settings.comments_notify_admin,
    comments_poll_seconds: props.settings.comments_poll_seconds,
    comments_akismet_key: '',
})

const statusLabel = (status: string) => {
    if (status === 'approved') return t('Approved')
    if (status === 'spam') return t('Spam')
    return t('Pending')
}

const authorName = (comment: CommentItem) => comment.user?.name || comment.guest_name || t('Guest')
const contentTitle = (comment: CommentItem) => comment.commentable?.title || comment.commentable?.name || t('Unknown content')

const filterBy = (status: string | null) => {
    router.get(route('admin.comments.index'), status ? { status } : {}, { preserveScroll: true, preserveState: true })
}

const approve = (comment: CommentItem) => router.post(route('admin.comments.approve', comment.id), {}, { preserveScroll: true })
const markSpam = (comment: CommentItem) => router.post(route('admin.comments.spam', comment.id), {}, { preserveScroll: true })
const remove = (comment: CommentItem) => router.delete(route('admin.comments.delete', comment.id), { preserveScroll: true })
const saveSettings = () => settingsForm.post(route('admin.comments.settings'), { preserveScroll: true })
</script>

<template>
    <Head :title="t('Comments')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Comments') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t(':count comments waiting for moderation.', { count: pendingCount }) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium" @click="filterBy(null)">{{ t('All') }}</button>
                <button type="button" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700" @click="filterBy('pending')">{{ t('Pending') }}</button>
                <button type="button" class="rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700" @click="filterBy('approved')">{{ t('Approved') }}</button>
                <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700" @click="filterBy('spam')">{{ t('Spam') }}</button>
            </div>
        </div>

        <section class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ t('Comment settings') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ t('Control moderation, guest comments, notifications, and spam filtering.') }}</p>
                </div>
                <button type="button" :disabled="settingsForm.processing" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" @click="saveSettings">
                    {{ settingsForm.processing ? t('Saving...') : t('Save settings') }}
                </button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium">
                    {{ t('Enable comments globally') }}
                    <input v-model="settingsForm.comments_enabled" type="checkbox">
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium">
                    {{ t('Auto-approve logged-in users') }}
                    <input v-model="settingsForm.comments_auto_approve_users" type="checkbox">
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium">
                    {{ t('Allow guest comments') }}
                    <input v-model="settingsForm.comments_allow_guests" type="checkbox">
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium">
                    {{ t('Require approval for all') }}
                    <input v-model="settingsForm.comments_require_approval" type="checkbox">
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium">
                    {{ t('Notify admin on new comment') }}
                    <input v-model="settingsForm.comments_notify_admin" type="checkbox">
                </label>
                <label class="block rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium">
                    <span class="mb-1 block">{{ t('Polling interval seconds') }}</span>
                    <input v-model="settingsForm.comments_poll_seconds" type="number" min="10" max="300" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                </label>
                <label class="block rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium md:col-span-2 xl:col-span-3">
                    <span class="mb-1 block">{{ t('Akismet API key') }}</span>
                    <input v-model="settingsForm.comments_akismet_key" type="password" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" :placeholder="settings.comments_akismet_configured ? t('Configured - leave blank to keep') : t('Optional spam filter key')">
                </label>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ t('Comment') }}</th>
                        <th class="px-4 py-3">{{ t('Author') }}</th>
                        <th class="px-4 py-3">{{ t('Content') }}</th>
                        <th class="px-4 py-3">{{ t('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="comment in comments.data" :key="comment.id" class="border-t border-gray-100 hover:bg-primary-50/30">
                        <td class="max-w-lg px-4 py-3">
                            <p class="line-clamp-3 text-gray-700">{{ comment.content }}</p>
                            <p class="mt-2 text-xs text-gray-400">{{ t(':likes likes, :reports reports', { likes: comment.likes_count, reports: comment.reports_count }) }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-900">{{ authorName(comment) }}</div>
                            <div class="text-xs text-gray-500">{{ comment.user?.email || comment.guest_email }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ contentTitle(comment) }}</td>
                        <td class="px-4 py-3">
                            <span :class="comment.status === 'approved' ? 'bg-primary-100 text-primary-700' : comment.status === 'spam' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                                {{ statusLabel(comment.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button v-if="comment.status !== 'approved'" type="button" class="mr-2 rounded-lg border border-primary-200 px-3 py-1 text-xs font-semibold text-primary-700" @click="approve(comment)">{{ t('Approve') }}</button>
                            <button v-if="comment.status !== 'spam'" type="button" class="mr-2 rounded-lg border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700" @click="markSpam(comment)">{{ t('Spam') }}</button>
                            <button type="button" class="rounded-lg bg-red-500 px-3 py-1 text-xs font-semibold text-white" @click="remove(comment)">{{ t('Delete') }}</button>
                        </td>
                    </tr>
                    <tr v-if="comments.data.length === 0">
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">{{ t('No comments found.') }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="comments.links.length > 3" class="flex flex-wrap gap-2 border-t border-gray-100 p-4">
                <Link v-for="link in comments.links" :key="link.label" :href="link.url || '#'" preserve-scroll :class="[link.active ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600', !link.url ? 'pointer-events-none opacity-50' : '']" class="rounded-lg px-3 py-1 text-xs font-semibold" v-html="link.label" />
            </div>
        </section>
    </div>
</template>
