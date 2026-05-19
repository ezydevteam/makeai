<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: number) => string

interface Tag { id: number; name: string; slug: string; description: string | null; posts_count: number }
interface Paginated<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }

defineProps<{ tags: Paginated<Tag>; filters: { search?: string } }>()
const { t } = useTranslate()
const editingId = ref<number | null>(null)
const form = useForm({ name: '', slug: '', description: '', meta_title: '', meta_description: '' })

const edit = (tag: Tag) => {
    editingId.value = tag.id
    form.name = tag.name
    form.slug = tag.slug
    form.description = tag.description ?? ''
}
const reset = () => { editingId.value = null; form.reset() }
const submit = () => {
    if (editingId.value) form.put(route('admin.blog.tags.update', editingId.value), { onSuccess: reset })
    else form.post(route('admin.blog.tags.store'), { onSuccess: reset })
}
const remove = (tag: Tag) => {
    if (!confirm(t('Delete this tag?'))) return
    router.delete(route('admin.blog.tags.destroy', tag.id), { preserveScroll: true })
}
const deleteUnused = () => {
    if (!confirm(t('Delete all unused tags?'))) return
    router.delete(route('admin.blog.tags.unused.delete'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('Blog Tags')" />
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Blog Tags') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage searchable labels for blog posts.') }}</p>
            </div>
            <div class="flex gap-2">
                <button @click="deleteUnused" type="button" class="rounded-lg border border-danger-200 px-4 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50">{{ t('Delete Unused') }}</button>
                <Link :href="route('admin.blog.posts.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:bg-surface-900 dark:border-surface-800 dark:text-gray-300">{{ t('Posts') }}</Link>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-6">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editingId ? t('Edit Tag') : t('Create Tag') }}</h2>
                <div class="space-y-4">
                    <input v-model="form.name" :placeholder="t('Name')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <input v-model="form.slug" :placeholder="t('Slug')" type="text" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white">
                    <textarea v-model="form.description" :placeholder="t('Description')" rows="3" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:border-surface-700 dark:text-white"></textarea>
                    <button @click="submit" :disabled="form.processing" type="button" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ t('Save') }}</button>
                    <button v-if="editingId" @click="reset" type="button" class="w-full rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 dark:border-surface-700 dark:text-gray-300">{{ t('Cancel') }}</button>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-surface-900 dark:border-surface-800">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 dark:bg-surface-800">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Name') }}</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase text-gray-500">{{ t('Posts') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">{{ t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tag in tags.data" :key="tag.id" class="border-t border-gray-100 dark:border-surface-800">
                            <td class="px-4 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ tag.name }}</div><div class="text-xs text-gray-500">{{ tag.slug }}</div></td>
                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">{{ tag.posts_count }}</td>
                            <td class="px-4 py-4 text-right">
                                <button @click="edit(tag)" type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:text-primary-600 dark:border-surface-700">{{ t('Edit') }}</button>
                                <button @click="remove(tag)" type="button" class="ms-2 rounded-lg border border-danger-200 px-3 py-1.5 text-xs text-danger-600 hover:bg-danger-50">{{ t('Delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>
