import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface LocaleProp {
    code: string
}

export function useDateFormat() {
    const page = usePage()
    const locale = computed(() => (page.props.locale as LocaleProp)?.code ?? 'en')

    const asDate = (date: string | Date) => date instanceof Date ? date : new Date(date)

    const formatDate = (date: string | Date): string => new Intl.DateTimeFormat(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(asDate(date))

    const formatTime = (date: string | Date): string => new Intl.DateTimeFormat(locale.value, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(asDate(date))

    const formatDateTime = (date: string | Date): string => new Intl.DateTimeFormat(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(asDate(date))

    const formatRelative = (date: string | Date): string => {
        const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })
        const diff = (asDate(date).getTime() - Date.now()) / 1000
        const units: [Intl.RelativeTimeFormatUnit, number][] = [
            ['year', 31536000],
            ['month', 2592000],
            ['week', 604800],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60],
            ['second', 1],
        ]

        for (const [unit, seconds] of units) {
            if (Math.abs(diff) >= seconds) {
                return rtf.format(Math.round(diff / seconds), unit)
            }
        }

        return rtf.format(0, 'second')
    }

    return { formatDate, formatTime, formatDateTime, formatRelative }
}
