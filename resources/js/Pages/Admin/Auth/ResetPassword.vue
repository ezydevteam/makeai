<script setup lang="ts">
import AuthCaptchaField from '@/Components/Auth/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

useFlashToasts()

const props = defineProps<{
    email?: string
    verified?: boolean
}>()

interface PageProps {
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
    appearanceAdminSettings?: Record<string, string>
}

const verifyForm = useForm({
    email: props.email ?? '',
    code: '',
    captcha_token: '',
})

const resetForm = useForm({
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
    captcha_token: '',
})

const resendForm = useForm({
    email: props.email ?? '',
})

const page = usePage()
const { isDark } = useTheme()
const { t } = useTranslate()
const branding = computed(() => (page.props as unknown as PageProps).branding)
const appearanceAdminSettings = computed(() => (page.props as unknown as PageProps).appearanceAdminSettings ?? {})
const appName = computed(() => String(branding.value?.site_name || page.props.appName || t('Application')))
const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const authLogo = computed(() => (isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)))
const captcha = computed<{ enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }>(() => (page.props as unknown as PageProps).captcha ?? { enabled: false, provider: 'recaptcha', site_key: '' })
const adminAuthStyle = computed(() => ({
    '--admin-auth-primary': appearanceAdminSettings.value.primary_color || appearanceAdminSettings.value.button_color || '',
    '--admin-auth-accent': appearanceAdminSettings.value.accent_color || appearanceAdminSettings.value.primary_color || '',
}))

const inputs = ref<HTMLInputElement[]>([])
const digits = ref(['', '', '', '', '', ''])
const verificationStarted = ref(false)
const resendCountdown = ref(60)
let resendTimer: number | null = null

const maskedEmail = computed(() => {
    const email = verifyForm.email || props.email || ''
    const [name = '', domain = ''] = email.split('@')
    const [domainName = '', domainTld = ''] = domain.split('.')
    const visibleName = name.slice(0, Math.min(2, name.length))
    const visibleTld = domainTld ? `.${domainTld}` : ''

    if (!email || !domain) {
        return ''
    }

    return `${visibleName}${'*'.repeat(Math.max(3, name.length - visibleName.length))}@${domainName.slice(0, 1)}***${visibleTld}`
})

const startResendCountdown = () => {
    resendCountdown.value = 60

    if (resendTimer) {
        window.clearInterval(resendTimer)
    }

    resendTimer = window.setInterval(() => {
        resendCountdown.value -= 1

        if (resendCountdown.value <= 0 && resendTimer) {
            window.clearInterval(resendTimer)
            resendTimer = null
        }
    }, 1000)
}

const verifyCode = () => {
    if (verificationStarted.value || verifyForm.processing || verifyForm.code.length !== 6) return
    if (captcha.value.enabled && !verifyForm.captcha_token) {
        verifyForm.setError('captcha_token', t('Please complete the captcha challenge.'))
        return
    }

    verificationStarted.value = true
    verifyForm.clearErrors('captcha_token')
    verifyForm.post(route('admin.password.verify'), {
        preserveScroll: true,
        onError: () => {
            verificationStarted.value = false
            digits.value = ['', '', '', '', '', '']
            verifyForm.code = ''
            inputs.value[0]?.focus()
        },
        onFinish: () => {
            if (!verifyForm.hasErrors) {
                verificationStarted.value = false
            }
        },
    })
}

const handleInput = (index: number, event: Event) => {
    const target = event.target as HTMLInputElement
    const value = target.value.replace(/\D/g, '')

    if (value.length > 1) {
        value.slice(0, 6).split('').forEach((char, i) => {
            digits.value[i] = char
        })
        verifyForm.code = digits.value.join('')
        inputs.value[Math.min(value.length - 1, 5)]?.focus()
        verifyCode()
        return
    }

    digits.value[index] = value
    verifyForm.code = digits.value.join('')

    if (value && index < 5) {
        inputs.value[index + 1]?.focus()
    }

    if (verifyForm.code.length === 6) {
        verifyCode()
    }
}

const handleKeydown = (index: number, event: KeyboardEvent) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus()
    }
}

const submit = () => {
    resetForm.post(route('admin.password.update'), {
        onFinish: () => resetForm.reset('password', 'password_confirmation'),
    })
}

const resendOtp = () => {
    if (resendCountdown.value > 0 || resendForm.processing) return

    resendForm.email = verifyForm.email
    resendForm.post(route('admin.password.email'), {
        preserveScroll: true,
        onSuccess: () => {
            digits.value = ['', '', '', '', '', '']
            verifyForm.code = ''
            verifyForm.clearErrors()
            startResendCountdown()
            inputs.value[0]?.focus()
        },
    })
}

watch(() => verifyForm.captcha_token, (token) => {
    if (!token) {
        return
    }

    verifyForm.clearErrors('captcha_token')

    if (!props.verified && verifyForm.code.length === 6) {
        verifyCode()
    }
})

onMounted(() => {
    if (!props.verified) {
        inputs.value[0]?.focus()
        startResendCountdown()
    }
})

onUnmounted(() => {
    if (resendTimer) {
        window.clearInterval(resendTimer)
    }
})
</script>

<template>
    <Head :title="$t('Reset Admin Password')" />

    <div class="auth-page admin-auth-page" :style="adminAuthStyle">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <Link :href="route('home')" class="mb-8 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                    <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                    <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                </Link>
                <h1 class="font-heading text-2xl font-bold text-gray-950 dark:text-white">
                    {{ verified ? $t('Set New Password') : $t('Verify OTP Code') }}
                </h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <template v-if="verified">
                        {{ $t('Your reset code is verified. Choose a strong new password.') }}
                    </template>
                    <template v-else>
                        {{ $t("A 6-digit OTP code was sent to :email", { email: maskedEmail }) }}
                    </template>
                </p>
            </div>

            <div class="auth-card p-8">
                <form class="space-y-5" @submit.prevent="verified ? submit() : verifyCode()">
                    <div  v-if="verifyForm.errors.email || resetForm.errors.email" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                        {{ verifyForm.errors.email || resetForm.errors.email }}
                    </div>

                    <div v-if="!verified">
                        <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('OTP Code') }}</label>
                        <div class="w-full flex justify-center gap-3">
                            <input
                                v-for="(_, i) in 6"
                                :key="i"
                                :ref="(el) => { if (el) inputs[i] = el as HTMLInputElement }"
                                :value="digits[i]"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                :disabled="verifyForm.processing"
                                class="h-12 w-12 rounded-xl border border-gray-200 bg-white text-center text-lg font-bold text-gray-900 transition-all duration-200 focus:border-primary-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-surface-900 dark:text-white"
                                @input="handleInput(i, $event)"
                                @keydown="handleKeydown(i, $event)"
                            />
                        </div>
                        <p v-if="verifyForm.errors.code" class="mt-2 text-center text-sm text-danger-500">{{ verifyForm.errors.code }}</p>
                        <p v-else-if="verifyForm.processing" class="mt-2 text-center text-sm text-primary-600 dark:text-primary-300">{{ $t('Verifying code...') }}</p>

                        <AuthCaptchaField
                            v-if="captcha.enabled"
                            v-model="verifyForm.captcha_token"
                            class="mt-5"
                            :config="captcha"
                            :error="verifyForm.errors.captcha_token"
                        />

                        <button
                            type="button"
                            :disabled="resendCountdown > 0 || resendForm.processing"
                            class="auth-social-button mt-5"
                            @click="resendOtp"
                        >
                            <span v-if="resendForm.processing">{{ $t('Sending...') }}</span>
                            <span v-else-if="resendCountdown > 0">{{ $t('Resend OTP in :seconds s', { seconds: resendCountdown }) }}</span>
                            <span v-else>{{ $t('Resend OTP') }}</span>
                        </button>
                        <p v-if="resendForm.errors.email" class="mt-2 text-center text-sm text-danger-500">{{ resendForm.errors.email }}</p>
                    </div>

                    <template v-else>
                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('New Password') }}</label>
                            <input
                                id="password"
                                v-model="resetForm.password"
                                type="password"
                                required
                                autofocus
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 transition-all duration-200 focus:border-primary-500 focus:outline-none dark:border-white/10 dark:bg-surface-900 dark:text-white"
                            />
                            <p v-if="resetForm.errors.password" class="mt-1.5 text-sm text-danger-500">{{ resetForm.errors.password }}</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Confirm Password') }}</label>
                            <input
                                id="password_confirmation"
                                v-model="resetForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 transition-all duration-200 focus:border-primary-500 focus:outline-none dark:border-white/10 dark:bg-surface-900 dark:text-white"
                            />
                        </div>

                        <AuthCaptchaField
                            v-if="captcha.enabled"
                            v-model="resetForm.captcha_token"
                            :config="captcha"
                            :error="resetForm.errors.captcha_token"
                        />
                    </template>

                    <button
                        v-if="verified"
                        type="submit"
                        :disabled="resetForm.processing"
                        class="auth-primary-button flex items-center justify-center gap-2"
                    >
                        <svg v-if="resetForm.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ resetForm.processing ? $t('Resetting...') : $t('Reset Password') }}</span>
                    </button>

                    <Link :href="route('admin.password.request')" class="auth-inline-link block text-center text-sm">
                        {{ $t('Request a new code') }}
                    </Link>
                </form>
            </div>
        </div>
    </div>
</template>
