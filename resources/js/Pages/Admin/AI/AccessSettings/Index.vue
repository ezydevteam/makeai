<template>
    <Head :title="t('AI Access Settings')" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ t('AI Access Settings') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('Manage visibility and access requirements for all AI tools.') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Preset Actions Card -->
                <div class="p-6 mb-6 overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">{{ t('Global Presets') }}</h3>
                    <div class="flex flex-wrap gap-4">
                        <button @click="applyPreset('all_public')" class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
                            {{ t('Make All Public') }}
                        </button>
                        <button @click="applyPreset('all_login')" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                            {{ t('Require Login for All') }}
                        </button>
                        <button @click="applyPreset('all_pro')" class="px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium">
                            {{ t('Pro Only for All') }}
                        </button>
                        <button @click="applyPreset('reset_inherit')" class="px-5 py-2.5 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors text-sm font-medium dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            {{ t('Reset to Inherit') }}
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-64">
                            <input
                                v-model="form.search"
                                type="text"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                :placeholder="t('Search tools...')"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="w-48">
                            <select
                                v-model="form.category"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                @change="applyFilters"
                            >
                                <option value="">{{ t('All Categories') }}</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Bulk Action Dropdown -->
                        <div class="flex items-center gap-2" v-if="selectedIds.length > 0">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t(':count selected', { count: selectedIds.length }) }}
                            </span>
                            <select
                                v-model="bulkAction"
                                class="w-48 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                            >
                                <option value="">{{ t('Select Access Level') }}</option>
                                <option v-for="level in accessLevels" :key="level.value" :value="level.value">
                                    {{ level.label }}
                                </option>
                            </select>
                            <button @click="applyBulkUpdate" :disabled="!bulkAction" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium disabled:opacity-50">
                                {{ t('Apply') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th scope="col" class="p-4 w-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                class="w-4 h-4 bg-gray-50 border-gray-300 rounded text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                                :checked="isAllSelected"
                                                @change="toggleAll"
                                            />
                                        </div>
                                    </th>
                                    <th scope="col" class="px-6 py-3">{{ t('Tool Name') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Category') }}</th>
                                    <th scope="col" class="px-6 py-3">{{ t('Current Access') }}</th>
                                    <th scope="col" class="px-6 py-3 text-right">{{ t('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="template in templates.data"
                                    :key="template.id"
                                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <td class="w-4 p-4">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                v-model="selectedIds"
                                                :value="template.id"
                                                class="w-4 h-4 bg-gray-50 border-gray-300 rounded text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                            />
                                        </div>
                                    </td>
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {{ template.name }}
                                    </th>
                                    <td class="px-6 py-4">
                                        {{ template.tool_category?.name || t('Uncategorized') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getAccessLevelBadgeClass(template.access_level)">
                                            {{ getAccessLevelLabel(template.access_level) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <Link
                                            :href="route('admin.ai.templates.edit', template.id)"
                                            class="font-medium text-primary-600 dark:text-primary-500 hover:underline"
                                        >
                                            {{ t('Edit') }}
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="templates.data.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        {{ t('No tools found.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-4" v-if="templates.links && templates.links.length > 3">
                    <Pagination :links="templates.links" />
                </div>

            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useTranslate } from '@/Composables/useTranslate';

const { t } = useTranslate();

const props = defineProps({
    templates: Object,
    categories: Array,
    filters: Object,
    globalDefault: String,
});

const form = useForm({
    search: props.filters.search || '',
    category: props.filters.category || '',
});

const selectedIds = ref([]);
const bulkAction = ref('');

const accessLevels = [
    { value: 'inherit', label: t('Inherit (Default)') },
    { value: 'public', label: t('Public (No Login)') },
    { value: 'login_required', label: t('Login Required') },
    { value: 'free_plan', label: t('Free Plan') },
    { value: 'pro_plan', label: t('Pro Plan') },
];

const isAllSelected = computed(() => {
    return props.templates.data.length > 0 && selectedIds.value.length === props.templates.data.length;
});

const toggleAll = (e) => {
    if (e.target.checked) {
        selectedIds.value = props.templates.data.map(t => t.id);
    } else {
        selectedIds.value = [];
    }
};

const getAccessLevelLabel = (level) => {
    return accessLevels.find(l => l.value === level)?.label || level;
};

const getAccessLevelBadgeClass = (level) => {
    switch (level) {
        case 'public': return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'login_required': return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'pro_plan': return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'free_plan': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        default: return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
};

const applyFilters = () => {
    form.get(route('admin.ai.access.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};

const applyPreset = (presetValue) => {
    if (confirm(t('Are you sure you want to apply this preset to ALL tools?'))) {
        router.post(route('admin.ai.access.preset'), { preset: presetValue }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            }
        });
    }
};

const applyBulkUpdate = () => {
    if (selectedIds.value.length === 0 || !bulkAction.value) return;

    if (confirm(t('Update access level to ":level" for :count tools?', { level: getAccessLevelLabel(bulkAction.value), count: selectedIds.value.length }))) {
        router.post(route('admin.ai.access.bulk'), {
            template_ids: selectedIds.value,
            access_level: bulkAction.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                bulkAction.value = '';
            }
        });
    }
};
</script>
