<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

type RenderTypeRow = {
    type: string
    count: number
}

type ProviderRow = {
    provider: string
    count: number
    avg_polls: number
}

type TopUserRow = {
    user_name: string
    user_email: string
    renders: number
    credits: number
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
    total_renders: StatObject
    processing: StatObject
    completed_today: StatObject
    failed_today: StatObject
    total_storage_gb: StatObject
    by_type: RenderTypeRow[]
    by_provider: ProviderRow[]
    top_users: TopUserRow[]
}>()

const typeLabelMap: Record<string, string> = {
    text_to_video: t('Text to Video'),
    image_to_video: t('Image to Video'),
    avatar_video: t('AI Avatar'),
    slideshow: t('Slideshow'),
}

const providerLabelMap: Record<string, string> = {
    kling: t('Kling AI'),
    runway: t('Runway ML'),
    pika: t('Pika Labs'),
    minimax: t('Minimax'),
    heygen: t('HeyGen'),
    did: t('D-ID'),
    openai: t('OpenAI'),
    elevenlabs: t('ElevenLabs'),
    whisper: t('Whisper'),
}

const typeLabel = (type: string) => typeLabelMap[type] ?? type
const providerLabel = (provider: string) => providerLabelMap[provider] ?? provider

const hasUsers = computed(() => props.top_users.length > 0)
</script>

<template>
    <Head :title="t('Video Creator Overview')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Video Creator') }}</h1>
                    <span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                        {{ t('Admin Overview') }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Monitor render volume, pipeline health, and the most active users from one compact dashboard.') }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <StatsCard
                :title="t('Total Renders')"
                :value="total_renders.value"
                :comparison="total_renders.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="total_renders.comparison.type"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-video text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Processing')"
                :value="processing.value"
                :comparison="processing.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="processing.comparison.type"
                :color="processing.value > 0 ? 'warning' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-loader text-lg animate-spin"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Completed Today')"
                :value="completed_today.value"
                :comparison="completed_today.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="completed_today.comparison.type"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-checkbox text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Failed Today')"
                :value="failed_today.value"
                :comparison="failed_today.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="failed_today.comparison.type"
                :color="failed_today.value > 0 ? 'danger' : 'primary'"
            >
                <template #icon>
                    <i class="ti ti-alert-triangle text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Storage')"
                :value="`${total_storage_gb.value} GB`"
                :comparison="total_storage_gb.comparison.label"
                :comparison-detail="t('vs last week')"
                :comparison-type="total_storage_gb.comparison.type"
                color="accent"
            >
                <template #icon>
                    <i class="ti ti-database text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('By Type') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Render count split by workflow.') }}</p>
                    </div>
                </div>

                <div v-if="by_type.length" class="space-y-3">
                    <div v-for="row in by_type" :key="row.type" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3 dark:border-surface-800 dark:bg-surface-800/60">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ typeLabel(row.type) }}</span>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ row.count }}</span>
                    </div>
                </div>
                <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                    {{ t('No render data yet.') }}
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
                <div class="mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('By Provider') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Average poll activity by provider.') }}</p>
                </div>

                <div v-if="by_provider.length" class="overflow-hidden rounded-xl border border-gray-100 dark:border-surface-800">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                        <thead class="bg-gray-50 dark:bg-surface-800/60">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Provider') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Renders') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Avg Polls') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-surface-800 dark:bg-gray-900">
                            <tr v-for="row in by_provider" :key="row.provider" class="hover:bg-emerald-50/50 dark:hover:bg-surface-800/60">
                                <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ providerLabel(row.provider) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.count }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ Number(row.avg_polls).toFixed(1) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                    {{ t('No provider activity yet.') }}
                </div>
            </section>
        </div>

        <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-gray-900">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Top Users') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Users generating the most video renders and credits.') }}</p>
            </div>

            <div v-if="hasUsers" class="overflow-hidden rounded-xl border border-gray-100 dark:border-surface-800">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                    <thead class="bg-gray-50 dark:bg-surface-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('User') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Renders') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">{{ t('Credits Spent') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-surface-800 dark:bg-gray-900">
                        <tr v-for="user in top_users" :key="user.user_email" class="hover:bg-emerald-50/50 dark:hover:bg-surface-800/60">
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.user_name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ user.user_email }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ user.renders }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ user.credits }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-surface-800 dark:text-gray-400">
                {{ t('No user activity yet.') }}
            </div>
        </section>
    </div>
</template>
