<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    language: any,
    translations: any,
    filters: any
}>();

const search = ref(props.filters.search || '');

const debounce = (fn: Function, delay: number) => {
    let timeoutId: ReturnType<typeof setTimeout>;
    return (...args: any[]) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};

watch(search, debounce((val: string) => {
    router.get(route('admin.translations.index', props.language.id), { search: val }, { preserveState: true, replace: true });
}, 300));

const updateTranslation = (translation: any) => {
    useForm({ value: translation.value }).post(route('admin.translations.update', translation.id));
};

const aiTranslate = (id: number) => {
    router.post(route('admin.translations.ai', id), {}, { preserveScroll: true });
};

const aiTranslateAll = () => {
    if (confirm('AI will attempt to translate missing strings. Continue?')) {
        router.post(route('admin.translations.ai_all', props.language.id));
    }
};
</script>

<template>
    <Head :title="`Translations — ${language.name}`" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center gap-3 mb-8">
            <Link :href="route('admin.languages.index')" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-500 hover:text-gray-900 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </Link>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900">{{ language.name }} Translations</h1>
                <p class="text-sm text-gray-500">Edit phrases and use AI to fill missing translations.</p>
            </div>
            <button @click="aiTranslateAll" class="px-4 py-2 bg-accent-600 text-white text-xs font-bold rounded-lg hover:bg-accent-500 transition-all shadow-lg shadow-accent-500/20 flex items-center gap-2">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                AI TRANSLATE MISSING
            </button>
        </div>

        <!-- Search Bar -->
        <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6 shadow-sm relative">
            <span class="absolute inset-y-0 left-7 flex items-center text-gray-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </span>
            <input v-model="search" type="text" placeholder="Search by key or translation..." class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none transition-all" />
        </div>

        <!-- Translations Grid -->
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="divide-y divide-gray-100">
                <div v-for="t in translations.data" :key="t.id" class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4 hover:bg-gray-50/50 transition-colors group">
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Original / Key</label>
                        <p class="text-sm font-medium text-gray-900 break-words">{{ t.key }}</p>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center justify-between">
                            Translation
                            <button @click="aiTranslate(t.id)" class="text-accent-600 hover:text-accent-700 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 lowercase">
                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                                AI auto-fill
                            </button>
                        </label>
                        <div class="flex gap-2">
                            <textarea v-model="t.value" rows="1" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-900 focus:border-primary-500 focus:outline-none transition-all resize-none overflow-hidden" @blur="updateTranslation(t)"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="translations.links.length > 3" class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-500">Showing {{ translations.from }} to {{ translations.to }} of {{ translations.total }} phrases</p>
                <div class="flex items-center gap-1">
                    <Link v-for="(link, i) in translations.links" :key="i" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50', !link.url ? 'opacity-50 cursor-not-allowed' : '']" class="px-3 py-1.5 text-xs font-bold border rounded-lg transition-all" />
                </div>
            </div>
        </div>
    </div>
</template>
