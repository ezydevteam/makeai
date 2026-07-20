<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { Head, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import DOMPurify from 'dompurify'
import KbLayout from './KbLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation as SwiperNavigation, Pagination as SwiperPagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

interface Paginator<T> {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  total: number
}

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
  featuredArticles: Paginator<Article>
  meta: { title: string; description: string }
  activeCategory: Category | null
  categoryArticles: Article[]
}>()

const searchQuery = ref('')
const streaming = ref(false)
const answer = ref('')
// Sanitize the streamed AI answer before it reaches v-html.
const safeAnswer = computed(() => DOMPurify.sanitize(answer.value, {
    USE_PROFILES: { html: true },
    FORBID_TAGS: ['style', 'form', 'input', 'button'],
    FORBID_ATTR: ['style'],
}))
const sources = ref<any[]>([])
const searchDone = ref(false)
const abort = ref<AbortController | null>(null)

const hasSearchResults = computed(() => sources.value.length > 0 || Boolean(answer.value))

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

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

function clearSearch() {
  searchQuery.value = ''
  answer.value = ''
  sources.value = []
  searchDone.value = false
  streaming.value = false
  if (abort.value) {
    abort.value.abort()
    abort.value = null
  }
}

function helpfulPercent(a: Article): string | null {
  if (!a.helpful_count) return null
  return `${a.helpful_count} +`
}

onMounted(() => {
  const queryParam = new URLSearchParams(window.location.search).get('q')
  if (queryParam) {
    searchQuery.value = queryParam
    doSearch()
  }
})
</script>

<template>
  <Head :title="props.meta.title">
    <meta name="description" :content="props.meta.description || ''" head-key="description" />
    <meta property="og:title" :content="props.meta.title" head-key="ogtitle" />
    <meta property="og:description" :content="props.meta.description || ''" head-key="ogdescription" />
    <meta property="og:type" content="website" head-key="ogtype" />
  </Head>

  <div class="pb-12">
    <!-- Dedicated Category Page (OpenAI Style) -->
    <div v-if="props.activeCategory" class="max-w-4xl mx-auto space-y-8">
      <!-- Breadcrumbs & Back -->
      <nav class="flex items-center gap-2 text-xs tracking-wider text-gray-400 dark:text-gray-500">
        <a :href="`/${kbSlug}`" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ t('Help Center') }}</a>
        <i class="ti ti-chevron-right text-[10px]"></i>
        <span class="text-gray-600 dark:text-gray-300 font-semibold">{{ props.activeCategory.name }}</span>
      </nav>

      <!-- Category Hero Header -->
      <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-850 rounded-3xl p-6 sm:p-8 shadow-sm flex flex-col sm:flex-row items-center sm:items-start gap-5">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-primary-50 to-indigo-50/50 dark:from-primary-950/20 dark:to-surface-800 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
          <i :class="props.activeCategory.icon || 'ti ti-category'" class="text-3xl"></i>
        </div>
        <div class="text-center sm:text-left space-y-1 flex-grow">
          <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ props.activeCategory.name }}
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ props.activeCategory.description || t('All resources and support articles related to this topic.') }}
          </p>
          <div class="pt-2 flex items-center justify-center sm:justify-start gap-1.5 text-xs text-gray-400 dark:text-gray-500 font-semibold">
            <i class="ti ti-files"></i>
            <span>{{ props.categoryArticles.length }} {{ t('articles') }}</span>
          </div>
        </div>
      </div>

      <!-- Articles List -->
      <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-850 rounded-3xl overflow-hidden shadow-sm divide-y divide-gray-100 dark:divide-surface-850">
        <div v-if="!props.categoryArticles.length" class="p-8 text-center text-gray-400 dark:text-gray-500">
          <i class="ti ti-info-circle text-2xl mb-2 text-gray-400"></i>
          <p class="text-sm font-medium">{{ t('No articles in this category yet.') }}</p>
        </div>
        <a
          v-else
          v-for="art in props.categoryArticles"
          :key="art.ulid"
          :href="`/${kbSlug}/article/${art.slug}`"
          class="group flex items-center justify-between p-6 hover:bg-gray-50/50 dark:hover:bg-surface-900/50 transition-colors"
        >
          <div class="min-w-0 flex-1 pr-6 space-y-1">
            <h3 class="text-base font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
              {{ art.title }}
            </h3>
            <p v-if="art.excerpt" class="text-xs text-gray-400 dark:text-gray-500 line-clamp-2 leading-relaxed">
              {{ art.excerpt }}
            </p>
          </div>
          <span class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-surface-800 text-gray-400 dark:text-gray-500 flex items-center justify-center group-hover:bg-primary-50 dark:group-hover:bg-primary-950/30 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-all shrink-0">
            <i class="ti ti-chevron-right text-sm"></i>
          </span>
        </a>
      </div>
    </div>

    <!-- Default Help Center Home Page -->
    <div v-else class="space-y-16">
      <!-- Hero Banner & Search Section -->
      <section class="kb-hero relative overflow-hidden rounded-3xl px-6 py-12 sm:px-12 sm:py-16 bg-gradient-to-br from-white via-primary-50/20 to-indigo-50/20 dark:from-surface-900 dark:via-primary-950/5 dark:to-indigo-950/5 border border-gray-200 dark:border-surface-850 shadow-sm">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary-500/5 dark:bg-primary-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-indigo-500/5 dark:bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl mx-auto text-center">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-primary-50 dark:bg-primary-950/30 text-primary-700 dark:text-primary-300 border border-primary-200/30">
            <i class="ti ti-lifebuoy"></i>
            {{ t('Help Center') }}
          </span>

          <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
            {{ t('Hi, how can we help you?') }}
          </h1>

          <!-- Search Input Box -->
          <div class="mt-8 max-w-2xl mx-auto">
            <div class="flex flex-col sm:flex-row gap-2.5 p-2 bg-white dark:bg-surface-850 rounded-full border border-gray-200 dark:border-surface-700 shadow-md">
              <div class="relative flex-1">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-lg text-gray-400"></i>
                <input
                  v-model="searchQuery"
                  @keydown.enter="doSearch"
                  type="text"
                  :placeholder="t('Search articles, guides, questions...')"
                  class="w-full pl-11 pr-10 py-3 bg-transparent text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none text-sm"
                />
                <button
                  v-if="searchQuery"
                  @click="clearSearch"
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 w-6 h-6 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-surface-800 transition-all cursor-pointer"
                  :title="t('Clear search')"
                >
                  <i class="ti ti-x text-sm"></i>
                </button>
              </div>
              <button
                @click="doSearch"
                type="button"
                class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 active:scale-98 text-white rounded-full text-sm font-semibold flex items-center justify-center gap-2 shadow-lg shadow-primary-500/10 transition-all cursor-pointer"
              >
                <span>{{ t('Search') }}</span>
                <i class="ti ti-arrow-right"></i>
              </button>
            </div>

            <!-- AI Search Results Panel -->
            <div v-if="streaming || hasSearchResults || searchDone" class="mt-6 text-left bg-white dark:bg-surface-850 border border-gray-200 dark:border-surface-750 rounded-2xl p-6 shadow-xl space-y-4">
              <div v-if="sources.length" class="grid gap-3 sm:grid-cols-2">
                <a
                  v-for="s in sources"
                  :key="s.ulid"
                  :href="`/${kbSlug}/article/${s.slug}`"
                  class="block p-4 rounded-xl border border-gray-100 dark:border-surface-750 hover:border-primary-400 dark:hover:border-primary-500/50 bg-gray-50/50 dark:bg-surface-900/30 hover:bg-white dark:hover:bg-surface-900/60 transition-all group"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-sm font-bold text-gray-900 dark:text-white line-clamp-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ s.title }}</p>
                      <p class="mt-1 line-clamp-2 text-xs text-gray-400 dark:text-gray-500 leading-normal">{{ s.excerpt }}</p>
                    </div>
                    <span class="w-7 h-7 rounded-lg bg-gray-100 dark:bg-surface-800 text-gray-500 flex items-center justify-center shrink-0 group-hover:bg-primary-50 dark:group-hover:bg-primary-950/30 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                      <i class="ti ti-arrow-up-right text-sm"></i>
                    </span>
                  </div>
                </a>
              </div>

              <!-- Dynamic Answer from AI -->
              <div v-if="answer" class="pt-2 border-t border-gray-100 dark:border-surface-750">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-1.5">
                  <i class="ti ti-sparkles text-primary-500"></i>
                  <span>{{ t('AI-Generated Answer') }}</span>
                </p>
                <div class="prose prose-sm dark:prose-invert prose-p:leading-relaxed prose-pre:bg-gray-50 dark:prose-pre:bg-surface-900 max-w-none text-gray-700 dark:text-gray-300" v-html="safeAnswer"></div>
              </div>

              <div v-if="streaming" class="flex items-center justify-center gap-2 py-4 text-xs font-bold text-primary-600 dark:text-primary-400">
                <span class="kb-pulse-bar"></span>
                <span>{{ t('Generating full response...') }}</span>
              </div>

              <p v-if="searchDone && !streaming && !answer && !sources.length" class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                {{ t('No matching help articles were found. Try another keyword.') }}
              </p>
            </div>

            <!-- Suggested Quick Links -->
            <div v-if="props.featuredArticles.data.length && !hasSearchResults && !streaming" class="mt-5 flex flex-wrap items-center justify-center gap-2 text-xs">
              <span class="text-gray-400 dark:text-gray-500 font-medium">{{ t('Popular searches') }}:</span>
              <a
                v-for="article in props.featuredArticles.data.slice(0, 3)"
                :key="article.ulid"
                :href="`/${kbSlug}/article/${article.slug}`"
                class="px-3 py-1.5 rounded-full border border-gray-200 dark:border-surface-700 hover:border-primary-400 dark:hover:border-primary-500/50 bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all font-medium"
              >
                {{ article.title }}
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- Categories Grid (Browse Topics) -->
      <section v-if="props.categories.length" class="space-y-6">
        <div class="flex items-center gap-4">
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white shrink-0">{{ t('Explore by Category') }}</h2>
          <div class="flex-grow h-px bg-gray-200 dark:bg-surface-800"></div>

          <!-- Swiper hides these automatically (swiper-button-lock) when every
               category already fits on screen, so there is nothing to scroll. -->
          <div class="flex items-center gap-2 shrink-0">
            <button type="button" class="kb-cat-nav kb-cat-prev" :aria-label="t('Previous categories')">
              <i class="ti ti-chevron-left"></i>
            </button>
            <button type="button" class="kb-cat-nav kb-cat-next" :aria-label="t('Next categories')">
              <i class="ti ti-chevron-right"></i>
            </button>
          </div>
        </div>

        <Swiper
          :modules="[SwiperNavigation, SwiperPagination]"
          :slides-per-view="1.15"
          :space-between="20"
          :breakpoints="{ 640: { slidesPerView: 2 }, 768: { slidesPerView: 3 }, 1024: { slidesPerView: 4 } }"
          :navigation="{ prevEl: '.kb-cat-prev', nextEl: '.kb-cat-next' }"
          :pagination="{ clickable: true }"
          class="kb-category-swiper !px-2 !pt-3 !pb-12"
        >
          <SwiperSlide v-for="cat in props.categories" :key="cat.id" class="!h-auto">
            <a
              :href="`/${kbSlug}?category=${cat.slug}`"
              class="group relative flex h-full flex-col p-6 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-850 hover:border-primary-400 dark:hover:border-primary-500/50 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-1 text-center"
            >
              <!-- Article Count Pill -->
              <span class="absolute top-3 right-3 px-2 py-0.5 rounded-full bg-primary-50/70 dark:bg-primary-950/30 text-[10px] font-bold text-primary-700 dark:text-primary-400 group-hover:bg-primary-600 group-hover:text-white transition-colors duration-300">
                {{ cat.articles_count }} {{ t('articles') }}
              </span>

              <!-- Centered Icon -->
              <div class="mx-auto w-14 h-14 rounded-2xl bg-gradient-to-tr from-primary-50 to-primary-100/50 dark:from-primary-950/20 dark:to-surface-800 text-primary-600 dark:text-primary-400 flex items-center justify-center group-hover:scale-110 group-hover:from-primary-600 group-hover:to-primary-500 group-hover:text-white transition-all duration-300 shadow-sm">
                <i :class="cat.icon || 'ti ti-category'" class="text-2xl"></i>
              </div>

              <h3 class="mt-4 text-base font-bold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors leading-tight">
                {{ cat.name }}
              </h3>

              <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed">
                {{ cat.description || t('View guides and resources.') }}
              </p>
            </a>
          </SwiperSlide>
        </Swiper>
      </section>

      <!-- Popular Articles -->
      <section v-if="props.featuredArticles.data.length" class="space-y-6">
        <div class="flex items-center gap-4">
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white shrink-0">{{ t('Popular Articles') }}</h2>
          <div class="flex-grow h-px bg-gray-200 dark:bg-surface-800"></div>
          <a :href="`/${kbSlug}/articles`" class="shrink-0 text-sm font-semibold text-primary-600 hover:text-primary-700 dark:text-primary-400 transition-colors">{{ t('View all') }}</a>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <a
            v-for="a in props.featuredArticles.data"
            :key="a.ulid"
            :href="`/${kbSlug}/article/${a.slug}`"
            class="group block p-6 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-850 hover:border-primary-300 dark:hover:border-primary-500/40 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 hover:-translate-y-0.5"
          >
            <div class="flex items-center justify-between gap-3 mb-4">
              <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold tracking-tight bg-primary-50/50 dark:bg-primary-950/20 text-primary-700 dark:text-primary-300">
                {{ a.category?.name || t('Help Guide') }}
              </span>
              <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 font-bold uppercase tracking-wider bg-gray-50 dark:bg-surface-800 px-2 py-1 rounded-md">
                <i class="ti ti-eye"></i>
                <span>{{ a.views }}</span>
              </span>
            </div>

            <h3 class="text-base font-extrabold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors leading-snug line-clamp-2 mb-2">
              {{ a.title }}
            </h3>

            <p class="text-xs text-gray-400 dark:text-gray-500 leading-relaxed line-clamp-3 mb-4">
              {{ a.excerpt }}
            </p>

            <div class="flex items-center justify-between text-[11px] pt-3 border-t border-gray-100 dark:border-surface-850">
              <span class="text-gray-400 font-medium">{{ formatDate(a.published_at) }}</span>
              <span v-if="helpfulPercent(a)" class="inline-flex items-center gap-1 font-semibold text-primary-600 dark:text-primary-400">
                <i class="ti ti-thumb-up"></i>
                <span>{{ helpfulPercent(a) }}</span>
              </span>
            </div>
          </a>
        </div>

        <Pagination
          v-if="props.featuredArticles.last_page > 1"
          :links="props.featuredArticles.links"
          :from="props.featuredArticles.from"
          :to="props.featuredArticles.to"
          :total="props.featuredArticles.total"
          :current-page="props.featuredArticles.current_page"
          :last-page="props.featuredArticles.last_page"
        />
      </section>
    </div>
  </div>
</template>

<style scoped>
.kb-spinner {
  display: inline-block;
  height: 1.1rem;
  width: 1.1rem;
  border-radius: 50%;
  border: 2px solid color-mix(in srgb, var(--color-primary-500) 20%, transparent);
  border-top-color: var(--color-primary-500);
  animation: kb-spin 0.8s linear infinite;
}

.kb-pulse-bar {
  display: inline-block;
  height: 0.9rem;
  width: 0.18rem;
  border-radius: 99px;
  background: var(--color-primary-500);
  animation: kb-pulse 0.9s ease-in-out infinite;
}

@keyframes kb-spin {
  to { transform: rotate(360deg); }
}

@keyframes kb-pulse {
  0%, 100% { opacity: 0.35; }
  50% { opacity: 1; }
}

.kb-category-swiper :deep(.swiper-pagination-bullet) {
  background: color-mix(in srgb, var(--color-primary-500) 40%, transparent);
  opacity: 1;
}
.kb-category-swiper :deep(.swiper-pagination-bullet-active) {
  background: var(--color-primary-500);
}

.kb-cat-nav {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 2.25rem;
  width: 2.25rem;
  border-radius: 9999px;
  border: 1px solid color-mix(in srgb, var(--color-primary-500) 22%, transparent);
  background: color-mix(in srgb, var(--color-primary-500) 8%, transparent);
  color: var(--color-primary-500);
  font-size: 1.05rem;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s, border-color 0.2s, opacity 0.2s;
}

.kb-cat-nav:hover:not(.swiper-button-disabled) {
  background: var(--color-primary-500);
  border-color: var(--color-primary-500);
  color: #fff;
}

/* Swiper adds this class at the first/last slide. */
.kb-cat-nav.swiper-button-disabled {
  opacity: 0.35;
  cursor: default;
  pointer-events: none;
}
</style>
