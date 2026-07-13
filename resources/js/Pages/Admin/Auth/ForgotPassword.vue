<script setup lang="ts">
import AuthCaptchaField from '@/Components/Utility/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

useFlashToasts()

interface PageProps {
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
    appearanceAdminSettings?: Record<string, string>
}

const page = usePage()
const { isDark } = useTheme()
const { t } = useTranslate()

const form = useForm({
    email: '',
    captcha_token: '',
})

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

const submit = () => {
    form.post(route('admin.password.email'))
}
</script>

<template>
    <Head :title="$t('Admin Password Reset')" />

    <div class="auth-page admin-auth-page" :style="adminAuthStyle">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <Link :href="route('home')" class="mb-8 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                    <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                    <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                </Link>
                <h1 class="font-heading text-2xl font-bold text-gray-950 dark:text-white">{{ $t('Reset Password') }}</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('Enter your email address to reset your password.') }}</p>
            </div>

            <div class="auth-card p-8">
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Email') }}</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 transition-all duration-200 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none dark:border-white/10 dark:bg-surface-900 dark:text-white dark:placeholder:text-gray-500"
                            :placeholder="$t('admin@example.com')"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-danger-500">{{ form.errors.email }}</p>
                    </div>

                    <AuthCaptchaField
                        v-if="captcha.enabled"
                        v-model="form.captcha_token"
                        :config="captcha"
                        :error="form.errors.captcha_token"
                    />

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="auth-primary-button flex items-center justify-center gap-2"
                    >
                        <svg v-if="form.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ form.processing ? $t('Sending...') : $t('Send Reset Code') }}</span>
                    </button>

                    <Link :href="route('admin.login')" class="auth-inline-link block text-center text-sm">
                        {{ $t('Back to login') }}
                    </Link>
                </form>
            </div>
        </div>
    </div>
</template>
