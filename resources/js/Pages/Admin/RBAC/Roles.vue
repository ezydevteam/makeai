<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
    roles: any[];
    permissions: any;
}>();

const showModal = ref(false);
const isEditing = ref(false);
const currentRole = ref<any>(null);

const form = useForm({
    name: '',
    description: '',
    permissions: [] as number[],
});

const openModal = (role: any = null) => {
    isEditing.value = !!role;
    currentRole.value = role;
    form.reset();
    form.clearErrors();

    if (role) {
        form.name = role.name;
        form.description = role.description;
        form.permissions = role.permissions ? role.permissions.map((p: any) => p.id) : [];
    }
    
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    setTimeout(() => {
        form.reset();
        currentRole.value = null;
    }, 200);
};

const togglePermission = (id: number) => {
    const index = form.permissions.indexOf(id);
    if (index === -1) {
        form.permissions.push(id);
    } else {
        form.permissions.splice(index, 1);
    }
};

const submit = () => {
    if (isEditing.value) {
        form.post(route('admin.roles.update', currentRole.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    } else {
        form.post(route('admin.roles.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal()
        });
    }
};

const deleteRole = (role: any) => {
    if (role.is_system) {
        alert('System roles cannot be deleted.');
        return;
    }
    
    if (role.admins_count > 0) {
        alert('Cannot delete this role because it is assigned to one or more administrators.');
        return;
    }

    if (confirm(`Are you sure you want to delete the role ${role.name}?`)) {
        router.delete(route('admin.roles.delete', role.id), {
            preserveScroll: true
        });
    }
};

// Format permission name (e.g. from users.view to View)
const formatPermissionName = (name: string) => {
    const parts = name.split('.');
    if (parts.length > 1) {
        return parts[1].charAt(0).toUpperCase() + parts[1].slice(1);
    }
    return name;
};
</script>

<template>
    <Head title="Roles & Permissions" />
    <AdminLayout>
        <template #title>Roles & Permissions</template>

        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Access Control</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage roles and their assigned permissions</p>
                </div>
                <button @click="openModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors shadow-sm font-medium flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Create Role
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div v-for="role in roles" :key="role.id" class="bg-white dark:bg-surface-900 rounded-xl shadow-sm border border-gray-200 dark:border-surface-800 p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ role.name }}</h3>
                                <span v-if="role.is_system" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                                    System
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ role.description || 'No description provided.' }}</p>
                        </div>
                    </div>
                    
                    <div class="mt-auto pt-4 border-t border-gray-100 dark:border-surface-800 flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <span>{{ role.admins_count }} {{ role.admins_count === 1 ? 'user' : 'users' }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="openModal(role)" class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 text-sm font-medium">Edit</button>
                            <button v-if="!role.is_system" @click="deleteRole(role)" class="text-danger-600 hover:text-danger-700 dark:text-danger-400 dark:hover:text-danger-300 text-sm font-medium ml-2">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-surface-900 rounded-2xl w-full max-w-4xl shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-surface-800 flex justify-between items-center shrink-0">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ isEditing ? 'Edit Role' : 'Create Role' }}</h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                
                <div class="p-6 overflow-y-auto">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Role Name <span class="text-danger-500">*</span></label>
                                <input v-model="form.name" type="text" :disabled="currentRole?.is_system" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500 disabled:opacity-50" required>
                                <p v-if="form.errors.name" class="mt-1 text-sm text-danger-500">{{ form.errors.name }}</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <input v-model="form.description" type="text" class="w-full rounded-xl border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                <p v-if="form.errors.description" class="mt-1 text-sm text-danger-500">{{ form.errors.description }}</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-md font-bold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-100 dark:border-surface-800">Permissions Setup</h4>
                            
                            <div v-if="currentRole?.slug === 'super-admin'" class="p-4 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 rounded-xl mb-4 border border-indigo-100 dark:border-indigo-800/30 flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <div>
                                    <h5 class="font-bold">Super Admin Bypass</h5>
                                    <p class="text-sm mt-1">The Super Admin role automatically bypasses all permission checks in the system. Checkboxes below are disabled for this role.</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <div v-for="(perms, group) in permissions" :key="group" class="bg-gray-50 dark:bg-surface-800/50 rounded-xl p-4 border border-gray-100 dark:border-surface-800">
                                    <h5 class="font-bold text-gray-800 dark:text-gray-200 uppercase text-xs tracking-wider mb-3">{{ String(group).replace('_', ' ') }}</h5>
                                    
                                    <div class="space-y-2">
                                        <label v-for="permission in perms" :key="permission.id" class="flex items-center gap-3 cursor-pointer group">
                                            <div class="relative flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input 
                                                        type="checkbox" 
                                                        :checked="currentRole?.slug === 'super-admin' ? true : form.permissions.includes(permission.id)"
                                                        @change="togglePermission(permission.id)"
                                                        :disabled="currentRole?.slug === 'super-admin'"
                                                        class="w-4 h-4 text-primary-600 border-gray-300 dark:border-surface-600 rounded focus:ring-primary-500 dark:bg-surface-800 disabled:opacity-50"
                                                    >
                                                </div>
                                                <div class="ml-2 text-sm">
                                                    <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors" :class="{'opacity-75': currentRole?.slug === 'super-admin'}">
                                                        {{ formatPermissionName(permission.name) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="p-6 border-t border-gray-100 dark:border-surface-800 bg-gray-50 dark:bg-surface-800/50 flex justify-end gap-3 shrink-0">
                    <button type="button" @click="closeModal" class="px-4 py-2 text-gray-600 dark:text-gray-300 bg-white dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 transition-colors font-medium">
                        Cancel
                    </button>
                    <button type="button" @click="submit" :disabled="form.processing" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors shadow-sm font-medium disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save Role' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
