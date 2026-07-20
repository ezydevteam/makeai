<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import AppLayout from '@themes/default/js/Layouts/AppLayout.vue'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const { t } = useTranslate()
const page = usePage()
const appName = (page.props.app as any)?.name || 'MakeAI'
const audioRef = ref<HTMLAudioElement | null>(null)

defineProps<{
  episode: {
    title: string
    file_url: string | null
    duration_seconds: number | null
    duration_label: string
    waveform_url: string | null
  }
}>()
</script>

<template>
  <Head :title="episode.title" />

  <div class="mx-auto max-w-3xl px-6 py-16">
    <div class="rounded-2xl border border-gray-200 bg-white p-8 text-center dark:border-surface-800 dark:bg-gray-900">
      <i class="ti ti-headphones text-5xl text-primary-500"></i>

      <h1 class="mt-4 text-2xl font-bold text-gray-900 dark:text-white">{{ episode.title }}</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ episode.duration_label }}</p>

      <div class="mt-8">
        <div v-if="episode.waveform_url" class="mb-4 overflow-hidden rounded-lg">
          <img :src="episode.waveform_url" alt="waveform" class="h-10 w-full object-cover" />
        </div>

        <audio
          v-if="episode.file_url"
          ref="audioRef"
          :src="episode.file_url"
          controls
          class="w-full"
        ></audio>

        <p v-else class="text-sm text-gray-500">{{ t('Audio not available.') }}</p>
      </div>

      <p class="mt-12 text-xs text-gray-400">
        {{ t('Made with :name', { name: appName }) }}
      </p>
    </div>
  </div>
</template>
