import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface LocaleProp {
    code: string
    date_format?: string
    time_format?: string
}

const dateFormatTokens: Record<string, (d: Date, locale: string) => string> = {
    'YYYY': (d) => String(d.getFullYear()),
    'YY': (d) => String(d.getFullYear()).slice(-2),
    'MMMM': (d, l) => new Intl.DateTimeFormat(l, { month: 'long' }).format(d),
    'MMM': (d, l) => new Intl.DateTimeFormat(l, { month: 'short' }).format(d),
    'MM': (d) => String(d.getMonth() + 1).padStart(2, '0'),
    'M': (d) => String(d.getMonth() + 1),
    'DD': (d) => String(d.getDate()).padStart(2, '0'),
    'D': (d) => String(d.getDate()),
    'dddd': (d, l) => new Intl.DateTimeFormat(l, { weekday: 'long' }).format(d),
    'ddd': (d, l) => new Intl.DateTimeFormat(l, { weekday: 'short' }).format(d),
    'HH': (d) => String(d.getHours()).padStart(2, '0'),
    'H': (d) => String(d.getHours()),
    'hh': (d) => String(d.getHours() % 12 || 12).padStart(2, '0'),
    'h': (d) => String(d.getHours() % 12 || 12),
    'mm': (d) => String(d.getMinutes()).padStart(2, '0'),
    'm': (d) => String(d.getMinutes()),
    'ss': (d) => String(d.getSeconds()).padStart(2, '0'),
    's': (d) => String(d.getSeconds()),
    'A': (d) => d.getHours() < 12 ? 'AM' : 'PM',
    'a': (d) => d.getHours() < 12 ? 'am' : 'pm',
}

const tokenRegex = /Y{2,4}|M{1,4}|D{1,2}|d{3,4}|H{1,2}|h{1,2}|m{1,2}|s{1,2}|[Aa]|[^YMDdHhmsAa]+/g

function formatWithTemplate(date: Date, template: string, locale: string): string {
    return template.replace(tokenRegex, (match) => {
        const token = dateFormatTokens[match]
        return token ? token(date, locale) : match
    })
}

export function useDateFormat() {
    const page = usePage()
    const locale = computed(() => (page.props.locale as LocaleProp)?.code ?? 'en')
    const dateFormat = computed(() => (page.props.locale as LocaleProp)?.date_format ?? 'MMM D, YYYY')
    const timeFormat = computed(() => (page.props.locale as LocaleProp)?.time_format ?? 'h:mm A')

    const asDate = (date: string | Date) => date instanceof Date ? date : new Date(date)

    const formatDate = (date: string | Date): string =>
        formatWithTemplate(asDate(date), dateFormat.value, locale.value)

    const formatTime = (date: string | Date): string =>
        formatWithTemplate(asDate(date), timeFormat.value, locale.value)

    const formatDateTime = (date: string | Date): string => {
        const d = asDate(date)
        return `${formatWithTemplate(d, dateFormat.value, locale.value)} ${formatWithTemplate(d, timeFormat.value, locale.value)}`
    }

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
