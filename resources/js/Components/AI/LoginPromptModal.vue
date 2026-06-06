<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = withDefaults(defineProps<{
    open: boolean
}>(), {
    open: false,
})

const emit = defineEmits<{
    close: []
}>()

const { t } = useTranslate()
const modalRef = ref<HTMLElement | null>(null)
const loginBtnRef = ref<HTMLButtonElement | null>(null)

const signIn = () => {
    router.visit(route('login'))
}

const signUp = () => {
    router.visit(route('register'))
}

const handleKeydown = (event: KeyboardEvent) => {
    if (!props.open) {
        return
    }
    if (event.key === 'Escape') {
        emit('close')
    }
}

watch(() => props.open, (isOpen) => {
    document.body.classList.toggle('overflow-hidden', isOpen)
    if (isOpen) {
        nextTick(() => loginBtnRef.value?.focus())
    }
})

onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    document.body.classList.remove('overflow-hidden')
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="emit('close')"
            >
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="translate-y-2 scale-95 opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="translate-y-0 scale-100 opacity-100"
                    leave-to-class="translate-y-2 scale-95 opacity-0"
                >
                    <div ref="modalRef" class="w-full max-w-sm rounded-2xl border border-white/5 bg-surface-900 p-8 text-center shadow-2xl">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/10">
                            <svg class="h-7 w-7 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <h3 class="mb-2 text-lg font-bold text-white">{{ t('Sign in required') }}</h3>
                        <p class="mb-6 text-sm text-gray-400">
                            {{ t('Please sign in or create an account to use this tool.') }}
                        </p>
                        <div class="flex flex-col gap-3">
                            <button
                                ref="loginBtnRef"
                                class="w-full rounded-xl bg-gradient-to-r from-primary-600 to-accent-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-primary-500/25 transition-all hover:-translate-y-0.5 hover:shadow-primary-500/35"
                                @click="signIn"
                            >
                                {{ t('Sign In') }}
                            </button>
                            <button
                                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-gray-300 transition-all hover:bg-white/10"
                                @click="signUp"
                            >
                                {{ t('Create Account') }}
                            </button>
                        </div>
                        <button
                            class="mt-4 text-xs text-gray-500 hover:text-gray-400"
                            @click="emit('close')"
                        >
                            {{ t('Continue as guest') }}
                        </button>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
