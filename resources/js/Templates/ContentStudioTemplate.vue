<template>
  <AppLayout>
    <div class="template-wrapper" :style="cssVars">
      <Head>
        <title>{{ template.meta_title ?? 'Content Studio — ' + $page.props.appName }}</title>
        <meta name="description" :content="template.meta_description ?? template.tagline" />
      </Head>
      <article v-if="template.custom_html_head" v-html="template.custom_html_head" />

      <section class="py-16 md:py-24 bg-white dark:bg-surface-950">
        <div class="max-w-7xl mx-auto px-6 flex gap-8">
          <aside class="hidden lg:block w-44 shrink-0">
            <div class="sticky top-24">
              <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">{{ t('Content Types') }}</h3>
              <nav class="space-y-1">
                <button
                  v-for="ct in contentTypes"
                  :key="ct.slug"
                  class="w-full flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                  :class="activeType === ct.slug
                    ? 'text-white shadow-md'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-800'"
                  :style="activeType === ct.slug ? { background: cssVars['--t-primary'] } : {}"
                  @click="selectType(ct.slug)"
                >
                  <i :class="ct.icon" class="text-lg"></i>
                  <span>{{ ct.label }}</span>
                  <span
                    class="ml-auto text-xs rounded-full px-2 py-0.5"
                    :class="activeType === ct.slug ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500 dark:bg-surface-700 dark:text-gray-400'"
                  >
                    {{ typeToolCounts[ct.slug] ?? 0 }}
                  </span>
                </button>
              </nav>
              <button
                class="w-full mt-3 flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-surface-800 transition-all"
                @click="selectType(null)"
              >
                <i class="ti ti-apps text-lg"></i>
                <span>{{ t('Browse All') }}</span>
              </button>
            </div>
          </aside>

          <div class="flex-1 min-w-0">
            <div class="lg:hidden mb-6 flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
              <button
                v-for="ct in contentTypes"
                :key="ct.slug"
                class="shrink-0 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border"
                :class="activeType === ct.slug
                  ? 'border-transparent text-white shadow-md'
                  : 'border-gray-200 bg-white text-gray-600 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
                :style="activeType === ct.slug ? { background: cssVars['--t-primary'] } : {}"
                @click="selectType(ct.slug)"
              >
                <i :class="ct.icon" class="text-base"></i> {{ ct.label }}
              </button>
              <button
                class="shrink-0 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300"
                @click="selectType(null)"
              >
                <i class="ti ti-apps text-base"></i> {{ t('All') }}
              </button>
            </div>

            <div v-if="filteredTools.length === 0" class="text-center text-gray-400 dark:text-gray-500 py-16">
              <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
              <p class="text-lg font-medium">{{ t('No tools available for this content type yet.') }}</p>
              <p class="text-sm mt-1">{{ t('Try browsing all tools or check back later.') }}</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div
                v-for="tool in filteredTools"
                :key="tool.slug"
                class="group rounded-2xl border border-gray-100 dark:border-surface-700 p-6 transition-all hover:shadow-lg"
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
                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ tool.description }}</p>
                <Link :href="'/ai-tools/' + tool.slug" class="inline-flex items-center gap-1 mt-4 text-sm font-semibold" :style="{ color: cssVars['--t-primary'] }">
                  {{ t('Use Tool') }} →
                </Link>
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
import { useTranslate } from '@/Composables/useTranslate'

interface ContentTypeDef {
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

const { t } = useTranslate()

const props = defineProps<{
  template: Record<string, any>
  tools: ToolItem[]
  contentTypeSettings?: ContentTypeDef[]
  defaultType?: string | null
}>()

const cssVars = computed(() => ({
  '--t-primary': props.template.color_primary ?? 'var(--color-primary-500)',
  '--t-secondary': props.template.color_secondary ?? 'var(--color-secondary-500)',
  '--t-bg': props.template.color_bg ?? 'var(--surface-bg)',
  '--t-surface': props.template.color_surface ?? 'var(--surface-card)',
  '--t-text': props.template.color_text ?? 'var(--color-gray-700)',
}))

const contentTypes = computed<ContentTypeDef[]>(() => {
  return props.contentTypeSettings ?? [
    { slug: 'articles', label: 'Articles', icon: 'ti ti-pencil' },
    { slug: 'seo', label: 'SEO', icon: 'ti ti-world-search' },
    { slug: 'rewriting', label: 'Rewriting', icon: 'ti ti-refresh' },
    { slug: 'social', label: 'Social', icon: 'ti ti-share' },
  ]
})

const activeType = ref<string | null>(props.defaultType ?? null)

function parseTags(tool: ToolItem): Record<string, any> {
  if (!tool.tags) return {}
  if (typeof tool.tags === 'string') {
    try { return JSON.parse(tool.tags) } catch { return {} }
  }
  return tool.tags
}

function getToolContentType(tool: ToolItem): string | null {
  return parseTags(tool).content_type ?? null
}

const filteredTools = computed(() => {
  if (activeType.value === null) return props.tools
  return props.tools.filter(t => getToolContentType(t) === activeType.value)
})

const typeToolCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const ct of contentTypes.value) {
    counts[ct.slug] = props.tools.filter(t => getToolContentType(t) === ct.slug).length
  }
  return counts
})

function selectType(slug: string | null) {
  activeType.value = slug
  const url = new URL(window.location.href)
  if (slug) {
    url.searchParams.set('type', slug)
  } else {
    url.searchParams.delete('type')
  }
  window.history.replaceState({}, '', url.toString())
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const typeParam = params.get('type')
  if (typeParam && contentTypes.value.some(ct => ct.slug === typeParam)) {
    activeType.value = typeParam
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
