/**
 * Fetch helpers for the non-chat panels (Help / Message / CSAT).
 *
 * These are plain JSON calls, so unlike the SSE chat they don't need stream parsing —
 * but they still go through the app's CSRF flow for POSTs and always send credentials so
 * the server-side visibility gate sees the session.
 */
import { csrfHeaders } from './useAssistantApi'
import type { ConversationSummary, HelpArticle, HelpArticleDetail } from '../types'

async function getJson<T>(url: string): Promise<T | null> {
    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
        if (!response.ok) return null
        return await response.json() as T
    } catch {
        return null
    }
}

export interface HelpListResult {
    available: boolean
    articles: HelpArticle[]
    has_more: boolean
    leave_message: boolean
}

export async function fetchHelpArticles(endpoint: string): Promise<HelpListResult> {
    const data = await getJson<HelpListResult>(endpoint)
    return data ?? { available: false, articles: [], has_more: false, leave_message: false }
}

export async function searchHelpArticles(endpoint: string, query: string): Promise<HelpArticle[]> {
    const data = await getJson<{ articles: HelpArticle[] }>(`${endpoint}?q=${encodeURIComponent(query)}`)
    return data?.articles ?? []
}

export async function fetchHelpArticle(baseEndpoint: string, slug: string): Promise<HelpArticleDetail | null> {
    const data = await getJson<{ article: HelpArticleDetail | null }>(`${baseEndpoint}/${encodeURIComponent(slug)}`)
    return data?.article ?? null
}

export interface ConversationListResult {
    conversations: ConversationSummary[]
    /** False for guests — they can chat, but history isn't kept. */
    can_save: boolean
}

export async function fetchConversations(endpoint: string): Promise<ConversationListResult> {
    const data = await getJson<ConversationListResult>(endpoint)
    return data ?? { conversations: [], can_save: false }
}

export async function deleteConversation(endpoint: string, sessionId: string): Promise<boolean> {
    try {
        const response = await fetch(endpoint, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            credentials: 'same-origin',
            body: JSON.stringify({ session_id: sessionId }),
        })
        return response.ok
    } catch {
        return false
    }
}

export interface MessageResult {
    ok: boolean
    message: string
    /** Field-level validation errors keyed by field name. */
    errors?: Record<string, string[]>
}

export async function submitMessage(
    endpoint: string,
    payload: { email: string; message: string; name?: string; website?: string },
): Promise<MessageResult> {
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })

        const data = await response.json().catch(() => null) as
            | { status?: string; message?: string; errors?: Record<string, string[]> }
            | null

        if (response.ok && data?.status === 'ok') {
            return { ok: true, message: data.message ?? 'Your message has been sent.' }
        }

        return {
            ok: false,
            message: data?.message ?? 'Your message could not be sent. Please try again.',
            errors: data?.errors,
        }
    } catch {
        return { ok: false, message: 'Your message could not be sent. Please try again.' }
    }
}

export async function submitCsat(
    endpoint: string,
    payload: { session_id: string; score: number; context_page?: string },
): Promise<boolean> {
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        return response.ok
    } catch {
        return false
    }
}
