<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: string | number | Record<string, string | number | undefined>) => string

interface Category { id: number; name: string; slug?: string }
interface Author { id: number; name: string }
interface BlogPost {
    ulid: string
    title: string
    slug: string
    status: string
    views_count: number
    published_at: string | null
    updated_at: string
    author: Author | null
    categories: Category[]
}
interface Paginated<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    posts: Paginated<BlogPost>
    categories: Category[]
    authors: Author[]
    filters: { search?: string; status?: string; category?: string; author?: string }
}>()

const { t } = useTranslate()
const selected = ref<string[]>([])
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const category = ref(props.filters.category ?? '')
const author = ref(props.filters.author ?? '')

const applyFilters = () => {
    router.get(route('admin.blog.posts.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        category: category.value || undefined,
        author: author.value || undefined,
    }, { preserveState: true, replace: true })
}

const remove = (post: BlogPost) => {
    if (!confirm(t('Move this blog post to trash?'))) return
    router.delete(route('admin.blog.posts.destroy', post.ulid), { preserveScroll: true })
}

const duplicate = (post: BlogPost) => {
    router.post(route('admin.blog.posts.duplicate', post.ulid), {}, { preserveScroll: true })
}

const bulk = (action: 'publish' | 'draft' | 'delete') => {
    if (!selected.value.length) return
    if (action === 'delete' && !confirm(t('Move selected posts to trash?'))) return
    router.post(route('admin.blog.posts.bulk'), { ids: selected.value, action }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = [] },
    })
}

const badgeClass = (value: string) => {
    if (value === 'published') return 'bg-success-500/10 text-success-600'
    if (value === 'scheduled') return 'bg-warning-500/10 text-warning-600'
    if (value === 'private') return 'bg-accent-500/10 text-accent-600'
    return 'bg-gray-100 text-gray-600 dark:bg-surface-800 dark:text-gray-400'
}

const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : t('Not published')
</script>

<template>
    <Head :title="t('Blog Posts')" />

    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Posts') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Create, edit, schedule, and publish blog content.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.blog.categories.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Categories') }}</Link>
                <Link :href="route('admin.blog.tags.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Tags') }}</Link>
                <Link :href="route('admin.blog.settings.edit')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Settings') }}</Link>
                <Link :href="route('admin.blog.posts.create')" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Create Post') }}</Link>
            </div>
        </div>

        <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-surface-900 dark:border-surface-800">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input v-model="search" @keyup.enter="applyFilters" type="search" :placeholder="t('Search posts')" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                <select v-model="status" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <option value="">{{ t('All statuses') }}</option>
                    <option value="draft">{{ t('Draft') }}</option>
                    <option value="published">{{ t('Published') }}</option>
                    <option value="scheduled">{{ t('Scheduled') }}</option>
                    <option value="private">{{ t('Private') }}</option>
                </select>
                <select v-model="category" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <option value="">{{ t('All categories') }}</option>
                    <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
                <select v-model="author" @change="applyFilters" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <option value="">{{ t('All authors') }}</option>
                    <option v-for="item in authors" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
                <button @click="applyFilters" type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Filter') }}</button>
            </div>
            <div v-if="selected.length" class="mt-4 flex flex-wrap gap-2">
                <button @click="bulk('publish')" type="button" class="rounded-lg bg-success-500 px-3 py-2 text-xs font-medium text-white">{{ t('Publish Selected') }}</button>
                <button @click="bulk('draft')" type="button" class="rounded-lg bg-gray-700 px-3 py-2 text-xs font-medium text-white">{{ t('Move to Draft') }}</button>
                <button @click="bulk('delete')" type="button" class="rounded-lg bg-danger-600 px-3 py-2 text-xs font-medium text-white">{{ t('Delete Selected') }}</button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-surface-900 dark:border-surface-800">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-surface-800">
                    <tr>
                        <th class="w-10 px-4 py-3"><span class="sr-only">{{ t('Select') }}</span></th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Title') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Status') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Author') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Views') }}</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Date') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="post in posts.data" :key="post.ulid" class="border-t border-gray-100 hover:bg-primary-50/40 dark:border-surface-800">
                        <td class="px-4 py-4"><input v-model="selected" :value="post.ulid" type="checkbox" class="rounded border-gray-300 text-primary-600"></td>
                        <td class="px-4 py-4">
                            <div class="font-medium text-gray-900 dark:text-white">{{ post.title }}</div>
                            <div class="mt-1 text-xs text-gray-500">/blog/{{ post.slug }}</div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <span v-for="item in post.categories" :key="item.id" class="rounded-full bg-primary-50 px-2 py-0.5 text-xs text-primary-700">{{ item.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-4"><span :class="badgeClass(post.status)" class="rounded-full px-2.5 py-1 text-xs font-medium capitalize">{{ t(post.status) }}</span></td>
                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ post.author?.name }}</td>
                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ new Intl.NumberFormat().format(post.views_count) }}</td>
                        <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ formatDate(post.published_at) }}</td>
                        <td class="px-4 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <a :href="route('blog.show', post.slug)" target="_blank" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:text-primary-600 dark:border-surface-700">{{ t('View') }}</a>
                                <button @click="duplicate(post)" type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:text-primary-600 dark:border-surface-700">{{ t('Duplicate') }}</button>
                                <Link :href="route('admin.blog.posts.edit', post.ulid)" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:text-primary-600 dark:border-surface-700">{{ t('Edit') }}</Link>
                                <button @click="remove(post)" type="button" class="rounded-lg border border-danger-200 px-3 py-1.5 text-xs text-danger-600 hover:bg-danger-50">{{ t('Delete') }}</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!posts.data.length">
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No blog posts found') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="posts.links.length > 3" class="mt-6 flex flex-wrap gap-2">
            <Link v-for="link in posts.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 dark:bg-surface-900 dark:text-gray-300', !link.url ? 'opacity-50 pointer-events-none' : 'hover:border-primary-300']" class="min-w-10 rounded-lg border border-gray-200 px-3 py-2 text-center text-sm dark:border-surface-800" />
        </div>
    </div>
</template>
