<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { resolveNotificationIconClass } from '@/Composables/useNotifications'

defineOptions({ layout: UserDashboardLayout })

interface NotificationItem {
    id: string
    icon: string | null
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
        current_page: number
        last_page: number
        total: number
        from: number | null
        to: number | null
    }
    unreadCount: number
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

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

const markAllAsRead = async () => {
    await fetch(route('user.dashboard.notifications.read-all'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
    })

    router.reload()
}

const levelClass = (level: NotificationItem['level']) => ({
    success: 'bg-primary-100 text-primary-700',
    warning: 'bg-amber-100 text-amber-700',
    error: 'bg-red-100 text-red-700',
    info: 'bg-secondary-100 text-secondary-700 dark:bg-gray-700 dark:text-gray-300',
}[level] ?? 'bg-secondary-100 text-secondary-700 dark:bg-gray-700 dark:text-gray-300')

// Preferences modal
const showPreferencesModal = ref(false)
const localGroups = ref<NotificationGroup[]>(props.notificationGroups ?? [])

const preferencesForm = useForm({
    in_app: props.notificationPreferences?.in_app ?? {},
    email: props.notificationPreferences?.email ?? {},
})

const openPreferencesModal = () => {
    localGroups.value = props.notificationGroups ?? []
    preferencesForm.in_app = props.notificationPreferences?.in_app ?? {}
    preferencesForm.email = props.notificationPreferences?.email ?? {}
    showPreferencesModal.value = true
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
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ t('Notifications') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Review account updates, payments, documents, and admin messages.') }}</p>
            </div>
            <button
                type="button"
                class="shrink-0 inline-flex items-center gap-2 btn-primary"
                @click="openPreferencesModal"
            >
                <i class="ti ti-adjustments-horizontal text-base"></i>
                {{ t('Preferences') }}
            </button>
        </div>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-5 border-b border-gray-100">
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-full border border-green-500 px-5 py-2 text-sm font-semibold text-green-600 transition hover:!bg-green-600 hover:!text-white dark:border-green-900/80 dark:text-green-300"
                    @click="markAllAsRead"
                >
                    <i class="ti ti-checks text-base"></i>
                    {{ t('Mark all as read') }}
                </button>
                <div class="flex flex-wrap items-center justify-center gap-2 rounded-full border border-gray-200 bg-gray-50 p-1.5 shadow-sm dark:border-surface-700 dark:bg-surface-800">
                    <button type="button" class="rounded-full px-3 py-1 text-sm font-semibold transition" :class="!props.filters.status ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" @click="setStatus(null)">{{ t('All') }}</button>
                    <button type="button" class="rounded-full px-3 py-1 text-sm font-semibold transition" :class="props.filters.status === 'unread' ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" @click="setStatus('unread')">{{ t('Unread') }}</button>
                    <button type="button" class="rounded-full px-3 py-1 text-sm font-semibold transition" :class="props.filters.status === 'read' ? 'bg-white text-primary-700 shadow-sm dark:bg-surface-900 dark:text-primary-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" @click="setStatus('read')">{{ t('Read') }}</button>
                </div>
            </div>
            <div v-for="item in notifications.data" :key="item.id" class="flex gap-4 border-b border-gray-100 p-5 last:border-b-0 dark:border-surface-800">
                <div :class="levelClass(item.level)" class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full shadow-sm">
                    <i :class="[resolveNotificationIconClass(item), 'text-lg']"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <!-- The date pill drops onto its own line below sm. `flex-wrap` alone
                         never wrapped it: the title/message block is `flex-1 min-w-0`, so it
                         shrinks toward zero instead of pushing the pill down, leaving the
                         date to eat most of a narrow row. -->
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between sm:gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.title }}</h2>
                                <span v-if="!item.is_read" class="rounded-full bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-700">{{ t('Unread') }}</span>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ item.message }}</p>
                        </div>
                        <div class="shrink-0">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-medium text-gray-500 shadow-sm dark:border-surface-700 dark:bg-surface-900 dark:text-gray-400">
                                <i class="ti ti-calendar-event text-xs"></i>
                                {{ item.created_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(item.created_at)) : t('Unknown date') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="notifications.data.length === 0" class="px-6 py-16 text-center text-sm text-gray-500">
                {{ t('No notifications found.') }}
            </div>
        </section>

        <!-- from/to/total drive the component's "showing X to Y of Z" row; with only :links
             it rendered the page buttons and an empty count line. -->
        <Pagination
            v-if="notifications.last_page > 1"
            :links="notifications.links"
            :from="notifications.from"
            :to="notifications.to"
            :total="notifications.total"
            :current-page="notifications.current_page"
            :last-page="notifications.last_page"
        />
    </div>

    <!-- Preferences Modal -->
    <AppModal
        :open="showPreferencesModal"
        max-width="max-w-2xl"
        :title="t('Notification Preferences')"
        :subtitle="t('Choose which notifications you want to receive via in-app and email.')"
        @close="showPreferencesModal = false"
    >
        <div class="space-y-4">
            <div
                v-for="group in localGroups"
                :key="group.key"
                class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-surface-700 sm:flex-row sm:items-start sm:gap-4"
            >
                <!-- Icon + copy stay together; the switch pair drops below them on narrow
                     screens. Side by side there is no room left for the description: the
                     two labelled switches are shrink-0 and eat ~210px of a ~285px row, so
                     the text was squeezed to a few pixels and ran into them. -->
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
                        <i :class="group.icon" class="text-xl"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ group.label }}</h3>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ group.description }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-6 pl-14 sm:pl-0">
                    <AppSwitch
                        :model-value="group.in_app"
                        :label="t('In-App')"
                        @update:model-value="togglePreference(group, 'in_app')"
                    />
                    <AppSwitch
                        :model-value="group.email"
                        :label="t('Email')"
                        @update:model-value="togglePreference(group, 'email')"
                    />
                </div>
            </div>
        </div>
        <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
            <p class="text-xs text-blue-800 dark:text-blue-300">
                <i class="ti ti-info-circle mr-1"></i>
                {{ t('Security-critical emails (password reset, 2FA codes) are always sent to keep your account safe.') }}
            </p>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-3">
                <button
                    type="button"
                    class="rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800"
                    @click="showPreferencesModal = false"
                >
                    {{ t('Cancel') }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 btn-primary disabled:opacity-50"
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
        </template>
    </AppModal>
</template>
