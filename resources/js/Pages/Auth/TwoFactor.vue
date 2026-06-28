<script setup lang="ts">
import AuthCaptchaField from '@/Components/Auth/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

useFlashToasts()

interface PageProps {
    twoFactorMethod?: string
    userOtpExpiresAt?: string | null
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
}

const page = usePage()
const props = page.props as unknown as PageProps
const { isDark } = useTheme()
const { t } = useTranslate()

const isOtp = computed(() => props.twoFactorMethod === 'otp')
const branding = computed(() => props.branding)
const appName = computed(() => String(branding.value?.site_name || t('Application')))
const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const authLogo = computed(() => (isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)))
const captcha = computed<{ enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }>(() => props.captcha ?? { enabled: false, provider: 'recaptcha', site_key: '' })

const form = useForm({
    code: '',
    captcha_token: '',
})

const submit = () => {
    form.post(route('two-factor.verify'), {
        onFinish: () => form.reset('code'),
    })
}
</script>

<template>
    <Head :title="$t('Two-Factor Verification')" />

    <div class="auth-page p-4 lg:p-8">
        <div class="mx-auto max-w-md">
            <div class="auth-card mx-auto rounded-2xl border border-gray-200 bg-white px-6 py-7 shadow-none dark:border-white/10 dark:bg-surface-950 sm:px-7">
                <div class="auth-brand-block mb-8">
                    <Link :href="route('home')" class="mb-8 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                        <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                        <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                    </Link>
                    <h1 class="mt-2 font-heading text-[2rem] font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $t('2FA Verification') }}
                    </h1>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">
                        <template v-if="isOtp">
                            {{ $t('Enter the 6-digit verification code sent to your email address to finish signing in.') }}
                        </template>
                        <template v-else>
                            {{ $t('Enter the current code from your authenticator app or use one of your recovery codes.') }}
                        </template>
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="auth-floating-group">
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            required
                            autofocus
                            :inputmode="isOtp ? 'numeric' : 'text'"
                            autocomplete="one-time-code"
                            placeholder=" "
                            :class="['auth-floating-input text-center font-semibold tracking-[0.28em]', { 'is-invalid': Boolean(form.errors.code) }]"
                            :maxlength="isOtp ? 6 : 32"
                        >
                        <label for="code" class="auth-floating-label">
                            {{ isOtp ? $t('Verification Code') : $t('Authenticator or Recovery Code') }}
                        </label>
                    </div>
                    <p v-if="form.errors.code" class="auth-error">{{ form.errors.code }}</p>

                    <AuthCaptchaField
                        v-if="captcha.enabled"
                        v-model="form.captcha_token"
                        :config="captcha"
                        :error="form.errors.captcha_token"
                    />

                    <button type="submit" :disabled="form.processing || form.code.length < 6" class="auth-primary-button">
                        <span v-if="!form.processing">{{ $t('Verify Code') }}</span>
                        <span v-else class="inline-flex items-center gap-2">
                            <i class="ti ti-loader-2 animate-spin text-lg"></i>
                            {{ $t('Verifying...') }}
                        </span>
                    </button>

                    <p v-if="isOtp" class="text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("Didn't receive the code? Check your spam folder and try again.") }}
                    </p>

                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        <Link :href="route('login')" class="auth-inline-link">
                            {{ $t('Back to login') }}
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>
