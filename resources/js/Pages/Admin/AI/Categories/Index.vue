<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

interface Category {
    id: number
    name: string
    slug: string
    description?: string
    icon?: string
    color?: string
    is_active: boolean
    requires_pro: boolean
    sort_order: number
    tools_count: number
}

const props = defineProps<{ categories: Category[] }>()

const editingId = ref<number | null>(null)
const form = useForm({
    name: '',
    slug: '',
    description: '',
    icon: '',
    color: '#10b981',
    is_active: true,
    requires_pro: false,
    sort_order: 0,
})

const resetForm = () => {
    editingId.value = null
    form.defaults({
        name: '',
        slug: '',
        description: '',
        icon: '',
        color: '#10b981',
        is_active: true,
        requires_pro: false,
        sort_order: 0,
    })
    form.reset()
}

const editCategory = (category: Category) => {
    editingId.value = category.id
    form.name = category.name
    form.slug = category.slug
    form.description = category.description || ''
    form.icon = category.icon || ''
    form.color = category.color || '#10b981'
    form.is_active = category.is_active
    form.requires_pro = category.requires_pro
    form.sort_order = category.sort_order || 0
}

const submit = () => {
    if (editingId.value) {
        form.put(route('admin.ai.categories.update', editingId.value), { preserveScroll: true, onSuccess: resetForm })
        return
    }

    form.post(route('admin.ai.categories.store'), { preserveScroll: true, onSuccess: resetForm })
}

const destroyCategory = (category: Category) => {
    if (!confirm(`Delete ${category.name}? Tools will be detached, not deleted.`)) return
    router.delete(route('admin.ai.categories.destroy', category.id), { preserveScroll: true })
}
</script>

<template>
    <Head title="AI Tool Categories - Admin" />

    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">AI Tool Categories</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage the dynamic groups used by public tool pages and admin templates.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl p-5 space-y-4" @submit.prevent="submit">
                <h2 class="font-bold text-gray-900 dark:text-white">{{ editingId ? 'Edit Category' : 'New Category' }}</h2>

                <input v-model="form.name" required placeholder="Name" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                <input v-model="form.slug" placeholder="slug" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm font-mono" />
                <textarea v-model="form.description" rows="3" placeholder="Description" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm"></textarea>
                <div class="grid grid-cols-2 gap-3">
                    <input v-model="form.icon" placeholder="ti-pencil" class="px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />
                    <input v-model="form.color" type="color" class="h-11 w-full rounded-xl border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800" />
                </div>
                <input v-model.number="form.sort_order" type="number" min="0" placeholder="Sort order" class="w-full px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm" />

                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary-600" /> Active
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input v-model="form.requires_pro" type="checkbox" class="rounded border-gray-300 text-primary-600" /> Requires Pro
                    </label>
                </div>

                <div class="flex gap-2">
                    <button :disabled="form.processing" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-sm font-semibold disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Category' }}
                    </button>
                    <button v-if="editingId" type="button" class="px-4 py-2 bg-gray-100 dark:bg-surface-800 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-semibold" @click="resetForm">Cancel</button>
                </div>
            </form>

            <div class="lg:col-span-2 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-surface-800 text-gray-500">
                        <tr>
                            <th class="text-left px-5 py-3">Category</th>
                            <th class="text-left px-5 py-3">Tools</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
                        <tr v-for="category in props.categories" :key="category.id">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ category.name }}</div>
                                <div class="text-xs text-gray-500">{{ category.slug }}</div>
                            </td>
                            <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ category.tools_count }}</td>
                            <td class="px-5 py-4">
                                <span :class="category.is_active ? 'bg-success-500/10 text-success-600' : 'bg-gray-500/10 text-gray-500'" class="px-2 py-1 rounded-lg text-xs font-semibold">
                                    {{ category.is_active ? 'Active' : 'Hidden' }}
                                </span>
                                <span v-if="category.requires_pro" class="ml-2 px-2 py-1 rounded-lg text-xs font-semibold bg-accent-500/10 text-accent-600">Pro</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button class="text-primary-600 hover:text-primary-700 text-sm font-semibold mr-3" @click="editCategory(category)">Edit</button>
                                <button class="text-danger-600 hover:text-danger-700 text-sm font-semibold" @click="destroyCategory(category)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

