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
    user_email?: string | null
    user_ulid?: string | null
    detail: string
    ip_address?: string | null
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
    const minutes = Math.floor(diff / 60000)
    const hours = Math.floor(minutes / 60)
    const days = Math.floor(hours / 24)

    if (minutes < 60) return t(':count m ago', { count: String(minutes) })
    if (hours < 24) return t(':count h ago', { count: String(hours) })

    return t(':count d ago', { count: String(days) })
}

function formatDateTime(time: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(time))
}

const rangeSummary = computed(() => props.rangeLabel)
</script>

<template>
    <Head :title="t('User Activity Logs')" />

        <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('User Activity Logs') }}</h1>
                <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Review recent user-facing platform activity across registrations, payments, subscriptions, AI usage, and referrals.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="route('admin.system.index')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    {{ t('Back') }}
                </Link>

                <div class="inline-flex gap-1 rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <Link
                        :href="route('admin.activity.admin-logs.index')"
                        class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/60"
                    >
                        {{ t('Admin') }}
                    </Link>
                    <Link
                        :href="route('admin.activity.user-logs.index')"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-50 px-4 py-2 text-sm font-medium text-primary-700 transition-colors dark:bg-primary-900/30 dark:text-primary-300"
                    >
                        {{ t('User') }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Showing :from-:to of :count items from :range.', {
                        from: String(activity.from ?? 0),
                        to: String(activity.to ?? 0),
                        count: String(activity.total ?? 0),
                        range: rangeSummary
                    }) }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-700 dark:border-gray-800 dark:bg-gray-700/60 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">{{ t('User') }}</th>
                            <th class="px-6 py-3">{{ t('Activity') }}</th>
                            <th class="px-6 py-3">{{ t('IP Address') }}</th>
                            <th class="px-6 py-3">{{ t('Details') }}</th>
                            <th class="px-6 py-3">{{ t('Time') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="activity.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                {{ t('No user activity found.') }}
                            </td>
                        </tr>

                        <tr
                            v-for="(item, index) in activity.data"
                            :key="`${item.type}-${item.time}-${index}`"
                            class="border-b border-gray-100 bg-white transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700/40"
                        >
                            <td class="px-6 py-4">
                                <div class="flex min-w-0 items-center gap-3">
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

                                    <div class="min-w-0">
                                        <Link
                                            v-if="item.user_ulid"
                                            :href="route('admin.users.show', item.user_ulid)"
                                            class="truncate font-medium text-primary-700 transition-colors hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200"
                                        >
                                            {{ item.title }}
                                        </Link>
                                        <p v-else class="truncate font-medium text-gray-900 dark:text-white">{{ item.title }}</p>
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ item.user_email || '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="{
                                    'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400': item.type === 'user_registered',
                                    'bg-success-50 text-success-600 dark:bg-success-900/30 dark:text-success-400': item.type === 'payment',
                                    'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400': item.type === 'subscription',
                                    'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400': item.type === 'ai_request',
                                    'bg-pink-50 text-pink-600 dark:bg-pink-900/30 dark:text-pink-400': item.type === 'referral',
                                }">
                                    {{ activityTypeLabels[item.type] || item.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm text-gray-600 dark:text-gray-300">{{ item.ip_address || '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div class="max-w-md truncate" :title="item.detail">
                                    {{ item.detail }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <div>{{ timeAgo(item.time) }}</div>
                                <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ formatDateTime(item.time) }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                <Pagination
                    :links="activity.links"
                    :from="activity.from"
                    :to="activity.to"
                    :total="activity.total"
                    :current-page="activity.current_page"
                    :last-page="activity.last_page"
                />
            </div>
        </section>
    </div>

</template>
