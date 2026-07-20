<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import StatsCard from '@/Components/UI/StatsCard.vue'
import { asZonedDisplayDate, siteTimeZone } from '@/lib/timezone'

defineOptions({ layout: AdminLayout })

interface FeedbackRow {
    id: number
    rating: number
    comment: string | null
    context_page: string | null
    created_at: string | null
    user: { name: string; email: string } | null
    message: string | null
}

interface Paginated<T> {
    data: T[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    total: number
}

const props = defineProps<{
    feedback: Paginated<FeedbackRow>
    stats: { total: number; positive: number; negative: number }
}>()

const { t } = useTranslate()

const satisfaction = computed(() => {
    const rated = props.stats.positive + props.stats.negative
    if (rated === 0) return null
    return Math.round((props.stats.positive / rated) * 100)
})

const satisfactionColor = computed(() => {
    if (satisfaction.value === null) return 'neutral'
    if (satisfaction.value >= 80) return 'up'
    if (satisfaction.value >= 50) return 'warning'
    return 'down'
})

function formatFeedbackDate(dateStr: string | null): string {
    if (!dateStr) return '—'
    const cleanDateStr = dateStr.includes(' ') && !dateStr.includes('T') ? dateStr.replace(' ', 'T') + 'Z' : dateStr
    const d = asZonedDisplayDate(new Date(cleanDateStr), siteTimeZone())
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    const day = d.getDate()
    const month = months[d.getMonth()]
    const year = d.getFullYear()

    const rawHours = d.getHours()
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const ampm = rawHours < 12 ? 'am' : 'pm'
    const hours = rawHours % 12 || 12

    return `${day} ${month} ${year} ${hours}:${minutes}${ampm}`
}

function go(url: string | null) {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true })
}
</script>

<template>
    <Head :title="t('Assistant Feedback')" />
    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('Assistant Feedback') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('What users thought of the assistant’s answers, joined to the message they rated.') }}
                </p>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <StatsCard
                :title="t('Total Feedback')"
                :value="stats.total"
                color="primary"
            >
                <template #icon>
                    <i class="ti ti-message-star text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Positive Ratings')"
                :value="stats.positive"
                :comparison="satisfaction !== null ? satisfaction + '%' : undefined"
                :comparison-detail="satisfaction !== null ? t('satisfaction rate') : undefined"
                :comparison-type="satisfactionColor"
                color="success"
            >
                <template #icon>
                    <i class="ti ti-thumb-up text-lg"></i>
                </template>
            </StatsCard>

            <StatsCard
                :title="t('Negative Ratings')"
                :value="stats.negative"
                color="danger"
            >
                <template #icon>
                    <i class="ti ti-thumb-down text-lg"></i>
                </template>
            </StatsCard>
        </div>

        <!-- Empty state -->
        <div
            v-if="!feedback.data.length"
            class="rounded-2xl border border-dashed border-gray-200 py-16 text-center dark:border-gray-800"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('No feedback yet.') }}</p>
        </div>

        <!-- Feedback list -->
        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800/50 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-3 font-semibold">{{ t('Rating') }}</th>
                            <th class="px-6 py-3 font-semibold">{{ t('Message') }}</th>
                            <th class="px-6 py-3 font-semibold">{{ t('Comment') }}</th>
                            <th class="px-6 py-3 font-semibold">{{ t('User') }}</th>
                            <th class="px-6 py-3 font-semibold">{{ t('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in feedback.data" :key="row.id" class="align-top text-gray-700 dark:text-gray-300">
                            <td class="px-6 py-4">
                                <span v-if="row.rating === 1" class="text-emerald-600 dark:text-emerald-400" :title="t('Positive')">
                                    <i class="ti ti-thumb-up text-lg"></i>
                                </span>
                                <span v-else class="text-rose-600 dark:text-rose-400" :title="t('Negative')">
                                    <i class="ti ti-thumb-down text-lg"></i>
                                </span>
                            </td>
                            <td class="max-w-md px-6 py-4">
                                <span v-if="row.message" class="text-gray-900 dark:text-gray-100 font-medium">{{ row.message }}</span>
                                <span v-else class="italic text-gray-400">{{ t('Message not available') }}</span>
                                <div v-if="row.context_page" class="mt-1 text-xs text-gray-400">{{ row.context_page }}</div>
                            </td>
                            <td class="max-w-xs px-6 py-4 text-gray-600 dark:text-gray-400">
                                {{ row.comment || '—' }}
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                <template v-if="row.user">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ row.user.name }}</div>
                                    <div class="text-xs text-gray-400">{{ row.user.email }}</div>
                                </template>
                                <span v-else class="text-gray-400">{{ t('Guest') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-gray-500 dark:text-gray-400">{{ formatFeedbackDate(row.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <nav v-if="feedback.links.length > 3" class="flex flex-wrap gap-1">
            <button
                v-for="link in feedback.links"
                :key="link.label"
                :disabled="!link.url"
                class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                :class="[
                    link.active
                        ? 'bg-primary-600 text-white'
                        : 'border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
                ]"
                @click="go(link.url)"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
