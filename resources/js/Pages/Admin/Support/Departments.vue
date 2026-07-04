<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'

defineOptions({ layout: AdminLayout })
declare const route: (name: string, params?: unknown) => string

interface Role { id: number; name: string }
interface Department { id: number; name: string; slug: string; description?: string; email?: string; assigned_role_id?: number | null; is_active: boolean; sort_order: number; tickets_count: number; assigned_role?: Role }

const props = defineProps<{ departments: { data: Department[] }; roles: Role[]; filters: { search?: string } }>()
const { t } = useTranslate()

const editing = ref<Department | null>(null)
const deleting = ref<Department | null>(null)
const deleteProcessing = ref(false)
const slugTouched = ref(false)
const search = ref(props.filters.search ?? '')
const searchFocused = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
const form = useForm({ name: '', slug: '', description: '', email: '', assigned_role_id: null as number | null, is_active: true, sort_order: 0 })

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

const filteredDepartments = computed(() => {
    const query = search.value.trim().toLowerCase()
    if (!query) return props.departments.data
    return props.departments.data.filter((department) => department.name.toLowerCase().includes(query) || department.slug.toLowerCase().includes(query) || (department.email ?? '').toLowerCase().includes(query))
})

const roleOptions = computed(() => [
    { value: null, label: t('None') },
    ...props.roles.map((role) => ({ value: role.id, label: role.name })),
])

const makeSlug = (value: string) => value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

const syncSlug = () => {
    if (slugTouched.value) return
    form.slug = makeSlug(form.name)
}

const markSlugTouched = () => {
    slugTouched.value = true
    form.slug = makeSlug(form.slug)
}

const edit = (department: Department) => {
    editing.value = department
    slugTouched.value = true
    form.defaults({ name: department.name, slug: department.slug, description: department.description ?? '', email: department.email ?? '', assigned_role_id: department.assigned_role_id ?? null, is_active: department.is_active, sort_order: department.sort_order })
    form.reset()
}

const reset = () => {
    editing.value = null
    slugTouched.value = false
    form.defaults({ name: '', slug: '', description: '', email: '', assigned_role_id: null, is_active: true, sort_order: 0 })
    form.reset()
}

const save = () => {
    if (editing.value) {
        form.put(route('admin.support.departments.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: reset,
        })
        return
    }

    form.post(route('admin.support.departments.store'), {
        preserveScroll: true,
        onSuccess: reset,
    })
}

const remove = () => {
    if (!deleting.value) return
    deleteProcessing.value = true
    router.delete(route('admin.support.departments.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => { deleteProcessing.value = false },
        onSuccess: () => { deleting.value = null },
    })
}
</script>

<template>
    <Head :title="t('Support Departments')" />

    <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Support Departments') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Manage ticket departments and auto-assignment rules.') }}</p>
            </div>
            <Link :href="route('admin.support.tickets.index')" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"><i class="ti ti-arrow-left text-base"></i>{{ t('Back to Tickets') }}</Link>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="border-b border-gray-100 p-4 dark:border-surface-800 sm:px-6">
                    <div class="relative">
                        <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400"></i>
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            :placeholder="t('Search departments...')"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                            @focus="searchFocused = true"
                            @blur="searchFocused = false"
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
                </div>
                <div class="divide-y divide-gray-100 dark:divide-surface-800">
                    <div v-for="department in filteredDepartments" :key="department.id" class="flex items-center justify-between gap-4 px-4 py-2.5">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ department.name }}</div>
                            <div class="text-sm text-gray-500">{{ department.slug }} · {{ department.tickets_count }} {{ t('tickets') }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ department.email || t('No reply email') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <Tooltip :content="t('Edit department')" placement="top">
                                <button
                                    type="button"
                                    @click="edit(department)"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950/30"
                                >
                                    <i class="ti ti-edit text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Delete department')" placement="top">
                                <button
                                    type="button"
                                    @click="deleting = department"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full text-danger-600 transition hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-950/30"
                                >
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </Tooltip>
                        </div>
                    </div>
                    <div v-if="filteredDepartments.length === 0" class="px-4 py-12 text-center text-sm text-gray-500">{{ t('No departments found.') }}</div>
                </div>
            </section>

            <form @submit.prevent="save" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="mb-4 text-lg font-bold text-gray-900 dark:text-white">{{ editing ? t('Edit Department') : t('New Department') }}</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Name') }} <span class="text-danger-600">*</span></span>
                            <input
                                v-model="form.name"
                                @input="syncSlug"
                                :placeholder="t('Enter department name')"
                                type="text"
                                required
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :class="{ 'border-danger-600': form.errors.name }"
                            >
                        </label>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs text-danger-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Slug') }} <span class="text-danger-600">*</span></span>
                            <input
                                v-model="form.slug"
                                @input="markSlugTouched"
                                :placeholder="t('billing-support')"
                                type="text"
                                required
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :class="{ 'border-danger-600': form.errors.slug }"
                            >
                        </label>
                        <p v-if="form.errors.slug" class="mt-1.5 text-xs text-danger-600">{{ form.errors.slug }}</p>
                    </div>

                    <div>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Reply email') }}</span>
                            <input
                                v-model="form.email"
                                :placeholder="t('support@example.com')"
                                type="email"
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :class="{ 'border-danger-600': form.errors.email }"
                            >
                        </label>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-danger-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <AppSelect v-model="form.assigned_role_id" :options="roleOptions" :label="t('Assigned role')" :placeholder="t('Select assigned role')" />
                        <p v-if="form.errors.assigned_role_id" class="mt-1.5 text-xs text-danger-600">{{ form.errors.assigned_role_id }}</p>
                    </div>

                    <div>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Description') }}</span>
                            <textarea
                                v-model="form.description"
                                :placeholder="t('Add a short description for routing and agents')"
                                rows="3"
                                class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :class="{ 'border-danger-600': form.errors.description }"
                            ></textarea>
                        </label>
                        <p v-if="form.errors.description" class="mt-1.5 text-xs text-danger-600">{{ form.errors.description }}</p>
                    </div>

                    <div class="flex items-center justify-between rounded-xl border border-gray-100 p-4 dark:border-surface-800">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Active') }}</span>
                        <button type="button" role="switch" :aria-checked="form.is_active" @click="form.is_active = !form.is_active" class="relative inline-flex h-6 w-11 rounded-full transition" :class="form.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'">
                            <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                        </button>
                    </div>
                </div>
                <div class="mt-5 flex gap-2">
                    <button v-if="editing" type="button" @click="reset" class="flex-1 rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="submit" :disabled="form.processing" class="flex-1 rounded-lg btn-primary disabled:cursor-not-allowed disabled:opacity-70">{{ form.processing ? t('Processing...') : (editing ? t('Update') : t('Create')) }}</button>
                </div>
            </form>
        </div>

        <ActionConfirmModal :open="!!deleting" :title="t('Delete department')" :message="t('This department will be removed permanently.')" :confirm-label="t('Delete')" :cancel-label="t('Cancel')" :processing="deleteProcessing" @confirm="remove" @cancel="deleting = null" />
    </div>
</template>
