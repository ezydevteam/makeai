/**
 * Clean up provider-specific raw API error messages into friendly, translatable messages.
 * Never expose raw provider errors, stack traces, or internal details to end-users.
 *
 * SPEC: 45.2 AI Generation Error Messages
 */
export function sanitizeErrorMessage(message: string | null | undefined): string {
    if (!message) {
        return 'Something went wrong. Please try again.'
    }

    const lower = message.toLowerCase()

    // ── Rate limit / Quota ───────────────────────────────────
    if (
        lower.includes('rate limited by ai provider') ||
        lower.includes('rate limit') ||
        lower.includes('rate_limit') ||
        lower.includes('too many requests') ||
        lower.includes('quota exceeded') ||
        lower.includes('quota_exceeded') ||
        lower.includes('insufficient_quota') ||
        lower.includes('insufficient balance') ||
        lower.includes('billing') && lower.includes('quota') ||
        lower.includes('credit limit') ||
        lower.includes('credits exhausted') ||
        lower.includes('payment required') ||
        lower.includes('429') // HTTP status embedded in message
    ) {
        return 'Rate limit reached. Please try again in a moment.'
    }

    // ── Content policy / Safety filters ──────────────────────
    if (
        lower.includes('content policy') ||
        lower.includes('content_filter') ||
        lower.includes('content filter') ||
        lower.includes('safety filter') ||
        lower.includes('safety system') ||
        lower.includes('flagged by') ||
        lower.includes('inappropriate content') ||
        lower.includes('violates') && lower.includes('policy') ||
        lower.includes('responsible ai') ||
        lower.includes('content moderation')
    ) {
        return 'Your request was flagged. Please modify your input and try again.'
    }

    // ── Context / Token length ───────────────────────────────
    if (
        lower.includes('context length') ||
        lower.includes('context_length') ||
        lower.includes('token limit') ||
        lower.includes('token_limit') ||
        lower.includes('max tokens') ||
        lower.includes('max_tokens') ||
        lower.includes('too long') ||
        (lower.includes('exceed') && lower.includes('token')) ||
        (lower.includes('exceed') && lower.includes('context'))
    ) {
        return 'Your input is too long. Please shorten it and try again.'
    }

    // ── Timeout ──────────────────────────────────────────────
    if (
        lower.includes('timeout') ||
        lower.includes('timed out') ||
        lower.includes('time_out') ||
        lower.includes('response_time')
    ) {
        return 'Generation timed out. Try a shorter length or a different model.'
    }

    // ── Authentication / API key ─────────────────────────────
    if (
        lower.includes('api key') ||
        lower.includes('api_key') ||
        lower.includes('apikey') ||
        lower.includes('invalid key') ||
        lower.includes('authentication') ||
        lower.includes('unauthorized') ||
        lower.includes('unauthenticated') ||
        lower.includes('401') ||
        lower.includes('not authorized') ||
        lower.includes('access denied') && (lower.includes('key') || lower.includes('credential'))
    ) {
        return 'This AI provider is not configured. Please contact support.'
    }

    // ── Model not found / unavailable ────────────────────────
    if (
        lower.includes('model not found') ||
        lower.includes('model_not_found') ||
        lower.includes('model unavailable') ||
        lower.includes('model is not available') ||
        lower.includes('invalid model') ||
        lower.includes('unsupported model') ||
        lower.includes('no such model') ||
        lower.includes('deprecated')
    ) {
        return 'The selected model is unavailable. Please try a different one.'
    }

    // ── Network / Connection ─────────────────────────────────
    if (
        lower.includes('network error') ||
        lower.includes('network_error') ||
        lower.includes('connection refused') ||
        lower.includes('connection reset') ||
        lower.includes('connection_error') ||
        lower.includes('dns') && lower.includes('resolve') ||
        lower.includes('econnrefused') ||
        lower.includes('econnreset') ||
        lower.includes('enotfound') ||
        lower.includes('etimedout')
    ) {
        return 'Connection error. Please check your internet and try again.'
    }

    // ── Server errors ────────────────────────────────────────
    if (
        lower.includes('internal server error') ||
        lower.includes('internal_error') ||
        lower.includes('server error') ||
        lower.includes('server_error') ||
        lower.includes('bad gateway') ||
        lower.includes('gateway timeout') ||
        lower.includes('service unavailable') ||
        lower.includes('overloaded') ||
        lower.includes('500') || lower.includes('502') || lower.includes('503') || lower.includes('504')
    ) {
        return 'The AI service is temporarily unavailable. Please try again later.'
    }

    // ── Stream error from provider (catch-all for SSE errors) ─
    if (
        lower.includes('stream error') ||
        lower.includes('stream_error') ||
        lower.includes('sse error') ||
        lower.includes('event stream')
    ) {
        return 'Generation interrupted. Please try again.'
    }

    // ── Generic / unknown errors ─────────────────────────────
    // If it looks like a raw provider error (contains technical terms), sanitize it
    if (
        lower.includes('exception') ||
        lower.includes('stack trace') ||
        lower.includes('at ') && lower.includes('.php') || // stack frame
        lower.includes('nullpointerexception') ||
        lower.includes('runtimeexception')
    ) {
        return 'Something went wrong. Please try again or contact support.'
    }

    return message
}
