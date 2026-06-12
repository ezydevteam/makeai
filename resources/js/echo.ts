import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

type NotificationRealtimeConfig = {
    driver?: 'reverb' | 'pusher' | 'polling' | 'disabled'
    key?: string
    host?: string
    port?: number
    scheme?: 'http' | 'https'
    cluster?: string
}

declare global {
    interface Window {
        Echo?: Echo<'reverb'>
        Pusher?: typeof Pusher
    }
}

let activeEcho: Echo<'reverb'> | null = null
let activeSignature = ''

export function resolveNotificationChannel(context: 'user' | 'admin', id: number | string): string {
    return context === 'admin' ? `App.Models.Admin.${id}` : `App.Models.User.${id}`
}

export function getNotificationEcho(config: NotificationRealtimeConfig | null | undefined): Echo<'reverb'> | null {
    if (!config || !['reverb', 'pusher'].includes(config.driver ?? '')) {
        disconnectNotificationEcho()
        return null
    }

    if (!config.key) {
        disconnectNotificationEcho()
        return null
    }

    const scheme = config.scheme ?? 'http'
    const port = Number(config.port ?? (scheme === 'https' ? 443 : 80))
    const host = config.host || window.location.hostname
    const signature = JSON.stringify({
        driver: config.driver,
        key: config.key,
        host,
        port,
        scheme,
        cluster: config.cluster ?? 'mt1',
    })

    if (activeEcho && activeSignature === signature) {
        return activeEcho
    }

    disconnectNotificationEcho()

    window.Pusher = Pusher
    activeSignature = signature
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

    if (config.driver === 'pusher') {
        activeEcho = new Echo({
            broadcaster: 'pusher',
            key: config.key,
            cluster: config.cluster ?? 'mt1',
            forceTLS: scheme === 'https',
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        })
    } else {
        activeEcho = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        })
    }

    window.Echo = activeEcho

    return activeEcho
}

export function disconnectNotificationEcho(): void {
    if (activeEcho) {
        activeEcho.disconnect()
    }

    activeEcho = null
    activeSignature = ''
    delete window.Echo
}
