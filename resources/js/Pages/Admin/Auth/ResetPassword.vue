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
    if (resendCountdown.value > 0 || resendForm.processing) {
        return
    }

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

    <div class="flex min-h-screen items-center justify-center bg-surface-950 p-4">
        <div class="fixed inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/30 to-surface-950"></div>
            <div class="absolute left-1/4 top-1/3 h-64 w-64 rounded-full bg-primary-600/8 blur-3xl"></div>
        </div>

        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-500/10">
                    <i class="ti ti-shield-lock text-3xl text-primary-400"></i>
                </div>
                <h1 class="font-heading text-2xl font-bold text-white">
                    {{ verified ? $t('Set New Password') : $t('Enter Reset Code') }}
                </h1>
                <p class="mt-2 text-sm text-gray-500">
                    <template v-if="verified">
                        {{ $t('Your reset code is verified. Choose a strong new password.') }}
                    </template>
                    <template v-else>
                        {{ $t("We've sent a 6-digit OTP to :email.", { email: maskedEmail }) }}
                    </template>
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-xl">
                <form class="space-y-5" @submit.prevent="verified ? submit() : verifyCode()">
                    <div class="rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $t('Admin email') }}</p>
                        <p class="mt-1 font-mono text-sm font-semibold text-white">{{ maskedEmail || $t('Email hidden') }}</p>
                        <p v-if="verifyForm.errors.email || resetForm.errors.email" class="mt-1.5 text-sm text-danger-500">
                            {{ verifyForm.errors.email || resetForm.errors.email }}
                        </p>
                    </div>

                    <div v-if="!verified">
                        <label class="mb-3 block text-sm font-medium text-gray-300">{{ $t('Reset Code') }}</label>
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
                            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-300">{{ $t('New Password') }}</label>
                            <input
                                id="password"
                                v-model="resetForm.password"
                                type="password"
                                required
                                autofocus
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white transition-all duration-200 focus:border-primary-500/50 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                            />
                            <p v-if="resetForm.errors.password" class="mt-1.5 text-sm text-danger-500">{{ resetForm.errors.password }}</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-300">{{ $t('Confirm Password') }}</label>
                            <input
                                id="password_confirmation"
                                v-model="resetForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white transition-all duration-200 focus:border-primary-500/50 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                            />
                        </div>
                    </template>

                    <button
                        v-if="verified"
                        type="submit"
                        :disabled="resetForm.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-600 py-3 font-semibold text-white shadow-lg shadow-primary-600/25 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary-500/30 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0"
                    >
                        <svg v-if="resetForm.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ resetForm.processing ? $t('Resetting...') : $t('Reset Password') }}</span>
                    </button>

                    <Link :href="route('admin.password.request')" class="block text-center text-sm text-gray-500 transition-colors hover:text-gray-300">
                        {{ $t('Request a new code') }}
                    </Link>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-heading {
    font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
}
</style>
