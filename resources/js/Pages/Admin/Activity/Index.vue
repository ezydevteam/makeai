<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface ActivityItem {
    type: string
    icon: string
    title: string
    detail: string
    time: string
}

interface ActivityPagination {
    data: ActivityItem[]
    links: Array<{ url: string | null; label: string; active: boolean }>
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
}

const props = defineProps<{
    activity: ActivityPagination
    rangeLabel: string
}>()

const { t } = useTranslate()

const activityIcons: Record<string, string> = {
    user: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    dollar: 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    spark: 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
    ticket: 'M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z',
}

const activityTypeLabels: Record<string, string> = {
    user_registered: t('User Registered'),
    payment: t('Payment'),
    subscription: t('Pro Subscription'),
    ai_request: t('AI Request'),
    referral: t('Referral'),
}

function timeAgo(time: string): string {
    const diff = Date.now() - new Date(time).getTime()
    const m = Math.floor(diff / 60000)
    const h = Math.floor(m / 60)
    const d = Math.floor(h / 24)
    if (m < 60) return `${m}m ago`
    if (h < 24) return `${h}h ago`
    return `${d}d ago`
}

const rangeSummary = computed(() => props.rangeLabel)
</script>

<template>
    <Head :title="t('Recent Activity')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Recent Activity') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Activity from the last :range.', { range: rangeSummary }) }}
                </p>
            </div>
            <Link :href="route('admin.dashboard')" class="inline-flex items-center gap-2 self-start rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                {{ t('Back to Dashboard') }}
            </Link>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 bg-gray-50/80 px-5 py-3 dark:border-surface-800 dark:bg-surface-950/40">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Showing :from-:to of :count items', { from: String(activity.from ?? 0), to: String(activity.to ?? 0), count: String(activity.total ?? 0) }) }}
                </p>
            </div>

            <div v-if="activity.data.length" class="divide-y divide-gray-100 dark:divide-surface-800">
                <div v-for="(item, index) in activity.data" :key="`${item.type}-${item.time}-${index}`" class="flex items-center gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-surface-800/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="{
                        'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400': item.icon === 'user',
                        'bg-success-100 text-success-600 dark:bg-success-900/30 dark:text-success-400': item.icon === 'dollar',
                        'bg-accent-100 text-accent-600 dark:bg-accent-900/30 dark:text-accent-400': item.icon === 'spark',
                        'bg-warning-100 text-warning-600 dark:bg-warning-900/30 dark:text-warning-400': item.icon === 'ticket',
                    }">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path :d="activityIcons[item.icon] || activityIcons.user" />
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.title }}</p>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium" :class="{
                                'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400': item.type === 'user_registered',
                                'bg-success-50 text-success-600 dark:bg-success-900/30 dark:text-success-400': item.type === 'payment',
                                'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400': item.type === 'subscription',
                                'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400': item.type === 'ai_request',
                                'bg-pink-50 text-pink-600 dark:bg-pink-900/30 dark:text-pink-400': item.type === 'referral',
                            }">
                                {{ activityTypeLabels[item.type] || item.type }}
                            </span>
                        </div>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ item.detail }}</p>
                    </div>

                    <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ timeAgo(item.time) }}</span>
                </div>
            </div>

            <div v-else class="px-5 py-16 text-center text-gray-400 dark:text-gray-500">
                <svg class="mx-auto mb-3 h-10 w-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm">{{ t('No activity found') }}</p>
            </div>

            <div class="border-t border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-surface-800 dark:bg-surface-950/40">
                <Pagination
                    :links="activity.links"
                    :from="activity.from"
                    :to="activity.to"
                    :total="activity.total"
                    :current-page="activity.current_page"
                    :last-page="activity.last_page"
                />
            </div>
        </div>
    </div>
</template>
