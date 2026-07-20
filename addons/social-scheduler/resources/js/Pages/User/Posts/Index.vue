<script setup lang="ts">
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: UserDashboardLayout })

type SocialPost = {
    ulid: string
    title: string | null
    caption: string
    platforms: string[]
    status: string
    post_type: string
    is_overdue: boolean
    scheduled_at: string | null
    published_at: string | null
    created_at: string
    media: Array<{ url: string; type: string }>
    platform_statuses: Array<{ platform: string; status: string }>
}

type PaginatedPosts = {
    data: SocialPost[]
    total?: number
}

const props = defineProps<{
    posts: PaginatedPosts
}>()

const { t } = useTranslate()
const deletingPost = ref<SocialPost | null>(null)
const deleteProcessing = ref(false)

const hasPosts = computed(() => props.posts.data.length > 0)
const visibleTotal = computed(() => props.posts.total ?? props.posts.data.length)

const summaryCards = computed(() => {
    const statuses = props.posts.data.reduce<Record<string, number>>((counts, post) => {
        counts[post.status] = (counts[post.status] ?? 0) + 1
        return counts
    }, {})

    return [
        {
            label: t('Total Posts'),
            value: visibleTotal.value,
            tone: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
            icon: 'ti ti-layout-grid',
            helper: t('Current queue'),
        },
        {
            label: t('Scheduled'),
            value: statuses.scheduled ?? 0,
            tone: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
            icon: 'ti ti-calendar-time',
            helper: t('Ready to publish'),
        },
        {
            label: t('Pending Approval'),
            value: statuses.pending_approval ?? 0,
            tone: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            icon: 'ti ti-shield-check',
            helper: t('Waiting for review'),
        },
        {
            label: t('Published'),
            value: statuses.published ?? 0,
            tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            icon: 'ti ti-rocket',
            helper: t('Already live'),
        },
    ]
})

function statusClass(status: string) {
    if (status === 'published') return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/20'
    if (status === 'scheduled') return 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/20'
    if (status === 'pending_approval') return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/20'
    if (status === 'publishing') return 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/20'
    if (status === 'partial') return 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-500/20'
    if (status === 'failed') return 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/20'
    return 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-surface-800 dark:text-gray-300 dark:ring-surface-700'
}

function statusLabel(status: string) {
    if (status === 'draft') return t('Draft')
    if (status === 'pending_approval') return t('Pending Approval')
    if (status === 'scheduled') return t('Scheduled')
    if (status === 'publishing') return t('Publishing')
    if (status === 'published') return t('Published')
    if (status === 'partial') return t('Partially Published')
    if (status === 'failed') return t('Failed')
    if (status === 'cancelled') return t('Cancelled')

    return status
}

function postTypeLabel(type: string) {
    if (type === 'single') return t('Single')
    if (type === 'carousel') return t('Carousel')
    if (type === 'thread') return t('Thread')
    if (type === 'story') return t('Story')
    if (type === 'reel') return t('Reel')

    return type
}

function platformLabel(platform: string) {
    if (platform === 'instagram') return t('Instagram')
    if (platform === 'facebook') return t('Facebook')
    if (platform === 'twitter') return t('X / Twitter')
    if (platform === 'linkedin') return t('LinkedIn')

    return platform
}

function platformIcon(platform: string) {
    if (platform === 'instagram') return 'ti ti-brand-instagram'
    if (platform === 'facebook') return 'ti ti-brand-facebook'
    if (platform === 'twitter') return 'ti ti-brand-x'
    if (platform === 'linkedin') return 'ti ti-brand-linkedin'

    return 'ti ti-device-mobile'
}

function formatDateTime(value: string | null) {
    if (!value) {
        return t('Not scheduled')
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return t('Invalid date')
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date)
}

function openDeleteModal(post: SocialPost) {
    deletingPost.value = post
}

function closeDeleteModal() {
    if (!deleteProcessing.value) {
        deletingPost.value = null
    }
}

function deletePost() {
    if (!deletingPost.value) {
        return
    }

    deleteProcessing.value = true

    router.delete(route('addon.social.user.posts.index') + '/' + deletingPost.value.ulid, {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false
            deletingPost.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('Posts')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Scheduled Posts') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Review your queue, monitor status, and jump back into editing when something needs attention.') }}</p>
            </div>

            <Link
                :href="route('addon.social.user.posts.create')"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold !text-white shadow-sm transition hover:bg-primary-600 sm:w-auto"
            >
                <i class="ti ti-plus text-sm"></i>
                {{ t('New Post') }}
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ card.label }}</p>
                        <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ card.value }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ card.helper }}</p>
                    </div>

                    <div :class="card.tone" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl">
                        <i :class="card.icon" class="text-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="!hasPosts"
            class="rounded-2xl border border-dashed border-gray-300 bg-white/90 p-10 text-center shadow-[0_18px_40px_rgba(15,23,42,0.04)] ring-1 ring-black/5 dark:border-surface-700 dark:bg-gray-900 dark:ring-white/5"
        >
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                <i class="ti ti-calendar-plus text-2xl"></i>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('No posts yet') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ t('Start your queue with a new draft or scheduled post, then come back here to track progress across all connected platforms.') }}</p>
            <Link
                :href="route('addon.social.user.posts.create')"
                class="mt-5 inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold !text-white shadow-sm transition hover:bg-primary-600"
            >
                <i class="ti ti-plus text-sm"></i>
                {{ t('Create your first post') }}
            </Link>
        </div>

        <div
            v-else
            class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5"
        >
            <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Post Queue') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Showing :count posts from your current queue.', { count: visibleTotal }) }}</p>
                </div>

                <div class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                    <i class="ti ti-clock-hour-4 text-sm"></i>
                    {{ t('Latest first') }}
                </div>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-surface-800">
                <div
                    v-for="post in posts.data"
                    :key="post.ulid"
                    class="px-5 py-5 transition hover:bg-gray-50/80 dark:hover:bg-surface-800/40 sm:px-6"
                >
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-start gap-2">
                                <h3 class="min-w-0 text-base font-semibold text-gray-900 dark:text-white">
                                    {{ post.title || post.caption }}
                                </h3>
                                <span :class="statusClass(post.status)" class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset">
                                    {{ statusLabel(post.status) }}
                                </span>
                                <span
                                    v-if="post.is_overdue"
                                    class="inline-flex shrink-0 items-center rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-500/20"
                                >
                                    {{ t('Overdue') }}
                                </span>
                            </div>

                            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ post.caption }}</p>

                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-layout text-sm"></i>
                                    {{ postTypeLabel(post.post_type) }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-photo text-sm"></i>
                                    {{ t(':count media', { count: post.media.length }) }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-calendar-event text-sm"></i>
                                    {{ formatDateTime(post.scheduled_at) }}
                                </span>
                                <span v-if="post.published_at" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-send text-sm"></i>
                                    {{ t('Published :time', { time: formatDateTime(post.published_at) }) }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="platform in post.platforms"
                                    :key="platform"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300"
                                >
                                    <i :class="platformIcon(platform)" class="text-sm"></i>
                                    {{ platformLabel(platform) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 xl:w-[15rem] xl:items-end">
                            <div class="flex w-full flex-wrap gap-2 xl:justify-end">
                                <Link
                                    v-if="['draft', 'scheduled', 'pending_approval'].includes(post.status)"
                                    :href="route('addon.social.user.posts.index') + '/' + post.ulid + '/edit'"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:bg-surface-800 xl:flex-none"
                                >
                                    <i class="ti ti-pencil text-sm"></i>
                                    {{ t('Edit') }}
                                </Link>

                                <button
                                    type="button"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-full border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-900/60 dark:bg-surface-900 dark:text-red-400 dark:hover:bg-red-950/30 xl:flex-none"
                                    :disabled="post.status === 'publishing'"
                                    @click="openDeleteModal(post)"
                                >
                                    <i class="ti ti-trash text-sm"></i>
                                    {{ t('Delete') }}
                                </button>
                            </div>

                            <div
                                v-if="post.platform_statuses.length"
                                class="flex w-full flex-wrap gap-2 xl:justify-end"
                            >
                                <span
                                    v-for="platformStatus in post.platform_statuses"
                                    :key="`${post.ulid}-${platformStatus.platform}`"
                                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300"
                                >
                                    <i :class="platformIcon(platformStatus.platform)" class="text-xs"></i>
                                    {{ platformLabel(platformStatus.platform) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ActionConfirmModal
            :open="!!deletingPost"
            :title="t('Delete post')"
            :message="t('This scheduled post will be removed permanently from your queue.')"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            :processing="deleteProcessing"
            @confirm="deletePost"
            @cancel="closeDeleteModal"
        />
    </div>
</template>
