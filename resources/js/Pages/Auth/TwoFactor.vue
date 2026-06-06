<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

interface PageProps {
    twoFactorMethod?: string
    userOtpExpiresAt?: string | null
}

const page = usePage()
const props = page.props as unknown as PageProps
const isOtp = computed(() => props.twoFactorMethod === 'otp')

const form = useForm({
    code: '',
})

const submit = () => {
    form.post(route('two-factor.verify'), {
        onFinish: () => form.reset('code'),
    })
}
</script>

<template>
    <Head :title="$t('Two-Factor Verification')" />

    <div class="auth-page">
        <div class="auth-glow">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-50 via-primary-50/20 to-white"></div>
            <div class="absolute top-1/4 left-1/3 w-80 h-80 bg-primary-100/40 rounded-full blur-3xl"></div>
        </div>

        <div class="w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-500/10">
                    <i v-if="isOtp" class="ti ti-mail text-3xl text-primary-600"></i>
                    <i v-else class="ti ti-shield-lock text-3xl text-primary-600"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $t('Two-Factor Verification') }}</h1>
                <p v-if="isOtp" class="text-gray-500 mt-2 text-sm">{{ $t('Enter the 6-digit verification code sent to your email.') }}</p>
                <p v-else class="text-gray-500 mt-2 text-sm">{{ $t('Enter your authenticator app code or a recovery code.') }}</p>
            </div>

            <div class="auth-card">
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="code" class="auth-label">
                            {{ isOtp ? $t('Verification code') : $t('Authenticator or recovery code') }}
                        </label>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            required
                            autofocus
                            :inputmode="isOtp ? 'numeric' : 'text'"
                            autocomplete="one-time-code"
                            class="auth-input text-center text-lg font-bold tracking-widest"
                            :placeholder="isOtp ? $t('000000') : $t('123456 or ABCDE-FGHIJ')"
                            :maxlength="isOtp ? 6 : 32"
                        />
                        <p v-if="form.errors.code" class="auth-error">{{ form.errors.code }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing || form.code.length < 6" class="auth-btn">
                        <span>{{ form.processing ? $t('Verifying...') : $t('Verify Code') }}</span>
                    </button>

                    <p v-if="isOtp" class="text-center text-sm text-gray-500">
                        {{ $t('Didn\'t receive the code? Check your spam folder.') }}
                    </p>

                    <p class="text-center text-sm text-gray-500">
                        <Link :href="route('login')" class="text-primary-600 hover:text-primary-700 font-semibold">
                            {{ $t('Back to login') }}
                        </Link>
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>
