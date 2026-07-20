<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppModal from '@/Components/UI/AppModal.vue'

const props = withDefaults(defineProps<{
    open: boolean
}>(), {
    open: false,
})

const emit = defineEmits<{
    close: []
}>()

const { t } = useTranslate()
const loginBtnRef = ref<HTMLButtonElement | null>(null)

const signIn = () => {
    router.visit(route('login'))
}

const signUp = () => {
    router.visit(route('register'))
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        nextTick(() => loginBtnRef.value?.focus())
    }
})
</script>

<template>
    <AppModal
        :open="open"
        max-width="max-w-sm"
        :title="t('Sign in required')"
        :subtitle="t('Please sign in or create an account to use this tool.')"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10">
                <svg class="h-7 w-7 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>
            <div class="flex flex-col gap-3">
                <button
                    ref="loginBtnRef"
                    class="w-full rounded-xl bg-gradient-to-r from-primary-600 to-accent-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-500/25 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/35"
                    @click="signIn"
                >
                    {{ t('Sign In') }}
                </button>
                <button
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300 transition-all hover:bg-gray-100 dark:hover:bg-white/10"
                    @click="signUp"
                >
                    {{ t('Create Account') }}
                </button>
            </div>
            <div class="text-center">
                <button
                    class="text-xs text-gray-500 hover:text-gray-400"
                    @click="emit('close')"
                >
                    {{ t('Continue as guest') }}
                </button>
            </div>
        </div>
    </AppModal>
</template>
