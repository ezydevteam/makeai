<script setup lang="ts">
import AuthCaptchaField from '@/Components/Utility/AuthCaptchaField.vue'
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
const appName = computed(() => String(branding.value?.site_name || page.props.appName || t('MakeAI')))
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
const codeInputClass = 'border-1 border-gray-300 bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:border-surface-600 dark:bg-surface-950 dark:shadow-black/20 dark:focus:border-primary-400 dark:focus:ring-primary-900/30'

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
        <div class="mx-auto w-full max-w-xl px-4 sm:px-6 lg:px-8">
            <section class="rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="px-6 py-5">
                    <div class="flex gap-4 flex-col items-center text-center">
                        <Link :href="route('home')" class="mb-4 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                            <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                            <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                        </Link>
                        <h1 class="mb-1 font-heading text-2xl font-bold text-gray-950 dark:text-white">{{ verificationTitle }}</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ verificationDescription }}
                        </p>
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

                        <div class="pt-5">
                            <button
                                type="submit"
                                :disabled="form.processing || form.code.length < 6"
                                class="btn-primary-admin w-full flex items-center justify-center gap-2 disabled:cursor-not-allowed disabled:opacity-60"
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
        </div>
    </div>
</template>
