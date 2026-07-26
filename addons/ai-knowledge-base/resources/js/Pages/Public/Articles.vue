<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import KbLayout from './KbLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'

defineOptions({ layout: KbLayout })

const { t } = useTranslate()
const page = usePage()
const kbSlug = computed(() => ((page.props as any).kbSettings?.public_slug) || 'help')

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
interface Category { id: number; slug: string; name: string; icon: string | null; articles_count: number }
interface Paginator<T> {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  total: number
}

const props = defineProps<{
  articles: Paginator<Article>
  categories: Category[]
  activeCategory: Category | null
  totalArticles: number
  filters: { q: string }
  meta: { title: string; description: string }
}>()

const search = ref(props.filters.q ?? '')
let searchTimer: ReturnType<typeof setTimeout> | null = null

// Debounced so a typed word is one request, not one per keystroke. `replace` keeps the
// back button pointing at the page the visitor arrived from rather than every keystroke.
watch(search, (value) => {
  if (searchTimer) clearTimeout(searchTimer)

  searchTimer = setTimeout(() => {
    router.get(
      `/${kbSlug.value}/articles`,
      { category: props.activeCategory?.slug || undefined, q: value.trim() || undefined },
      { preserveState: true, preserveScroll: true, replace: true },
    )
  }, 350)
})

onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })

// Category links carry the current search, so narrowing by topic does not silently drop it.
function categoryHref(slug?: string): string {
  const query = new URLSearchParams()
  if (slug) query.set('category', slug)
  if (search.value.trim()) query.set('q', search.value.trim())
  const qs = query.toString()

  return `/${kbSlug.value}/articles${qs ? `?${qs}` : ''}`
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}
// Same shape as the homepage's: the bare count, with the thumb rendered as an icon in the
// template rather than an emoji in the string. An emoji picks up the OS font, so it sat at
// a different size and colour from every other icon on the card and ignored the theme.
function helpfulPercent(a: Article): string | null {
  if (!a.helpful_count) return null
  return `${a.helpful_count} +`
}
</script>

<template>
  <Head :title="props.meta.title">
    <meta name="description" :content="props.meta.description || ''" head-key="description" />
    <meta property="og:title" :content="props.meta.title" head-key="ogtitle" />
    <meta property="og:type" content="website" head-key="ogtype" />
  </Head>

  <div class="max-w-6xl mx-auto space-y-8">
    <!-- Breadcrumbs -->
    <nav class="flex items-center flex-wrap gap-2 text-xs tracking-wider text-gray-400 dark:text-gray-500">
      <a :href="`/${kbSlug}`" class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">{{ t('Help Center') }}</a>
      <i class="ti ti-chevron-right text-[10px]"></i>
      <span class="text-gray-600 dark:text-gray-300">{{ props.activeCategory ? props.activeCategory.name : t('All Articles') }}</span>
    </nav>

    <div class="flex flex-wrap items-center gap-4">
      <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white shrink-0">
        {{ props.activeCategory ? props.activeCategory.name : t('All Articles') }}
      </h1>
      <div class="hidden sm:block flex-grow h-px bg-gray-200 dark:bg-surface-800"></div>

      <!-- The count moved onto the chips below, where each one states its own total. This
           slot is more useful as the filter for the list underneath it. -->
      <div class="relative w-full sm:w-64 shrink-0">
        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
        <input
          v-model="search"
          type="text"
          :placeholder="t('Search articles...')"
          class="w-full !rounded-full border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 py-2 pl-9 pr-9 text-sm text-gray-700 dark:text-gray-200 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 transition-all"
        />
        <button
          v-if="search"
          type="button"
          class="absolute right-2 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
          :aria-label="t('Clear search')"
          @click="search = ''"
        >
          <i class="ti ti-x text-sm"></i>
        </button>
      </div>
    </div>

    <!-- Category filter chips (Inertia visits — no full page reload). Styled like the
         home page's pills, each carrying its own count in a circular badge. -->
    <div v-if="props.categories.length" class="flex flex-wrap gap-2">
      <Link
        :href="categoryHref()"
        preserve-scroll
        class="group inline-flex items-center gap-2 rounded-full border bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md dark:bg-surface-900"
        :class="!props.activeCategory
          ? 'border-primary-400 text-primary-700 dark:border-primary-500/50 dark:text-primary-300'
          : 'border-gray-200 text-gray-600 hover:border-primary-400 hover:text-primary-600 dark:border-surface-850 dark:text-gray-400 dark:hover:border-primary-500/50 dark:hover:text-primary-400'"
      >
        {{ t('All') }}
        <!-- Same pill as the homepage category cards: primary-tinted at rest, solid
             primary on hover. Circular here because it holds a bare number. -->
        <span
          class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold transition-colors duration-300"
          :class="!props.activeCategory
            ? 'bg-primary-600 text-white'
            : 'bg-primary-50/70 text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-950/30 dark:text-primary-400'"
        >
          {{ props.totalArticles }}
        </span>
      </Link>
      <Link
        v-for="cat in props.categories"
        :key="cat.id"
        :href="categoryHref(cat.slug)"
        preserve-scroll
        class="group inline-flex items-center gap-1.5 rounded-full border bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md dark:bg-surface-900"
        :class="props.activeCategory?.slug === cat.slug
          ? 'border-primary-400 text-primary-700 dark:border-primary-500/50 dark:text-primary-300'
          : 'border-gray-200 text-gray-600 hover:border-primary-400 hover:text-primary-600 dark:border-surface-850 dark:text-gray-400 dark:hover:border-primary-500/50 dark:hover:text-primary-400'"
      >
        <i v-if="cat.icon" :class="cat.icon"></i>
        {{ cat.name }}
        <span
          class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-bold transition-colors duration-300"
          :class="props.activeCategory?.slug === cat.slug
            ? 'bg-primary-600 text-white'
            : 'bg-primary-50/70 text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-950/30 dark:text-primary-400'"
        >
          {{ cat.articles_count }}
        </span>
      </Link>
    </div>

    <!-- Empty state -->
    <div v-if="!props.articles.data.length" class="rounded-2xl border border-dashed border-gray-200 dark:border-surface-800 p-12 text-center">
      <i class="ti ti-file-search text-3xl text-gray-300 dark:text-gray-600"></i>
      <p class="mt-2 text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('No articles found.') }}</p>
      <!-- With a filter applied, "nothing here" is a dead end unless the way back is offered. -->
      <button
        v-if="search"
        type="button"
        class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:border-primary-400 hover:text-primary-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:border-primary-500/50 dark:hover:text-primary-400"
        @click="search = ''"
      >
        <i class="ti ti-x text-sm"></i>
        {{ t('Clear search') }}
      </button>
    </div>

    <!-- Articles grid -->
    <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <a
        v-for="a in props.articles.data"
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
      v-if="props.articles.last_page > 1"
      :links="props.articles.links"
      :from="props.articles.from"
      :to="props.articles.to"
      :total="props.articles.total"
      :current-page="props.articles.current_page"
      :last-page="props.articles.last_page"
    />
  </div>
</template>
