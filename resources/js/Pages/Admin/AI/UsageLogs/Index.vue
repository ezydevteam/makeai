<template>
    <Head title="AI Usage Logs" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        AI Usage Logs
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Monitor AI token consumption and generations across all tools.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-64">
                            <input
                                v-model="form.tool_slug"
                                type="text"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                placeholder="Search by tool slug..."
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="w-48">
                            <select
                                v-model="form.provider"
                                class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 text-sm focus:border-primary-500 focus:outline-none dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                @change="applyFilters"
                            >
                                <option value="">All Providers</option>
                                <option value="openai">OpenAI</option>
                                <option value="anthropic">Anthropic</option>
                                <option value="gemini">Gemini</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3">User</th>
                                    <th scope="col" class="px-6 py-3">Provider</th>
                                    <th scope="col" class="px-6 py-3">Tool</th>
                                    <th scope="col" class="px-6 py-3">Tokens</th>
                                    <th scope="col" class="px-6 py-3">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="log in logs.data"
                                    :key="log.id"
                                    class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                        {{ log.user?.name || 'Guest' }}
                                        <div class="text-xs font-normal text-gray-500">{{ log.user?.email }}</div>
                                    </th>
                                    <td class="px-6 py-4">
                                        {{ log.provider }}
                                        <div class="text-xs text-gray-500">{{ log.model }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            {{ log.tool_slug || 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ log.total_tokens }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </td>
                                </tr>
                                <tr v-if="logs.data.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No usage logs found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-4" v-if="logs.links && logs.links.length > 3">
                    <Pagination :links="logs.links" />
                </div>

            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const form = useForm({
    tool_slug: props.filters.tool_slug || '',
    provider: props.filters.provider || '',
});

const applyFilters = () => {
    form.get(route('admin.ai.logs.index'), {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>
