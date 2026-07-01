<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
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
const creating = ref(false)
const form = ref({ tool_slug: '', label: '', password: '', theme: 'auto' as string, show_branding: true })
const copiedToken = ref<string | null>(null)
const regeneratedToken = ref<string | null>(null)
let copyResetTimer: ReturnType<typeof setTimeout> | null = null
let regenResetTimer: ReturnType<typeof setTimeout> | null = null

const themeOptions = [
  { value: 'auto', label: t('Auto') },
  { value: 'light', label: t('Light') },
  { value: 'dark', label: t('Dark') },
]

const confirmDelete = ref<Embed | null>(null)
const deleting = ref(false)
const deleteMessage = computed(() => {
  if (!confirmDelete.value) return ''

  return t('Are you sure you want to delete :name?', {
    name: confirmDelete.value.label || confirmDelete.value.tool_slug,
  })
})

function create() {
  creating.value = true
  router.post(route('user.dashboard.embeds.store'), form.value, {
    onSuccess: () => { showCreate.value = false; form.value = { tool_slug: '', label: '', password: '', theme: 'auto', show_branding: true } },
    onFinish: () => { creating.value = false },
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
  router.post(route('user.dashboard.embeds.regen', embed.ulid), {}, {
    preserveScroll: true,
    onSuccess: () => {
      regeneratedToken.value = embed.token

      if (regenResetTimer) {
        clearTimeout(regenResetTimer)
      }

      regenResetTimer = setTimeout(() => {
        if (regeneratedToken.value === embed.token) {
          regeneratedToken.value = null
        }
      }, 1500)
    },
  })
}

function copyToken(token: string) {
  navigator.clipboard.writeText(token).then(() => {
    copiedToken.value = token

    if (copyResetTimer) {
      clearTimeout(copyResetTimer)
    }

    copyResetTimer = setTimeout(() => {
      if (copiedToken.value === token) {
        copiedToken.value = null
      }
    }, 1500)
  })
}

const embedUrl = (token: string) => route('embed.show', token)

onBeforeUnmount(() => {
  if (copyResetTimer) {
    clearTimeout(copyResetTimer)
  }

  if (regenResetTimer) {
    clearTimeout(regenResetTimer)
  }
})
</script>

<template>
  <div>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Embeds') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Embed any AI tool on external websites.') }}</p>
      </div>
      <button
        type="button"
        @click="showCreate = !showCreate"
        :class="showCreate ? 'border border-red-500 bg-red-500 text-white hover:bg-red-400' : 'bg-primary-500 text-white'"
        class="inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold transition"
      >
        <i :class="showCreate ? 'ti ti-x mr-2' : 'ti ti-plus mr-2'"></i>
        {{ showCreate ? t('Close') : t('New Embed') }}
      </button>
    </div>

    <div v-if="showCreate" class="mb-6 w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <AppSelect v-model="form.tool_slug" :options="toolOptions" :label="t('Tool')" :placeholder="t('Select a tool...')" live-search :size="8" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Label') }} <span class="text-gray-400 font-normal">({{ t('optional') }})</span></label>
          <input v-model="form.label" :placeholder="t('Display name for this embed')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Password') }} <span class="text-gray-400 font-normal">({{ t('optional') }})</span></label>
          <input v-model="form.password" type="password" autocomplete="new-password" :placeholder="t('Protect with a password')" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        </div>
        <div>
          <AppSelect v-model="form.theme" :options="themeOptions" :label="t('Theme')" />
        </div>
        <label class="flex items-center gap-2 rounded-lg border border-dashed border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
          <input v-model="form.show_branding" type="checkbox" class="rounded" />
          {{ t('Show branding') }}
        </label>
        <div class="md:col-span-2 flex justify-end">
          <button @click="create" :disabled="creating" class="inline-flex items-center justify-center rounded-full bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600 disabled:opacity-60">
            {{ creating ? t('Creating...') : t('Create embed') }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="embeds.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900/50">
      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-950/30">
        <i class="ti ti-code text-2xl text-primary-600 dark:text-primary-400"></i>
      </div>
      <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('No embeds yet') }}</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create one to share a tool on your site.') }}</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div v-for="embed in embeds" :key="embed.ulid" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-1 hover:border-primary-300 dark:border-surface-800 dark:bg-gray-900 dark:hover:border-primary-700">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ embed.label || embed.tool_slug }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ embed.tool_slug }}</p>
          </div>
          <span :class="embed.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'" class="rounded-full px-2 py-0.5 text-[10px] font-semibold">
            {{ embed.is_active ? t('Active') : t('Inactive') }}
          </span>
        </div>

        <div class="mt-3 flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-gray-800">
          <code class="flex-1 text-xs text-gray-600 truncate dark:text-gray-400">{{ embed.token }}</code>
          <Tooltip :content="copiedToken === embed.token ? t('Copied') : t('Copy token')" placement="top">
            <button
              type="button"
              @click="copyToken(embed.token)"
              :class="copiedToken === embed.token ? 'text-green-700 dark:text-green-300' : 'text-gray-600 hover:text-primary-700 dark:text-gray-300 dark:hover:text-primary-300'"
              class="shrink-0 transition"
            >
              <i :class="copiedToken === embed.token ? 'ti ti-check text-base' : 'ti ti-copy text-base'"></i>
            </button>
          </Tooltip>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
          <p class="text-xs text-gray-400">{{ t('Created') }}: {{ formatDate(embed.created_at) }}</p>

          <div class="flex items-center gap-1.5">
            <Tooltip :content="t('Preview')" placement="top">
              <Link
                :href="route('embed.show', embed.token)"
                target="_blank"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full !text-amber-500 transition hover:bg-amber-100 hover:text-amber-500 dark:text-amber-500 dark:hover:bg-slate-800 dark:hover:text-amber-400"
              >
                <i class="ti ti-eye text-base"></i>
              </Link>
            </Tooltip>
            <Tooltip :content="t('Re-generate token')" placement="top">
              <button
                type="button"
                @click="regenerate(embed)"
                :class="regeneratedToken === embed.token ? 'text-green-700 dark:text-green-300' : 'text-green-500 hover:bg-green-100 hover:text-green-500 dark:text-green-500 dark:hover:bg-slate-800 dark:hover:text-green-400'"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full transition"
              >
                <i :class="regeneratedToken === embed.token ? 'ti ti-check text-base' : 'ti ti-refresh text-base'"></i>
              </button>
            </Tooltip>
            <Tooltip :content="t('Delete')" placement="top">
              <button
                type="button"
                @click="confirmRemove(embed)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-red-500 transition hover:bg-red-100 hover:text-red-500 dark:text-red-500 dark:hover:bg-slate-800 dark:hover:text-red-400"
              >
                <i class="ti ti-trash text-base"></i>
              </button>
            </Tooltip>
          </div>
        </div>
      </div>
    </div>

    <ActionConfirmModal
      :open="confirmDelete !== null"
      :title="t('Delete embed?')"
      :message="deleteMessage"
      :confirm-label="t('Delete')"
      :processing="deleting"
      @cancel="confirmDelete = null"
      @confirm="doRemove"
    />
  </div>
</template>
