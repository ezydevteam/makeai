<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import type { CSSProperties } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { resolveNotificationIconClass } from '@/Composables/useNotifications'
import { disconnectNotificationEcho, getNotificationEcho, resolveNotificationChannel } from '@/echo'

interface NotificationItem {
    id: string
    icon: string | null
    title: string
    message: string
    level: 'info' | 'success' | 'warning' | 'error'
    action_url: string | null
    action_label: string | null
    read_at: string | null
    created_at: string | null
    is_read: boolean
}

interface NotificationBellUi {
    wrapperClass?: string
    triggerClass?: string
    triggerStyle?: CSSProperties
    dropdownClass?: string
    iconClass?: string
    iconStyle?: CSSProperties
}

const props = withDefaults(defineProps<{
    context?: 'user' | 'admin'
    label?: string
    ui?: NotificationBellUi
}>(), {
    context: 'user',
    label: '',
    ui: () => ({}),
})

const { t } = useTranslate()
const page = usePage()

const open = ref(false)
const loading = ref(false)
const unreadCount = ref(Number((page.props.notifications as any)?.unread_count ?? 0))
const items = ref<NotificationItem[]>(((page.props.notifications as any)?.items ?? []) as NotificationItem[])
let timer: number | undefined
let subscribedChannel: any = null

const enabled = computed(() => Boolean((page.props.notifications as any)?.enabled))
const shouldRender = computed(() => enabled.value || unreadCount.value > 0 || items.value.length > 0)
const notificationLabel = computed(() => props.label || t('Notifications'))
const notificationWrapperClass = computed(() => props.ui?.wrapperClass || '')
const notificationButtonClass = computed(() => props.ui?.triggerClass || 'relative flex h-8 w-8 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:bg-surface-800 dark:text-gray-400 dark:hover:bg-surface-700 dark:hover:text-white')
const notificationButtonStyle = computed(() => props.ui?.triggerStyle ?? {})
const notificationDropdownClass = computed(() => props.ui?.dropdownClass || 'absolute right-0 z-50 mt-2 w-[360px] overflow-hidden rounded-xl border border-gray-200 bg-white text-gray-700 shadow-xl dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 rtl:left-0 rtl:right-auto')
const notificationIconClass = computed(() => props.ui?.iconClass || 'ti ti-bell')
const notificationIconStyle = computed(() => props.ui?.iconStyle ?? {})
const interval = computed(() => Number((page.props.notifications as any)?.polling_interval ?? 30000))
const realtime = computed(() => (page.props.notifications as any)?.realtime ?? null)
const actorId = computed(() => {
    if (props.context === 'admin') {
        return Number((page.props.admin as any)?.user?.id ?? 0)
    }

    return Number((page.props.auth as any)?.user?.id ?? 0)
})
const latestRoute = computed(() => props.context === 'admin' ? route('admin.notifications.latest') : route('user.dashboard.notifications.latest'))
const indexRoute = computed(() => props.context === 'admin' ? route('admin.notifications.index') : route('user.dashboard.notifications.index'))
const markAllRoute = computed(() => props.context === 'admin' ? route('admin.notifications.read-all') : route('user.dashboard.notifications.read-all'))

const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

const request = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            ...(options.headers ?? {}),
        },
        ...options,
    })

    if (!response.ok) {
        throw new Error('Notification request failed')
    }

    return response.json()
}

const refresh = async () => {
    // No authenticated actor → the endpoint is auth-only and would just 401.
    if (!enabled.value || actorId.value <= 0 || loading.value) return

    loading.value = true
    try {
        const json = await request(latestRoute.value)
        unreadCount.value = Number(json.data?.unread_count ?? 0)
        items.value = (json.data?.items ?? []) as NotificationItem[]
    } finally {
        loading.value = false
    }
}

const normalizeBroadcast = (payload: any): NotificationItem => ({
    id: String(payload.id ?? crypto.randomUUID()),
    icon: payload.icon ?? payload.data?.icon ?? null,
    title: String(payload.title ?? payload.data?.title ?? t('Notification')),
    message: String(payload.message ?? payload.data?.message ?? ''),
    level: (payload.level ?? payload.data?.level ?? 'info') as NotificationItem['level'],
    action_url: payload.action_url ?? payload.data?.action_url ?? null,
    action_label: payload.action_label ?? payload.data?.action_label ?? null,
    read_at: null,
    created_at: payload.created_at ?? new Date().toISOString(),
    is_read: false,
})

const subscribeRealtime = () => {
    if (!enabled.value || actorId.value <= 0) return

    const echo = getNotificationEcho(realtime.value)
    if (!echo) return

    const channelName = resolveNotificationChannel(props.context, actorId.value)
    subscribedChannel = echo.private(channelName)
    subscribedChannel.notification((payload: any) => {
        const item = normalizeBroadcast(payload)
        items.value = [item, ...items.value.filter((existing) => existing.id !== item.id)].slice(0, 10)
        unreadCount.value += 1
    })
}

const unsubscribeRealtime = () => {
    if (subscribedChannel && actorId.value > 0) {
        const echo = getNotificationEcho(realtime.value)
        echo?.leave(resolveNotificationChannel(props.context, actorId.value))
    }
    subscribedChannel = null
}

const markReadUrl = (id: string) => props.context === 'admin'
    ? route('admin.notifications.read', id)
    : route('user.dashboard.notifications.read', id)

const markRead = async (item: NotificationItem) => {
    if (!item.is_read) {
        await request(markReadUrl(item.id), { method: 'POST' })
        item.is_read = true
        item.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
    }

    if (item.action_url) {
        router.visit(item.action_url)
    }
}

const markAllRead = async () => {
    await request(markAllRoute.value, { method: 'POST' })
    unreadCount.value = 0
    items.value = items.value.map((item) => ({
        ...item,
        is_read: true,
        read_at: item.read_at ?? new Date().toISOString(),
    }))
}

const levelClasses = (level: NotificationItem['level']) => ({
    success: 'border border-primary-200 bg-primary-100 text-primary-600 dark:border-primary-800/70 dark:bg-primary-900/20 dark:text-primary-300',
    warning: 'border border-primary-200 bg-primary-100 text-amber-600 dark:border-primary-800/70 dark:bg-primary-900/20 dark:text-amber-300',
    error: 'border border-primary-200 bg-primary-100 text-red-600 dark:border-primary-800/70 dark:bg-primary-900/20 dark:text-red-300',
    info: 'border border-primary-200 bg-primary-100 text-blue-600 dark:border-primary-800/70 dark:bg-primary-900/20 dark:text-secondary-300',
}[level] ?? 'border border-primary-200 bg-primary-100 text-secondary-600')

const timeAgo = (value: string | null) => {
    if (!value) return ''
    const diff = Math.round((new Date(value).getTime() - Date.now()) / 1000)
    const formatter = new Intl.RelativeTimeFormat((page.props.locale as any)?.code ?? 'en', { numeric: 'auto' })
    const abs = Math.abs(diff)

    if (abs < 60) return formatter.format(diff, 'second')
    if (abs < 3600) return formatter.format(Math.round(diff / 60), 'minute')
    if (abs < 86400) return formatter.format(Math.round(diff / 3600), 'hour')

    return formatter.format(Math.round(diff / 86400), 'day')
}

const close = () => {
    open.value = false
}

onMounted(() => {
    document.addEventListener('click', close)
    subscribeRealtime()
    if (enabled.value && actorId.value > 0) {
        timer = window.setInterval(refresh, interval.value)
    }
})

onUnmounted(() => {
    document.removeEventListener('click', close)
    if (timer) window.clearInterval(timer)
    unsubscribeRealtime()
    disconnectNotificationEcho()
})

watch(realtime, () => {
    unsubscribeRealtime()
    subscribeRealtime()
})
</script>

<template>
    <div v-if="shouldRender" :class="['relative', notificationWrapperClass]" @click.stop>
        <button
            type="button"
            :class="notificationButtonClass"
            :style="notificationButtonStyle"
            :aria-label="notificationLabel"
            @click="open = !open"
        >
            <i :class="[notificationIconClass, 'text-[18px] leading-none']" :style="notificationIconStyle" aria-hidden="true" />
            <span v-if="props.label" class="max-w-full truncate text-[11px] leading-none">{{ props.label }}</span>
            <span v-if="unreadCount > 0" class="absolute flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white" :class="props.label ? 'right-2 top-1' : '-right-1 -top-1'">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="scale-95 opacity-0" enter-to-class="scale-100 opacity-100" leave-active-class="transition ease-in duration-150" leave-from-class="scale-100 opacity-100" leave-to-class="scale-95 opacity-0">
            <div v-if="open" :class="notificationDropdownClass">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2 dark:border-surface-800 dark:bg-surface-800/70">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Notifications') }}</h3>
                        <p class="text-xs text-gray-500">{{ t(':count unread', { count: unreadCount }) }}</p>
                    </div>
                    <button v-if="unreadCount > 0" type="button" class="text-xs font-semibold !text-primary-600 hover:!text-primary-500" @click="markAllRead">
                        {{ t('Mark all as read') }}
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <button
                        v-for="item in items"
                        :key="item.id"
                        type="button"
                        class="flex w-full gap-3 border-b border-gray-100 px-4 py-3 text-left transition hover:bg-primary-50/70 dark:border-surface-800 dark:hover:bg-surface-800"
                        :class="!item.is_read ? 'bg-primary-50/40 dark:bg-primary-900/10' : ''"
                        @click="markRead(item)"
                    >
                        <span :class="levelClasses(item.level)" class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                            <i :class="[resolveNotificationIconClass(item), 'text-base leading-none']" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start gap-2">
                                <span class="line-clamp-1 text-sm font-semibold text-gray-900 dark:text-white">{{ item.title }}</span>
                                <span v-if="!item.is_read" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                            </span>
                            <span class="flex items-start justify-between gap-3">
                                <span class="line-clamp-2 min-w-0 flex-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ item.message }}</span>
                                <span class="shrink-0 whitespace-nowrap pt-0.5 text-[11px] text-gray-400">{{ timeAgo(item.created_at) }}</span>
                            </span>
                        </span>
                    </button>

                    <div v-if="items.length === 0" class="px-4 py-10 text-center text-sm text-gray-500">
                        {{ t('No notifications yet.') }}
                    </div>
                </div>

                <Link :href="indexRoute" class="block bg-gray-50 px-4 py-3 text-center text-sm font-medium !text-primary-700 hover:!text-primary-600 dark:!text-primary-400 dark:bg-surface-800">
                    {{ t('View all notifications') }}
                </Link>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.header-soft-icon-button {
    background: var(--header-soft-icon-bg, var(--surface-card));
    border-color: var(--header-soft-icon-border, transparent);
}
.header-soft-icon-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-soft-icon-color, inherit));
    background: var(--header-soft-icon-hover-bg, var(--color-primary-50));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg, var(--color-gray-100)) !important;
    border-color: transparent !important;
}
.dark .header-soft-icon-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-soft-icon-color, inherit));
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.dark .header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08)) !important;
    border-color: transparent !important;
}
</style>
