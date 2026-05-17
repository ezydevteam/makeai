<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    ad: any
}>();

const form = useForm({
    name: props.ad?.name ?? '',
    type: props.ad?.type ?? 'image',
    placement: props.ad?.placement ?? 'sidebar',
    content: props.ad?.content ?? '',
    link_url: props.ad?.link_url ?? '',
    is_active: props.ad?.is_active ?? true,
    starts_at: props.ad?.starts_at ? new Date(props.ad.starts_at).toISOString().slice(0, 16) : null,
    ends_at: props.ad?.ends_at ? new Date(props.ad.ends_at).toISOString().slice(0, 16) : null,
    image: null as File | null,
});

const previewUrl = ref(props.ad?.image_path ? `/storage/${props.ad.image_path}` : null);

const handleFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.image = file;
        previewUrl.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    if (props.ad) {
        form.post(route('admin.ads.update', props.ad.id), {
            preserveScroll: true,
            forceFormData: true,
        });
    } else {
        form.post(route('admin.ads.store'), {
            forceFormData: true,
        });
    }
};
</script>

<template>
    <Head :title="ad ? 'Edit Ad' : 'Create Ad'" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ ad ? 'Edit Advertisement' : 'Create New Advertisement' }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure your ad campaign settings and placement.</p>
            </div>
            <button @click="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                {{ ad ? 'Save Changes' : 'Create Ad' }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-surface-900 p-8 rounded-3xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-8">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Campaign Name</label>
                            <input v-model="form.name" type="text" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Ad Type</label>
                            <select v-model="form.type" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                                <option value="image">Image Banner</option>
                                <option value="script">Script / AdSense</option>
                            </select>
                        </div>
                    </div>

                    <!-- Placement -->
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Placement Zone</label>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <button v-for="p in ['top', 'bottom', 'sidebar', 'feed', 'blog_side']" :key="p" @click="form.placement = p" type="button" :class="form.placement === p ? 'bg-primary-600 text-white shadow-lg shadow-primary-600/20' : 'bg-gray-50 text-gray-400 hover:bg-gray-100'" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                                {{ p.replace('_', ' ') }}
                            </button>
                        </div>
                    </div>

                    <!-- Image Ad Fields -->
                    <div v-if="form.type === 'image'" class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Banner Image</label>
                            <div class="flex items-center gap-6">
                                <div v-if="previewUrl" class="w-32 h-32 rounded-2xl overflow-hidden border border-gray-100 bg-gray-50 shadow-inner">
                                    <img :src="previewUrl" class="w-full h-full object-cover">
                                </div>
                                <label class="flex-1 border-2 border-dashed border-gray-100 rounded-3xl p-8 flex flex-col items-center justify-center cursor-pointer hover:border-primary-200 transition-all group">
                                    <input type="file" class="hidden" @change="handleFileChange" accept="image/*">
                                    <svg class="w-8 h-8 text-gray-300 group-hover:text-primary-500 mb-2 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-xs font-bold text-gray-400 group-hover:text-primary-600">Click to upload banner</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Target URL</label>
                            <input v-model="form.link_url" type="url" placeholder="https://..." class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                        </div>
                    </div>

                    <!-- Script Ad Fields -->
                    <div v-if="form.type === 'script'">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Ad Code (HTML/Script)</label>
                        <textarea v-model="form.content" rows="10" placeholder="Paste AdSense or HTML code here..." class="w-full bg-gray-900 text-white border-none rounded-2xl px-4 py-3 text-sm font-mono focus:ring-2 focus:ring-primary-500 transition-all resize-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Schedule Card -->
                <div class="bg-white dark:bg-surface-900 p-8 rounded-3xl border border-gray-100 dark:border-surface-700 shadow-sm space-y-6">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4">Scheduling</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Start Date</label>
                            <input v-model="form.starts_at" type="datetime-local" class="w-full bg-gray-50 border-none rounded-xl px-4 py-2 text-xs focus:ring-2 focus:ring-primary-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">End Date</label>
                            <input v-model="form.ends_at" type="datetime-local" class="w-full bg-gray-50 border-none rounded-xl px-4 py-2 text-xs focus:ring-2 focus:ring-primary-500 transition-all">
                        </div>
                    </div>
                    <div class="pt-4 flex items-center gap-3 border-t border-gray-50">
                        <button @click="form.is_active = !form.is_active" type="button" :class="form.is_active ? 'bg-success-600' : 'bg-gray-200'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                            <span :class="form.is_active ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                        </button>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">Ad Active</span>
                    </div>
                </div>

                <!-- Guidance Card -->
                <div class="bg-primary-600 p-8 rounded-3xl shadow-xl shadow-primary-600/20 text-white space-y-4">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h4 class="font-black uppercase tracking-widest text-xs mb-1">Ad Specifications</h4>
                        <p class="text-xs text-white/80 leading-relaxed">Top/Bottom: 728x90<br>Sidebar: 300x250<br>Feed: 600x200</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
