<script setup lang="ts">
defineProps<{
    currentStep: number
    stepsCompleted: number[]
    totalSteps: number
}>()

const stepLabels = [
    'System Check',
    'License',
    'Database',
    'Site Setup',
    'Admin Account',
    'Install',
]

function circleClass(step: number, current: number, completed: number[]): string {
    if (completed.includes(step)) {
        return 'bg-emerald-600 text-white border-emerald-600'
    }
    if (step === current) {
        return 'bg-white text-emerald-600 border-2 border-emerald-600'
    }
    return 'bg-slate-200 text-slate-500 border-slate-200'
}
</script>

<template>
    <div class="mb-8">
        <!-- Circles + connectors. Labels are absolutely positioned so each step
             column is exactly the circle's size — that lets the flex-1 connectors
             touch the circles and, with items-center, sit on their vertical middle. -->
        <div class="flex items-center sm:pb-6">
            <template v-for="step in totalSteps" :key="step">
                <!-- Circle -->
                <div class="relative shrink-0">
                    <div
                        :class="['flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold transition-colors', circleClass(step, currentStep, stepsCompleted)]"
                    >
                        <svg v-if="stepsCompleted.includes(step)" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>{{ step }}</span>
                    </div>
                    <span class="absolute left-1/2 top-11 hidden -translate-x-1/2 whitespace-nowrap text-xs font-medium text-slate-600 sm:block">
                        {{ stepLabels[step - 1] }}
                    </span>
                </div>

                <!-- Connector -->
                <div
                    v-if="step < totalSteps"
                    class="h-0.5 flex-1"
                    :class="stepsCompleted.includes(step) ? 'bg-emerald-600' : 'bg-slate-200'"
                />
            </template>
        </div>

        <!-- Mobile label (shown below current step) -->
        <p class="mt-2 text-center text-xs font-medium text-slate-600 sm:hidden">
            Step {{ currentStep }}: {{ stepLabels[currentStep - 1] }}
        </p>
    </div>
</template>
