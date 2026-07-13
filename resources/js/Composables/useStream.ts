import { ref } from 'vue'
import { sanitizeErrorMessage } from '@/Composables/useErrorSanitizer'
import { t } from '@/Composables/useTranslate'

interface StreamPayload {
    slug: string
    fields: Record<string, unknown>
    model?: string
    action?: string
    refine_content?: string
    refine_instruction?: string
}

export function useStream() {
    const output = ref('')
    const reasoning = ref('')
    const isReasoning = ref(false)
    const isStreaming = ref(false)
    const error = ref('')
    const usage = ref<Record<string, unknown> | null>(null)
    const savedDocument = ref<Record<string, unknown> | null>(null)
    const truncated = ref(false)
    const retryCountdown = ref(0)
    const retryCancelled = ref(false)
    let headerCallback: ((headers: Headers) => void) | null = null
    let retryCancelToken: (() => void) | null = null

    const onHeaders = (cb: (headers: Headers) => void) => {
        headerCallback = cb
    }

    const csrfToken = (): string => {
        const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
        return cookie ? decodeURIComponent(cookie.pop() || '') : ''
    }

    const isRetryable = (msg: string): boolean => {
        const lower = msg.toLowerCase()
        return /rate limit|too many requests|quota exceeded|429|overloaded|service unavailable|503|gateway timeout|504/i.test(lower)
    }

    const parseRetryAfter = (headers: Headers): number | null => {
        const retryAfter = headers.get('Retry-After') || headers.get('X-RateLimit-Reset')
        if (retryAfter) {
            const seconds = parseInt(retryAfter, 10)
            if (!isNaN(seconds)) return Math.min(seconds, 120)
            const date = Date.parse(retryAfter)
            if (!isNaN(date)) return Math.max(0, Math.ceil((date - Date.now()) / 1000))
        }
        return null
    }

    const delay = async (seconds: number) => {
        retryCancelled.value = false
        retryCountdown.value = seconds
        return new Promise<void>((resolve, reject) => {
            retryCancelToken = () => {
                retryCancelled.value = true
                retryCountdown.value = 0
                reject(new Error('Retry cancelled'))
            }
            const start = Date.now()
            const tick = () => {
                if (retryCancelled.value) return
                const elapsed = Math.floor((Date.now() - start) / 1000)
                retryCountdown.value = Math.max(0, seconds - elapsed)
                if (retryCountdown.value <= 0) {
                    retryCountdown.value = 0
                    resolve()
                } else {
                    setTimeout(tick, 1000)
                }
            }
            tick()
        })
    }

    const cancelRetry = () => {
        if (retryCancelToken) {
            retryCancelToken()
            retryCancelToken = null
        }
    }

    const generate = async (payload: StreamPayload): Promise<void> => {
        output.value = ''
        reasoning.value = ''
        isReasoning.value = false
        error.value = ''
        usage.value = null
        savedDocument.value = null
        truncated.value = false
        retryCountdown.value = 0
        retryCancelled.value = false
        retryCancelToken = null
        isStreaming.value = true

        const maxRetries = 3
        const backoffs = [5, 10, 20]

        for (let attempt = 0; attempt <= maxRetries; attempt++) {
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

                if (headerCallback) {
                    headerCallback(response.headers)
                }

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}))
                    const errMsg = data.message || data.error || t('Generation failed (:status).', { status: response.status })

                    if (isRetryable(errMsg) && attempt < maxRetries) {
                        const retrySecs = parseRetryAfter(response.headers) ?? backoffs[attempt]
                        try {
                            await delay(retrySecs)
                            continue
                        } catch {
                            error.value = t('Retry cancelled.')
                            isStreaming.value = false
                            return
                        }
                    }

                    throw new Error(errMsg)
                }

                const reader = response.body?.getReader()
                if (!reader) throw new Error(t('Streaming is not supported by this browser.'))

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
                        if (data.error) {
                            const sanitized = sanitizeErrorMessage(data.error)
                            if (isRetryable(data.error) && attempt < maxRetries) {
                                const retrySecs = backoffs[attempt]
                                try {
                                    await delay(retrySecs)
                                    break
                                } catch {
                                    error.value = t('Retry cancelled.')
                                    isStreaming.value = false
                                    return
                                }
                            }
                            error.value = sanitized
                            isStreaming.value = false
                            return
                        }
                        if (data.reasoning_start) isReasoning.value = true
                        if (data.reasoning_end) isReasoning.value = false
                        if (data.reasoning) reasoning.value += data.reasoning
                        if (data.token) output.value += data.token
                        if (data.usage) usage.value = data.usage
                        if (data.document) savedDocument.value = data.document
                        if (data.truncated) truncated.value = true
                    }
                }
                isStreaming.value = false
                return
            } catch (err) {
                const errMsg = err instanceof Error ? err.message : t('Network error. Please try again.')
                if (isRetryable(errMsg) && attempt < maxRetries) {
                    const retrySecs = backoffs[attempt]
                    try {
                        await delay(retrySecs)
                        continue
                    } catch {
                        error.value = t('Retry cancelled.')
                        isStreaming.value = false
                        return
                    }
                }
                error.value = sanitizeErrorMessage(errMsg)
                isStreaming.value = false
                return
            }
        }

        error.value = t('AI is busy. Please try again later.')
        isStreaming.value = false
    }

    return { output, reasoning, isReasoning, isStreaming, error, usage, savedDocument, truncated, retryCountdown, retryCancelled, generate, onHeaders, cancelRetry }
}
