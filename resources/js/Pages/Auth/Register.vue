<script setup lang="ts">
import AuthCaptchaField from '@/Components/Auth/AuthCaptchaField.vue'
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'

useFlashToasts()

interface SocialLoginProvider {
    provider: string
    label: string
    url: string
    display_mode?: 'icon' | 'icon-label'
}

interface DemoCredentials {
    admin: { email: string; password: string }
    user: { email: string; password: string }
}

interface PageProps {
    socialLoginProviders?: SocialLoginProvider[]
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    app?: { demo?: boolean; demo_credentials?: DemoCredentials | null }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
}

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    captcha_token: '',
})

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)
const page = usePage()
const { t } = useTranslate()
const { isDark } = useTheme()
const branding = computed(() => (page.props as unknown as PageProps).branding)
const appName = computed(() => String(branding.value?.site_name || t('Application')))
const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const authLogo = computed(() => (isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)))
const isDemo = computed(() => (page.props as unknown as PageProps).app?.demo ?? false)
const demoCredentials = computed(() => (page.props as unknown as PageProps).app?.demo_credentials ?? null)
const socialProviders = computed(() => ((page.props as unknown as PageProps).socialLoginProviders ?? []))
const captcha = computed<{ enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }>(() => (page.props as unknown as PageProps).captcha ?? { enabled: false, provider: 'recaptcha', site_key: '' })
const socialProviderIcons: Record<string, string> = {
    github: 'ti ti-brand-github',
    reddit: 'ti ti-brand-reddit',
    twitter: 'ti ti-brand-x',
}
const socialProviderIconColors: Record<string, string> = {
    google: 'text-[#ea4335]',
    github: 'text-[#111827] dark:text-white',
    facebook: 'text-[#1877f2]',
    reddit: 'text-[#ff4500]',
    twitter: 'text-[#111827] dark:text-white',
}

const submit = () => {
    form.post(route('register.attempt'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head :title="t('Create Account')" />

    <div class="auth-page p-4 lg:p-8">
        <div class="auth-shell">
            <div class="auth-split-card grid lg:grid-cols-2 lg:rounded-2xl lg:overflow-hidden">
                <section class="auth-visual-panel relative hidden lg:flex lg:min-h-[42rem] lg:items-center lg:justify-center lg:px-6 xl:px-8">
                    <div class="auth-art-surface"></div>
                    <div class="auth-side-cta">
                        <p class="text-sm font-medium text-white/80">{{ t('Already joined?') }}</p>
                        <h2 class="mt-2 font-heading text-2xl font-bold leading-tight text-white">
                            {{ t('Sign in and continue with your workspace, saved tools, and account settings.') }}
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-white/80">
                            {{ t('Pick up where you left off with a clean dashboard and your existing content ready to go.') }}
                        </p>
                        <Link :href="route('login')" class="auth-side-cta-button mt-5">
                            <i class="ti ti-user-check mr-2"></i>
                            {{ t('Sign In') }}
                        </Link>
                    </div>
                </section>

                <section class="auth-form-panel flex items-center px-6 py-10 sm:px-8 lg:min-h-[42rem] lg:px-12 xl:px-16">
                    <div class="auth-form-inner mx-auto">
                        <div class="auth-brand-block mb-8">
                            <Link :href="route('home')" class="mb-8 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                                <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                                <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                            </Link>

                            <h1 class="font-heading text-[2.25rem] font-bold tracking-tight text-gray-950 dark:text-white">
                                {{ t('Create Account') }}
                            </h1>
                            <p class="mt-2 text-base text-gray-500 dark:text-gray-400 max-w-sm">{{ t('Start your journey by providing basic info.') }}</p>
                        </div>

                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="auth-floating-group">
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    placeholder=" "
                                    :class="['auth-floating-input', { 'is-invalid': Boolean(form.errors.name) }]"
                                >
                                <label for="name" class="auth-floating-label">{{ t('Full Name') }}</label>
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-300 dark:text-gray-500">
                                    <i class="ti ti-user text-[1.15rem]"></i>
                                </span>
                                <p v-if="form.errors.name" class="auth-error">{{ form.errors.name }}</p>
                            </div>

                            <div class="auth-floating-group">
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    placeholder=" "
                                    :class="['auth-floating-input', { 'is-invalid': Boolean(form.errors.email) }]"
                                >
                                <label for="email" class="auth-floating-label">{{ t('E-mail') }}</label>
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-300 dark:text-gray-500">
                                    <i class="ti ti-mail text-[1.15rem]"></i>
                                </span>
                                <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>
                            </div>

                            <div class="auth-floating-group">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    placeholder=" "
                                    :class="['auth-floating-input', { 'is-invalid': Boolean(form.errors.password) }]"
                                >
                                <label for="password" class="auth-floating-label">{{ t('Password') }}</label>
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-4 flex items-center text-gray-300 transition-colors hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="showPassword ? t('Hide password') : t('Show password')"
                                    @click="showPassword = !showPassword"
                                >
                                    <i :class="showPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-[1.15rem]"></i>
                                </button>
                                <p v-if="form.errors.password" class="auth-error">{{ form.errors.password }}</p>
                            </div>

                            <div class="auth-floating-group">
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    required
                                    autocomplete="new-password"
                                    placeholder=" "
                                    class="auth-floating-input"
                                >
                                <label for="password_confirmation" class="auth-floating-label">{{ t('Confirm Password') }}</label>
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-4 flex items-center text-gray-300 transition-colors hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-300"
                                    :aria-label="showPasswordConfirmation ? t('Hide password') : t('Show password')"
                                    @click="showPasswordConfirmation = !showPasswordConfirmation"
                                >
                                    <i :class="showPasswordConfirmation ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-[1.15rem]"></i>
                                </button>
                            </div>

                            <AuthCaptchaField
                                v-if="captcha.enabled"
                                v-model="form.captcha_token"
                                :config="captcha"
                                :error="form.errors.captcha_token"
                            />

                            <button type="submit" :disabled="form.processing" class="auth-primary-button auth-primary-button--reverse">
                                <span v-if="!form.processing">{{ t('Sign Up') }}</span>
                                <span v-else class="inline-flex items-center gap-2">
                                    <i class="ti ti-loader-2 animate-spin text-lg"></i>
                                    {{ t('Creating account...') }}
                                </span>
                            </button>
                        </form>

                        <div v-if="socialProviders.length > 0" class="mt-10">
                            <div class="auth-divider">{{ t('or sign up with') }}</div>
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <a
                                    v-for="provider in socialProviders"
                                    :key="provider.provider"
                                    :href="provider.url"
                                    class="auth-social-button"
                                    :aria-label="t('Continue with :provider', { provider: provider.label })"
                                >
                                    <span class="auth-social-icon">
                                        <svg v-if="provider.provider === 'google'" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                            <path fill="#4285F4" d="M21.805 12.23c0-.75-.067-1.47-.191-2.16H12v4.09h5.498a4.7 4.7 0 0 1-2.04 3.08v2.56h3.3c1.93-1.78 3.047-4.4 3.047-7.57Z" />
                                            <path fill="#34A853" d="M12 22c2.76 0 5.07-.91 6.76-2.47l-3.3-2.56c-.91.61-2.08.97-3.46.97-2.66 0-4.91-1.8-5.72-4.22H2.87v2.64A10 10 0 0 0 12 22Z" />
                                            <path fill="#FBBC05" d="M6.28 13.72A5.99 5.99 0 0 1 5.96 12c0-.6.11-1.18.32-1.72V7.64H2.87A10 10 0 0 0 2 12c0 1.61.39 3.13 1.08 4.36l3.2-2.64Z" />
                                            <path fill="#EA4335" d="M12 6.04c1.5 0 2.84.52 3.9 1.53l2.93-2.93C17.06 2.98 14.76 2 12 2A10 10 0 0 0 3.08 7.64l3.2 2.64C7.09 7.84 9.34 6.04 12 6.04Z" />
                                        </svg>
                                        <svg v-else-if="provider.provider === 'facebook'" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                                            <path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07c0 6.02 4.39 11.02 10.12 11.93v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.88v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.09 24 18.09 24 12.07Z" />
                                            <path fill="#FFFFFF" d="M16.67 15.56l.53-3.49h-3.32V9.81c0-.95.46-1.88 1.95-1.88h1.51V4.96s-1.37-.24-2.68-.24c-2.74 0-4.53 1.67-4.53 4.69v2.66H7.08v3.49h3.04V24a12.2 12.2 0 0 0 3.75 0v-8.44h2.8Z" />
                                        </svg>
                                        <i v-else :class="[socialProviderIcons[provider.provider] || 'ti ti-login-2', socialProviderIconColors[provider.provider] || 'text-primary-600 dark:text-primary-300']"></i>
                                    </span>
                                    <span>{{ provider.label }}</span>
                                </a>
                            </div>
                        </div>

                        <div v-if="isDemo && demoCredentials" class="mt-8 rounded-xl border border-amber-200 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:text-amber-200">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <span>{{ t('Demo') }}:</span>
                                <code class="font-mono text-xs">{{ demoCredentials.user.email }}</code>
                                <code class="font-mono text-xs">{{ demoCredentials.admin.password }}</code>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
