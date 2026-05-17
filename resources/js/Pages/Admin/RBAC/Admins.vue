<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
    admins: any;
    roles: any[];
    filters: any;
}>();

const showModal = ref(false);
const isEditing = ref(false);
const currentAdmin = ref<any>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    is_active: true,
});

const openModal = (admin: any = null) => {
    isEditing.value = !!admin;
    currentAdmin.value = admin;
    form.reset();
    form.clearErrors();

    if (admin) {
        form.name = admin.name;
        form.email = admin.email;
        form.role_id = admin.role_id;
        form.is_active = admin.is_active;
    } else {
        form.is_active = true;
    }
    
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => {
        form.reset();
        currentAdmin.value = null;
    }, 200);
};

const submit = () => {
    if (isEditing.value) {
        form.post(route('admin.admins.update', currentAdmin.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('admin.admins.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    }
};

const deleteAdmin = (admin: any) => {
    if (confirm(`Are you sure you want to delete administrator ${admin.name}?`)) {
        router.delete(route('admin.admins.delete', admin.id), {
            preserveScroll: true
        });
    }
};
</script>

<template>
    <Head title="Administrators" />
    <AdminLayout>
        <template #title>Administrators</template>

        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Admin Management</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage team members and their roles</p>
                </div>
                <button @click="openModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors shadow-sm font-medium flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add Administrator
                </button>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-surface-900 rounded-xl shadow-sm border border-gray-200 dark:border-surface-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Login</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-surface-800">
                            <tr v-for="admin in admins.data" :key="admin.id" class="hover:bg-gray-50 dark:hover:bg-surface-800/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ admin.name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ admin.email }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="[admin.role?.slug === 'super-admin' ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400' : 'bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-400']">
                                        {{ admin.role?.name || 'None' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="[admin.is_active ? 'bg-success-100 text-success-800 dark:bg-success-900/30 dark:text-success-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400']">
                                        {{ admin.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ admin.last_login_at ? new Date(admin.last_login_at).toLocaleDateString() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <button @click="openModal(admin)" class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button @click="deleteAdmin(admin)" class="text-gray-400 hover:text-danger-600 dark:hover:text-danger-400 transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="admins.data.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No administrators found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="admins.links && admins.links.length > 3" class="mt-6 flex flex-wrap gap-1">
                <template v-for="(link, k) in admins.links" :key="k">
                    <div v-if="link.url === null" class="px-4 py-2 text-sm text-gray-400 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-lg cursor-not-allowed" v-html="link.label"></div>
                    <Link v-else :href="link.url" class="px-4 py-2 text-sm rounded-lg transition-colors border" :class="[link.active ? 'bg-primary-600 text-white border-primary-600 font-bold shadow-sm' : 'bg-white dark:bg-surface-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-surface-800 hover:bg-gray-50 dark:hover:bg-surface-800']" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-surface-900 rounded-2xl w-full max-w-lg shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-surface-800 flex justify-between items-center shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ isEditing ? 'Edit Administrator' : 'Add Administrator' }}</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-danger-500">*</span></label>
                            <input v-model="form.name" type="text" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500" required>
                            <p v-if="form.errors.name" class="mt-1 text-sm text-danger-500">{{ form.errors.name }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-danger-500">*</span></label>
                            <input v-model="form.email" type="email" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500" required>
                            <p v-if="form.errors.email" class="mt-1 text-sm text-danger-500">{{ form.errors.email }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Role <span class="text-danger-500">*</span></label>
                            <select v-model="form.role_id" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500" required>
                                <option value="" disabled>Select a role...</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                            <p v-if="form.errors.role_id" class="mt-1 text-sm text-danger-500">{{ form.errors.role_id }}</p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Password <span v-if="!isEditing" class="text-danger-500">*</span></label>
                                <input v-model="form.password" type="password" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500" :required="!isEditing">
                                <p v-if="isEditing" class="mt-1 text-xs text-gray-500">Leave blank to keep current</p>
                                <p v-if="form.errors.password" class="mt-1 text-sm text-danger-500">{{ form.errors.password }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                                <input v-model="form.password_confirmation" type="password" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500" :required="!isEditing || form.password.length > 0">
                            </div>
                        </div>

                        <div class="pt-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
                                    <div class="w-11 h-6 bg-gray-200 dark:bg-surface-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-success-500"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Account Active</span>
                            </label>
                        </div>
                    </form>
                </div>
                
                <div class="p-6 border-t border-gray-100 dark:border-surface-800 bg-gray-50 dark:bg-surface-800/50 flex justify-end gap-3 shrink-0">
                    <button type="button" @click="closeModal" class="px-4 py-2 text-gray-600 dark:text-gray-300 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors font-medium">
                        Cancel
                    </button>
                    <button type="button" @click="submit" :disabled="form.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors shadow-sm font-medium disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Administrator' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
