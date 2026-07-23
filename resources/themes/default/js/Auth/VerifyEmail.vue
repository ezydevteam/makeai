<script setup lang="ts">
import AuthCaptchaField from '@/Components/Utility/AuthCaptchaField.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useFlashToasts } from '@/Composables/useToastr'
import { useTheme } from '@/Composables/useTheme'

useFlashToasts()

interface PageProps {
    branding?: { site_name?: string; site_logo_light?: string; site_logo_dark?: string }
    captcha?: { enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }
    email?: string
}

const { t } = useTranslate()
const { isDark } = useTheme()
const page = usePage()
const props = page.props as unknown as PageProps

const branding = computed(() => props.branding)
const appName = computed(() => String(branding.value?.site_name || 'MakeAI'))
const logoLight = computed(() => String(branding.value?.site_logo_light || ''))
const logoDark = computed(() => String(branding.value?.site_logo_dark || ''))
const authLogo = computed(() => (isDark.value ? (logoDark.value || logoLight.value) : (logoLight.value || logoDark.value)))
const captcha = computed<{ enabled: boolean; provider: 'recaptcha' | 'hcaptcha'; site_key: string }>(() => props.captcha ?? { enabled: false, provider: 'recaptcha', site_key: '' })
const email = computed(() => String(props.email || ''))

const form = useForm({ code: '', captcha_token: '' })
const digits = ref(['', '', '', '', '', ''])
const inputs = ref<HTMLInputElement[]>([])

const handleInput = (index: number, event: Event) => {
    const value = (event.target as HTMLInputElement).value.replace(/\D/g, '')

    if (value.length > 1) {
        value.split('').slice(0, 6).forEach((char, i) => {
            if (i < 6) {
                digits.value[i] = char
            }
        })
        form.code = digits.value.join('')
        inputs.value[Math.min(value.length - 1, 5)]?.focus()
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

const submit = () => form.post(route('verification.verify'))
const resend = () => form.post(route('verification.resend'), { preserveState: true })

onMounted(() => inputs.value[0]?.focus())
</script>

<template>
    <Head :title="t('Verify Email')" />

    <div class="frontend-theme">
        <div class="auth-page p-4 lg:p-8">
            <div class="mx-auto max-w-md">
                <div class="auth-card mx-auto rounded-2xl border border-gray-200 bg-white px-6 py-7 shadow-none dark:border-white/10 dark:bg-surface-950 sm:px-7">
                    <div class="auth-brand-block mb-8">
                        <Link :href="route('home')" class="mb-8 inline-flex items-center justify-center text-gray-950 no-underline dark:text-white">
                            <img v-if="authLogo" :src="authLogo" :alt="appName" class="h-11 w-auto max-w-[180px] object-contain">
                            <span v-else class="font-heading text-[1.75rem] font-bold">{{ appName }}</span>
                        </Link>

                        <h1 class="font-heading text-[2rem] font-bold tracking-tight text-gray-950 dark:text-white">
                            {{ t('Verify your email') }}
                        </h1>
                        <p class="mt-2 max-w-sm text-sm leading-6 text-gray-500 dark:text-gray-400">
                            <template v-if="email">
                                {{ t('A 6-digit verification code has been sent to :email', { email }) }}
                            </template>
                            <template v-else>
                                {{ t('A 6-digit verification code has been sent to your email.') }}
                            </template>
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="flex justify-center gap-2.5">
                            <input
                                v-for="(_, i) in 6"
                                :key="i"
                                :ref="(el) => { if (el) inputs[i] = el as HTMLInputElement }"
                                :value="digits[i]"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                class="h-12 w-11 rounded-2xl border border-gray-200 bg-white text-center text-lg font-bold text-gray-900 transition-all duration-200 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/[0.03] dark:text-white"
                                @input="handleInput(i, $event)"
                                @keydown="handleKeydown(i, $event)"
                            />
                        </div>
                        <p v-if="form.errors.code" class="mt-2 text-center text-sm text-danger-500">{{ form.errors.code }}</p>

                        <AuthCaptchaField
                            v-if="captcha.enabled"
                            v-model="form.captcha_token"
                            :config="captcha"
                            :error="form.errors.captcha_token"
                        />

                        <button type="submit" :disabled="form.processing || form.code.length !== 6" class="auth-primary-button auth-primary-button--reverse">
                            <span v-if="!form.processing">{{ $t('Verify Email') }}</span>
                            <span v-else class="inline-flex items-center gap-2">
                                <i class="ti ti-loader-2 animate-spin text-lg"></i>
                                {{ $t('Verifying...') }}
                            </span>
                        </button>

                        <div class="flex items-center justify-center gap-2">
                            <p class="text-sm text-gray-500 dark:text-gray-300">{{ t("Didn't receive the code?") }}</p>
                            <button type="button" @click="resend" :disabled="form.processing" class="auth-inline-link text-sm">
                                {{ t("Resend") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
