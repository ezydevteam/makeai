<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import { computed, ref, watch } from 'vue'

defineOptions({ layout: AdminLayout })

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

const enableForm = useForm({
    code: '',
})

const disableForm = useForm({
    password: '',
    code: '',
})

const recoveryForm = useForm({
    password: '',
    code: '',
})

const qrDataUrl = ref('')
const qrError = ref('')
const manualKeyGroups = computed(() => props.twoFactor.manual_key?.match(/.{1,4}/g)?.join(' ') ?? '')
const securityInputClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-900 shadow-sm shadow-gray-200/40 transition focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-600 dark:bg-surface-800 dark:text-white dark:shadow-black/20 dark:focus:border-primary-400 dark:focus:ring-primary-900/30'
const codeInputClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-900 shadow-sm shadow-gray-200/40 transition focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100 dark:border-surface-600 dark:bg-surface-800 dark:text-white dark:shadow-black/20 dark:focus:border-primary-400 dark:focus:ring-primary-900/30'

watch(
    () => props.twoFactor.provisioning_uri,
    async (uri) => {
        qrDataUrl.value = ''
        qrError.value = ''

        if (!uri) {
            return
        }

        try {
            qrDataUrl.value = await QRCode.toDataURL(uri, {
                errorCorrectionLevel: 'M',
                margin: 2,
                width: 224,
                color: {
                    dark: '#111827',
                    light: '#ffffff',
                },
            })
        } catch {
            qrError.value = 'Unable to generate QR code. Use the manual setup key instead.'
        }
    },
    { immediate: true },
)

const enable = () => {
    enableForm.post(route('admin.security.2fa.enable'), {
        preserveScroll: true,
        onFinish: () => enableForm.reset('code'),
    })
}

const disable = () => {
    disableForm.post(route('admin.security.2fa.disable'), {
        preserveScroll: true,
        onSuccess: () => disableForm.reset(),
        onFinish: () => disableForm.reset('password'),
    })
}

const regenerateRecoveryCodes = () => {
    recoveryForm.post(route('admin.security.2fa.recovery-codes'), {
        preserveScroll: true,
        onSuccess: () => recoveryForm.reset(),
        onFinish: () => recoveryForm.reset('password'),
    })
}
</script>

<template>
    <Head :title="$t('Two-Factor Security')" />

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-heading text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Two-Factor Security') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Protect your admin account with an authenticator app and recovery codes.') }}</p>
            </div>
            <span
                class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold"
                :class="twoFactor.enabled ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'"
            >
                {{ twoFactor.enabled ? $t('Enabled') : $t('Not enabled') }}
            </span>
        </div>

        <div v-if="recoveryCodes.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-900/20">
            <h2 class="font-heading text-lg font-bold text-amber-950 dark:text-amber-100">{{ $t('Save these recovery codes now') }}</h2>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ $t('Each code can be used once if you lose access to your authenticator app.') }}</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <code v-for="code in recoveryCodes" :key="code" class="rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-sm font-bold tracking-wider text-amber-950 dark:border-amber-800 dark:bg-surface-950 dark:text-amber-100">
                    {{ code }}
                </code>
            </div>
        </div>

        <div v-if="!twoFactor.enabled" class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ $t('Set up authenticator app') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Add a new account in Google Authenticator, 1Password, Authy, or any TOTP-compatible app.') }}</p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $t('Scan QR code') }}</label>
                        <div class="flex justify-center rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-950">
                            <div v-if="qrDataUrl" class="rounded-lg bg-white p-3 shadow-sm">
                                <img :src="qrDataUrl" :alt="$t('Authenticator setup QR code')" class="h-56 w-56" />
                            </div>
                            <div v-else class="flex h-56 w-56 items-center justify-center rounded-lg bg-white text-center text-sm text-gray-500">
                                {{ qrError ? $t(qrError) : $t('Generating QR code...') }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $t('Manual setup key') }}</label>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm font-bold tracking-widest text-gray-900 dark:border-surface-700 dark:bg-surface-950 dark:text-white">
                            {{ manualKeyGroups }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ $t('Confirm setup') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Enter the 6-digit code from your authenticator app.') }}</p>

                <form class="mt-5 space-y-4" @submit.prevent="enable">
                    <div>
                        <label for="enable-code" class="mb-1.5 block text-sm text-gray-800 font-semibold dark:text-gray-300">{{ $t('OTP Code') }}</label>
                        <input
                            id="enable-code"
                            v-model="enableForm.code"
                            type="text"
                            :placeholder="$t('Enter 6-digit code')"
                            inputmode="numeric"
                            maxlength="6"
                            required
                            :class="[codeInputClass, 'text-center']"
                        />
                        <p v-if="enableForm.errors.code" class="mt-1 text-sm text-danger-500">{{ enableForm.errors.code }}</p>
                    </div>

                    <button type="submit" :disabled="enableForm.processing || enableForm.code.length !== 6" class="w-full rounded-lg btn-primary-admin shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-60">
                        {{ enableForm.processing ? $t('Enabling...') : $t('Enable Two-Factor') }}
                    </button>
                </form>
            </section>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ $t('Recovery codes') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('You have :count recovery codes remaining.', { count: twoFactor.recovery_codes_count }) }}</p>

                <form class="mt-5 space-y-4" @submit.prevent="regenerateRecoveryCodes">
                    <input v-model="recoveryForm.password" type="password" required autocomplete="current-password" :class="securityInputClass" :placeholder="$t('Current password')" />
                    <p v-if="recoveryForm.errors.password" class="text-sm text-danger-500">{{ recoveryForm.errors.password }}</p>

                    <input v-model="recoveryForm.code" type="text" required :class="codeInputClass" :placeholder="$t('Authenticator or recovery code')" />
                    <p v-if="recoveryForm.errors.code" class="text-sm text-danger-500">{{ recoveryForm.errors.code }}</p>

                    <button type="submit" :disabled="recoveryForm.processing" class="rounded-lg border border-primary-500 px-4 py-2.5 text-sm font-semibold text-primary-700 transition-colors hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60 dark:text-primary-300 dark:hover:bg-primary-900/20">
                        {{ recoveryForm.processing ? $t('Regenerating...') : $t('Regenerate recovery codes') }}
                    </button>
                </form>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ $t('Disable two-factor') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('Disabling two-factor authentication lowers admin account protection.') }}</p>

                <form class="mt-5 space-y-4" @submit.prevent="disable">
                    <input v-model="disableForm.password" type="password" required autocomplete="current-password" :class="securityInputClass" :placeholder="$t('Current password')" />
                    <p v-if="disableForm.errors.password" class="text-sm text-danger-500">{{ disableForm.errors.password }}</p>

                    <input v-model="disableForm.code" type="text" required :class="codeInputClass" :placeholder="$t('Authenticator or recovery code')" />
                    <p v-if="disableForm.errors.code" class="text-sm text-danger-500">{{ disableForm.errors.code }}</p>

                    <button type="submit" :disabled="disableForm.processing" class="rounded-xl bg-danger-600 hover:bg-danger-700 text-white px-4 py-2 text-sm font-semibold shadow-sm transition-all hover:-translate-y-px active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ disableForm.processing ? $t('Disabling...') : $t('Disable Two-Factor') }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>
