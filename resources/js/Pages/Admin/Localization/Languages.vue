<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    languages: Array<any>
}>();

const showAddModal = ref(false);
const editingLang = ref<any>(null);

const form = useForm({
    name: '',
    code: '',
    flag: '',
    is_rtl: false,
    is_active: true
});

const openAddModal = () => {
    editingLang.value = null;
    form.reset();
    showAddModal.value = true;
};

const openEditModal = (lang: any) => {
    editingLang.value = lang;
    form.name = lang.name;
    form.code = lang.code;
    form.flag = lang.flag;
    form.is_rtl = lang.is_rtl;
    form.is_active = lang.is_active;
    showAddModal.value = true;
};

const submit = () => {
    if (editingLang.value) {
        form.post(route('admin.languages.update', editingLang.value.id), {
            onSuccess: () => showAddModal.value = false
        });
    } else {
        form.post(route('admin.languages.store'), {
            onSuccess: () => showAddModal.value = false
        });
    }
};

const setDefault = (id: number) => {
    useForm({}).post(route('admin.languages.default', id));
};

const deleteLang = (id: number) => {
    if (confirm('Delete this language and all its translations?')) {
        useForm({}).delete(route('admin.languages.delete', id));
    }
};
</script>

<template>
    <Head title="Languages — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Languages</h1>
                <p class="text-sm text-gray-500 mt-1">Manage platform languages and RTL settings.</p>
            </div>
            <button @click="openAddModal" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">
                ADD LANGUAGE
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="lang in languages" :key="lang.id" :class="[lang.is_default ? 'border-primary-500 ring-1 ring-primary-500' : 'border-gray-200']" class="bg-white border rounded-2xl p-6 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div class="text-3xl">{{ lang.flag || '🌐' }}</div>
                    <div class="flex gap-2">
                        <span v-if="lang.is_default" class="px-2 py-0.5 bg-primary-50 text-primary-600 text-[10px] font-bold rounded-full border border-primary-100">DEFAULT</span>
                        <span v-if="lang.is_rtl" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded-full">RTL</span>
                        <span v-if="!lang.is_active" class="px-2 py-0.5 bg-danger-50 text-danger-600 text-[10px] font-bold rounded-full">INACTIVE</span>
                    </div>
                </div>

                <h3 class="text-lg font-bold text-gray-900">{{ lang.name }}</h3>
                <p class="text-sm text-gray-400 font-mono mb-6">{{ lang.code }}</p>

                <div class="mt-auto pt-6 border-t border-gray-100 flex items-center justify-between">
                    <Link :href="route('admin.translations.index', lang.id)" class="text-sm font-bold text-primary-600 hover:text-primary-700">
                        Translations
                    </Link>
                    <div class="flex items-center gap-3">
                        <button v-if="!lang.is_default" @click="setDefault(lang.id)" class="text-xs font-bold text-gray-400 hover:text-gray-900 transition-colors">Set Default</button>
                        <button @click="openEditModal(lang)" class="text-xs font-bold text-gray-400 hover:text-primary-600 transition-colors">Edit</button>
                        <button v-if="!lang.is_default" @click="deleteLang(lang.id)" class="text-xs font-bold text-gray-400 hover:text-danger-600 transition-colors">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">{{ editingLang ? 'Edit' : 'Add' }} Language</h3>
                    <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submit" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
                            <input v-model="form.name" type="text" placeholder="e.g. French" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">ISO Code</label>
                            <input v-model="form.code" type="text" placeholder="fr" :disabled="editingLang" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none disabled:opacity-50" required />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Flag (Emoji)</label>
                        <input v-model="form.flag" type="text" placeholder="🇫🇷" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                    </div>
                    <div class="flex items-center gap-6 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.is_rtl" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">RTL Layout</span>
                        </label>
                        <label v-if="editingLang" class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">Active</span>
                        </label>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="form.processing" class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ form.processing ? 'Processing...' : (editingLang ? 'Update Language' : 'Create Language') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
