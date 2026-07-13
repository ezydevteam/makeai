<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
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
  meta: { title: string; description: string }
}>()

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}
function helpfulPercent(a: Article): string | null {
  return a.helpful_count ? `${a.helpful_count} 👍` : null
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

    <div class="flex items-center gap-4">
      <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white shrink-0">
        {{ props.activeCategory ? props.activeCategory.name : t('All Articles') }}
      </h1>
      <div class="flex-grow h-px bg-gray-200 dark:bg-surface-800"></div>
      <span class="shrink-0 text-sm text-gray-400 dark:text-gray-500">{{ props.articles.total }} {{ t('articles') }}</span>
    </div>

    <!-- Category filter chips (Inertia visits — no full page reload) -->
    <div v-if="props.categories.length" class="flex flex-wrap gap-2">
      <Link
        :href="`/${kbSlug}/articles`"
        preserve-scroll
        class="px-3 py-1.5 rounded-full border text-sm font-medium transition-all"
        :class="!props.activeCategory
          ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500/50 dark:bg-primary-950/30 dark:text-primary-300'
          : 'border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 text-gray-600 dark:text-gray-400 hover:border-primary-400'"
      >
        {{ t('All') }}
      </Link>
      <Link
        v-for="cat in props.categories"
        :key="cat.id"
        :href="`/${kbSlug}/articles?category=${cat.slug}`"
        preserve-scroll
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-sm font-medium transition-all"
        :class="props.activeCategory?.slug === cat.slug
          ? 'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500/50 dark:bg-primary-950/30 dark:text-primary-300'
          : 'border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-900 text-gray-600 dark:text-gray-400 hover:border-primary-400'"
      >
        <i v-if="cat.icon" :class="cat.icon"></i>
        {{ cat.name }}
        <span class="text-xs opacity-60">{{ cat.articles_count }}</span>
      </Link>
    </div>

    <!-- Empty state -->
    <div v-if="!props.articles.data.length" class="rounded-2xl border border-dashed border-gray-200 dark:border-surface-800 p-12 text-center">
      <i class="ti ti-file-search text-3xl text-gray-300 dark:text-gray-600"></i>
      <p class="mt-2 text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('No articles found.') }}</p>
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
            <i class="ti ti-eye"></i><span>{{ a.views }}</span>
          </span>
        </div>

        <h3 class="text-base font-extrabold text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors leading-snug line-clamp-2 mb-2">
          {{ a.title }}
        </h3>
        <p class="text-xs text-gray-400 dark:text-gray-500 leading-relaxed line-clamp-3 mb-4">{{ a.excerpt }}</p>

        <div class="flex items-center justify-between text-[11px] pt-3 border-t border-gray-100 dark:border-surface-850">
          <span class="text-gray-400 font-medium">{{ formatDate(a.published_at) }}</span>
          <span v-if="helpfulPercent(a)" class="inline-flex items-center gap-1 font-semibold text-primary-600 dark:text-primary-400">{{ helpfulPercent(a) }}</span>
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
