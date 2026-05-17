<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

const form = useForm({ code: '' })
const digits = ref(['', '', '', '', '', ''])
const inputs = ref<HTMLInputElement[]>([])

const handleInput = (index: number, event: Event) => {
    const val = (event.target as HTMLInputElement).value.replace(/\D/g, '')
    if (val.length > 1) {
        val.split('').slice(0, 6).forEach((c, i) => { if (i < 6) digits.value[i] = c })
        form.code = digits.value.join('')
        inputs.value[Math.min(val.length - 1, 5)]?.focus()
        return
    }
    digits.value[index] = val
    form.code = digits.value.join('')
    if (val && index < 5) inputs.value[index + 1]?.focus()
}

const handleKeydown = (index: number, e: KeyboardEvent) => {
    if (e.key === 'Backspace' && !digits.value[index] && index > 0) inputs.value[index - 1]?.focus()
}

const submit = () => form.post(route('verification.verify'))
const resend = () => form.post(route('verification.resend'), { preserveState: true })

onMounted(() => inputs.value[0]?.focus())
</script>

<template>
    <Head title="Verify Email" />
    <div class="auth-page">
        <div class="auth-glow">
            <div class="absolute inset-0 bg-gradient-to-br from-surface-950 via-primary-950/20 to-surface-950"></div>
        </div>
        <div class="w-full max-w-md relative z-10">
            <div class="text-center mb-8">
                <div class="w-16 h-16 mx-auto mb-4 bg-primary-500/10 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                </div>
                <h1 class="text-2xl font-bold text-white">Verify your email</h1>
                <p class="text-gray-500 mt-2 text-sm">Enter the 6-digit code we sent to your email</p>
            </div>

            <div class="auth-card">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="flex justify-center gap-3">
                        <input v-for="(_, i) in 6" :key="i" :ref="(el) => { if (el) inputs[i] = el as HTMLInputElement }" :value="digits[i]" @input="handleInput(i, $event)" @keydown="handleKeydown(i, $event)" type="text" inputmode="numeric" maxlength="6" class="w-12 h-14 text-center text-xl font-bold bg-white/5 border border-white/10 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition-all" />
                    </div>
                    <p v-if="form.errors.code" class="text-center text-sm text-danger-500">{{ form.errors.code }}</p>

                    <button type="submit" :disabled="form.processing || form.code.length !== 6" class="auth-btn">
                        <span>Verify Email</span>
                    </button>

                    <button type="button" @click="resend" class="w-full text-center text-sm text-gray-500 hover:text-primary-400 transition-colors">
                        Didn't receive the code? Resend
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
