<template>
  <AppLayout>
    <div class="template-wrapper" :style="cssVars">
      <Head>
        <title>{{ template.meta_title ?? 'Social Media Manager — ' + $page.props.appName }}</title>
        <meta name="description" :content="template.meta_description ?? template.tagline" />
      </Head>
      <article v-if="template.custom_html_head" v-html="template.custom_html_head" />

      <!-- Hero Banner -->
      <section class="relative py-16 md:py-24 bg-gradient-to-br from-indigo-50 to-white dark:from-surface-900 dark:to-surface-950">
        <div class="max-w-7xl mx-auto px-6 text-center">
          <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight">
            {{ template.hero_headline ?? 'Create content for every platform' }}
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

          <!-- Platform chips row (mobile-friendly horizontal scroll) -->
          <div class="mt-10 flex justify-center gap-2 flex-wrap">
            <button
              v-for="p in enabledPlatforms"
              :key="p.slug"
              class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border hover:shadow-md"
              :class="isPlatformActive(p.slug)
                ? 'border-transparent text-white shadow-md'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
              :style="isPlatformActive(p.slug) ? { background: p.color_hex } : {}"
              @click="selectPlatform(p.slug)"
            >
              <i :class="p.icon" class="text-base"></i>
              {{ p.label }}
            </button>
            <button
              class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border hover:shadow-md"
              :class="activePlatform === null
                ? 'border-transparent text-white shadow-md'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
              :style="activePlatform === null ? { background: cssVars['--t-primary'] } : {}"
              @click="selectPlatform(null)"
            >
              <i class="ti ti-world text-base"></i>
              All Platforms
            </button>
          </div>
        </div>
      </section>

      <!-- Main Content: Sidebar + Tool Grid -->
      <section class="py-12 md:py-20 bg-white dark:bg-surface-950">
        <div class="max-w-7xl mx-auto px-6 flex gap-8">
          <!-- Platform Filter Sidebar (desktop) -->
          <aside class="hidden lg:block w-56 shrink-0">
            <div class="sticky top-24">
              <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">Platforms</h3>
              <nav class="space-y-1">
                <button
                  class="w-full flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                  :class="activePlatform === null
                    ? 'text-white shadow-md'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-800'"
                  :style="activePlatform === null ? { background: cssVars['--t-primary'] } : {}"
                  @click="selectPlatform(null)"
                >
                  <i class="ti ti-world text-lg"></i>
                  <span>All Platforms</span>
                  <span class="ml-auto text-xs rounded-full px-2 py-0.5" :class="activePlatform === null ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500 dark:bg-surface-700 dark:text-gray-400'">
                    {{ allToolsCount }}
                  </span>
                </button>
                <button
                  v-for="p in enabledPlatforms"
                  :key="p.slug"
                  class="w-full flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium transition-all"
                  :class="isPlatformActive(p.slug)
                    ? 'text-white shadow-md'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-surface-800'"
                  :style="isPlatformActive(p.slug) ? { background: p.color_hex } : {}"
                  @click="selectPlatform(p.slug)"
                >
                  <i :class="p.icon" class="text-lg"></i>
                  <span>{{ p.label }}</span>
                  <span class="ml-auto text-xs rounded-full px-2 py-0.5" :class="isPlatformActive(p.slug) ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500 dark:bg-surface-700 dark:text-gray-400'">
                    {{ platformToolCounts[p.slug] ?? 0 }}
                  </span>
                </button>
              </nav>

              <!-- Quick Stats -->
              <div v-if="$page.props.auth.user" class="mt-8 pt-6 border-t border-gray-100 dark:border-surface-700">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Quick Stats</h3>
                <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                  <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><i class="ti ti-sparkles text-base text-amber-500"></i> Tools used today</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ userStats?.toolsUsedToday ?? 0 }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="flex items-center gap-2"><i class="ti ti-calendar-week text-base text-emerald-500"></i> Generations this week</span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ userStats?.generationsThisWeek ?? 0 }}</span>
                  </div>
                </div>
              </div>
            </div>
          </aside>

          <!-- Tool Grid -->
          <div class="flex-1 min-w-0">
            <!-- Mobile: horizontal scrollable platform chips -->
            <div class="lg:hidden mb-6 flex gap-2 overflow-x-auto pb-2 -mx-1 px-1 scrollbar-hide">
              <button
                class="shrink-0 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border"
                :class="activePlatform === null
                  ? 'border-transparent text-white shadow-md'
                  : 'border-gray-200 bg-white text-gray-600 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
                :style="activePlatform === null ? { background: cssVars['--t-primary'] } : {}"
                @click="selectPlatform(null)"
              >
                <i class="ti ti-world text-base"></i> All
              </button>
              <button
                v-for="p in enabledPlatforms"
                :key="p.slug"
                class="shrink-0 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-all border"
                :class="isPlatformActive(p.slug)
                  ? 'border-transparent text-white shadow-md'
                  : 'border-gray-200 bg-white text-gray-600 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
                :style="isPlatformActive(p.slug) ? { background: p.color_hex } : {}"
                @click="selectPlatform(p.slug)"
              >
                <i :class="p.icon" class="text-base"></i> {{ p.label }}
              </button>
            </div>

            <!-- Empty state -->
            <div v-if="filteredTools.length === 0" class="text-center text-gray-400 dark:text-gray-500 py-16">
              <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
              <p class="text-lg font-medium">No tools available for this platform yet.</p>
              <p class="text-sm mt-1">Try selecting "All Platforms" or check back later.</p>
            </div>

            <!-- Grouped by platform (All Platforms view) -->
            <template v-if="activePlatform === null">
              <div v-for="group in groupedTools" :key="group.slug" class="mb-10 last:mb-0">
                <div class="flex items-center gap-3 mb-5">
                  <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white"
                    :style="{ background: group.color_hex }"
                  >
                    <i :class="group.icon" class="text-lg"></i>
                  </span>
                  <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ group.label }}</h3>
                  <span class="text-sm text-gray-400">{{ group.tools.length }} {{ group.tools.length === 1 ? 'tool' : 'tools' }}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                  <ToolCard
                    v-for="tool in group.tools"
                    :key="tool.slug"
                    :tool="tool"
                    :platform="group"
                    :primary-color="cssVars['--t-primary']"
                    :surface-color="cssVars['--t-surface']"
                  />
                </div>
              </div>
            </template>

            <!-- Flat grid (platform filtered) -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              <ToolCard
                v-for="tool in filteredTools"
                :key="tool.slug"
                :tool="tool"
                :platform="getToolPlatform(tool)"
                :primary-color="cssVars['--t-primary']"
                :surface-color="cssVars['--t-surface']"
              />
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
import { computed, ref, watch, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AdSection from '@/Components/AdSection.vue'
import ToolCard from './SocialMediaManager/ToolCard.vue'

interface PlatformDef {
  slug: string
  label: string
  icon: string
  color_hex: string
  enabled: boolean
}

interface ToolItem {
  slug: string
  name: string
  description: string
  icon: string | null
  color: string | null
  fields?: Array<{ name: string; key: string; type: string; default?: string }> | string
  usage_count?: number
  avg_rating?: number
}

interface UserStats {
  toolsUsedToday: number
  generationsThisWeek: number
}

const props = defineProps<{
  template: Record<string, any>
  tools: ToolItem[]
  platformSettings?: PlatformDef[]
  userStats?: UserStats | null
  defaultPlatform?: string | null
}>()

const cssVars = computed(() => ({
  '--t-primary': props.template.color_primary ?? 'var(--color-primary-500)',
  '--t-secondary': props.template.color_secondary ?? 'var(--color-secondary-500)',
  '--t-bg': props.template.color_bg ?? 'var(--surface-bg)',
  '--t-surface': props.template.color_surface ?? 'var(--surface-card)',
  '--t-text': props.template.color_text ?? 'var(--color-gray-700)',
}))

const activePlatform = ref<string | null>(null)

const platforms = computed<PlatformDef[]>(() => {
  return props.platformSettings ?? [
    { slug: 'instagram', label: 'Instagram', icon: 'ti ti-brand-instagram', color_hex: '#e1306c', enabled: true },
    { slug: 'twitter', label: 'Twitter/X', icon: 'ti ti-brand-x', color_hex: '#1da1f2', enabled: true },
    { slug: 'linkedin', label: 'LinkedIn', icon: 'ti ti-brand-linkedin', color_hex: '#0a66c2', enabled: true },
    { slug: 'tiktok', label: 'TikTok', icon: 'ti ti-brand-tiktok', color_hex: '#000000', enabled: true },
    { slug: 'facebook', label: 'Facebook', icon: 'ti ti-brand-facebook', color_hex: '#1877f2', enabled: true },
    { slug: 'youtube', label: 'YouTube', icon: 'ti ti-brand-youtube', color_hex: '#ff0000', enabled: true },
  ]
})

const enabledPlatforms = computed(() => platforms.value.filter(p => p.enabled))

function getToolPlatformSlug(tool: ToolItem): string | null {
  const fields = Array.isArray(tool.fields) ? tool.fields : (typeof tool.fields === 'string' ? JSON.parse(tool.fields) : [])
  const platformField = Array.isArray(fields) ? fields.find((f: any) => f.key === 'platform') : null
  if (platformField && platformField.default) {
    const pVal = platformField.default.toLowerCase()
    if (pVal === 'twitter/x' || pVal === 'twitter') return 'twitter'
    return pVal
  }
  return null
}

function getToolPlatform(tool: ToolItem): PlatformDef | null {
  const slug = getToolPlatformSlug(tool)
  if (!slug) return null
  return platforms.value.find(p => p.slug === slug) ?? null
}

const filteredTools = computed(() => {
  if (activePlatform.value === null) return props.tools
  return props.tools.filter(t => {
    const slug = getToolPlatformSlug(t)
    return slug === null || slug === activePlatform.value
  })
})

function isPlatformActive(slug: string): boolean {
  return activePlatform.value === slug
}

const allToolsCount = computed(() => props.tools.length)

const platformToolCounts = computed<Record<string, number>>(() => {
  const counts: Record<string, number> = {}
  for (const tool of props.tools) {
    const slug = getToolPlatformSlug(tool)
    if (slug) {
      counts[slug] = (counts[slug] ?? 0) + 1
    }
  }
  return counts
})

const groupedTools = computed(() => {
  const groups: Record<string, { slug: string; label: string; icon: string; color_hex: string; tools: ToolItem[] }> = {}

  for (const p of enabledPlatforms.value) {
    groups[p.slug] = { slug: p.slug, label: p.label, icon: p.icon, color_hex: p.color_hex, tools: [] }
  }

  const crossPlatformTools: ToolItem[] = []

  for (const tool of props.tools) {
    const slug = getToolPlatformSlug(tool)
    if (slug && groups[slug]) {
      groups[slug].tools.push(tool)
    } else {
      crossPlatformTools.push(tool)
    }
  }

  const result = Object.values(groups).filter(g => g.tools.length > 0)

  if (crossPlatformTools.length > 0) {
    result.push({
      slug: 'cross-platform',
      label: 'All Platforms',
      icon: 'ti ti-world',
      color_hex: cssVars.value['--t-primary'],
      tools: crossPlatformTools,
    })
  }

  return result
})

function selectPlatform(slug: string | null) {
  activePlatform.value = slug
  const url = new URL(window.location.href)
  if (slug) {
    url.searchParams.set('platform', slug)
  } else {
    url.searchParams.delete('platform')
  }
  window.history.replaceState({}, '', url.toString())
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const platformParam = params.get('platform')
  if (platformParam && enabledPlatforms.value.some(p => p.slug === platformParam)) {
    activePlatform.value = platformParam
  } else if (props.defaultPlatform && enabledPlatforms.value.some(p => p.slug === props.defaultPlatform)) {
    activePlatform.value = props.defaultPlatform
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
