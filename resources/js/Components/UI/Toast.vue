<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'

export interface ToastItem {
    id: number
    message: string
    type: 'success' | 'error' | 'warning' | 'info'
    title?: string
    duration: number
    removing: boolean
    paused: boolean
}

const props = defineProps<{
    toast: ToastItem
}>()

const emit = defineEmits<{
    close: [id: number]
    pause: [id: number]
    resume: [id: number]
}>()

const progress = ref(100)
const elapsedMs = ref(0)
let rafId: number | null = null
let lastFrame: number | null = null

function tick(now: number) {
    if (!lastFrame) lastFrame = now

    if (!props.toast.paused) {
        const delta = now - lastFrame
        elapsedMs.value = Math.min(elapsedMs.value + delta, props.toast.duration)
        progress.value = Math.max(0, 100 - (elapsedMs.value / props.toast.duration) * 100)

        if (progress.value <= 0) {
            emit('close', props.toast.id)
            return
        }
    }

    lastFrame = now
    rafId = requestAnimationFrame(tick)
}

onMounted(() => { rafId = requestAnimationFrame(tick) })
onUnmounted(() => { if (rafId) cancelAnimationFrame(rafId) })

const iconColors = computed<Record<string, string>>(() => {
    switch (props.toast.type) {
        case 'success': return { stroke: '#059669', fill: '#059669' }
        case 'error': return { stroke: '#dc2626', fill: '#dc2626' }
        case 'warning': return { stroke: '#d97706', fill: '#d97706' }
        case 'info': return { stroke: '#4f46e5', fill: '#4f46e5' }
    }
})

const icon = computed(() => {
    switch (props.toast.type) {
        case 'success': return {
            path: ['M5 13l4 4L19 7'],
            stroke: true,
        }
        case 'error': return {
            path: ['M6 6l12 12', 'M18 6L6 18'],
            stroke: true,
        }
        case 'warning': return {
            path: ['M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
            stroke: false,
            fill: 'currentColor',
        }
        case 'info': return {
            path: ['M12 12v5m0-8h.01M12 3a9 9 0 100 18 9 9 0 000-18z'],
            stroke: true,
        }
    }
})

const colorClasses = computed(() => {
    switch (props.toast.type) {
        case 'success': return {
            border: 'border-emerald-200 dark:border-emerald-400/30',
            glow: 'shadow-emerald-200/50 dark:shadow-emerald-500/20',
            iconBg: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
            progress: 'bg-emerald-500',
        }
        case 'error': return {
            border: 'border-red-200 dark:border-red-400/30',
            glow: 'shadow-red-200/50 dark:shadow-red-500/20',
            iconBg: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
            progress: 'bg-red-500',
        }
        case 'warning': return {
            border: 'border-amber-200 dark:border-amber-400/30',
            glow: 'shadow-amber-200/50 dark:shadow-amber-500/20',
            iconBg: 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
            progress: 'bg-amber-500',
        }
        case 'info': return {
            border: 'border-indigo-200 dark:border-indigo-400/30',
            glow: 'shadow-indigo-200/50 dark:shadow-indigo-500/20',
            iconBg: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
            progress: 'bg-indigo-500',
        }
    }
})

function dismiss() {
    emit('close', props.toast.id)
}
</script>

<template>
    <div
        class="toast-item group relative flex w-full max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg backdrop-blur-xl transition-all duration-200 hover:scale-[1.02] bg-white/90 text-gray-900 border-gray-200 shadow-gray-200/50 dark:bg-gray-900/92 dark:text-gray-100 dark:border-gray-700/30 dark:shadow-gray-950/50"
        :class="[colorClasses.border, colorClasses.glow, toast.removing ? 'toast-exit' : '']"
        @mouseenter="emit('pause', toast.id)"
        @mouseleave="emit('resume', toast.id)"
    >
        <!-- Icon -->
        <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg" :class="colorClasses.iconBg">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" :stroke="icon.stroke ? iconColors.stroke : undefined" :stroke-width="icon.stroke ? '2' : '0'" stroke-linecap="round" stroke-linejoin="round">
                <template v-if="icon.stroke">
                    <path v-for="(p, i) in icon.path" :key="i" :d="p" />
                </template>
                <path v-else :d="icon.path[0]" :fill="iconColors.fill" />
            </svg>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
            <p v-if="toast.title" class="text-sm font-semibold text-gray-900 dark:text-white/90">{{ toast.title }}</p>
            <p class="text-sm text-gray-600 dark:text-white/70 leading-snug">{{ toast.message }}</p>
        </div>

        <!-- Close -->
        <button
            type="button"
            class="flex-shrink-0 -mt-0.5 -mr-1 p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:text-white/40 dark:hover:text-white/80 dark:hover:bg-white/10 transition-colors"
            @click="dismiss"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18" />
            </svg>
        </button>

        <!-- Progress bar -->
        <div class="absolute bottom-0 left-0 right-0 h-0.5 rounded-b-xl overflow-hidden">
            <div
                class="h-full rounded-b-xl transition-[width] duration-75 ease-linear"
                :class="[colorClasses.progress, toast.paused ? '!transition-none' : '']"
                :style="{ width: progress + '%' }"
            />
        </div>
    </div>
</template>
