<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
    config: any;
    menus: Array<{ id: number, name: string, slug: string }>;
}>();

const form = useForm({
    layout: props.config.layout || 4,
    columns: props.config.columns || [[], [], [], []],
    bottom_bar: props.config.bottom_bar || {
        copyright_text: '',
        menu_slug: null,
        show_payment_icons: true,
        payment_icons: [],
        show_back_to_top: true,
    }
});

// Watch layout changes to ensure we have enough columns in the array
watch(() => form.layout, (newVal) => {
    while (form.columns.length < newVal) {
        form.columns.push([]);
    }
});

// Available block types
const blockTypes = [
    { type: 'about_text', label: 'About Text', desc: 'Logo and description paragraph' },
    { type: 'menu_list', label: 'Menu List', desc: 'Vertical list of links from a saved menu' },
    { type: 'contact_info', label: 'Contact Info', desc: 'Address, phone, email with icons' },
    { type: 'social_icons', label: 'Social Icons', desc: 'Links to social media profiles' },
    { type: 'newsletter', label: 'Newsletter Form', desc: 'Email subscription input' },
    { type: 'custom_html', label: 'Custom HTML', desc: 'Raw HTML content' },
];

const availablePaymentIcons = ['visa', 'mastercard', 'paypal', 'stripe', 'amex', 'discover', 'apple_pay', 'google_pay'];

// Modal states
const blockModalOpen = ref(false);
const editingBlock = ref<any>(null);
const targetColIndex = ref<number>(0);

const addBlockModalOpen = ref(false);

const openAddBlock = (colIndex: number) => {
    targetColIndex.value = colIndex;
    addBlockModalOpen.value = true;
};

const addBlock = (type: string) => {
    const newBlock = {
        id: 'block_' + Math.random().toString(36).substr(2, 9),
        type: type,
        config: getDefaultConfigForType(type)
    };
    form.columns[targetColIndex.value].push(newBlock);
    addBlockModalOpen.value = false;
    openSettings(targetColIndex.value, form.columns[targetColIndex.value].length - 1);
};

const getDefaultConfigForType = (type: string) => {
    switch(type) {
        case 'about_text': return { logo: null, description: 'Enter your site description here.' };
        case 'menu_list': return { title: 'Quick Links', menu_slug: '' };
        case 'contact_info': return { title: 'Contact Us', address: '', phone: '', email: '' };
        case 'social_icons': return { title: 'Follow Us', icons: [] };
        case 'newsletter': return { title: 'Subscribe', description: 'Get the latest updates.' };
        case 'custom_html': return { title: '', content: '' };
        default: return {};
    }
};

const removeBlock = (colIndex: number, blockIndex: number) => {
    form.columns[colIndex].splice(blockIndex, 1);
};

const moveUp = (colIndex: number, blockIndex: number) => {
    if (blockIndex > 0) {
        const temp = form.columns[colIndex][blockIndex - 1];
        form.columns[colIndex][blockIndex - 1] = form.columns[colIndex][blockIndex];
        form.columns[colIndex][blockIndex] = temp;
    }
};

const moveDown = (colIndex: number, blockIndex: number) => {
    if (blockIndex < form.columns[colIndex].length - 1) {
        const temp = form.columns[colIndex][blockIndex + 1];
        form.columns[colIndex][blockIndex + 1] = form.columns[colIndex][blockIndex];
        form.columns[colIndex][blockIndex] = temp;
    }
};

const openSettings = (colIndex: number, blockIndex: number) => {
    editingBlock.value = {
        colIndex,
        blockIndex,
        data: JSON.parse(JSON.stringify(form.columns[colIndex][blockIndex])) // deep clone
    };
    blockModalOpen.value = true;
};

const saveBlockSettings = () => {
    if (editingBlock.value) {
        form.columns[editingBlock.value.colIndex][editingBlock.value.blockIndex] = editingBlock.value.data;
        blockModalOpen.value = false;
        editingBlock.value = null;
    }
};

const getBlockLabel = (type: string) => {
    const found = blockTypes.find(b => b.type === type);
    return found ? found.label : type;
};

const togglePaymentIcon = (icon: string) => {
    const index = form.bottom_bar.payment_icons.indexOf(icon);
    if (index === -1) {
        form.bottom_bar.payment_icons.push(icon);
    } else {
        form.bottom_bar.payment_icons.splice(index, 1);
    }
};

const save = () => {
    form.post(route('admin.footer.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Footer Builder" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between w-full">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Footer Builder</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Design your platform's footer layout and content</p>
                </div>
                <button @click="save" :disabled="form.processing" class="px-5 py-2.5 bg-primary-600 text-white font-bold rounded-xl hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50 flex items-center gap-2">
                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                    Save Changes
                </button>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Global Settings -->
            <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-6">Column Layout</h3>
                <div class="grid grid-cols-4 gap-4">
                    <button v-for="n in 4" :key="n" @click="form.layout = n" :class="form.layout === n ? 'border-primary-500 ring-1 ring-primary-500 bg-primary-50 dark:bg-primary-900/10 text-primary-600 dark:text-primary-400' : 'border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-700 dark:text-gray-300'" class="w-full text-center p-4 rounded-xl border transition-all text-sm font-bold flex flex-col items-center gap-2">
                        <div class="flex items-center gap-1">
                            <div v-for="i in n" :key="i" class="w-4 h-6 rounded-sm" :class="form.layout === n ? 'bg-primary-400 dark:bg-primary-600' : 'bg-gray-300 dark:bg-surface-600'"></div>
                        </div>
                        {{ n }} Column{{ n > 1 ? 's' : '' }}
                    </button>
                </div>
            </div>

            <!-- Builder Area -->
            <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm overflow-x-auto">
                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-6">Footer Blocks Grid</h3>
                
                <div class="flex gap-4 min-w-[800px]">
                    <!-- Columns -->
                    <div v-for="colIndex in form.layout" :key="colIndex" class="flex-1 bg-gray-50 dark:bg-surface-950 border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-xl p-4 flex flex-col gap-3 min-h-[300px]">
                        <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest text-center mb-2">Column {{ colIndex }}</div>
                        
                        <div v-for="(block, blockIndex) in form.columns[Number(colIndex) - 1]" :key="block.id" class="bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 rounded-lg p-3 shadow-sm flex items-center justify-between group relative overflow-hidden transition-all hover:shadow-md hover:border-primary-300 dark:hover:border-primary-700">
                            <div class="flex items-center gap-3 w-full">
                                <div class="flex flex-col gap-0.5">
                                    <button @click="moveUp(Number(colIndex) - 1, Number(blockIndex))" :disabled="blockIndex === 0" class="p-0.5 text-gray-400 hover:text-primary-600 disabled:opacity-20 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                                    </button>
                                    <button @click="moveDown(Number(colIndex) - 1, Number(blockIndex))" :disabled="blockIndex === form.columns[Number(colIndex) - 1].length - 1" class="p-0.5 text-gray-400 hover:text-primary-600 disabled:opacity-20 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </div>
                                <div class="flex-1 min-w-0" @click="openSettings(Number(colIndex) - 1, Number(blockIndex))">
                                    <div class="font-bold text-sm text-gray-900 dark:text-white truncate cursor-pointer">{{ getBlockLabel(block.type) }}</div>
                                    <div class="text-[10px] text-gray-500 truncate mt-0.5 cursor-pointer">{{ block.config.title || 'No Title' }}</div>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click="openSettings(Number(colIndex) - 1, Number(blockIndex))" class="p-1.5 text-gray-400 hover:text-primary-600 bg-gray-50 hover:bg-primary-50 dark:bg-surface-800 dark:hover:bg-primary-900/20 rounded-md transition-colors" title="Settings">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    </button>
                                    <button @click="removeBlock(Number(colIndex) - 1, Number(blockIndex))" class="p-1.5 text-gray-400 hover:text-danger-500 bg-gray-50 hover:bg-danger-50 dark:bg-surface-800 dark:hover:bg-danger-500/20 rounded-md transition-colors" title="Remove">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button @click="openAddBlock(Number(colIndex) - 1)" class="w-full py-2 mt-auto border-2 border-dashed border-gray-300 dark:border-surface-600 rounded-lg text-xs font-bold text-gray-500 hover:text-primary-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-all flex items-center justify-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            Add Block
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sub-Footer (Bottom Bar) Settings -->
            <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-6">Sub-Footer (Bottom Bar)</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Copyright Text</label>
                        <input v-model="form.bottom_bar.copyright_text" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all" placeholder="© {year} Your Company. All rights reserved.">
                        <p class="text-[10px] text-gray-500 mt-1">Use <code>{year}</code> to automatically output current year.</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Bottom Menu</label>
                        <select v-model="form.bottom_bar.menu_slug" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            <option :value="null">None</option>
                            <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                        </select>
                    </div>

                    <div class="col-span-1 md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100 dark:border-surface-800">
                        <!-- Toggles -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">Back to Top Button</div>
                                    <div class="text-[10px] text-gray-500">Show floating scroll-to-top arrow</div>
                                </div>
                                <button @click="form.bottom_bar.show_back_to_top = !form.bottom_bar.show_back_to_top" type="button" :class="form.bottom_bar.show_back_to_top ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                    <span :class="form.bottom_bar.show_back_to_top ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                </button>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">Payment Icons</div>
                                    <div class="text-[10px] text-gray-500">Show accepted payment methods</div>
                                </div>
                                <button @click="form.bottom_bar.show_payment_icons = !form.bottom_bar.show_payment_icons" type="button" :class="form.bottom_bar.show_payment_icons ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                    <span :class="form.bottom_bar.show_payment_icons ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Payment Icons Select -->
                        <div v-if="form.bottom_bar.show_payment_icons" class="bg-gray-50 dark:bg-surface-950 p-4 rounded-xl border border-gray-100 dark:border-surface-800">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wider">Select Icons</label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="icon in availablePaymentIcons" :key="icon" @click="togglePaymentIcon(icon)" type="button" :class="form.bottom_bar.payment_icons.includes(icon) ? 'bg-primary-100 text-primary-700 border-primary-300 dark:bg-primary-900/30 dark:text-primary-400 dark:border-primary-700' : 'bg-white text-gray-500 border-gray-200 dark:bg-surface-800 dark:text-gray-400 dark:border-surface-700'" class="px-3 py-1.5 text-[10px] font-bold border rounded-lg hover:shadow-sm transition-all capitalize">
                                    {{ icon.replace('_', ' ') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Block Modal -->
        <div v-if="addBlockModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Add Block to Column {{ targetColIndex + 1 }}</h3>
                    <div class="space-y-2">
                        <button v-for="bt in blockTypes" :key="bt.type" @click="addBlock(bt.type)" class="w-full flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-surface-700 hover:border-primary-500 dark:hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-all text-left group">
                            <div class="w-10 h-10 rounded-lg bg-gray-50 dark:bg-surface-800 group-hover:bg-white dark:group-hover:bg-surface-900 flex items-center justify-center shrink-0 border border-gray-100 dark:border-surface-700 shadow-sm">
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-primary-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                            </div>
                            <div>
                                <div class="font-bold text-sm text-gray-900 dark:text-white">{{ bt.label }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ bt.desc }}</div>
                            </div>
                        </button>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button @click="addBlockModalOpen = false" class="px-4 py-2 text-sm font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Block Settings Modal -->
        <div v-if="blockModalOpen && editingBlock" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {{ getBlockLabel(editingBlock.data.type) }} Settings
                    </h3>

                    <div class="space-y-4">
                        <!-- Standard Block Title (Shared by most blocks) -->
                        <div v-if="editingBlock.data.config.title !== undefined">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Block Title</label>
                            <input v-model="editingBlock.data.config.title" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                        </div>

                        <!-- About Text Settings -->
                        <template v-if="editingBlock.data.type === 'about_text'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Description</label>
                                <textarea v-model="editingBlock.data.config.description" rows="3" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all"></textarea>
                            </div>
                        </template>

                        <!-- Menu List Settings -->
                        <template v-if="editingBlock.data.type === 'menu_list'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Select Menu</label>
                                <select v-model="editingBlock.data.config.menu_slug" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                                    <option value="">Select a menu</option>
                                    <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                                </select>
                            </div>
                        </template>

                        <!-- Contact Info Settings -->
                        <template v-if="editingBlock.data.type === 'contact_info'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Address</label>
                                <input v-model="editingBlock.data.config.address" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Phone</label>
                                <input v-model="editingBlock.data.config.phone" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Email</label>
                                <input v-model="editingBlock.data.config.email" type="email" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                        </template>

                        <!-- Newsletter Settings -->
                        <template v-if="editingBlock.data.type === 'newsletter'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Description</label>
                                <input v-model="editingBlock.data.config.description" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                        </template>

                        <!-- Custom HTML Settings -->
                        <template v-if="editingBlock.data.type === 'custom_html'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">HTML Content</label>
                                <textarea v-model="editingBlock.data.config.content" rows="4" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white font-mono transition-all"></textarea>
                            </div>
                        </template>
                    </div>

                    <div class="mt-8 flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-surface-800">
                        <button @click="blockModalOpen = false" class="px-5 py-2.5 text-sm font-bold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-800 rounded-xl transition-colors">Cancel</button>
                        <button @click="saveBlockSettings" class="px-5 py-2.5 bg-primary-600 text-white text-sm font-bold rounded-xl hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20">Apply Configuration</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
