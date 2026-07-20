/**
 * Small presentation helpers for the assistant widget. Dependency-free on purpose —
 * the widget is embedded on every page, so it avoids pulling in a date library.
 */

/**
 * A compact relative timestamp: "just now", "5 min ago", "2 hours ago", "3 days ago",
 * then an absolute date beyond a week. `epochMs` is milliseconds since the epoch.
 */
export function relativeTime(epochMs: number | undefined, now: number = Date.now()): string {
    if (!epochMs) return ''

    const diff = Math.max(0, now - epochMs)
    const sec = Math.floor(diff / 1000)

    if (sec < 45) return 'just now'

    const min = Math.floor(sec / 60)
    if (min < 60) return `${min} min ago`

    const hr = Math.floor(min / 60)
    if (hr < 24) return `${hr} ${hr === 1 ? 'hour' : 'hours'} ago`

    const day = Math.floor(hr / 24)
    if (day < 7) return `${day} ${day === 1 ? 'day' : 'days'} ago`

    return new Date(epochMs).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}
