<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()

const rawTools = (page.props.tools as Array<{ slug: string; name: string }>) ?? []
const toolOptions = computed(() => rawTools.map(t => ({ value: t.slug, label: t.name })))
const editing = (page.props.editing as boolean) ?? false
const chain = page.props.chain as any

const name = ref(chain?.name ?? '')
const steps = ref<Array<{ tool_slug: string; static_inputs: Record<string, string>; field_map: Record<string, string> }>>(
  chain?.steps?.map((s: any) => ({ tool_slug: s.tool_slug, static_inputs: s.static_inputs ?? {}, field_map: s.field_map ?? {} })) ?? [{ tool_slug: '', static_inputs: {}, field_map: {} }]
)

function addStep() {
  if (steps.value.length >= 5) return
  steps.value.push({ tool_slug: '', static_inputs: {}, field_map: {} })
}

function removeStep(index: number) {
  if (steps.value.length <= 1) return
  steps.value.splice(index, 1)
}

function save() {
  if (editing) {
    router.put(route('user.dashboard.chains.update', chain.ulid), { name: name.value, steps: steps.value })
  } else {
    router.post(route('user.dashboard.chains.store'), { name: name.value, steps: steps.value })
  }
}
</script>

<template>
  <div>
    <div class="mb-6">
      <Link :href="route('user.dashboard.chains.index')" class="mb-3 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        {{ t('Back to chains') }}
      </Link>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Chain') : t('New Chain') }}</h1>
    </div>

    <div class="max-w-2xl space-y-6">
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ t('Chain name') }}</label>
        <input v-model="name" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
      </div>

      <div>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ t('Steps') }} ({{ steps.length }}/5)</h2>
          <button v-if="steps.length < 5" @click="addStep" class="text-xs font-medium text-primary-600 hover:text-primary-700">{{ t('Add step') }}</button>
        </div>

        <div class="space-y-3">
          <div v-for="(step, idx) in steps" :key="idx" class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ idx + 1 }}</span>
            <AppSelect
              v-model="step.tool_slug"
              :options="toolOptions"
              :placeholder="t('Select a tool...')"
              :search-placeholder="t('Type to search tools...')"
              live-search
              :size="8"
              class="flex-1"
            />
            <button @click="removeStep(idx)" class="shrink-0 rounded p-1 text-gray-400 hover:text-red-500">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
        </div>
      </div>

      <button @click="save" class="btn-primary rounded-xl px-6 py-2.5 text-sm font-semibold">
        {{ editing ? t('Save Changes') : t('Create Chain') }}
      </button>
    </div>
  </div>
</template>
