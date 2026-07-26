<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()
const { formatDate } = useDateFormat()

interface Chain {
  id: number
  ulid: string
  name: string
  steps: Array<{ step: number; tool_slug: string }>
  run_count: number
  last_run_at: string | null
  created_at: string
}

interface RunStep {
  step: number
  tool_slug: string
  output: string
  tokens: number
  credits: number
}

interface Run {
  ulid: string
  status: 'running' | 'completed' | 'failed'
  input: string | null
  step_outputs: RunStep[] | null
  total_tokens: number
  total_credits: number
  error: string | null
  created_at: string
  chain: { ulid: string; name: string } | null
}

const chains = computed(() => (page.props.chains as Chain[]) ?? [])
const runs = computed(() => (page.props.runs as Run[]) ?? [])

const confirmDelete = ref<Chain | null>(null)
const deleting = ref(false)

function confirmRemove(chain: Chain) {
  confirmDelete.value = chain
}

function doRemove() {
  if (!confirmDelete.value) return
  deleting.value = true
  router.delete(route('user.dashboard.chains.destroy', confirmDelete.value.ulid), {
    onFinish: () => { deleting.value = false; confirmDelete.value = null },
  })
}

// ── Running a chain ──────────────────────────────────────────────
// A chain's first step needs something to work on, so ask for it here; steps map it
// in as {{input}}.
const runTarget = ref<Chain | null>(null)
const runInput = ref('')
const starting = ref(false)

function openRun(chain: Chain) {
  runTarget.value = chain
  runInput.value = ''
}

function startRun() {
  if (!runTarget.value) return
  starting.value = true

  router.post(route('user.dashboard.chains.run', runTarget.value.ulid), { input: runInput.value }, {
    preserveScroll: true,
    onSuccess: () => { runTarget.value = null; runInput.value = '' },
    onFinish: () => { starting.value = false },
  })
}

// ── Run history ──────────────────────────────────────────────────
// Runs finish on the queue, so refresh while any is still in flight. Polling rather
// than Echo: broadcasting is optional on an install, the history is not.
const expanded = ref<string | null>(null)
const hasRunning = computed(() => runs.value.some(run => run.status === 'running'))
let poll: ReturnType<typeof setInterval> | null = null

function stopPolling() {
  if (poll) {
    clearInterval(poll)
    poll = null
  }
}

watch(hasRunning, (running) => {
  if (running && !poll) {
    poll = setInterval(() => router.reload({ only: ['runs', 'chains'] }), 4000)
  } else if (!running) {
    stopPolling()
  }
}, { immediate: true })

onBeforeUnmount(stopPolling)

const statusStyles: Record<Run['status'], string> = {
  running: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300',
  completed: 'border-green-200 bg-green-50 text-green-700 dark:border-green-900/40 dark:bg-green-950/20 dark:text-green-300',
  failed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-300',
}

function copyOutput(text: string) {
  navigator.clipboard.writeText(text)
}
</script>

<template>
  <div>
    <Head :title="t('Tool Chains')" />
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Chains') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Chain multiple AI tools into automated workflows.') }}</p>
      </div>
      <Link
        :href="route('user.dashboard.chains.create')"
        class="shrink-0 inline-flex items-center justify-center gap-2 rounded-full btn-primary"
      >
        <i class="ti ti-plus text-base"></i>
        {{ t('New Chain') }}
      </Link>
    </div>

    <div v-if="chains.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900/50">
      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/30">
        <i class="ti ti-git-branch text-2xl text-primary-600 dark:text-primary-400"></i>
      </div>
      <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('No chains yet') }}</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Build your first workflow to connect tools in sequence.') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="chain in chains" :key="chain.ulid" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-1 hover:border-primary-300 dark:border-surface-800 dark:bg-gray-900 dark:hover:border-primary-700">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <Link :href="route('user.dashboard.chains.show', chain.ulid)" class="block truncate font-semibold text-gray-900 transition hover:text-primary-600 dark:text-white">{{ chain.name }}</Link>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ chain.steps.length }} {{ t('steps') }}<span v-if="chain.run_count"> · {{ chain.run_count }} {{ t('runs') }}</span></p>
          </div>
          <div class="flex items-center gap-1">
            <Tooltip :content="t('Run chain')" placement="top">
              <button type="button" @click="openRun(chain)" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-green-500 transition hover:bg-green-50 hover:text-green-600 dark:text-green-400 dark:hover:bg-green-900/20 dark:hover:text-green-300">
                <i class="ti ti-player-play text-base"></i>
              </button>
            </Tooltip>
            <Tooltip :content="t('Delete chain')" placement="top">
              <button type="button" @click="confirmRemove(chain)" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-red-500 transition hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-300">
                <i class="ti ti-trash text-base"></i>
              </button>
            </Tooltip>
          </div>
        </div>
        <div v-if="chain.steps.length" class="mt-4 flex items-center flex-wrap gap-y-2 gap-x-1.5">
          <template v-for="(step, idx) in chain.steps" :key="step.step">
            <span class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[10px] font-medium text-gray-700 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-300">
              <i class="ti ti-wand text-primary-500"></i>
              {{ step.tool_slug }}
            </span>
            <i v-if="idx < chain.steps.length - 1" class="ti ti-chevron-right text-xs text-gray-400 dark:text-gray-600"></i>
          </template>
        </div>
        <p v-if="chain.last_run_at" class="mt-4 text-xs text-gray-400">{{ t('Last run') }}: {{ formatDate(chain.last_run_at) }}</p>
      </div>
    </div>

    <!-- Recent runs -->
    <section v-if="runs.length" class="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-surface-800">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Recent runs') }}</h2>
        <span v-if="hasRunning" class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
          <i class="ti ti-loader animate-spin"></i>
          {{ t('Running...') }}
        </span>
      </div>

      <div class="divide-y divide-gray-100 dark:divide-surface-800">
        <div v-for="run in runs" :key="run.ulid" class="px-5 py-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ run.chain?.name ?? t('Deleted chain') }}</span>
                <span :class="statusStyles[run.status]" class="inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                  {{ run.status === 'running' ? t('Running') : run.status === 'completed' ? t('Completed') : t('Failed') }}
                </span>
              </div>
              <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                <span>{{ formatDate(run.created_at) }}</span>
                <span v-if="run.total_tokens"> · {{ t(':n tokens', { n: run.total_tokens }) }}</span>
                <span v-if="run.total_credits"> · {{ t(':n credits', { n: run.total_credits }) }}</span>
              </p>
            </div>

            <button
              v-if="run.step_outputs?.length"
              type="button"
              @click="expanded = expanded === run.ulid ? null : run.ulid"
              class="shrink-0 text-xs font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
              {{ expanded === run.ulid ? t('Hide output') : t('View output') }}
            </button>
          </div>

          <p v-if="run.error" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950/20 dark:text-red-300">
            {{ run.error }}
          </p>

          <div v-if="expanded === run.ulid" class="mt-3 space-y-3">
            <div v-if="run.input" class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2 dark:border-surface-800 dark:bg-surface-950/40">
              <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400">{{ t('Chain input') }}</p>
              <p class="mt-1 whitespace-pre-wrap text-xs text-gray-700 dark:text-gray-300">{{ run.input }}</p>
            </div>

            <div
              v-for="step in run.step_outputs"
              :key="step.step"
              class="rounded-xl border border-gray-100 bg-gray-50/60 px-3 py-2.5 dark:border-surface-800 dark:bg-surface-950/40"
            >
              <div class="flex items-center justify-between gap-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-400">
                  {{ t('Step :n', { n: step.step }) }} · {{ step.tool_slug }}
                </p>
                <button type="button" @click="copyOutput(step.output)" class="text-[10px] font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400">
                  {{ t('Copy') }}
                </button>
              </div>
              <p class="mt-1.5 max-h-56 overflow-y-auto whitespace-pre-wrap text-xs leading-5 text-gray-700 dark:text-gray-300">{{ step.output }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Run chain -->
    <div v-if="runTarget" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/40 p-4" @click.self="runTarget = null">
      <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-surface-800 dark:bg-gray-900">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Run :name', { name: runTarget.name }) }}</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('This text is passed to the steps that map the chain input.') }}</p>

        <textarea
          v-model="runInput"
          rows="6"
          :placeholder="t('What should this chain work on?')"
          class="mt-4 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-950/60 dark:text-gray-200"
        />

        <div class="mt-5 flex justify-end gap-2">
          <button type="button" @click="runTarget = null" class="rounded-full border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-surface-700 dark:text-gray-200">
            {{ t('Cancel') }}
          </button>
          <button type="button" @click="startRun" :disabled="starting" class="btn-primary rounded-full px-5 py-2 text-sm font-semibold disabled:opacity-50">
            {{ starting ? t('Starting...') : t('Run chain') }}
          </button>
        </div>
      </div>
    </div>

    <ActionConfirmModal
      :open="confirmDelete !== null"
      :title="t('Delete chain?')"
      :message="confirmDelete ? t('Are you sure you want to delete :name?', { name: confirmDelete.name }) : ''"
      :confirm-label="t('Delete')"
      :processing="deleting"
      @cancel="confirmDelete = null"
      @confirm="doRemove"
    />
  </div>
</template>
