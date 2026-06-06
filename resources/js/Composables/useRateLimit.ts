import { ref, computed, onUnmounted } from 'vue'

export function useRateLimit() {
    const limit = ref(60)
    const remaining = ref(60)
    const resetAt = ref<number | null>(null)
    const retryAfter = ref<number | null>(null)
    const isLimited = ref(false)
    const countdown = ref(0)
    let timer: ReturnType<typeof setInterval> | null = null

    const parseHeaders = (headers: Headers) => {
        const hLimit = parseInt(headers.get('X-RateLimit-Limit') || '60', 10)
        const hRemaining = parseInt(headers.get('X-RateLimit-Remaining') || '60', 10)
        const hResetAt = parseInt(headers.get('X-RateLimit-Reset') || '0', 10)
        const hRetryAfter = parseInt(headers.get('Retry-After') || '0', 10)

        limit.value = hLimit
        remaining.value = hRemaining
        resetAt.value = hResetAt || null
        retryAfter.value = hRetryAfter || null
        isLimited.value = hRemaining <= 0

        if (hRetryAfter > 0) {
            startCountdown(hRetryAfter)
        }
    }

    const startCountdown = (seconds: number) => {
        stopCountdown()
        countdown.value = seconds
        timer = setInterval(() => {
            countdown.value--
            if (countdown.value <= 0) {
                stopCountdown()
                isLimited.value = false
                retryAfter.value = null
                remaining.value = limit.value
            }
        }, 1000)
    }

    const stopCountdown = () => {
        if (timer) {
            clearInterval(timer)
            timer = null
        }
    }

    const formattedCountdown = computed(() => {
        const mins = Math.floor(countdown.value / 60)
        const secs = countdown.value % 60
        return `${mins}:${secs.toString().padStart(2, '0')}`
    })

    const isNearLimit = computed(() => remaining.value <= 5 && remaining.value > 0)

    onUnmounted(() => stopCountdown())

    return {
        limit,
        remaining,
        resetAt,
        retryAfter,
        isLimited,
        isNearLimit,
        countdown,
        formattedCountdown,
        parseHeaders,
        startCountdown,
        stopCountdown,
    }
}
