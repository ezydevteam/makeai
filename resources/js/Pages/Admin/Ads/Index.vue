<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps<{
    ads: any[]
}>();

const toggleAd = (id: number) => {
    router.post(route('admin.ads.toggle', id), {}, { preserveScroll: true });
};

const deleteAd = (id: number) => {
    if (confirm('Are you sure you want to delete this advertisement?')) {
        router.delete(route('admin.ads.delete', id));
    }
};
</script>

<template>
    <Head title="Ad Management — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Advertisement Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage banners, AdSense scripts, and promotional placements.</p>
            </div>
            <Link :href="route('admin.ads.create')" class="bg-primary-600 hover:bg-primary-500 text-white px-6 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Create New Ad
            </Link>
        </div>

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100">
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Name & Type</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Placement</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Stats</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="ad in ads" :key="ad.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900">{{ ad.name }}</div>
                            <div class="text-[10px] text-gray-400 uppercase font-black">{{ ad.type }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                {{ ad.placement.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-6">
                                <div class="text-center">
                                    <div class="text-xs font-bold text-gray-900">{{ ad.views.toLocaleString() }}</div>
                                    <div class="text-[8px] text-gray-400 uppercase font-black">Views</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs font-bold text-gray-900">{{ ad.clicks.toLocaleString() }}</div>
                                    <div class="text-[8px] text-gray-400 uppercase font-black">Clicks</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs font-bold text-primary-600">{{ ad.ctr }}%</div>
                                    <div class="text-[8px] text-gray-400 uppercase font-black">CTR</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button @click="toggleAd(ad.id)" :class="ad.is_active ? 'bg-success-50 text-success-700' : 'bg-gray-50 text-gray-400'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest transition-all">
                                {{ ad.is_active ? 'Active' : 'Paused' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link :href="route('admin.ads.edit', ad.id)" class="p-2 text-gray-400 hover:text-primary-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </Link>
                                <button @click="deleteAd(ad.id)" class="p-2 text-gray-400 hover:text-danger-600 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="ads.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">No advertisements found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
