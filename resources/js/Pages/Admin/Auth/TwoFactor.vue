<script setup lang="ts">
import AuthCaptchaField from '@/Components/Auth/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'
import { useTranslate } from '@/Composables/useTranslate'

useFlashToasts()

const props = defineProps<{
    method?: 'totp' | 'email'
}>()

interface PageProps {
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
    appearanceAdminSettings?: Record<string, string>
}

const page = usePage()
const { isDark } = useTheme()
const { t } = useTranslate()

const form = useForm({
    code: '',
    captcha_token: '',
})

const inputs = ref<HTMLInputElement[]>([])
const digits = ref(['', '', '', '', '', ''])
const isTotp = computed(() => props.method === 'totp')
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
const verificationTitle = computed(() => isTotp.value ? t('Two-Factor Verification') : t('Email Verification'))
const verificationDescription = computed(() => isTotp.value
    ? t('Confirm your admin login with an authenticator app code or a recovery code.')
    : t('Enter the 6-digit verification code sent to your admin email address.'))
const entryLabel = computed(() => isTotp.value ? t('Authenticator or recovery code') : t('Verification code'))
const helperItems = computed(() => isTotp.value
    ? [
        t('Open your authenticator app and enter the latest 6-digit code.'),
        t('If your device is unavailable, use one of your saved recovery codes.'),
        t('Codes refresh quickly, so submit the newest one if a previous code fails.'),
    ]
    : [
        t('Use the latest email we sent to your admin inbox.'),
        t('Check spam or promotions if the code does not appear right away.'),
        t('Request a fresh login code if the current one expires before you submit it.'),
    ])
const codeInputClass = 'border-2 border-gray-300 bg-white shadow-sm shadow-gray-200/50 focus:border-primary-500 focus:ring-4 focus:ring-primary-100 dark:border-surface-600 dark:bg-surface-950 dark:shadow-black/20 dark:focus:border-primary-400 dark:focus:ring-primary-900/30'

const handleInput = (index: number, event: Event) => {
    const target = event.target as HTMLInputElement
    const value = target.value.replace(/\D/g, '')

    if (value.length > 1) {
        // Paste handler
        const chars = value.split('').slice(0, 6)
        chars.forEach((char, i) => {
            if (i < 6) digits.value[i] = char
        })
        form.code = digits.value.join('')
        const lastIndex = Math.min(chars.length - 1, 5)
        inputs.value[lastIndex]?.focus()
        return
    }

    digits.value[index] = value
    form.code = digits.value.join('')

    if (value && index < 5) {
        inputs.value[index + 1]?.focus()
    }
}

const handleKeydown = (index: number, event: KeyboardEvent) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus()
    }
}

const submit = () => {
    form.post(route('admin.2fa.verify'))
}

onMounted(() => {
    inputs.value[0]?.focus()
})
</script>

<template>
    <Head :title="$t('Two-Factor Authentication')" />

    <div class="auth-page admin-auth-page" :style="adminAuthStyle">
        <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <Link :href="route('home')" class="mb-4 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                                    <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                                    <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                                </Link>
                                <h1 class="font-heading text-2xl font-bold text-gray-950 dark:text-white">{{ verificationTitle }}</h1>
                                <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                    {{ verificationDescription }}
                                </p>
                            </div>

                            <span class="inline-flex w-fit items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                {{ isTotp ? $t('Authenticator') : $t('Email Code') }}
                            </span>
                        </div>
                    </div>

                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div v-if="isTotp">
                                <label for="code" class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ entryLabel }}</label>
                                <input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    required
                                    autofocus
                                    inputmode="text"
                                    autocomplete="one-time-code"
                                    :class="['w-full rounded-xl px-4 py-3 text-center text-lg font-bold tracking-widest text-gray-900 transition-all duration-200 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500', codeInputClass]"
                                    :placeholder="$t('123456 or ABCDE-FGHIJ')"
                                />
                            </div>

                            <div v-else>
                                <div class="mb-1.5 flex items-center justify-between gap-3">
                                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ entryLabel }}</label>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('6 digits required') }}</span>
                                </div>
                                <div class="flex flex-wrap justify-center gap-3 sm:flex-nowrap sm:justify-start">
                                    <input
                                        v-for="(_, i) in 6"
                                        :key="i"
                                        :ref="(el) => { if (el) inputs[i] = el as HTMLInputElement }"
                                        :value="digits[i]"
                                        @input="handleInput(i, $event)"
                                        @keydown="handleKeydown(i, $event)"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="6"
                                        :class="['h-14 w-12 rounded-xl text-center text-xl font-bold text-gray-900 transition-all duration-200 focus:outline-none dark:text-white', codeInputClass]"
                                    />
                                </div>
                            </div>

                            <p v-if="form.errors.code" class="text-sm text-danger-500">{{ form.errors.code }}</p>

                            <AuthCaptchaField
                                v-if="captcha.enabled"
                                v-model="form.captcha_token"
                                :config="captcha"
                                :error="form.errors.captcha_token"
                            />

                            <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 dark:border-surface-800 sm:flex-row sm:items-center sm:justify-between">
                                <Link :href="route('admin.login')" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800">
                                    {{ $t('Back to login') }}
                                </Link>

                                <button
                                    type="submit"
                                    :disabled="form.processing || form.code.length < 6"
                                    class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>{{ form.processing ? $t('Verifying...') : $t('Verify Code') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                        <div class="border-b border-gray-100 px-6 py-5 dark:border-surface-800">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t('Verification Tips') }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('A few quick checks before you submit your code.') }}</p>
                        </div>
                        <div class="space-y-3 p-6">
                            <div
                                v-for="item in helperItems"
                                :key="item"
                                class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-4 dark:border-surface-800 dark:bg-surface-950/60"
                            >
                                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                    <i class="ti ti-check text-sm"></i>
                                </span>
                                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ item }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/30 dark:bg-amber-900/10">
                        <div class="p-6">
                            <h2 class="text-lg font-semibold text-amber-900 dark:text-amber-100">{{ $t('Protected Admin Access') }}</h2>
                            <p class="mt-2 text-sm leading-6 text-amber-800 dark:text-amber-200">
                                {{ $t('Two-factor verification adds a second checkpoint before anyone can access your admin dashboard and sensitive settings.') }}
                            </p>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</template>
