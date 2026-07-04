<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import StatsCard from '@/Components/UI/StatsCard.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface TopQueryItem {
  query: string
  count: number
}

interface TopArticleItem {
  id: number
  title: string
  views: number
  helpful_count: number
  not_helpful_count: number
}

interface EmbedSummaryItem {
  key: string
  label: string
  count: number
}

const { t } = useTranslate()

interface ComparisonData {
  label: string
  type: 'up' | 'down' | 'neutral'
}

interface StatObject {
  value: number
  comparison: ComparisonData
}

const props = defineProps<{
    searches_today: StatObject
    searches_7d: StatObject
    answer_rate: StatObject
    published_count: StatObject
    unanswered: string[]
    top_queries: TopQueryItem[]
    top_articles: TopArticleItem[]
    embed_summary: Record<string, number>
}>()

const embedSummaryItems = computed<EmbedSummaryItem[]>(() => {
    const labels: Record<string, string> = {
        pending: t('Pending'),
        processing: t('Processing'),
        completed: t('Completed'),
        failed: t('Failed'),
    }

    return Object.entries(props.embed_summary)
        .map(([key, count]) => ({
            key,
            label: labels[key] ?? key,
            count,
        }))
        .sort((a, b) => a.label.localeCompare(b.label))
})

const helpfulRate = (article: TopArticleItem) => {
    const total = article.helpful_count + article.not_helpful_count
    if (total === 0) {
        return null
    }

    return Math.round((article.helpful_count / total) * 100)
}
</script>

<template>
    <AdminLayout :title="t('KB Analytics')">
        <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ t('Knowledge Base Analytics') }}
                    </h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Track how visitors search, which articles help most, and what content still needs better answers.') }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatsCard
                    :title="t('Searches Today')"
                    :value="props.searches_today.value"
                    :comparison="props.searches_today.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="props.searches_today.comparison.type"
                    color="primary"
                >
                    <template #icon>
                        <i class="ti ti-search text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Searches in 7 Days')"
                    :value="props.searches_7d.value"
                    :comparison="props.searches_7d.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="props.searches_7d.comparison.type"
                    color="success"
                >
                    <template #icon>
                        <i class="ti ti-history text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Answer Rate')"
                    :value="`${props.answer_rate.value}%`"
                    :comparison="props.answer_rate.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="props.answer_rate.comparison.type"
                    color="warning"
                >
                    <template #icon>
                        <i class="ti ti-circle-check text-lg"></i>
                    </template>
                </StatsCard>

                <StatsCard
                    :title="t('Published Articles')"
                    :value="props.published_count.value"
                    :comparison="props.published_count.comparison.label"
                    :comparison-detail="t('vs last week')"
                    :comparison-type="props.published_count.comparison.type"
                    color="accent"
                >
                    <template #icon>
                        <i class="ti ti-article text-lg"></i>
                    </template>
                </StatsCard>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ t('Unanswered Queries') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Recent search terms that did not return a useful answer.') }}
                            </p>
                        </div>

                        <div v-if="props.unanswered.length" class="divide-y divide-gray-100 dark:divide-surface-800">
                            <div
                                v-for="(query, index) in props.unanswered"
                                :key="`${query}-${index}`"
                                class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ query }}
                            </div>
                        </div>

                        <div v-else class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ t('No unanswered queries yet.') }}
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ t('Top Search Queries') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Queries with the highest request volume.') }}
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                                <thead class="bg-gray-50/80 dark:bg-surface-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                            {{ t('Query') }}
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                            {{ t('Count') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                                    <tr v-if="props.top_queries.length === 0">
                                        <td colspan="2" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ t('No search data yet.') }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="item in props.top_queries"
                                        :key="item.query"
                                        class="transition hover:bg-primary-50/60 dark:hover:bg-surface-800/70"
                                    >
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ item.query }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ item.count }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="space-y-6">
                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ t('Top Articles') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('The most viewed articles and their feedback rate.') }}
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-surface-800">
                                <thead class="bg-gray-50/80 dark:bg-surface-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                            {{ t('Title') }}
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                            {{ t('Views') }}
                                        </th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-gray-500">
                                            {{ t('Helpful') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                                    <tr v-if="props.top_articles.length === 0">
                                        <td colspan="3" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ t('No articles published yet.') }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="article in props.top_articles"
                                        :key="article.id"
                                        class="transition hover:bg-primary-50/60 dark:hover:bg-surface-800/70"
                                    >
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {{ article.title }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ article.views }}
                                        </td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                            <span
                                                v-if="helpfulRate(article) !== null"
                                                class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                            >
                                                {{ helpfulRate(article) }}%
                                            </span>
                                            <span v-else class="text-gray-400">
                                                {{ t('No votes') }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ t('Embed Status') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ t('Current article embedding progress by status.') }}
                            </p>
                        </div>

                        <div class="px-5 py-5">
                            <div v-if="embedSummaryItems.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div
                                    v-for="item in embedSummaryItems"
                                    :key="item.key"
                                    class="rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-surface-700 dark:bg-surface-800/60"
                                >
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                        {{ item.label }}
                                    </p>
                                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                                        {{ item.count }}
                                    </p>
                                </div>
                            </div>

                            <div v-else class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ t('No embed status data yet.') }}
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
