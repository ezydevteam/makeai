<template>
  <AppLayout>
    <div class="template-wrapper" :style="cssVars">
      <Head>
        <title>{{ template.meta_title ?? 'Academic Writer — ' + $page.props.appName }}</title>
        <meta name="description" :content="template.meta_description ?? template.tagline" />
      </Head>
      <article v-if="template.custom_html_head" v-html="template.custom_html_head" />

      <!-- Hero Banner (editorial) -->
      <header class="py-16 md:py-24" :style="{ background: cssLinearBg }">
        <div class="max-w-4xl mx-auto px-6 text-center">
          <h1 class="text-3xl md:text-5xl font-bold tracking-tight" :style="{ color: cssVars['--t-text'], fontFamily: 'Georgia, serif' }">
            {{ template.hero_headline ?? 'Write smarter, not harder' }}
          </h1>
          <p v-if="template.hero_subheadline" class="mt-4 text-lg leading-relaxed max-w-2xl mx-auto" :style="{ color: cssVars['--t-text'], opacity: 0.65 }">
            {{ template.hero_subheadline }}
          </p>
          <Link
            v-if="template.hero_cta_text"
            :href="template.hero_cta_url"
            class="inline-flex items-center mt-8 px-8 py-3 rounded-xl text-white font-semibold shadow-md hover:shadow-lg transition-all"
            :style="{ background: cssVars['--t-primary'] }"
          >
            {{ template.hero_cta_text }}
          </Link>
        </div>
      </header>

      <!-- Writing Stage Flow (connected step indicator) -->
      <div :style="{ background: cssVars['--t-surface'] }">
        <div class="max-w-4xl mx-auto px-6 py-6">
          <div class="flex items-center justify-center">
            <template v-for="(stage, i) in stages" :key="stage.slug">
              <button
                class="flex items-center gap-3 px-5 py-3 rounded-xl transition-all text-sm font-semibold"
                :class="{
                  'shadow-md': activeStage === stage.slug,
                  'opacity-70 hover:opacity-100': activeStage !== stage.slug,
                }"
                :style="{
                  background: activeStage === stage.slug ? cssVars['--t-primary'] : 'transparent',
                  color: activeStage === stage.slug ? '#fff' : cssVars['--t-text'],
                }"
                @click="selectStage(stage.slug)"
              >
                <span
                  class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold"
                  :style="{
                    background: activeStage === stage.slug ? 'rgba(255,255,255,0.2)' : (cssVars['--t-primary'] + '20'),
                    color: activeStage === stage.slug ? '#fff' : cssVars['--t-primary'],
                  }"
                >
                  {{ i + 1 }}
                </span>
                <span class="hidden sm:inline">{{ stage.label }}</span>
                <span
                  class="hidden sm:inline-flex items-center justify-center min-w-[20px] h-[20px] rounded-full text-xs font-bold"
                  :style="{
                    background: activeStage === stage.slug ? 'rgba(255,255,255,0.2)' : (cssVars['--t-border'] || '#e5e7eb'),
                    color: activeStage === stage.slug ? '#fff' : cssVars['--t-text'],
                  }"
                >
                  {{ stageToolCounts[stage.slug] ?? 0 }}
                </span>
              </button>
              <div
                v-if="i < stages.length - 1"
                class="hidden sm:block h-px w-8 mx-1"
                :style="{
                  background: i < currentStageIndex ? cssVars['--t-primary'] : (cssVars['--t-border'] || '#e5e7eb'),
                }"
              ></div>
            </template>
          </div>
        </div>
      </div>

      <!-- Academic Context Panel -->
      <div v-if="showContextPanel" class="border-y" :style="{ background: cssVars['--t-bg'] + '80', borderColor: cssVars['--t-border'] || '#e5e7eb' }">
        <div class="max-w-4xl mx-auto px-6">
          <div v-show="!contextCollapsed" class="py-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-5">
            <label class="flex-1 block">
              <span class="block text-xs font-bold mb-1.5" :style="{ color: cssVars['--t-text'], opacity: 0.6 }">{{ t('Subject') }}</span>
              <input
                v-model="academicContext.subject"
                type="text"
                class="w-full rounded-xl border px-4 py-2.5 text-sm"
                :style="{ borderColor: cssVars['--t-border'] || '#e5e7eb', background: cssVars['--t-surface'], color: cssVars['--t-text'] }"
                :placeholder="contextSubjectPlaceholder"
              />
            </label>
            <label class="flex-1 block">
              <span class="block text-xs font-bold mb-1.5" :style="{ color: cssVars['--t-text'], opacity: 0.6 }">{{ t('Level') }}</span>
              <select
                v-model="academicContext.level"
                class="w-full rounded-xl border px-4 py-2.5 text-sm"
                :style="{ borderColor: cssVars['--t-border'] || '#e5e7eb', background: cssVars['--t-surface'], color: cssVars['--t-text'] }"
              >
                <option v-for="lvl in activeLevels" :key="lvl.label" :value="lvl.label">{{ lvl.label }}</option>
              </select>
            </label>
            <label class="flex-1 block">
              <span class="block text-xs font-bold mb-1.5" :style="{ color: cssVars['--t-text'], opacity: 0.6 }">{{ t('Citation Style') }}</span>
              <select
                v-model="academicContext.citation"
                class="w-full rounded-xl border px-4 py-2.5 text-sm"
                :style="{ borderColor: cssVars['--t-border'] || '#e5e7eb', background: cssVars['--t-surface'], color: cssVars['--t-text'] }"
              >
                <option v-for="cs in activeCitationStyles" :key="cs.label" :value="cs.label">{{ cs.label }}</option>
              </select>
            </label>
            <button
              class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md hover:shadow-lg transition-all"
              :style="{ background: cssVars['--t-primary'] }"
              @click="saveContext"
            >
              <i class="ti ti-device-floppy text-base"></i>
              {{ t('Save Context') }}
            </button>
          </div>
          <button
            class="w-full flex items-center justify-center gap-2 py-2.5 text-xs font-medium transition-colors"
            :style="{ color: cssVars['--t-text'], opacity: 0.5 }"
            @click="contextCollapsed = !contextCollapsed; saveCollapsedState()"
          >
            <i :class="contextCollapsed ? 'ti ti-chevron-down' : 'ti ti-chevron-up'" class="text-sm"></i>
            {{ contextPanelLabel }}
            <span v-if="contextCollapsed && hasSavedContext" :style="{ opacity: 0.7 }">
              ({{ academicContext.subject || t('No subject set') }})
            </span>
          </button>
        </div>
      </div>

      <!-- Tool Grid -->
      <section class="py-12 md:py-20" :style="{ background: cssVars['--t-bg'] }">
        <div class="max-w-7xl mx-auto px-6">
          <div v-if="filteredTools.length === 0" class="text-center py-16" :style="{ color: cssVars['--t-text'], opacity: 0.4 }">
            <i class="ti ti-mood-empty text-5xl mb-4 block"></i>
            <p class="text-lg font-medium">{{ t('No tools available for this stage yet.') }}</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="tool in filteredTools"
              :key="tool.slug"
              class="group rounded-2xl p-6 transition-all hover:shadow-md flex flex-col"
              :style="{ background: cssVars['--t-surface'], border: '1px solid ' + (cssVars['--t-border'] || '#e5e7eb') }"
            >
              <div class="flex items-start gap-3 mb-3">
                <span
                  v-if="tool.icon"
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                  :style="{ background: (tool.color ?? cssVars['--t-primary']) + '15' }"
                >
                  <i :class="tool.icon" class="text-lg" :style="{ color: tool.color ?? cssVars['--t-primary'] }"></i>
                </span>
                <h3 class="text-lg font-semibold" :style="{ color: cssVars['--t-text'] }">{{ tool.name }}</h3>
              </div>
              <p class="text-sm flex-1" :style="{ color: cssVars['--t-text'], opacity: 0.55 }">{{ tool.description }}</p>
              <Link
                :href="buildToolUrl(tool.slug)"
                class="inline-flex items-center gap-1 mt-auto pt-3 text-sm font-semibold"
                :style="{ color: cssVars['--t-primary'] }"
              >
                {{ t('Use Tool') }} →
              </Link>
            </div>
          </div>
        </div>
      </section>

      <div v-if="showSaveToast" class="fixed bottom-6 right-6 z-50 rounded-2xl shadow-xl px-5 py-3 text-sm font-semibold animate-[fade-in-up_0.3s_ease]" :style="{ background: cssVars['--t-text'], color: cssVars['--t-surface'] }">
        {{ t('Academic context saved') }}
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

interface LevelOption {
  label: string
  enabled: boolean | string
}

interface CitationOption {
  label: string
  enabled: boolean | string
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
  academicStageSettings?: StageDef[]
  defaultStage?: string | null
  showContextPanel?: string | boolean
  contextPanelLabel?: string | null
  contextSubjectPlaceholder?: string | null
  academicLevels?: LevelOption[]
  defaultLevel?: string | null
  academicCitationStyles?: CitationOption[]
  defaultCitation?: string | null
}>()

const cssVars = computed(() => ({
  '--t-primary': props.template.color_primary ?? '#2c5282',
  '--t-secondary': props.template.color_secondary ?? '#4a5568',
  '--t-bg': props.template.color_bg ?? '#fafafa',
  '--t-surface': props.template.color_surface ?? '#ffffff',
  '--t-text': props.template.color_text ?? '#1a202c',
  '--t-border': '#e5e7eb',
}))

const cssLinearBg = computed(() => `linear-gradient(180deg, ${cssVars.value['--t-surface']} 0%, ${cssVars.value['--t-bg']} 100%)`)

const stages = computed<StageDef[]>(() =>
  props.academicStageSettings ?? [
    { slug: 'research', label: 'Research', icon: 'ti ti-search' },
    { slug: 'outline', label: 'Outline', icon: 'ti ti-list-tree' },
    { slug: 'write', label: 'Write', icon: 'ti ti-pencil' },
    { slug: 'polish', label: 'Polish', icon: 'ti ti-sparkles' },
  ]
)

const activeStage = ref(props.defaultStage ?? 'research')

const currentStageIndex = computed(() => stages.value.findIndex(s => s.slug === activeStage.value))

function parseTags(tool: ToolItem): Record<string, any> {
  if (!tool.tags) return {}
  if (typeof tool.tags === 'string') {
    try { return JSON.parse(tool.tags) } catch { return {} }
  }
  return tool.tags
}

function getWritingStage(tool: ToolItem): string | null {
  return parseTags(tool).writing_stage ?? null
}

const filteredTools = computed(() =>
  props.tools.filter(t => getWritingStage(t) === activeStage.value)
)

const stageToolCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const s of stages.value) {
    counts[s.slug] = props.tools.filter(t => getWritingStage(t) === s.slug).length
  }
  return counts
})

function selectStage(slug: string) {
  activeStage.value = slug
  const url = new URL(window.location.href)
  url.searchParams.set('stage', slug)
  window.history.replaceState({}, '', url.toString())
}

// Academic Context
const storagePrefix = 'makeai_academic_'
const contextCollapsed = ref(false)
const showSaveToast = ref(false)
const showContextPanel = computed(() => String(props.showContextPanel) !== '0')
const contextPanelLabel = computed(() => props.contextPanelLabel || t('Academic Context'))
const contextSubjectPlaceholder = computed(() => props.contextSubjectPlaceholder || t('e.g. "Environmental Science"'))
const hasSavedContext = computed(() => !!academicContext.subject || academicContext.level !== '')

const activeLevels = computed(() => {
  if (props.academicLevels?.length) return props.academicLevels.filter(l => String(l.enabled) !== '0')
  return [
    { label: 'High School', enabled: true },
    { label: 'Undergraduate', enabled: true },
    { label: 'Graduate', enabled: true },
    { label: 'PhD', enabled: true },
    { label: 'Professional', enabled: true },
  ]
})

const activeCitationStyles = computed(() => {
  if (props.academicCitationStyles?.length) return props.academicCitationStyles.filter(cs => String(cs.enabled) !== '0')
  return [
    { label: 'APA', enabled: true },
    { label: 'MLA', enabled: true },
    { label: 'Chicago', enabled: true },
    { label: 'Harvard', enabled: true },
    { label: 'IEEE', enabled: true },
  ]
})

const academicContext = reactive({
  subject: '',
  level: props.defaultLevel ?? 'Undergraduate',
  citation: props.defaultCitation ?? 'APA',
})

function saveContext() {
  localStorage.setItem(storagePrefix + 'subject', academicContext.subject)
  localStorage.setItem(storagePrefix + 'level', academicContext.level)
  localStorage.setItem(storagePrefix + 'citation', academicContext.citation)
  showSaveToast.value = true
  setTimeout(() => { showSaveToast.value = false }, 2000)
}

function saveCollapsedState() {
  localStorage.setItem(storagePrefix + 'context_collapsed', contextCollapsed.value ? '1' : '0')
}

function buildToolUrl(slug: string): string {
  const params = new URLSearchParams()
  if (academicContext.subject) params.set('prefill_subject', academicContext.subject)
  if (academicContext.level) params.set('prefill_level', academicContext.level)
  if (academicContext.citation) params.set('prefill_citation', academicContext.citation)
  const qs = params.toString()
  return '/ai-tools/' + slug + (qs ? '?' + qs : '')
}

onMounted(() => {
  academicContext.subject = localStorage.getItem(storagePrefix + 'subject') ?? ''
  academicContext.level = localStorage.getItem(storagePrefix + 'level') ?? props.defaultLevel ?? 'Undergraduate'
  academicContext.citation = localStorage.getItem(storagePrefix + 'citation') ?? props.defaultCitation ?? 'APA'
  contextCollapsed.value = localStorage.getItem(storagePrefix + 'context_collapsed') === '1'

  const params = new URLSearchParams(window.location.search)
  const stageParam = params.get('stage')
  if (stageParam && stages.value.some(s => s.slug === stageParam)) {
    activeStage.value = stageParam
  }
})
</script>

<style scoped>
@keyframes fade-in-up {
  0% { opacity: 0; transform: translateY(10px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>
