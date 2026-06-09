<script setup lang="ts">
import UserLayout from '@/Layouts/UserLayout.vue'

defineOptions({ layout: UserLayout })

interface Snapshot {
  prompt: string
  output_left: string
  output_right: string
  params_left: { provider: string; model: string; temperature: number }
  params_right: { provider: string; model: string; temperature: number }
  created_at: string
}

const props = defineProps<{ snapshot: Snapshot }>()
</script>

<template>
  <div class="mx-auto max-w-screen-xl px-4 py-6">
    <div class="mb-4">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">Shared Playground Snapshot</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">{{ new Date(snapshot.created_at).toLocaleString() }}</p>
    </div>

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Prompt</p>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ snapshot.prompt }}</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-2 flex gap-2 text-[10px] text-gray-400">
          <span>{{ snapshot.params_left.provider }}</span>
          <span>{{ snapshot.params_left.model }}</span>
          <span>T: {{ snapshot.params_left.temperature }}</span>
        </div>
        <pre class="whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ snapshot.output_left }}</pre>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-2 flex gap-2 text-[10px] text-gray-400">
          <span>{{ snapshot.params_right.provider }}</span>
          <span>{{ snapshot.params_right.model }}</span>
          <span>T: {{ snapshot.params_right.temperature }}</span>
        </div>
        <pre class="whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ snapshot.output_right }}</pre>
      </div>
    </div>
  </div>
</template>
