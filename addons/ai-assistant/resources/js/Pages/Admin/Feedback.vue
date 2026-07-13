<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

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

function go(url: string | null) {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true })
}
</script>

<template>
    <div class="space-y-6 p-4 sm:p-6">
        <header>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Assistant Feedback') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ t('What users thought of the assistant’s answers, joined to the message they rated.') }}
            </p>
        </header>

        <!-- Stat tiles -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Total') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total }}</div>
            </div>
            <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Positive') }}</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ stats.positive }}
                    <span v-if="satisfaction !== null" class="text-sm font-normal text-gray-400">· {{ satisfaction }}%</span>
                </div>
            </div>
            <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Negative') }}</div>
                <div class="mt-1 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ stats.negative }}</div>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="!feedback.data.length"
            class="rounded-xl border border-dashed border-gray-200 py-16 text-center dark:border-gray-800"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('No feedback yet.') }}</p>
        </div>

        <!-- Feedback list -->
        <div v-else class="overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">{{ t('Rating') }}</th>
                            <th class="px-4 py-3">{{ t('Message') }}</th>
                            <th class="px-4 py-3">{{ t('Comment') }}</th>
                            <th class="px-4 py-3">{{ t('User') }}</th>
                            <th class="px-4 py-3">{{ t('When') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="row in feedback.data" :key="row.id" class="align-top">
                            <td class="px-4 py-3">
                                <span v-if="row.rating === 1" class="text-emerald-600 dark:text-emerald-400" :title="t('Positive')">
                                    <i class="ti ti-thumb-up"></i>
                                </span>
                                <span v-else class="text-rose-600 dark:text-rose-400" :title="t('Negative')">
                                    <i class="ti ti-thumb-down"></i>
                                </span>
                            </td>
                            <td class="max-w-md px-4 py-3 text-gray-700 dark:text-gray-300">
                                <span v-if="row.message">{{ row.message }}</span>
                                <span v-else class="italic text-gray-400">{{ t('Message not available') }}</span>
                                <div v-if="row.context_page" class="mt-1 text-xs text-gray-400">{{ row.context_page }}</div>
                            </td>
                            <td class="max-w-xs px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ row.comment || '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                <template v-if="row.user">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ row.user.name }}</div>
                                    <div class="text-xs text-gray-400">{{ row.user.email }}</div>
                                </template>
                                <span v-else class="text-gray-400">{{ t('Guest') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">{{ row.created_at }}</td>
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
                class="rounded-md px-3 py-1.5 text-sm transition-colors"
                :class="[
                    link.active
                        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                        : 'text-gray-600 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-400 dark:hover:bg-gray-800',
                ]"
                @click="go(link.url)"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
