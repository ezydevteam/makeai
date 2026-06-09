<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()
const { formatDate } = useDateFormat()

interface Embed {
  id: number
  ulid: string
  tool_slug: string
  label: string | null
  token: string
  is_active: boolean
  allowed_origins: string[] | null
  theme: string
  created_at: string
}

interface ToolOption {
  slug: string
  name: string
}

const tools = computed(() => (page.props.tools as ToolOption[]) ?? [])
const toolOptions = computed(() => tools.value.map(t => ({ value: t.slug, label: t.name })))

const embeds = computed(() => (page.props.embeds as Embed[]) ?? [])
const showCreate = ref(false)
const form = ref({ tool_slug: '', label: '', password: '', theme: 'auto' as string, show_branding: true })

const themeOptions = [
  { value: 'auto', label: t('Auto') },
  { value: 'light', label: t('Light') },
  { value: 'dark', label: t('Dark') },
]

const confirmDelete = ref<Embed | null>(null)
const deleting = ref(false)

function create() {
  router.post(route('user.dashboard.embeds.store'), form.value, {
    onSuccess: () => { showCreate.value = false; form.value = { tool_slug: '', label: '', password: '', theme: 'auto', show_branding: true } },
  })
}

function confirmRemove(embed: Embed) {
  confirmDelete.value = embed
}

function doRemove() {
  if (!confirmDelete.value) return
  deleting.value = true
  router.delete(route('user.dashboard.embeds.destroy', confirmDelete.value.ulid), {
    onFinish: () => { deleting.value = false; confirmDelete.value = null },
  })
}

function regenerate(embed: Embed) {
  router.post(route('user.dashboard.embeds.regen', embed.ulid), {}, { preserveScroll: true })
}

function copyToken(token: string) {
  navigator.clipboard.writeText(token)
}

const embedUrl = (token: string) => route('embed.show', token)
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Embeds') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Embed any AI tool on external websites.') }}</p>
      </div>
      <button @click="showCreate = !showCreate" class="btn-primary rounded-xl px-4 py-2 text-sm font-semibold">
        {{ showCreate ? t('Cancel') : t('New Embed') }}
      </button>
    </div>

    <div v-if="showCreate" class="mb-6 max-w-lg rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <div class="space-y-4">
        <div>
          <AppSelect v-model="form.tool_slug" :options="toolOptions" :label="t('Tool')" :placeholder="t('Select a tool...')" live-search :size="8" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Label') }} <span class="text-gray-400 font-normal">({{ t('optional') }})</span></label>
          <input v-model="form.label" :placeholder="t('Display name for this embed')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Password') }} <span class="text-gray-400 font-normal">({{ t('optional') }})</span></label>
          <input v-model="form.password" type="password" :placeholder="t('Protect with a password')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <AppSelect v-model="form.theme" :options="themeOptions" :label="t('Theme')" />
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input v-model="form.show_branding" type="checkbox" class="rounded" />
          {{ t('Show branding') }}
        </label>
        <button @click="create" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
          {{ t('Create embed') }}
        </button>
      </div>
    </div>

    <div v-if="embeds.length === 0" class="rounded-xl border border-gray-200 bg-white py-12 text-center dark:border-gray-800 dark:bg-gray-900">
      <p class="text-gray-500">{{ t('No embeds yet. Create one to share a tool!') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="embed in embeds" :key="embed.ulid" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ embed.label || embed.tool_slug }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ embed.tool_slug }}</p>
          </div>
          <span :class="embed.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="rounded-full px-2 py-0.5 text-[10px] font-semibold">
            {{ embed.is_active ? t('Active') : t('Inactive') }}
          </span>
        </div>

        <div class="mt-3 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
          <code class="flex-1 text-xs text-gray-600 truncate dark:text-gray-400">{{ embed.token }}</code>
          <button @click="copyToken(embed.token)" class="shrink-0 text-xs text-primary-600 hover:text-primary-700">{{ t('Copy') }}</button>
        </div>

        <div class="mt-3 flex items-center gap-2">
          <Link :href="route('embed.show', embed.token)" target="_blank" class="text-xs text-primary-600 hover:underline">{{ t('Preview') }}</Link>
          <button @click="regenerate(embed)" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">{{ t('Regen') }}</button>
          <button @click="confirmRemove(embed)" class="text-xs text-red-500 hover:text-red-700 ml-auto">{{ t('Delete') }}</button>
        </div>

        <p class="mt-2 text-[10px] text-gray-400">{{ t('Created') }}: {{ formatDate(embed.created_at) }}</p>
      </div>
    </div>

    <ActionConfirmModal
      :open="confirmDelete !== null"
      title="Delete embed?"
      :message="confirmDelete ? `Are you sure you want to delete &quot;${confirmDelete.label || confirmDelete.tool_slug}&quot;?` : ''"
      confirm-label="Delete"
      :processing="deleting"
      @cancel="confirmDelete = null"
      @confirm="doRemove"
    />
  </div>
</template>
