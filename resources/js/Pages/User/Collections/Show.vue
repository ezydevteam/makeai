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
  description?: string | null
  icon?: string | null
  color?: string | null
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

function getToolAccent(color?: string | null) {
  if (!color) return 'rgba(16, 185, 129, 0.12)'

  if (/^#([0-9a-f]{3}){1,2}$/i.test(color)) {
    const hex = color.slice(1)
    const normalized = hex.length === 3
      ? hex.split('').map(char => char + char).join('')
      : hex
    const value = Number.parseInt(normalized, 16)
    const red = (value >> 16) & 255
    const green = (value >> 8) & 255
    const blue = value & 255

    return `rgba(${red}, ${green}, ${blue}, 0.12)`
  }

  return color
}

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
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ collection.name }}</h1>
        <p v-if="collection.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ collection.description }}</p>
      </div>
      <Link
        :href="route('user.dashboard.collections.index')"
        class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
      >
        <i class="ti ti-arrow-left text-base"></i>
        {{ t('Back') }}
      </Link>
    </div>

    <div v-if="toolOptions.length > 0" class="mb-4 flex items-end gap-3">
      <div class="flex-1 max-w-sm">
        <AppSelect v-model="selectedTool" :options="toolOptions" :label="t('Add tool')" :placeholder="t('Search tools...')" live-search :size="8" />
      </div>
      <button @click="addTool" :disabled="!selectedTool || addingTool" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50">
        {{ addingTool ? t('Adding...') : t('Add') }}
      </button>
    </div>

    <div v-if="tools.length === 0" class="rounded-2xl border border-gray-200 bg-white py-12 text-center dark:border-surface-800 dark:bg-gray-900">
      <p class="text-gray-500">{{ t('No tools in this collection yet.') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="tool in tools" :key="tool.slug" class="group flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 dark:border-surface-800 dark:bg-gray-900">
        <Link :href="route('ai.tools.show', tool.slug)" class="flex min-w-0 items-start gap-3">
          <span
            v-if="tool.icon"
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
            :style="{ backgroundColor: getToolAccent(tool.color) }"
          >
            <i :class="tool.icon" class="text-lg" :style="{ color: tool.color || 'rgb(16, 185, 129)' }"></i>
          </span>
          <span class="min-w-0">
            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ tool.name }}</span>
            <span v-if="tool.description" class="mt-1 block truncate text-xs leading-5 text-gray-500 dark:text-gray-400" :title="tool.description">
              {{ tool.description }}
            </span>
          </span>
        </Link>
        <button @click="confirmRemove(tool)" class="flex items-center justify-center ml-2 w-8 h-8 rounded-full shrink-0 text-gray-400 transition hover:bg-red-50 hover:!text-red-500 dark:hover:bg-red-900/20 dark:hover:text-red-400">
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
