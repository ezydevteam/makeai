<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    users: any,
    filters: any,
    plans: Array<{ id: number, name: string }>
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const plan = ref(props.filters.plan || '');
const selectedIds = ref<number[]>([]);
const selectAll = ref(false);

const debounce = (fn: Function, delay: number) => {
    let timeoutId: ReturnType<typeof setTimeout>;
    return (...args: any[]) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

const updateFilters = debounce(() => {
    router.get(route('admin.users.index'), {
        search: search.value,
        status: status.value,
        plan: plan.value
    }, { preserveState: true, replace: true });
}, 300);

watch([search, status, plan], updateFilters);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.users.data.map((u: any) => u.id);
    } else {
        selectedIds.value = [];
    }
};

const bulkForm = useForm({
    ids: [] as number[],
    action: '',
    value: ''
});

const runBulkAction = (action: string) => {
    if (selectedIds.value.length === 0) return;
    
    let value = '';
    if (action === 'add_credits') {
        value = prompt('Enter credits to add:') || '';
        if (!value) return;
    }

    if (!confirm(`Are you sure you want to perform "${action}" on ${selectedIds.value.length} users?`)) return;

    bulkForm.ids = selectedIds.value;
    bulkForm.action = action;
    bulkForm.value = value;
    bulkForm.post(route('admin.users.bulk'), {
        onSuccess: () => {
            selectedIds.value = [];
            selectAll.value = false;
        }
    });
};
</script>

<template>
    <Head title="User Management — Admin" />
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage platform users, credits, and subscription states.</p>
            </div>
            <div class="flex items-center gap-3">
                <a :href="route('admin.users.export')" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </a>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6 shadow-sm flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[240px] relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </span>
                <input v-model="search" type="text" placeholder="Search by name, email or ULID..." class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none transition-all" />
            </div>

            <select v-model="status" class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-900 focus:outline-none">
                <option value="">All Status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>

            <select v-model="plan" class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-sm text-gray-900 focus:outline-none">
                <option value="">All Plans</option>
                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <!-- Bulk Actions -->
        <div v-if="selectedIds.length > 0" class="bg-primary-50 border border-primary-100 rounded-xl px-4 py-3 mb-6 flex items-center justify-between animate-in fade-in slide-in-from-top-2">
            <span class="text-sm font-medium text-primary-700">{{ selectedIds.length }} users selected</span>
            <div class="flex items-center gap-2">
                <button @click="runBulkAction('activate')" class="px-3 py-1.5 bg-white text-success-600 border border-success-100 text-xs font-bold rounded-lg hover:bg-success-50 transition-colors">ACTIVATE</button>
                <button @click="runBulkAction('deactivate')" class="px-3 py-1.5 bg-white text-warning-600 border border-warning-100 text-xs font-bold rounded-lg hover:bg-warning-50 transition-colors">DEACTIVATE</button>
                <button @click="runBulkAction('add_credits')" class="px-3 py-1.5 bg-white text-primary-600 border border-primary-200 text-xs font-bold rounded-lg hover:bg-primary-50 transition-colors">ADD CREDITS</button>
                <button @click="runBulkAction('delete')" class="px-3 py-1.5 bg-white text-danger-600 border border-danger-100 text-xs font-bold rounded-lg hover:bg-danger-50 transition-colors">DELETE</button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 w-10">
                            <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        </th>
                        <th class="px-6 py-4 font-semibold text-gray-700 uppercase tracking-wider">User</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 uppercase tracking-wider">Credits</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 uppercase tracking-wider">Plan</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 uppercase tracking-wider">Joined</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/50 transition-colors group">
                        <td class="px-6 py-4">
                            <input type="checkbox" :value="user.id" v-model="selectedIds" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center text-white font-bold text-xs">
                                    {{ user.name.charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 truncate">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-mono text-gray-900">{{ parseFloat(user.credits).toFixed(2) }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span v-if="user.plan" class="px-2.5 py-1 bg-primary-50 text-primary-700 text-xs font-medium rounded-full border border-primary-100">
                                {{ user.plan.name }}
                            </span>
                            <span v-else class="text-gray-400 text-xs italic">Free Tier</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1.5">
                                <div :class="user.is_active ? 'bg-success-500' : 'bg-gray-300'" class="w-2 h-2 rounded-full"></div>
                                <span :class="user.is_active ? 'text-success-700' : 'text-gray-500'" class="text-xs font-medium">{{ user.is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Link :href="route('admin.users.show', user.ulid)" class="p-2 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-all" title="Edit User">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                </Link>
                                <form @submit.prevent="router.post(route('admin.users.impersonate', user.ulid))" class="inline">
                                    <button type="submit" class="p-2 text-gray-400 hover:text-accent-600 hover:bg-accent-50 rounded-lg transition-all" title="Impersonate">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 italic">No users found matching your criteria.</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="users.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-500">Showing {{ users.from }} to {{ users.to }} of {{ users.total }} users</p>
                <div class="flex items-center gap-1">
                    <Link v-for="(link, i) in users.links" :key="i" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50', !link.url ? 'opacity-50 cursor-not-allowed' : '']" class="px-3 py-1.5 text-xs font-bold border rounded-lg transition-all" />
                </div>
            </div>
        </div>
    </div>
</template>
