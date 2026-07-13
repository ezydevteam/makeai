<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/UI/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'

const RichEditor = defineAsyncComponent(() => import('@/Components/UI/RichEditor.vue'))
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'

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
const searchFocused = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
const department = ref(props.filters.department ?? '')
const form = useForm({ title: '', content: '', department_id: null as number | null })

const handleKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null
    const tagName = target?.tagName
    const isTypingTarget = tagName === 'INPUT' || tagName === 'TEXTAREA' || target?.isContentEditable

    if (event.key === '/' && !isTypingTarget) {
        event.preventDefault()
        searchInput.value?.focus()
        searchInput.value?.select()
        return
    }

    if (event.key === 'Escape' && document.activeElement === searchInput.value) {
        event.preventDefault()
        search.value = ''
        searchInput.value?.blur()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
})

const departmentOptions = computed(() => [
    { value: '', label: t('All departments') },
    ...props.departments.map((item) => ({ value: String(item.id), label: item.name })),
])

const formDepartmentOptions = computed(() => [
    { value: null, label: t('All departments') },
    ...props.departments.map((item) => ({ value: item.id, label: item.name })),
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

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Canned Responses') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage reusable replies for support agents.') }}</p>
            </div>
            <Link :href="route('admin.support.tickets.index')" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <i class="ti ti-arrow-left text-base"></i>
                {{ t('Back to Tickets') }}
            </Link>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_520px]">
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row dark:border-surface-800 sm:px-6">
                    <div class="relative flex-1">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            :placeholder="t('Search responses...')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @focus="searchFocused = true"
                            @blur="searchFocused = false"
                            @keyup.enter="filter"
                        >
                        <span
                            v-if="!search && !searchFocused"
                            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                        >/</span>
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                            @click="search = ''"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                    <div class="w-full md:w-52">
                        <AppSelect v-model="department" :options="departmentOptions" :placeholder="t('All departments')" @update:model-value="filter" />
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-for="response in responses.data" :key="response.id" class="flex items-center justify-between gap-4 px-4 py-2.5">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ response.title }}</div>
                            <div class="text-sm text-gray-500">{{ response.department?.name ?? t('All departments') }} Â· {{ response.usage_count }} {{ t('uses') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <Tooltip :content="t('Edit response')" placement="top">
                                <button
                                    type="button"
                                    @click="edit(response)"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950/30"
                                >
                                    <i class="ti ti-edit text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Delete response')" placement="top">
                                <button
                                    type="button"
                                    @click="deleting = response"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-danger-600 transition hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-950/30"
                                >
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>
                    <div v-if="responses.data.length === 0" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No canned responses found.') }}</div>
                </div>
            </section>

            <form @submit.prevent="save" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Response') : t('New Response') }}</h2>

                <div>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Title') }} <span class="text-danger-600">*</span></span>
                        <input
                            v-model="form.title"
                            required
                            :placeholder="t('Enter response title')"
                            type="text"
                            class="mt-2 w-full rounded-lg border bg-gray-50 px-3 py-2 text-sm dark:bg-surface-800 dark:text-white"
                            :class="form.errors.title ? 'border-danger-600 focus:border-danger-600 focus:ring-danger-500/20 dark:border-danger-600' : 'border-gray-200 dark:border-surface-700'"
                        >
                    </label>
                    <p v-if="form.errors.title" class="mt-1.5 text-xs text-danger-600">{{ form.errors.title }}</p>
                </div>

                <div class="mt-4">
                    <AppSelect
                        v-model="form.department_id"
                        :options="formDepartmentOptions"
                        :label="t('Department')"
                        :placeholder="t('Select department')"
                        :error="form.errors.department_id"
                    />
                </div>

                <div class="mt-4">
                    <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Content') }} <span class="text-danger-600">*</span></span>
                    <RichEditor v-model="form.content" variant="comment" />
                    <p v-if="form.errors.content" class="mt-1.5 text-xs text-danger-600">{{ form.errors.content }}</p>
                </div>

                <div class="mt-5 flex gap-2">
                    <button v-if="editing" type="button" @click="reset" class="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg btn-primary-admin disabled:cursor-not-allowed disabled:opacity-70">{{ form.processing ? t('Processing...') : (editing ? t('Update') : t('Create')) }}</button>
                </div>
            </form>
        </div>

        <ActionConfirmModal :open="!!deleting" :title="t('Delete canned response')" :message="t('This canned response will be removed permanently.')" :confirm-label="t('Delete')" :cancel-label="t('Cancel')" :processing="deleteProcessing" @confirm="remove" @cancel="deleting = null" />
    </div>
</template>
