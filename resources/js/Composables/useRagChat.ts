import { ref, nextTick } from 'vue'
import { sanitizeErrorMessage } from '@/Composables/useErrorSanitizer'

function getCsrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

export interface RagChatMessage {
    id: string
    role: 'user' | 'assistant'
    content: string
    sources?: Array<{
        doc: string
        chunk?: number
        score?: number
        snippet?: string
        start?: number
        doc_label?: string
    }>
    input_tokens?: number
    output_tokens?: number
    credits_used?: number
}

export interface StreamingState {
    isStreaming: boolean
    content: string
    sources: RagChatMessage['sources']
    abort: () => void
}

export function useRagChat(sessionId: string, mode: string = 'chat') {
    const messages = ref<RagChatMessage[]>([])
    const isStreaming = ref(false)
    const streamingContent = ref('')
    const streamingSources = ref<RagChatMessage['sources']>([])
    const error = ref<string | null>(null)
    let abortController: AbortController | null = null

    async function loadMessages(): Promise<void> {
        try {
            const res = await fetch(`/tools/rag/sessions/${sessionId}`, {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrf() },
                credentials: 'same-origin',
            })
            if (res.ok) {
                const data = await res.json()
                messages.value = data.messages || []
            }
        } catch {}
    }

    function addUserMessage(content: string) {
        messages.value.push({
            id: crypto.randomUUID(),
            role: 'user',
            content,
        })
    }

    async function sendMessage(message: string, extraFields: Record<string, string> = {}): Promise<void> {
        if (!message.trim() || isStreaming.value) return

        error.value = null
        addUserMessage(message)
        isStreaming.value = true
        streamingContent.value = ''
        streamingSources.value = []

        abortController = new AbortController()

        try {
            const body = { message, mode, ...extraFields }

            const res = await fetch(`/tools/rag/sessions/${sessionId}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-XSRF-TOKEN': getCsrf(),
                },
                credentials: 'same-origin',
                signal: abortController.signal,
                body: JSON.stringify(body),
            })

            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Request failed' }))
                throw new Error(err.message)
            }

            const reader = res.body?.getReader()
            if (!reader) throw new Error('Streaming not supported')

            const decoder = new TextDecoder()
            let buffer = ''
            let usageStats: Record<string, unknown> = {}

            while (true) {
                const { done, value } = await reader.read()
                if (done) break

                buffer += decoder.decode(value, { stream: true })
                const lines = buffer.split('\n')
                buffer = lines.pop() || ''

                for (const line of lines) {
                    if (!line.startsWith('data: ')) continue
                    try {
                        const event = JSON.parse(line.slice(6))

                        if (event.type === 'sources') {
                            streamingSources.value = event.items || []
                        } else if (event.type === 'token') {
                            streamingContent.value += event.content
                        } else if (event.type === 'usage') {
                            usageStats = event
                        } else if (event.type === 'error') {
                            throw new Error(event.message)
                        }
                    } catch (e) {
                        if (e instanceof SyntaxError) continue
                        throw e
                    }
                }
            }

            // Persist completed message
            messages.value.push({
                id: crypto.randomUUID(),
                role: 'assistant',
                content: streamingContent.value,
                sources: streamingSources.value?.length ? [...streamingSources.value] : undefined,
                input_tokens: usageStats.input_tokens as number,
                output_tokens: usageStats.output_tokens as number,
                credits_used: usageStats.credits as number,
            })
        } catch (e: any) {
            if (e.name !== 'AbortError') {
                error.value = sanitizeErrorMessage(e.message || 'Stream failed')
                if (streamingContent.value) {
                    messages.value.push({
                        id: crypto.randomUUID(),
                        role: 'assistant',
                        content: streamingContent.value + '\n\n*Error: ' + error.value + '*',
                        sources: streamingSources.value?.length ? [...streamingSources.value] : undefined,
                    })
                }
            }
        } finally {
            isStreaming.value = false
            streamingContent.value = ''
            streamingSources.value = []
            abortController = null
        }
    }

    function stop() {
        abortController?.abort()
    }

    function clear() {
        messages.value = []
        error.value = null
    }

    return {
        messages,
        isStreaming,
        streamingContent,
        streamingSources,
        error,
        loadMessages,
        addUserMessage,
        sendMessage,
        stop,
        clear,
    }
}
