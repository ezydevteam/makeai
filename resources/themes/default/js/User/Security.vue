<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import { computed, ref, watch } from 'vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

type TwoFactorPayload = {
    enabled: boolean
    channel: 'totp' | 'sms'
    confirmed_at: string | null
    recovery_codes_count: number
    manual_key: string | null
    provisioning_uri: string | null
    sms_available: boolean
    has_verified_phone: boolean
    phone_masked: string | null
}

const props = defineProps<{
    twoFactor: TwoFactorPayload
    recoveryCodes: string[]
}>()

// Which method the user is setting up (only relevant before 2FA is enabled).
const selectedMethod = ref<'totp' | 'sms'>('totp')
const smsCodeSent = ref(false)

const enableForm = useForm({ method: 'totp', code: '' })
const smsCodeForm = useForm({})
const disableForm = useForm({ password: '', code: '' })
const recoveryForm = useForm({ password: '', code: '' })

const isSmsChannel = computed(() => props.twoFactor.channel === 'sms')

const qrDataUrl = ref('')
const qrError = ref('')
const manualKeyGroups = computed(() => props.twoFactor.manual_key?.match(/.{1,4}/g)?.join(' ') ?? '')

watch(
    () => props.twoFactor.provisioning_uri,
    async (uri) => {
        qrDataUrl.value = ''
        qrError.value = ''
        if (!uri) return
        try {
            qrDataUrl.value = await QRCode.toDataURL(uri, {
                errorCorrectionLevel: 'M',
                margin: 2,
                width: 224,
                color: { dark: '#111827', light: '#ffffff' },
            })
        } catch {
            qrError.value = 'Unable to generate QR code.'
        }
    },
    { immediate: true },
)

const selectMethod = (method: 'totp' | 'sms') => {
    selectedMethod.value = method
    smsCodeSent.value = false
    enableForm.clearErrors()
}

const sendSmsCode = () => {
    smsCodeForm.post(route('user.dashboard.security.2fa.sms-code'), {
        preserveScroll: true,
        onSuccess: () => { smsCodeSent.value = true },
    })
}

const enable = () => {
    enableForm.method = selectedMethod.value
    enableForm.post(route('user.dashboard.security.2fa.enable'), {
        preserveScroll: true,
        onFinish: () => enableForm.reset('code'),
    })
}

const disable = () => {
    disableForm.post(route('user.dashboard.security.2fa.disable'), {
        preserveScroll: true,
        onSuccess: () => disableForm.reset(),
        onFinish: () => disableForm.reset('password'),
    })
}

const regenerateRecoveryCodes = () => {
    recoveryForm.post(route('user.dashboard.security.2fa.recovery-codes'), {
        preserveScroll: true,
        onSuccess: () => recoveryForm.reset(),
        onFinish: () => recoveryForm.reset('password'),
    })
}

const downloadRecoveryCodes = () => {
    if (!props.recoveryCodes.length) {
        return
    }

    const content = props.recoveryCodes.join('\r\n') + '\r\n'
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'recovery-codes.txt'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
}
</script>

<template>
    <Head :title="$t('Security')" />

    <div class="space-y-6">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Security') }}</h1>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="twoFactor.enabled ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'">
                    {{ twoFactor.enabled ? $t('2FA Enabled') : $t('2FA Not enabled') }}
                </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('Manage your security and two-factor authentication settings.') }}</p>
        </div>

        <div v-if="recoveryCodes.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-amber-900/40 dark:bg-amber-900/20">
            <h2 class="text-lg font-bold text-amber-950 dark:text-amber-100">{{ $t('Save these recovery codes now') }}</h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ $t('Each code can be used once if you lose access to your authenticator or phone.') }}</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <code v-for="code in recoveryCodes" :key="code" class="rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-sm font-bold tracking-wider text-amber-950 dark:border-amber-800 dark:bg-gray-950 dark:text-amber-100">
                    {{ code }}
                </code>
            </div>
            <div class="mt-4">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 dark:border-amber-700 dark:bg-gray-950 dark:text-amber-100 dark:hover:bg-amber-900/40"
                    @click="downloadRecoveryCodes"
                >
                    <i class="ti ti-download text-base"></i>
                    {{ $t('Download codes (.txt)') }}
                </button>
            </div>
        </div>

        <!-- ─── Not enabled: choose a method ─── -->
        <div v-if="!twoFactor.enabled" class="space-y-6">
            <!-- With SMS unavailable, authenticator is the only method — skip the chooser
                 and show its setup directly (selectedMethod stays 'totp'). -->
            <div v-if="twoFactor.sms_available" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Choose a two-factor method') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Add a second step to your sign-in for stronger account protection.') }}</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="flex items-start gap-3 rounded-xl border p-4 text-left transition"
                        :class="selectedMethod === 'totp' ? 'border-primary-500 bg-primary-50/60 dark:border-primary-500 dark:bg-primary-900/20' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'"
                        @click="selectMethod('totp')"
                    >
                        <i class="ti ti-device-mobile text-2xl text-primary-600 dark:text-primary-400"></i>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $t('Authenticator app') }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $t('Google Authenticator, Authy, 1Password, etc.') }}</span>
                        </span>
                    </button>

                    <button
                        type="button"
                        :disabled="!twoFactor.sms_available"
                        class="flex items-start gap-3 rounded-xl border p-4 text-left transition disabled:cursor-not-allowed disabled:opacity-60"
                        :class="selectedMethod === 'sms' && twoFactor.sms_available ? 'border-primary-500 bg-primary-50/60 dark:border-primary-500 dark:bg-primary-900/20' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'"
                        @click="twoFactor.sms_available && selectMethod('sms')"
                    >
                        <i class="ti ti-message-2 text-2xl text-primary-600 dark:text-primary-400"></i>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ $t('Text message (SMS)') }}</span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                <template v-if="twoFactor.sms_available">{{ $t('Codes sent to :phone', { phone: twoFactor.phone_masked ?? '' }) }}</template>
                                <template v-else-if="!twoFactor.has_verified_phone">{{ $t('Requires a verified phone number') }}</template>
                                <template v-else>{{ $t('Currently unavailable') }}</template>
                            </span>
                        </span>
                    </button>
                </div>

                <p v-if="selectedMethod === 'sms' && !twoFactor.has_verified_phone" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    <Link :href="route('user.dashboard.profile')" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $t('Add and verify a phone number') }}</Link>
                    {{ $t('to use SMS two-factor.') }}
                </p>
            </div>

            <!-- TOTP setup -->
            <div v-if="selectedMethod === 'totp'" class="grid gap-6 lg:grid-cols-[1fr_360px]">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Set up authenticator app') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Scan the QR code in Google Authenticator, 1Password, Authy, or any TOTP-compatible app.') }}</p>
                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $t('Scan QR code') }}</label>
                            <div class="flex justify-center rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950">
                                <div v-if="qrDataUrl" class="rounded-lg bg-white p-3 shadow-sm">
                                    <img :src="qrDataUrl" :alt="$t('Authenticator setup QR code')" class="h-56 w-56" />
                                </div>
                                <div v-else class="flex h-56 w-56 items-center justify-center rounded-lg bg-white text-center text-sm text-gray-500">
                                    {{ qrError || $t('Generating QR code...') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $t('Manual setup key') }}</label>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm font-bold tracking-widest text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                {{ manualKeyGroups }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Confirm setup') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Enter the 6-digit code from your authenticator app.') }}</p>
                    <form class="mt-5 space-y-4" @submit.prevent="enable">
                        <div>
                            <label for="enable-code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Authenticator code') }}</label>
                            <input id="enable-code" v-model="enableForm.code" type="text" inputmode="numeric" maxlength="6" :placeholder="$t('Enter 6 digit code...')" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-center text-lg font-bold tracking-widest text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <p v-if="enableForm.errors.code" class="text-sm text-red-500">{{ enableForm.errors.code }}</p>
                        <button type="submit" :disabled="enableForm.processing || enableForm.code.length !== 6" class="w-full btn-primary disabled:cursor-not-allowed disabled:opacity-60">
                            {{ enableForm.processing ? $t('Enabling...') : $t('Enable Two-Factor') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- SMS setup -->
            <div v-else-if="selectedMethod === 'sms' && twoFactor.sms_available" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900 lg:max-w-md">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Set up SMS two-factor') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('We will text a 6-digit code to :phone. Enter it below to turn on SMS two-factor.', { phone: twoFactor.phone_masked ?? '' }) }}</p>
                <div class="mt-5 space-y-4">
                    <button type="button" :disabled="smsCodeForm.processing" class="w-full rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm font-semibold text-primary-700 hover:bg-primary-100 disabled:opacity-60 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300" @click="sendSmsCode">
                        {{ smsCodeForm.processing ? $t('Sending...') : (smsCodeSent ? $t('Resend code') : $t('Send code')) }}
                    </button>

                    <form v-if="smsCodeSent" class="space-y-4" @submit.prevent="enable">
                        <div>
                            <label for="sms-enable-code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Verification code') }}</label>
                            <input id="sms-enable-code" v-model="enableForm.code" type="text" inputmode="numeric" maxlength="6" :placeholder="$t('Enter 6 digit code...')" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-center text-lg font-bold tracking-widest text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <p v-if="enableForm.errors.code" class="text-sm text-red-500">{{ enableForm.errors.code }}</p>
                        <button type="submit" :disabled="enableForm.processing || enableForm.code.length !== 6" class="w-full btn-primary disabled:cursor-not-allowed disabled:opacity-60">
                            {{ enableForm.processing ? $t('Enabling...') : $t('Enable Two-Factor') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ─── Enabled ─── -->
        <div v-else class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <i class="text-2xl text-green-600 dark:text-green-400" :class="isSmsChannel ? 'ti ti-message-2' : 'ti ti-device-mobile'"></i>
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                            {{ isSmsChannel ? $t('Protected by SMS text codes') : $t('Protected by an authenticator app') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <template v-if="isSmsChannel">{{ $t('Codes are sent to :phone at sign-in.', { phone: twoFactor.phone_masked ?? '' }) }}</template>
                            <template v-else>{{ $t('Codes come from your authenticator app at sign-in.') }}</template>
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Recovery codes') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('You have :count recovery codes remaining.', { count: twoFactor.recovery_codes_count }) }}</p>
                    <form class="mt-5 space-y-4" @submit.prevent="regenerateRecoveryCodes">
                        <div>
                            <label for="recovery-password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Current password') }}</label>
                            <input id="recovery-password" v-model="recoveryForm.password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <p v-if="recoveryForm.errors.password" class="text-sm text-red-500">{{ recoveryForm.errors.password }}</p>
                        <div>
                            <label for="recovery-code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ isSmsChannel ? $t('SMS or recovery code') : $t('Authenticator or recovery code') }}</label>
                            <input id="recovery-code" v-model="recoveryForm.code" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <button v-if="isSmsChannel" type="button" :disabled="smsCodeForm.processing" class="text-xs font-semibold text-primary-600 hover:underline disabled:opacity-60 dark:text-primary-400" @click="sendSmsCode">
                            {{ smsCodeForm.processing ? $t('Sending...') : (smsCodeSent ? $t('Code sent — resend') : $t('Text me a code')) }}
                        </button>
                        <p v-if="recoveryForm.errors.code" class="text-sm text-red-500">{{ recoveryForm.errors.code }}</p>
                        <button type="submit" :disabled="recoveryForm.processing" class="rounded-lg border border-[#1F75FE] px-4 py-2.5 text-sm font-semibold text-[#1F75FE] transition hover:bg-[#1F75FE]/5 dark:hover:bg-[#1F75FE]/10 disabled:cursor-not-allowed disabled:opacity-60">
                            {{ recoveryForm.processing ? $t('Regenerating...') : $t('Regenerate recovery codes') }}
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Disable two-factor') }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Disabling two-factor authentication lowers account protection.') }}</p>
                    <form class="mt-5 space-y-4" @submit.prevent="disable">
                        <div>
                            <label for="disable-password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('Current password') }}</label>
                            <input id="disable-password" v-model="disableForm.password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <p v-if="disableForm.errors.password" class="text-sm text-red-500">{{ disableForm.errors.password }}</p>
                        <div>
                            <label for="disable-code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ isSmsChannel ? $t('SMS or recovery code') : $t('Authenticator or recovery code') }}</label>
                            <input id="disable-code" v-model="disableForm.code" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <button v-if="isSmsChannel" type="button" :disabled="smsCodeForm.processing" class="text-xs font-semibold text-primary-600 hover:underline disabled:opacity-60 dark:text-primary-400" @click="sendSmsCode">
                            {{ smsCodeForm.processing ? $t('Sending...') : (smsCodeSent ? $t('Code sent — resend') : $t('Text me a code')) }}
                        </button>
                        <p v-if="disableForm.errors.code" class="text-sm text-red-500">{{ disableForm.errors.code }}</p>
                        <button type="submit" :disabled="disableForm.processing" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-60">
                            {{ disableForm.processing ? $t('Disabling...') : $t('Disable Two-Factor') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
