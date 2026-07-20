<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

interface NotificationItem {
    id: string
    title: string
    message: string
    level: 'info' | 'success' | 'warning' | 'error'
    action_url: string | null
    is_read: boolean
}

const props = defineProps<{
    notifications: {
        data: NotificationItem[]
        links: Array<{ url: string | null, label: string, active: boolean }>
    }
    filters: { status: string | null }
}>()

const { t } = useTranslate()

const setStatus = (status: string | null) => {
    router.get(route('admin.notifications.index'), status ? { status } : {}, {
        preserveScroll: true,
        preserveState: true,
    })
}

const levelClass = (level: NotificationItem['level']) => ({
    success: 'bg-primary-100 text-primary-700',
    warning: 'bg-amber-100 text-amber-700',
    error: 'bg-red-100 text-red-700',
    info: 'bg-secondary-100 text-secondary-700',
}[level] ?? 'bg-secondary-100 text-secondary-700')
</script>

<template>
    <Head :title="t('Admin Notifications')" />

    <div class="mx-auto max-w-5xl space-y-6 px-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex-1">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Admin Notifications') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review operational alerts, support messages, contact forms, payments, and system warnings.') }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                <div class="flex rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-surface-700 dark:bg-surface-800">
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="!props.filters.status ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900' : 'text-gray-500'" @click="setStatus(null)">{{ t('All') }}</button>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="props.filters.status === 'unread' ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900' : 'text-gray-500'" @click="setStatus('unread')">{{ t('Unread') }}</button>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="props.filters.status === 'read' ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900' : 'text-gray-500'" @click="setStatus('read')">{{ t('Read') }}</button>
                </div>
            </div>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div v-for="item in notifications.data" :key="item.id" class="flex gap-4 border-b border-gray-100 p-5 last:border-b-0 dark:border-surface-800">
                <span :class="levelClass(item.level)" class="mt-1 rounded-full px-2 py-0.5 text-xs font-semibold">{{ t(item.level) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.title }}</h2>
                        <span v-if="!item.is_read" class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700">{{ t('Unread') }}</span>
                    </div>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ item.message }}</p>
                    <Link v-if="item.action_url" :href="item.action_url" class="mt-2 inline-flex text-sm font-semibold text-primary-600 hover:text-primary-500">
                        {{ t('Open') }}
                    </Link>
                </div>
            </div>
            <div v-if="notifications.data.length === 0" class="px-6 py-16 text-center text-sm text-gray-500">
                <i class="ti ti-bell-off text-4xl block mb-3"></i>
                {{ t('No notifications found.') }}
            </div>
        </section>

        <Pagination :links="notifications.links" />
    </div>
</template>
