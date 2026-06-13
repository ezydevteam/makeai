<template>
  <AppLayout>
    <div class="dev-template-wrapper" :style="devCssVars">
      <!-- Google Fonts: JetBrains Mono -->
      <component :is="'link'" rel="preconnect" href="https://fonts.googleapis.com" />
      <component :is="'link'" rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
      <component :is="'link'" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet" />

      <Head>
        <title>{{ template.meta_title ?? 'Developer Assistant — ' + $page.props.appName }}</title>
        <meta name="description" :content="template.meta_description ?? template.tagline" />
      </Head>
      <article v-if="template.custom_html_head" v-html="template.custom_html_head" />

      <!-- Hero Banner -->
      <header class="py-16 md:py-24" :style="{ background: devCssVars['--t-surface'] }">
        <div class="max-w-5xl mx-auto px-6 text-center">
          <h1 class="text-3xl md:text-5xl font-bold mono-font tracking-tight" :style="{ color: devCssVars['--t-text'] }">
            <span>{{ template.hero_headline ?? 'Build &amp; debug faster with AI' }}</span>
            <span class="blinking-cursor">_</span>
          </h1>
          <p v-if="template.hero_subheadline" class="mt-4 text-lg max-w-xl mx-auto" :style="{ color: devCssVars['--t-text'], opacity: 0.65 }">
            {{ template.hero_subheadline }}
          </p>
          <Link
            v-if="template.hero_cta_text"
            :href="template.hero_cta_url"
            class="inline-flex items-center mt-8 px-8 py-3 rounded-xl text-sm font-semibold transition-all shadow-lg hover:shadow-xl"
            :style="{ background: devCssVars['--t-primary'], color: '#fff' }"
          >
            {{ template.hero_cta_text }}
          </Link>
        </div>
      </header>

      <!-- Language Chips -->
      <div class="py-4" :style="{ background: devCssVars['--t-bg'] }">
        <div class="max-w-7xl mx-auto px-6">
          <div class="flex flex-wrap items-center gap-2">
            <button
              class="lang-chip"
              :class="{ 'lang-chip-active': activeLanguage === null }"
              :style="activeLanguage === null ? { background: devCssVars['--t-primary'], color: '#fff', borderColor: devCssVars['--t-primary'] } : { color: devCssVars['--t-text'], borderColor: devCssVars['--t-border'] }"
              @click="selectLanguage(null)"
            >
              {{ t('All') }}
            </button>
            <button
              v-for="lang in visibleLanguages"
              :key="lang.slug"
              class="lang-chip"
              :class="{ 'lang-chip-active': activeLanguage === lang.slug }"
              :style="activeLanguage === lang.slug ? { background: devCssVars['--t-primary'], color: '#fff', borderColor: devCssVars['--t-primary'] } : { color: devCssVars['--t-text'], borderColor: devCssVars['--t-border'] }"
              @click="selectLanguage(lang.slug)"
            >
              {{ lang.label }}
            </button>
            <div v-if="overflowLanguages.length > 0" class="relative" @mouseenter="showLangPopover = true" @mouseleave="showLangPopover = false">
              <button
                class="lang-chip"
                :style="{ color: devCssVars['--t-text'], borderColor: devCssVars['--t-border'] }"
              >
                +{{ overflowLanguages.length }}
              </button>
              <div
                v-if="showLangPopover"
                class="absolute top-full left-0 mt-1 rounded-xl border p-2 z-30 flex flex-wrap gap-1.5 min-w-[200px] shadow-xl"
                :style="{ background: devCssVars['--t-surface'], borderColor: devCssVars['--t-border'] }"
              >
                <button
                  v-for="lang in overflowLanguages"
                  :key="lang.slug"
                  class="lang-chip"
                  :class="{ 'lang-chip-active': activeLanguage === lang.slug }"
                  :style="activeLanguage === lang.slug ? { background: devCssVars['--t-primary'], color: '#fff', borderColor: devCssVars['--t-primary'] } : { color: devCssVars['--t-text'], borderColor: devCssVars['--t-border'] }"
                  @click="selectLanguage(lang.slug); showLangPopover = false"
                >
                  {{ lang.label }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Tabs -->
      <div class="border-b" :style="{ borderColor: devCssVars['--t-border'], background: devCssVars['--t-bg'] }">
        <div class="max-w-7xl mx-auto px-6">
          <div class="flex gap-1 overflow-x-auto py-2 scrollbar-hide">
            <button
              v-for="cat in categories"
              :key="cat.slug"
              class="shrink-0 relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-lg transition-all"
              :class="activeCategory === cat.slug ? 'text-white' : ''"
              :style="activeCategory === cat.slug
                ? { background: devCssVars['--t-primary'] }
                : { color: devCssVars['--t-text'], opacity: 0.65 }"
              @click="selectCategory(cat.slug)"
            >
              <i :class="cat.icon" class="text-base"></i>
              {{ cat.label }}
              <span
                class="inline-flex items-center justify-center min-w-[20px] h-[20px] rounded-full text-xs font-bold"
                :style="activeCategory === cat.slug ? 'background: rgba(255,255,255,0.15); color: #fff' : { background: devCssVars['--t-border'], color: devCssVars['--t-text'] }"
              >
                {{ categoryToolCounts[cat.slug] ?? 0 }}
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Tool Grid -->
      <section class="py-12 md:py-20" :style="{ background: devCssVars['--t-bg'] }">
        <div class="max-w-7xl mx-auto px-6">
          <div v-if="filteredTools.length === 0" class="text-center py-16" :style="{ color: devCssVars['--t-text'], opacity: 0.4 }">
            <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
            <p class="text-lg mono-font font-medium">{{ t('No tools available for this category yet.') }}</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div
              v-for="tool in filteredTools"
              :key="tool.slug"
              class="group rounded-2xl border p-6 transition-all hover:shadow-lg"
              :style="{ background: devCssVars['--t-surface'], borderColor: devCssVars['--t-border'] }"
            >
              <div class="flex items-center gap-3 mb-3">
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                  :style="{ background: (tool.color ?? devCssVars['--t-primary']) + '20' }"
                >
                  <i
                    v-if="tool.icon"
                    :class="tool.icon"
                    class="text-lg"
                    :style="{ color: tool.color ?? devCssVars['--t-primary'] }"
                  ></i>
                  <span v-else class="text-sm font-bold code-badge" :style="{ color: devCssVars['--t-primary'] }">&lt;/&gt;</span>
                </span>
                <div class="flex-1">
                  <h3 class="text-lg font-semibold mono-font" :style="{ color: devCssVars['--t-text'] }">{{ tool.name }}</h3>
                </div>
                <span class="code-badge shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold" :style="{ background: devCssVars['--t-primary'] + '15', color: devCssVars['--t-primary'] }">
                  &lt;/&gt;
                </span>
              </div>
              <p class="text-sm mb-4" :style="{ color: devCssVars['--t-text'], opacity: 0.55 }">{{ tool.description }}</p>
              <Link
                :href="buildToolUrl(tool.slug)"
                class="inline-flex items-center gap-1 text-sm font-semibold"
                :style="{ color: devCssVars['--t-primary'] }"
              >
                {{ t('Open Tool') }} →
              </Link>
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

interface CategoryDef {
  slug: string
  label: string
  icon: string
}

interface LanguageDef {
  slug: string
  label: string
  visible: boolean
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
  devCategorySettings?: CategoryDef[]
  defaultCategory?: string | null
  devLanguageSettings?: LanguageDef[]
}>()

const devCssVars = computed(() => ({
  '--t-primary': props.template.color_primary ?? '#58a6ff',
  '--t-secondary': props.template.color_secondary ?? '#3fb950',
  '--t-bg': props.template.color_bg ?? '#0d1117',
  '--t-surface': props.template.color_surface ?? '#161b22',
  '--t-text': props.template.color_text ?? '#e6edf3',
  '--t-border': '#30363d',
}))

const categories = computed<CategoryDef[]>(() =>
  props.devCategorySettings ?? [
    { slug: 'generate', label: 'Generate', icon: 'ti ti-code-plus' },
    { slug: 'debug', label: 'Debug', icon: 'ti ti-bug' },
    { slug: 'optimize', label: 'Optimize', icon: 'ti ti-bolt' },
    { slug: 'document', label: 'Document', icon: 'ti ti-file-text' },
  ]
)

const languageList = computed<LanguageDef[]>(() =>
  props.devLanguageSettings ?? [
    { slug: 'python', label: 'Python', visible: true },
    { slug: 'javascript', label: 'JavaScript', visible: true },
    { slug: 'typescript', label: 'TypeScript', visible: true },
    { slug: 'php', label: 'PHP', visible: true },
    { slug: 'go', label: 'Go', visible: true },
    { slug: 'rust', label: 'Rust', visible: true },
    { slug: 'sql', label: 'SQL', visible: true },
    { slug: 'bash', label: 'Bash', visible: true },
    { slug: 'csharp', label: 'C#', visible: false },
    { slug: 'swift', label: 'Swift', visible: false },
  ]
)

const visibleLanguages = computed(() =>
  languageList.value.filter(l => l.visible).slice(0, 8)
)

const overflowLanguages = computed(() =>
  languageList.value.filter(l => l.visible).slice(8)
)

const activeCategory = ref(props.defaultCategory ?? 'generate')
const activeLanguage = ref<string | null>(null)
const showLangPopover = ref(false)

function parseTags(tool: ToolItem): Record<string, any> {
  if (!tool.tags) return {}
  if (typeof tool.tags === 'string') {
    try { return JSON.parse(tool.tags) } catch { return {} }
  }
  return tool.tags
}

function getDevCategory(tool: ToolItem): string | null {
  return parseTags(tool).dev_category ?? null
}

const filteredTools = computed(() =>
  props.tools.filter(t => getDevCategory(t) === activeCategory.value)
)

const categoryToolCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const c of categories.value) {
    counts[c.slug] = props.tools.filter(t => getDevCategory(t) === c.slug).length
  }
  return counts
})

function selectCategory(slug: string) {
  activeCategory.value = slug
  updateUrlParams()
}

function selectLanguage(slug: string | null) {
  activeLanguage.value = slug
  localStorage.setItem('makeai_dev_language', slug ?? '')
  updateUrlParams()
}

function updateUrlParams() {
  const url = new URL(window.location.href)
  url.searchParams.set('category', activeCategory.value)
  if (activeLanguage.value) {
    url.searchParams.set('lang', activeLanguage.value)
  } else {
    url.searchParams.delete('lang')
  }
  window.history.replaceState({}, '', url.toString())
}

function buildToolUrl(slug: string): string {
  const params = new URLSearchParams()
  params.set('prefill_output_format', 'code')
  if (activeLanguage.value) {
    params.set('prefill_language', activeLanguage.value)
  }
  return '/ai-tools/' + slug + '?' + params.toString()
}

onMounted(() => {
  const savedLang = localStorage.getItem('makeai_dev_language')
  const params = new URLSearchParams(window.location.search)

  if (savedLang && languageList.value.some(l => l.slug === savedLang)) {
    activeLanguage.value = savedLang
  }
  const langParam = params.get('lang')
  if (langParam && languageList.value.some(l => l.slug === langParam)) {
    activeLanguage.value = langParam
  }

  const catParam = params.get('category')
  if (catParam && categories.value.some(c => c.slug === catParam)) {
    activeCategory.value = catParam
  }
})
</script>

<style scoped>
.dev-template-wrapper,
.dev-template-wrapper * {
  font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace !important;
}

.dev-template-wrapper i {
  font-family: 'tabler-icons' !important;
}

.mono-font {
  font-family: inherit;
}

.lang-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.85rem;
  border-radius: 9999px;
  border: 1px solid;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  background: transparent;
}

.lang-chip:hover {
  opacity: 0.8;
}

.code-badge {
  font-family: inherit;
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

@keyframes blink {
  0%, 100% { opacity: 0; }
  50% { opacity: 1; }
}

.blinking-cursor {
  display: inline-block;
  animation: blink 1s step-end infinite;
  color: var(--t-primary, #58a6ff);
  font-weight: 700;
}
</style>
