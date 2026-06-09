<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Site Templates') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Full pre-built page experiences. Click "Edit" to customize appearance, content, code, SEO, and view bundled tools.') }}</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="template in props.templates"
          :key="template.slug"
          class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-6 flex flex-col gap-3 transition-shadow hover:shadow-md"
          :class="{ 'opacity-50': !template.is_active }"
        >
          <div class="flex items-center gap-3">
            <span v-if="template.icon" class="text-2xl text-primary-500 dark:text-primary-400">
              <i :class="template.icon" />
            </span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ template.name }}</h3>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 flex-1">{{ template.tagline }}</p>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-surface-700 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
              {{ t(':count tools', { count: template.bundled_tool_count }) }}
            </span>
            <span v-if="template.requires_pro" class="inline-flex items-center rounded-full bg-purple-100 dark:bg-purple-900/30 px-2.5 py-0.5 text-xs font-medium text-purple-700 dark:text-purple-400">
              {{ t('Pro') }}
            </span>
            <span v-if="!template.is_active" class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
              {{ t('Inactive') }}
            </span>
          </div>
          <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-surface-700">
            <a
              :href="route('admin.site-templates.edit', template.slug)"
              class="inline-flex items-center rounded-lg btn-primary transition-colors"
            >
              {{ t('Edit') }}
            </a>
            <button
              @click="toggle(template)"
              class="inline-flex items-center rounded-lg border border-gray-200 dark:border-surface-600 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors"
            >
              {{ template.is_active ? t('Disable') : t('Enable') }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="props.templates.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-500">
        {{ t('No site templates found. Run') }} <code class="text-sm bg-gray-100 dark:bg-surface-700 px-1 rounded">php artisan db:seed --class=SiteTemplateSeeder</code>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
  templates: Array<{
    id: number
    slug: string
    name: string
    tagline: string | null
    icon: string | null
    requires_pro: boolean
    is_active: boolean
    bundled_tool_count: number
  }>
}>()

const { t } = useTranslate()

function toggle(template: { slug: string }) {
  router.post(route('admin.site-templates.toggle', template.slug))
}
</script>
