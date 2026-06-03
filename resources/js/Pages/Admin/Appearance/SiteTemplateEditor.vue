<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Site Template — {{ form.name }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Slug: <code class="text-xs bg-gray-100 dark:bg-surface-700 px-1.5 py-0.5 rounded">{{ props.template.slug }}</code> · Layout: <code class="text-xs bg-gray-100 dark:bg-surface-700 px-1.5 py-0.5 rounded">{{ props.template.layout_component }}</code></p>
      </div>

      <div class="flex items-center gap-1 border-b border-gray-200 dark:border-surface-700 mb-6">
        <button
          v-for="tab in tabs" :key="tab.key"
          @click="activeTab = tab.key"
          :class="activeTab === tab.key
            ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400'
            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
          class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px"
        >{{ tab.label }}</button>
      </div>

      <form @submit.prevent="save" class="max-w-2xl space-y-8">
        <!-- Appearance Tab -->
        <div v-show="activeTab === 'appearance'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Appearance</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Leave blank to inherit global design system values.</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</span>
              <input v-model="form.color_primary" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Color</span>
              <input v-model="form.color_secondary" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Background Color</span>
              <input v-model="form.color_bg" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Surface/Card Color</span>
              <input v-model="form.color_surface" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Text Color</span>
              <input v-model="form.color_text" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Heading Font</span>
              <input v-model="form.font_heading" placeholder="e.g. Inter" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Body Font</span>
              <input v-model="form.font_body" placeholder="e.g. Inter" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400" />
            </label>
          </div>

          <button type="button" @click="resetToDefaults" class="inline-flex items-center rounded-lg border border-gray-200 dark:border-surface-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors">
            Reset to Defaults
          </button>
        </div>

        <!-- Content Tab -->
        <div v-show="activeTab === 'content'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Content</h2>

          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Template Name</span>
            <input v-model="form.name" required class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tagline</span>
            <input v-model="form.tagline" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hero Headline</span>
            <input v-model="form.hero_headline" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" placeholder="Main heading on template landing" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Hero Subheadline</span>
            <textarea v-model="form.hero_subheadline" rows="3" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CTA Text</span>
              <input v-model="form.hero_cta_text" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" placeholder="Get Started" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CTA URL</span>
              <input v-model="form.hero_cta_url" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" placeholder="Leave empty for login/register" />
            </label>
          </div>
        </div>

        <!-- Custom Code Tab -->
        <div v-show="activeTab === 'code'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Custom Code</h2>
          <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            Custom code can break the template. Test carefully.
          </div>

          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom CSS</span>
            <textarea v-model="form.custom_css" rows="6" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white" placeholder="Scoped to this template only" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom HTML (head)</span>
            <textarea v-model="form.custom_html_head" rows="4" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white" placeholder="Injected in <head> — scripts, fonts, analytics" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Custom HTML (body end)</span>
            <textarea v-model="form.custom_html_body" rows="4" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white" placeholder="Injected before </body> — chat widgets, pixels" />
          </label>
        </div>

        <!-- SEO Tab -->
        <div v-show="activeTab === 'seo'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">SEO</h2>

          <label class="block space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Meta Title</span>
              <span class="text-xs text-gray-400">{{ (form.meta_title ?? '').length }}/60</span>
            </div>
            <input v-model="form.meta_title" maxlength="60" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <label class="block space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Meta Description</span>
              <span class="text-xs text-gray-400">{{ (form.meta_description ?? '').length }}/160</span>
            </div>
            <textarea v-model="form.meta_description" maxlength="160" rows="3" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
        </div>

        <!-- Tools Tab -->
        <div v-show="activeTab === 'tools'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Bundled Tools</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">These tools are defined by the developer and cannot be changed here.</p>

          <div v-if="props.bundled_tools.length > 0" class="space-y-2">
            <div v-for="tool in props.bundled_tools" :key="tool.slug" class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-4 py-3">
              <i v-if="tool.icon" :class="tool.icon" class="text-lg text-primary-500 dark:text-primary-400" />
              <span class="text-sm text-gray-900 dark:text-white flex-1">{{ tool.name }}</span>
              <span v-if="!tool.is_active" class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                Disabled — enable in AI Tools
              </span>
              <span v-else class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                Active
              </span>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400 dark:text-gray-500 py-4">No bundled tools configured for this template.</div>

          <div v-if="props.missing_tool_slugs.length > 0" class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            <strong>Missing tools:</strong> {{ props.missing_tool_slugs.join(', ') }}
          </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-surface-700">
          <button type="submit" class="inline-flex items-center rounded-lg bg-primary-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600 transition-colors shadow-sm">
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps<{
  template: Record<string, any>
  bundled_tools: Array<{ slug: string; name: string; icon: string | null; is_active: boolean }>
  missing_tool_slugs: string[]
}>()

const tabs = [
  { key: 'appearance', label: 'Appearance' },
  { key: 'content', label: 'Content' },
  { key: 'code', label: 'Custom Code' },
  { key: 'seo', label: 'SEO' },
  { key: 'tools', label: 'Tools' },
]

const activeTab = ref('appearance')

const form = ref({
  name: props.template.name ?? '',
  tagline: props.template.tagline ?? '',
  icon: props.template.icon ?? '',
  color_primary: props.template.color_primary ?? '',
  color_secondary: props.template.color_secondary ?? '',
  color_bg: props.template.color_bg ?? '',
  color_surface: props.template.color_surface ?? '',
  color_text: props.template.color_text ?? '',
  font_heading: props.template.font_heading ?? '',
  font_body: props.template.font_body ?? '',
  hero_headline: props.template.hero_headline ?? '',
  hero_subheadline: props.template.hero_subheadline ?? '',
  hero_cta_text: props.template.hero_cta_text ?? '',
  hero_cta_url: props.template.hero_cta_url ?? '',
  custom_css: props.template.custom_css ?? '',
  custom_html_head: props.template.custom_html_head ?? '',
  custom_html_body: props.template.custom_html_body ?? '',
  meta_title: props.template.meta_title ?? '',
  meta_description: props.template.meta_description ?? '',
})

function save() {
  router.post(route('admin.site-templates.update', props.template.slug), form.value, {
    preserveScroll: true,
  })
}

function resetToDefaults() {
  router.post(route('admin.site-templates.reset', props.template.slug), {}, {
    preserveScroll: true,
    onSuccess: () => {
      form.value.color_primary = ''
      form.value.color_secondary = ''
      form.value.color_bg = ''
      form.value.color_surface = ''
      form.value.color_text = ''
      form.value.font_heading = ''
      form.value.font_body = ''
    },
  })
}
</script>
