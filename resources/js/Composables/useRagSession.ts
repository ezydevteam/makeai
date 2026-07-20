function getCsrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

async function apiGet<T>(url: string): Promise<T> {
    const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(await res.json().then(d => d.message).catch(() => 'Request failed'))
    return res.json()
}

async function apiPost<T>(url: string, body?: Record<string, unknown>): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    })
    if (!res.ok) throw new Error(await res.json().then(d => d.message).catch(() => 'Request failed'))
    return res.json()
}

async function apiDelete(url: string): Promise<void> {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        credentials: 'same-origin',
    })
    if (!res.ok) throw new Error('Delete failed')
}

export interface RagSession {
    id: string
    status: string
    title: string | null
    source_meta: Record<string, unknown> | null
    ingest_error?: string | null
    created_at: string
}

export interface RagMessage {
    id: string
    role: 'user' | 'assistant'
    content: string
    sources?: Array<Record<string, unknown>>
    created_at: string
}

export function useRagSession() {
    async function createSession(toolSlug: string, payload: FormData | Record<string, string>): Promise<RagSession> {
        const res = await fetch(`/tools/rag/${toolSlug}/sessions`, {
            method: 'POST',
            headers: payload instanceof FormData
                ? { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() }
                : { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            credentials: 'same-origin',
            body: payload instanceof FormData ? payload : JSON.stringify(payload),
        })
        if (!res.ok) throw new Error(await res.json().then(d => d.message).catch(() => 'Failed'))
        const data = await res.json()
        return data.session
    }

    async function getStatus(sessionId: string): Promise<RagSession> {
        return apiGet(`/tools/rag/sessions/${sessionId}/status`)
    }

    async function getSession(sessionId: string): Promise<{ session: RagSession; messages: RagMessage[] }> {
        return apiGet(`/tools/rag/sessions/${sessionId}`)
    }

    async function saveToKb(sessionId: string, name: string): Promise<{ knowledge_base: { id: number; name: string } }> {
        return apiPost(`/tools/rag/sessions/${sessionId}/save-to-kb`, { name })
    }

    async function deleteSession(sessionId: string): Promise<void> {
        return apiDelete(`/tools/rag/sessions/${sessionId}`)
    }

    async function getShareToken(sessionId: string): Promise<{ share_url: string }> {
        return apiPost(`/tools/rag/sessions/${sessionId}/share`)
    }

    async function revokeShareToken(sessionId: string): Promise<void> {
        return apiDelete(`/tools/rag/sessions/${sessionId}/share`)
    }

    return {
        createSession,
        getStatus,
        getSession,
        saveToKb,
        deleteSession,
        getShareToken,
        revokeShareToken,
    }
}
