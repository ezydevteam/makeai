<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

type PlatformRow = {
    platform: string
    count: number
}

type RecentPost = {
    ulid: string
    title: string | null
    caption: string
    status: string
    platforms: string[]
    scheduled_at: string | null
    user: { name: string } | null
}

interface ComparisonData {
    label: string
    type: 'up' | 'down' | 'neutral'
}

interface StatObject {
    value: number
    comparison: ComparisonData
}

const props = defineProps<{
    total_posts: StatObject
    scheduled_posts: StatObject
    pending_approval: StatObject
    published_today: StatObject
    failed_posts: StatObject
    platform_breakdown: PlatformRow[]
    recent_posts: RecentPost[]
}>()

const platformLabels: Record<string, string> = {
    instagram: 'Instagram',
    facebook: 'Facebook',
    twitter: 'X / Twitter',
    linkedin: 'LinkedIn',
}

const platformLabel = (platform: string) => platformLabels[platform] ?? platform

const statusClass = (status: string) => {
    switch (status) {
        case 'published':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300'
        case 'scheduled':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-300'
        case 'pending_approval':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300'
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-300'
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300'
    }
}

const approvalBadgeClass = computed(() => (
    props.pending_approval > 0
        ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200'
        : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300'
))
</script>

<template>
    <Head :title="t('Social Scheduler Overview')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Social Scheduler') }}
                    </h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Addon') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Track scheduled content, approval traffic, publishing success, and connected accounts from one unified dashboard.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="route('addon.social.admin.approval.index')"
                    class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition"
                    :class="approvalBadgeClass"
                >
                    <i class="ti ti-clipboard-check text-base"></i>
                    <span v-if="pending_approval.value > 0">
                        {{ t('Approval Queue') }} ({{ pending_approval.value }})
                    </span>
                    <span v-else>
                        {{ t('Approval Queue') }}
                    </span>
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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <StatsCard
                :title="t('Total Posts')"
                :value="total_posts.value"
                :comparison="total_posts.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="total_posts.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-notes text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Scheduled')"
                :value="scheduled_posts.value"
                :comparison="scheduled_posts.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="scheduled_posts.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-calendar-event text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Pending Approval')"
                :value="pending_approval.value"
                :comparison="pending_approval.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="pending_approval.comparison.type"
                :color="pending_approval.value > 0 ? 'warning' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-clock text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Published Today')"
                :value="published_today.value"
                :comparison="published_today.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="published_today.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-circle-check text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Failed')"
                :value="failed_posts.value"
                :comparison="failed_posts.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="failed_posts.comparison.type"
                :color="failed_posts.value > 0 ? 'danger' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-alert-triangle text-lg"></i>
                </template>
            </StatsCard>


        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ t('Accounts by Platform') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Connected account distribution across supported platforms.') }}
                    </p>
                </div>

                <div class="px-5 py-5">
                    <div v-if="platform_breakdown.length" class="space-y-3">
                        <div
                            v-for="row in platform_breakdown"
                            :key="row.platform"
                            class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3 dark:border-surface-800 dark:bg-surface-800/60"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ platformLabel(row.platform) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ t('Active connections') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="h-2.5 w-28 overflow-hidden rounded-full bg-gray-200 dark:bg-surface-700">
                                    <div
                                        class="h-full rounded-full bg-primary-500"
                                        :style="{ width: `${Math.min(Math.max(row.count * 20, 10), 100)}%` }"
                                    ></div>
                                </div>
                                <span class="w-8 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ row.count }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No connected accounts yet.') }}
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ t('Recent Posts') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Latest posts and their current publishing state.') }}
                    </p>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-if="recent_posts.length === 0" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ t('No posts yet.') }}
                    </div>

                    <div
                        v-for="post in recent_posts"
                        :key="post.ulid"
                        class="flex items-start gap-4 px-5 py-4 transition hover:bg-primary-50/60 dark:hover:bg-surface-800/70"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ post.title || post.caption }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ post.user?.name ?? t('Unknown') }}
                                        <span v-if="post.scheduled_at"> · {{ post.scheduled_at }}</span>
                                    </p>
                                </div>

                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(post.status)">
                                    {{ post.status.replaceAll('_', ' ') }}
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span
                                    v-for="platform in post.platforms"
                                    :key="platform"
                                    class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300"
                                >
                                    {{ platformLabel(platform) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
