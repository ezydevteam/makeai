<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({ email: '' })
const submit = () => form.post(route('password.email'))
</script>

<template>
    <Head :title="$t('Forgot Password')" />
    <div class="auth-page">
        <div class="auth-glow"><div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/20 to-surface-950"></div></div>
        <div class="w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white">{{ $t('Reset password') }}</h1>
                <p class="text-gray-500 mt-1 text-sm">{{ $t('Enter your email to receive a reset code') }}</p>
            </div>
            <div class="auth-card">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label for="email" class="auth-label">{{ $t('Email') }}</label>
                        <input id="email" v-model="form.email" type="email" required autofocus class="auth-input" :placeholder="$t('you@example.com')" />
                        <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing" class="auth-btn">
                        <span>{{ form.processing ? $t('Sending...') : $t('Send Reset Code') }}</span>
                    </button>
                </form>
                <p class="text-center mt-6 text-sm text-gray-500">
                    <Link :href="route('login')" class="text-primary-400 hover:text-primary-300 transition-colors">{{ $t('Back to login') }}</Link>
                </p>
            </div>
        </div>
    </div>
</template>
