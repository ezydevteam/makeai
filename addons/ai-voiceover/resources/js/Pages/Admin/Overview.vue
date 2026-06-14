<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
  stats: {
    total_episodes: number
    processing: number
    completed_today: number
    failed: number
    total_storage: number
    credits_used_today: number
    by_provider: Record<string, number>
  }
}>()

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 MB'
  const mb = bytes / (1024 * 1024)
  if (mb >= 1024) return (mb / 1024).toFixed(1) + ' GB'
  return mb.toFixed(1) + ' MB'
}
</script>

<template>
  <Head :title="t('Voiceover Overview')" />

  <div class="mx-auto max-w-7xl px-6 py-8">
    <h1 class="mb-8 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Voiceover Studio — Overview') }}</h1>

    <!-- Stats cards -->
    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
            <i class="ti ti-headphones text-blue-600 dark:text-blue-400"></i>
          </span>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.total_episodes }}</p>
            <p class="text-xs text-gray-500">{{ t('Total Episodes') }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
            <i class="ti ti-loader text-amber-600 dark:text-amber-400"></i>
          </span>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.processing }}</p>
            <p class="text-xs text-gray-500">{{ t('Processing') }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
            <i class="ti ti-check text-green-600 dark:text-green-400"></i>
          </span>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.completed_today }}</p>
            <p class="text-xs text-gray-500">{{ t('Completed Today') }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30">
            <i class="ti ti-database text-purple-600 dark:text-purple-400"></i>
          </span>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatBytes(props.stats.total_storage) }}</p>
            <p class="text-xs text-gray-500">{{ t('Total Storage') }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Second row -->
    <div class="mb-8 grid gap-4 sm:grid-cols-2">
      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
            <i class="ti ti-coin text-amber-600 dark:text-amber-400"></i>
          </span>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.credits_used_today }}</p>
            <p class="text-xs text-gray-500">{{ t('Credits Used Today') }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
        <div class="flex items-center gap-3">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-red-100 dark:bg-red-900/30">
            <i class="ti ti-exclamation-mark text-red-600 dark:text-red-400"></i>
          </span>
          <div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ props.stats.failed }}</p>
            <p class="text-xs text-gray-500">{{ t('Failed') }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- By provider -->
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-surface-800 dark:bg-gray-900">
      <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('By Provider') }}</h2>
      <div class="space-y-2">
        <div v-for="(count, provider) in props.stats.by_provider" :key="provider" class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2 dark:bg-gray-800/30">
          <span class="text-sm font-medium text-gray-700 dark:text-gray-300 capitalize">{{ provider }}</span>
          <span class="text-sm text-gray-500">{{ count }} {{ t('episodes') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
