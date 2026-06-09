<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()

interface ToolOption {
  slug: string
  name: string
}

const collection = page.props.collection as any
const tools = computed(() => (page.props.tools as any[]) ?? [])
const availableTools = computed(() => (page.props.availableTools as ToolOption[]) ?? [])
const addingTool = ref(false)
const selectedTool = ref('')

const removingTool = ref<string | null>(null)
const deleting = ref(false)

const toolOptions = computed(() => {
  const existing = new Set(tools.value.map((t: any) => t.slug))
  return availableTools.value.filter(at => !existing.has(at.slug)).map(at => ({ value: at.slug, label: at.name }))
})

function addTool() {
  if (!selectedTool.value) return
  addingTool.value = true
  router.post(route('user.dashboard.collections.tools.add', collection.ulid), { tool_slug: selectedTool.value }, {
    onFinish: () => { addingTool.value = false; selectedTool.value = '' },
  })
}

function confirmRemove(tool: any) {
  removingTool.value = tool.slug
}

function doRemove() {
  if (!removingTool.value) return
  deleting.value = true
  router.delete(route('user.dashboard.collections.tools.remove', [collection.ulid, removingTool.value]), {
    onFinish: () => { deleting.value = false; removingTool.value = null },
  })
}
</script>

<template>
  <div>
    <div class="mb-6">
      <Link :href="route('user.dashboard.collections.index')" class="mb-3 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        {{ t('Back to collections') }}
      </Link>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ collection.name }}</h1>
      <p v-if="collection.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ collection.description }}</p>
    </div>

    <div v-if="toolOptions.length > 0" class="mb-4 flex items-end gap-3">
      <div class="flex-1 max-w-sm">
        <AppSelect v-model="selectedTool" :options="toolOptions" :label="t('Add tool')" :placeholder="t('Search tools...')" live-search :size="8" />
      </div>
      <button @click="addTool" :disabled="!selectedTool || addingTool" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
        {{ addingTool ? t('Adding...') : t('Add') }}
      </button>
    </div>

    <div v-if="tools.length === 0" class="rounded-xl border border-gray-200 bg-white py-12 text-center dark:border-gray-800 dark:bg-gray-900">
      <p class="text-gray-500">{{ t('No tools in this collection yet.') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="tool in tools" :key="tool.slug" class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <Link :href="route('ai.tools.show', tool.slug)" class="flex items-center gap-3 min-w-0">
          <i v-if="tool.icon" :class="tool.icon" class="text-xl shrink-0"></i>
          <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ tool.name }}</span>
        </Link>
        <button @click="confirmRemove(tool)" class="shrink-0 ml-2 text-gray-400 hover:text-red-500">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
    </div>

    <ActionConfirmModal
      :open="removingTool !== null"
      title="Remove tool?"
      message="Are you sure you want to remove this tool from the collection?"
      confirm-label="Remove"
      :processing="deleting"
      @cancel="removingTool = null"
      @confirm="doRemove"
    />
  </div>
</template>
