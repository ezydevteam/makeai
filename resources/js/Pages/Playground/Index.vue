<script setup lang="ts">
import { watch, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { usePlayground, playgroundState } from '@/composables/usePlayground'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { runBoth, shareSnapshot, clearOutputs } = usePlayground()

interface ProviderOption {
  slug: string
  name: string
  models: Array<{ slug: string; name: string }>
}

const rawProviders = (page.props.providers as ProviderOption[]) ?? []

const providerOptions = computed(() =>
  rawProviders.map(p => ({ value: p.slug, label: p.name }))
)

const leftModels = computed(() => {
  const p = rawProviders.find(pr => pr.slug === playgroundState.leftPanel.provider)
  return (p?.models ?? []).map(m => ({ value: m.slug, label: m.name }))
})

const rightModels = computed(() => {
  const p = rawProviders.find(pr => pr.slug === playgroundState.rightPanel.provider)
  return (p?.models ?? []).map(m => ({ value: m.slug, label: m.name }))
})

watch(() => playgroundState.syncPanels, (sync) => {
  if (sync && playgroundState.rightPanel.systemPrompt !== playgroundState.leftPanel.systemPrompt) {
    playgroundState.rightPanel.systemPrompt = playgroundState.leftPanel.systemPrompt
  }
})
</script>

<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">AI Playground</h1>
      <div class="flex items-center gap-2">
        <Link
          :href="route('ai.tools.index')"
          class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
        >
          Save as AI Tool
        </Link>
        <button
          @click="clearOutputs"
          class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
        >
          Clear
        </button>
      </div>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-4">
      <!-- Left Panel -->
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 grid grid-cols-2 gap-3">
          <AppSelect
            v-model="playgroundState.leftPanel.provider"
            :options="providerOptions"
            :label="'Provider'"
            :placeholder="'Select a provider...'"
            live-search
            :size="8"
          />
          <AppSelect
            v-model="playgroundState.leftPanel.model"
            :options="leftModels"
            :label="'Model'"
            :placeholder="'Select a model...'"
            live-search
            :size="8"
          />
        </div>
        <div class="mb-3 grid grid-cols-3 gap-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Temperature</label>
            <input v-model.number="playgroundState.leftPanel.temperature" type="number" step="0.1" min="0" max="2" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Top P</label>
            <input v-model.number="playgroundState.leftPanel.topP" type="number" step="0.1" min="0" max="1" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Max Tokens</label>
            <input v-model.number="playgroundState.leftPanel.maxTokens" type="number" min="1" max="32000" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
        <template v-if="!playgroundState.syncPanels">
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">System Prompt</label>
          <textarea
            v-model="playgroundState.leftPanel.systemPrompt"
            rows="2"
            placeholder="Optional system instructions..."
            class="mb-3 w-full rounded-lg border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </template>
        <div class="relative">
          <textarea
            :value="playgroundState.leftPanel.output"
            readonly
            rows="12"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            :class="{ 'animate-pulse': playgroundState.leftPanel.streaming }"
            placeholder="Output will appear here..."
          />
          <div v-if="playgroundState.leftPanel.tokens.input > 0" class="mt-1 flex justify-between text-[10px] text-gray-400">
            <span>In: {{ playgroundState.leftPanel.tokens.input }} · Out: {{ playgroundState.leftPanel.tokens.output }}</span>
            <button @click="navigator.clipboard.writeText(playgroundState.leftPanel.output)" class="hover:text-gray-600 dark:hover:text-gray-300">Copy</button>
          </div>
        </div>
      </div>

      <!-- Right Panel -->
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-3 grid grid-cols-2 gap-3">
          <AppSelect
            v-model="playgroundState.rightPanel.provider"
            :options="providerOptions"
            :label="'Provider'"
            :placeholder="'Select a provider...'"
            live-search
            :size="8"
          />
          <AppSelect
            v-model="playgroundState.rightPanel.model"
            :options="rightModels"
            :label="'Model'"
            :placeholder="'Select a model...'"
            live-search
            :size="8"
          />
        </div>
        <div class="mb-3 grid grid-cols-3 gap-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Temperature</label>
            <input v-model.number="playgroundState.rightPanel.temperature" type="number" step="0.1" min="0" max="2" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Top P</label>
            <input v-model.number="playgroundState.rightPanel.topP" type="number" step="0.1" min="0" max="1" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Max Tokens</label>
            <input v-model.number="playgroundState.rightPanel.maxTokens" type="number" min="1" max="32000" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
        <template v-if="!playgroundState.syncPanels">
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">System Prompt</label>
          <textarea
            v-model="playgroundState.rightPanel.systemPrompt"
            rows="2"
            placeholder="Optional system instructions..."
            class="mb-3 w-full rounded-lg border border-gray-200 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
        </template>
        <div class="relative">
          <textarea
            :value="playgroundState.rightPanel.output"
            readonly
            rows="12"
            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            :class="{ 'animate-pulse': playgroundState.rightPanel.streaming }"
            placeholder="Output will appear here..."
          />
          <div v-if="playgroundState.rightPanel.tokens.input > 0" class="mt-1 flex justify-between text-[10px] text-gray-400">
            <span>In: {{ playgroundState.rightPanel.tokens.input }} · Out: {{ playgroundState.rightPanel.tokens.output }}</span>
            <button @click="navigator.clipboard.writeText(playgroundState.rightPanel.output)" class="hover:text-gray-600 dark:hover:text-gray-300">Copy</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Shared Controls -->
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <div class="mb-3 flex items-center gap-3">
        <input
          v-model="playgroundState.sharedMessage"
          placeholder="Type your message here..."
          class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          @keyup.enter="runBoth"
        />
        <label class="flex cursor-pointer items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
          <input v-model="playgroundState.syncPanels" type="checkbox" class="rounded" />
          Sync system prompts
        </label>
        <button
          @click="runBoth"
          :disabled="!playgroundState.sharedMessage || playgroundState.leftPanel.streaming || playgroundState.rightPanel.streaming"
          class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-50"
        >
          {{ playgroundState.leftPanel.streaming || playgroundState.rightPanel.streaming ? 'Running...' : 'Run Both →' }}
        </button>
        <button
          @click="shareSnapshot"
          :disabled="!playgroundState.leftPanel.output && !playgroundState.rightPanel.output"
          class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
        >
          Share
        </button>
      </div>

      <!-- History -->
      <details v-if="playgroundState.history.length > 0" class="mt-2">
        <summary class="cursor-pointer text-xs font-medium text-gray-500 dark:text-gray-400">
          History ({{ playgroundState.history.length }})
        </summary>
        <div class="mt-2 max-h-40 space-y-1 overflow-y-auto">
          <div
            v-for="item in playgroundState.history"
            :key="item.id"
            class="cursor-pointer rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            @click="playgroundState.sharedMessage = item.prompt"
          >
            <span class="font-medium">{{ item.prompt.substring(0, 60) }}{{ item.prompt.length > 60 ? '...' : '' }}</span>
            <span class="ml-2 text-gray-400">{{ new Date(item.createdAt).toLocaleTimeString() }}</span>
          </div>
        </div>
      </details>
    </div>
  </div>
</template>
