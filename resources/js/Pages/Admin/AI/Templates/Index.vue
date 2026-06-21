<template>
  <AdminLayout>
    <Head :title="t('Templates')" />

    <div class="px-4 py-8 sm:px-6">
      <div class="mx-auto flex w-full sm:max-w-7xl flex-col gap-6">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Templates') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ t('Manage full-site AI template experiences, bundled tools, and landing content.') }}
            </p>
          </div>
        </section>

        <section v-if="props.templates.length > 0">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2 2xl:grid-cols-3">
            <article
              v-for="template in props.templates"
              :key="template.slug"
              class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-card transition duration-200 hover:-translate-y-1 hover:border-primary-200 hover:shadow-md dark:border-surface-700 dark:bg-surface-900"
            >
              <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-4">
                  <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                    <i :class="template.icon || 'ti ti-layout-board'" class="text-xl"></i>
                  </span>
                  <div class="min-w-0">
                    <h3 class="truncate font-heading text-lg font-bold text-gray-900 dark:text-white">{{ template.name }}</h3>
                    <p class="text-xs font-medium lowercase tracking-[0.18em] text-gray-400 dark:text-gray-500">{{ template.slug }}</p>
                  </div>
                </div>
                <span
                  class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-semibold"
                  :class="template.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                >
                  {{ template.is_active ? t('Active') : t('Inactive') }}
                </span>
              </div>

              <p class="mt-4 min-h-12 text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ template.tagline || t('No tagline added yet.') }}
              </p>

              <div class="mt-5 flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">
                  {{ t(':count tools', { count: template.bundled_tool_count }) }}
                </span>
                <span v-if="template.access_level === 'premium' || template.access_level?.startsWith('plan:')" class="inline-flex items-center rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                  {{ t('Pro experience') }}
                </span>
              </div>

              <div class="mt-6 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 dark:border-surface-800">
                <a
                  :href="route('admin.ai.templates.edit', template.slug)"
                  class="inline-flex items-center justify-center rounded-xl btn-primary px-4 py-2.5 text-sm font-semibold"
                >
                  <i class="ti ti-pencil me-2 text-base"></i>
                  {{ t('Edit') }}
                </a>
                <button
                  type="button"
                  class="inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
                  :class="template.is_active
                    ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300'
                    : 'border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300'"
                  @click="toggle(template)"
                >
                  <i :class="template.is_active ? 'ti ti-player-pause' : 'ti ti-player-play'" class="me-2 text-base"></i>
                  {{ template.is_active ? t('Disable') : t('Enable') }}
                </button>
              </div>
            </article>
          </div>
        </section>

        <section v-else class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center shadow-card dark:border-surface-700 dark:bg-surface-900">
          <div class="mx-auto max-w-2xl">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
              <i class="ti ti-layout-grid text-3xl"></i>
            </div>
            <h2 class="mt-5 font-heading text-2xl font-bold text-gray-900 dark:text-white">{{ t('No templates available yet') }}</h2>
            <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
              {{ t('Seed the starter template set before customizing AI landing experiences.') }}
            </p>
            <code class="mt-4 inline-flex rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 dark:bg-surface-800 dark:text-gray-300">php artisan db:seed --class=SiteTemplateSeeder</code>
          </div>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

type TemplateCard = {
  id: number
  slug: string
  name: string
  tagline: string | null
  icon: string | null
  access_level: string
  is_active: boolean
  bundled_tool_count: number
}

const props = defineProps<{
  templates: TemplateCard[]
}>()

const { t } = useTranslate()

function toggle(template: Pick<TemplateCard, 'slug'>) {
  router.post(route('admin.ai.templates.toggle', template.slug))
}
</script>
