import { useToastr } from '@/Composables/useToastr'

/**
 * One place that turns a refused chat request into something the visitor can see.
 *
 * The chatbot's API helpers reduced every failure to `new Error('Forbidden')` / `HTTP 500`
 * and several callers swallowed even that, so a refusal looked like nothing happening at
 * all — the conversation stayed in the sidebar, the thumb stayed lit, the rename reverted
 * with no explanation.
 *
 * The server's own wording wins over anything invented here, so demo mode's block, a rate
 * limit (429), an expired session (419) and validation each speak for themselves.
 */
export async function chatErrorMessage(response: Response, fallback = 'Something went wrong. Please try again.'): Promise<string> {
    let body: { message?: string; error?: string } | null = null

    try {
        body = await response.clone().json() as { message?: string; error?: string }
    } catch {
        body = null
    }

    if (body?.message) return body.message
    if (body?.error) return body.error
    if (response.status === 429) return 'Too many requests. Please slow down.'
    if (response.status === 419) return 'Your session expired. Please reload the page.'
    if (response.status === 401) return 'Please sign in to continue.'

    return fallback
}

/** Report a refused request as a toast and hand the message back to the caller. */
export async function toastChatError(response: Response, fallback?: string): Promise<string> {
    const message = await chatErrorMessage(response, fallback)

    useToastr().error(message)

    return message
}

/** The same toast where there is no Response to read (a network failure). */
export function toastChatFailure(message: string): string {
    useToastr().error(message)

    return message
}
