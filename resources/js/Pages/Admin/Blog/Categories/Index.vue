<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: number) => string

interface Category {
    id: number
    name: string
    slug: string
    description: string | null
    parent_id: number | null
    parent?: { name: string } | null
    icon: string | null
    color: string | null
    is_active: boolean
    sort_order: number
    posts_count: number
}
interface Paginated<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }

const props = defineProps<{ categories: Paginated<Category>; parents: Category[] }>()
const { t } = useTranslate()
const editingId = ref<number | null>(null)
const slugTouched = ref(false)

const form = useForm({
    name: '',
    slug: '',
    description: '',
    parent_id: '',
    icon: '',
    color: '#10b981',
    meta_title: '',
    meta_description: '',
    og_image: '',
    is_active: true,
    sort_order: 0,
})

const edit = (category: Category) => {
    editingId.value = category.id
    slugTouched.value = true
    form.name = category.name
    form.slug = category.slug
    form.description = category.description ?? ''
    form.parent_id = category.parent_id ? String(category.parent_id) : ''
    form.icon = category.icon ?? ''
    form.color = category.color ?? '#10b981'
    form.is_active = category.is_active
    form.sort_order = category.sort_order
}

const reset = () => {
    editingId.value = null
    slugTouched.value = false
    form.reset()
    form.color = '#10b981'
    form.is_active = true
    form.sort_order = 0
}

const makeSlug = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')

const syncSlug = () => {
    if (slugTouched.value) return
    form.slug = makeSlug(form.name)
}

const markSlugTouched = () => {
    slugTouched.value = true
    form.slug = makeSlug(form.slug)
}

const submit = () => {
    const payload = { ...form.data(), parent_id: form.parent_id || null }
    if (editingId.value) {
        form.transform(() => payload).put(route('admin.blog.categories.update', editingId.value), { onSuccess: reset })
    } else {
        form.transform(() => payload).post(route('admin.blog.categories.store'), { onSuccess: reset })
    }
}

const remove = (category: Category) => {
    if (!confirm(t('Delete this category?'))) return
    router.delete(route('admin.blog.categories.destroy', category.id), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Blog Categories')" />
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Categories') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Organize posts with active, hierarchical categories.') }}</p>
            </div>
            <Link :href="route('admin.blog.posts.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Posts') }}</Link>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-6">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editingId ? t('Edit Category') : t('Create Category') }}</h2>
                <div class="space-y-4">
                    <input v-model="form.name" @input="syncSlug" :placeholder="t('Name')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <div>
                        <input v-model="form.slug" @input="markSlugTouched" :placeholder="t('Slug')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        <p class="mt-1 text-xs text-gray-400">{{ t('Generated from name. You can edit it.') }}</p>
                    </div>
                    <select v-model="form.parent_id" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        <option value="">{{ t('No parent') }}</option>
                        <option v-for="parent in parents.filter(item => item.id !== editingId)" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                    </select>
                    <textarea v-model="form.description" :placeholder="t('Description')" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="form.icon" :placeholder="t('Icon class')" type="text" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                        <input v-model="form.color" type="color" class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 p-1 dark:bg-surface-800 dark:border-surface-700">
                    </div>
                    <input v-model="form.sort_order" type="number" min="0" :placeholder="t('Sort order')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <label class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-300">
                        <span>{{ t('Active') }}</span>
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600">
                    </label>
                    <div class="flex gap-2">
                        <button @click="submit" :disabled="form.processing" type="button" class="flex-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Save') }}</button>
                        <button v-if="editingId" @click="reset" type="button" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 dark:border-surface-700 dark:text-gray-300">{{ t('Cancel') }}</button>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Name') }}</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Parent') }}</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Posts') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="category in categories.data" :key="category.id" class="border-t border-gray-100 dark:border-surface-800">
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ category.name }}</div>
                                <div class="text-xs text-gray-500">{{ category.slug }}</div>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ category.parent?.name ?? t('None') }}</td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ category.posts_count }}</td>
                            <td class="px-4 py-4 text-right">
                                <button @click="edit(category)" type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:text-primary-600 dark:border-surface-700">{{ t('Edit') }}</button>
                                <button @click="remove(category)" type="button" class="ms-2 rounded-lg border border-danger-200 px-3 py-1.5 text-xs text-danger-600 hover:bg-danger-50">{{ t('Delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>
