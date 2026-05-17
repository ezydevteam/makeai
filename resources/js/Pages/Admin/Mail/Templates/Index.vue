<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps<{
    templates: Array<{
        id: number,
        slug: string,
        name: string,
        subject: string,
        category: string,
        is_active: boolean,
        is_system: boolean
    }>
}>();
</script>

<template>
    <Head title="Mail Templates — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Mail Templates</h1>
                <p class="text-sm text-gray-500 mt-1">Manage system notifications and custom email communications.</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Template Name</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Category</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Subject</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in templates" :key="t.id" class="hover:bg-gray-50/50 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900">{{ t.name }}</div>
                            <div class="text-[10px] font-mono text-gray-400">{{ t.slug }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-gray-100 text-gray-600 text-[10px] font-black uppercase tracking-widest rounded-lg">
                                {{ t.category }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-xs text-gray-600 max-w-xs truncate">{{ t.subject }}</div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span :class="t.is_active ? 'bg-success-100 text-success-600' : 'bg-gray-100 text-gray-400'" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest rounded-lg">
                                {{ t.is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="route('admin.mail.templates.edit', t.id)" class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-primary-600 transition-all shadow-md shadow-gray-900/10">
                                Edit Template
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
