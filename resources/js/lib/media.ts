import { usePage } from '@inertiajs/vue3'

/**
 * Client-side mirror of PHP's media_url() helper.
 *
 * Media paths are stored in the database as RELATIVE disk keys (e.g. "avatars/1.png").
 * Where they resolve to depends on the storage driver the admin picked in
 * Settings → Storage: the local server, or an S3-compatible bucket (S3, R2, Spaces,
 * Wasabi, B2). Hardcoding "/storage/" + path breaks every image the moment a cloud
 * driver is active, because the file lives in the bucket, not on the app server.
 *
 * `mediaBase` is shared globally by HandleInertiaRequests: "/storage" on the local
 * disk, or the bucket/CDN origin on a cloud driver.
 *
 * Values that are already absolute (http/https/protocol-relative), a data: URI, or a
 * blob: URL (a local file preview from URL.createObjectURL) are returned untouched.
 * A legacy value that already carries the "/storage/" prefix is normalised so it is
 * not doubled up.
 *
 * Returns '' for null/empty input, so it is safe to bind directly to :src.
 */
export function mediaUrl(path?: string | null): string {
    if (!path) return ''

    const value = String(path).trim()
    if (!value) return ''

    if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) {
        return value
    }

    // A root-relative path that is NOT a stored media key points at a static asset
    // shipped in public/ (e.g. "/images/default-avatar.png"). Leave it alone — it is
    // served by the app, not the media disk.
    if (value.startsWith('/') && !/^\/storage\//i.test(value)) {
        return value
    }

    // Normalise a legacy value that already includes the public prefix.
    let key = value.replace(/^\/+/, '')
    if (key.startsWith('storage/')) {
        key = key.slice('storage/'.length)
    }

    // Windows-style separators can leak in from settings written on a Windows host.
    key = key.replace(/\\/g, '/')

    const base = (usePage().props.mediaBase as string | undefined) ?? '/storage'

    return `${base.replace(/\/+$/, '')}/${key}`
}
