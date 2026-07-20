<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: AdminLayout })

interface AccountPayload {
    name: string
    email: string
    avatar: string | null
}

interface TwoFactorPayload {
    enabled: boolean
    confirmed_at: string | null
    recovery_codes_count: number
}

const props = defineProps<{
    account: AccountPayload
    twoFactor: TwoFactorPayload
}>()

const { t } = useTranslate()

const profileForm = useForm({
    name: props.account.name ?? '',
    email: props.account.email ?? '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const avatarForm = useForm({
    avatar: null as File | null,
})

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)
const avatarPreview = ref<string | null>(props.account.avatar ? resolveMediaUrl(props.account.avatar) : null)

const avatarFallback = computed(() => {
    const name = props.account.name?.trim() || t('Admin')
    return name.charAt(0).toUpperCase()
})
const twoFactorStatusClass = computed(() => props.twoFactor.enabled
    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300')
const twoFactorStatusText = computed(() => props.twoFactor.enabled ? t('Enabled') : t('Not enabled'))

function resolveMediaUrl(path?: string | null): string {
    return mediaUrl(path)
}

function onAvatarChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0]
    if (!file) return

    avatarForm.avatar = file
    avatarPreview.value = URL.createObjectURL(file)
}

function uploadAvatar(): void {
    avatarForm.post(route('admin.account.avatar.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset('avatar')
        },
    })
}

function updateProfile(): void {
    profileForm.post(route('admin.account.profile.update'), {
        preserveScroll: true,
    })
}

function updatePassword(): void {
    passwordForm.post(route('admin.account.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset()
            showCurrentPassword.value = false
            showNewPassword.value = false
            showConfirmPassword.value = false
        },
    })
}
</script>

<template>
    <Head :title="t('Account Settings')" />

    <div class="w-full space-y-6 px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ t('Manage Account Details') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Manage your account details, photo, and password.') }}</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 space-y-1">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Profile Photo') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Upload a clear photo to show in the admin navbar and account menu.') }}</p>
                    </div>

                    <div class="flex items-center gap-5">
                        <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-2xl font-bold text-gray-500 dark:bg-surface-800 dark:text-gray-300">
                            <img v-if="avatarPreview" :src="avatarPreview" :alt="t('Admin avatar')" class="h-full w-full object-cover" />
                            <span v-else>{{ avatarFallback }}</span>
                        </div>

                        <form class="flex-1 space-y-3" @submit.prevent="uploadAvatar">
                            <input id="admin-avatar-upload" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />

                            <label for="admin-avatar-upload" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:bg-surface-700">
                                <i class="ti ti-upload text-base"></i>
                                {{ t('Choose Photo') }}
                            </label>

                            <button v-if="avatarForm.avatar" type="submit" :disabled="avatarForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-linear-to-r from-primary-500 to-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-500/20 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary-500/25 disabled:opacity-60">
                                <i class="ti ti-device-floppy text-base"></i>
                                {{ avatarForm.processing ? t('Uploading...') : t('Save Photo') }}
                            </button>

                            <p v-if="avatarForm.errors.avatar" class="text-xs text-danger-500">{{ avatarForm.errors.avatar }}</p>
                        </form>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="space-y-1">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Two-Factor Security') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Check your current 2FA protection status and open the full security page.') }}</p>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Current Status') }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ props.twoFactor.enabled
                                        ? t('Your admin account currently requires an authenticator code at sign-in.')
                                        : t('Two-factor authentication is not enabled on this admin account.') }}
                                </p>
                            </div>

                            <span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold" :class="twoFactorStatusClass">
                                {{ twoFactorStatusText }}
                            </span>
                        </div>

                        <div v-if="props.twoFactor.enabled" class="rounded-xl border border-gray-100 bg-white px-4 py-3 dark:border-surface-700 dark:bg-surface-900">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ t('Recovery Codes Left') }}</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ props.twoFactor.recovery_codes_count }}</p>
                        </div>

                        <Link :href="route('admin.security.2fa.show')" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-200 dark:hover:border-primary-500/40 dark:hover:text-primary-300">
                            <i class="ti ti-shield-lock text-base"></i>
                            {{ t(':status 2FA Security', { status: props.twoFactor.enabled ? t('Manage') : t('Enable') }) }}
                        </Link>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 space-y-1">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Account Details') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Keep your administrator name and email up to date.') }}</p>
                    </div>

                    <form class="grid gap-5 md:grid-cols-2" @submit.prevent="updateProfile">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Full Name') }}
                            <input v-model="profileForm.name" type="text" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-danger-500">{{ profileForm.errors.name }}</p>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Email Address') }}
                            <input v-model="profileForm.email" type="email" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                            <p v-if="profileForm.errors.email" class="mt-1 text-xs text-danger-500">{{ profileForm.errors.email }}</p>
                        </label>

                        <div class="md:col-span-2">
                            <button type="submit" :disabled="profileForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-linear-to-r from-primary-500 to-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-500/20 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary-500/25 disabled:opacity-60">
                                <i class="ti ti-device-floppy text-base"></i>
                                {{ profileForm.processing ? t('Saving...') : t('Save Details') }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="mb-5 space-y-1">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Change Password') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Use your current password to set a new password for this admin account.') }}</p>
                    </div>

                    <form class="grid gap-5 md:grid-cols-2" @submit.prevent="updatePassword">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                            {{ t('Current Password') }}
                            <div class="relative mt-2">
                                <input v-model="passwordForm.current_password" :type="showCurrentPassword ? 'text' : 'password'" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-11 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                <button type="button" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200" @click="showCurrentPassword = !showCurrentPassword">
                                    <i :class="showCurrentPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-danger-500">{{ passwordForm.errors.current_password }}</p>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('New Password') }}
                            <div class="relative mt-2">
                                <input v-model="passwordForm.password" :type="showNewPassword ? 'text' : 'password'" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-11 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                <button type="button" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200" @click="showNewPassword = !showNewPassword">
                                    <i :class="showNewPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-danger-500">{{ passwordForm.errors.password }}</p>
                        </label>

                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Confirm New Password') }}
                            <div class="relative mt-2">
                                <input v-model="passwordForm.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-11 text-sm text-gray-900 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-500/10 dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
                                <button type="button" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200" @click="showConfirmPassword = !showConfirmPassword">
                                    <i :class="showConfirmPassword ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                                </button>
                            </div>
                        </label>

                        <div class="md:col-span-2">
                            <button type="submit" :disabled="passwordForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-linear-to-r from-primary-500 to-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-500/20 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary-500/25 disabled:opacity-60">
                                <i class="ti ti-key text-base"></i>
                                {{ passwordForm.processing ? t('Updating...') : t('Update Password') }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</template>
