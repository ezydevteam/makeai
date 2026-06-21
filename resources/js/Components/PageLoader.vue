<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const loading = ref(false)
let startHandler: (() => void) | null = null
let finishHandler: (() => void) | null = null

const getAnimation = (): string => {
    // Never show on admin pages
    if (window.location.pathname.startsWith('/admin')) return 'none'
    return document.documentElement.dataset.pageLoading || 'none'
}

onMounted(() => {
    startHandler = () => {
        const anim = getAnimation()
        if (anim !== 'none') {
            loading.value = true
        }
    }
    finishHandler = () => {
        loading.value = false
    }

    router.on('start', startHandler)
    router.on('finish', finishHandler)
})

onUnmounted(() => {
    if (startHandler) router.off('start', startHandler)
    if (finishHandler) router.off('finish', finishHandler)
})
</script>

<template>
    <Teleport to="body">
        <!-- Spinner overlay -->
        <Transition
            v-if="getAnimation() === 'spinner'"
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="loading"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/80 backdrop-blur-sm dark:bg-surface-950/80"
            >
                <div class="flex flex-col items-center gap-3">
                    <div class="h-10 w-10 animate-spin rounded-full border-4 border-primary-200 border-t-primary-600"></div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Loading...</p>
                </div>
            </div>
        </Transition>

        <!-- Skeleton overlay — content area only, header remains visible -->
        <Transition
            v-if="getAnimation() === 'skeleton'"
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="loading"
                class="fixed inset-x-0 bottom-0 z-[9999] overflow-hidden bg-white dark:bg-surface-950"
                style="top: calc(var(--header-height, 64px))"
            >
                <div class="mx-auto max-w-7xl space-y-6 p-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2 space-y-4">
                            <div class="h-8 w-3/4 animate-pulse rounded-lg bg-gray-200 dark:bg-surface-800"></div>
                            <div class="h-4 w-full animate-pulse rounded-lg bg-gray-200 dark:bg-surface-800"></div>
                            <div class="h-4 w-5/6 animate-pulse rounded-lg bg-gray-200 dark:bg-surface-800"></div>
                            <div class="h-4 w-4/6 animate-pulse rounded-lg bg-gray-200 dark:bg-surface-800"></div>
                            <div class="h-40 w-full animate-pulse rounded-xl bg-gray-200 dark:bg-surface-800"></div>
                        </div>
                        <div class="space-y-4">
                            <div class="h-40 w-full animate-pulse rounded-xl bg-gray-200 dark:bg-surface-800"></div>
                            <div class="h-24 w-full animate-pulse rounded-xl bg-gray-200 dark:bg-surface-800"></div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
