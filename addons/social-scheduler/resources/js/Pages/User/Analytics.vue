<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

type PlatformAnalytics = {
    platform: string
    total_impressions: number | string
    total_likes: number | string
    total_comments: number | string
    total_shares: number | string
    avg_engagement: number | string
    post_count: number | string
}

type TopPost = {
    title: string | null
    caption: string
    platform: string
    external_post_url: string | null
    impressions: number | string
    engagement_rate: number | string
    likes: number | string
    comments: number | string
}

const props = defineProps<{
    platforms: PlatformAnalytics[]
    top_posts: TopPost[]
}>()

const { t } = useTranslate()

function toNumber(value: number | string) {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : 0
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

    return 'ti ti-chart-bar'
}

function platformTone(platform: string) {
    if (platform === 'instagram') return 'bg-pink-50 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300'
    if (platform === 'facebook') return 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
    if (platform === 'twitter') return 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
    if (platform === 'linkedin') return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'

    return 'bg-gray-100 text-gray-700 dark:bg-surface-800 dark:text-gray-300'
}

function formatNumber(value: number | string) {
    return new Intl.NumberFormat().format(toNumber(value))
}

function formatPercent(value: number | string) {
    return `${toNumber(value).toFixed(1)}%`
}

const totalImpressions = computed(() =>
    props.platforms.reduce((sum, platform) => sum + toNumber(platform.total_impressions), 0),
)

const totalPosts = computed(() =>
    props.platforms.reduce((sum, platform) => sum + toNumber(platform.post_count), 0),
)

const totalLikes = computed(() =>
    props.platforms.reduce((sum, platform) => sum + toNumber(platform.total_likes), 0),
)

const averageEngagement = computed(() => {
    if (props.platforms.length === 0) {
        return 0
    }

    const total = props.platforms.reduce((sum, platform) => sum + toNumber(platform.avg_engagement), 0)
    return total / props.platforms.length
})

const summaryCards = computed(() => [
    {
        label: t('Impressions'),
        value: formatNumber(totalImpressions.value),
        helper: t('Across tracked posts'),
        icon: 'ti ti-chart-bar',
        tone: 'bg-primary-50 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300',
    },
    {
        label: t('Published Posts'),
        value: formatNumber(totalPosts.value),
        helper: t('Contributing to analytics'),
        icon: 'ti ti-article',
        tone: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    },
    {
        label: t('Total Likes'),
        value: formatNumber(totalLikes.value),
        helper: t('Audience reactions'),
        icon: 'ti ti-heart',
        tone: 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    },
    {
        label: t('Avg. Engagement'),
        value: formatPercent(averageEngagement.value),
        helper: t('Platform average'),
        icon: 'ti ti-activity-heartbeat',
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
])
</script>

<template>
    <Head :title="t('Analytics')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Post Analytics') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Track how your scheduled content performs across networks, then use the strongest signals to guide future posts.') }}</p>
            </div>

            <Link
                :href="route('addon.social.user.posts.index')"
                class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:bg-surface-800"
            >
                <i class="ti ti-list-details text-sm"></i>
                {{ t('View Posts') }}
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

        <div v-if="platforms.length" class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div
                v-for="platform in platforms"
                :key="platform.platform"
                class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6"
            >
                <div class="flex items-center gap-3">
                    <div :class="platformTone(platform.platform)" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl">
                        <i :class="platformIcon(platform.platform)" class="text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ platformLabel(platform.platform) }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t(':count tracked post(s)', { count: formatNumber(platform.post_count) }) }}</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-surface-800/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-gray-400 dark:text-gray-500">{{ t('Impressions') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatNumber(platform.total_impressions) }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-surface-800/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-gray-400 dark:text-gray-500">{{ t('Engagement') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatPercent(platform.avg_engagement) }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-surface-800/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-gray-400 dark:text-gray-500">{{ t('Likes') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatNumber(platform.total_likes) }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-surface-800/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-gray-400 dark:text-gray-500">{{ t('Comments') }}</p>
                        <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatNumber(platform.total_comments) }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded-2xl bg-gray-50 p-4 dark:bg-surface-800/60">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-gray-400 dark:text-gray-500">{{ t('Shares') }}</p>
                            <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white">{{ formatNumber(platform.total_shares) }}</p>
                        </div>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-surface-900 dark:text-gray-300 dark:ring-surface-700">
                            {{ t('Posts: :count', { count: formatNumber(platform.post_count) }) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed border-gray-300 bg-white/90 p-10 text-center shadow-[0_18px_40px_rgba(15,23,42,0.04)] ring-1 ring-black/5 dark:border-surface-700 dark:bg-gray-900 dark:ring-white/5"
        >
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                <i class="ti ti-chart-bar text-2xl"></i>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('No analytics yet') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ t('Publish your first scheduled post to start collecting impressions, engagement, and platform-level performance data here.') }}</p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5">
            <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 dark:border-surface-800 sm:px-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Top Performing Posts') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Your best engagement results, ranked from strongest to weakest.') }}</p>
            </div>

            <div v-if="top_posts.length" class="divide-y divide-gray-100 dark:divide-surface-800">
                <div
                    v-for="(post, index) in top_posts"
                    :key="`${post.platform}-${index}`"
                    class="px-5 py-5 transition hover:bg-gray-50/80 dark:hover:bg-surface-800/40 sm:px-6"
                >
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-start gap-2">
                                <h3 class="min-w-0 text-base font-semibold text-gray-900 dark:text-white">{{ post.title || post.caption }}</h3>
                                <span :class="platformTone(post.platform)" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold">
                                    <i :class="platformIcon(post.platform)" class="text-xs"></i>
                                    {{ platformLabel(post.platform) }}
                                </span>
                            </div>

                            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ post.caption }}</p>

                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-chart-bar text-sm"></i>
                                    {{ formatNumber(post.impressions) }} {{ t('impressions') }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-heart text-sm"></i>
                                    {{ formatNumber(post.likes) }} {{ t('likes') }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                                    <i class="ti ti-message-circle text-sm"></i>
                                    {{ formatNumber(post.comments) }} {{ t('comments') }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                                    <i class="ti ti-trending-up text-sm"></i>
                                    {{ formatPercent(post.engagement_rate) }} {{ t('engagement') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 xl:justify-end">
                            <a
                                v-if="post.external_post_url"
                                :href="post.external_post_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:bg-surface-800"
                            >
                                <i class="ti ti-external-link text-sm"></i>
                                {{ t('Open Post') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                {{ t('No analytics yet. Publish your first post to start seeing data.') }}
            </div>
        </div>
    </div>
</template>
