<template>
  <AppLayout>
    <div class="template-wrapper" :style="cssVars">
      <Head>
        <title>{{ template.meta_title ?? 'eCommerce Toolkit — ' + $page.props.appName }}</title>
        <meta name="description" :content="template.meta_description ?? template.tagline" />
      </Head>
      <article v-if="template.custom_html_head" v-html="template.custom_html_head" />

      <!-- Hero Banner -->
      <section class="relative py-16 md:py-24 bg-gradient-to-br from-emerald-50 to-white dark:from-surface-900 dark:to-surface-950">
        <div class="max-w-7xl mx-auto px-6 text-center">
          <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 dark:text-white tracking-tight">
            {{ template.hero_headline ?? 'Tools built for online stores' }}
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

      <!-- Store Context Panel (collapsible) -->
      <div v-if="showContextPanel" class="bg-gray-50 dark:bg-surface-900 border-b border-gray-100 dark:border-surface-700">
        <div class="max-w-7xl mx-auto px-6">
          <div v-show="!contextCollapsed" class="py-5 flex flex-col gap-5 md:flex-row md:items-end md:gap-6">
            <label class="flex-1 block">
              <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">{{ t('Store Name') }}</span>
              <input
                v-model="storeContext.store_name"
                type="text"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                :placeholder="contextPlaceholders.store_name"
              />
            </label>
            <label class="flex-1 block">
              <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">{{ t('Product Category') }}</span>
              <input
                v-model="storeContext.product_category"
                type="text"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                :placeholder="contextPlaceholders.category"
              />
            </label>
            <label class="flex-1 block">
              <span class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1.5">{{ t('Brand Tone') }}</span>
              <select
                v-model="storeContext.brand_tone"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
              >
                <option value="Professional">{{ t('Professional') }}</option>
                <option value="Friendly">{{ t('Friendly') }}</option>
                <option value="Playful">{{ t('Playful') }}</option>
                <option value="Luxury">{{ t('Luxury') }}</option>
              </select>
            </label>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:shadow-lg"
              :style="{ background: cssVars['--t-primary'] }"
              @click="saveContext"
            >
              <i class="ti ti-device-floppy text-base"></i>
              {{ t('Save Context') }}
            </button>
          </div>
          <button
            type="button"
            class="w-full flex items-center justify-center gap-2 py-2.5 text-xs font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
            @click="contextCollapsed = !contextCollapsed; saveCollapsedState()"
          >
            <i :class="contextCollapsed ? 'ti ti-chevron-down' : 'ti ti-chevron-up'" class="text-sm"></i>
            {{ contextPanelLabel }}
            <span v-if="contextCollapsed && hasSavedContext" class="text-xs text-gray-400">
              ({{ storeContext.store_name || t('No store set') }})
            </span>
          </button>
        </div>
      </div>

      <!-- Stage Tabs -->
      <div class="sticky top-[var(--header-height,64px)] z-20 bg-white/95 dark:bg-surface-950/95 backdrop-blur border-b border-gray-100 dark:border-surface-700">
        <div class="max-w-7xl mx-auto px-6">
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
          <div v-if="filteredTools.length === 0" class="text-center text-gray-400 dark:text-gray-500 py-16">
            <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
            <p class="text-lg font-medium">{{ t('No tools available for this stage yet.') }}</p>
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
              <p class="text-sm text-gray-500 dark:text-gray-400 flex-1 line-clamp-2">{{ tool.description }}</p>

              <Link
                :href="buildToolUrl(tool.slug)"
                class="inline-flex items-center gap-1 mt-auto pt-3 border-t border-gray-50 dark:border-surface-600 text-sm font-semibold"
                :style="{ color: cssVars['--t-primary'] }"
              >
                {{ t('Use Tool') }} →
              </Link>
            </div>
          </div>
        </div>
      </section>

      <div v-if="showSaveToast" class="fixed bottom-6 right-6 z-50 rounded-2xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-5 py-3 text-sm font-semibold shadow-xl animate-[fade-in-up_0.3s_ease]">
        {{ t('Store context saved') }}
      </div>

      <AdSection zone="template_page" class="mx-auto mb-8 w-full max-w-7xl px-6" />
      <article v-if="template.custom_html_body" v-html="template.custom_html_body" />
      <component :is="'style'" v-if="template.custom_css" v-text="template.custom_css" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref, reactive, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AdSection from '@/Components/AdSection.vue'
import { useTranslate } from '@/Composables/useTranslate'

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

const { t } = useTranslate()

const props = defineProps<{
  template: Record<string, any>
  tools: ToolItem[]
  ecomStageSettings?: StageDef[]
  defaultStage?: string | null
  showContextPanel?: string | boolean
  contextPanelLabel?: string | null
}>()

const cssVars = computed(() => ({
  '--t-primary': props.template.color_primary ?? 'var(--color-primary-500)',
  '--t-secondary': props.template.color_secondary ?? 'var(--color-secondary-500)',
  '--t-bg': props.template.color_bg ?? 'var(--surface-bg)',
  '--t-surface': props.template.color_surface ?? 'var(--surface-card)',
  '--t-text': props.template.color_text ?? 'var(--color-gray-700)',
}))

const stages = computed<StageDef[]>(() => {
  return props.ecomStageSettings ?? [
    { slug: 'product-listing', label: 'Product Listing', icon: 'ti ti-package' },
    { slug: 'email-retention', label: 'Email & Retention', icon: 'ti ti-mail-heart' },
    { slug: 'promotions', label: 'Promotions', icon: 'ti ti-discount-2' },
  ]
})

const activeStage = ref(props.defaultStage ?? 'product-listing')

function parseTags(tool: ToolItem): Record<string, any> {
  if (!tool.tags) return {}
  if (typeof tool.tags === 'string') {
    try { return JSON.parse(tool.tags) } catch { return {} }
  }
  return tool.tags
}

function getToolStage(tool: ToolItem): string | null {
  return parseTags(tool).ecom_stage ?? null
}

const filteredTools = computed(() =>
  props.tools.filter(t => getToolStage(t) === activeStage.value)
)

const stageToolCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const s of stages.value) {
    counts[s.slug] = props.tools.filter(t => getToolStage(t) === s.slug).length
  }
  return counts
})

function selectStage(slug: string) {
  activeStage.value = slug
  const url = new URL(window.location.href)
  url.searchParams.set('stage', slug)
  window.history.replaceState({}, '', url.toString())
}

// Store Context
const storagePrefix = 'makeai_ecom_'
const contextCollapsed = ref(false)
const showSaveToast = ref(false)
const showContextPanel = computed(() => String(props.showContextPanel) !== '0')
const contextPanelLabel = computed(() => props.contextPanelLabel || t('Your Store Context'))
const hasSavedContext = computed(() => !!storeContext.store_name || !!storeContext.product_category)

const contextPlaceholders = {
  store_name: t('e.g. "My Shopify Store"'),
  category: t('e.g. "Women\'s Clothing"'),
}

const storeContext = reactive({
  store_name: '',
  product_category: '',
  brand_tone: 'Professional',
})

function saveContext() {
  localStorage.setItem(storagePrefix + 'store_name', storeContext.store_name)
  localStorage.setItem(storagePrefix + 'product_category', storeContext.product_category)
  localStorage.setItem(storagePrefix + 'brand_tone', storeContext.brand_tone)
  showSaveToast.value = true
  setTimeout(() => { showSaveToast.value = false }, 2000)
}

function saveCollapsedState() {
  localStorage.setItem(storagePrefix + 'context_collapsed', contextCollapsed.value ? '1' : '0')
}

function buildToolUrl(slug: string): string {
  const params = new URLSearchParams()
  if (storeContext.store_name) params.set('prefill_store_name', storeContext.store_name)
  if (storeContext.product_category) params.set('prefill_product_category', storeContext.product_category)
  if (storeContext.brand_tone) params.set('prefill_brand_tone', storeContext.brand_tone)
  const qs = params.toString()
  return '/ai-tools/' + slug + (qs ? '?' + qs : '')
}

onMounted(() => {
  storeContext.store_name = localStorage.getItem(storagePrefix + 'store_name') ?? ''
  storeContext.product_category = localStorage.getItem(storagePrefix + 'product_category') ?? ''
  storeContext.brand_tone = localStorage.getItem(storagePrefix + 'brand_tone') ?? 'Professional'
  contextCollapsed.value = localStorage.getItem(storagePrefix + 'context_collapsed') === '1'

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

@keyframes fade-in-up {
  0% { opacity: 0; transform: translateY(10px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>
