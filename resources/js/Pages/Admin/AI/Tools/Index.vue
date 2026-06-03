<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: AdminLayout })

const props = defineProps<{
    tools: any
    categories: any[]
    filters: { category?: string; search?: string; status?: string }
}>()

const search = ref(props.filters.search || '')
const selectedCategory = ref(props.filters.category || '')
const selectedStatus = ref(props.filters.status || '')
const { t } = useTranslate()
const { formatNumber } = useNumberFormat()

const applyFilters = () => {
    router.get(route('admin.ai.tools.index'), {
        search: search.value || undefined,
        category: selectedCategory.value || undefined,
        status: selectedStatus.value || undefined,
    }, { preserveState: true, replace: true })
}

const toggleTool = (id: number) => {
    router.post(route('admin.ai.tools.toggle', id), {}, { preserveScroll: true })
}

const deleteTool = (id: number) => {
    if (confirm(t('Are you sure you want to delete this tool?'))) {
        router.delete(route('admin.ai.tools.destroy', id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="t('AI Tools — Admin')" />

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('AI Tools') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Manage AI tools, prompts, and configurations.') }}</p>
            </div>
            <Link :href="route('admin.ai.tools.create')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl shadow-lg shadow-primary-500/20 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                {{ t('New Tool') }}
            </Link>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-200 dark:border-surface-800 p-4">
            <div class="flex flex-wrap gap-3">
                <input v-model="search" @keyup.enter="applyFilters" type="text" :placeholder="t('Search tools...')" class="flex-1 min-w-[200px] px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                <select v-model="selectedCategory" @change="applyFilters" class="px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm text-gray-700 dark:text-gray-300">
                    <option value="">{{ t('All Categories') }}</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <select v-model="selectedStatus" @change="applyFilters" class="px-4 py-2.5 bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl text-sm text-gray-700 dark:text-gray-300">
                    <option value="">{{ t('All Status') }}</option>
                    <option value="active">{{ t('Active') }}</option>
                    <option value="inactive">{{ t('Inactive') }}</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-surface-900 rounded-2xl border border-gray-200 dark:border-surface-800 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-surface-800 bg-gray-50/50 dark:bg-surface-800/50">
                        <th class="text-left px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400">{{ t('Tool') }}</th>
                        <th class="text-left px-4 py-3.5 font-semibold text-gray-600 dark:text-gray-400">{{ t('Category') }}</th>
                        <th class="text-center px-4 py-3.5 font-semibold text-gray-600 dark:text-gray-400">{{ t('Status') }}</th>
                        <th class="text-center px-4 py-3.5 font-semibold text-gray-600 dark:text-gray-400">{{ t('Uses') }}</th>
                        <th class="text-right px-6 py-3.5 font-semibold text-gray-600 dark:text-gray-400">{{ t('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-surface-800">
                    <tr v-for="tool in tools.data" :key="tool.id" class="hover:bg-gray-50/50 dark:hover:bg-surface-800/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div :style="{ background: tool.color || '#6366f1' }" class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold shrink-0 shadow-sm">
                                    {{ tool.name.charAt(0) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ tool.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ tool.slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span v-if="tool.category" class="text-xs font-medium px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-surface-800 text-gray-600 dark:text-gray-400">
                                {{ typeof tool.category === 'object' ? tool.category.name : tool.category }}
                            </span>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <button @click="toggleTool(tool.id)" :class="tool.is_active ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : 'bg-gray-100 dark:bg-surface-800 text-gray-500'" class="text-xs font-semibold px-3 py-1 rounded-full transition-colors">
                                {{ tool.is_active ? t('Active') : t('Inactive') }}
                            </button>
                        </td>
                        <td class="px-4 py-4 text-center text-gray-600 dark:text-gray-400 font-medium">
                            {{ formatNumber(Number(tool.usage_count || 0)) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link :href="route('admin.ai.tools.edit', tool.id)" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-800 text-gray-500 dark:text-gray-400 hover:text-primary-600 transition-colors" :title="t('Edit')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                </Link>
                                <button @click="deleteTool(tool.id)" class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-500 transition-colors" :title="t('Delete')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!tools.data?.length">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                            <svg class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                            <p class="font-medium">{{ t('No tools found') }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="tools.links?.length > 3" class="flex justify-center gap-1">
            <template v-for="link in tools.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" :class="[link.active ? 'bg-primary-600 text-white' : 'bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-surface-700']" class="px-3.5 py-2 text-sm rounded-lg border border-gray-200 dark:border-surface-700 transition-colors" v-html="link.label" />
                <span v-else class="px-3.5 py-2 text-sm text-gray-300 dark:text-gray-600" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
