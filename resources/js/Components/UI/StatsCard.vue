<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    title: string
    value: string | number
    comparison?: string
    comparisonDetail?: string
    comparisonType?: 'up' | 'down' | 'neutral'
    icon?: string
    color?: 'primary' | 'accent' | 'success' | 'warning' | 'danger' | 'pink'
    sparkline?: number[]
}>()

const colorMap: Record<string, string> = {
    primary: 'from-primary-500/10 to-primary-500/5 text-primary-400 border-primary-500/10',
    accent: 'from-accent-500/10 to-accent-500/5 text-accent-400 border-accent-500/10',
    success: 'from-success-500/10 to-success-500/5 text-success-500 border-success-500/10',
    warning: 'from-warning-500/10 to-warning-500/5 text-warning-500 border-warning-500/10',
    danger: 'from-danger-500/10 to-danger-500/5 text-danger-500 border-danger-500/10',
    pink: 'from-pink-500/10 to-pink-500/5 text-pink-500 border-pink-500/10',
}

const sparklineColorMap: Record<string, string> = {
    primary: 'text-primary-500',
    accent: 'text-accent-500',
    success: 'text-success-500',
    warning: 'text-warning-500',
    danger: 'text-danger-500',
    pink: 'text-pink-500',
}

function sampleSparkline(values: number[], maxPoints = 18): number[] {
    if (values.length <= maxPoints) {
        return values
    }

    const step = (values.length - 1) / (maxPoints - 1)
    const sampled: number[] = []

    for (let index = 0; index < maxPoints; index += 1) {
        sampled.push(values[Math.round(index * step)])
    }

    return sampled
}

const sparklinePath = computed(() => {
    const values = sampleSparkline(props.sparkline ?? [])

    if (values.length < 2) {
        return ''
    }

    const min = Math.min(...values)
    const max = Math.max(...values)
    const range = max - min || 1
    const width = 120
    const height = 36

    return values
        .map((value, index) => {
            const x = (index / (values.length - 1)) * width
            const y = height - ((value - min) / range) * height
            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`
        })
        .join(' ')
})

const { t } = useTranslate()
</script>

<template>
    <div class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 hover:border-gray-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700">
        <div class="mb-3 flex items-start justify-between gap-3">
            <p class="text-sm font-medium text-gray-400">{{ t(title) }}</p>
            <div v-if="color" :class="colorMap[color]" class="flex h-9 w-9 items-center justify-center rounded-xl border bg-gradient-to-br transition-transform group-hover:scale-110">
                <slot name="icon" />
            </div>
        </div>
        <p class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">{{ value }}</p>
        <div v-if="sparkline && sparkline.length > 1" :class="[color ? sparklineColorMap[color] : 'text-gray-400']" class="w-full">
            <svg viewBox="0 0 120 36" preserveAspectRatio="none" class="block h-9 w-full overflow-visible">
                <path
                    v-if="sparklinePath"
                    :d="`${sparklinePath} L 120 36 L 0 36 Z`"
                    fill="currentColor"
                    opacity="0.08"
                />
                <path
                    v-if="sparklinePath"
                    :d="sparklinePath"
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                />
            </svg>
        </div>
        <p v-if="comparison" :class="[comparisonType === 'up' ? 'text-success-500' : comparisonType === 'down' ? 'text-danger-500' : 'text-gray-500 dark:text-gray-400']" class="mt-2 flex items-center gap-1 text-xs font-medium">
            <svg v-if="comparisonType === 'up'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" /></svg>
            <svg v-else-if="comparisonType === 'down'" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5l15 15m0 0V8.25m0 11.25H8.25" /></svg>
            <span>{{ comparison }}</span>
            <span v-if="comparisonDetail" class="text-gray-400 dark:text-gray-500">{{ comparisonDetail }}</span>
        </p>
    </div>
</template>
