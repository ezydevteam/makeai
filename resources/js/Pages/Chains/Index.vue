<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
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

function run(chain: Chain) {
  router.post(route('user.dashboard.chains.run', chain.ulid), {}, { preserveScroll: true })
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Chains') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Chain multiple AI tools into automated workflows.') }}</p>
      </div>
      <Link :href="route('user.dashboard.chains.create')" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold">
        {{ t('New Chain') }}
      </Link>
    </div>

    <div v-if="chains.length === 0" class="rounded-xl border border-gray-200 bg-white py-12 text-center dark:border-gray-800 dark:bg-gray-900">
      <p class="text-gray-500">{{ t('No chains yet. Build your first workflow!') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="chain in chains" :key="chain.ulid" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-start justify-between">
          <Link :href="route('user.dashboard.chains.show', chain.ulid)" class="font-semibold text-gray-900 hover:text-primary-600 dark:text-white">{{ chain.name }}</Link>
          <div class="flex items-center gap-1">
            <button @click="run(chain)" title="Run" class="rounded p-1 text-gray-400 hover:text-green-600">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
            </button>
            <button @click="confirmRemove(chain)" title="Delete" class="rounded p-1 text-gray-400 hover:text-red-500">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            </button>
          </div>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          {{ chain.steps.length }} {{ t('steps') }}
          <span v-if="chain.run_count"> · {{ chain.run_count }} {{ t('runs') }}</span>
        </p>
        <div v-if="chain.steps.length" class="mt-3 flex flex-wrap gap-1">
          <span v-for="step in chain.steps" :key="step.step" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
            {{ step.step }}. {{ step.tool_slug }}
          </span>
        </div>
        <p v-if="chain.last_run_at" class="mt-2 text-[10px] text-gray-400">{{ t('Last run') }}: {{ formatDate(chain.last_run_at) }}</p>
      </div>
    </div>

    <ActionConfirmModal
      :open="confirmDelete !== null"
      title="Delete chain?"
      :message="confirmDelete ? `Are you sure you want to delete &quot;${confirmDelete.name}&quot;?` : ''"
      confirm-label="Delete"
      :processing="deleting"
      @cancel="confirmDelete = null"
      @confirm="doRemove"
    />
  </div>
</template>
