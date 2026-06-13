<template>
  <div class="template-tool-grid">
    <!-- Social Media Manager: platform sidebar + chips -->
    <template v-if="templateSlug === 'social-media-manager'">
      <div class="flex gap-8">
        <aside v-if="showFilter" class="hidden lg:block w-52 shrink-0">
          <div class="sticky top-24 space-y-1">
            <button
              v-for="p in enabledPlatforms"
              :key="p.slug"
              class="w-full flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
              :class="activePlatform === p.slug
                ? 'text-white shadow-md'
                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-800'"
              :style="activePlatform === p.slug ? { background: p.color_hex } : {}"
              @click="activePlatform = activePlatform === p.slug ? null : p.slug"
            >
              <i :class="p.icon" class="text-lg"></i>
              <span>{{ p.label }}</span>
            </button>
          </div>
        </aside>

        <div class="flex-1 min-w-0">
          <div v-if="showFilter" class="lg:hidden mb-6 flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
            <button
              v-for="p in enabledPlatforms"
              :key="p.slug"
              class="shrink-0 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border"
              :class="activePlatform === p.slug
                ? 'border-transparent text-white shadow-md'
                : 'border-gray-200 bg-white text-gray-600 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
              :style="activePlatform === p.slug ? { background: p.color_hex } : {}"
              @click="activePlatform = activePlatform === p.slug ? null : p.slug"
            >
              <i :class="p.icon" class="text-base"></i> {{ p.label }}
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
              v-for="tool in filteredTools"
              :key="tool.slug"
              class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg relative"
            >
              <span
                v-if="getToolPlatform(tool)"
                class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold text-white shadow-sm"
                :style="{ background: getToolPlatform(tool)!.color_hex }"
              >
                <i :class="getToolPlatform(tool)!.icon" class="text-xs"></i>
                {{ getToolPlatform(tool)!.label }}
              </span>
              <div class="flex items-start gap-3 mb-3">
                <span v-if="tool.icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white" :style="{ background: tool.color ?? cssPrimary }">
                  <i :class="tool.icon" class="text-lg"></i>
                </span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ tool.name }}</h3>
              </div>
              <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ tool.description }}</p>
              <Link :href="'/ai-tools/' + tool.slug" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold" :style="{ color: cssPrimary }">
                {{ t('Use Tool') }} →
              </Link>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Marketing Suite: stage tabs -->
    <template v-else-if="templateSlug === 'marketing-suite'">
      <div v-if="showFilter" class="flex gap-1 overflow-x-auto pb-3 mb-8 scrollbar-hide">
        <button
          v-for="stage in stages"
          :key="stage.slug"
          class="shrink-0 relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl transition-all"
          :class="activeStage === stage.slug
            ? 'text-white'
            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-surface-800'"
          :style="activeStage === stage.slug ? { background: cssPrimary } : {}"
          @click="activeStage = stage.slug"
        >
          <i :class="stage.icon" class="text-base"></i>
          {{ stage.label }}
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="tool in filteredTools"
          :key="tool.slug"
          class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg flex flex-col"
        >
          <div class="flex items-start gap-3 mb-3">
            <span v-if="tool.icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white" :style="{ background: tool.color ?? cssPrimary }">
              <i :class="tool.icon" class="text-lg"></i>
            </span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ tool.name }}</h3>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 flex-1 line-clamp-2">{{ tool.description }}</p>
          <div v-if="pairingHint(tool)" class="mb-3">
            <Link
              :href="'/ai-tools/' + pairingHint(tool)!.slug"
              class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
              :style="{ background: cssPrimary + '15', color: cssPrimary }"
            >
              <i class="ti ti-link text-xs"></i>
              Pairs well with {{ pairingHint(tool)!.name }}
            </Link>
          </div>
          <Link :href="'/ai-tools/' + tool.slug" class="inline-flex items-center gap-1 mt-auto pt-3 border-t border-gray-50 dark:border-surface-600 text-sm font-semibold" :style="{ color: cssPrimary }">
            {{ t('Use Tool') }} →
          </Link>
        </div>
      </div>
    </template>

    <!-- Default: flat grid -->
    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="tool in displayedTools"
          :key="tool.slug"
          class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg"
        >
          <div class="flex items-start gap-3 mb-3">
            <span v-if="tool.icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white" :style="{ background: tool.color ?? cssPrimary }">
              <i :class="tool.icon" class="text-lg"></i>
            </span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ tool.name }}</h3>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ tool.description }}</p>
          <Link :href="'/ai-tools/' + tool.slug" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold" :style="{ color: cssPrimary }">
            {{ t('Use Tool') }} →
          </Link>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

interface PlatformDef {
  slug: string
  label: string
  icon: string
  color_hex: string
  enabled: boolean
}

interface StageDef {
  slug: string
  label: string
  icon: string
}

interface ToolItem {
  slug: string
  name: string
  description: string
  icon: string | null
  color: string | null
  tags?: Record<string, any> | string | null
}

const props = withDefaults(defineProps<{
  templateSlug: string
  template?: Record<string, any>
  tools?: ToolItem[]
  platformSettings?: PlatformDef[]
  defaultPlatform?: string
  stageSettings?: StageDef[]
  defaultStage?: string
  maxItems?: number
  showFilter?: boolean
}>(), {
  template: () => ({}),
  tools: () => [],
  platformSettings: () => [],
  defaultPlatform: '',
  stageSettings: () => [],
  defaultStage: 'awareness',
  maxItems: 12,
  showFilter: true,
})

const { t } = useTranslate()

const cssPrimary = computed(() => props.template?.color_primary ?? 'var(--color-primary-500)')

// Platform helpers
const enabledPlatforms = computed(() => props.platformSettings.filter(p => p.enabled))

const activePlatform = ref(props.defaultPlatform || null)

function getToolPlatform(tool: ToolItem): PlatformDef | null {
  const tags = parseTags(tool)
  const platform = tags.platform as string | undefined
  if (!platform) return null
  const pVal = platform.toLowerCase()
  return props.platformSettings.find(p => p.slug === pVal || p.slug === pVal.replace(/\//g, '-')) ?? null
}

// Stage helpers
const stages = computed(() => props.stageSettings)

const activeStage = ref(props.defaultStage || 'awareness')

// Tag parsing
function parseTags(tool: ToolItem): Record<string, any> {
  if (!tool.tags) return {}
  if (typeof tool.tags === 'string') {
    try { return JSON.parse(tool.tags) } catch { return {} }
  }
  return tool.tags
}

// Social media filter
const socialFilteredTools = computed(() => {
  if (!activePlatform.value) return props.tools.slice(0, props.maxItems)
  return props.tools.filter(t => {
    const plat = getToolPlatform(t)
    return !plat || plat.slug === activePlatform.value
  }).slice(0, props.maxItems)
})

// Marketing filter
const stageFilteredTools = computed(() => {
  return props.tools
    .filter(t => {
      const tags = parseTags(t)
      const stage = tags.stage as string | undefined
      return !stage || stage === activeStage.value
    })
    .slice(0, props.maxItems)
})

// Default
const displayedTools = computed(() => props.tools.slice(0, props.maxItems))

// Unified filtered tools (pick based on template)
const filteredTools = computed(() => {
  if (props.templateSlug === 'social-media-manager') return socialFilteredTools.value
  if (props.templateSlug === 'marketing-suite') return stageFilteredTools.value
  return displayedTools.value
})

function pairingHint(tool: ToolItem): { slug: string; name: string } | null {
  const tags = parseTags(tool)
  const pairsWith = tags.pairs_with as string | undefined
  if (!pairsWith) return null
  const paired = props.tools.find(t => t.slug === pairsWith)
  if (!paired) return null
  return { slug: paired.slug, name: paired.name }
}
</script>

<style scoped>
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>
