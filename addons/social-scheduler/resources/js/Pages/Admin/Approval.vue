<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

type ApprovalPost = {
    id: number
    ulid: string
    title: string | null
    caption: string
    platforms: string[]
    post_type: string
    scheduled_at: string | null
    media_count: number
    user: { name: string; email: string } | null
    created_at: string
}

type PaginationLink = {
    url: string | null
    label: string
    active: boolean
}

type PostPagination = {
    data: ApprovalPost[]
    links: PaginationLink[]
}

const { t } = useTranslate()

const props = defineProps<{
    posts: PostPagination
}>()

const rejectForm = useForm({ reason: '' })
const rejectTarget = ref<ApprovalPost | null>(null)
const approveProcessingId = ref<number | null>(null)
const searchQuery = ref('')

const filteredPosts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()
    if (!query) {
        return props.posts.data
    }

    return props.posts.data.filter((post) => {
        const searchable = [
            post.title ?? '',
            post.caption,
            post.post_type,
            post.user?.name ?? '',
            post.user?.email ?? '',
            post.platforms.join(' '),
            post.scheduled_at ?? '',
        ].join(' ').toLowerCase()

        return searchable.includes(query)
    })
})

const hasPosts = computed(() => filteredPosts.value.length > 0)

const clearSearch = () => {
    searchQuery.value = ''
}

const platformClass = (platform: string) => ({
    instagram: 'bg-pink-100 text-pink-700 dark:bg-pink-500/10 dark:text-pink-300',
    facebook: 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
    twitter: 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    linkedin: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
}[platform] ?? 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300')

const statusClass = 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300'

function approve(post: ApprovalPost) {
    approveProcessingId.value = post.id

    router.post(route('addon.social.admin.approval.approve', post.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            approveProcessingId.value = null
            router.reload({ preserveScroll: true, preserveState: true })
        },
    })
}

function openReject(post: ApprovalPost) {
    rejectTarget.value = post
    rejectForm.reason = ''
}

function submitReject() {
    if (!rejectTarget.value) {
        return
    }

    router.post(route('addon.social.admin.approval.reject', rejectTarget.value.id), {
        reason: rejectForm.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            rejectTarget.value = null
        },
        onFinish: () => {
            router.reload({ preserveScroll: true, preserveState: true })
        },
    })
}
</script>

<template>
    <Head :title="t('Approval Queue')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Approval Queue') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review scheduled posts that require manual approval before they move to the publishing queue.') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    :href="route('addon.social.admin.overview')"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                >
                    <i class="ti ti-layout-dashboard text-base"></i>
                    {{ t('Overview') }}
                </Link>
                <Link
                    :href="route('addon.social.admin.settings')"
                    class="inline-flex items-center gap-2 rounded-lg btn-primary-admin px-4 py-2 text-sm font-medium"
                >
                    <i class="ti ti-settings text-base"></i>
                    {{ t('Settings') }}
                </Link>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ t('Approval Queue') }}
                        </h2>
                    </div>
                    <div class="relative w-full sm:max-w-md">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('Search by user, caption, platform, or date...')"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        >
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-3 top-1/2 inline-flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                            :aria-label="t('Clear search')"
                            @click="clearSearch"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50/80 dark:bg-surface-950">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                {{ t('User') }}
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                {{ t('Post') }}
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                {{ t('Platforms') }}
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                {{ t('Scheduled') }}
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                {{ t('Actions') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-if="!hasPosts">
                            <td colspan="5" class="px-5 py-14 text-center text-sm text-gray-500 dark:text-gray-400">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-surface-800 dark:text-gray-500">
                                    <i class="ti ti-clipboard-check text-xl"></i>
                                </div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ searchQuery ? t('No matching posts found.') : t('No posts awaiting approval.') }}
                                </p>
                                <p class="mt-1">
                                    {{ searchQuery ? t('Try a different search term.') : t('New submissions will appear here once they are sent for review.') }}
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="post in filteredPosts"
                            :key="post.ulid"
                            class="transition hover:bg-primary-50/60 dark:hover:bg-surface-800/70"
                        >
                            <td class="px-5 py-4 align-top">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ post.user?.name ?? t('Unknown') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ post.user?.email ?? '—' }}
                                    </p>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="min-w-0 max-w-[26rem]">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                        {{ post.title || post.caption }}
                                    </p>
                                    <p class="mt-1 line-clamp-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                        {{ post.caption }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                            {{ post.post_type }}
                                        </span>
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                            {{ t('Pending Approval') }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="platform in post.platforms"
                                        :key="platform"
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="platformClass(platform)"
                                    >
                                        {{ platform }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-gray-400">
                                    {{ post.media_count }} {{ t('media items') }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top text-sm text-gray-600 dark:text-gray-300">
                                {{ post.scheduled_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(post.scheduled_at)) : '—' }}
                            </td>

                            <td class="px-5 py-4 align-top text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        :disabled="approveProcessingId === post.id"
                                        class="inline-flex items-center gap-2 rounded-lg btn-primary-admin px-4 py-2 text-sm disabled:opacity-60"
                                        @click="approve(post)"
                                    >
                                        <i class="ti ti-check text-base"></i>
                                        {{ approveProcessingId === post.id ? t('Approving...') : t('Approve') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 dark:border-red-900/40 dark:bg-surface-900 dark:text-red-300 dark:hover:bg-red-900/10"
                                        @click="openReject(post)"
                                    >
                                        <i class="ti ti-x text-base"></i>
                                        {{ t('Reject') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="props.posts.links?.length" class="border-t border-gray-100 px-5 py-4 dark:border-surface-800">
                <Pagination :links="props.posts.links" />
            </div>
        </section>
    </div>

    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="rejectTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
            @click.self="rejectTarget = null"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-3 dark:border-surface-800">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ t('Reject Post') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Tell the author why this post was rejected.') }}
                    </p>
                </div>

                <div class="p-5">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ t('Reason') }}
                    </label>
                    <textarea
                        v-model="rejectForm.reason"
                        rows="4"
                        maxlength="500"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                        :placeholder="t('Provide a reason for rejection')"
                    ></textarea>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('This note will be saved with the post rejection record.') }}
                    </p>
                </div>

                <div class="border-t border-gray-100 bg-gray-50/80 px-5 py-3 dark:border-surface-800 dark:bg-surface-950">
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                            @click="rejectTarget = null"
                        >
                            {{ t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="!rejectForm.reason.trim() || rejectForm.processing"
                            class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-red-500 to-red-600 px-4 py-2 text-sm font-medium text-white transition hover:from-red-600 hover:to-red-700 disabled:opacity-60"
                            @click="submitReject"
                        >
                            <i class="ti ti-ban text-base"></i>
                            {{ rejectForm.processing ? t('Rejecting...') : t('Reject') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
