<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import PhoneInput from '@/Components/UI/PhoneInput.vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
interface CountryOption {
    value: string
    label: string
}

const user = computed(() => page.props.auth?.user as any)
const countries = computed(() => (page.props.countries as CountryOption[]) ?? [])
const timezones = computed(() => (page.props.timezones as CountryOption[]) ?? [])
const phoneVerificationEnabled = computed(() => Boolean(page.props.phoneVerificationEnabled))
// Admin requires a phone number and this user hasn't provided/verified one yet.
const phoneActionRequired = computed(
    () => Boolean(page.props.phoneRequired) && !page.props.phoneRequirementMet,
)

const profileForm = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
    country: user.value?.country ?? '',
    profession: user.value?.profession ?? '',
    phone: user.value?.phone ?? '',
    phone_country: user.value?.phone_country ?? (user.value?.country ?? 'US'),
    timezone: user.value?.timezone ?? 'UTC',
    brand_voice: user.value?.brand_voice ?? '',
})

const brandVoiceEnabled = computed(() => Boolean(page.props.brandVoiceEnabled))

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const avatarForm = useForm({ avatar: null as File | null })
const avatarPreview = ref<string | null>(user.value?.avatar ? mediaUrl(user.value.avatar) : null)

// ─── Phone verification (SMS OTP) ───────────────────────────────
const sendOtpForm = useForm({})
const verifyOtpForm = useForm({ code: '' })
const otpSent = ref(false)

// The saved phone is what an OTP is sent to; if the form holds unsaved edits,
// the user must save before verifying so the code isn't sent to a stale number.
const phoneDirty = computed(
    () =>
        (profileForm.phone ?? '') !== (user.value?.phone ?? '') ||
        (profileForm.phone_country ?? '') !== (user.value?.phone_country ?? ''),
)

const sendPhoneOtp = () => {
    sendOtpForm.post(route('user.dashboard.profile.phone.send-otp'), {
        preserveScroll: true,
        onSuccess: (page) => {
            // Only reveal the OTP field when the code was actually sent. A failed
            // send (e.g. gateway rejected) still returns a 2xx redirect with a
            // flash error, so gate on the success flash rather than onSuccess alone.
            const flash = (page.props.flash ?? {}) as { success?: string | null; error?: string | null }
            otpSent.value = Boolean(flash.success) && !flash.error
        },
    })
}

const verifyPhoneOtp = () => {
    verifyOtpForm.post(route('user.dashboard.profile.phone.verify-otp'), {
        preserveScroll: true,
        onSuccess: () => {
            otpSent.value = false
            verifyOtpForm.reset()
        },
    })
}

const showCurrentPw = ref(false)
const showNewPw = ref(false)
const showConfirmPw = ref(false)

const onAvatarChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    avatarForm.avatar = file
    avatarPreview.value = URL.createObjectURL(file)
}

const uploadAvatar = () => {
    avatarForm.post(route('user.dashboard.avatar.update'), {
        forceFormData: true,
        onSuccess: () => {
            avatarForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="$t('Profile')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('My Profile') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('Manage your profile information.') }}</p>
        </div>

        <!-- Required-phone prompt: the rest of the app stays blocked until this is done -->
        <div v-if="phoneActionRequired" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/20">
            <i class="ti ti-phone-plus mt-0.5 text-xl text-amber-600 dark:text-amber-400"></i>
            <div class="min-w-0">
                <p class="text-sm font-bold text-amber-950 dark:text-amber-100">{{ $t('Phone number required') }}</p>
                <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                    <template v-if="phoneVerificationEnabled">
                        {{ $t('Add your phone number below and verify it with the code we text you. Other pages stay locked until it is verified.') }}
                    </template>
                    <template v-else>
                        {{ $t('Add your phone number below and save to continue. Other pages stay locked until then.') }}
                    </template>
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Left column: Avatar + Password -->
            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-4">{{ $t('Avatar') }}</h2>
                    <div class="flex items-center gap-5">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-100 dark:bg-gray-800 dark:border-gray-900">
                            <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover" alt="avatar" />
                            <div v-else class="flex h-full w-full items-center justify-center text-3xl font-bold text-gray-400">{{ (user?.name || '?')[0]?.toUpperCase() }}</div>
                        </div>
                        <form @submit.prevent="uploadAvatar" class="flex-1">
                            <input id="avatar-upload" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                            <label for="avatar-upload" class="inline-block cursor-pointer rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                                {{ $t('Choose photo') }}
                            </label>
                            <button v-if="avatarForm.avatar" type="submit" :disabled="avatarForm.processing" class="ml-3 btn-primary disabled:opacity-60">
                                {{ avatarForm.processing ? $t('Uploading...') : $t('Upload') }}
                            </button>
                            <p v-if="avatarForm.errors.avatar" class="mt-1.5 text-xs text-red-500">{{ avatarForm.errors.avatar }}</p>
                        </form>
                    </div>
                </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-5">{{ $t('Change password') }}</h2>
                    <form @submit.prevent="passwordForm.put(route('user.dashboard.password.update'))" class="space-y-4">
                        <div>
                            <label for="current-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Current password') }}</label>
                            <div class="relative">
                                <input id="current-pw" v-model="passwordForm.current_password" :type="showCurrentPw ? 'text' : 'password'" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="showCurrentPw = !showCurrentPw">
                                    <i :class="showCurrentPw ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.current_password }}</p>
                        </div>
                        <div>
                            <label for="new-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('New password') }}</label>
                            <div class="relative">
                                <input id="new-pw" v-model="passwordForm.password" :type="showNewPw ? 'text' : 'password'" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="showNewPw = !showNewPw">
                                    <i :class="showNewPw ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                                </button>
                            </div>
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label for="confirm-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Confirm new password') }}</label>
                            <div class="relative">
                                <input id="confirm-pw" v-model="passwordForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="showConfirmPw = !showConfirmPw">
                                    <i :class="showConfirmPw ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" :disabled="passwordForm.processing" class="btn-primary disabled:opacity-60">
                            {{ passwordForm.processing ? $t('Changing...') : $t('Change password') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right column: Profile info -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-5">{{ $t('Profile information') }}</h2>
                <form @submit.prevent="profileForm.put(route('user.dashboard.profile.update'))" class="space-y-4">
                    <div>
                        <label for="profile-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Name') }}</label>
                        <input id="profile-name" v-model="profileForm.name" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">{{ profileForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="profile-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Email') }}</label>
                        <input id="profile-email" v-model="profileForm.email" type="email" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">{{ profileForm.errors.email }}</p>
                    </div>
                    <div>
                        <PhoneInput
                            v-model="profileForm.phone"
                            v-model:country="profileForm.phone_country"
                            :label="$t('Phone number')"
                            :error="profileForm.errors.phone"
                        />

                        <!-- Phone verification: only when an SMS gateway is active -->
                        <div v-if="phoneVerificationEnabled" class="mt-2">
                            <!-- Verified -->
                            <span v-if="user?.phone_verified && !phoneDirty" class="inline-flex items-center gap-1.5 text-xs font-medium text-green-600 dark:text-green-400">
                                <i class="ti ti-circle-check-filled"></i>
                                {{ $t('Verified') }}
                            </span>

                            <!-- Not verified (only shown when a phone is saved) -->
                            <template v-else-if="user?.phone">
                                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                        <i class="ti ti-alert-triangle"></i>
                                        {{ $t('Not verified') }}
                                    </span>
                                    <button
                                        v-if="!phoneDirty"
                                        type="button"
                                        :disabled="sendOtpForm.processing"
                                        class="text-xs font-semibold text-primary-600 hover:underline disabled:opacity-60 dark:text-primary-400"
                                        @click="sendPhoneOtp"
                                    >
                                        {{ sendOtpForm.processing ? $t('Sending...') : (otpSent ? $t('Resend code') : $t('Verify now')) }}
                                    </button>
                                    <span v-else class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $t('Save your changes to verify this number.') }}
                                    </span>
                                </div>

                                <!-- OTP entry -->
                                <div v-if="otpSent && !phoneDirty" class="mt-3">
                                    <label for="phone-otp" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                        {{ $t('Enter the 6-digit code sent to your phone') }}
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="phone-otp"
                                            v-model="verifyOtpForm.code"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="one-time-code"
                                            maxlength="6"
                                            class="w-32 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm tracking-[0.3em] text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                            @keyup.enter="verifyPhoneOtp"
                                        />
                                        <button
                                            type="button"
                                            :disabled="verifyOtpForm.processing || !verifyOtpForm.code"
                                            class="btn-primary !rounded-xl disabled:opacity-60"
                                            @click="verifyPhoneOtp"
                                        >
                                            {{ verifyOtpForm.processing ? $t('Verifying...') : $t('Confirm') }}
                                        </button>
                                    </div>
                                    <p v-if="verifyOtpForm.errors.code" class="mt-1 text-xs text-red-500">{{ verifyOtpForm.errors.code }}</p>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label for="profile-profession" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Profession') }}</label>
                        <input id="profile-profession" v-model="profileForm.profession" type="text" :placeholder="$t('e.g. Designer, Student, Developer')" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        <p v-if="profileForm.errors.profession" class="mt-1 text-xs text-red-500">{{ profileForm.errors.profession }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <AppSelect
                                v-model="profileForm.country"
                                :options="countries"
                                :label="$t('Country')"
                                :placeholder="$t('Select country')"
                                :error="profileForm.errors.country"
                                live-search
                            />
                        </div>
                        <div>
                            <AppSelect
                                v-model="profileForm.timezone"
                                :options="timezones"
                                :label="$t('Timezone')"
                                :placeholder="$t('Select timezone')"
                                :error="profileForm.errors.timezone"
                                live-search
                            />
                        </div>
                    </div>
                    <div v-if="brandVoiceEnabled">
                        <label for="profile-brand-voice" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            {{ $t('Brand Voice') }}
                            <span class="text-gray-400 font-normal">({{ $t('optional') }})</span>
                        </label>
                        <textarea
                            id="profile-brand-voice"
                            v-model="profileForm.brand_voice"
                            rows="4"
                            :maxlength="2000"
                            :placeholder="$t('Describe your brand — tone, style, words to use or avoid, audience. Tools that support brand voice will follow it.')"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        ></textarea>
                        <p class="mt-1 text-xs text-gray-400">{{ $t('Applied automatically on tools that support brand voice. You can toggle it off per generation.') }}</p>
                        <p v-if="profileForm.errors.brand_voice" class="mt-1 text-xs text-red-500">{{ profileForm.errors.brand_voice }}</p>
                    </div>
                    <button type="submit" :disabled="profileForm.processing" class="btn-primary disabled:opacity-60">
                        {{ profileForm.processing ? $t('Saving...') : $t('Save changes') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
