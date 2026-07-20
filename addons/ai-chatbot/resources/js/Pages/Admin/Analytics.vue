<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

interface ComparisonData {
    label: string
    type: 'up' | 'down' | 'neutral'
}

interface StatObject {
    value: number
    comparison: ComparisonData
}

interface Stats {
    total_conversations: StatObject
    total_messages: StatObject
    total_tokens: StatObject
    total_credits: StatObject
    likes_count: number
    dislikes_count: number
}

interface TrendDay {
    date: string
    conversations: number
    messages: number
}

interface ModelPopularity {
    model: string
    name: string
    provider: string
    messages_count: number
}

interface ModePopularity {
    slug: string
    name: string
    conversations_count: number
}

interface Feedback {
    id: number
    ulid: string
    rating: number
    comment: string | null
    created_at: string
    user?: {
        id: number
        name: string
        email: string
    }
    conversation?: {
        id: number
        ulid: string
        title: string | null
    }
}

const props = defineProps<{
    stats: Stats
    trends: TrendDay[]
    modelsPopularity: ModelPopularity[]
    modesPopularity: ModePopularity[]
    recentFeedbacks: Feedback[]
}>()

const { t } = useTranslate()

const hoveredDayIndex = ref<number | null>(null)

// Calculate max value for chart scaling
const maxVal = computed(() => {
    if (!props.trends.length) return 10
    const vals = props.trends.map(t => Math.max(t.conversations, t.messages))
    return Math.max(...vals, 10)
})

// Generate SVG Paths for Area/Line Chart
const chartDimensions = {
    width: 1000,
    height: 260,
    paddingX: 40,
    paddingY: 30
}

const chartData = computed(() => {
    if (!props.trends.length) {
        return {
            convLine: '',
            convArea: '',
            msgLine: '',
            msgArea: '',
            points: []
        }
    }

    const { width, height, paddingX, paddingY } = chartDimensions
    const max = maxVal.value
    const stepX = (width - paddingX * 2) / (props.trends.length - 1)
    const scaleY = (height - paddingY * 2) / max

    const pointsConv = props.trends.map((t, idx) => {
        const x = paddingX + idx * stepX
        const y = height - paddingY - t.conversations * scaleY
        return { x, y }
    })

    const pointsMsg = props.trends.map((t, idx) => {
        const x = paddingX + idx * stepX
        const y = height - paddingY - t.messages * scaleY
        return { x, y }
    })

    const convLine = pointsConv.map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
    const convArea = `${convLine} L ${pointsConv[pointsConv.length - 1].x} ${height - paddingY} L ${pointsConv[0].x} ${height - paddingY} Z`

    const msgLine = pointsMsg.map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
    const msgArea = `${msgLine} L ${pointsMsg[pointsMsg.length - 1].x} ${height - paddingY} L ${pointsMsg[0].x} ${height - paddingY} Z`

    const points = props.trends.map((t, idx) => ({
        index: idx,
        date: t.date,
        conversations: t.conversations,
        messages: t.messages,
        cx: pointsConv[idx].x,
        cyConv: pointsConv[idx].y,
        cyMsg: pointsMsg[idx].y,
    }))

    return { convLine, convArea, msgLine, msgArea, points }
})

// Calculate popularity percentages
const totalPopularityMessages = computed(() =>
    props.modelsPopularity.reduce((acc, m) => acc + m.messages_count, 0) || 1
)

const totalPopularityConversations = computed(() =>
    props.modesPopularity.reduce((acc, m) => acc + m.conversations_count, 0) || 1
)

const feedbackStats = computed(() => {
    const total = props.stats.likes_count + props.stats.dislikes_count
    if (total === 0) return { percentLikes: 0, percentDislikes: 0 }
    return {
        percentLikes: Math.round((props.stats.likes_count / total) * 100),
        percentDislikes: Math.round((props.stats.dislikes_count / total) * 100)
    }
})

const formatNumber = (num: number) => {
    return new Intl.NumberFormat().format(num)
}

const formatTokens = (tokens: number) => {
    if (tokens >= 1_000_000) {
        return (tokens / 1_000_000).toFixed(2) + 'M'
    }
    if (tokens >= 1_000) {
        return (tokens / 1_000).toFixed(1) + 'k'
    }
    return tokens.toString()
}

const formatCredits = (credits: number) => {
    return credits.toFixed(2)
}

const formatDate = (dateStr: string) => {
    try {
        const d = new Date(dateStr)
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
    } catch {
        return dateStr
    }
}
</script>

<template>
    <Head :title="t('Chatbot Analytics')" />

    <div class="admin-layout w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('Chatbot Analytics') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor conversation engagement, model usage patterns, credit consumption, and customer satisfaction.') }}
                </p>
            </div>

            <Link
                :href="route('admin.addons.settings', { slug: 'ai-chatbot' })"
                class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:!border-primary-900/30 dark:hover:!bg-primary-900/30 dark:hover:!text-primary-300"
            >
                <i class="ti ti-settings text-base"></i>
                {{ t('Settings') }}
            </Link>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                :title="t('Total Conversations')"
                :value="formatNumber(stats.total_conversations.value)"
                :comparison="stats.total_conversations.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_conversations.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-messages text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Total Messages')"
                :value="formatNumber(stats.total_messages.value)"
                :comparison="stats.total_messages.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_messages.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-message text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Total Tokens')"
                :value="formatTokens(stats.total_tokens.value)"
                :comparison="stats.total_tokens.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_tokens.comparison.type"
                color="accent"
            >
                <template #icon>
                    <i class="ti ti-database text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Credits Charged')"
                :value="formatCredits(stats.total_credits.value)"
                :comparison="stats.total_credits.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="stats.total_credits.comparison.type"
                color="pink"
            >
                <template #icon>
                    <i class="ti ti-coin text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <!-- Engagement Chart -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Engagement Overview') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Conversations and messages activity over the last 30 days.') }}</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold">
                    <div class="flex items-center gap-1.5 text-primary-600">
                        <span class="h-3 w-3 rounded-full bg-primary-600"></span>
                        <span>{{ t('Conversations') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-accent-600">
                        <span class="h-3 w-3 rounded-full bg-accent-600"></span>
                        <span>{{ t('Messages') }}</span>
                    </div>
                </div>
            </div>

            <!-- SVG Line Chart with Responsive Container -->
            <div class="relative overflow-hidden w-full h-[280px]">
                <svg
                    viewBox="0 0 1000 260"
                    preserveAspectRatio="none"
                    class="w-full h-full text-gray-200 dark:text-surface-800"
                >
                    <!-- Grid Lines -->
                    <line x1="40" y1="30" x2="960" y2="30" stroke="currentColor" stroke-dasharray="4" stroke-width="1" />
                    <line x1="40" y1="115" x2="960" y2="115" stroke="currentColor" stroke-dasharray="4" stroke-width="1" />
                    <line x1="40" y1="200" x2="960" y2="200" stroke="currentColor" stroke-dasharray="4" stroke-width="1" />
                    <line x1="40" y1="230" x2="960" y2="230" stroke="currentColor" stroke-width="1" />

                    <!-- Gradients -->
                    <defs>
                        <linearGradient id="gradientConv" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--admin-primary, #4f46e5)" stop-opacity="0.2" />
                            <stop offset="100%" stop-color="var(--admin-primary, #4f46e5)" stop-opacity="0.0" />
                        </linearGradient>
                        <linearGradient id="gradientMsg" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--admin-accent, #db2777)" stop-opacity="0.2" />
                            <stop offset="100%" stop-color="var(--admin-accent, #db2777)" stop-opacity="0.0" />
                        </linearGradient>
                    </defs>

                    <!-- Areas -->
                    <path v-if="chartData.convArea" :d="chartData.convArea" fill="url(#gradientConv)" />
                    <path v-if="chartData.msgArea" :d="chartData.msgArea" fill="url(#gradientMsg)" />

                    <!-- Lines -->
                    <path
                        v-if="chartData.convLine"
                        :d="chartData.convLine"
                        fill="none"
                        stroke="var(--admin-primary, #4f46e5)"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    />
                    <path
                        v-if="chartData.msgLine"
                        :d="chartData.msgLine"
                        fill="none"
                        stroke="var(--admin-accent, #db2777)"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    />

                    <!-- Interactive Bars/Points (invisible targets for easy hover) -->
                    <g v-for="pt in chartData.points" :key="pt.index">
                        <!-- Divider Line on Hover -->
                        <line
                            v-if="hoveredDayIndex === pt.index"
                            :x1="pt.cx"
                            y1="30"
                            :x2="pt.cx"
                            y2="230"
                            stroke="rgba(156, 163, 175, 0.4)"
                            stroke-width="1.5"
                        />

                        <!-- Conversation dot -->
                        <circle
                            v-if="hoveredDayIndex === pt.index"
                            :cx="pt.cx"
                            :cy="pt.cyConv"
                            r="5"
                            fill="var(--admin-primary, #4f46e5)"
                            stroke="white"
                            stroke-width="1.5"
                        />
                        <!-- Message dot -->
                        <circle
                            v-if="hoveredDayIndex === pt.index"
                            :cx="pt.cx"
                            :cy="pt.cyMsg"
                            r="5"
                            fill="var(--admin-accent, #db2777)"
                            stroke="white"
                            stroke-width="1.5"
                        />

                        <!-- Trigger bar -->
                        <rect
                            :x="pt.cx - 15"
                            y="10"
                            width="30"
                            height="230"
                            fill="transparent"
                            class="cursor-pointer"
                            @mouseenter="hoveredDayIndex = pt.index"
                            @mouseleave="hoveredDayIndex = null"
                        />
                    </g>
                </svg>

                <!-- Tooltip Overlay -->
                <div
                    v-if="hoveredDayIndex !== null && chartData.points[hoveredDayIndex]"
                    class="absolute z-10 p-3 bg-white dark:bg-[#1a1a1a] border border-gray-200 dark:border-surface-800 rounded-xl shadow-lg pointer-events-none transition-all duration-75"
                    :style="{
                        left: `${(chartData.points[hoveredDayIndex].cx / 1000) * 100}%`,
                        top: '15px',
                        transform: 'translateX(-50%)'
                    }"
                >
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        {{ chartData.points[hoveredDayIndex].date }}
                    </div>
                    <div class="mt-1.5 flex flex-col gap-1 text-sm font-bold text-gray-800 dark:text-gray-200">
                        <div class="flex items-center gap-3 justify-between">
                            <span class="text-primary-600 font-normal">{{ t('Conversations') }}:</span>
                            <span>{{ chartData.points[hoveredDayIndex].conversations }}</span>
                        </div>
                        <div class="flex items-center gap-3 justify-between">
                            <span class="text-accent-600 font-normal">{{ t('Messages') }}:</span>
                            <span>{{ chartData.points[hoveredDayIndex].messages }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- X-axis Labels -->
            <div class="flex justify-between px-8 text-xs font-semibold text-gray-400 dark:text-gray-500 mt-2 select-none">
                <span>{{ trends[0]?.date }}</span>
                <span>{{ trends[9]?.date }}</span>
                <span>{{ trends[19]?.date }}</span>
                <span>{{ trends[29]?.date }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Popular Models -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900 flex flex-col">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Model Popularity') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Most utilized AI models in the chatbot by message count.') }}</p>
                </div>

                <div class="flex-1 space-y-4">
                    <div v-if="!modelsPopularity.length" class="py-8 text-center text-sm text-gray-400">
                        {{ t('No model usage data available.') }}
                    </div>
                    <div
                        v-for="(item, idx) in modelsPopularity"
                        :key="item.model"
                        class="space-y-1.5"
                    >
                        <div class="flex justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="h-5 w-5 rounded bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-400 flex items-center justify-center text-xs font-semibold">
                                    {{ idx + 1 }}
                                </span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ item.name }}</span>
                                <span class="text-xs text-gray-400">({{ item.provider }})</span>
                            </div>
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(item.messages_count) }} {{ t('messages') }}
                            </span>
                        </div>
                        <div class="h-2 w-full bg-gray-100 rounded-full dark:bg-surface-800 overflow-hidden">
                            <div
                                class="h-full bg-primary-600 rounded-full transition-all duration-500"
                                :style="{ width: `${(item.messages_count / totalPopularityMessages) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Modes / Personas -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900 flex flex-col">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Mode Popularity') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Most popular chatbot modes and custom personas by conversations count.') }}</p>
                </div>

                <div class="flex-1 space-y-4">
                    <div v-if="!modesPopularity.length" class="py-8 text-center text-sm text-gray-400">
                        {{ t('No chatbot mode data available.') }}
                    </div>
                    <div
                        v-for="(item, idx) in modesPopularity"
                        :key="item.slug"
                        class="space-y-1.5"
                    >
                        <div class="flex justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="h-5 w-5 rounded bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-400 flex items-center justify-center text-xs font-semibold">
                                    {{ idx + 1 }}
                                </span>
                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ item.name }}</span>
                            </div>
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ formatNumber(item.conversations_count) }} {{ t('chats') }}
                            </span>
                        </div>
                        <div class="h-2 w-full bg-gray-100 rounded-full dark:bg-surface-800 overflow-hidden">
                            <div
                                class="h-full bg-secondary-600 rounded-full transition-all duration-500"
                                :style="{ width: `${(item.conversations_count / totalPopularityConversations) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback & Satisfaction -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Satisfaction Score Card -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Customer Satisfaction') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Ratio of thumbs up and thumbs down ratings on assistant responses.') }}</p>
                </div>

                <div class="py-6 flex flex-col items-center justify-center">
                    <div class="text-5xl font-extrabold text-primary-600">
                        {{ feedbackStats.percentLikes }}%
                    </div>
                    <div class="mt-2 text-sm font-semibold text-gray-500 dark:text-gray-400">
                        {{ t('Response Approval Rate') }}
                    </div>

                    <!-- Satisfaction Progress Bar -->
                    <div class="mt-6 flex h-4 w-full bg-gray-100 rounded-full dark:bg-surface-800 overflow-hidden text-center text-[10px] font-bold text-white select-none">
                        <div
                            v-if="stats.likes_count"
                            class="bg-emerald-500 flex items-center justify-center"
                            :style="{ width: `${feedbackStats.percentLikes}%` }"
                        >
                            {{ feedbackStats.percentLikes }}%
                        </div>
                        <div
                            v-if="stats.dislikes_count"
                            class="bg-rose-500 flex items-center justify-center"
                            :style="{ width: `${feedbackStats.percentDislikes}%` }"
                        >
                            {{ feedbackStats.percentDislikes }}%
                        </div>
                    </div>

                    <!-- Labels -->
                    <div class="mt-4 flex w-full justify-between text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1.5 text-emerald-600">
                            <i class="ti ti-thumb-up"></i>
                            {{ formatNumber(stats.likes_count) }} {{ t('Likes') }}
                        </span>
                        <span class="flex items-center gap-1.5 text-rose-600">
                            <i class="ti ti-thumb-down"></i>
                            {{ formatNumber(stats.dislikes_count) }} {{ t('Dislikes') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Recent Feedback Comments -->
            <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-gray-900 flex flex-col">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Recent Feedback comments') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Actual feedback and comments submitted by users.') }}</p>
                </div>

                <div class="mt-5 flex-1 divide-y divide-gray-100 dark:divide-surface-800 overflow-y-auto max-h-[320px] pr-2">
                    <div v-if="!recentFeedbacks.length" class="py-12 text-center text-sm text-gray-400">
                        {{ t('No feedback logs submitted yet.') }}
                    </div>
                    <div
                        v-for="feed in recentFeedbacks"
                        :key="feed.id"
                        class="py-4 first:pt-0 last:pb-0"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-full bg-gray-100 dark:bg-surface-800 flex items-center justify-center font-bold text-gray-600 dark:text-gray-400 text-sm">
                                    {{ feed.user?.name?.charAt(0) || 'U' }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ feed.user?.name || t('Guest User') }}</div>
                                    <div class="text-xs text-gray-400">{{ feed.user?.email || t('No email') }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="feed.rating === 1 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400'"
                                >
                                    <i :class="feed.rating === 1 ? 'ti ti-thumb-up' : 'ti ti-thumb-down'"></i>
                                    {{ feed.rating === 1 ? t('Liked') : t('Disliked') }}
                                </span>
                                <span class="text-xs text-gray-400">{{ formatDate(feed.created_at) }}</span>
                            </div>
                        </div>

                        <!-- Feedback Comment -->
                        <p v-if="feed.comment" class="mt-2.5 text-sm text-gray-600 dark:text-gray-300 italic bg-gray-50/50 dark:bg-surface-900/30 p-3 rounded-lg border border-gray-100 dark:border-surface-800/40">
                            "{{ feed.comment }}"
                        </p>

                        <!-- Attached Conversation reference -->
                        <div v-if="feed.conversation" class="mt-2 flex justify-end">
                            <span class="text-xs text-gray-400">
                                {{ t('On chat:') }}
                                <span class="font-semibold text-primary-600 dark:text-primary-400 ml-1">
                                    {{ feed.conversation.title || t('Untitled Chat') }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
