<script setup lang="ts">
defineProps<{
    title: string
    value: string | number
    change?: string
    changeType?: 'up' | 'down' | 'neutral'
    icon?: string
    color?: 'primary' | 'accent' | 'success' | 'warning' | 'danger'
}>()

const colorMap: Record<string, string> = {
    primary: 'from-primary-500/10 to-primary-500/5 text-primary-400 border-primary-500/10',
    accent: 'from-accent-500/10 to-accent-500/5 text-accent-400 border-accent-500/10',
    success: 'from-success-500/10 to-success-500/5 text-success-500 border-success-500/10',
    warning: 'from-warning-500/10 to-warning-500/5 text-warning-500 border-warning-500/10',
    danger: 'from-danger-500/10 to-danger-500/5 text-danger-500 border-danger-500/10',
}
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-2xl p-5 hover:border-gray-300 transition-all duration-300 group shadow-sm">
        <div class="flex items-start justify-between mb-3">
            <p class="text-sm text-gray-400 font-medium">{{ title }}</p>
            <div v-if="color" :class="colorMap[color]" class="w-9 h-9 rounded-xl bg-gradient-to-br flex items-center justify-center border group-hover:scale-110 transition-transform">
                <slot name="icon" />
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 mb-1">{{ value }}</p>
        <p v-if="change" :class="[changeType === 'up' ? 'text-success-500' : changeType === 'down' ? 'text-danger-500' : 'text-gray-500']" class="text-xs font-medium flex items-center gap-1">
            <svg v-if="changeType === 'up'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" /></svg>
            <svg v-if="changeType === 'down'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5l15 15m0 0V8.25m0 11.25H8.25" /></svg>
            {{ change }}
        </p>
    </div>
</template>
