/**
 * Bridging `<input type="datetime-local">` and the server's absolute instants.
 *
 * A datetime-local input has no timezone: its value is a bare wall clock
 * ("2026-07-16T22:06"). The server stores instants and serialises them as UTC
 * ("2026-07-16T16:06:00.000000Z"). Something has to say which zone the wall clock
 * belongs to, and here it is the SITE's display timezone (General Settings →
 * Timezone) — not the browser's, so the whole team schedules against one clock.
 *
 * Posting the raw input value instead makes the server read that wall clock as UTC,
 * which shifts the moment by the site's offset — the schedule lands hours from what
 * was picked, and rendering it back moves it again, so a date can return a day later
 * than it went in. Convert at both edges:
 *
 *     form.starts_at = toDateTimeLocalInput(row.starts_at)   // filling the input
 *     starts_at: fromDateTimeLocalInput(form.starts_at)      // submitting it
 */

import { siteTimeZone, zonedWallClock, zonedWallClockToInstant } from '@/lib/timezone'

const pad = (value: number): string => String(value).padStart(2, '0')

/**
 * Server instant (ISO/UTC) → the `YYYY-MM-DDTHH:mm` wall clock a datetime-local input
 * expects, read in the site's timezone. Empty string for null/unparseable, which
 * clears the input.
 */
export function toDateTimeLocalInput(value?: string | Date | null): string {
    if (!value) return ''

    const date = value instanceof Date ? value : new Date(value)

    if (Number.isNaN(date.getTime())) return ''

    const w = zonedWallClock(date, siteTimeZone())

    return `${w.year}-${pad(w.month)}-${pad(w.day)}T${pad(w.hour)}:${pad(w.minute)}`
}

/**
 * A datetime-local value → an absolute UTC instant for the server, reading the wall
 * clock in the site's timezone. Null for empty or unparseable input, so an optional
 * field clears rather than posting garbage.
 */
export function fromDateTimeLocalInput(value?: string | null): string | null {
    if (!value) return null

    // Parsed by hand, not `new Date(value)`: that resolves a bare wall clock against
    // the BROWSER's zone, which is the bug this file exists to prevent.
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value.trim())

    if (!match) return null

    const [, year, month, day, hour, minute, second] = match

    const instant = zonedWallClockToInstant({
        year: Number(year),
        month: Number(month),
        day: Number(day),
        hour: Number(hour),
        minute: Number(minute),
        second: Number(second ?? 0),
    }, siteTimeZone())

    return Number.isNaN(instant.getTime()) ? null : instant.toISOString()
}
