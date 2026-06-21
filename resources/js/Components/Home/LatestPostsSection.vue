<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

type SectionConfigValue = string | number | boolean | string[] | Record<string, string | number | boolean>[]
interface BlogPostPreview { title: string; slug: string; published_at: string | null; image: string | null; is_featured: boolean }
interface HomepageSection { id: string; type: string; enabled: boolean; core: boolean; config: Record<string, SectionConfigValue> }
const props = defineProps<{ section: HomepageSection; recentPosts: BlogPostPreview[] }>()
const { t } = useTranslate()
const asString = (v: SectionConfigValue | undefined, fallback = ''): string => typeof v === 'string' || typeof v === 'number' ? String(v) : fallback
const heroButtonClass = (style: string): string => ({ primary_filled: 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700', outline: 'border-2 border-gray-200 bg-transparent text-gray-900 hover:bg-gray-50', dark: 'bg-gray-900 text-white' }[style] ?? 'bg-primary-600 text-white shadow-2xl shadow-primary-600/20 hover:bg-primary-700')
const latestPostsPageButtonText = () => asString(props.section.config.button_text, t('Visit Blog'))
const latestPostsPageButtonLink = () => asString(props.section.config.button_link, '/blog')
const latestPostsPageButtonStyle = () => asString(props.section.config.button_style, 'outline')
const latestPostsSectionCardClass = (style: string): string => ({
    simple: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800',
    bordered: 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800',
}[style] ?? 'overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-primary-200 hover:shadow-xl dark:border-surface-700 dark:bg-surface-800')
const latestPostsItems = (): BlogPostPreview[] => {
    const max = parseInt(String(props.section.config.max_items ?? 3), 10)
    return (props.recentPosts || []).slice(0, max)
}
const formatDate = (date: string | null): string => date ? new Date(date).toLocaleDateString() : ''
</script>

<template>
    <section class="bg-gray-50 py-24 dark:bg-surface-900">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mb-12 text-center">
                <div v-if="asString(section.config.icon)" class="mb-5 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 shadow-sm dark:bg-primary-500/10 dark:text-primary-300">
                    <i :class="[asString(section.config.icon), 'text-2xl']"></i>
                </div>
                <h2 class="mb-4 text-3xl font-black text-gray-900 dark:text-white md:text-5xl">{{ asString(section.config.title, t('Latest from the Blog')) }}</h2>
                <p v-if="asString(section.config.subtitle)" class="mx-auto max-w-2xl font-medium text-gray-500 dark:text-gray-400">{{ asString(section.config.subtitle) }}</p>
            </div>
            <div v-if="latestPostsItems().length" class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                <Link v-for="post in latestPostsItems()" :key="post.slug" :href="route('blog.show', post.slug)" :class="[latestPostsSectionCardClass(asString(section.config.card_style, 'bordered')), 'group flex h-full flex-col']">
                    <div class="aspect-[16/9] overflow-hidden bg-gray-100 dark:bg-surface-800">
                        <img v-if="post.image" :src="post.image" :alt="post.title" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-100 to-secondary-100 text-primary-300 dark:from-primary-900/30 dark:to-surface-800">
                            <i class="ti ti-article text-4xl"></i>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col p-6 text-left">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gray-400">{{ formatDate(post.published_at) }}</span>
                        </div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-white">{{ post.title }}</h3>
                    </div>
                </Link>
            </div>
            <div v-else class="rounded-[2rem] border border-dashed border-gray-200 bg-white p-10 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">{{ t('No blog posts available yet.') }}</div>
            <div class="mt-10 text-center" v-if="latestPostsPageButtonText()">
                <Link :href="latestPostsPageButtonLink()" :class="heroButtonClass(latestPostsPageButtonStyle())" class="inline-flex items-center justify-center gap-3 rounded-2xl px-8 py-4 font-black transition-colors">{{ latestPostsPageButtonText() }}</Link>
            </div>
        </div>
    </section>
</template>
