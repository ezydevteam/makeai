import { ref } from 'vue'

interface StreamPayload {
    slug: string
    fields: Record<string, unknown>
    model?: string
}

export function useStream() {
    const output = ref('')
    const isStreaming = ref(false)
    const error = ref('')
    const usage = ref<Record<string, unknown> | null>(null)
    const savedDocument = ref<Record<string, unknown> | null>(null)
    const truncated = ref(false)

    const csrfToken = (): string => {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        return cookie ? decodeURIComponent(cookie.pop() || '') : ''
    }

    const generate = async (payload: StreamPayload): Promise<void> => {
        output.value = ''
        error.value = ''
        usage.value = null
        savedDocument.value = null
        truncated.value = false
        isStreaming.value = true

        try {
            const response = await fetch('/api/v1/generate/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            })

            if (!response.ok) {
                const data = await response.json().catch(() => ({}))
                throw new Error(data.message || 'Generation failed. Please try again.')
            }

            const reader = response.body?.getReader()
            if (!reader) throw new Error('Streaming is not supported by this browser.')

            const decoder = new TextDecoder()
            let buffer = ''

            while (true) {
                const { done, value } = await reader.read()
                if (done) break

                buffer += decoder.decode(value, { stream: true })
                const events = buffer.split('\n\n')
                buffer = events.pop() || ''

                for (const event of events) {
                    const line = event.split('\n').find((item) => item.startsWith('data: '))
                    if (!line) continue

                    const raw = line.slice(6)
                    if (raw === '[DONE]') {
                        isStreaming.value = false
                        return
                    }

                    const data = JSON.parse(raw)
                    if (data.error) error.value = data.error
                    if (data.token) output.value += data.token
                    if (data.usage) usage.value = data.usage
                    if (data.document) savedDocument.value = data.document
                    if (data.truncated) truncated.value = true
                }
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Network error. Please try again.'
        } finally {
            isStreaming.value = false
        }
    }

    return { output, isStreaming, error, usage, savedDocument, truncated, generate }
}
