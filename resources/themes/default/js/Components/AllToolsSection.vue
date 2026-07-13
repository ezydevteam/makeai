<template>
  <div class="relative">
    <!-- Search bar -->
    <div v-if="showSearch" class="max-w-2xl mx-auto mb-8">
      <div class="relative">
        <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg"></i>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="t('Search tools...')"
          class="w-full pl-11 pr-4 py-3.5 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-600 rounded-2xl text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all"
        />
      </div>
    </div>

    <!-- Category chips -->
    <div v-if="showCategories && categories.length > 0" class="flex flex-wrap justify-center gap-2 mb-8">
      <button
        class="category-chip"
        :class="{ 'category-chip-active': activeCategory === null }"
        @click="activeCategory = null"
      >
        {{ t('All Categories') }}
      </button>
      <button
        v-for="cat in categories"
        :key="cat"
        class="category-chip"
        :class="{ 'category-chip-active': activeCategory === cat }"
        @click="activeCategory = activeCategory === cat ? null : cat"
      >
        {{ cat }}
      </button>
    </div>

    <!-- Tabs: Popular / Featured / Recent -->
    <div class="flex justify-center gap-1 mb-10">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ 'tab-btn-active': activeTab === tab.key }"
        :style="activeTab === tab.key ? { background: 'var(--color-primary-500)', color: '#fff', borderColor: 'var(--color-primary-500)' } : {}"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span class="tab-count">{{ tabCounts[tab.key] }}</span>
      </button>
    </div>

    <!-- Tool grid -->
    <div v-if="displayedTools.length === 0" class="text-center py-16 text-gray-400 dark:text-gray-500">
      <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
      <p class="text-lg font-medium">{{ t('No tools found.') }}</p>
      <p class="text-sm mt-1">{{ t('Try a different search or category.') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <Link
        v-for="tool in displayedTools"
        :key="tool.slug"
        :href="'/ai-tools/' + tool.slug"
        class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg hover:-translate-y-0.5 flex flex-col bg-white dark:bg-surface-900"
      >
        <div class="flex items-center gap-3 mb-3">
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white"
            :style="{ background: tool.color ?? 'var(--color-primary-500)' }"
          >
            <i v-if="tool.icon" :class="tool.icon" class="text-lg"></i>
            <span v-else class="text-sm font-bold">{{ tool.name.charAt(0) }}</span>
          </span>
          <div class="flex-1 min-w-0">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ tool.name }}</h3>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span
                v-if="tool.is_featured"
                class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
              >
                {{ t('Featured') }}
              </span>
              <span
                v-if="isNewTool(tool)"
                class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400"
              >
                {{ t('New') }}
              </span>
            </div>
          </div>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 flex-1 line-clamp-2">{{ tool.description }}</p>
        <div class="mt-4 pt-3 border-t border-gray-50 dark:border-surface-700 flex items-center justify-between">
          <span v-if="tool.avg_rating" class="flex items-center gap-1 text-xs text-gray-400">
            <i class="ti ti-star-filled text-amber-400 text-xs"></i>
            {{ Number(tool.avg_rating).toFixed(1) }}
          </span>
          <span class="text-xs text-gray-400 flex items-center gap-1 ml-auto">
            <i class="ti ti-users text-xs"></i>
            {{ formatCount(tool.usage_count) }}
          </span>
        </div>
      </Link>
    </div>

    <!-- Show more button -->
    <div v-if="displayLimit < filteredByTab.length" class="text-center mt-8">
      <button
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 dark:border-surface-700 px-6 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-surface-800 transition-colors"
        @click="displayLimit = Math.min(displayLimit + 12, filteredByTab.length)"
      >
        {{ t('Show More Tools') }}
        <span class="text-xs text-gray-400">
          ({{ filteredByTab.length - displayLimit }})
        </span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

interface ToolItem {
  slug: string
  name: string
  description: string
  icon: string | null
  color: string | null
  category: string | null
  tags?: Record<string, any> | string | null
  usage_count: number
  avg_rating: number | null
  is_featured: boolean
  created_at?: string
}

const { t } = useTranslate()

const props = withDefaults(defineProps<{
  tools: ToolItem[]
  categories: string[]
  maxItems?: number
  defaultTab?: 'popular' | 'featured' | 'recent'
  showSearch?: boolean
  showCategories?: boolean
}>(), {
  maxItems: 12,
  defaultTab: 'popular',
  showSearch: true,
  showCategories: true,
})

const searchQuery = ref('')
const activeCategory = ref<string | null>(null)
const activeTab = ref<'popular' | 'featured' | 'recent'>(props.defaultTab)
const displayLimit = ref(props.maxItems)

function isNewTool(tool: ToolItem): boolean {
  if (!tool.created_at) return false
  const thirtyDaysAgo = Date.now() - 30 * 24 * 60 * 60 * 1000
  return Date.parse(tool.created_at) > thirtyDaysAgo
}

const tabs = [
  { key: 'popular' as const, label: t('Popular') },
  { key: 'featured' as const, label: t('Featured') },
  { key: 'recent' as const, label: t('Recent') },
]

const filteredBySearch = computed(() => {
  let tools = props.tools

  if (activeCategory.value) {
    tools = tools.filter(t => t.category === activeCategory.value)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    tools = tools.filter(t =>
      t.name.toLowerCase().includes(q) ||
      (t.description?.toLowerCase().includes(q))
    )
  }

  return tools
})

const filteredByTab = computed(() => {
  const tools = [...filteredBySearch.value]
  switch (activeTab.value) {
    case 'popular':
      return tools.sort((a, b) => (b.usage_count ?? 0) - (a.usage_count ?? 0))
    case 'featured':
      return tools.filter(t => t.is_featured)
    case 'recent':
      return tools.sort((a, b) => {
        const da = a.created_at ? Date.parse(a.created_at) : 0
        const db = b.created_at ? Date.parse(b.created_at) : 0
        return db - da
      })
    default:
      return tools
  }
})

const displayedTools = computed(() =>
  filteredByTab.value.slice(0, displayLimit.value)
)

const tabCounts = computed(() => ({
  popular: filteredBySearch.value.length,
  featured: filteredBySearch.value.filter(t => t.is_featured).length,
  recent: filteredBySearch.value.length,
}))

function formatCount(n: number | undefined): string {
  if (!n) return '0'
  if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M'
  if (n >= 1000) return (n / 1000).toFixed(1) + 'K'
  return String(n)
}
</script>

<style scoped>
.category-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.4rem 1rem;
  border-radius: 9999px;
  border: 1px solid #e5e7eb;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.15s ease;
  color: #6b7280;
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
}

:global(.dark) .category-chip {
  border-color: #374151;
  color: #9ca3af;
}

.category-chip:hover {
  border-color: var(--color-primary-300);
  color: var(--color-primary-600);
}

:global(.dark) .category-chip:hover {
  border-color: var(--color-primary-500);
  color: var(--color-primary-400);
}

.category-chip-active {
  background: var(--color-primary-500) !important;
  color: #fff !important;
  border-color: var(--color-primary-500) !important;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 1.25rem;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
  font-size: 0.875rem;
  font-weight: 600;
  color: #6b7280;
  background: transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

:global(.dark) .tab-btn {
  border-color: #374151;
  color: #9ca3af;
}

.tab-btn:hover {
  border-color: var(--color-primary-300);
  color: var(--color-primary-600);
}

:global(.dark) .tab-btn:hover {
  border-color: var(--color-primary-500);
  color: var(--color-primary-400);
}

.tab-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.5rem;
  height: 1.25rem;
  border-radius: 9999px;
  padding: 0 0.35rem;
  font-size: 0.7rem;
  font-weight: 700;
  background: #f3f4f6;
  color: #6b7280;
}

:global(.dark) .tab-count {
  background: #1f2937;
  color: #9ca3af;
}

.tab-btn-active .tab-count {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
