<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import AppSidebar from '@themes/default/js/Components/AppSidebar.vue'
import { mediaUrl } from '@/lib/media'

defineOptions({ layout: UserLayout })

declare const route: (name: string, params?: string | number | Record<string, string | number | undefined>) => string

interface Author { name: string }
interface Taxonomy { id: number; name: string; slug: string; icon?: string | null; color?: string | null; posts_count?: number }
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
    from?: number | null
    to?: number | null
    total?: number | null
    current_page?: number | null
    last_page?: number | null
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
    blogSettings: {
        sidebar_position: 'none' | 'left' | 'right'
        show_reading_time_archive: boolean
        show_view_count_archive: boolean
        sidebar_post_position: 'none' | 'left' | 'right'
        post_layout_width: 'default' | 'boxed'
        post_layout_centered: boolean
        show_reading_time_post: boolean
        show_view_count_post: boolean
        show_published_date_post: boolean
        show_related_posts_post: boolean
        show_comments_post: boolean
        show_post_author_post: boolean
        show_tags_post: boolean
        post_social_share_position: 'hide' | 'top' | 'bottom' | 'both'
    }
}>()

const { t } = useTranslate()
const search = ref(typeof props.filters.search === 'string' ? props.filters.search : '')
const currentSort = ref(typeof props.filters.sort === 'string' ? props.filters.sort : 'latest')
const isLoading = ref(false)

const sortOptions = computed(() => [
    { value: 'latest', label: t('Latest') },
    { value: 'popular', label: t('Most Popular') },
    { value: 'commented', label: t('Most Commented') },
])

const applyFilters = () => {
    router.get(window.location.pathname, { search: search.value || undefined, sort: currentSort.value === 'latest' ? undefined : currentSort.value }, {
        preserveState: true,
        replace: true,
        onStart: () => {
            isLoading.value = true
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
}

let searchTimeout: any = null
watch(search, () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 400)
})

const formatDate = (value: string | null) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}
</script>

<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description" />
        <meta v-if="meta.no_index" name="robots" content="noindex,nofollow" />
        <link rel="alternate" type="application/rss+xml" :href="meta.rss" />
    </Head>

    <div class="min-h-screen bg-gray-50 dark:bg-surface-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
            <div class="mb-8">
                <!-- Breadcrumbs -->
                <div class="mb-6 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <Link :href="route('home')" class="hover:text-primary-600">{{ t('Home') }}</Link>
                    <span><i class="ti ti-chevron-right text-xs"></i></span>

                    <template v-if="activeCategory">
                        <Link :href="route('blog.index')" class="hover:text-primary-600">{{ t('Blog') }}</Link>
                        <span><i class="ti ti-chevron-right text-xs"></i></span>
                        <span class="text-gray-900 dark:text-gray-300 font-medium">{{ activeCategory.name }}</span>
                    </template>
                    <template v-else-if="activeTag">
                        <Link :href="route('blog.index')" class="hover:text-primary-600">{{ t('Blog') }}</Link>
                        <span><i class="ti ti-chevron-right text-xs"></i></span>
                        <span class="text-gray-900 dark:text-gray-300 font-medium">{{ activeTag.name }}</span>
                    </template>
                    <template v-else>
                        <span class="text-gray-900 dark:text-gray-300 font-medium">{{ t('Blog') }}</span>
                    </template>
                </div>

                <h1 class="text-4xl font-bold text-gray-900 dark:text-white">{{ heading }}</h1>
                <p v-if="description" class="mt-3 max-w-2xl text-base text-gray-600 dark:text-gray-400">{{ description }}</p>
            </div>

            <div :class="[
                blogSettings.sidebar_position !== 'none'
                    ? (blogSettings.sidebar_position === 'left' ? 'grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6' : 'grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6')
                    : 'max-w-4xl mx-auto'
            ]">
                <main :class="[blogSettings.sidebar_position !== 'none' ? 'w-full' : 'max-w-4xl mx-auto w-full']">
                    <!-- Search & Filter Wrapper (no card) -->
                    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="relative flex-1 sm:max-w-sm">
                            <label class="sr-only" for="blog-search">{{ t('Search') }}</label>
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 pointer-events-none">
                                <i v-if="isLoading" class="ti ti-loader animate-spin text-primary-500 text-base"></i>
                                <i v-else class="ti ti-search text-base"></i>
                            </span>
                            <input
                                id="blog-search"
                                v-model="search"
                                @keyup.enter="applyFilters"
                                @search="applyFilters"
                                type="search"
                                :placeholder="t('Search articles...')"
                                class="w-full rounded-lg border border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-400 focus:ring-primary-400"
                            >
                        </div>
                        <div class="w-full sm:w-48 shrink-0">
                            <label class="sr-only" for="blog-sort">{{ t('Sort') }}</label>
                            <AppSelect
                                id="blog-sort"
                                v-model="currentSort"
                                :options="sortOptions"
                                class="w-full"
                                @update:model-value="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- Loading Skeleton Grid -->
                    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                        <div v-for="n in 6" :key="'skeleton-post-'+n" class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm overflow-hidden">
                            <div class="aspect-[16/9] w-full shimmer"></div>
                            <div class="p-5 space-y-4">
                                <div class="flex gap-2">
                                    <div class="h-5 w-16 shimmer rounded-full"></div>
                                    <div class="h-5 w-20 shimmer rounded-full"></div>
                                </div>
                                <div class="space-y-2">
                                    <div class="h-5 w-5/6 shimmer rounded"></div>
                                    <div class="h-4 w-full shimmer rounded"></div>
                                    <div class="h-4 w-2/3 shimmer rounded"></div>
                                </div>
                                <div class="pt-4 flex justify-between items-center border-t border-gray-100 dark:border-surface-800">
                                    <div class="h-3 w-24 shimmer rounded"></div>
                                    <div class="h-3 w-16 shimmer rounded"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template v-else>
                        <div v-if="posts.data.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                            <template v-for="(post, index) in posts.data" :key="post.ulid">
                                <Link :href="route('blog.show', post.slug)" class="group bg-white dark:bg-surface-900 border border-gray-100 dark:border-surface-800 rounded-xl shadow-sm overflow-hidden hover:!border-primary-200 dark:hover:!border-primary-900/60 hover:shadow-md transition-all">
                                    <div class="aspect-[16/9] bg-gray-100 dark:bg-surface-800 overflow-hidden">
                                        <img v-if="post.featured_image" :src="mediaUrl(post.featured_image)" :alt="post.featured_image_alt || post.title" class="h-full w-full object-cover group-hover:scale-[1.02] transition-transform duration-200">
                                        <div v-else class="h-full w-full bg-gradient-to-br from-primary-100 to-accent-400/20 dark:from-primary-900/30 dark:to-surface-800"></div>
                                    </div>
                                    <div class="p-5">
                                        <div class="flex flex-wrap gap-2 mb-3">
                                            <span v-if="post.is_featured" class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-700">{{ t('Featured') }}</span>
                                            <span
                                                v-if="post.categories && post.categories.length > 0"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium transition-colors"
                                                :class="!post.categories[0].color ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : ''"
                                                :style="post.categories[0].color ? {
                                                    color: post.categories[0].color,
                                                    backgroundColor: post.categories[0].color + '10',
                                                    border: '1px solid ' + post.categories[0].color + '20'
                                                } : {}"
                                            >
                                                <i v-if="post.categories[0].icon" :class="[post.categories[0].icon, 'text-xs']"></i>
                                                <span>{{ post.categories[0].name }}</span>
                                            </span>
                                        </div>
                                        <h2 class="text-lg font-bold text-gray-900 dark:text-white group-hover:!text-primary-600 dark:group-hover:!text-primary-400 transition-colors">{{ post.title }}</h2>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 line-clamp-3">{{ post.excerpt }}</p>
                                        <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                                            <span class="flex items-center gap-1">
                                                <i class="ti ti-calendar text-xs"></i>
                                                {{ formatDate(post.published_at) }}
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <span v-if="blogSettings.show_reading_time_archive" class="flex items-center gap-1">
                                                    <i class="ti ti-clock text-xs"></i>
                                                    {{ post.reading_time }} {{ t('min read') }}
                                                </span>
                                                <span v-if="blogSettings.show_reading_time_archive && blogSettings.show_view_count_archive && post.views_count" class="text-gray-300 dark:text-surface-700">•</span>
                                                <span v-if="blogSettings.show_view_count_archive && post.views_count" class="flex items-center gap-1">
                                                    <i class="ti ti-eye"></i>
                                                    {{ new Intl.NumberFormat().format(post.views_count) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                                <AdSection v-if="index === 2 || index === 5" zone="between_posts" class="col-span-full" />
                            </template>
                        </div>

                        <div v-else class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-12 text-center">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('No posts found') }}</h2>
                            <p class="mt-2 text-sm text-gray-500">{{ t('Try a different search or category.') }}</p>
                        </div>
                    </template>

                    <div v-if="posts.links.length > 3" class="mt-8">
                        <Pagination
                            :links="posts.links"
                            :from="posts.from"
                            :to="posts.to"
                            :total="posts.total"
                            :current-page="posts.current_page"
                            :last-page="posts.last_page"
                        />
                    </div>
                </main>

                <aside v-if="blogSettings.sidebar_position !== 'none'" :class="[blogSettings.sidebar_position === 'left' ? 'lg:order-first' : 'lg:order-last', 'space-y-5']">
                    <AdSection zone="sidebar_top" class="mb-3" />
                    <AppSidebar area="blog" />
                    <AdSection zone="sidebar_bottom" class="mt-3" />
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
.shimmer {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: shimmer-animation 1.5s infinite linear;
}

.dark .shimmer {
    background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
    background-size: 200% 100%;
}

@keyframes shimmer-animation {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}
</style>
