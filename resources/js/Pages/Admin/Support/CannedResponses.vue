<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichEditor from '@/Components/RichEditor.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface Department { id: number; name: string }
interface ResponseRow { id: number; title: string; content: string; department_id?: number | null; department?: Department; usage_count: number }

const props = defineProps<{ responses: { data: ResponseRow[] }; departments: Department[]; filters: { search?: string; department?: string } }>()
const { t } = useTranslate()
const editing = ref<ResponseRow | null>(null)
const search = ref(props.filters.search ?? '')
const department = ref(props.filters.department ?? '')
const form = useForm({ title: '', content: '', department_id: null as number | null })

const edit = (response: ResponseRow) => { editing.value = response; form.defaults({ title: response.title, content: response.content, department_id: response.department_id ?? null }); form.reset() }
const reset = () => { editing.value = null; form.defaults({ title: '', content: '', department_id: null }); form.reset() }
const save = () => form.post(editing.value ? route('admin.support.canned-responses.update', editing.value.id) : route('admin.support.canned-responses.store'), { preserveScroll: true, onSuccess: reset })
const remove = (response: ResponseRow) => { if (confirm(t('Delete this canned response?'))) router.delete(route('admin.support.canned-responses.destroy', response.id), { preserveScroll: true }) }
const filter = () => router.get(route('admin.support.canned-responses.index'), { search: search.value, department: department.value }, { preserveState: true, replace: true })
</script>

<template>
    <Head :title="t('Canned Responses')" />
    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Canned Responses') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage reusable replies for support agents.') }}</p>
            </div>
            <Link :href="route('admin.support.tickets.index')" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300">
                {{ t('Back to Tickets') }}
            </Link>
        </div>
        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_520px]">
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="grid gap-3 border-b border-gray-100 p-4 md:grid-cols-2 dark:border-surface-800">
                    <input v-model="search" @keyup.enter="filter" type="search" :placeholder="t('Search responses')" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    <select v-model="department" @change="filter" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option value="">{{ t('All departments') }}</option><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-for="response in responses.data" :key="response.id" class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ response.title }}</div>
                            <div class="text-sm text-gray-500">{{ response.department?.name ?? t('All departments') }} · {{ response.usage_count }} {{ t('uses') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="edit(response)" class="rounded-lg px-3 py-1.5 text-sm font-medium text-primary-700 hover:bg-primary-50">{{ t('Edit') }}</button>
                            <button type="button" @click="remove(response)" class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">{{ t('Delete') }}</button>
                        </div>
                    </div>
                </div>
            </section>
            <form @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Response') : t('New Response') }}</h2>
                <label class="block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</span><input v-model="form.title" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"></label>
                <label class="mt-4 block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Department') }}</span><select v-model="form.department_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option :value="null">{{ t('All departments') }}</option><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                <div class="mt-4"><span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content') }}</span><RichEditor v-model="form.content" variant="comment" /></div>
                <button type="submit" class="mt-5 w-full rounded-lg bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ editing ? t('Update') : t('Create') }}</button>
            </form>
        </div>
    </div>
</template>
