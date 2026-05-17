<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    menus: Array<any>,
    pages: Array<any>
}>();

const selectedMenu = ref<any>(props.menus[0] || null);
const showAddMenuModal = ref(false);
const showAddItemModal = ref(false);

const menuForm = useForm({
    name: '',
    slug: ''
});

const itemForm = useForm({
    label: '',
    type: 'url',
    url: '',
    page_id: null,
    route_name: '',
    target: '_self',
    icon: '',
    sort_order: 0
});

const submitMenu = () => {
    menuForm.post(route('admin.menus.store'), {
        onSuccess: () => {
            showAddMenuModal.value = false;
            menuForm.reset();
        }
    });
};

const addItem = () => {
    itemForm.post(route('admin.menus.item.store', selectedMenu.value.id), {
        onSuccess: () => {
            showAddItemModal.value = false;
            itemForm.reset();
        }
    });
};

const deleteItem = (id: number) => {
    if (confirm('Remove this menu item?')) {
        useForm({}).delete(route('admin.menus.item.delete', id));
    }
};

const deleteMenu = (id: number) => {
    if (confirm('Delete this entire menu?')) {
        useForm({}).delete(route('admin.menus.delete', id));
    }
};
</script>

<template>
    <Head title="Menu Builder — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Menu Builder</h1>
                <p class="text-sm text-gray-500 mt-1">Structure your site navigation and links.</p>
            </div>
            <button @click="showAddMenuModal = true" class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">
                NEW MENU
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Menus List -->
            <div class="space-y-4">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest px-2">Navigation Menus</h3>
                <div class="space-y-1">
                    <button v-for="menu in menus" :key="menu.id" @click="selectedMenu = menu" :class="selectedMenu?.id === menu.id ? 'bg-primary-50 text-primary-700 border-primary-100' : 'bg-white text-gray-600 border-transparent hover:bg-gray-50'" class="w-full text-left px-4 py-3 rounded-xl border transition-all flex items-center justify-between group">
                        <span class="font-bold text-sm">{{ menu.name }}</span>
                        <svg v-if="selectedMenu?.id === menu.id" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" /></svg>
                    </button>
                </div>
            </div>

            <!-- Menu Structure -->
            <div class="lg:col-span-3">
                <div v-if="selectedMenu" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-50 bg-gray-50/30 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">{{ selectedMenu.name }} Structure</h3>
                            <p class="text-[10px] text-gray-400 font-mono tracking-widest uppercase">SLUG: {{ selectedMenu.slug }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="showAddItemModal = true" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-xs font-bold hover:bg-gray-800 transition-all">ADD LINK</button>
                            <button @click="deleteMenu(selectedMenu.id)" class="px-4 py-2 bg-danger-50 text-danger-600 rounded-lg text-xs font-bold hover:bg-danger-100 transition-all">DELETE</button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div v-if="!selectedMenu.items?.length" class="text-center py-12">
                            <p class="text-sm text-gray-400">This menu is empty. Start adding some links!</p>
                        </div>
                        <div class="space-y-2">
                            <div v-for="item in selectedMenu.items" :key="item.id" class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100 group">
                                <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center border border-gray-100 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-bold text-gray-900">{{ item.label }}</div>
                                    <div class="text-[10px] text-gray-400 uppercase font-mono tracking-tighter">{{ item.type }}: {{ item.url || item.route_name || (item.page ? '/'+item.page.slug : '#') }}</div>
                                </div>
                                <button @click="deleteItem(item.id)" class="text-gray-300 hover:text-danger-600 transition-colors opacity-0 group-hover:opacity-100">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Menu Modal -->
        <div v-if="showAddMenuModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">New Menu</h3>
                    <button @click="showAddMenuModal = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
                <form @submit.prevent="submitMenu" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Menu Name</label>
                        <input v-model="menuForm.name" type="text" placeholder="e.g. Footer Links" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Slug</label>
                        <input v-model="menuForm.slug" type="text" placeholder="e.g. footer_links" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none font-mono" required />
                    </div>
                    <button type="submit" class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">CREATE MENU</button>
                </form>
            </div>
        </div>

        <!-- Add Item Modal -->
        <div v-if="showAddItemModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">Add Menu Item</h3>
                    <button @click="showAddItemModal = false" class="text-gray-400 hover:text-gray-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
                <form @submit.prevent="addItem" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Label</label>
                        <input v-model="itemForm.label" type="text" placeholder="e.g. About Us" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" required />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link Type</label>
                        <select v-model="itemForm.type" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            <option value="url">External URL</option>
                            <option value="page">CMS Page</option>
                            <option value="route">System Route</option>
                        </select>
                    </div>
                    <div v-if="itemForm.type === 'url'">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">URL</label>
                        <input v-model="itemForm.url" type="text" placeholder="https://..." class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                    </div>
                    <div v-if="itemForm.type === 'page'">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Page</label>
                        <select v-model="itemForm.page_id" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                            <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.title }}</option>
                        </select>
                    </div>
                    <div v-if="itemForm.type === 'route'">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Route Name</label>
                        <input v-model="itemForm.route_name" type="text" placeholder="pricing" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Sort Order</label>
                            <input v-model="itemForm.sort_order" type="number" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Target</label>
                            <select v-model="itemForm.target" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                <option value="_self">Same Tab</option>
                                <option value="_blank">New Tab</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-3 bg-primary-600 text-white rounded-xl font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20">ADD TO MENU</button>
                </form>
            </div>
        </div>
    </div>
</template>
