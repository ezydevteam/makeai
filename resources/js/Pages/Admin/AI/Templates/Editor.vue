<template>
  <AdminLayout>
    <Head :title="pageTitle" />

    <div class="px-4 py-8 sm:px-6">
      <div class="mx-auto flex w-full sm:max-w-7xl flex-col gap-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Template Editor') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ form.name || props.template.name }} · {{ props.template.slug }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <Link :href="route('admin.ai.templates.index')" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
              <i class="ti ti-arrow-left me-2 text-sm"></i>
              {{ t('Back') }}
            </Link>
            <button type="button" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800" :disabled="activeTab === 'chatbot' || activeTab === 'platforms' || activeTab === 'stages' || processingAction !== null" @click="resetToDefaults">
              <i :class="processingAction === 'reset' ? 'ti ti-loader-2 animate-spin' : 'ti ti-restore'" class="me-2 text-sm"></i>
              {{ processingAction === 'reset' ? t('Resetting...') : t('Reset') }}
            </button>
            <button v-if="activeTab === 'chatbot'" type="button" class="inline-flex items-center rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold disabled:opacity-60" :disabled="processingAction !== null" @click="saveChatbotSettings">
              <i :class="processingAction === 'chatbot' ? 'ti ti-loader-2 animate-spin' : 'ti ti-device-floppy'" class="me-2 text-sm"></i>
              {{ processingAction === 'chatbot' ? t('Saving...') : t('Save') }}
            </button>
            <button v-else-if="activeTab === 'platforms'" type="button" class="inline-flex items-center rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold disabled:opacity-60" :disabled="processingAction !== null" @click="savePlatformSettings">
              <i :class="processingAction === 'platforms' ? 'ti ti-loader-2 animate-spin' : 'ti ti-device-floppy'" class="me-2 text-sm"></i>
              {{ processingAction === 'platforms' ? t('Saving...') : t('Save') }}
            </button>
            <button v-else-if="activeTab === 'stages'" type="button" class="inline-flex items-center rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold disabled:opacity-60" :disabled="processingAction !== null" @click="saveStageSettings">
              <i :class="processingAction === 'stages' ? 'ti ti-loader-2 animate-spin' : 'ti ti-device-floppy'" class="me-1 text-sm"></i>
              {{ processingAction === 'stages' ? t('Saving...') : t('Save') }}
            </button>
            <button v-else type="button" class="inline-flex items-center rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold disabled:opacity-60" :disabled="processingAction !== null" @click="save">
              <i :class="processingAction === 'save' ? 'ti ti-loader-2 animate-spin' : 'ti ti-device-floppy'" class="me-1 text-sm"></i>
              {{ processingAction === 'save' ? t('Saving...') : t('Save') }}
            </button>
          </div>
        </section>

        <form class="rounded-2xl border border-gray-200 bg-white shadow-card dark:border-surface-700 dark:bg-surface-900" @submit.prevent="save">
          <section class="border-b border-gray-100 dark:border-surface-800">
            <div class="overflow-x-auto overflow-y-hidden px-6">
              <div class="flex min-w-max items-center gap-1">
                <button
                  v-for="tab in tabs"
                  :key="tab.key"
                  type="button"
                  class="-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-4 text-sm font-medium transition"
                  :class="activeTab === tab.key
                    ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                  @click="activeTab = tab.key"
                >
                  <i :class="tab.icon" class="text-base"></i>
                  {{ tab.label }}
                </button>
              </div>
            </div>
          </section>

          <div class="space-y-6 p-6">
          <section v-show="activeTab === 'appearance'">
            <div>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <AppColorPicker v-model="form.color_primary" :label="t('Primary Color')" />
                <AppColorPicker v-model="form.color_secondary" :label="t('Secondary Color')" />
                <AppColorPicker v-model="form.color_bg" :label="t('Background Color')" />
                <AppColorPicker v-model="form.color_surface" :label="t('Surface Color')" />
                <AppColorPicker v-model="form.color_text" :label="t('Text Color')" />
                <AppSelect v-model="form.font_heading" :label="t('Heading Font')" :options="fontFamilyOptions" :placeholder="t('Select heading font')" live-search />
                <div class="md:col-span-2 xl:col-span-3">
                  <AppSelect v-model="form.font_body" :label="t('Body Font')" :options="fontFamilyOptions" :placeholder="t('Select body font')" live-search />
                </div>
              </div>

              <div class="mt-5 rounded-2xl border border-gray-100 bg-gray-50 p-4 text-sm text-gray-500 dark:border-surface-800 dark:bg-surface-800/70 dark:text-gray-400">
                {{ t('Leave any color or font empty to inherit the global brand defaults for this template.') }}
              </div>
            </div>
          </section>

          <section v-show="activeTab === 'content'">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('Template Name') }}
                <input v-model="form.name" required :placeholder="t('Enter template name')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('Tagline') }}
                <input v-model="form.tagline" :placeholder="t('A concise positioning line for this experience')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                {{ t('Hero Headline') }}
                <input v-model="form.hero_headline" :placeholder="t('Main heading on template landing')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                {{ t('Hero Subheadline') }}
                <textarea v-model="form.hero_subheadline" rows="4" :placeholder="t('Expand on the value proposition with a short support paragraph.')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('CTA Text') }}
                <input v-model="form.hero_cta_text" :placeholder="t('Get Started')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('CTA URL') }}
                <input v-model="form.hero_cta_url" :placeholder="t('Leave empty for login/register')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
              </label>
            </div>
          </section>

          <section v-show="activeTab === 'code'">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-200">
              {{ t('Custom code can break the template rendering. Keep changes scoped and test them carefully before shipping.') }}
            </div>

            <div class="mt-5 space-y-5">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('Custom CSS') }}
                <textarea v-model="form.custom_css" rows="7" :placeholder="t('Scoped to this template only')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('Custom HTML (head)') }}
                <textarea v-model="form.custom_html_head" rows="5" :placeholder="t('Injected in <head> for fonts, analytics, or metadata fragments.')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ t('Custom HTML (body end)') }}
                <textarea v-model="form.custom_html_body" rows="5" :placeholder="t('Injected before </body> for chat widgets, pixels, or footer embeds.')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
              </label>
            </div>
          </section>

          <section v-show="activeTab === 'seo'">
            <div class="grid grid-cols-1 gap-5">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                <span class="flex items-center justify-between">
                  <span>{{ t('Meta Title') }}</span>
                  <span class="text-xs text-gray-400">{{ metaTitleLength }}/60</span>
                </span>
                <input v-model="form.meta_title" maxlength="60" :placeholder="t('Template page title for search results')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" />
              </label>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                <span class="flex items-center justify-between">
                  <span>{{ t('Meta Description') }}</span>
                  <span class="text-xs text-gray-400">{{ metaDescriptionLength }}/160</span>
                </span>
                <textarea v-model="form.meta_description" maxlength="160" rows="4" :placeholder="t('Summarize the value of this template in a concise search snippet.')" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
              </label>
            </div>
          </section>

          <section v-show="activeTab === 'tools'">
            <div v-if="props.bundled_tools.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <article v-for="tool in props.bundled_tools" :key="tool.slug" class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800/70">
                <div class="flex items-start justify-between gap-4">
                  <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                      <i :class="tool.icon || 'ti ti-sparkles'" class="text-lg"></i>
                    </span>
                    <div class="min-w-0">
                      <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ tool.name }}</div>
                      <div class="mt-1 truncate text-xs uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">{{ tool.slug }}</div>
                    </div>
                  </div>
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="tool.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'">
                    {{ tool.is_active ? t('Active') : t('Disabled') }}
                  </span>
                </div>
              </article>
            </div>
            <div v-else class="mt-6 rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-400 dark:border-surface-700 dark:text-gray-500">
              {{ t('No bundled tools configured for this template.') }}
            </div>

            <div v-if="props.missing_tool_slugs.length > 0" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-200">
              <strong>{{ t('Missing tools') }}:</strong> {{ props.missing_tool_slugs.join(', ') }}
            </div>
          </section>

          <section v-if="props.template.slug === 'social-media-manager'" v-show="activeTab === 'platforms'">
            <div class="space-y-3">
              <div
                v-for="(p, idx) in platformData"
                :key="p.slug"
                class="flex items-center gap-4 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70"
              >
                <span
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white"
                  :style="{ background: p.color_hex, opacity: p.enabled ? 1 : 0.3 }"
                >
                  <i :class="p.icon" class="text-lg"></i>
                </span>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ p.label }}</div>
                  <div class="text-xs text-gray-400 mt-0.5">{{ p.slug }}</div>
                </div>
                <button
                  type="button"
                  role="switch"
                  :aria-checked="p.enabled"
                  class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition"
                  :class="p.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'"
                  @click="platformData[idx].enabled = !platformData[idx].enabled"
                >
                  <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="p.enabled ? 'translate-x-5' : 'translate-x-0.5'"></span>
                </button>
              </div>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
              <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white mb-4">{{ t('Default Platform') }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ t('Pre-selected platform filter when the page loads (leave empty for "All Platforms").') }}</p>
              <AppSelect
                v-model="defaultPlatform"
                :label="t('Default platform')"
                :options="platformSelectOptions"
                :placeholder="t('All Platforms')"
              />
            </div>
          </section>

          <section v-if="props.template.slug === 'marketing-suite'" v-show="activeTab === 'stages'">
            <div class="space-y-4">
              <div
                v-for="(s, idx) in stageData"
                :key="s.slug"
                class="grid grid-cols-1 md:grid-cols-[auto_1fr] gap-4 items-start rounded-xl border border-gray-100 bg-gray-50 p-5 dark:border-surface-800 dark:bg-surface-800/70"
              >
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 dark:text-gray-300 pt-1.5">
                  Stage {{ idx + 1 }}
                </span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Label') }}
                    <input
                      v-model="stageData[idx].label"
                      required
                      :placeholder="t('Stage name')"
                      class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                    />
                  </label>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Tabler Icon') }}
                    <input
                      v-model="stageData[idx].icon"
                      required
                      :placeholder="t('ti ti-eye')"
                      class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 font-mono text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white"
                    />
                  </label>
                </div>
              </div>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
              <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white mb-4">{{ t('Default Stage') }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ t('Which stage is selected when the page first loads.') }}</p>
              <div class="flex flex-wrap gap-3">
                <label
                  v-for="s in stageData"
                  :key="s.slug"
                  class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium cursor-pointer transition-all"
                  :class="defaultStage === s.slug
                    ? 'border-transparent bg-primary-600 text-white shadow-md'
                    : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300'"
                >
                  <input type="radio" :value="s.slug" v-model="defaultStage" class="sr-only" />
                  <i :class="s.icon" class="text-base"></i>
                  {{ s.label }}
                </label>
              </div>
            </div>
          </section>

          <section v-if="props.template.slug === 'ai-chatbot'" v-show="activeTab === 'chatbot'">
            <div class="space-y-6">
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Page Layout') }}</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                  <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                    <div>
                      <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hide site header') }}</div>
                      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Remove the global site header from the chatbot page.') }}</div>
                    </div>
                    <button type="button" role="switch" :aria-checked="cs.hide_site_header" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.hide_site_header ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.hide_site_header = !cs.hide_site_header">
                      <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.hide_site_header ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                  </div>
                  <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                    <div>
                      <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Hide site footer') }}</div>
                      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Remove the global site footer from the chatbot page.') }}</div>
                    </div>
                    <button type="button" role="switch" :aria-checked="cs.hide_site_footer" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.hide_site_footer ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.hide_site_footer = !cs.hide_site_footer">
                      <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.hide_site_footer ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Guest Access') }}</h3>
                <div class="mt-4 flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                  <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Allow guest messages') }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Let visitors try the chatbot before logging in.') }}</div>
                  </div>
                  <button type="button" role="switch" :aria-checked="cs.allow_guest_messages" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.allow_guest_messages ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.allow_guest_messages = !cs.allow_guest_messages">
                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.allow_guest_messages ? 'translate-x-5' : 'translate-x-0.5'"></span>
                  </button>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Max messages per guest session') }}
                    <input v-model.number="cs.guest_max_messages" type="number" min="0" max="100" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                  </label>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Max tokens per guest message') }}
                    <input v-model.number="cs.guest_max_tokens" type="number" min="100" max="8000" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                  </label>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                  <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Free Plan Users') }}</h3>
                  <div class="mt-4 grid grid-cols-1 gap-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Credits per message') }}
                      <input v-model.number="cs.free_credits_per_message" type="number" step="0.1" min="0" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Max tokens per message') }}
                      <input v-model.number="cs.free_max_tokens" type="number" min="100" max="16000" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Max chat history stored') }}
                      <input v-model.number="cs.free_max_chat_history" type="number" min="1" max="500" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Max file size for free (MB)') }}
                      <input v-model.number="cs.free_max_file_size_mb" type="number" min="0" max="50" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                  </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                  <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Pro Plan Users') }}</h3>
                  <div class="mt-4 grid grid-cols-1 gap-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Credits per message') }}
                      <input v-model.number="cs.pro_credits_per_message" type="number" step="0.1" min="0" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Max tokens per message') }}
                      <input v-model.number="cs.pro_max_tokens" type="number" min="100" max="16000" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ t('Max file size for pro (MB)') }}
                      <input v-model.number="cs.pro_max_file_size_mb" type="number" min="0" max="100" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" />
                    </label>
                    <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                      <div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Unlimited chat history') }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Allow unlimited stored history for Pro users.') }}</div>
                      </div>
                      <button type="button" role="switch" :aria-checked="cs.pro_unlimited_history" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.pro_unlimited_history ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.pro_unlimited_history = !cs.pro_unlimited_history">
                        <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.pro_unlimited_history ? 'translate-x-5' : 'translate-x-0.5'"></span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Model Selection') }}</h3>
                <div class="mt-4 flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                  <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Allow users to select AI model') }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Shows a model picker in the chat input area.') }}</div>
                  </div>
                  <button type="button" role="switch" :aria-checked="cs.allow_model_select" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.allow_model_select ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.allow_model_select = !cs.allow_model_select">
                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.allow_model_select ? 'translate-x-5' : 'translate-x-0.5'"></span>
                  </button>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div v-if="cs.allow_model_select" class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                    <div>
                      <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show friendly model names') }}</div>
                      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Display simplified names like ChatGPT or Claude in the picker.') }}</div>
                    </div>
                    <button type="button" role="switch" :aria-checked="cs.show_friendly_model_names" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.show_friendly_model_names ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.show_friendly_model_names = !cs.show_friendly_model_names">
                      <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.show_friendly_model_names ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                  </div>
                  <div :class="cs.allow_model_select ? '' : 'md:col-span-2'">
                    <AppSelect
                      v-model="cs.default_chat_model"
                      :label="t('Default model')"
                      :options="chatModelOptions"
                      :placeholder="t('Select default model')"
                      live-search
                    />
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-surface-700 dark:bg-surface-800/70">
                <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Token Tracking') }}</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                    <div>
                      <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show token usage') }}</div>
                      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Display token usage below each AI message.') }}</div>
                    </div>
                    <button type="button" role="switch" :aria-checked="cs.show_token_usage" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.show_token_usage ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.show_token_usage = !cs.show_token_usage">
                      <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.show_token_usage ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                  </div>
                  <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white p-4 dark:border-surface-800 dark:bg-surface-900">
                    <div>
                      <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show credits charged') }}</div>
                      <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Display credits deducted below each AI message.') }}</div>
                    </div>
                    <button type="button" role="switch" :aria-checked="cs.show_credits_charged" class="relative inline-flex h-6 w-11 rounded-full transition" :class="cs.show_credits_charged ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="cs.show_credits_charged = !cs.show_credits_charged">
                      <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="cs.show_credits_charged ? 'translate-x-5' : 'translate-x-0.5'"></span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppColorPicker from '@/Components/AppColorPicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { FONT_FAMILY_SELECT_OPTIONS } from '@/config/fontFamilies'

type BundledTool = {
  slug: string
  name: string
  icon: string | null
  is_active: boolean
}

type TemplateRecord = {
  slug: string
  name: string
  tagline?: string | null
  icon?: string | null
  layout_component: string
  color_primary?: string | null
  color_secondary?: string | null
  color_bg?: string | null
  color_surface?: string | null
  color_text?: string | null
  font_heading?: string | null
  font_body?: string | null
  hero_headline?: string | null
  hero_subheadline?: string | null
  hero_cta_text?: string | null
  hero_cta_url?: string | null
  custom_css?: string | null
  custom_html_head?: string | null
  custom_html_body?: string | null
  meta_title?: string | null
  meta_description?: string | null
}

type ChatbotSettings = {
  hide_site_header: boolean
  hide_site_footer: boolean
  allow_guest_messages: boolean
  guest_max_messages: number
  guest_max_tokens: number
  free_credits_per_message: number
  free_max_tokens: number
  free_max_chat_history: number
  free_max_file_size_mb: number
  pro_credits_per_message: number
  pro_max_tokens: number
  pro_max_file_size_mb: number
  pro_unlimited_history: boolean
  show_token_usage: boolean
  show_credits_charged: boolean
  allow_model_select: boolean
  show_friendly_model_names: boolean
  default_chat_model: string
}

type PlatformDef = {
  slug: string
  label: string
  icon: string
  color_hex: string
  enabled: boolean
}

type StageDef = {
  slug: string
  label: string
  icon: string
}

type TabKey = 'appearance' | 'content' | 'code' | 'seo' | 'tools' | 'platforms' | 'stages' | 'chatbot'

const { t } = useTranslate()

const props = defineProps<{
  template: TemplateRecord
  bundled_tools: BundledTool[]
  missing_tool_slugs: string[]
  chatbotSettings?: Partial<ChatbotSettings>
  chatModels?: Record<string, string>
  platformSettings?: PlatformDef[]
  stageSettings?: StageDef[]
}>()

const tabs = computed<Array<{ key: TabKey; label: string; icon: string }>>(() => ([
  { key: 'appearance', label: t('Appearance'), icon: 'ti ti-palette' },
  { key: 'content', label: t('Content'), icon: 'ti ti-text-caption' },
  { key: 'code', label: t('Custom Code'), icon: 'ti ti-code' },
  { key: 'seo', label: t('SEO'), icon: 'ti ti-world-search' },
  { key: 'tools', label: t('Tools'), icon: 'ti ti-tool' },
  ...(props.template.slug === 'social-media-manager'
    ? [{ key: 'platforms' as TabKey, label: t('Platforms'), icon: 'ti ti-layout-grid' }]
    : []),
  ...(props.template.slug === 'marketing-suite'
    ? [{ key: 'stages' as TabKey, label: t('Stages'), icon: 'ti ti-route' }]
    : []),
  ...(props.template.slug === 'ai-chatbot'
    ? [{ key: 'chatbot' as TabKey, label: t('Chatbot Settings'), icon: 'ti ti-message-2-cog' }]
    : []),
]))

const activeTab = ref<TabKey>('appearance')
const pageTitle = computed(() => t('Template Editor'))
const metaTitleLength = computed(() => form.value.meta_title.length)
const metaDescriptionLength = computed(() => form.value.meta_description.length)
const fontFamilyOptions = computed(() => FONT_FAMILY_SELECT_OPTIONS)
const processingAction = ref<'save' | 'reset' | 'chatbot' | 'platforms' | 'stages' | null>(null)
const chatModelOptions = computed(() =>
  Object.entries(props.chatModels ?? {}).map(([value, label]) => ({ value, label }))
)

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
  processingAction.value = 'save'
  router.post(route('admin.ai.templates.update', props.template.slug), form.value, {
    preserveScroll: true,
    onFinish: () => {
      processingAction.value = null
    },
  })
}

function resetToDefaults() {
  processingAction.value = 'reset'
  router.post(route('admin.ai.templates.reset', props.template.slug), {}, {
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
    onFinish: () => {
      processingAction.value = null
    },
  })
}

const cs = ref<ChatbotSettings>({
  hide_site_header: false,
  hide_site_footer: false,
  allow_guest_messages: false,
  guest_max_messages: 3,
  guest_max_tokens: 500,
  free_credits_per_message: 1,
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
  if (props.platformSettings) {
    platformData.value = props.platformSettings.map(p => ({ ...p }))
  }
  if (props.stageSettings) {
    stageData.value = props.stageSettings.map(s => ({ ...s }))
  }
})

function saveChatbotSettings() {
  processingAction.value = 'chatbot'
  router.post(route('admin.ai.templates.chatbot-settings', props.template.slug), cs.value, {
    preserveScroll: true,
    onFinish: () => {
      processingAction.value = null
    },
  })
}

const platformData = ref<PlatformDef[]>([
  { slug: 'instagram', label: 'Instagram', icon: 'ti ti-brand-instagram', color_hex: '#e1306c', enabled: true },
  { slug: 'twitter', label: 'Twitter/X', icon: 'ti ti-brand-x', color_hex: '#1da1f2', enabled: true },
  { slug: 'linkedin', label: 'LinkedIn', icon: 'ti ti-brand-linkedin', color_hex: '#0a66c2', enabled: true },
  { slug: 'tiktok', label: 'TikTok', icon: 'ti ti-brand-tiktok', color_hex: '#000000', enabled: true },
  { slug: 'facebook', label: 'Facebook', icon: 'ti ti-brand-facebook', color_hex: '#1877f2', enabled: true },
  { slug: 'youtube', label: 'YouTube', icon: 'ti ti-brand-youtube', color_hex: '#ff0000', enabled: true },
])

const defaultPlatform = ref('')

const platformSelectOptions = computed(() =>
  platformData.value.map(p => ({ value: p.slug, label: p.label }))
)

function savePlatformSettings() {
  processingAction.value = 'platforms'
  router.post(route('admin.ai.templates.platform-settings', props.template.slug), {
    platforms: platformData.value,
    default_platform: defaultPlatform.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      processingAction.value = null
    },
  })
}

const stageData = ref<StageDef[]>([
  { slug: 'awareness', label: 'Awareness', icon: 'ti ti-eye' },
  { slug: 'consideration', label: 'Consideration', icon: 'ti ti-bulb' },
  { slug: 'conversion', label: 'Conversion', icon: 'ti ti-currency-dollar' },
  { slug: 'retention', label: 'Retention', icon: 'ti ti-repeat' },
])

const defaultStage = ref('awareness')

function saveStageSettings() {
  processingAction.value = 'stages'
  router.post(route('admin.ai.templates.stage-settings', props.template.slug), {
    stages: stageData.value,
    default_stage: defaultStage.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      processingAction.value = null
    },
  })
}
</script>
