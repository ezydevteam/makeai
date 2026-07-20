<script setup lang="ts">
import { ref } from 'vue'
import ErrorAlert from './ErrorAlert.vue'

defineProps<{
    systemCheck: Record<string, any[]>
    allPass: boolean
    formData: Record<string, any>
    error?: string | null
}>()

const confirmed = ref(false)

defineExpose({ getData: () => ({ confirmed: confirmed.value }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">System Requirements</h2>
        <p class="mt-1 text-sm text-slate-500">Verify your server meets the minimum requirements for MakeAI.</p>

        <!-- PHP Version -->
        <div v-for="(checks, category) in systemCheck" :key="category" class="mt-5">
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ category.replace(/_/g, ' ') }}
            </h3>

            <div class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                <div
                    v-for="(check, idx) in checks"
                    :key="idx"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <div class="mr-4 min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-900">{{ check.label }}</p>
                        <p class="text-xs text-slate-500">Required: {{ check.required }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span class="font-mono text-xs text-slate-500">{{ check.current }}</span>

                        <!-- Pass badge -->
                        <span
                            v-if="check.pass"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700"
                        >
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            Pass
                        </span>

                        <!-- Fail badge -->
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="check.severity === 'error'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-amber-100 text-amber-700'"
                        >
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            {{ check.severity === 'error' ? 'Fail' : 'Warning' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global fail warning -->
        <div v-if="!allPass" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold">Some requirements are not met</p>
            <p class="mt-1">Please resolve the failed items above before continuing. Items marked "Warning" can be skipped but may affect functionality.</p>
        </div>

        <!-- Confirmation -->
        <div class="mt-6 rounded-lg border border-slate-200 p-4">
            <label class="flex cursor-pointer items-center gap-3">
                <input
                    v-model="confirmed"
                    type="checkbox"
                    :disabled="!allPass"
                    class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:opacity-50"
                />
                <span class="text-sm text-slate-700">
                    I confirm all system requirements are met
                </span>
            </label>
        </div>
        <ErrorAlert :message="error" />
    </div>
</template>
