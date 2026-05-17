<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps<{ token: string; email?: string }>()

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Reset Password" />
    <div class="auth-page">
        <div class="auth-glow"><div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/20 to-surface-950"></div></div>
        <div class="w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white">Set new password</h1>
                <p class="text-gray-500 mt-1 text-sm">Choose a strong, unique password</p>
            </div>
            <div class="auth-card">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="email" class="auth-label">Email</label>
                        <input id="email" v-model="form.email" type="email" required class="auth-input" />
                        <p v-if="form.errors.email" class="auth-error">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="password" class="auth-label">New Password</label>
                        <input id="password" v-model="form.password" type="password" required class="auth-input" placeholder="Min 8 characters" />
                        <p v-if="form.errors.password" class="auth-error">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="auth-label">Confirm Password</label>
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" required class="auth-input" placeholder="••••••••" />
                    </div>
                    <button type="submit" :disabled="form.processing" class="auth-btn">
                        <span>{{ form.processing ? 'Resetting...' : 'Reset Password' }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
