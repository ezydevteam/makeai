<script setup lang="ts">
import AuthCaptchaField from '@/Components/Auth/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

useFlashToasts()

interface PageProps {
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
}

const form = useForm({ email: '', captcha_token: '' })
const page = usePage()
const { isDark } = useTheme()
const { t } = useTranslate()
const branding = computed(() => (page.props as unknown as PageProps).branding)
const appName = computed(() => String(branding.value?.site_name || t('Application')))
const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const authLogo = computed(() => (isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)))
const captcha = computed<{ enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }>(() => (page.props as unknown as PageProps).captcha ?? { enabled: false, provider: 'recaptcha', site_key: '' })

const submit = () => form.post(route('password.email'))
</script>

<template>
    <Head :title="$t('Forgot Password')" />

    <div class="auth-page p-4 lg:p-8">
        <div class="mx-auto max-w-md">
            <div class="auth-card mx-auto rounded-2xl border border-gray-200 bg-white px-6 py-7 shadow-none dark:border-white/10 dark:bg-surface-950 sm:px-7">
                <div class="auth-brand-block mb-8">
                    <Link :href="route('home')" class="mb-8 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                        <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                        <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                    </Link>

                    <h1 class="font-heading text-[2rem] font-bold tracking-tight text-gray-950 dark:text-white">{{ $t('Reset password') }}</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm">{{ $t('Enter your email address to get a 6-digit recovery code.') }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="auth-floating-group">
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            placeholder=" "
                            :class="['auth-floating-input', { 'is-invalid': Boolean(form.errors.email) }]"
                        >
                        <label for="email" class="auth-floating-label">{{ $t('E-mail') }}</label>
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-300 dark:text-gray-500">
                            <i class="ti ti-mail text-[1.1rem]"></i>
                        </span>
                    </div>
                    <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>

                    <AuthCaptchaField
                        v-if="captcha.enabled"
                        v-model="form.captcha_token"
                        :config="captcha"
                        :error="form.errors.captcha_token"
                    />

                    <button type="submit" :disabled="form.processing" class="auth-primary-button">
                        <span>{{ form.processing ? $t('Sending...') : $t('Send Reset Code') }}</span>
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    <Link :href="route('login')" class="auth-inline-link">
                        {{ $t('Back to login') }}
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
