<script setup lang="ts">
defineProps<{
    currentStep: number
    stepsCompleted: number[]
    totalSteps: number
}>()

const stepLabels = [
    'System Check',
    'Database',
    'Site Setup',
    'License',
    'Admin Account',
    'Demo Content',
    'Install',
]

function circleClass(step: number, current: number, completed: number[]): string {
    if (completed.includes(step)) {
        return 'bg-blue-600 text-white border-blue-600'
    }
    if (step === current) {
        return 'bg-white text-blue-600 border-2 border-blue-600'
    }
    return 'bg-gray-200 text-gray-500 border-gray-200 dark:bg-surface-700 dark:text-gray-400 dark:border-surface-600'
}
</script>

<template>
    <div class="mb-8">
        <!-- Circles + Lines -->
        <div class="flex items-center justify-between">
            <template v-for="step in totalSteps" :key="step">
                <!-- Circle -->
                <div class="flex flex-col items-center relative z-10">
                    <div
                        :class="['flex items-center justify-center h-10 w-10 rounded-full text-sm font-semibold border transition-colors', circleClass(step, currentStep, stepsCompleted)]"
                    >
                        <svg v-if="stepsCompleted.includes(step)" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>{{ step }}</span>
                    </div>
                    <span class="mt-2 text-xs font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap hidden sm:block">
                        {{ stepLabels[step - 1] }}
                    </span>
                </div>

                <!-- Line connector -->
                <div
                    v-if="step < totalSteps"
                    class="flex-1 h-0.5 mx-1"
                    :class="stepsCompleted.includes(step) ? 'bg-blue-600' : 'bg-gray-200 dark:bg-surface-600'"
                />
            </template>
        </div>

        <!-- Mobile labels (shown below current step) -->
        <p class="mt-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400 sm:hidden">
            Step {{ currentStep }}: {{ stepLabels[currentStep - 1] }}
        </p>
    </div>
</template>
