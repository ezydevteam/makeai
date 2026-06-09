<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

interface SocialLoginProvider {
    provider: string
    label: string
    url: string
}

interface PageProps {
    socialLoginProviders?: SocialLoginProvider[]
    branding?: { site_name?: string }
    app?: { demo?: boolean; demo_credentials?: { admin: { email: string; password: string }; user: { email: string; password: string } } | null }
}

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const showPassword = ref(false)
const page = usePage()
const { t } = useTranslate()
const appName = computed(() => String((page.props as unknown as PageProps).branding?.site_name || t('Application')))
const isDemo = computed(() => (page.props as unknown as PageProps).app?.demo ?? false)
const demoCredentials = computed(() => (page.props as unknown as PageProps).app?.demo_credentials ?? null)
const socialProviders = computed(() => {
    const props = page.props as unknown as PageProps

    return props.socialLoginProviders ?? []
})

const submit = () => {
    form.post(route('login.attempt'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Sign In" />

    <div class="auth-page">
        <div class="auth-glow">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-50 via-primary-50/20 to-white"></div>
            <div class="absolute top-1/4 left-1/3 w-80 h-80 bg-primary-100/40 rounded-full blur-3xl"></div>
            <div class="absolute bottom-1/3 right-1/4 w-64 h-64 bg-accent-100/30 rounded-full blur-3xl"></div>
        </div>

        <div class="w-full max-w-md relative z-10">
            <!-- Logo -->
            <div class="text-center mb-10">
                <Link :href="route('home')" class="inline-flex items-center gap-3 mb-6 group">
                    <div class="w-14 h-14 bg-gray-900 rounded-2xl flex items-center justify-center shadow-2xl shadow-gray-900/20 group-hover:scale-105 transition-transform">
                        <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                    </div>
                </Link>
                <h1 class="text-3xl font-black text-gray-900 tracking-tight">{{ t('Welcome back') }}</h1>
                <p class="text-gray-500 mt-2 text-sm font-medium">{{ t('Log in to your :app account to continue', { app: appName }) }}</p>
            </div>

            <!-- Card -->
            <div class="auth-card">
                <div v-if="socialProviders.length > 0" class="mb-8 space-y-3">
                    <a
                        v-for="provider in socialProviders"
                        :key="provider.provider"
                        :href="provider.url"
                        class="flex w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-gray-700 shadow-sm transition hover:border-primary-300 hover:bg-primary-50"
                    >
                        {{ t('Continue with :provider', { provider: provider.label }) }}
                    </a>
                    <div class="flex items-center gap-3">
                        <span class="h-px flex-1 bg-gray-100"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ t('or') }}</span>
                        <span class="h-px flex-1 bg-gray-100"></span>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="email" class="auth-label">{{ t('Email Address') }}</label>
                        <input id="email" v-model="form.email" type="email" required autofocus autocomplete="email" class="auth-input" :placeholder="t('name@company.com')" />
                        <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="auth-label mb-0">{{ t('Password') }}</label>
                        </div>
                        <div class="relative">
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="auth-input pr-12" :placeholder="t('••••••••')" />
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="auth-error">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input v-model="form.remember" type="checkbox" class="w-4 h-4 rounded border-gray-300 bg-gray-50 text-primary-600 focus:ring-primary-500 shadow-sm" />
                            <span class="text-sm text-gray-500 group-hover:text-gray-900 transition-colors font-medium">{{ t('Remember me') }}</span>
                        </label>
                        <Link :href="route('password.request')" class="text-sm text-primary-600 hover:text-primary-700 font-bold transition-colors">{{ t('Forgot password?') }}</Link>
                    </div>

                    <button type="submit" :disabled="form.processing" class="auth-btn">
                        <svg v-if="form.processing" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        <span>{{ form.processing ? t('Signing in...') : t('Sign In') }}</span>
                    </button>
                </form>

                <div class="mt-10 pt-8 border-t border-gray-50 text-center">
                    <!-- Demo credentials -->
                    <div v-if="isDemo && demoCredentials" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-left dark:border-amber-800 dark:bg-amber-900/20">
                        <p class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 mb-3">{{ t('Demo Credentials') }}</p>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">{{ t('Admin') }}</span>
                                <code class="text-xs bg-amber-100 dark:bg-amber-800 px-2 py-0.5 rounded font-mono text-amber-800 dark:text-amber-200">{{ demoCredentials.admin.email }}</code>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">{{ t('User') }}</span>
                                <code class="text-xs bg-amber-100 dark:bg-amber-800 px-2 py-0.5 rounded font-mono text-amber-800 dark:text-amber-200">{{ demoCredentials.user.email }}</code>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">{{ t('Password') }}</span>
                                <code class="text-xs bg-amber-100 dark:bg-amber-800 px-2 py-0.5 rounded font-mono text-amber-800 dark:text-amber-200">{{ demoCredentials.admin.password }}</code>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 font-medium">
                        {{ t('Don\'t have an account?') }}
                        <Link :href="route('register')" class="text-primary-600 hover:text-primary-700 font-black transition-colors ml-1">{{ t('Create an account') }}</Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
