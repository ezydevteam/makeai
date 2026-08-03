<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'

interface CommentUser {
    name: string
    avatar: string | null
}

interface CommentItem {
    id: number
    content: string
    created_at: string | null
    likes_count: number
    liked: boolean
    can_edit: boolean
    can_delete: boolean
    user: CommentUser | null
    guest_name: string | null
    replies: CommentItem[]
}

const props = defineProps<{
    comments: {
        data: CommentItem[]
        links: Array<{ url: string | null; label: string; active: boolean }>
        meta: { total: number; current_page: number; last_page: number }
    }
    modelType: string
    modelId: number
    enabled: boolean
    allowGuests: boolean
    pollSeconds?: number
}>()

const page = usePage()
const { t } = useTranslate()

const authUser = computed(() => page.props.auth?.user)
const replyingTo = ref<CommentItem | null>(null)
const editingComment = ref<CommentItem | null>(null)
const reportingComment = ref<CommentItem | null>(null)
const deletingComment = ref<CommentItem | null>(null)
const deleteProcessing = ref(false)

const form = useForm({
    commentable_type: props.modelType,
    commentable_id: props.modelId,
    content: '',
    parent_id: null as number | null,
    guest_name: '',
    guest_email: '',
})

const editForm = useForm({ content: '' })
const reportForm = useForm({
    reason: '',
})
let pollTimer: number | undefined

const canComment = computed(() => props.enabled && (authUser.value || props.allowGuests))

const authorName = (comment: CommentItem) => comment.user?.name || comment.guest_name || t('Guest')
// Resolve stored avatar keys through the media helper (a raw key would render as a broken
// relative path). Falls back to an initials avatar for guests / users without one.
const avatarUrl = (comment: CommentItem) => mediaUrl(comment.user?.avatar) || `https://ui-avatars.com/api/?name=${encodeURIComponent(authorName(comment))}&background=10b981&color=fff`

const formatDate = (value: string | null) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(page.props.locale?.code ?? 'en', { dateStyle: 'medium' }).format(new Date(value))
}

const setReply = (comment: CommentItem) => {
    replyingTo.value = comment
    form.parent_id = comment.id
    document.getElementById('comment-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const cancelReply = () => {
    replyingTo.value = null
    form.parent_id = null
}

const submit = () => {
    form.post(route('comments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('content')
            cancelReply()
        },
    })
}

const likeComment = (comment: CommentItem) => {
    router.post(route('comments.like', comment.id), {}, { preserveScroll: true })
}

const startEdit = (comment: CommentItem) => {
    editingComment.value = comment
    editForm.content = comment.content
}

const updateComment = () => {
    if (!editingComment.value) return

    editForm.patch(route('comments.update', editingComment.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingComment.value = null
            editForm.reset()
        },
    })
}

const requestDelete = (comment: CommentItem) => {
    deletingComment.value = comment
}

const confirmDelete = () => {
    if (!deletingComment.value) return

    deleteProcessing.value = true
    router.delete(route('comments.delete', deletingComment.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false
            deletingComment.value = null
        },
    })
}

const startReport = (comment: CommentItem) => {
    reportingComment.value = comment
    reportForm.reset()
}

const submitReport = () => {
    if (!reportingComment.value) return

    reportForm.post(route('comments.report', reportingComment.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            reportingComment.value = null
            reportForm.reset()
        },
    })
}

onMounted(() => {
    const seconds = props.pollSeconds ?? 60
    if (props.enabled && seconds >= 10) {
        pollTimer = window.setInterval(() => {
            router.reload({ only: ['comments'] })
        }, seconds * 1000)
    }
})

onUnmounted(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer)
    }
})
</script>

<template>
    <section class="mt-10 space-y-8">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-surface-800">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ t(':count Comments', { count: comments.meta.total }) }}</h3>
        </div>

        <div v-if="!enabled" class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-center text-sm text-gray-500 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-400">
            {{ t('Comments are closed for this content.') }}
        </div>

        <div v-else id="comment-form" class="rounded-xl border border-gray-100 bg-gray-50 p-6 dark:border-surface-800 dark:bg-surface-900">
            <div v-if="!canComment" class="py-4 text-center">
                <p class="mb-4 text-sm text-gray-500">{{ t('Please log in to join the discussion.') }}</p>
                <Link :href="route('login')" class="text-sm font-bold text-primary-600 hover:underline">{{ t('Log In') }}</Link>
            </div>

            <form v-else class="space-y-4" @submit.prevent="submit">
                <div v-if="replyingTo" class="flex items-center justify-between rounded-lg border border-primary-100 bg-white px-4 py-2 dark:border-primary-900/40 dark:bg-surface-800">
                    <span class="text-sm font-semibold text-gray-500">{{ t('Replying to :name', { name: authorName(replyingTo) }) }}</span>
                    <button type="button" class="text-gray-400 hover:text-gray-600" :aria-label="t('Cancel reply')" @click="cancelReply">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div v-if="!authUser" class="grid gap-4 md:grid-cols-2">
                    <input v-model="form.guest_name" type="text" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Your name')" required>
                    <input v-model="form.guest_email" type="email" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Your email')" required>
                </div>

                <textarea v-model="form.content" rows="3" class="w-full resize-none rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm transition-all focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="replyingTo ? t('Write a reply...') : t('Share your thoughts...')" required></textarea>
                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="rounded-lg btn-primary shadow-lg shadow-primary-500/20 transition-all disabled:opacity-50">
                        {{ form.processing ? t('Posting...') : (replyingTo ? t('Send Reply') : t('Post Comment')) }}
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <div v-for="comment in comments.data" :key="comment.id" class="space-y-4">
                <div class="flex gap-4">
                    <img :src="avatarUrl(comment)" :alt="authorName(comment)" class="h-10 w-10 shrink-0 rounded-full border border-gray-100">
                    <div class="flex-1">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ authorName(comment) }}</span>
                            <span class="text-xs font-medium text-gray-400">{{ formatDate(comment.created_at) }}</span>
                        </div>
                        <form v-if="editingComment?.id === comment.id" class="space-y-3" @submit.prevent="updateComment">
                            <textarea v-model="editForm.content" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" required></textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="rounded-lg btn-primary">{{ t('Save') }}</button>
                                <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold" @click="editingComment = null">{{ t('Cancel') }}</button>
                            </div>
                        </form>
                        <p v-else class="mb-3 whitespace-pre-wrap text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ comment.content }}</p>
                        <div class="flex flex-wrap items-center gap-4">
                            <button type="button" class="flex items-center gap-1 text-xs font-bold transition-colors" :class="comment.liked ? 'text-primary-600' : 'text-gray-400 hover:text-primary-600'" :title="t('Helpful')" @click="likeComment(comment)">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 0 1 1.789 2.894l-3.5 7A2 2 0 0 1 15.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 0 0-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2h2.5" /></svg>
                                {{ comment.likes_count }}
                            </button>
                            <button v-if="authUser" type="button" class="text-xs font-bold text-gray-400 hover:text-primary-600" @click="setReply(comment)">{{ t('Reply') }}</button>
                            <button v-if="comment.can_edit" type="button" class="text-xs font-bold text-gray-400 hover:text-primary-600" @click="startEdit(comment)">{{ t('Edit') }}</button>
                            <button v-if="comment.can_delete" type="button" class="text-xs font-bold text-gray-400 hover:text-red-600" @click="requestDelete(comment)">{{ t('Delete') }}</button>
                            <button type="button" class="text-xs font-bold text-gray-400 hover:text-amber-600" @click="startReport(comment)">{{ t('Report') }}</button>
                        </div>
                    </div>
                </div>

                <div v-if="comment.replies?.length" class="ml-14 space-y-4 border-l-2 border-gray-50 pl-6 dark:border-surface-800">
                    <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-4">
                        <img :src="avatarUrl(reply)" :alt="authorName(reply)" class="h-8 w-8 shrink-0 rounded-full border border-gray-100">
                        <div class="flex-1">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-xs font-bold text-gray-900 dark:text-white">{{ authorName(reply) }}</span>
                                <span class="text-xs font-medium text-gray-400">{{ formatDate(reply.created_at) }}</span>
                            </div>
                            <p class="whitespace-pre-wrap text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ reply.content }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <Pagination
                :links="comments.links"
                :total="comments.meta.total"
                :current-page="comments.meta.current_page"
                :last-page="comments.meta.last_page"
                preserve-scroll
                align="left"
                class="pt-2"
            />
        </div>

        <AppModal
            :open="Boolean(reportingComment)"
            max-width="max-w-md"
            :title="t('Report comment')"
            has-form
            :confirm-text="t('Submit report')"
            :confirm-loading="reportForm.processing"
            @close="reportingComment = null"
            @submit="submitReport"
        >
            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ t('Flag this comment for moderator review? Our team will take a look.') }}
                </p>
                <div class="space-y-1">
                    <label for="report-reason" class="text-xs font-semibold text-gray-500">{{ t('Reason') }}</label>
                    <textarea
                        id="report-reason"
                        v-model="reportForm.reason"
                        rows="3"
                        maxlength="500"
                        class="w-full resize-none rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm transition-all focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        :placeholder="t('Explain why you are reporting this comment... (optional)')"
                    ></textarea>
                    <div class="text-right text-[10px] text-gray-400">
                        {{ reportForm.reason.length }}/500
                    </div>
                </div>
            </div>
        </AppModal>

        <ActionConfirmModal
            :open="Boolean(deletingComment)"
            title="Delete comment?"
            message="This action cannot be undone. The comment and its replies will be removed."
            confirm-label="Delete comment"
            processing-label="Deleting..."
            :processing="deleteProcessing"
            variant="danger"
            @cancel="deletingComment = null"
            @confirm="confirmDelete"
        />
    </section>
</template>
