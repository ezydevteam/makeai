/**
 * Resolving instants against the site's display timezone (General Settings → Timezone).
 *
 * The server stores and sends UTC instants and tells the client which zone to present
 * them in. Everything the user reads or edits is that zone's wall clock — not the
 * browser's — so an admin in another country still sees the site's clock, and two
 * admins never disagree about when something is scheduled.
 *
 * Built on Intl rather than a date library (the project has none). The awkward
 * direction is wall clock → instant: a zone's offset depends on the instant you are
 * asking about, which is the thing being solved for. `zonedWallClockToInstant` handles
 * that with a two-pass correction.
 */

import { router } from '@inertiajs/vue3'

/**
 * The site's display timezone, falling back to the browser's when the prop is missing
 * (the install flow shares no settings, and a stray render shouldn't throw).
 */
export function siteTimeZone(): string {
    const locale = (router as any).page?.props?.locale as { timezone?: string } | undefined

    return locale?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
}

interface WallClock {
    year: number
    month: number
    day: number
    hour: number
    minute: number
    second: number
}

/** The wall clock a given instant reads as in `timeZone`. */
export function zonedWallClock(date: Date, timeZone: string): WallClock {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        hour12: false,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).formatToParts(date)

    const at: Record<string, string> = {}
    for (const part of parts) at[part.type] = part.value

    return {
        year: Number(at.year),
        month: Number(at.month),
        day: Number(at.day),
        // Some engines render midnight as "24" under hour12:false.
        hour: Number(at.hour) % 24,
        minute: Number(at.minute),
        second: Number(at.second),
    }
}

/** How far `timeZone` sits from UTC at a given instant, in ms (DST-aware). */
function zoneOffsetMs(date: Date, timeZone: string): number {
    const w = zonedWallClock(date, timeZone)
    const asIfUtc = Date.UTC(w.year, w.month - 1, w.day, w.hour, w.minute, w.second)

    // Match the instant's own sub-second part so the difference is a clean offset.
    return asIfUtc - (date.getTime() - date.getMilliseconds())
}

/**
 * A wall clock read in `timeZone` → the instant it refers to.
 *
 * Two passes: guess the offset from the naive instant, correct it, then re-measure at
 * the corrected instant. The second pass is what makes DST changeovers land right —
 * near a transition the offset at the guess differs from the offset at the answer.
 */
export function zonedWallClockToInstant(w: WallClock, timeZone: string): Date {
    const naive = Date.UTC(w.year, w.month - 1, w.day, w.hour, w.minute, w.second)

    let ts = naive - zoneOffsetMs(new Date(naive), timeZone)
    ts = naive - zoneOffsetMs(new Date(ts), timeZone)

    return new Date(ts)
}

/**
 * The instant re-based so that a Date's LOCAL getters report the site zone's wall
 * clock. Only for feeding formatters that read local fields — the returned Date is a
 * different instant and must never be sent to the server or compared against one.
 */
export function asZonedDisplayDate(date: Date, timeZone: string): Date {
    const w = zonedWallClock(date, timeZone)

    return new Date(w.year, w.month - 1, w.day, w.hour, w.minute, w.second)
}

/** The site zone's short name for a given instant ("GMT+6", "EDT"), for labelling. */
export function zoneAbbreviation(date: Date, timeZone: string): string {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'short' }).formatToParts(date)

    return parts.find((part) => part.type === 'timeZoneName')?.value ?? ''
}
