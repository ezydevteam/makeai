<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import DiffModal from '@themes/default/js/Components/AI/DiffModal.vue'
import { useToastr } from '@/Composables/useToastr'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

defineOptions({ layout: UserDashboardLayout })

const props = defineProps<{
  history: Array<{
    ulid: string
    tool_slug: string
    output_preview: string
    label: string | null
    is_favorited: boolean
    model: string
    created_at: string
  }>
  pagination: {
    current_page: number
    last_page: number
    total: number
    links: Array<{ url: string | null; label: string; active: boolean }>
  }
  filters: { tool_slug?: string }
}>()

const toast = useToastr()
const diffModalOpen = ref(false)
const diffA = ref<string | null>(null)
const diffB = ref<string | null>(null)
const editingLabel = ref<string | null>(null)
const editLabelValue = ref('')

const isDeleteModalOpen = ref(false)
const itemToDelete = ref<any>(null)
const isDeleting = ref(false)

const searchQuery = ref('')
const activeTab = ref<'all' | 'starred'>('all')

const starredCount = computed(() => {
  return props.history.filter(item => item.is_favorited).length
})

const filteredHistory = computed(() => {
  return props.history.filter(item => {
    // 1. Tab Filter
    if (activeTab.value === 'starred' && !item.is_favorited) {
      return false
    }

    // 2. Search Filter
    if (searchQuery.value.trim() !== '') {
      const query = searchQuery.value.toLowerCase()
      const matchesSlug = item.tool_slug.toLowerCase().includes(query)
      const matchesLabel = (item.label || '').toLowerCase().includes(query)
      const matchesOutput = item.output_preview.toLowerCase().includes(query)
      return matchesSlug || matchesLabel || matchesOutput
    }

    return true
  })
})

function toggleFavorite(item: any) {
  router.post(route('user.dashboard.history.favorite', item.ulid), {}, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      item.is_favorited = !item.is_favorited
    },
  })
}

function openDiff(a: string, b: string) {
  diffA.value = a
  diffB.value = b
  diffModalOpen.value = true
}

function saveLabel(item: any) {
  router.put(route('user.dashboard.history.label', item.ulid), { label: editLabelValue.value }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      item.label = editLabelValue.value
      editingLabel.value = null
      toast.success('Label saved')
    },
  })
}

function startEditLabel(item: any) {
  editingLabel.value = item.ulid
  editLabelValue.value = item.label || ''
}

function confirmDelete(item: any) {
  itemToDelete.value = item
  isDeleteModalOpen.value = true
}

function handleDeleteConfirm() {
  if (!itemToDelete.value) return
  isDeleting.value = true
  router.delete(route('user.dashboard.history.destroy', itemToDelete.value.ulid), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Deleted')
      isDeleteModalOpen.value = false
      itemToDelete.value = null
    },
    onFinish: () => {
      isDeleting.value = false
    },
  })
}

function handleDeleteCancel() {
  isDeleteModalOpen.value = false
  itemToDelete.value = null
}

function timeAgo(date: string): string {
  const diff = Date.now() - new Date(date).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d ago`
  return new Date(date).toLocaleDateString()
}
</script>

<template>
  <div>
    <Head :title="t('Generation History')" />
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Generation History</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ pagination.total }} generation{{ pagination.total !== 1 ? 's' : '' }}
        </p>
      </div>

      <!-- Live Search Filter -->
      <div class="relative w-full max-w-xs sm:w-64">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
          <i class="ti ti-search text-gray-400"></i>
        </span>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search history..."
          class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-8 text-sm outline-none transition focus:border-primary-500 focus:bg-white dark:border-surface-800 dark:bg-surface-900 dark:text-white dark:focus:border-primary-500"
        />
        <span v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-2.5">
          <button @click="searchQuery = ''" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <i class="ti ti-x text-xs"></i>
          </button>
        </span>
      </div>
    </div>

    <!-- Tabs (All vs Starred) -->
    <div class="mb-6 border-b border-gray-200 dark:border-surface-800">
      <div class="flex gap-6">
        <button
          @click="activeTab = 'all'"
          :class="[
            activeTab === 'all'
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
          ]"
          class="border-b-2 pb-3 text-sm font-semibold transition-colors flex items-center gap-1.5"
        >
          <span>All</span>
          <span
            :class="[
              activeTab === 'all'
                ? 'bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400'
                : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-400'
            ]"
            class="rounded-full px-2 py-0.5 text-xs"
          >
            {{ props.history.length }}
          </span>
        </button>

        <button
          @click="activeTab = 'starred'"
          :class="[
            activeTab === 'starred'
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
          ]"
          class="border-b-2 pb-3 text-sm font-semibold transition-colors flex items-center gap-1.5"
        >
          <span>Starred</span>
          <span
            :class="[
              activeTab === 'starred'
                ? 'bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400'
                : 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-400'
            ]"
            class="rounded-full px-2 py-0.5 text-xs"
          >
            {{ starredCount }}
          </span>
        </button>
      </div>
    </div>

    <div v-if="filteredHistory.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-surface-800 dark:bg-gray-900">
      <p class="text-gray-500 dark:text-gray-400">
        <span v-if="searchQuery || activeTab === 'starred'">No matching history found.</span>
        <span v-else>No generation history yet. Run an AI tool to get started.</span>
      </p>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="item in filteredHistory"
        :key="item.ulid"
        class="rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 dark:border-surface-800 dark:bg-gray-900 dark:hover:border-surface-700"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="mb-1 flex items-center gap-2">
              <Link
                :href="route('ai.tools.show', { slug: item.tool_slug })"
                class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
              >
                {{ item.tool_slug }}
              </Link>
            </div>

            <p v-if="item.label" class="mb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
              {{ item.label }}
            </p>

            <p class="text-sm text-gray-600 line-clamp-2 dark:text-gray-400">
              {{ item.output_preview }}
            </p>

            <span class="mt-1 text-[11px] text-gray-400">{{ timeAgo(item.created_at) }}</span>
          </div>

          <div class="flex shrink-0 items-center gap-1">
            <Tooltip content="Restore">
              <Link
                :href="route('ai.tools.show', { slug: item.tool_slug, restore: item.ulid })"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20"
              >
                <i class="ti ti-rotate-clockwise text-base"></i>
              </Link>
            </Tooltip>

            <Tooltip v-if="editingLabel === item.ulid" content="Save">
              <button
                @click="saveLabel(item)"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20"
              >
                <i class="ti ti-check text-base"></i>
              </button>
            </Tooltip>
            <Tooltip v-else content="Label">
              <button
                @click="startEditLabel(item)"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800"
              >
                <i class="ti ti-tag text-base"></i>
              </button>
            </Tooltip>

            <Tooltip :content="item.is_favorited ? 'Unstar' : 'Star'">
              <button
                @click="toggleFavorite(item)"
                :class="item.is_favorited ? 'text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/20' : 'text-gray-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/20'"
                class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
              >
                <svg class="h-4 w-4" :fill="item.is_favorited ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                </svg>
              </button>
            </Tooltip>

            <Tooltip content="Delete">
              <button
                @click="confirmDelete(item)"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-red-500 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
              >
                <i class="ti ti-trash text-base"></i>
              </button>
            </Tooltip>
          </div>
        </div>

        <div v-if="editingLabel === item.ulid" class="mt-2">
          <input
            v-model="editLabelValue"
            type="text"
            maxlength="100"
            placeholder="Label this version..."
            class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800"
            @keyup.enter="saveLabel(item)"
          />
        </div>
      </div>
    </div>

    <div v-if="pagination.last_page > 1" class="mt-6 flex justify-center">
      <nav class="flex gap-1">
        <Link
          v-for="link in pagination.links"
          :key="link.label"
          :href="link.url || '#'"
          :class="[
            'rounded-lg px-3 py-1.5 text-sm font-medium transition',
            link.active
              ? 'bg-primary-600 text-white'
              : link.url
                ? 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'
                : 'cursor-default text-gray-300 dark:text-gray-600',
          ]"
          v-html="link.label"
        />
      </nav>
    </div>

    <DiffModal
      v-if="diffModalOpen"
      :ulid-a="diffA!"
      :ulid-b="diffB!"
      @close="diffModalOpen = false"
    />

    <ActionConfirmModal
      :open="isDeleteModalOpen"
      title="Delete History Entry"
      message="Are you sure you want to permanently delete this history entry?"
      confirm-label="Delete"
      cancel-label="Cancel"
      variant="danger"
      :processing="isDeleting"
      @confirm="handleDeleteConfirm"
      @cancel="handleDeleteCancel"
    />
  </div>
</template>
