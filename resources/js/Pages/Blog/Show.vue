<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserLayout })

declare const route: (name: string, params?: string | number) => string

interface Author { name: string; avatar?: string | null }
interface Taxonomy { id: number; name: string; slug: string }
interface BlogPost {
    ulid: string
    title: string
    slug: string
    excerpt: string | null
    content: string
    featured_image: string | null
    featured_image_alt: string | null
    published_at: string | null
    reading_time: number
    show_author: boolean
    show_date: boolean
    show_reading_time: boolean
    show_share_buttons: boolean
    show_toc: boolean
    author: Author | null
    categories: Taxonomy[]
    tags: Taxonomy[]
}

const props = defineProps<{
    post: BlogPost
    relatedPosts: BlogPost[]
    schema: Record<string, unknown>
    meta: { title: string; description: string; no_index?: boolean }
}>()

const { t } = useTranslate()
const schemaJson = computed(() => JSON.stringify(props.schema))

const formatDate = (value: string | null) => {
    if (!value) return ''
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(value))
}
</script>

<template>
    <Head>
        <title>{{ meta.title }}</title>
        <meta name="description" :content="meta.description" />
        <meta v-if="meta.no_index" name="robots" content="noindex,nofollow" />
        <component :is="'script'" type="application/ld+json" v-html="schemaJson" />
    </Head>

    <article class="min-h-screen bg-emerald-50/50 dark:bg-surface-950">
        <div class="max-w-4xl mx-auto px-6 py-10">
            <nav class="mb-6 flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('home')" class="hover:text-primary-600">{{ t('Home') }}</Link>
                <span>/</span>
                <Link :href="route('blog.index')" class="hover:text-primary-600">{{ t('Blog') }}</Link>
            </nav>

            <header class="mb-8">
                <div class="mb-4 flex flex-wrap gap-2">
                    <Link v-for="category in post.categories" :key="category.id" :href="route('blog.category', category.slug)" class="rounded-full bg-primary-100 px-3 py-1 text-xs font-medium text-primary-700">
                        {{ category.name }}
                    </Link>
                </div>
                <h1 class="text-4xl font-bold leading-tight text-gray-900 dark:text-white">{{ post.title }}</h1>
                <p v-if="post.excerpt" class="mt-4 text-lg text-gray-600 dark:text-gray-400">{{ post.excerpt }}</p>
                <div class="mt-5 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                    <span v-if="post.show_author && post.author">{{ post.author.name }}</span>
                    <span v-if="post.show_date">{{ formatDate(post.published_at) }}</span>
                    <span v-if="post.show_reading_time">{{ post.reading_time }} {{ t('min read') }}</span>
                </div>
            </header>

            <img v-if="post.featured_image" :src="post.featured_image" :alt="post.featured_image_alt || post.title" class="mb-8 aspect-[16/8] w-full rounded-xl object-cover shadow-sm">

            <div class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-6 md:p-8">
                <div class="prose prose-emerald max-w-none dark:prose-invert" v-html="post.content"></div>

                <div v-if="post.tags.length" class="mt-8 flex flex-wrap gap-2 border-t border-gray-100 dark:border-surface-800 pt-6">
                    <Link v-for="tag in post.tags" :key="tag.id" :href="route('blog.tag', tag.slug)" class="rounded-full bg-gray-100 dark:bg-surface-800 px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:bg-primary-100 hover:text-primary-700">
                        {{ tag.name }}
                    </Link>
                </div>
            </div>

            <section v-if="relatedPosts.length" class="mt-10">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Related Posts') }}</h2>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Link v-for="related in relatedPosts" :key="related.ulid" :href="route('blog.show', related.slug)" class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-xl shadow-sm p-4 hover:border-primary-200 hover:shadow-md transition-all">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ related.title }}</h3>
                        <p class="mt-2 text-xs text-gray-500 line-clamp-2">{{ related.excerpt }}</p>
                    </Link>
                </div>
            </section>
        </div>
    </article>
</template>
