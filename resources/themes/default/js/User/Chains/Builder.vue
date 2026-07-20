<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()

interface ToolField {
  key: string
  label: string
  type: string
  required: boolean
  options: string[]
  default: string | null
}

interface ToolOption {
  slug: string
  name: string
  fields: ToolField[]
}

/** How a step's field gets its value. */
type Source = 'none' | 'input' | 'previous' | 'static' | `step:${number}`

interface StepState {
  tool_slug: string
  /** field key → where its value comes from */
  sources: Record<string, Source>
  /** field key → the fixed value, when source is 'static' */
  statics: Record<string, string>
}

const rawTools = (page.props.tools as ToolOption[]) ?? []
const toolOptions = computed(() => rawTools.map(tool => ({ value: tool.slug, label: tool.name })))
const editing = (page.props.editing as boolean) ?? false
const chain = page.props.chain as any
const errors = computed(() => (page.props.errors as Record<string, string>) ?? {})

const fieldsFor = (slug: string): ToolField[] => rawTools.find(tool => tool.slug === slug)?.fields ?? []

/** Turn a stored template token back into the source the builder shows. */
function sourceFromTemplate(template: string): Source {
  if (template === '{{input}}') return 'input'
  if (template === '{{previous_output}}') return 'previous'

  const step = template.match(/^\{\{step_(\d+)_output\}\}$/)

  return step ? (`step:${Number(step[1])}` as Source) : 'none'
}

function templateFromSource(source: Source): string | null {
  if (source === 'input') return '{{input}}'
  if (source === 'previous') return '{{previous_output}}'
  if (source.startsWith('step:')) return `{{step_${source.slice(5)}_output}}`

  return null
}

function hydrateStep(step: any): StepState {
  const sources: Record<string, Source> = {}
  const statics: Record<string, string> = {}

  Object.entries(step.field_map ?? {}).forEach(([key, template]) => {
    sources[key] = sourceFromTemplate(String(template))
  })

  Object.entries(step.static_inputs ?? {}).forEach(([key, value]) => {
    sources[key] = 'static'
    statics[key] = String(value)
  })

  return { tool_slug: step.tool_slug ?? '', sources, statics }
}

const name = ref(chain?.name ?? '')
const steps = ref<StepState[]>(
  chain?.steps?.length
    ? chain.steps.map(hydrateStep)
    : [
        { tool_slug: '', sources: {}, statics: {} },
        { tool_slug: '', sources: {}, statics: {} },
      ]
)

/**
 * A step with every field left unmapped runs its tool with nothing to work on, so
 * pre-wire the first required field to the obvious source: the chain's own input for
 * step 1, the preceding step's output for the rest. That is what a chain is for.
 */
function applyDefaultMapping(index: number) {
  const step = steps.value[index]
  const fields = fieldsFor(step.tool_slug)

  step.sources = {}
  step.statics = {}

  const primary = fields.find(field => field.required) ?? fields[0]

  fields.forEach(field => {
    if (field === primary) {
      step.sources[field.key] = index === 0 ? 'input' : 'previous'
    } else {
      step.sources[field.key] = field.default ? 'static' : 'none'
      if (field.default) step.statics[field.key] = String(field.default)
    }
  })
}

watch(
  () => steps.value.map(step => step.tool_slug).join('|'),
  (next, previous) => {
    const nextSlugs = next.split('|')
    const previousSlugs = (previous ?? '').split('|')

    nextSlugs.forEach((slug, index) => {
      if (slug && slug !== previousSlugs[index]) applyDefaultMapping(index)
    })
  }
)

/** Sources a given step may pull from — only steps that run before it. */
function sourceOptions(index: number) {
  const options = [
    { value: 'none', label: t('Leave empty') },
    { value: 'input', label: t('Chain input') },
    { value: 'static', label: t('Fixed value') },
  ]

  if (index > 0) {
    options.splice(1, 0, { value: 'previous', label: t('Previous step output') })

    for (let n = 1; n < index; n++) {
      options.splice(1 + n, 0, { value: `step:${n}`, label: t('Step :n output', { n }) })
    }
  }

  return options
}

function addStep() {
  if (steps.value.length >= 5) return
  steps.value.push({ tool_slug: '', sources: {}, statics: {} })
}

function removeStep(index: number) {
  if (steps.value.length <= 2) return
  steps.value.splice(index, 1)
}

/** Collapse the builder's per-field sources back into what the backend stores. */
const payloadSteps = computed(() =>
  steps.value.map(step => {
    const field_map: Record<string, string> = {}
    const static_inputs: Record<string, string> = {}

    fieldsFor(step.tool_slug).forEach(field => {
      const source = step.sources[field.key] ?? 'none'

      if (source === 'static') {
        const value = step.statics[field.key] ?? ''
        if (value !== '') static_inputs[field.key] = value

        return
      }

      const template = templateFromSource(source)
      if (template) field_map[field.key] = template
    })

    return { tool_slug: step.tool_slug, field_map, static_inputs }
  })
)

/** A step that receives nothing produces [MISSING: …] output — warn before saving. */
const unmappedSteps = computed(() =>
  payloadSteps.value
    .map((step, index) => ({ step, index }))
    .filter(({ step }) => step.tool_slug && Object.keys(step.field_map).length === 0)
    .map(({ index }) => index + 1)
)

const saving = ref(false)

function save() {
  saving.value = true

  const payload = { name: name.value, steps: payloadSteps.value }
  const options = { onFinish: () => { saving.value = false } }

  if (editing) {
    router.put(route('user.dashboard.chains.update', chain.ulid), payload, options)
  } else {
    router.post(route('user.dashboard.chains.store'), payload, options)
  }
}
</script>

<template>
  <div>
    <Head :title="editing ? t('Edit Chain') : t('New Chain')" />
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Chain') : t('New Chain') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Build a reusable workflow by connecting tools in sequence. Each step feeds the next.') }}</p>
      </div>
      <Link
        :href="route('user.dashboard.chains.index')"
        class="shrink-0 inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:!border-gray-200 hover:!bg-gray-50 hover:text-gray-700 dark:border-surface-700 dark:bg-surface-900/20 dark:text-gray-200 dark:hover:!border-gray-800 dark:hover:!bg-surface-900/30 dark:hover:!text-gray-300"
      >
        <i class="ti ti-arrow-left text-base"></i>
        {{ t('Back') }}
      </Link>
    </div>

    <div class="w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="space-y-6">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Chain name') }}</label>
          <input v-model="name" :placeholder="t('Give a name for this chain')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
        </div>

        <div>
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Steps') }} ({{ steps.length }}/5)</h2>
            <button v-if="steps.length < 5" @click="addStep" class="text-xs font-medium text-primary-600 hover:text-primary-700">
              <i class="ti ti-plus"></i>
              {{ t('Add step') }}
            </button>
          </div>

          <div class="space-y-4">
            <div v-for="(step, idx) in steps" :key="idx" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
              <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ idx + 1 }}</span>
                <AppSelect
                  v-model="step.tool_slug"
                  :options="toolOptions"
                  :placeholder="t('Select a tool...')"
                  :search-placeholder="t('Type to search tools...')"
                  live-search
                  :size="8"
                  class="flex-1"
                  :error="errors[`steps.${idx}.tool_slug`]"
                />
                <button v-if="steps.length > 2" @click="removeStep(idx)" class="shrink-0 rounded p-1 text-gray-400 hover:text-red-500">
                  <i class="ti ti-x text-base"></i>
                </button>
              </div>

              <!-- Field mapping: where each of the tool's inputs comes from -->
              <div v-if="step.tool_slug && fieldsFor(step.tool_slug).length" class="mt-4 space-y-2.5 border-t border-gray-100 pt-4 dark:border-gray-700">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400">{{ t('Inputs for this step') }}</p>

                <div
                  v-for="field in fieldsFor(step.tool_slug)"
                  :key="field.key"
                  class="grid grid-cols-1 items-center gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)]"
                >
                  <label class="truncate text-xs font-medium text-gray-600 dark:text-gray-400">
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-500">*</span>
                  </label>

                  <AppSelect
                    v-model="step.sources[field.key]"
                    :options="sourceOptions(idx)"
                    :placeholder="t('Source...')"
                  />

                  <template v-if="step.sources[field.key] === 'static'">
                    <AppSelect
                      v-if="field.options.length"
                      v-model="step.statics[field.key]"
                      :options="field.options.map(o => ({ value: o, label: o }))"
                      :placeholder="t('Pick a value...')"
                    />
                    <input
                      v-else
                      v-model="step.statics[field.key]"
                      :placeholder="t('Fixed value')"
                      class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                  </template>
                  <span v-else class="hidden text-[11px] text-gray-400 sm:block"></span>
                </div>
              </div>

              <p v-else-if="step.tool_slug" class="mt-3 text-xs text-gray-400">
                {{ t('This tool has no configurable inputs.') }}
              </p>
            </div>
          </div>
          <p v-if="errors.steps" class="mt-1 text-xs text-red-500">{{ errors.steps }}</p>
        </div>

        <div v-if="unmappedSteps.length" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">
          <i class="ti ti-alert-triangle"></i>
          {{ t('Step :steps has no input mapped — it will run with empty fields.', { steps: unmappedSteps.join(', ') }) }}
        </div>

        <button @click="save" :disabled="saving" class="btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold disabled:opacity-50">
          {{ editing ? t('Save Changes') : t('Create Chain') }}
        </button>
      </div>
    </div>
  </div>
</template>
