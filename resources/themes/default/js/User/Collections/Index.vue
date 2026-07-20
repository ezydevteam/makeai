<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()

interface Collection {
  id: number
  ulid: string
  name: string
  description: string | null
  icon: string | null
  color: string | null
  tool_count: number
  tools_count?: number
  created_at: string
}

const collections = computed(() => (page.props.collections as Collection[]) ?? [])
const showCreate = ref(false)
const form = ref({ name: '', description: '', icon: '', color: '' })
const confirmDelete = ref<Collection | null>(null)
const deleting = ref(false)

function create() {
  router.post(route('user.dashboard.collections.store'), form.value, {
    onSuccess: () => { showCreate.value = false; form.value = { name: '', description: '', icon: '', color: '' } },
  })
}

function remove(col: Collection) {
  confirmDelete.value = col
}

function confirmRemove() {
  if (!confirmDelete.value) return
  deleting.value = true
  router.delete(route('user.dashboard.collections.destroy', confirmDelete.value.ulid), {
    onFinish: () => { deleting.value = false; confirmDelete.value = null },
  })
}
</script>

<template>
  <div class="space-y-6">
    <Head :title="t('My Collections')" />

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('My Collections') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Organize your favorite tools into curated collections.') }}</p>
      </div>
      <button @click="showCreate = !showCreate" :class="showCreate ? 'btn-danger' : 'btn-primary'" class="shrink-0">
        <i :class="showCreate ? 'ti ti-x text-base' : 'ti ti-plus text-base'" class="transition-all"></i>
        {{ showCreate ? t('Cancel') : t('New Collection') }}
      </button>
    </div>

    <div v-if="showCreate" class="mb-6 w-full rounded-2xl border border-gray-200 bg-white p-4 dark:border-surface-800 dark:bg-gray-900">
      <div class="space-y-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Collection name') }}</label>
          <input v-model="form.name" :placeholder="t('e.g. Writing assistants')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-surface-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }} <span class="font-normal text-gray-400">({{ t('optional') }})</span></label>
          <input v-model="form.description" :placeholder="t('Brief description')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-surface-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <button @click="create" class="btn-primary">
          {{ t('Create collection') }}
        </button>
      </div>
    </div>

    <div v-if="collections.length === 0" class="rounded-2xl border border-gray-200 bg-white py-12 text-center dark:border-surface-800 dark:bg-gray-900">
      <p class="text-gray-500">{{ t('No collections yet. Group your favorite tools together!') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Link
        v-for="col in collections"
        :key="col.ulid"
        :href="route('user.dashboard.collections.show', col.ulid)"
        class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-gray-900"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100/80 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400">
              <i v-if="col.icon" :class="col.icon" class="text-2xl"></i>
              <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
            </span>
            <div class="min-w-0">
              <h3 class="truncate font-semibold text-gray-900 dark:text-white">{{ col.name }}</h3>
              <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                {{ t(':count tools', { count: Number(col.tools_count ?? col.tool_count ?? 0) }) }}
              </p>
            </div>
          </div>
          <Tooltip :content="t('Delete')">
            <button
              @click.prevent="remove(col)"
              class="inline-flex h-8 w-8 items-center justify-center rounded-full text-red-500 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-100 dark:hover:text-red-700"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
            </button>
          </Tooltip>
        </div>
        <p class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ col.description || t('No description') }}</p>
      </Link>
    </div>

    <ActionConfirmModal
      :open="confirmDelete !== null"
      title="Delete collection?"
      :message="confirmDelete ? `Are you sure you want to delete &quot;${confirmDelete.name}&quot;?` : ''"
      confirm-label="Delete"
      :processing="deleting"
      @cancel="confirmDelete = null"
      @confirm="confirmRemove"
    />
  </div>
</template>
