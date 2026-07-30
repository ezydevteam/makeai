<script setup lang="ts">
import AuthCaptchaField from '@/Components/Utility/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

useFlashToasts()

interface DemoCredential {
    email: string
    password: string
}

interface PageProps {
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
    appearanceAdminSettings?: Record<string, string>
    app?: { demo?: boolean; demo_credentials?: { admin?: DemoCredential | null; user?: DemoCredential | null } | null }
}

const page = usePage()
const { isDark } = useTheme()
const { t } = useTranslate()

const form = useForm({
    email: '',
    password: '',
    remember: false,
    captcha_token: '',
})

const showPassword = ref(false)
const branding = computed(() => (page.props as unknown as PageProps).branding)
const appearanceAdminSettings = computed(() => (page.props as unknown as PageProps).appearanceAdminSettings ?? {})
const appName = computed(() => String(branding.value?.site_name || page.props.appName || t('Application')))
const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const authLogo = computed(() => (isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)))
const captcha = computed<{ enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }>(() => (page.props as unknown as PageProps).captcha ?? { enabled: false, provider: 'recaptcha', site_key: '' })

// Demo sign-in hint. The shared prop carries both pairs, but only the admin one belongs
// on this screen — and it is null unless DEMO_ADMIN_PASSWORD is actually configured, so
// there is nothing to publish on an install that never set one.
const isDemo = computed(() => (page.props as unknown as PageProps).app?.demo ?? false)
const demoAdmin = computed(() => (page.props as unknown as PageProps).app?.demo_credentials?.admin ?? null)
const adminAuthStyle = computed(() => ({
    '--admin-auth-primary': appearanceAdminSettings.value.primary_color || appearanceAdminSettings.value.button_color || '',
    '--admin-auth-accent': appearanceAdminSettings.value.accent_color || appearanceAdminSettings.value.primary_color || '',
}))

const submit = () => {
    form.post(route('admin.login.attempt'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head :title="$t('Admin Login')" />

    <div class="auth-page admin-auth-page" :style="adminAuthStyle">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link :href="route('home')" class="mb-5 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                    <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                    <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                </Link>
                <h1 class="font-heading text-2xl font-bold text-gray-950 dark:text-white">{{ $t('Welcome back! Sign In') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Enter your details to manage :app', { app: $page.props.appName }) }}</p>
            </div>

            <div class="auth-card p-8">
                <form @submit.prevent="submit" class="space-y-5">
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

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Password') }}</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 pr-12 text-gray-900 transition-all duration-200 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none dark:border-white/10 dark:bg-surface-900 dark:text-white dark:placeholder:text-gray-500"
                                :placeholder="$t('••••••••')"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            >
                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-danger-500">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded"
                            />
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('Remember me') }}</span>
                        </label>
                        <Link :href="route('admin.password.request')" class="text-sm font-medium text-primary-600 transition-colors hover:text-primary-700 dark:text-primary-300 dark:hover:text-primary-200">
                            {{ $t('Forgot password?') }}
                        </Link>
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
                        <svg v-if="form.processing" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ form.processing ? $t('Signing in...') : $t('Sign In') }}</span>
                    </button>
                </form>

                <div v-if="isDemo && demoAdmin" class="mt-6 rounded-xl border border-amber-200 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:text-amber-200">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span>{{ $t('Demo') }}:</span>
                        <code class="font-mono text-xs">{{ demoAdmin.email }}</code>
                        <code class="font-mono text-xs">{{ demoAdmin.password }}</code>
                    </div>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
                &copy; 2026 {{ $page.props.appName }}. {{ $t('Admin access only.') }}
            </p>
        </div>
    </div>
</template>
