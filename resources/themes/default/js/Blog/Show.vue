<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import SocialShare from '@themes/default/js/Components/SocialShare.vue'
import CommentSection from '@themes/default/js/Components/CommentSection.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'
import AdSection from '@themes/default/js/Components/AdSection.vue'
import AppSidebar from '@themes/default/js/Components/AppSidebar.vue'

defineOptions({ layout: UserLayout })

declare const route: (name: string, params?: string | number) => string

interface Author { name: string; avatar?: string | null }
interface Taxonomy { id: number; name: string; slug: string; posts_count?: number }
interface BlogPost {
    id: number
    ulid: string
    title: string
    slug: string
    excerpt: string | null
    content: string
    featured_image: string | null
    featured_image_alt: string | null
    published_at: string | null
    reading_time: number
    views_count?: number
    share_count?: number
    is_featured?: boolean
    show_toc: boolean
    allow_comments: boolean
    favorites_count?: number
    is_favorited?: boolean
    author: Author | null
    categories: Taxonomy[]
    tags: Taxonomy[]
}

type ShareNetwork = 'facebook' | 'x' | 'linkedin' | 'whatsapp' | 'telegram' | 'pinterest' | 'reddit' | 'email' | 'copy'

const props = defineProps<{
    post: BlogPost
    relatedPosts: BlogPost[]
    comments: {
        data: any[]
        links: Array<{ url: string | null; label: string; active: boolean }>
        meta: { total: number }
    }
    commentSettings: {
        enabled: boolean
        allow_guests: boolean
        poll_seconds: number
    }
    share: {
        url: string
        title: string
        style: 'icon' | 'icon-label' | 'icon-count'
        networks: ShareNetwork[]
        urls: Partial<Record<Exclude<ShareNetwork, 'copy'>, string>>
        counts: Partial<Record<ShareNetwork, number>>
        show_counts: boolean
    } | null
    schema: Record<string, unknown>
    meta: {
        title: string
        document_title: string
        description: string
        keywords?: string
        canonical?: string
        og_title?: string
        og_description?: string
        og_image?: string | null
        no_index?: boolean
    }
    categories: Taxonomy[]
    tags: Taxonomy[]
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
const schemaJson = computed(() => JSON.stringify(props.schema))

const formatDate = (value: string | null) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(value))
}

// Compact counts: 1500 -> 1.5k, 1200000 -> 1.2m
const formatCount = (value: number): string => {
    if (value >= 1_000_000) return (value / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'm'
    if (value >= 1_000) return (value / 1_000).toFixed(1).replace(/\.0$/, '') + 'k'
    return String(value)
}

const isShareOpen = ref(false)
const shareDropdownRef = ref<HTMLElement | null>(null)

onClickOutside(shareDropdownRef, () => {
    isShareOpen.value = false
})
</script>

<template>
    <!-- Site-free title portion only. The global "| site" callback (app.ts) runs over a
         <title> child too, so passing meta.document_title (which already ends in the
         site name) would double-suffix it. The result matches the server-rendered
         <title inertia>, which uses document_title. -->
    <Head>
        <title>{{ meta.title }}</title>
        <meta name="description" :content="meta.description" />
        <meta v-if="meta.keywords" name="keywords" :content="meta.keywords" />
        <link v-if="meta.canonical" rel="canonical" :href="meta.canonical" />
        <meta v-if="meta.no_index" name="robots" content="noindex,nofollow" />
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="meta.og_title || meta.title" />
        <meta property="og:description" :content="meta.og_description || meta.description" />
        <meta v-if="meta.canonical" property="og:url" :content="meta.canonical" />
        <meta v-if="meta.og_image" property="og:image" :content="meta.og_image" />
        <meta name="twitter:card" :content="meta.og_image ? 'summary_large_image' : 'summary'" />
        <meta name="twitter:title" :content="meta.og_title || meta.title" />
        <meta name="twitter:description" :content="meta.og_description || meta.description" />
        <meta v-if="meta.og_image" name="twitter:image" :content="meta.og_image" />
        <component :is="'script'" type="application/ld+json" v-html="schemaJson" />
    </Head>

    <article class="min-h-screen">
        <div :class="[
            blogSettings.post_layout_width === 'boxed' ? '!max-w-5xl' : 'max-w-4xl',
            'mx-auto px-4 sm:px-6 py-10'
        ]">
            <nav :class="['mb-3 flex flex-wrap items-center gap-2 text-sm text-gray-500', blogSettings.post_layout_centered ? 'justify-center' : '']">
                <Link :href="route('home')" class="hover:text-primary-600">{{ t('Home') }}</Link>
                <span><i class="ti ti-chevron-right text-xs"></i></span>
                <Link :href="route('blog.index')" class="hover:text-primary-600">{{ t('Blog') }}</Link>
                <span><i class="ti ti-chevron-right text-xs"></i></span>
                <span class="text-gray-900 dark:text-gray-300 font-medium truncate max-w-[240px] sm:max-w-none">{{ post.title }}</span>
            </nav>

            <div :class="[
                blogSettings.sidebar_post_position !== 'none'
                    ? (blogSettings.sidebar_post_position === 'left' ? 'grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6' : 'grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6')
                    : ''
            ]">
                <!-- Main Content Column -->
                <div :class="[blogSettings.sidebar_post_position !== 'none' ? 'w-full' : 'max-w-4xl mx-auto w-full']" class="space-y-6">
                    <header :class="['mb-8', blogSettings.post_layout_centered ? 'text-center flex flex-col items-center justify-center' : '']">
                        <div v-if="post.is_featured || (post.categories && post.categories.length > 0)" :class="['mb-2 flex flex-wrap items-center gap-2', blogSettings.post_layout_centered ? 'justify-center' : '']">
                            <span v-if="post.is_featured" class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                <i class="ti ti-star-filled text-xs"></i>{{ t('Featured') }}
                            </span>
                            <Link v-if="post.categories && post.categories.length > 0" :href="route('blog.category', post.categories[0].slug)" class="rounded-full bg-primary-100 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/40">
                                {{ post.categories[0].name }}
                            </Link>
                        </div>
                        <h1 class="text-4xl font-bold leading-tight text-gray-900 dark:text-white" :class="[blogSettings.post_layout_centered ? 'text-center' : '']">{{ post.title }}</h1>
                        <p v-if="post.excerpt" :class="['mt-3 text-base text-gray-600 dark:text-gray-400', blogSettings.post_layout_centered ? 'text-center' : '']">{{ post.excerpt }}</p>
                        <div :class="['mt-3 flex flex-wrap gap-4 text-sm text-gray-500 items-center', blogSettings.post_layout_centered ? 'justify-center' : '']">
                            <span v-if="post.author && blogSettings.show_post_author_post" class="flex items-center gap-1">
                                <i class="ti ti-user"></i>
                                {{ post.author.name }}
                            </span>
                            <span v-if="blogSettings.show_published_date_post" class="flex items-center gap-1">
                                <i class="ti ti-calendar"></i>
                                {{ formatDate(post.published_at) }}
                            </span>
                            <span v-if="blogSettings.show_reading_time_post" class="flex items-center gap-1">
                                <i class="ti ti-clock"></i>
                                {{ post.reading_time }} {{ t('min read') }}
                            </span>
                            <span v-if="blogSettings.show_view_count_post && post.views_count" class="flex items-center gap-1">
                                <i class="ti ti-eye"></i>
                                {{ formatCount(post.views_count) }} {{ t('views') }}
                            </span>
                            <span v-if="post.share_count" class="flex items-center gap-1">
                                <i class="ti ti-share"></i>
                                {{ formatCount(post.share_count) }} {{ t('shares') }}
                            </span>
                            <div
                                v-if="share && (blogSettings.post_social_share_position === 'top' || blogSettings.post_social_share_position === 'both')"
                                ref="shareDropdownRef"
                                class="relative inline-block"
                            >
                                <button
                                    @click="isShareOpen = !isShareOpen"
                                    type="button"
                                    class="flex items-center gap-1 hover:text-primary-600 dark:hover:text-primary-400 focus:outline-none transition-colors"
                                >
                                    <i class="ti ti-share"></i>
                                    <span>{{ t('Share') }}</span>
                                </button>

                                <div
                                    v-if="isShareOpen"
                                    class="absolute left-0 mt-2 z-40 w-48 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-surface-800 dark:bg-surface-900 focus:outline-none"
                                >
                                    <div class="share-dropdown-menu">
                                        <SocialShare
                                            :url="share.url"
                                            :title="share.title"
                                            :style="'icon-label'"
                                            :networks="share.networks"
                                            :urls="share.urls"
                                            :counts="share.counts"
                                            :show-counts="false"
                                            layout="vertical"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </header>

                    <img v-if="post.featured_image" :src="mediaUrl(post.featured_image)" :alt="post.featured_image_alt || post.title" class="mb-8 aspect-[16/8] w-full rounded-xl object-cover shadow-sm">

                    <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl p-6">
                        <div :class="['prose prose-emerald max-w-none dark:prose-invert', blogSettings.post_layout_centered ? 'text-center' : '']" v-html="post.content"></div>

                        <!-- Bottom Tags & Share inline -->
                        <div v-if="(post.tags.length && blogSettings.show_tags_post) || (share && (blogSettings.post_social_share_position === 'bottom' || blogSettings.post_social_share_position === 'both'))" class="mt-8 pt-6 border-t border-gray-100 dark:border-surface-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <!-- Tags -->
                            <div v-if="post.tags.length && blogSettings.show_tags_post" class="shrink-0 flex flex-wrap gap-2">
                                <Link v-for="tag in post.tags.slice(0, 5)" :key="tag.id" :href="route('blog.tag', tag.slug)" class="rounded-full bg-gray-200/50 dark:bg-surface-800 px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-primary-100 hover:text-primary-700 dark:hover:bg-primary-900/40 transition-colors">
                                    {{ tag.name }}
                                </Link>
                            </div>

                            <!-- Share Buttons -->
                            <div v-if="share && (blogSettings.post_social_share_position === 'bottom' || blogSettings.post_social_share_position === 'both')">
                                <SocialShare
                                    :url="share.url"
                                    :title="share.title"
                                    :style="share.style"
                                    :networks="share.networks"
                                    :urls="share.urls"
                                    :counts="share.counts"
                                    :show-counts="share.show_counts"
                                />
                            </div>
                        </div>
                    </div>

                    <section v-if="relatedPosts.length && blogSettings.show_related_posts_post" class="mt-10">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white" :class="[blogSettings.post_layout_centered ? 'text-center' : '']">{{ t('Related Posts') }}</h2>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Link v-for="related in relatedPosts" :key="related.ulid" :href="route('blog.show', related.slug)" class="group bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl p-4 hover:!border-primary-200 dark:hover:!border-primary-900/60 hover:shadow-sm transition-all">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white group-hover:!text-primary-600 dark:group-hover:!text-primary-400 transition-colors">{{ related.title }}</h3>
                                <p class="mt-2 text-xs text-gray-500 line-clamp-2">{{ related.excerpt }}</p>
                            </Link>
                        </div>
                    </section>

                    <CommentSection
                        v-if="blogSettings.show_comments_post"
                        :comments="comments"
                        model-type="blog_posts"
                        :model-id="post.id"
                        :enabled="commentSettings.enabled"
                        :allow-guests="commentSettings.allow_guests"
                        :poll-seconds="commentSettings.poll_seconds"
                    />
                </div>

                <!-- Sidebar Column -->
                <aside v-if="blogSettings.sidebar_post_position !== 'none'" :class="[blogSettings.sidebar_post_position === 'left' ? 'lg:order-first' : 'lg:order-last', 'space-y-5 h-fit']">
                    <AdSection zone="sidebar_top" class="mb-3" />
                    <AppSidebar area="blog" />
                    <AdSection zone="sidebar_bottom" class="mt-3" />
                </aside>
            </div>
        </div>
    </article>
</template>

<style scoped>
.share-dropdown-menu :deep(button),
.share-dropdown-menu :deep(a) {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    min-height: 2.25rem !important;
    padding: 0.375rem 0.75rem !important;
    width: 100% !important;
    border-radius: 0.375rem !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
}
</style>
