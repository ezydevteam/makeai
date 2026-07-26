import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

/**
 * One place that turns a refused request into something the visitor can see.
 *
 * Every write the widget makes — chat, thumbs, CSAT, contact message, file upload, deleting
 * a conversation — is a POST/DELETE, so demo mode answers all of them with
 * `403 {"message": "Destructive actions are disabled in demo mode."}`. Several call sites
 * threw that body away and set a bare boolean, which left the widget looking either broken
 * or, worse, successful: the thumb lit up and nothing was recorded.
 *
 * The server's own wording is preferred over anything invented here, so demo mode, rate
 * limits (429), an expired session (419) and validation all speak for themselves.
 */
export async function assistantErrorMessage(response: Response, fallback?: string): Promise<string> {
    const { t } = useTranslate()

    let body: { message?: string; error?: string } | null = null

    try {
        body = await response.clone().json() as { message?: string; error?: string }
    } catch {
        body = null
    }

    if (body?.message) return body.message
    if (body?.error) return body.error
    if (response.status === 429) return t('Too many requests. Please slow down.')
    if (response.status === 419) return t('Your session expired. Please reload the page.')

    return fallback ?? t('Something went wrong. Please try again.')
}

/**
 * Report a refused request as a toast and hand the message back, so a caller that also has
 * an inline slot for it (the chat panel's error line) can show both without parsing twice.
 */
export async function toastAssistantError(response: Response, fallback?: string): Promise<string> {
    const message = await assistantErrorMessage(response, fallback)

    useToastr().error(message)

    return message
}

/** The same toast for a thrown/network failure, where there is no Response to read. */
export function toastAssistantFailure(message: string): string {
    useToastr().error(message)

    return message
}
