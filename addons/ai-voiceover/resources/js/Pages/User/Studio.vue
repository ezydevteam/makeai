<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { ref } from 'vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()
const page = usePage()
const voiceover = (page.props.voiceover as any) || {}

interface Project {
  id: number
  ulid: string
  title: string
  type: 'voiceover' | 'podcast'
  description: string | null
  cover_art_url: string | null
  total_duration: number
  episode_count: number
  updated_at: string
  rss_enabled: boolean
}

const props = defineProps<{
  projects: {
    data: Project[]
    current_page: number
    last_page: number
    total: number
  }
}>()

const showNewModal = ref(false)
const form = useForm({
  title: '',
  type: 'voiceover' as string,
  description: '',
  cover_art: null as File | null,
  podcast_author: '',
  podcast_category: '',
  podcast_language: 'en',
  podcast_explicit: false,
})

const createProject = () => {
  form.post(route('addon.vo.user.projects.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      showNewModal.value = false
      form.reset()
    },
  })
}

const formatDuration = (seconds: number): string => {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${String(secs).padStart(2, '0')}`
}
</script>

<template>
  <Head :title="t('Voiceover Studio')" />

  <div class="mx-auto max-w-7xl px-6 py-8">
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Voiceover Studio') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create AI voiceovers and podcast episodes.') }}</p>
      </div>
      <button
        @click="showNewModal = true"
        class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium"
      >
        <i class="ti ti-plus"></i>
        {{ t('New Project') }}
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="props.projects.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center dark:border-gray-700 dark:bg-gray-800/50">
      <i class="ti ti-microphone text-4xl text-gray-400 dark:text-gray-500"></i>
      <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Create your first voiceover project') }}</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Get started by creating a new voiceover or podcast project.') }}</p>
      <button @click="showNewModal = true" class="btn-primary mt-6 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium">
        <i class="ti ti-plus"></i>
        {{ t('New Project') }}
      </button>
    </div>

    <!-- Project grid -->
    <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <Link
        v-for="project in props.projects.data"
        :key="project.ulid"
        :href="route('addon.vo.user.projects.show', { project: project.ulid })"
        class="group rounded-2xl border border-gray-200 bg-white p-6 transition-all hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-gray-900 dark:hover:border-primary-600"
      >
        <div class="mb-4 flex h-32 items-center justify-center rounded-xl bg-gradient-to-br from-primary-50 to-purple-50 dark:from-primary-900/20 dark:to-purple-900/20">
          <i
            :class="project.type === 'podcast' ? 'ti ti-podcast' : 'ti ti-microphone'"
            class="text-4xl text-primary-500 dark:text-primary-400"
          ></i>
        </div>
        <div class="mb-2 flex items-center gap-2">
          <span
            :class="[
              'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
              project.type === 'podcast'
                ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            ]"
          >
            <i :class="project.type === 'podcast' ? 'ti ti-podcast' : 'ti ti-microphone'" class="text-xs"></i>
            {{ project.type === 'podcast' ? t('Podcast') : t('Voiceover') }}
          </span>
          <span v-if="project.rss_enabled" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
            <i class="ti ti-rss text-xs"></i>
            RSS
          </span>
        </div>
        <h3 class="text-lg font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ project.title }}</h3>
        <div class="mt-3 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
          <span class="flex items-center gap-1">
            <i class="ti ti-headphones"></i>
            {{ project.episode_count }} {{ t('episodes') }}
          </span>
          <span class="flex items-center gap-1">
            <i class="ti ti-clock"></i>
            {{ formatDuration(project.total_duration) }}
          </span>
        </div>
      </Link>
    </div>

    <!-- Pagination -->
    <div v-if="props.projects.last_page > 1" class="mt-8 flex justify-center">
      <nav class="flex items-center gap-1">
        <template v-for="p in props.projects.last_page" :key="p">
          <Link
            :href="route('addon.vo.user.studio', { page: p })"
            :class="[
              'rounded-lg px-3 py-1.5 text-sm',
              p === props.projects.current_page
                ? 'bg-primary-500 text-white'
                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800',
            ]"
            preserve-scroll
          >
            {{ p }}
          </Link>
        </template>
      </nav>
    </div>
  </div>

  <!-- New Project Modal -->
  <Teleport to="body">
    <div v-if="showNewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showNewModal = false">
      <div class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-surface-800 dark:bg-gray-900">
        <div class="mb-5 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('New Project') }}</h2>
          <button @click="showNewModal = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
            <i class="ti ti-x"></i>
          </button>
        </div>

        <form @submit.prevent="createProject" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</label>
            <input
              v-model="form.title"
              type="text"
              required
              class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              :placeholder="t('My Awesome Project')"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Type') }}</label>
            <div class="flex gap-3">
              <label
                :class="[
                  'flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-3 transition-colors',
                  form.type === 'voiceover'
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                    : 'border-gray-200 dark:border-gray-700',
                ]"
              >
                <input v-model="form.type" type="radio" value="voiceover" class="sr-only" />
                <i class="ti ti-microphone text-primary-500"></i>
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Voiceover') }}</span>
              </label>
              <label
                :class="[
                  'flex cursor-pointer items-center gap-2 rounded-xl border px-4 py-3 transition-colors',
                  form.type === 'podcast'
                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                    : 'border-gray-200 dark:border-gray-700',
                ]"
              >
                <input v-model="form.type" type="radio" value="podcast" class="sr-only" />
                <i class="ti ti-podcast text-primary-500"></i>
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Podcast') }}</span>
              </label>
            </div>
          </div>

          <template v-if="form.type === 'podcast'">
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Author') }}</label>
              <input
                v-model="form.podcast_author"
                type="text"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :placeholder="t('Podcast Host')"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Category') }}</label>
              <input
                v-model="form.podcast_category"
                type="text"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                :placeholder="t('Technology')"
              />
            </div>
          </template>

          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              :placeholder="t('Optional description...')"
            ></textarea>
          </div>

          <div v-if="form.errors.title" class="text-sm text-red-500">{{ form.errors.title }}</div>

          <div class="flex justify-end gap-3">
            <button
              type="button"
              @click="showNewModal = false"
              class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
            >
              {{ t('Cancel') }}
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium"
            >
              <i v-if="form.processing" class="ti ti-loader animate-spin"></i>
              {{ form.processing ? t('Creating...') : t('Create Project') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
