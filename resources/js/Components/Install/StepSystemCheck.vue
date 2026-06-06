<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

defineProps<{
    systemCheck: Record<string, any[]>
    allPass: boolean
    formData: Record<string, any>
}>()

const confirmed = ref(false)
const { t } = useTranslate()

defineExpose({ getData: () => ({ confirmed: confirmed.value }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('System Requirements') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ t('Verify your server meets the minimum requirements for :app.', { app: t('Application') }) }}</p>

        <!-- PHP Version -->
        <div v-for="(checks, category) in systemCheck" :key="category" class="mt-5">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">
                {{ category.replace(/_/g, ' ') }}
            </h3>

            <div class="rounded-lg border border-gray-200 dark:border-surface-700 divide-y divide-gray-100 dark:divide-surface-700">
                <div
                    v-for="(check, idx) in checks"
                    :key="idx"
                    class="flex items-center justify-between px-4 py-3"
                >
                    <div class="flex-1 min-w-0 mr-4">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ check.label }}</p>
                        <p class="text-xs text-gray-500">{{ t('Required') }}: {{ check.required }}</p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-gray-500 font-mono">{{ check.current }}</span>

                        <!-- Pass badge -->
                        <span
                            v-if="check.pass"
                            class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300"
                        >
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ t('Pass') }}
                        </span>

                        <!-- Fail badge -->
                        <span
                            v-else
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="check.severity === 'error'
                                ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'"
                        >
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            {{ check.severity === 'error' ? t('Fail') : t('Warning') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global fail warning -->
        <div v-if="!allPass" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300">
            <p class="font-semibold">{{ t('Some requirements are not met') }}</p>
            <p class="mt-1">{{ t('Please resolve the failed items above before continuing. Items marked "Warning" can be skipped but may affect functionality.') }}</p>
        </div>

        <!-- Confirmation -->
        <div class="mt-6 rounded-lg border border-gray-200 dark:border-surface-700 p-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    v-model="confirmed"
                    type="checkbox"
                    :disabled="!allPass"
                    class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:opacity-50"
                />
                <span class="text-sm text-gray-700 dark:text-gray-300">
                    {{ t('I confirm all system requirements are met') }}
                </span>
            </label>
        </div>
    </div>
</template>
