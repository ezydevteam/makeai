<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import AppModal from '@/Components/UI/AppModal.vue'

const props = defineProps<{
  ulidA: string
  ulidB: string
}>()

const emit = defineEmits<{ close: [] }>()

interface DiffItem {
  word: string
  status: 'added' | 'removed' | 'unchanged'
}

const diffItems = ref<DiffItem[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await axios.post(route('user.dashboard.history.diff', { historyA: props.ulidA, historyB: props.ulidB }))
    diffItems.value = data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppModal
    open
    max-width="max-w-2xl"
    title="Diff View"
    @close="emit('close')"
  >
    <div v-if="loading" class="py-8 text-center text-gray-400">Loading diff...</div>

    <div v-else class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
      <p class="text-sm leading-relaxed">
        <span
          v-for="(item, idx) in diffItems"
          :key="idx"
          :class="{
            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': item.status === 'added',
            'bg-red-100 text-red-800 line-through dark:bg-red-900/30 dark:text-red-300': item.status === 'removed',
            'text-gray-700 dark:text-gray-300': item.status === 'unchanged',
          }"
          class="rounded px-0.5"
        >{{ item.word }} </span>
      </p>
    </div>
  </AppModal>
</template>
