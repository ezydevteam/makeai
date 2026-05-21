<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string) => string

interface Author { id: number; name: string }
interface ShareNetworkOption { key: string; label: string }
const props = defineProps<{ settings: Record<string, any>; authors: Author[]; shareNetworks: ShareNetworkOption[] }>()
const { t } = useTranslate()
const form = useForm({ ...props.settings })

const submit = () => form.post(route('admin.blog.settings.update'), { preserveScroll: true })
</script>

<template>
    <Head :title="t('Blog Settings')" />
    <div class="max-w-4xl mx-auto px-6 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Settings') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Configure listing, related posts, RSS, and defaults.') }}</p>
            </div>
            <Link :href="route('admin.blog.posts.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Posts') }}</Link>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:bg-surface-900 dark:border-surface-800">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Posts per page') }}<input v-model="form.posts_per_page" type="number" min="1" max="48" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Related posts count') }}<input v-model="form.related_posts_count" type="number" min="1" max="12" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Related posts algorithm') }}<select v-model="form.related_posts_algorithm" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"><option value="tags_first">{{ t('Tags first') }}</option><option value="category_first">{{ t('Category first') }}</option><option value="recent">{{ t('Recent') }}</option></select></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Auto excerpt length') }}<input v-model="form.auto_excerpt_length" type="number" min="80" max="500" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Default author') }}<select v-model="form.default_author" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"><option :value="null">{{ t('Current admin') }}</option><option v-for="author in authors" :key="author.id" :value="author.id">{{ author.name }}</option></select></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('RSS posts count') }}<input v-model="form.rss_posts_count" type="number" min="1" max="100" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Words per minute') }}<input v-model="form.words_per_minute" type="number" min="100" max="500" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></label>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Sidebar position') }}<select v-model="form.blog_sidebar_position" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"><option value="right">{{ t('Right') }}</option><option value="left">{{ t('Left') }}</option></select></label>
            </div>
            <div class="mt-6 space-y-3">
                <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300"><span>{{ t('Default allow comments') }}</span><input v-model="form.default_allow_comments" type="checkbox" class="rounded border-gray-300 text-primary-600"></label>
                <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300"><span>{{ t('Show reading time') }}</span><input v-model="form.show_reading_time" type="checkbox" class="rounded border-gray-300 text-primary-600"></label>
                <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300"><span>{{ t('Show blog sidebar') }}</span><input v-model="form.blog_sidebar" type="checkbox" class="rounded border-gray-300 text-primary-600"></label>
            </div>
            <button @click="submit" :disabled="form.processing" type="button" class="mt-6 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">{{ form.processing ? t('Saving...') : t('Save Settings') }}</button>
        </section>

        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:bg-surface-900 dark:border-surface-800">
            <div class="mb-5">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Social share buttons') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ t('Choose networks and display style for blog post share buttons.') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <label v-for="network in shareNetworks" :key="network.key" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                    <input v-model="form.social_share_networks" :value="network.key" type="checkbox" class="rounded border-gray-300 text-primary-600">
                    <span>{{ t(network.label) }}</span>
                </label>
            </div>
            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ t('Display style') }}
                    <select v-model="form.social_share_blog_style" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        <option value="icon">{{ t('Icon only') }}</option>
                        <option value="icon-label">{{ t('Icon and label') }}</option>
                        <option value="icon-count">{{ t('Icon and count') }}</option>
                    </select>
                </label>
                <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                    <span>{{ t('Show share counts when available') }}</span>
                    <input v-model="form.social_share_show_counts" type="checkbox" class="rounded border-gray-300 text-primary-600">
                </label>
            </div>
            <p v-if="form.errors.social_share_networks" class="mt-3 text-sm text-danger-600">{{ form.errors.social_share_networks }}</p>
            <button @click="submit" :disabled="form.processing" type="button" class="mt-6 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-60">{{ form.processing ? t('Saving...') : t('Save Settings') }}</button>
        </section>
    </div>
</template>
