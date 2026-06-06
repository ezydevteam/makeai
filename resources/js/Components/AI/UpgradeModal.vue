<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
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
const upgradeBtnRef = ref<HTMLButtonElement | null>(null)

const page = usePage()

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const goToPricing = () => {
    router.visit(route('pricing'))
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
        nextTick(() => upgradeBtnRef.value?.focus())
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
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent-500/10">
                            <svg class="h-7 w-7 text-accent-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>

                        <template v-if="isProAvailable">
                            <h3 class="mb-2 text-lg font-bold text-white">{{ t('Pro feature') }}</h3>
                            <p class="mb-6 text-sm text-gray-400">
                                {{ t('This tool requires a Pro subscription. Upgrade to unlock all advanced features.') }}
                            </p>
                            <button
                                ref="upgradeBtnRef"
                                class="w-full rounded-xl bg-gradient-to-r from-accent-500 to-amber-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-accent-500/25 transition-all hover:-translate-y-0.5 hover:shadow-accent-500/35"
                                @click="goToPricing"
                            >
                                {{ t('View Plans') }}
                            </button>
                        </template>
                        <template v-else>
                            <h3 class="mb-2 text-lg font-bold text-white">{{ t('Feature unavailable') }}</h3>
                            <p class="text-sm text-gray-400">
                                {{ t('Premium features are not available on this installation.') }}
                            </p>
                        </template>

                        <button
                            class="mt-4 text-xs text-gray-500 hover:text-gray-400"
                            @click="emit('close')"
                        >
                            {{ t('Go back') }}
                        </button>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
