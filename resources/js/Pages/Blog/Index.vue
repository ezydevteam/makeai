<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import AdSection from '@/Components/AdSection.vue'

defineOptions({ layout: UserLayout })

declare const route: (name: string, params?: string | number | Record<string, string | number | undefined>) => string

interface Author { name: string }
interface Taxonomy { id: number; name: string; slug: string; color?: string; posts_count?: number }
interface BlogPost {
    ulid: string
    title: string
    slug: string
    excerpt: string | null
    featured_image: string | null
    featured_image_alt: string | null
    published_at: string | null
    reading_time: number
    views_count: number
    is_featured: boolean
    categories: Taxonomy[]
    author: Author | null
}
interface Paginated<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    posts: Paginated<BlogPost>
    categories: Taxonomy[]
    tags: Taxonomy[]
    filters: { search?: string; sort?: string }
    activeCategory?: Taxonomy
    activeTag?: Taxonomy
    heading: string
    description?: string | null
    meta: { title: string; description: string; no_index?: boolean; rss: string }
}>()

const { t } = useTranslate()
const search = ref(props.filters.search ?? '')
const currentSort = ref(props.filters.sort ?? 'latest')

const sortOptions = computed(() => [
    { value: 'latest', label: t('Latest') },
    { value: 'popular', label: t('Most Popular') },
    { value: 'commented', label: t('Most Commented') },
])

const applyFilters = () => {
    router.get(window.location.pathname, { search: search.value || undefined, sort: currentSort.value === 'latest' ? undefined : currentSort.value }, {
        preserveState: true,
        replace: true,
    })
}

const formatDate = (value: string | null) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}
</script>

<template>
    <Head>
        <title>{{ meta.title }}</title>
        <meta name="description" :content="meta.description" />
        <meta v-if="meta.no_index" name="robots" content="noindex,nofollow" />
        <link rel="alternate" type="application/rss+xml" :href="meta.rss" />
    </Head>

    <div class="min-h-screen bg-emerald-50/50 dark:bg-surface-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
            <div class="mb-8">
                <Link :href="route('home')" class="text-sm text-primary-600 hover:text-primary-700">{{ t('Home') }}</Link>
                <h1 class="mt-3 text-4xl font-bold text-gray-900 dark:text-white">{{ heading }}</h1>
                <p v-if="description" class="mt-3 max-w-2xl text-base text-gray-600 dark:text-gray-400">{{ description }}</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6">
                <main>
                    <div class="mb-5 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-4 flex flex-col md:flex-row gap-3">
                        <label class="sr-only" for="blog-search">{{ t('Search') }}</label>
                        <input id="blog-search" v-model="search" @keyup.enter="applyFilters" type="search" :placeholder="t('Search articles')" class="flex-1 rounded-lg border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 px-4 py-2.5 text-sm text-gray-900 dark:text-white focus:border-primary-400 focus:ring-primary-400">
                        <label class="sr-only" for="blog-sort">{{ t('Sort') }}</label>
                        <AppSelect id="blog-sort" v-model="currentSort" :options="sortOptions" @update:model-value="applyFilters" />
                        <button @click="applyFilters" type="button" class="rounded-lg btn-primary transition-colors">
                            {{ t('Filter') }}
                        </button>
                    </div>

                    <div v-if="posts.data.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                        <template v-for="(post, index) in posts.data" :key="post.ulid">
                            <AdSection v-if="index === 2 || index === 5" zone="between_posts" class="col-span-full" />
                            <Link :href="route('blog.show', post.slug)" class="group bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm overflow-hidden hover:border-primary-200 hover:shadow-md transition-all">
                            <div class="aspect-[16/9] bg-gray-100 dark:bg-surface-800 overflow-hidden">
                                <img v-if="post.featured_image" :src="post.featured_image" :alt="post.featured_image_alt || post.title" class="h-full w-full object-cover group-hover:scale-[1.02] transition-transform duration-200">
                                <div v-else class="h-full w-full bg-gradient-to-br from-primary-100 to-accent-400/20 dark:from-primary-900/30 dark:to-surface-800"></div>
                            </div>
                            <div class="p-5">
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <span v-if="post.is_featured" class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-700">{{ t('Featured') }}</span>
                                    <span v-if="post.categories && post.categories.length > 0" class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700">{{ post.categories[0].name }}</span>
                                </div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">{{ post.title }}</h2>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 line-clamp-3">{{ post.excerpt }}</p>
                                <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ formatDate(post.published_at) }}</span>
                                    <span>{{ post.reading_time }} {{ t('min read') }}</span>
                                </div>
                            </div>
                        </Link>
                        </template>
                    </div>

                    <div v-else class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-12 text-center">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('No posts found') }}</h2>
                        <p class="mt-2 text-sm text-gray-500">{{ t('Try a different search or category.') }}</p>
                    </div>

                    <div v-if="posts.links.length > 3" class="mt-8 flex flex-wrap gap-2">
                        <Link v-for="link in posts.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'btn-primary' : 'bg-white dark:bg-surface-900 text-gray-700 dark:text-gray-300', !link.url ? 'opacity-50 pointer-events-none' : 'hover:border-primary-300']" class="min-w-10 rounded-lg border border-gray-200 dark:border-surface-800 px-3 py-2 text-center text-sm transition-colors" />
                    </div>
                </main>

                <aside class="space-y-5">
                    <AdSection zone="sidebar_top" class="mb-3" />
                    <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ t('Categories') }}</h2>
                        <div class="mt-4 space-y-2">
                            <Link :href="route('blog.index')" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary-50 hover:text-primary-700">
                                <span>{{ t('All Posts') }}</span>
                            </Link>
                            <Link v-for="category in categories" :key="category.id" :href="route('blog.category', category.slug)" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-primary-50 hover:text-primary-700">
                                <span>{{ category.name }}</span>
                                <span class="text-xs text-gray-400">{{ category.posts_count }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-5">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ t('Tags') }}</h2>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link v-for="tag in tags" :key="tag.id" :href="route('blog.tag', tag.slug)" class="rounded-full bg-gray-100 dark:bg-surface-800 px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-primary-100 hover:text-primary-700 transition-colors">
                                {{ tag.name }}
                            </Link>
                        </div>
                    </div>
                    <AdSection zone="sidebar_bottom" class="mt-3" />
                </aside>
            </div>
        </div>
    </div>
</template>
