<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
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

const page = usePage()
const user = computed(() => page.props.auth?.user as any)

const props = defineProps<{
    twoFactor: TwoFactorPayload
    recoveryCodes: string[]
}>()

// Profile form
const profileForm = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
})

// Password form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

// Avatar form
const avatarForm = useForm({ avatar: null as File | null })
const avatarPreview = ref<string | null>(user.value?.avatar ?? null)

const onAvatarChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    avatarForm.avatar = file
    avatarPreview.value = URL.createObjectURL(file)
}

const uploadAvatar = () => {
    avatarForm.put(route('user.avatar.update'), {
        onSuccess: () => {
            avatarForm.reset()
        },
    })
}

// 2FA forms
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
    enableForm.post(route('user.settings.2fa.enable'), {
        preserveScroll: true,
        onFinish: () => enableForm.reset('code'),
    })
}

const disable = () => {
    disableForm.post(route('user.settings.2fa.disable'), {
        preserveScroll: true,
        onSuccess: () => disableForm.reset(),
        onFinish: () => disableForm.reset('password'),
    })
}

const regenerateRecoveryCodes = () => {
    recoveryForm.post(route('user.settings.2fa.recovery-codes'), {
        preserveScroll: true,
        onSuccess: () => recoveryForm.reset(),
        onFinish: () => recoveryForm.reset('password'),
    })
}

const activeTab = ref<'profile' | 'security'>('profile')
</script>

<template>
    <Head :title="$t('Account Settings')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Account Settings') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('Manage your profile and security settings.') }}</p>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-xl border border-gray-200 bg-gray-100 p-1 w-fit dark:border-gray-800 dark:bg-gray-800">
            <button @click="activeTab = 'profile'" :class="activeTab === 'profile' ? 'bg-white shadow-sm text-gray-900 dark:bg-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'" class="rounded-lg px-4 py-2 text-sm font-semibold transition">
                {{ $t('Profile') }}
            </button>
            <button @click="activeTab = 'security'" :class="activeTab === 'security' ? 'bg-white shadow-sm text-gray-900 dark:bg-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'" class="rounded-lg px-4 py-2 text-sm font-semibold transition">
                {{ $t('Security & 2FA') }}
            </button>
        </div>

        <!-- Profile Tab -->
        <div v-if="activeTab === 'profile'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Avatar card -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6 text-center">
                <div class="mx-auto mb-4 h-24 w-24 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <img v-if="avatarPreview" :src="avatarPreview.startsWith('blob:') ? avatarPreview : '/storage/' + avatarPreview" class="h-full w-full object-cover" alt="avatar" />
                    <div v-else class="flex h-full w-full items-center justify-center text-2xl font-bold text-gray-400">{{ (user?.name || '?')[0]?.toUpperCase() }}</div>
                </div>
                <form @submit.prevent="uploadAvatar">
                    <input id="avatar-upload" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                    <label for="avatar-upload" class="cursor-pointer text-sm font-semibold text-[#1F75FE] hover:underline">{{ $t('Choose photo') }}</label>
                    <button v-if="avatarForm.avatar" type="submit" :disabled="avatarForm.processing" class="mt-3 w-full rounded-lg bg-[#1F75FE] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1a65e0] transition">
                        {{ avatarForm.processing ? $t('Uploading...') : $t('Upload') }}
                    </button>
                    <p v-if="avatarForm.errors.avatar" class="mt-1 text-xs text-red-500">{{ avatarForm.errors.avatar }}</p>
                </form>
            </div>

            <!-- Name / Email -->
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-5">{{ $t('Profile information') }}</h2>
                <form @submit.prevent="profileForm.put(route('user.profile.update'))" class="space-y-4">
                    <div>
                        <label for="settings-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Name') }}</label>
                        <input id="settings-name" v-model="profileForm.name" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">{{ profileForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="settings-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Email') }}</label>
                        <input id="settings-email" v-model="profileForm.email" type="email" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">{{ profileForm.errors.email }}</p>
                    </div>
                    <button type="submit" :disabled="profileForm.processing" class="rounded-xl bg-[#1F75FE] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] transition disabled:opacity-60">
                        {{ profileForm.processing ? $t('Saving...') : $t('Save changes') }}
                    </button>
                </form>

                <!-- Password -->
                <hr class="my-6 border-gray-100 dark:border-gray-800" />
                <h2 class="font-semibold text-gray-900 dark:text-white mb-5">{{ $t('Change password') }}</h2>
                <form @submit.prevent="passwordForm.put(route('user.password.update'))" class="space-y-4">
                    <div>
                        <label for="current-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Current password') }}</label>
                        <input id="current-pw" v-model="passwordForm.current_password" type="password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label for="new-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('New password') }}</label>
                        <input id="new-pw" v-model="passwordForm.password" type="password" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="confirm-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Confirm new password') }}</label>
                        <input id="confirm-pw" v-model="passwordForm.password_confirmation" type="password" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>
                    <button type="submit" :disabled="passwordForm.processing" class="rounded-xl bg-[#1F75FE] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] transition disabled:opacity-60">
                        {{ passwordForm.processing ? $t('Changing...') : $t('Change password') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Security & 2FA Tab -->
        <div v-if="activeTab === 'security'" class="space-y-6">
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
    </div>
</template>
