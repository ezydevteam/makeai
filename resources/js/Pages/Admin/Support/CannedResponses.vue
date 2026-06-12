<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'

const RichEditor = defineAsyncComponent(() => import('@/Components/RichEditor.vue'))
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface Department { id: number; name: string }
interface ResponseRow { id: number; title: string; content: string; department_id?: number | null; department?: Department; usage_count: number }

const props = defineProps<{ responses: { data: ResponseRow[] }; departments: Department[]; filters: { search?: string; department?: string } }>()
const { t } = useTranslate()

const editing = ref<ResponseRow | null>(null)
const deleting = ref<ResponseRow | null>(null)
const deleteProcessing = ref(false)
const search = ref(props.filters.search ?? '')
const department = ref(props.filters.department ?? '')
const form = useForm({ title: '', content: '', department_id: null as number | null })

const departmentOptions = computed(() => [
    { value: '', label: t('All departments') },
    ...props.departments.map((item) => ({ value: String(item.id), label: item.name })),
])

const edit = (response: ResponseRow) => {
    editing.value = response
    form.defaults({ title: response.title, content: response.content, department_id: response.department_id ?? null })
    form.reset()
}

const reset = () => {
    editing.value = null
    form.defaults({ title: '', content: '', department_id: null })
    form.reset()
}

const save = () => {
    if (editing.value) {
        form.put(route('admin.support.canned-responses.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: reset,
        })
        return
    }

    form.post(route('admin.support.canned-responses.store'), {
        preserveScroll: true,
        onSuccess: reset,
    })
}

const remove = () => {
    if (!deleting.value) return
    deleteProcessing.value = true
    router.delete(route('admin.support.canned-responses.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => { deleteProcessing.value = false },
        onSuccess: () => { deleting.value = null },
    })
}

const filter = () => router.get(route('admin.support.canned-responses.index'), { search: search.value, department: department.value }, { preserveState: true, replace: true })
</script>

<template>
    <Head :title="t('Canned Responses')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Canned Responses') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage reusable replies for support agents.') }}</p>
            </div>
            <Link :href="route('admin.support.tickets.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:border-primary-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"><i class="ti ti-arrow-left text-base"></i>{{ t('Back to Tickets') }}</Link>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_520px]">
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row dark:border-surface-800">
                    <div class="relative flex-1">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                        <input v-model="search" @keyup.enter="filter" type="search" :placeholder="t('Search responses')" class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-10 pr-3 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </div>
                    <div class="w-full md:w-52"><AppSelect v-model="department" :options="departmentOptions" :placeholder="t('All departments')" @update:model-value="filter" /></div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-for="response in responses.data" :key="response.id" class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ response.title }}</div>
                            <div class="text-sm text-gray-500">{{ response.department?.name ?? t('All departments') }} · {{ response.usage_count }} {{ t('uses') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <Tooltip :content="t('Edit response')" placement="top"><button type="button" @click="edit(response)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-primary-700 hover:bg-primary-50"><i class="ti ti-pencil text-base"></i></button></Tooltip>
                            <Tooltip :content="t('Delete response')" placement="top"><button type="button" @click="deleting = response" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-600 hover:bg-red-50"><i class="ti ti-trash text-base"></i></button></Tooltip>
                        </div>
                    </div>
                    <div v-if="responses.data.length === 0" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No canned responses found.') }}</div>
                </div>
            </section>

            <form @submit.prevent="save" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Response') : t('New Response') }}</h2>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }}</span>
                    <input v-model="form.title" required :placeholder="t('Enter response title')" type="text" class="mt-2 w-full rounded-lg border bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:text-white" :class="form.errors.title ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20 dark:border-danger-500' : 'border-gray-200 dark:border-surface-700'">
                    <span v-if="form.errors.title" class="mt-1 block text-xs text-danger-600">{{ form.errors.title }}</span>
                </label>
                <label class="mt-4 block"><span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Department') }}</span><select v-model="form.department_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"><option :value="null">{{ t('All departments') }}</option><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                <div class="mt-4"><span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content') }}</span><RichEditor v-model="form.content" variant="comment" /></div>
                <div class="mt-5 flex gap-2">
                    <button v-if="editing" type="button" @click="reset" class="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg btn-primary disabled:cursor-not-allowed disabled:opacity-70">{{ form.processing ? t('Processing...') : (editing ? t('Update') : t('Create')) }}</button>
                </div>
            </form>
        </div>

        <ActionConfirmModal :open="!!deleting" :title="t('Delete canned response')" :message="t('This canned response will be removed permanently.')" :confirm-label="t('Delete')" :cancel-label="t('Cancel')" :processing="deleteProcessing" @confirm="remove" @cancel="deleting = null" />
    </div>
</template>
