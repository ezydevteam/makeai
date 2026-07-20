<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

interface CaptchaConfig {
    enabled: boolean
    provider: 'recaptcha' | 'hcaptcha'
    site_key: string
}

interface Props {
    modelValue: string
    config: CaptchaConfig
    error?: string
}

interface CaptchaWidget {
    render: (container: HTMLElement, options: Record<string, unknown>) => number | string
    reset?: (widgetId?: number | string) => void
}

declare global {
    interface Window {
        grecaptcha?: CaptchaWidget
        hcaptcha?: CaptchaWidget
        __makeAiCaptchaScriptPromises?: Record<string, Promise<void>>
    }
}

const props = defineProps<Props>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const { isDark } = useTheme()
const { t } = useTranslate()

const container = ref<HTMLElement | null>(null)
const loading = ref(false)
const renderFailed = ref(false)
const widgetId = ref<number | string | null>(null)

const scriptUrl = computed(() => props.config.provider === 'hcaptcha'
    ? 'https://js.hcaptcha.com/1/api.js?render=explicit'
    : 'https://www.google.com/recaptcha/api.js?render=explicit')
const theme = computed(() => isDark.value ? 'dark' : 'light')

const getProviderApi = () => {
    if (typeof window === 'undefined') {
        return undefined
    }

    return props.config.provider === 'hcaptcha' ? window.hcaptcha : window.grecaptcha
}

const ensureScript = async () => {
    if (typeof window === 'undefined') {
        return
    }

    if (getProviderApi()) {
        return
    }

    window.__makeAiCaptchaScriptPromises ??= {}
    const existingPromise = window.__makeAiCaptchaScriptPromises[props.config.provider]
    if (existingPromise) {
        return existingPromise
    }

    window.__makeAiCaptchaScriptPromises[props.config.provider] = new Promise<void>((resolve, reject) => {
        const existingScript = document.querySelector<HTMLScriptElement>(`script[src="${scriptUrl.value}"]`)
        if (existingScript) {
            if (getProviderApi()) {
                resolve()
                return
            }

            existingScript.addEventListener('load', () => resolve(), { once: true })
            existingScript.addEventListener('error', () => reject(new Error('captcha_script_error')), { once: true })
            return
        }

        const script = document.createElement('script')
        script.src = scriptUrl.value
        script.async = true
        script.defer = true
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('captcha_script_error'))
        document.head.appendChild(script)
    })

    return window.__makeAiCaptchaScriptPromises[props.config.provider]
}

const resetToken = () => {
    emit('update:modelValue', '')
}

const resetWidget = () => {
    if (widgetId.value === null) {
        resetToken()
        return
    }

    getProviderApi()?.reset?.(widgetId.value)
    widgetId.value = null
    resetToken()
}

const renderWidget = async () => {
    if (!props.config.enabled || !props.config.site_key) {
        return
    }

    loading.value = true
    renderFailed.value = false

    try {
        await ensureScript()
        await nextTick()

        const providerApi = getProviderApi()

        if (!container.value || !providerApi) {
            throw new Error('captcha_unavailable')
        }

        container.value.innerHTML = ''
        widgetId.value = providerApi.render(container.value, {
            sitekey: props.config.site_key,
            theme: theme.value,
            callback: (token: string) => emit('update:modelValue', token),
            'expired-callback': resetToken,
            'error-callback': resetToken,
        })
    } catch {
        renderFailed.value = true
        resetToken()
    } finally {
        loading.value = false
    }
}

watch(() => props.config.provider, async () => {
    resetWidget()
    await renderWidget()
})

watch(theme, async () => {
    if (!props.config.enabled) {
        return
    }

    resetWidget()
    await renderWidget()
})

onMounted(async () => {
    await renderWidget()
})

onUnmounted(() => {
    resetToken()
})
</script>

<template>
    <div v-if="config.enabled" class="space-y-2">
        <div
            class="rounded-2xl border border-gray-200 bg-white/80 p-3 dark:border-white/10 dark:bg-white/[0.03]"
            :class="{ 'border-danger-400/70': Boolean(error) }"
        >
            <div ref="container" class="flex min-h-[78px] items-center justify-center overflow-hidden rounded-xl"></div>
            <p v-if="loading" class="mt-2 text-center text-sm text-gray-500 dark:text-gray-400">{{ t('Loading captcha...') }}</p>
            <p v-else-if="renderFailed" class="mt-2 text-center text-sm text-danger-500">{{ t('Captcha could not be loaded. Please refresh and try again.') }}</p>
        </div>
        <p v-if="error" class="auth-error">{{ error }}</p>
    </div>
</template>
