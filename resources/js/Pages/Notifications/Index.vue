<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

interface NotificationItem {
    id: string
    title: string
    message: string
    level: 'info' | 'success' | 'warning' | 'error'
    action_url: string | null
    created_at: string | null
    is_read: boolean
}

interface NotificationGroup {
    key: string
    icon: string
    label: string
    description: string
    in_app: boolean
    email: boolean
}

const props = defineProps<{
    notifications: {
        data: NotificationItem[]
        links: Array<{ url: string | null, label: string, active: boolean }>
    }
    filters: { status: string | null }
    notificationGroups?: NotificationGroup[]
    notificationPreferences?: {
        in_app: Record<string, boolean>
        email: Record<string, boolean>
    }
}>()

const { t } = useTranslate()

const setStatus = (status: string | null) => {
    router.get(route('user.dashboard.notifications.index'), status ? { status } : {}, {
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

// Preferences modal
const showPreferencesModal = ref(false)
const localGroups = ref<NotificationGroup[]>(props.notificationGroups ?? [])

const preferencesForm = useForm({
    in_app: props.notificationPreferences?.in_app ?? {},
    email: props.notificationPreferences?.email ?? {},
})

const openPreferencesModal = () => {
    // Fetch fresh preferences data
    router.get(route('user.dashboard.notifications.index'), {}, {
        preserveState: true,
        preserveScroll: true,
        only: ['notificationGroups', 'notificationPreferences'],
        onSuccess: () => {
            localGroups.value = props.notificationGroups ?? []
            preferencesForm.in_app = props.notificationPreferences?.in_app ?? {}
            preferencesForm.email = props.notificationPreferences?.email ?? {}
            showPreferencesModal.value = true
        },
    })
}

const savePreferences = () => {
    preferencesForm.put(route('user.dashboard.notifications.preferences.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showPreferencesModal.value = false
        },
    })
}

const togglePreference = (group: NotificationGroup, channel: 'in_app' | 'email') => {
    group[channel] = !group[channel]
    preferencesForm[channel][group.key] = group[channel]
}
</script>

<template>
    <Head :title="t('Notifications')" />

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Notifications') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review account updates, payments, documents, and admin messages.') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                        @click="openPreferencesModal"
                    >
                        <i class="ti ti-settings text-lg"></i>
                        {{ t('Preferences') }}
                    </button>
                    <div class="flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-surface-700 dark:bg-surface-800">
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold" :class="!props.filters.status ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900' : 'text-gray-500'" @click="setStatus(null)">{{ t('All') }}</button>
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold" :class="props.filters.status === 'unread' ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900' : 'text-gray-500'" @click="setStatus('unread')">{{ t('Unread') }}</button>
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-semibold" :class="props.filters.status === 'read' ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900' : 'text-gray-500'" @click="setStatus('read')">{{ t('Read') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div v-for="item in notifications.data" :key="item.id" class="flex gap-4 border-b border-gray-100 p-5 last:border-b-0 dark:border-surface-800">
                <span :class="levelClass(item.level)" class="mt-1 h-3 w-3 shrink-0 rounded-full"></span>
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
                {{ t('No notifications found.') }}
            </div>
        </section>

        <Pagination :links="notifications.links" />
    </div>

    <!-- Preferences Modal -->
    <Teleport to="body">
        <div v-if="showPreferencesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" @click="showPreferencesModal = false"></div>
            <div class="relative w-full max-w-2xl rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-200 p-6 dark:border-surface-700">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Notification Preferences') }}</h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800 dark:hover:text-gray-300"
                        @click="showPreferencesModal = false"
                    >
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>
                <div class="max-h-[60vh] overflow-y-auto p-6">
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Choose which notifications you want to receive via in-app and email.') }}
                    </p>
                    <div class="space-y-4">
                        <div
                            v-for="group in localGroups"
                            :key="group.key"
                            class="flex items-start gap-4 rounded-lg border border-gray-200 p-4 dark:border-surface-700"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                                <i :class="group.icon" class="text-xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ group.label }}</h3>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ group.description }}</p>
                            </div>
                            <div class="flex items-center gap-4 shrink-0">
                                <div class="flex flex-col items-center gap-1.5">
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            :checked="group.in_app"
                                            class="peer sr-only"
                                            @change="togglePreference(group, 'in_app')"
                                        />
                                        <div class="h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-primary-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2 dark:bg-surface-700 dark:peer-checked:bg-primary-600 dark:peer-focus:ring-offset-surface-900 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:after:border-surface-600 dark:after:bg-surface-400 dark:peer-checked:after:bg-white"></div>
                                    </label>
                                    <span class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('In-App') }}</span>
                                </div>
                                <div class="flex flex-col items-center gap-1.5">
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            :checked="group.email"
                                            class="peer sr-only"
                                            @change="togglePreference(group, 'email')"
                                        />
                                        <div class="h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-primary-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2 dark:bg-surface-700 dark:peer-checked:bg-primary-600 dark:peer-focus:ring-offset-surface-900 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:after:border-surface-600 dark:after:bg-surface-400 dark:peer-checked:after:bg-white"></div>
                                    </label>
                                    <span class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Email') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                        <p class="text-xs text-blue-800 dark:text-blue-300">
                            <i class="ti ti-info-circle mr-1"></i>
                            {{ t('Security-critical emails (password reset, 2FA codes) are always sent to keep your account safe.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-200 p-6 dark:border-surface-700">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                        @click="showPreferencesModal = false"
                    >
                        {{ t('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-primary-700 disabled:opacity-50"
                        :disabled="preferencesForm.processing"
                        @click="savePreferences"
                    >
                        <svg v-if="preferencesForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        {{ t('Save Preferences') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
