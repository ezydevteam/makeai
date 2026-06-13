<template>
  <AppLayout>
    <div class="template-wrapper" :style="cssVars">
      <Head>
        <title>{{ template.meta_title ?? 'Marketing Suite — ' + $page.props.appName }}</title>
        <meta name="description" :content="template.meta_description ?? template.tagline" />
      </Head>
      <article v-if="template.custom_html_head" v-html="template.custom_html_head" />

      <!-- Hero Banner -->
      <section class="relative py-16 md:py-24 bg-gradient-to-br from-indigo-50 to-white dark:from-surface-900 dark:to-surface-950">
        <div class="max-w-7xl mx-auto px-6 text-center">
          <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight">
            {{ template.hero_headline ?? 'Marketing Content Command Center' }}
          </h1>
          <p v-if="template.hero_subheadline" class="mt-4 text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto">
            {{ template.hero_subheadline }}
          </p>
          <Link
            v-if="template.hero_cta_text"
            :href="template.hero_cta_url"
            class="inline-flex items-center mt-8 px-8 py-3 rounded-xl text-white font-bold transition-all shadow-lg"
            :style="{ background: cssVars['--t-primary'] }"
          >
            {{ template.hero_cta_text }}
          </Link>
        </div>
      </section>

      <!-- Stage Navigation Tabs -->
      <div class="sticky top-[var(--header-height,64px)] z-20 bg-white/95 dark:bg-surface-950/95 backdrop-blur border-b border-gray-100 dark:border-surface-700">
        <div class="max-w-7xl mx-auto px-6">
          <!-- Mobile: horizontal scrollable -->
          <div class="flex gap-1 overflow-x-auto py-3 scrollbar-hide">
            <button
              v-for="stage in stages"
              :key="stage.slug"
              class="shrink-0 relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl transition-all"
              :class="activeStage === stage.slug
                ? 'text-white'
                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-surface-800'"
              :style="activeStage === stage.slug ? { background: cssVars['--t-primary'] } : {}"
              @click="selectStage(stage.slug)"
            >
              <i :class="stage.icon" class="text-base"></i>
              {{ stage.label }}
              <span
                class="inline-flex items-center justify-center min-w-[22px] h-[22px] rounded-full text-xs font-bold"
                :class="activeStage === stage.slug ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500 dark:bg-surface-700 dark:text-gray-400'"
              >
                {{ stageToolCounts[stage.slug] ?? 0 }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Tool Grid -->
      <section class="py-12 md:py-20 bg-white dark:bg-surface-950">
        <div class="max-w-7xl mx-auto px-6">
          <!-- Empty state -->
          <div v-if="filteredTools.length === 0" class="text-center text-gray-400 dark:text-gray-500 py-16">
            <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
            <p class="text-lg font-medium">No tools available for this stage yet.</p>
            <p class="text-sm mt-1">Try another stage or check back later.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
              v-for="tool in filteredTools"
              :key="tool.slug"
              class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg flex flex-col"
              :style="{ background: cssVars['--t-surface'] }"
            >
              <div class="flex items-start gap-3 mb-3">
                <span
                  v-if="tool.icon"
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white"
                  :style="{ background: tool.color ?? cssVars['--t-primary'] }"
                >
                  <i :class="tool.icon" class="text-lg"></i>
                </span>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ tool.name }}</h3>
              </div>

              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 flex-1 line-clamp-2">{{ tool.description }}</p>

              <!-- Campaign pairing hint -->
              <div v-if="pairingHint(tool)" class="mb-4">
                <Link
                  :href="'/ai-tools/' + pairingHint(tool)!.slug"
                  class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                  :style="{
                    background: cssVars['--t-primary'] + '15',
                    color: cssVars['--t-primary'],
                  }"
                >
                  <i class="ti ti-link text-xs"></i>
                  Pairs well with {{ pairingHint(tool)!.name }}
                </Link>
              </div>

              <div class="flex items-center justify-between pt-3 border-t border-gray-50 dark:border-surface-600 mt-auto">
                <Link
                  :href="'/ai-tools/' + tool.slug"
                  class="text-sm font-semibold"
                  :style="{ color: cssVars['--t-primary'] }"
                >
                  Generate →
                </Link>
                <span v-if="tool.avg_rating" class="flex items-center gap-1 text-xs text-amber-500">
                  <i class="ti ti-star-filled text-xs"></i>
                  {{ tool.avg_rating }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <AdSection zone="template_page" class="mx-auto mb-8 w-full max-w-7xl px-6" />

      <article v-if="template.custom_html_body" v-html="template.custom_html_body" />
      <component :is="'style'" v-if="template.custom_css" v-text="template.custom_css" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AdSection from '@/Components/AdSection.vue'

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
  avg_rating?: number
}

const props = defineProps<{
  template: Record<string, any>
  tools: ToolItem[]
  stageSettings?: StageDef[]
  defaultStage?: string | null
}>()

const cssVars = computed(() => ({
  '--t-primary': props.template.color_primary ?? 'var(--color-primary-500)',
  '--t-secondary': props.template.color_secondary ?? 'var(--color-secondary-500)',
  '--t-bg': props.template.color_bg ?? 'var(--surface-bg)',
  '--t-surface': props.template.color_surface ?? 'var(--surface-card)',
  '--t-text': props.template.color_text ?? 'var(--color-gray-700)',
}))

const stages = computed<StageDef[]>(() => {
  return props.stageSettings ?? [
    { slug: 'awareness', label: 'Awareness', icon: 'ti ti-eye' },
    { slug: 'consideration', label: 'Consideration', icon: 'ti ti-bulb' },
    { slug: 'conversion', label: 'Conversion', icon: 'ti ti-currency-dollar' },
    { slug: 'retention', label: 'Retention', icon: 'ti ti-repeat' },
  ]
})

const activeStage = ref(props.defaultStage ?? 'awareness')

function parseTags(tool: ToolItem): Record<string, any> {
  if (!tool.tags) return {}
  if (typeof tool.tags === 'string') {
    try { return JSON.parse(tool.tags) } catch { return {} }
  }
  return tool.tags
}

function getToolStage(tool: ToolItem): string | null {
  const tags = parseTags(tool)
  return tags.stage ?? null
}

const filteredTools = computed(() => {
  return props.tools.filter(t => getToolStage(t) === activeStage.value)
})

const stageToolCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const s of stages.value) {
    counts[s.slug] = props.tools.filter(t => getToolStage(t) === s.slug).length
  }
  return counts
})

function pairingHint(tool: ToolItem): { slug: string; name: string } | null {
  const tags = parseTags(tool)
  const pairsWith = tags.pairs_with as string | undefined
  if (!pairsWith) return null
  const paired = props.tools.find(t => t.slug === pairsWith)
  if (!paired) return null
  return { slug: paired.slug, name: paired.name }
}

function selectStage(slug: string) {
  activeStage.value = slug
  const url = new URL(window.location.href)
  url.searchParams.set('stage', slug)
  window.history.replaceState({}, '', url.toString())
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const stageParam = params.get('stage')
  if (stageParam && stages.value.some(s => s.slug === stageParam)) {
    activeStage.value = stageParam
  }
})
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
