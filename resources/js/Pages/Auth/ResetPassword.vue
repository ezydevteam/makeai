<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps<{
    email?: string
    verified?: boolean
}>()

const verifyForm = useForm({
    email: props.email ?? '',
    code: '',
})

const resetForm = useForm({
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
})

const resendForm = useForm({
    email: props.email ?? '',
})

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
    if (verificationStarted.value || verifyForm.processing || verifyForm.code.length !== 6) {
        return
    }

    verificationStarted.value = true
    verifyForm.post(route('password.verify'), {
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
    resetForm.post(route('password.update'), {
        onFinish: () => resetForm.reset('password', 'password_confirmation'),
    })
}

const resendOtp = () => {
    if (resendCountdown.value > 0 || resendForm.processing) {
        return
    }

    resendForm.email = verifyForm.email
    resendForm.post(route('password.email'), {
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
    <Head :title="$t('Reset Password')" />

    <div class="auth-page">
        <div class="auth-glow">
            <div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/20 to-surface-950"></div>
        </div>

        <div class="w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white">
                    {{ verified ? $t('Set new password') : $t('Enter reset code') }}
                </h1>
                <p class="text-gray-500 mt-1 text-sm">
                    <template v-if="verified">
                        {{ $t('Your reset code is verified. Choose a strong new password.') }}
                    </template>
                    <template v-else>
                        {{ $t("We've sent a 6-digit OTP to :email.", { email: maskedEmail }) }}
                    </template>
                </p>
            </div>

            <div class="auth-card">
                <form class="space-y-5" @submit.prevent="verified ? submit() : verifyCode()">
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Email') }}</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-white">{{ maskedEmail || $t('Email hidden') }}</p>
                        <p v-if="verifyForm.errors.email || resetForm.errors.email" class="auth-error mt-1.5">
                            {{ verifyForm.errors.email || resetForm.errors.email }}
                        </p>
                    </div>

                    <div v-if="!verified">
                        <label class="auth-label mb-3 block">{{ $t('Reset Code') }}</label>
                        <div class="flex justify-center gap-3">
                            <input
                                v-for="(_, i) in 6"
                                :key="i"
                                :ref="(el) => { if (el) inputs[i] = el as HTMLInputElement }"
                                :value="digits[i]"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                :disabled="verifyForm.processing"
                                class="h-12 w-10 rounded-xl border border-white/10 bg-white/5 text-center text-lg font-bold text-white transition-all duration-200 focus:border-primary-500/50 focus:outline-none focus:ring-2 focus:ring-primary-500/50 disabled:cursor-not-allowed disabled:opacity-60"
                                @input="handleInput(i, $event)"
                                @keydown="handleKeydown(i, $event)"
                            />
                        </div>
                        <p v-if="verifyForm.errors.code" class="mt-2 text-center text-sm text-danger-500">{{ verifyForm.errors.code }}</p>
                        <p v-else-if="verifyForm.processing" class="mt-2 text-center text-sm text-primary-300">{{ $t('Verifying code...') }}</p>

                        <button
                            type="button"
                            :disabled="resendCountdown > 0 || resendForm.processing"
                            class="mt-5 flex w-full items-center justify-center rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-primary-300 transition-colors hover:border-primary-400/50/10 disabled:cursor-not-allowed disabled:text-gray-500 disabled:hover:border-white/10 disabled:hover:bg-transparent"
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
                            <label for="password" class="auth-label">{{ $t('New Password') }}</label>
                            <input
                                id="password"
                                v-model="resetForm.password"
                                type="password"
                                required
                                autofocus
                                autocomplete="new-password"
                                class="auth-input"
                                :placeholder="$t('Min 8 characters')"
                            />
                            <p v-if="resetForm.errors.password" class="auth-error">{{ resetForm.errors.password }}</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="auth-label">{{ $t('Confirm Password') }}</label>
                            <input
                                id="password_confirmation"
                                v-model="resetForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="auth-input"
                                placeholder="••••••••"
                            />
                        </div>
                    </template>

                    <button v-if="verified" type="submit" :disabled="resetForm.processing" class="auth-btn">
                        <span>{{ resetForm.processing ? $t('Resetting...') : $t('Reset Password') }}</span>
                    </button>

                    <p class="text-center text-sm text-gray-500">
                        <Link :href="route('password.request')" class="text-primary-400 hover:text-primary-300 transition-colors">
                            {{ $t('Request a new code') }}
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>
