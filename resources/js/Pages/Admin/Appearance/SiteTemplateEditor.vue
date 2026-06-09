<template>
  <AdminLayout>
    <div class="p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Edit Site Template') }} - {{ form.name }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Slug') }}: <code class="text-xs bg-gray-100 dark:bg-surface-700 px-1.5 py-0.5 rounded">{{ props.template.slug }}</code> · {{ t('Layout') }}: <code class="text-xs bg-gray-100 dark:bg-surface-700 px-1.5 py-0.5 rounded">{{ props.template.layout_component }}</code></p>
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
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Appearance') }}</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Leave blank to inherit global design system values.') }}</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Primary Color') }}</span>
              <input v-model="form.color_primary" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Secondary Color') }}</span>
              <input v-model="form.color_secondary" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Background Color') }}</span>
              <input v-model="form.color_bg" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Surface/Card Color') }}</span>
              <input v-model="form.color_surface" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Text Color') }}</span>
              <input v-model="form.color_text" type="color" class="h-10 w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 text-sm cursor-pointer" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Heading Font') }}</span>
              <input v-model="form.font_heading" :placeholder="t('e.g. Inter')" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Body Font') }}</span>
              <input v-model="form.font_body" :placeholder="t('e.g. Inter')" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder:text-gray-400" />
            </label>
          </div>

          <button type="button" @click="resetToDefaults" class="inline-flex items-center rounded-lg border border-gray-200 dark:border-surface-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors">
            {{ t('Reset to Defaults') }}
          </button>
        </div>

        <!-- Content Tab -->
        <div v-show="activeTab === 'content'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Content') }}</h2>

          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Template Name') }}</span>
            <input v-model="form.name" required class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Tagline') }}</span>
            <input v-model="form.tagline" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hero Headline') }}</span>
            <input v-model="form.hero_headline" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" :placeholder="t('Main heading on template landing')" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hero Subheadline') }}</span>
            <textarea v-model="form.hero_subheadline" rows="3" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('CTA Text') }}</span>
              <input v-model="form.hero_cta_text" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" :placeholder="t('Get Started')" />
            </label>
            <label class="block space-y-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('CTA URL') }}</span>
              <input v-model="form.hero_cta_url" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" :placeholder="t('Leave empty for login/register')" />
            </label>
          </div>
        </div>

        <!-- Custom Code Tab -->
        <div v-show="activeTab === 'code'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Custom Code') }}</h2>
          <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            {{ t('Custom code can break the template. Test carefully.') }}
          </div>

          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom CSS') }}</span>
            <textarea v-model="form.custom_css" rows="6" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white" :placeholder="t('Scoped to this template only')" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom HTML (head)') }}</span>
            <textarea v-model="form.custom_html_head" rows="4" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white" :placeholder="t('Injected in <head> - scripts, fonts, analytics')" />
          </label>
          <label class="block space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Custom HTML (body end)') }}</span>
            <textarea v-model="form.custom_html_body" rows="4" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-gray-50 dark:bg-surface-800 px-3 py-2 text-sm font-mono text-gray-900 dark:text-white" :placeholder="t('Injected before </body> - chat widgets, pixels')" />
          </label>
        </div>

        <!-- SEO Tab -->
        <div v-show="activeTab === 'seo'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('SEO') }}</h2>

          <label class="block space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Title') }}</span>
              <span class="text-xs text-gray-400">{{ (form.meta_title ?? '').length }}/60</span>
            </div>
            <input v-model="form.meta_title" maxlength="60" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
          <label class="block space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Meta Description') }}</span>
              <span class="text-xs text-gray-400">{{ (form.meta_description ?? '').length }}/160</span>
            </div>
            <textarea v-model="form.meta_description" maxlength="160" rows="3" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
          </label>
        </div>

        <!-- Tools Tab -->
        <div v-show="activeTab === 'tools'" class="space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Bundled Tools') }}</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('These tools are defined by the developer and cannot be changed here.') }}</p>

          <div v-if="props.bundled_tools.length > 0" class="space-y-2">
            <div v-for="tool in props.bundled_tools" :key="tool.slug" class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-4 py-3">
              <i v-if="tool.icon" :class="tool.icon" class="text-lg text-primary-500 dark:text-primary-400" />
              <span class="text-sm text-gray-900 dark:text-white flex-1">{{ tool.name }}</span>
              <span v-if="!tool.is_active" class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                {{ t('Disabled - enable in AI Tools') }}
              </span>
              <span v-else class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                {{ t('Active') }}
              </span>
            </div>
          </div>
          <div v-else class="text-sm text-gray-400 dark:text-gray-500 py-4">{{ t('No bundled tools configured for this template.') }}</div>

          <div v-if="props.missing_tool_slugs.length > 0" class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
            <strong>{{ t('Missing tools') }}:</strong> {{ props.missing_tool_slugs.join(', ') }}
          </div>
        </div>

        <!-- Chatbot Settings Tab (ai-chatbot only) -->
        <div v-if="props.template.slug === 'ai-chatbot'" v-show="activeTab === 'chatbot'" class="space-y-6">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Chatbot Settings') }}</h2>

          <div class="rounded-xl border border-gray-200 dark:border-surface-700 p-5 space-y-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('Page Layout') }}</h3>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="cs.hide_site_header" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hide site header') }}</span>
                <p class="text-xs text-gray-400 mt-0.5">{{ t('Remove the global site header from the chatbot page.') }}</p>
              </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="cs.hide_site_footer" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Hide site footer') }}</span>
                <p class="text-xs text-gray-400 mt-0.5">{{ t('Remove the global site footer from the chatbot page.') }}</p>
              </div>
            </label>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-surface-700 p-5 space-y-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('Guest (not logged in)') }}</h3>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="cs.allow_guest_messages" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Allow guest messages (no login required)') }}</span>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label class="block space-y-1">
                <span class="text-xs font-medium text-gray-500">{{ t('Max messages per guest session') }}</span>
                <input v-model.number="cs.guest_max_messages" type="number" min="0" max="100" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
              </label>
              <label class="block space-y-1">
                <span class="text-xs font-medium text-gray-500">{{ t('Max tokens per guest message') }}</span>
                <input v-model.number="cs.guest_max_tokens" type="number" min="100" max="8000" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" />
              </label>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-surface-700 p-5 space-y-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('Free Plan Users') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Credits per message') }}</span><input v-model.number="cs.free_credits_per_message" type="number" step="0.1" min="0" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Max tokens per message') }}</span><input v-model.number="cs.free_max_tokens" type="number" min="100" max="16000" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Max chat history stored') }}</span><input v-model.number="cs.free_max_chat_history" type="number" min="1" max="500" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Max file size for free (MB)') }}</span><input v-model.number="cs.free_max_file_size_mb" type="number" min="0" max="50" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-surface-700 p-5 space-y-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('Pro Plan Users') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Credits per message') }}</span><input v-model.number="cs.pro_credits_per_message" type="number" step="0.1" min="0" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Max tokens per message') }}</span><input v-model.number="cs.pro_max_tokens" type="number" min="100" max="16000" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
              <label class="block space-y-1"><span class="text-xs font-medium text-gray-500">{{ t('Max file size for pro (MB)') }}</span><input v-model.number="cs.pro_max_file_size_mb" type="number" min="0" max="100" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white" /></label>
              <label class="flex items-center gap-3 cursor-pointer mt-6"><input v-model="cs.pro_unlimited_history" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" /><span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Unlimited chat history for Pro') }}</span></label>
            </div>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-surface-700 p-5 space-y-3">
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('Model Selection') }}</h3>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="cs.allow_model_select" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Allow users to select AI model') }}</span>
                <p class="text-xs text-gray-400 mt-0.5">{{ t('Shows a model picker in the chat input area.') }}</p>
              </div>
            </label>
            <label v-if="cs.allow_model_select" class="flex items-center gap-3 cursor-pointer pl-1">
              <input v-model="cs.show_friendly_model_names" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Show friendly model names') }}</span>
                <p class="text-xs text-gray-400 mt-0.5">{{ t('Displays "ChatGPT" instead of "gpt-4o", "Claude" instead of "claude-3-opus", etc.') }}</p>
              </div>
            </label>
            <label class="block space-y-1">
              <span class="text-xs font-medium text-gray-500">{{ t('Default model (seed)') }}</span>
              <select v-model="cs.default_chat_model" class="w-full rounded-lg border border-gray-200 dark:border-surface-600 bg-white dark:bg-surface-800 px-3 py-2 text-sm text-gray-900 dark:text-white">
                <option v-for="(label, value) in (props.chatModels ?? {})" :key="value" :value="value">{{ label }}</option>
              </select>
              <p class="text-xs text-gray-400 mt-0.5">{{ t('Used when no user model is selected.') }}</p>
            </label>
          </div>

          <div class="rounded-xl border border-gray-200 dark:border-surface-700 p-5 space-y-3">
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ t('Token Tracking') }}</h3>
            <label class="flex items-center gap-3 cursor-pointer"><input v-model="cs.show_token_usage" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" /><span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Show token usage below each AI message') }}</span></label>
            <label class="flex items-center gap-3 cursor-pointer"><input v-model="cs.show_credits_charged" type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500" /><span class="text-sm text-gray-700 dark:text-gray-300">{{ t('Show credits charged below each AI message') }}</span></label>
          </div>

          <button type="button" @click="saveChatbotSettings" class="inline-flex items-center rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600 transition-colors shadow-sm">
            {{ t('Save Chatbot Settings') }}
          </button>
        </div>

        <!-- Only show general Save Changes when NOT on chatbot tab -->
        <div v-if="activeTab !== 'chatbot'" class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-surface-700">
          <button type="submit" class="inline-flex items-center rounded-lg btn-primary transition-colors shadow-sm">
            {{ t('Save Changes') }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

const props = defineProps<{
  template: Record<string, any>
  bundled_tools: Array<{ slug: string; name: string; icon: string | null; is_active: boolean }>
  missing_tool_slugs: string[]
  chatbotSettings?: Record<string, any>
  chatModels?: Record<string, string>
}>()

const tabs = [
  { key: 'appearance', label: t('Appearance') },
  { key: 'content', label: t('Content') },
  { key: 'code', label: t('Custom Code') },
  { key: 'seo', label: t('SEO') },
  { key: 'tools', label: t('Tools') },
  ...(props.template.slug === 'ai-chatbot' ? [{ key: 'chatbot', label: t('Chatbot Settings') }] : []),
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

const cs = ref({
  hide_site_header: false,
  hide_site_footer: false,
  allow_guest_messages: false,
  guest_max_messages: 3,
  guest_max_tokens: 500,
  free_credits_per_message: 1.0,
  free_max_tokens: 2000,
  free_max_chat_history: 30,
  free_max_file_size_mb: 5,
  pro_credits_per_message: 0.5,
  pro_max_tokens: 8000,
  pro_max_file_size_mb: 20,
  pro_unlimited_history: true,
  show_token_usage: true,
  show_credits_charged: true,
  allow_model_select: true,
  show_friendly_model_names: false,
  default_chat_model: 'gpt-4o-mini',
})

onMounted(() => {
  if (props.chatbotSettings) {
    Object.assign(cs.value, props.chatbotSettings)
  }
})

function saveChatbotSettings() {
  router.post(route('admin.site-templates.chatbot-settings', props.template.slug), cs.value, {
    preserveScroll: true,
  })
}
</script>
