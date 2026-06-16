<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { useFlashToasts } from '@/Composables/useToastr'

useFlashToasts()

const props = defineProps<{
    method?: 'totp' | 'email'
}>()

const form = useForm({
    code: '',
})

const inputs = ref<HTMLInputElement[]>([])
const digits = ref(['', '', '', '', '', ''])
const isTotp = computed(() => props.method === 'totp')

const handleInput = (index: number, event: Event) => {
    const target = event.target as HTMLInputElement
    const value = target.value.replace(/\D/g, '')

    if (value.length > 1) {
        // Paste handler
        const chars = value.split('').slice(0, 6)
        chars.forEach((char, i) => {
            if (i < 6) digits.value[i] = char
        })
        form.code = digits.value.join('')
        const lastIndex = Math.min(chars.length - 1, 5)
        inputs.value[lastIndex]?.focus()
        return
    }

    digits.value[index] = value
    form.code = digits.value.join('')

    if (value && index < 5) {
        inputs.value[index + 1]?.focus()
    }
}

const handleKeydown = (index: number, event: KeyboardEvent) => {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus()
    }
}

const submit = () => {
    form.post(route('admin.2fa.verify'))
}

onMounted(() => {
    inputs.value[0]?.focus()
})
</script>

<template>
    <Head :title="$t('Two-Factor Authentication')" />

    <div class="min-h-screen bg-surface-950 flex items-center justify-center p-4">
        <div class="fixed inset-0 -z-10">
            <div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/30 to-surface-950"></div>
            <div class="absolute top-1/3 left-1/4 w-64 h-64 bg-primary-600/8 rounded-full blur-3xl"></div>
        </div>

        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="w-16 h-16 mx-auto mb-4 bg-primary-500/10 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white font-heading">{{ $t('Two-Factor Verification') }}</h1>
                <p class="text-gray-500 mt-2 text-sm">
                    {{ isTotp ? $t('Enter your authenticator app code or a recovery code.') : $t('Enter the 6-digit code sent to your email.') }}
                </p>
            </div>

            <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-2xl p-8 shadow-2xl">
                <form @submit.prevent="submit" class="space-y-6">
                    <div v-if="isTotp">
                        <label for="code" class="mb-1.5 block text-sm font-medium text-gray-300">{{ $t('Authenticator or recovery code') }}</label>
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            required
                            autofocus
                            inputmode="text"
                            autocomplete="one-time-code"
                            class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center text-lg font-bold tracking-widest text-white transition-all duration-200 focus:border-primary-500/50 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                            :placeholder="$t('123456 or ABCDE-FGHIJ')"
                        />
                    </div>

                    <!-- OTP Inputs -->
                    <div v-else class="flex justify-center gap-3">
                        <input
                            v-for="(_, i) in 6"
                            :key="i"
                            :ref="(el) => { if (el) inputs[i] = el as HTMLInputElement }"
                            :value="digits[i]"
                            @input="handleInput(i, $event)"
                            @keydown="handleKeydown(i, $event)"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            class="w-12 h-14 text-center text-xl font-bold bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500/50 transition-all duration-200"
                        />
                    </div>

                    <p v-if="form.errors.code" class="text-center text-sm text-danger-500">{{ form.errors.code }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length < 6"
                        class="w-full py-3 bg-gradient-to-r from-primary-600 to-accent-600 text-white rounded-xl font-semibold transition-all duration-200 shadow-lg shadow-primary-600/25 hover:shadow-xl hover:shadow-primary-500/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <svg v-if="form.processing" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ form.processing ? $t('Verifying...') : $t('Verify Code') }}</span>
                    </button>

                    <Link :href="route('admin.login')" class="block text-center text-sm text-gray-500 hover:text-gray-300 transition-colors">
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
