/**
 * Request plumbing shared by the assistant widget.
 *
 * All assistant routes sit behind Laravel's `web` middleware group, so every POST
 * needs a CSRF token. The token is read from the <meta name="csrf-token"> tag, with
 * the XSRF-TOKEN cookie as a fallback (it survives a session regeneration that the
 * baked-in meta tag would miss).
 */

function metaToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
}

function cookieToken(): string {
    const match = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return match ? decodeURIComponent(match.pop() ?? '') : ''
}

export function csrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {}

    const meta = metaToken()
    if (meta) headers['X-CSRF-TOKEN'] = meta

    const cookie = cookieToken()
    if (cookie) headers['X-XSRF-TOKEN'] = cookie

    return headers
}
