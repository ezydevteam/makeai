import { ref, onMounted, onUnmounted, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getNotificationEcho, resolveNotificationChannel, disconnectNotificationEcho } from '@/echo'

interface NotificationItem {
    id: string
    title: string
    message: string
    category: string
    level: string
    icon: string | null
    action_url: string | null
    action_label: string | null
    meta: Record<string, unknown>
    read_at: string | null
    created_at: string
    is_read: boolean
}

export interface NotificationIconSource {
    icon: string | null
    level: string
}

interface BroadcastingConfig {
    driver: 'reverb' | 'pusher' | 'polling'
    key?: string
    host?: string
    port?: number
    scheme?: string
    cluster?: string
    interval_seconds?: number
}

export const resolveNotificationIconClass = (item: NotificationIconSource) => {
    const icon = item.icon?.trim()
    if (icon) {
        return icon
    }

    return ({
        success: 'ti ti-badge-check',
        warning: 'ti ti-alert-triangle',
        error: 'ti ti-bell-ringing',
        info: 'ti ti-bell',
    }[item.level] ?? 'ti ti-bell')
}

export function useNotifications(context: 'user' | 'admin' = 'user') {
    const page = usePage()
    const notifications = computed<NotificationItem[]>(() => {
        return (page.props.notifications as any)?.items ?? []
    })
    const unreadCount = computed<number>(() => {
        return (page.props.notifications as any)?.unread_count ?? 0
    })
    const enabled = computed<boolean>(() => {
        return (page.props.notifications as any)?.enabled ?? false
    })
    const broadcasting = computed<BroadcastingConfig>(() => {
        return (page.props.broadcasting as BroadcastingConfig) ?? { driver: 'polling', interval_seconds: 30 }
    })

    let pollingTimer: ReturnType<typeof setInterval> | null = null
    let echoChannel: any = null
    const actorId = ref<number | string | null>(null)

    const determineActorId = (): number | string | null => {
        if (context === 'admin') {
            const adminUser = (page.props.admin as any)?.user
            return adminUser?.id ?? null
        }
        const authUser = (page.props.auth as any)?.user
        return authUser?.id ?? null
    }

    const fetchLatest = async () => {
        const prefix = context === 'admin' ? '/admin' : ''
        try {
            const response = await fetch(`${prefix}/notifications/latest`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            })
            if (response.ok) {
                const data = await response.json()
                // Mutate reactive state via page.props
                ;(page.props.notifications as any).items = data.items ?? data.data ?? []
                ;(page.props.notifications as any).unread_count = data.unread_count ?? 0
            }
        } catch {
            // silently ignore polling errors
        }
    }

    const startPolling = () => {
        if (pollingTimer) clearInterval(pollingTimer)
        const intervalMs = (broadcasting.value.interval_seconds ?? 30) * 1000
        pollingTimer = setInterval(fetchLatest, intervalMs)
    }

    const stopPolling = () => {
        if (pollingTimer) {
            clearInterval(pollingTimer)
            pollingTimer = null
        }
    }

    const subscribeRealtime = () => {
        const config = broadcasting.value
        if (!config.driver || config.driver === 'polling') return

        const echo = getNotificationEcho(config)
        if (!echo) return

        const id = actorId.value
        if (!id) return

        const channelName = resolveNotificationChannel(context, id)
        echoChannel = echo.private(channelName)
        echoChannel.notification((notification: any) => {
            // Normalize the broadcast payload into NotificationItem shape
            const data = notification.data ?? notification.payload ?? notification
            const item: NotificationItem = {
                id: data.id ?? notification.id ?? crypto.randomUUID(),
                title: data.title ?? 'Notification',
                message: data.message ?? '',
                category: data.category ?? 'system',
                level: data.level ?? 'info',
                icon: data.icon ?? null,
                action_url: data.action_url ?? null,
                action_label: data.action_label ?? null,
                meta: data.meta ?? {},
                read_at: null,
                created_at: new Date().toISOString(),
                is_read: false,
            }
            const items = (page.props.notifications as any)?.items ?? []
            items.unshift(item)
            ;(page.props.notifications as any).items = items.slice(0, 10)
            ;(page.props.notifications as any).unread_count = ((page.props.notifications as any)?.unread_count ?? 0) + 1
        })
    }

    const unsubscribeRealtime = () => {
        if (echoChannel) {
            echoChannel.stopListening('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated')
            echoChannel = null
        }
    }

    onMounted(() => {
        actorId.value = determineActorId()

        if (enabled.value) {
            subscribeRealtime()
        }

        // Always start polling as a safety net (works even when WebSocket is connected)
        if (enabled.value) {
            startPolling()
        }
    })

    onUnmounted(() => {
        stopPolling()
        unsubscribeRealtime()
        disconnectNotificationEcho()
    })

    return {
        notifications,
        unreadCount,
        enabled,
        broadcasting,
        fetchLatest,
    }
}
