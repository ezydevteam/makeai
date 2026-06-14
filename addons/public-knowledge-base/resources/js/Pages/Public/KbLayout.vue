<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { usePage, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const { t } = useTranslate()

const page = usePage()
const kbSettings = computed(() => (page.props as any).kbSettings || {})

const searchOpen = ref(false)
const searchQuery = ref('')

defineExpose({ searchOpen, searchQuery })
</script>

<template>
  <div class="min-h-screen bg-white dark:bg-gray-900">
    <nav class="border-b border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/80 backdrop-blur sticky top-0 z-40">
      <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <div class="flex items-center gap-6">
          <a :href="'/' + kbSettings.public_slug" class="font-bold text-emerald-600 dark:text-emerald-400 text-lg">
            {{ kbSettings.page_title || t('Help Center') }}
          </a>
          <a :href="'/' + kbSettings.public_slug" class="text-sm text-gray-600 dark:text-gray-300 hover:text-emerald-600">
            {{ t('Home') }}
          </a>
        </div>
        <div class="flex items-center gap-3">
          <div class="relative hidden sm:block">
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="t('Search...')"
              class="input text-sm w-48"
              @focus="searchOpen = true"
            />
            <div
              v-if="searchOpen && searchQuery.length >= 2"
              class="absolute top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 shadow-lg rounded-lg border border-gray-200 dark:border-gray-700 z-50 p-3 max-h-80 overflow-y-auto"
            >
              <p class="text-xs text-gray-400">{{ t('Type to search') }}</p>
            </div>
          </div>
          <a href="/" class="text-sm text-gray-500 hover:text-emerald-600">{{ t('Back to App') }}</a>
        </div>
      </div>
      <div class="sm:hidden px-4 pb-3">
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="t('Search...')"
          class="input text-sm w-full"
          @focus="searchOpen = true"
        />
      </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 py-8">
      <slot />
    </main>

    <footer class="border-t border-gray-200 dark:border-gray-700 py-6 text-center text-sm text-gray-400">
      {{ kbSettings.page_title || t('Help Center') }} &mdash; {{ new Date().getFullYear() }}
    </footer>
  </div>
</template>
