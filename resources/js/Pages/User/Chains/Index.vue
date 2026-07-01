<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

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

const chains = computed(() => (page.props.chains as Chain[]) ?? [])
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

const showSuccessAlert = ref(false)
const lastExecutedChainName = ref('')

function run(chain: Chain) {
  router.post(route('user.dashboard.chains.run', chain.ulid), {}, {
    preserveScroll: true,
    onSuccess: () => {
      lastExecutedChainName.value = chain.name
      showSuccessAlert.value = true
    }
  })
}
</script>

<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Chains') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Chain multiple AI tools into automated workflows.') }}</p>
      </div>
      <Link
        :href="route('user.dashboard.chains.create')"
        class="inline-flex items-center justify-center gap-2 rounded-full btn-primary"
      >
        <i class="ti ti-plus text-base"></i>
        {{ t('New Chain') }}
      </Link>
    </div>

    <!-- Success Alert Card -->
    <div v-if="showSuccessAlert" class="mb-6 flex flex-col gap-4 rounded-2xl border border-green-500/20 bg-green-500/10 p-4 text-green-800 dark:border-green-500/30 dark:bg-green-500/5 dark:text-green-300 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-500/20">
          <i class="ti ti-circle-check text-xl text-green-600 dark:text-green-400"></i>
        </div>
        <div>
          <h4 class="font-semibold text-sm">{{ t('Chain execution started!') }}</h4>
          <p class="text-xs text-green-700/80 dark:text-green-300/80 mt-0.5">
            {{ t('Workflow ":name" is now running & may take a while. The output will appear in your generation history.', { name: lastExecutedChainName }) }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2 self-end sm:self-auto">
        <Link
          :href="route('user.dashboard.history.index')"
          class="inline-flex items-center justify-center gap-1.5 rounded-full bg-green-600 px-4 py-2 text-xs font-semibold !text-white shadow-sm transition hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600"
        >
          <i class="ti ti-history"></i>
          {{ t('Go to History') }}
        </Link>
        <button
          type="button"
          @click="showSuccessAlert = false"
          class="inline-flex h-8 w-8 items-center justify-center rounded-full text-green-700 hover:bg-green-500/15 dark:text-green-400"
        >
          <i class="ti ti-x text-base"></i>
        </button>
      </div>
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
              <button type="button" @click="run(chain)" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-green-500 transition hover:bg-green-50 hover:text-green-600 dark:text-green-400 dark:hover:bg-green-900/20 dark:hover:text-green-300">
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
