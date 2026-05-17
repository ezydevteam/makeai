<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichEditor from '@/Components/RichEditor.vue';
import { ref } from 'vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    page: any;
    parents: any[];
}>();

const form = useForm({
    title: props.page?.title ?? '',
    slug: props.page?.slug ?? '',
    content: props.page?.content ?? '',
    excerpt: props.page?.excerpt ?? '',
    meta_title: props.page?.meta_title ?? '',
    meta_description: props.page?.meta_description ?? '',
    meta_keywords: props.page?.meta_keywords ?? '',
    template: props.page?.template ?? 'default',
    status: props.page?.status ?? 'published',
    published_at: props.page?.published_at ? new Date(props.page.published_at).toISOString().slice(0, 16) : null,
    password: props.page?.password ?? '',
    parent_id: props.page?.parent_id ?? null,
    sort_order: props.page?.sort_order ?? 0,
    show_title: props.page?.show_title ?? true,
    show_breadcrumbs: props.page?.show_breadcrumbs ?? true,
    show_featured_image: props.page?.show_featured_image ?? true,
    show_sidebar: props.page?.show_sidebar ?? false,
    sidebar_position: props.page?.sidebar_position ?? 'right',
    container_width: props.page?.container_width ?? 'default',
    featured_image: null as File | null,
    og_image: null as File | null,
});

const featuredPreview = ref(props.page?.featured_image ? `/storage/${props.page.featured_image}` : null);
const ogPreview = ref(props.page?.og_image ? `/storage/${props.page.og_image}` : null);

const handleFeaturedChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.featured_image = file;
        featuredPreview.value = URL.createObjectURL(file);
    }
};

const handleOgChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.og_image = file;
        ogPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    if (props.page) {
        form.post(route('admin.pages.update', props.page.id), {
            forceFormData: true,
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.pages.store'), {
            forceFormData: true,
        });
    }
};
</script>

<template>
    <Head :title="page ? 'Edit Page' : 'New Page'" />
    <div class="max-w-[1400px] mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <Link :href="route('admin.pages.index')" class="w-10 h-10 bg-white border border-gray-100 rounded-xl flex items-center justify-center text-gray-400 hover:text-gray-900 transition-all shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ page ? 'Edit Page' : 'Create Page' }}</h1>
                    <p class="text-sm text-gray-500 mt-1">Design and publish custom content for your site.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <select v-model="form.status" class="bg-white border-gray-200 rounded-xl text-xs font-bold uppercase tracking-widest px-4 py-2.5 focus:ring-primary-500">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="scheduled">Scheduled</option>
                </select>
                <button @click="submit" :disabled="form.processing" class="bg-primary-600 hover:bg-primary-500 text-white px-8 py-3 rounded-2xl font-bold transition-all shadow-lg shadow-primary-600/20">
                    {{ page ? 'Update Page' : 'Publish Page' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Editor Column -->
            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <div>
                        <input v-model="form.title" type="text" placeholder="Page Title" class="w-full text-4xl font-black border-none focus:ring-0 p-0 placeholder:text-gray-100">
                        <div class="flex items-center gap-2 mt-4 text-xs font-bold text-gray-400">
                            <span class="uppercase tracking-widest">Slug:</span>
                            <span class="text-gray-900 lowercase">{{ $page.props.app?.url }}/</span>
                            <input v-model="form.slug" type="text" placeholder="page-slug" class="border-none bg-gray-50 rounded-lg px-2 py-1 text-xs focus:ring-1 focus:ring-primary-500 min-w-[200px]">
                        </div>
                    </div>

                    <div>
                        <RichEditor v-model="form.content" height="500px" />
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Excerpt (Short Summary)</label>
                        <textarea v-model="form.excerpt" rows="3" class="w-full bg-gray-50 border-none rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all resize-none"></textarea>
                    </div>
                </div>

                <!-- SEO Card -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-8">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Search Engine Optimization</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Meta Title</label>
                            <input v-model="form.meta_title" type="text" class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Meta Description</label>
                            <textarea v-model="form.meta_description" rows="3" class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Meta Keywords</label>
                            <input v-model="form.meta_keywords" type="text" placeholder="keyword1, keyword2..." class="w-full bg-gray-50 border-none rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary-500 transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Column -->
            <div class="space-y-6">
                <!-- Page Attributes -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Attributes</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Template</label>
                            <select v-model="form.template" class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-xs focus:ring-2 focus:ring-primary-500">
                                <option value="default">Default</option>
                                <option value="full_width">Full Width</option>
                                <option value="blank">Blank Canvas</option>
                                <option value="landing">Landing Page</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Parent Page</label>
                            <select v-model="form.parent_id" class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-xs focus:ring-2 focus:ring-primary-500">
                                <option :value="null">None (Top Level)</option>
                                <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.title }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Display Order</label>
                            <input v-model="form.sort_order" type="number" class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-xs focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-4">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Featured Image</h3>
                    <div class="relative group">
                        <div class="aspect-video bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 flex items-center justify-center">
                            <img v-if="featuredPreview" :src="featuredPreview" class="w-full h-full object-cover">
                            <svg v-else class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        </div>
                        <label class="absolute inset-0 flex items-center justify-center bg-gray-900/40 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl cursor-pointer">
                            <input type="file" class="hidden" @change="handleFeaturedChange" accept="image/*">
                            <span class="text-white text-[10px] font-black uppercase tracking-widest">Update Image</span>
                        </label>
                    </div>
                </div>

                <!-- Layout Options -->
                <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm space-y-6">
                    <h3 class="font-black text-gray-900 uppercase tracking-widest text-xs">Layout Options</h3>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-gray-600 group-hover:text-gray-900">Show Title</span>
                            <button @click="form.show_title = !form.show_title" type="button" :class="form.show_title ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_title ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-gray-600 group-hover:text-gray-900">Show Sidebar</span>
                            <button @click="form.show_sidebar = !form.show_sidebar" type="button" :class="form.show_sidebar ? 'bg-primary-600' : 'bg-gray-200'" class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer rounded-full transition-colors">
                                <span :class="form.show_sidebar ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow transition mt-0.5 ml-0.5"></span>
                            </button>
                        </label>
                        <div v-if="form.show_sidebar" class="pt-2">
                            <select v-model="form.sidebar_position" class="w-full bg-gray-50 border-none rounded-xl px-4 py-2.5 text-xs">
                                <option value="left">Sidebar Left</option>
                                <option value="right">Sidebar Right</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
