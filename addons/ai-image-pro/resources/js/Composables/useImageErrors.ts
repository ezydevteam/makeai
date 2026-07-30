import { useToastr } from '@/Composables/useToastr'

/**
 * One place that turns a refused image request into something the visitor can see.
 *
 * This addon talks over axios, so a failure arrives as a thrown error carrying the response
 * rather than a Response object — the message lives at `error.response.data.message`. The
 * Studio already dug it out for its inline slots; the Library threw it away and wrote a
 * hardcoded "Could not delete image." over the top, so demo mode's block, a storage-quota
 * rejection and a genuine server fault all read identically.
 *
 * The server's own wording wins over anything invented here.
 */
export function imageErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { response?: { status?: number; data?: { message?: string; error?: string } } }).response

    if (response?.data?.message) return response.data.message
    if (response?.data?.error) return response.data.error
    if (response?.status === 429) return 'Too many requests. Please slow down.'
    if (response?.status === 419) return 'Your session expired. Please reload the page.'

    return fallback
}

/** Report a refused request as a toast and hand the message back for any inline slot. */
export function toastImageError(error: unknown, fallback: string): string {
    const message = imageErrorMessage(error, fallback)

    useToastr().error(message)

    return message
}
