<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

interface Snapshot {
  prompt: string
  output_left: string
  output_right: string
  params_left: { provider: string; model: string; temperature: number }
  params_right: { provider: string; model: string; temperature: number }
  created_at: string
}

const props = defineProps<{ snapshot: Snapshot }>()
const { t } = useTranslate()

const copiedPrompt = ref(false)
const copiedLeft = ref(false)
const copiedRight = ref(false)

const copyPrompt = () => {
  navigator.clipboard.writeText(props.snapshot.prompt)
  copiedPrompt.value = true
  setTimeout(() => {
    copiedPrompt.value = false
  }, 2000)
}

const copyLeft = () => {
  navigator.clipboard.writeText(props.snapshot.output_left)
  copiedLeft.value = true
  setTimeout(() => {
    copiedLeft.value = false
  }, 2000)
}

const copyRight = () => {
  navigator.clipboard.writeText(props.snapshot.output_right)
  copiedRight.value = true
  setTimeout(() => {
    copiedRight.value = false
  }, 2000)
}

const formatProviderName = (name: string) => {
  if (!name) return ''
  return name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()
}
</script>

<template>
  <Head :title="t('Shared Playground Snapshot')" />

  <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <!-- Header Block -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-primary-600 dark:text-primary-400 uppercase tracking-wider mb-1.5">
          <i class="ti ti-share-spark text-sm"></i>
          <span>{{ t('AI Playground Snapshot') }}</span>
        </div>
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
          {{ t('Compare Models') }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ t('Snapshot captured on') }} {{ new Date(snapshot.created_at).toLocaleString() }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Link
          :href="route('user.dashboard.playground.index')"
          class="inline-flex items-center justify-center gap-2 rounded-full btn-primary"
        >
          <i class="ti ti-player-play text-base"></i>
          {{ t('Try AI Playground') }}
        </Link>
      </div>
    </div>

    <div class="grid gap-8">
      <!-- Prompt Container -->
      <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900/50">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-surface-800">
          <div class="flex items-center gap-2">
            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400">
              <i class="ti ti-message-dots text-base"></i>
            </div>
            <h3 class="font-bold text-gray-900 dark:text-white">{{ t('Prompt') }}</h3>
          </div>
          <button
            @click="copyPrompt"
            :class="copiedPrompt ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-500 hover:text-primary-700 dark:text-gray-400 dark:hover:text-primary-400'"
            class="inline-flex items-center gap-1.5 text-xs transition"
          >
            <i :class="copiedPrompt ? 'ti ti-check' : 'ti ti-copy'" class="text-sm"></i>
            <span>{{ copiedPrompt ? t('Copied!') : t('Copy') }}</span>
          </button>
        </div>
        <div class="mt-4 prose dark:prose-invert max-w-none">
          <div class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-sans">{{ snapshot.prompt }}</div>
        </div>
      </section>

      <!-- Side-by-Side Comparison Panels -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Left Panel -->
        <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
          <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-surface-800 dark:bg-surface-900/30">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                  <i class="ti ti-cpu text-xs"></i>
                  {{ formatProviderName(snapshot.params_left.provider) }}
                </span>
                <h3 class="mt-2 font-bold text-gray-900 dark:text-white truncate">
                  {{ snapshot.params_left.model }}
                </h3>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-medium text-gray-500 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-400">
                  {{ t('Temp') }}: {{ snapshot.params_left.temperature }}
                </span>
                <button
                  @click="copyLeft"
                  :disabled="!snapshot.output_left"
                  :class="copiedLeft ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-500 hover:text-primary-700 dark:text-gray-400 dark:hover:text-primary-400'"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm transition hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-surface-800"
                >
                  <i :class="copiedLeft ? 'ti ti-check text-green-500' : 'ti ti-copy'" class="text-sm"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="p-5">
            <div class="relative">
              <textarea
                :value="snapshot.output_left || t('No output generated')"
                readonly
                rows="16"
                class="w-full rounded-xl border border-gray-100 bg-gray-50/50 px-4 py-3 text-sm font-mono text-gray-700 dark:border-surface-850 dark:bg-surface-950/40 dark:text-gray-200 leading-relaxed resize-none focus:outline-none focus:ring-0"
              />
            </div>
          </div>
        </article>

        <!-- Right Panel -->
        <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
          <div class="border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-surface-800 dark:bg-surface-900/30">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                  <i class="ti ti-cpu text-xs"></i>
                  {{ formatProviderName(snapshot.params_right.provider) }}
                </span>
                <h3 class="mt-2 font-bold text-gray-900 dark:text-white truncate">
                  {{ snapshot.params_right.model }}
                </h3>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-medium text-gray-500 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-400">
                  {{ t('Temp') }}: {{ snapshot.params_right.temperature }}
                </span>
                <button
                  @click="copyRight"
                  :disabled="!snapshot.output_right"
                  :class="copiedRight ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-500 hover:text-primary-700 dark:text-gray-400 dark:hover:text-primary-400'"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm transition hover:bg-gray-50 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-surface-800"
                >
                  <i :class="copiedRight ? 'ti ti-check text-green-500' : 'ti ti-copy'" class="text-sm"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="p-5">
            <div class="relative">
              <textarea
                :value="snapshot.output_right || t('No output generated')"
                readonly
                rows="16"
                class="w-full rounded-xl border border-gray-100 bg-gray-50/50 px-4 py-3 text-sm font-mono text-gray-700 dark:border-surface-850 dark:bg-surface-950/40 dark:text-gray-200 leading-relaxed resize-none focus:outline-none focus:ring-0"
              />
            </div>
          </div>
        </article>
      </div>
    </div>
  </div>
</template>
