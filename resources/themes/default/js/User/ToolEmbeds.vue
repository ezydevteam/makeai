<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'

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

// Drives allowed_origins, which is what the frame-ancestors CSP and the origin check
// are built from. With no input for it, every embed was framable from any site.
const originsInput = ref('')
const allowedOrigins = computed(() =>
  originsInput.value.split(',').map(origin => origin.trim()).filter(Boolean)
)
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
  router.post(route('user.dashboard.embeds.store'), { ...form.value, allowed_origins: allowedOrigins.value }, {
    onSuccess: () => {
      showCreate.value = false
      form.value = { tool_slug: '', label: '', password: '', theme: 'auto', show_branding: true }
      originsInput.value = ''
    },
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

// Preview modal. An inactive embed is deliberately 404'd by the public endpoint, so it is
// never framed — the modal explains the state instead of showing the error page.
const previewEmbed = ref<Embed | null>(null)

function openPreview(embed: Embed) {
  previewEmbed.value = embed
  document.addEventListener('keydown', closePreviewOnEscape)
}

function closePreview() {
  previewEmbed.value = null
  document.removeEventListener('keydown', closePreviewOnEscape)
}

function closePreviewOnEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closePreview()
  }
}

onBeforeUnmount(() => {
  document.removeEventListener('keydown', closePreviewOnEscape)

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
    <Head :title="t('Tool Embeds')" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Tool Embeds') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Embed any AI tool on external websites.') }}</p>
      </div>
      <button
        type="button"
        @click="showCreate = !showCreate"
        :class="showCreate ? 'btn-danger' : 'btn-primary'"
        class="shrink-0 inline-flex items-center justify-center gap-2"
      >
        <i :class="showCreate ? 'ti ti-x' : 'ti ti-plus'"></i>
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
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800/30">
          <AppSwitch v-model="form.show_branding" :label="t('Show branding')" class="w-full justify-between" />
        </div>
        <div class="md:col-span-2">
          <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ t('Allowed domains') }} <span class="text-gray-400 font-normal">({{ t('optional') }})</span>
          </label>
          <input
            v-model="originsInput"
            :placeholder="t('example.com, app.example.com')"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ t('Comma-separated. Only these sites may embed the tool. Leave empty to allow any site.') }}
          </p>
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button @click="create" :disabled="creating" class="btn-primary disabled:opacity-60">
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
              <button
                type="button"
                @click="openPreview(embed)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full !text-amber-500 transition hover:bg-amber-100 hover:text-amber-500 dark:text-amber-500 dark:hover:bg-slate-800 dark:hover:text-amber-400"
              >
                <i class="ti ti-eye text-base"></i>
              </button>
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

    <Teleport to="body">
      <div
        v-if="previewEmbed"
        class="fixed inset-0 z-[120] flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
        @click.self="closePreview"
      >
        <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-surface-800 dark:bg-gray-900">
          <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-surface-800">
            <div class="min-w-0">
              <h3 class="truncate font-semibold text-gray-900 dark:text-white">
                {{ previewEmbed.label || previewEmbed.tool_slug }}
              </h3>
              <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ previewEmbed.tool_slug }}</p>
            </div>
            <button
              type="button"
              @click="closePreview"
              :aria-label="t('Close')"
              class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-surface-800 dark:hover:text-gray-200"
            >
              <i class="ti ti-x text-lg"></i>
            </button>
          </div>

          <!-- A paused embed returns 404 to visitors by design, so there is nothing to
               frame — say so instead of putting an error page inside the modal. -->
          <div v-if="!previewEmbed.is_active" class="px-6 py-12 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-950/30">
              <i class="ti ti-player-pause text-2xl text-amber-500"></i>
            </div>
            <h4 class="text-base font-bold text-gray-900 dark:text-white">{{ t('This embed is paused') }}</h4>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
              {{ t('Paused embeds are not served to visitors, so there is nothing to preview. Activate it to make it live again.') }}
            </p>
          </div>

          <div v-else class="min-h-0 flex-1 bg-gray-50 dark:bg-surface-950">
            <iframe
              :src="embedUrl(previewEmbed.token)"
              :title="previewEmbed.label || previewEmbed.tool_slug"
              class="h-[60vh] w-full border-0"
            ></iframe>
          </div>

          <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-5 py-3 dark:border-surface-800">
            <p class="truncate text-xs text-gray-400">{{ embedUrl(previewEmbed.token) }}</p>
            <a
              v-if="previewEmbed.is_active"
              :href="embedUrl(previewEmbed.token)"
              target="_blank"
              rel="noopener"
              class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-primary-600 transition hover:text-primary-700 dark:text-primary-400"
            >
              <i class="ti ti-external-link"></i>
              {{ t('Open in new tab') }}
            </a>
          </div>
        </div>
      </div>
    </Teleport>

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
