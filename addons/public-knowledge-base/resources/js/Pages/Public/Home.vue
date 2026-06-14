<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import KbLayout from './KbLayout.vue'

defineOptions({ layout: KbLayout })

const { t } = useTranslate()
const page = usePage()
const kbSettings = computed(() => (page.props as any).kbSettings || {})
const kbSlug = computed(() => kbSettings.value.public_slug || 'help')

interface Category {
  id: number
  slug: string
  name: string
  icon: string
  description: string | null
  articles_count: number
}

interface Article {
  ulid: string
  title: string
  slug: string
  excerpt: string | null
  views: number
  helpful_count: number
  published_at: string
  category?: { name: string } | null
}

const props = defineProps<{
  categories: Category[]
  featuredArticles: Article[]
  meta: { title: string; description: string }
}>()

const searchQuery = ref('')
const streaming = ref(false)
const answer = ref('')
const sources = ref<any[]>([])
const searchDone = ref(false)
const abort = ref<AbortController | null>(null)

async function doSearch() {
  if (searchQuery.value.length < 2) return
  if (abort.value) abort.value.abort()
  abort.value = new AbortController()

  streaming.value = true
  answer.value = ''
  sources.value = []
  searchDone.value = false

  try {
    const res = await fetch(`/${kbSlug.value}/search`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content },
      body: JSON.stringify({ query: searchQuery.value }),
      signal: abort.value.signal,
    })

    const reader = res.body!.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (true) {
      const { done, value } = await reader.read()
      if (done) break
      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop()!
      for (const line of lines) {
        if (!line.trim()) continue
        try {
          const event = JSON.parse(line)
          if (event.type === 'sources') {
            sources.value = event.articles || []
          } else if (event.type === 'delta') {
            answer.value += event.text
          } else if (event.type === 'done') {
            searchDone.value = true
          }
        } catch {}
      }
    }
  } catch {
    answer.value = t('Search unavailable. Please try again.')
    searchDone.value = true
  } finally {
    streaming.value = false
  }
}

function helpfulPercent(a: Article): string | null {
  if (!a.helpful_count) return null
  return `${a.helpful_count} +`
}
</script>

<template>
  <div>
    <section class="text-center py-12">
      <h1 class="text-3xl font-bold mb-3">{{ props.meta.title || t('Help Center') }}</h1>
      <p v-if="props.meta.description" class="text-gray-500 mb-6">{{ props.meta.description }}</p>
      <div class="max-w-xl mx-auto">
        <div class="relative">
          <input
            v-model="searchQuery"
            @keydown.enter="doSearch"
            type="text"
            :placeholder="t('Search the help center...')"
            class="input w-full text-lg py-3 px-5 rounded-full shadow"
          />
          <button @click="doSearch" class="absolute right-1 top-1 bottom-1 btn btn-primary rounded-full px-5">
            {{ t('Search') }}
          </button>
        </div>

        <div v-if="streaming || sources.length" class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-5 text-left max-h-96 overflow-y-auto">
          <div v-if="sources.length" class="grid gap-2 mb-4">
            <a
              v-for="s in sources"
              :key="s.ulid"
              :href="`/${kbSlug}/article/${s.slug}`"
              class="block p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-emerald-400 transition"
            >
              <p class="font-medium text-sm">{{ s.title }}</p>
              <p class="text-xs text-gray-500">{{ s.excerpt }}</p>
            </a>
          </div>
          <div v-if="answer" class="text-sm whitespace-pre-wrap leading-relaxed">{{ answer }}</div>
          <div v-if="streaming && !answer" class="flex gap-2 items-center text-sm text-gray-400">
            <span class="animate-spin inline-block w-4 h-4 border-2 border-emerald-500 border-t-transparent rounded-full" />
            {{ t('Searching...') }}
          </div>
          <div v-if="streaming && answer" class="inline-block w-2 h-4 bg-emerald-500 ml-0.5 animate-pulse" />
        </div>
      </div>
    </section>

    <section v-if="props.categories.length" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-10">
      <a
        v-for="cat in props.categories"
        :key="cat.id"
        :href="`/${kbSlug}?category=${cat.slug}`"
        class="card hover:shadow-md transition p-5 flex gap-4 items-start"
      >
        <i v-if="cat.icon" :class="cat.icon" class="text-2xl text-emerald-500 mt-1" />
        <div>
          <h3 class="font-semibold mb-1">{{ cat.name }}</h3>
          <p class="text-sm text-gray-500 line-clamp-2">{{ cat.description }}</p>
          <p class="text-xs text-gray-400 mt-2">{{ cat.articles_count }} {{ t('articles') }}</p>
        </div>
      </a>
    </section>

    <section v-if="props.featuredArticles.length">
      <h2 class="text-xl font-bold mb-4">{{ t('Top Articles') }}</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <a
          v-for="a in props.featuredArticles"
          :key="a.ulid"
          :href="`/${kbSlug}/article/${a.slug}`"
          class="card hover:shadow-md transition p-5"
        >
          <p class="font-semibold mb-1">{{ a.title }}</p>
          <p class="text-sm text-gray-500 line-clamp-2">{{ a.excerpt }}</p>
          <div class="flex items-center justify-between mt-3 text-xs text-gray-400">
            <span>{{ new Date(a.published_at).toLocaleDateString() }}</span>
            <span>{{ a.views }} {{ t('views') }}</span>
          </div>
        </a>
      </div>
    </section>
  </div>
</template>
