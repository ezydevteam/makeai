<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { usePlayground, playgroundState } from '@/Composables/usePlayground'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { runBoth, shareSnapshot, clearOutputs } = usePlayground()
const { t } = useTranslate()

const copiedLeft = ref(false)
const copiedRight = ref(false)
const copiedShare = ref(false)

const copyLeftText = () => {
  navigator.clipboard.writeText(playgroundState.leftPanel.output)
  copiedLeft.value = true
  setTimeout(() => {
    copiedLeft.value = false
  }, 2000)
}

const copyRightText = () => {
  navigator.clipboard.writeText(playgroundState.rightPanel.output)
  copiedRight.value = true
  setTimeout(() => {
    copiedRight.value = false
  }, 2000)
}

const handleShare = async () => {
  const url = await shareSnapshot()
  if (url) {
    copiedShare.value = true
    setTimeout(() => {
      copiedShare.value = false
    }, 3000)
  }
}

const loadHistoryItem = (item: any) => {
  playgroundState.sharedMessage = item.prompt
  playgroundState.leftPanel.output = item.outputLeft || ''
  playgroundState.rightPanel.output = item.outputRight || ''

  if (item.paramsLeft) {
    if (item.paramsLeft.provider) playgroundState.leftPanel.provider = item.paramsLeft.provider
    if (item.paramsLeft.model) playgroundState.leftPanel.model = item.paramsLeft.model
    if (item.paramsLeft.temperature !== undefined) playgroundState.leftPanel.temperature = item.paramsLeft.temperature
  }

  if (item.paramsRight) {
    if (item.paramsRight.provider) playgroundState.rightPanel.provider = item.paramsRight.provider
    if (item.paramsRight.model) playgroundState.rightPanel.model = item.paramsRight.model
    if (item.paramsRight.temperature !== undefined) playgroundState.rightPanel.temperature = item.paramsRight.temperature
  }
}

const sharingItemId = ref<string | null>(null)
const copiedHistoryShareId = ref<string | null>(null)

const shareHistoryItem = async (item: any) => {
  sharingItemId.value = item.id
  try {
    const res = await fetch(route('user.dashboard.playground.share'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
      },
      body: JSON.stringify({
        prompt: item.prompt,
        params_left: item.paramsLeft,
        params_right: item.paramsRight,
        output_left: item.outputLeft.substring(0, 5000),
        output_right: item.outputRight.substring(0, 5000),
      }),
    })
    const data = await res.json()
    const fullUrl = window.location.origin + data.url
    await navigator.clipboard.writeText(fullUrl)

    const found = playgroundState.history.find((h: any) => h.id === item.id)
    if (found) {
      found.shareUrl = fullUrl
      localStorage.setItem('playground_history', JSON.stringify(playgroundState.history))
    }
  } catch (err) {
    console.error(err)
  } finally {
    sharingItemId.value = null
  }
}

const copyHistoryShareLink = (url: string, itemId: string) => {
  navigator.clipboard.writeText(url)
  copiedHistoryShareId.value = itemId
  setTimeout(() => {
    copiedHistoryShareId.value = null
  }, 2000)
}

interface ProviderOption {
  slug: string
  name: string
  models: Array<{ slug: string; name: string }>
}

const rawProviders = (page.props.providers as ProviderOption[]) ?? []
const defaultProvider = (page.props.defaultProvider as string) || 'openai'
const defaultModel = (page.props.defaultModel as string) || 'gpt-4o-mini'

// Initialize panels with server-side defaults
onMounted(() => {
  if (!playgroundState.leftPanel.provider || playgroundState.leftPanel.provider === 'openai') {
    playgroundState.leftPanel.provider = defaultProvider
    playgroundState.leftPanel.model = defaultModel
  }
  if (!playgroundState.rightPanel.provider || playgroundState.rightPanel.provider === 'openai') {
    playgroundState.rightPanel.provider = defaultProvider
    playgroundState.rightPanel.model = defaultModel
  }
})

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
  <Head :title="t('AI Playground')" />

  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Playground') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">
          {{ t('Compare providers side by side, tune prompts quickly, and save the best setup as a reusable AI tool.') }}
        </p>
      </div>
      <button
        @click="clearOutputs"
        class="shrink-0 inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:!border-gray-200 hover:!bg-gray-50 hover:text-gray-700 dark:border-surface-700 dark:bg-surface-900/20 dark:text-gray-200 dark:hover:!border-gray-800 dark:hover:!bg-surface-900/30 dark:hover:!text-gray-300"
      >
        <i class="ti ti-x"></i>
        {{ t('Clear') }}
      </button>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <!-- Left Panel -->
      <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Provider A') }}</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Tune one prompt and compare the output against the second panel.') }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-5 p-5">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <AppSelect
            v-model="playgroundState.leftPanel.provider"
            :options="providerOptions"
            :label="t('Provider')"
            :placeholder="t('Select a provider...')"
            live-search
            :size="8"
          />
          <AppSelect
            v-model="playgroundState.leftPanel.model"
            :options="leftModels"
            :label="t('Model')"
            :placeholder="t('Select a model...')"
            live-search
            :size="8"
          />
        </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Temperature') }}</label>
            <input v-model.number="playgroundState.leftPanel.temperature" type="number" step="0.1" min="0" max="2" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Top P') }}</label>
            <input v-model.number="playgroundState.leftPanel.topP" type="number" step="0.1" min="0" max="1" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Max Tokens') }}</label>
            <input v-model.number="playgroundState.leftPanel.maxTokens" type="number" min="1" max="32000" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
          <div v-if="!playgroundState.syncPanels" class="space-y-2">
            <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('System prompt') }}</label>
            <textarea
              v-model="playgroundState.leftPanel.systemPrompt"
              rows="2"
              :placeholder="t('Optional system instructions...')"
              class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-950/60 dark:text-gray-200"
            />
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Output') }}</label>
              <button
                @click="copyLeftText"
                :class="copiedLeft ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-primary-700 hover:text-primary-800 dark:text-primary-400'"
                class="text-xs font-semibold transition"
              >
                {{ copiedLeft ? t('Copied!') : t('Copy') }}
              </button>
            </div>
            <textarea
              :value="playgroundState.leftPanel.output"
              readonly
              rows="12"
              class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-mono text-gray-700 dark:border-surface-700 dark:bg-surface-950/60 dark:text-gray-200"
              :class="{ 'animate-pulse': playgroundState.leftPanel.streaming }"
              :placeholder="t('Output will appear here...')"
            />
            <div v-if="playgroundState.leftPanel.tokens.input > 0" class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-gray-400">
              <span>{{ t('In: :input · Out: :output', { input: playgroundState.leftPanel.tokens.input, output: playgroundState.leftPanel.tokens.output }) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Right Panel -->
      <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
              <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Provider B') }}</h2>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Use a second model to compare tone, accuracy, and structure.') }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-5 p-5">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <AppSelect
            v-model="playgroundState.rightPanel.provider"
            :options="providerOptions"
            :label="t('Provider')"
            :placeholder="t('Select a provider...')"
            live-search
            :size="8"
          />
          <AppSelect
            v-model="playgroundState.rightPanel.model"
            :options="rightModels"
            :label="t('Model')"
            :placeholder="t('Select a model...')"
            live-search
            :size="8"
          />
        </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Temperature') }}</label>
            <input v-model.number="playgroundState.rightPanel.temperature" type="number" step="0.1" min="0" max="2" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Top P') }}</label>
            <input v-model.number="playgroundState.rightPanel.topP" type="number" step="0.1" min="0" max="1" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Max Tokens') }}</label>
            <input v-model.number="playgroundState.rightPanel.maxTokens" type="number" min="1" max="32000" class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
          </div>
        </div>
          <div v-if="!playgroundState.syncPanels" class="space-y-2">
            <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('System prompt') }}</label>
            <textarea
              v-model="playgroundState.rightPanel.systemPrompt"
              rows="2"
              :placeholder="t('Optional system instructions...')"
              class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-950/60 dark:text-gray-200"
            />
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ t('Output') }}</label>
              <button
                @click="copyRightText"
                :class="copiedRight ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-primary-700 hover:text-primary-800 dark:text-primary-400'"
                class="text-xs font-semibold transition"
              >
                {{ copiedRight ? t('Copied!') : t('Copy') }}
              </button>
            </div>
            <textarea
              :value="playgroundState.rightPanel.output"
              readonly
              rows="12"
              class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-mono text-gray-700 dark:border-surface-700 dark:bg-surface-950/60 dark:text-gray-200"
              :class="{ 'animate-pulse': playgroundState.rightPanel.streaming }"
              :placeholder="t('Output will appear here...')"
            />
            <div v-if="playgroundState.rightPanel.tokens.input > 0" class="flex flex-wrap items-center justify-between gap-2 text-[10px] text-gray-400">
              <span>{{ t('In: :input · Out: :output', { input: playgroundState.rightPanel.tokens.input, output: playgroundState.rightPanel.tokens.output }) }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Playground Controller -->
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="border-b border-gray-100 px-5 py-4 dark:border-surface-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
          <i class="ti ti-adjustments-horizontal text-primary-500"></i>
          {{ t('Playground Controller') }}
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ t('Write a single prompt here to execute and compare results across both panels simultaneously.') }}
        </p>
      </div>

      <div class="space-y-5 p-5">
        <!-- Prompt Box -->
        <div class="space-y-1.5">
          <label class="block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
            {{ t('Shared User Prompt') }}
          </label>
          <textarea
            v-model="playgroundState.sharedMessage"
            rows="4"
            :placeholder="t('Enter the test prompt or message here... (e.g. Write a tagline for an AI writing assistant)')"
            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-950/60 dark:text-gray-200 focus:border-primary-400 focus:ring focus:ring-primary-100 focus:outline-none dark:focus:ring-primary-950/50"
          />
        </div>

        <!-- Controls Toolbar -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-t border-gray-100 pt-4 dark:border-surface-800">
          <!-- Toggle sync prompts -->
          <div class="flex items-center gap-4">
            <label class="relative flex cursor-pointer items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-400">
              <input v-model="playgroundState.syncPanels" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-surface-700 dark:bg-surface-800" />
              {{ t('Sync System Prompts') }}
            </label>
          </div>

          <!-- Buttons Actions -->
          <div class="flex flex-wrap items-center gap-2.5">
            <!-- Share Snap Shot -->
            <button
              @click="handleShare"
              :disabled="!playgroundState.leftPanel.output && !playgroundState.rightPanel.output"
              :class="copiedShare ? 'border-green-500/30 bg-green-50/50 text-green-700 dark:border-green-500/20 dark:bg-green-950/20 dark:text-green-400' : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300 hover:text-primary-700 disabled:opacity-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300'"
              class="inline-flex h-11 items-center gap-1.5 rounded-full border px-5 text-sm font-semibold transition"
            >
              <i :class="copiedShare ? 'ti ti-check' : 'ti ti-share'" class="text-base"></i>
              <span>{{ copiedShare ? t('Link Copied!') : t('Share Snapshot') }}</span>
            </button>

            <!-- Run Both Models -->
            <button
              @click="runBoth"
              :disabled="!playgroundState.sharedMessage || playgroundState.leftPanel.streaming || playgroundState.rightPanel.streaming"
              class="inline-flex h-11 items-center gap-1.5 btn-primary disabled:opacity-50"
            >
              <i v-if="playgroundState.leftPanel.streaming || playgroundState.rightPanel.streaming" class="ti ti-loader animate-spin text-base"></i>
              <i v-else class="ti ti-player-play text-base"></i>
              <span>{{ playgroundState.leftPanel.streaming || playgroundState.rightPanel.streaming ? t('Running...') : t('Run Comparison') }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- History -->
      <details v-if="playgroundState.history.length > 0" class="border-t border-gray-100 px-5 py-4 dark:border-surface-800">
        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
          {{ t('History (:count)', { count: playgroundState.history.length }) }}
        </summary>
        <div class="mt-4 max-h-56 space-y-1.5 overflow-y-auto">
          <div
            v-for="item in playgroundState.history"
            :key="item.id"
            class="flex items-center justify-between gap-4 rounded-xl border border-transparent bg-gray-50/50 p-3 text-xs transition hover:border-gray-200 hover:bg-gray-50 dark:bg-surface-900/30 dark:hover:border-surface-800 dark:hover:bg-surface-900/60"
          >
            <!-- Left Info Zone: Click to load -->
            <div
              class="flex-1 min-w-0 cursor-pointer"
              @click="loadHistoryItem(item)"
            >
              <div class="font-semibold text-gray-800 dark:text-gray-200 truncate">
                {{ item.prompt }}
              </div>
              <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-gray-400">
                <span>{{ new Date(item.createdAt).toLocaleTimeString() }}</span>
                <span>·</span>
                <span class="text-[9px] tracking-leading text-gray-500 font-medium">
                  {{ item.paramsLeft?.model || 'Model A' }} vs {{ item.paramsRight?.model || 'Model B' }}
                </span>
              </div>
            </div>

            <!-- Right Actions Zone: Share & View -->
            <div class="flex items-center gap-2 shrink-0">
              <!-- Loading Share Snapshot -->
              <span v-if="sharingItemId === item.id" class="text-[10px] text-gray-400 font-medium animate-pulse">
                {{ t('Sharing...') }}
              </span>

              <!-- Share Snapshot CTA -->
              <button
                v-else-if="!item.shareUrl"
                type="button"
                @click="shareHistoryItem(item)"
                class="inline-flex h-7 px-2.5 items-center gap-1 rounded-full border border-gray-200 bg-white text-[10px] font-semibold text-gray-600 shadow-sm transition hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:border-primary-700 dark:hover:text-primary-400"
              >
                <i class="ti ti-share text-xs"></i>
                {{ t('Share') }}
              </button>

              <!-- Shared Snapshot Actions -->
              <template v-else>
                <!-- Copy link -->
                <button
                  type="button"
                  @click="copyHistoryShareLink(item.shareUrl, item.id)"
                  :class="copiedHistoryShareId === item.id ? 'border-green-500/20 bg-green-50/50 text-green-700 dark:text-green-400' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300'"
                  class="inline-flex h-7 px-2.5 items-center gap-1 rounded-full border text-[10px] font-semibold shadow-sm transition"
                >
                  <i :class="copiedHistoryShareId === item.id ? 'ti ti-check text-green-600 dark:text-green-400' : 'ti ti-copy'" class="text-xs"></i>
                  {{ copiedHistoryShareId === item.id ? t('Copied!') : t('Copy Link') }}
                </button>

                <!-- View snapshot link -->
                <a
                  :href="item.shareUrl"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex h-7 px-2.5 items-center gap-1 rounded-full border border-gray-200 bg-white text-[10px] font-semibold text-gray-600 shadow-sm transition hover:border-primary-300 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:border-primary-700 dark:hover:text-primary-400"
                >
                  <i class="ti ti-external-link text-xs"></i>
                  {{ t('View') }}
                </a>
              </template>
            </div>
          </div>
        </div>
      </details>
    </section>
  </div>
</template>
