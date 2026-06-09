<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import { computed, ref, watch } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

type TwoFactorPayload = {
    enabled: boolean
    confirmed_at: string | null
    recovery_codes_count: number
    manual_key: string | null
    provisioning_uri: string | null
}

const props = defineProps<{
    twoFactor: TwoFactorPayload
    recoveryCodes: string[]
}>()

const enableForm = useForm({ code: '' })
const disableForm = useForm({ password: '', code: '' })
const recoveryForm = useForm({ password: '', code: '' })

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

const enable = () => {
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
</script>

<template>
    <Head :title="$t('Security')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Security') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('Manage your security and two-factor authentication settings.') }}</p>
        </div>

        <!-- 2FA Status badge -->
        <div class="flex items-center gap-4">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold" :class="twoFactor.enabled ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'">
                {{ twoFactor.enabled ? $t('2FA Enabled') : $t('2FA Not enabled') }}
            </span>
        </div>

        <div v-if="recoveryCodes.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-900/20">
            <h2 class="text-lg font-bold text-amber-950 dark:text-amber-100">{{ $t('Save these recovery codes now') }}</h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ $t('Each code can be used once if you lose access to your authenticator app.') }}</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <code v-for="code in recoveryCodes" :key="code" class="rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-sm font-bold tracking-wider text-amber-950 dark:border-amber-800 dark:bg-gray-950 dark:text-amber-100">
                    {{ code }}
                </code>
            </div>
        </div>

        <div v-if="!twoFactor.enabled" class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
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

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Confirm setup') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Enter the 6-digit code from your authenticator app.') }}</p>
                <form class="mt-5 space-y-4" @submit.prevent="enable">
                    <input id="enable-code" v-model="enableForm.code" type="text" inputmode="numeric" maxlength="6" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-center text-lg font-bold tracking-widest text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <p v-if="enableForm.errors.code" class="text-sm text-red-500">{{ enableForm.errors.code }}</p>
                    <button type="submit" :disabled="enableForm.processing || enableForm.code.length !== 6" class="w-full rounded-lg bg-[#1F75FE] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1a65e0] disabled:cursor-not-allowed disabled:opacity-60">
                        {{ enableForm.processing ? $t('Enabling...') : $t('Enable Two-Factor') }}
                    </button>
                </form>
            </div>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Recovery codes') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('You have :count recovery codes remaining.', { count: twoFactor.recovery_codes_count }) }}</p>
                <form class="mt-5 space-y-4" @submit.prevent="regenerateRecoveryCodes">
                    <input v-model="recoveryForm.password" type="password" required autocomplete="current-password" :placeholder="$t('Current password')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <p v-if="recoveryForm.errors.password" class="text-sm text-red-500">{{ recoveryForm.errors.password }}</p>
                    <input v-model="recoveryForm.code" type="text" required :placeholder="$t('Authenticator or recovery code')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <p v-if="recoveryForm.errors.code" class="text-sm text-red-500">{{ recoveryForm.errors.code }}</p>
                    <button type="submit" :disabled="recoveryForm.processing" class="rounded-lg border border-[#1F75FE] px-4 py-2.5 text-sm font-semibold text-[#1F75FE] transition hover:bg-[#1F75FE]/5 dark:hover:bg-[#1F75FE]/10 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ recoveryForm.processing ? $t('Regenerating...') : $t('Regenerate recovery codes') }}
                    </button>
                </form>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $t('Disable two-factor') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Disabling two-factor authentication lowers account protection.') }}</p>
                <form class="mt-5 space-y-4" @submit.prevent="disable">
                    <input v-model="disableForm.password" type="password" required autocomplete="current-password" :placeholder="$t('Current password')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <p v-if="disableForm.errors.password" class="text-sm text-red-500">{{ disableForm.errors.password }}</p>
                    <input v-model="disableForm.code" type="text" required :placeholder="$t('Authenticator or recovery code')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <p v-if="disableForm.errors.code" class="text-sm text-red-500">{{ disableForm.errors.code }}</p>
                    <button type="submit" :disabled="disableForm.processing" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ disableForm.processing ? $t('Disabling...') : $t('Disable Two-Factor') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
