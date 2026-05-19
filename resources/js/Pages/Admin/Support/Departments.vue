<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface Role { id: number; name: string }
interface Department { id: number; name: string; slug: string; description?: string; email?: string; assigned_role_id?: number | null; is_active: boolean; sort_order: number; tickets_count: number; assigned_role?: Role }

const props = defineProps<{ departments: { data: Department[] }; roles: Role[]; filters: { search?: string } }>()
const { t } = useTranslate()
const editing = ref<Department | null>(null)
const search = ref(props.filters.search ?? '')
const form = useForm({ name: '', slug: '', description: '', email: '', assigned_role_id: null as number | null, is_active: true, sort_order: 0 })

const edit = (department: Department) => {
    editing.value = department
    form.defaults({ name: department.name, slug: department.slug, description: department.description ?? '', email: department.email ?? '', assigned_role_id: department.assigned_role_id ?? null, is_active: department.is_active, sort_order: department.sort_order })
    form.reset()
}
const reset = () => { editing.value = null; form.defaults({ name: '', slug: '', description: '', email: '', assigned_role_id: null, is_active: true, sort_order: 0 }); form.reset() }
const save = () => {
    const target = editing.value ? route('admin.support.departments.update', editing.value.id) : route('admin.support.departments.store')
    form.post(target, { preserveScroll: true, onSuccess: reset })
}
const remove = (department: Department) => {
    if (!confirm(t('Delete this department?'))) return
    router.delete(route('admin.support.departments.destroy', department.id), { preserveScroll: true })
}
const filter = () => router.get(route('admin.support.departments.index'), { search: search.value }, { preserveState: true, replace: true })
</script>

<template>
    <Head :title="t('Support Departments')" />
    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support Departments') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage ticket departments and auto-assignment rules.') }}</p>
            </div>
            <Link :href="route('admin.support.tickets.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300">
                {{ t('Back to Tickets') }}
            </Link>
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 p-4 dark:border-surface-800">
                    <input v-model="search" @keyup.enter="filter" type="search" :placeholder="t('Search departments')" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                </div>
                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-for="department in departments.data" :key="department.id" class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ department.name }}</div>
                            <div class="text-sm text-gray-500">{{ department.slug }} · {{ department.tickets_count }} {{ t('tickets') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="edit(department)" class="rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50">{{ t('Edit') }}</button>
                            <button type="button" @click="remove(department)" class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">{{ t('Delete') }}</button>
                        </div>
                    </div>
                </div>
            </section>
            <form @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Department') : t('New Department') }}</h2>
                <div class="space-y-4">
                    <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }}</span><input v-model="form.name" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }}</span><input v-model="form.slug" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Reply email') }}</span><input v-model="form.email" type="email" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                    <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Assigned role') }}</span><select v-model="form.assigned_role_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option :value="null">{{ t('None') }}</option><option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option></select></label>
                    <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</span><textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea></label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input v-model="form.is_active" type="checkbox" class="rounded border-gray-300"> {{ t('Active') }}</label>
                </div>
                <div class="mt-5 flex gap-2">
                    <button v-if="editing" type="button" @click="reset" class="flex-1 rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="submit" class="flex-1 rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ editing ? t('Update') : t('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</template>
