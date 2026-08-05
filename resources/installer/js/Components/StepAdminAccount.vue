<script setup lang="ts">
import { reactive, ref } from 'vue'
import ErrorAlert from './ErrorAlert.vue'

const props = defineProps<{
    formData: Record<string, any>
    error?: string | null
}>()

const admin = reactive({
    admin_name: props.formData?.step_5?.admin_name ?? '',
    admin_email: props.formData?.step_5?.admin_email ?? '',
    // Never prefilled: the backend only sends a redacted mask, so the password
    // must be re-entered on back-navigation (standard, secure installer behavior).
    admin_password: '',
    admin_password_confirmation: '',
})

const showPassword = ref(false)

defineExpose({ getData: () => ({ ...admin }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Admin Account</h2>
        <p class="mt-1 text-sm text-slate-500">Create your super administrator account. You'll use this to log into the admin panel.</p>

        <ErrorAlert :message="error" />

        <div class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Full Name</span>
                <input
                    v-model="admin.admin_name"
                    type="text"
                    placeholder="John Doe"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Email Address</span>
                <input
                    v-model="admin.admin_email"
                    type="email"
                    placeholder="admin@example.com"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                />
            </label>

            <div class="relative">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Password</span>
                    <div class="relative mt-1.5">
                        <input
                            v-model="admin.admin_password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="Minimum 8 characters"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 pr-10 text-sm"
                        />
                        <button
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            @click="showPassword = !showPassword"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path v-if="showPassword" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </label>
            </div>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Confirm Password</span>
                <input
                    v-model="admin.admin_password_confirmation"
                    type="password"
                    placeholder="Re-enter your password"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                />
            </label>
        </div>
    </div>
</template>
