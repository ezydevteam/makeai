<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('admin.password.email'))
}
</script>

<template>
    <Head :title="$t('Admin Password Reset')" />

    <div class="flex min-h-screen items-center justify-center bg-surface-950 p-4">
        <div class="fixed inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/30 to-surface-950"></div>
            <div class="absolute left-1/3 top-1/4 h-80 w-80 rounded-full bg-primary-600/8 blur-3xl"></div>
        </div>

        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-500/10">
                    <i class="ti ti-lock-question text-3xl text-primary-400"></i>
                </div>
                <h1 class="font-heading text-2xl font-bold text-white">{{ $t('Reset Admin Password') }}</h1>
                <p class="mt-2 text-sm text-gray-500">{{ $t('Enter your admin email to receive a secure reset code.') }}</p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur-xl">
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-300">{{ $t('Email') }}</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-white transition-all duration-200 focus:border-primary-500/50 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                            :placeholder="$t('admin@example.com')"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-danger-500">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-accent-600 py-3 font-semibold text-white shadow-lg shadow-primary-600/25 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary-500/30 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0"
                    >
                        <svg v-if="form.processing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ form.processing ? $t('Sending...') : $t('Send Reset Code') }}</span>
                    </button>

                    <Link :href="route('admin.login')" class="block text-center text-sm text-gray-500 transition-colors hover:text-gray-300">
                        {{ $t('Back to login') }}
                    </Link>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-heading {
    font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
}
</style>
