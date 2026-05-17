<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    config: any;
    menus: Array<{ id: number; name: string; slug: string }>;
}>();

// Initialize form with defaults provided by backend
const form = useForm({
    layout: props.config.layout || 'classic',
    sticky: props.config.sticky ?? true,
    transparent_homepage: props.config.transparent_homepage ?? false,
    height: props.config.height || 72,
    hide_on_scroll: props.config.hide_on_scroll ?? false,
    blocks: props.config.blocks || [],
    mobile: props.config.mobile || { menu_slug: 'mobile', show_logo: true, show_hamburger: true },
});

const submit = () => {
    form.post(route('admin.header.update'), {
        preserveScroll: true,
    });
};

// Layout presets
const layouts = [
    { id: 'classic', name: 'Classic', desc: 'Logo left, Nav center, Actions right' },
    { id: 'centered', name: 'Centered', desc: 'Logo center, Nav below' },
    { id: 'minimal', name: 'Minimal', desc: 'Logo + Hamburger only' },
];

// Block management
const moveBlockUp = (index: number) => {
    if (index > 0) {
        const temp = form.blocks[index - 1];
        form.blocks[index - 1] = form.blocks[index];
        form.blocks[index] = temp;
    }
};

const moveBlockDown = (index: number) => {
    if (index < form.blocks.length - 1) {
        const temp = form.blocks[index + 1];
        form.blocks[index + 1] = form.blocks[index];
        form.blocks[index] = temp;
    }
};

// Selected block for editing config
const selectedBlockIndex = ref<number | null>(null);

const getBlockLabel = (type: string) => {
    const labels: Record<string, string> = {
        logo: 'Logo',
        navigation: 'Navigation Menu',
        search: 'Search Bar',
        cta_button: 'CTA Button',
        language_switcher: 'Language Switcher',
        dark_mode: 'Dark Mode Toggle',
        user_menu: 'User Menu / Login',
        credit_balance: 'Credit Balance',
        notification_bell: 'Notifications',
        social_icons: 'Social Icons',
        custom_html: 'Custom HTML',
    };
    return labels[type] || type;
};
const draggedIndex = ref<number | null>(null);

const onDragStart = (e: DragEvent, index: number) => {
    draggedIndex.value = index;
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', index.toString());
    }
};

const onDrop = (e: DragEvent, targetIndex: number) => {
    if (draggedIndex.value !== null && draggedIndex.value !== targetIndex) {
        const item = form.blocks.splice(draggedIndex.value, 1)[0];
        form.blocks.splice(targetIndex, 0, item);
    }
    draggedIndex.value = null;
};
</script>

<template>
    <Head title="Header Builder — Admin" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Header Builder</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Visually design the platform header layout and elements.</p>
            </div>
            <button @click="submit" :disabled="form.processing" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50">
                {{ form.processing ? 'SAVING...' : 'SAVE CONFIGURATION' }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Layout & Settings -->
            <div class="space-y-6">
                <!-- Layout Preset -->
                <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-4">Layout Preset</h3>
                    <div class="space-y-3">
                        <button v-for="layout in layouts" :key="layout.id" @click="form.layout = layout.id" :class="form.layout === layout.id ? 'border-primary-500 ring-1 ring-primary-500 bg-primary-50 dark:bg-primary-900/10' : 'border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 hover:bg-gray-100 dark:hover:bg-surface-700'" class="w-full text-left p-4 rounded-xl border transition-all text-sm group">
                            <div class="font-bold text-gray-900 dark:text-white">{{ layout.name }}</div>
                            <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">{{ layout.desc }}</div>
                        </button>
                    </div>
                </div>

                <!-- Header Behaviors -->
                <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-4">Header Behaviors</h3>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-bold text-gray-900 dark:text-white">Sticky Header</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Keep header at top when scrolling</div>
                            </div>
                            <button @click="form.sticky = !form.sticky" type="button" :class="form.sticky ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                <span :class="form.sticky ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-bold text-gray-900 dark:text-white">Hide on Scroll Down</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Reveals again when scrolling up</div>
                            </div>
                            <button @click="form.hide_on_scroll = !form.hide_on_scroll" type="button" :class="form.hide_on_scroll ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                <span :class="form.hide_on_scroll ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                            </button>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-bold text-gray-900 dark:text-white">Transparent Homepage</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Header overlaps hero section</div>
                            </div>
                            <button @click="form.transparent_homepage = !form.transparent_homepage" type="button" :class="form.transparent_homepage ? 'bg-success-600' : 'bg-gray-200 dark:bg-surface-700'" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out">
                                <span :class="form.transparent_homepage ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition duration-200 mt-0.5 ml-0.5"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Dimensions -->
                <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-4">Dimensions</h3>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Header Height (px)</label>
                        <input v-model="form.height" type="number" min="48" max="120" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:outline-none" />
                        <div class="mt-2 flex gap-2">
                            <button @click="form.height = 64" type="button" class="px-2 py-1 bg-gray-100 dark:bg-surface-800 text-[10px] font-bold rounded hover:bg-gray-200 dark:hover:bg-surface-700">Small (64px)</button>
                            <button @click="form.height = 72" type="button" class="px-2 py-1 bg-gray-100 dark:bg-surface-800 text-[10px] font-bold rounded hover:bg-gray-200 dark:hover:bg-surface-700">Standard (72px)</button>
                            <button @click="form.height = 96" type="button" class="px-2 py-1 bg-gray-100 dark:bg-surface-800 text-[10px] font-bold rounded hover:bg-gray-200 dark:hover:bg-surface-700">Large (96px)</button>
                        </div>
                    </div>
                </div>

                 <!-- Mobile Header -->
                 <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs border-b border-gray-50 dark:border-surface-800 pb-4 mb-4">Mobile Header</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Mobile Menu Source</label>
                            <select v-model="form.mobile.menu_slug" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:outline-none">
                                <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                            </select>
                            <p class="text-[10px] text-gray-400 mt-1">This menu will open when the hamburger icon is tapped.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Block Builder -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Active Blocks List -->
                <div class="bg-white dark:bg-surface-900 p-6 rounded-2xl border border-gray-100 dark:border-surface-700 shadow-sm">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-50 dark:border-surface-800">
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs">Header Elements</h3>
                        <span class="text-[10px] bg-gray-100 dark:bg-surface-800 text-gray-500 px-2 py-1 rounded-md font-mono tracking-widest">DRAG TO REORDER (LEFT TO RIGHT)</span>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(block, index) in form.blocks" :key="block.id"
                            draggable="true"
                            @dragstart="onDragStart($event, Number(index))"
                            @dragover.prevent
                            @drop.prevent="onDrop($event, Number(index))"
                            :class="[
                                block.enabled ? 'border-primary-200 bg-primary-50/50 dark:border-primary-900/50 dark:bg-primary-900/10' : 'border-gray-100 bg-gray-50/50 dark:border-surface-700 dark:bg-surface-800/50',
                                draggedIndex === index ? 'opacity-50 ring-2 ring-primary-500 ring-dashed' : ''
                            ]"
                            class="flex items-center justify-between p-4 rounded-xl border transition-all cursor-move hover:shadow-md">

                            <div class="flex items-center gap-4">
                                <!-- Drag Handle Icon -->
                                <div class="text-gray-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                                </div>

                                <div>
                                    <div class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                                        {{ getBlockLabel(block.type) }}
                                        <span v-if="block.enabled" class="w-2 h-2 rounded-full bg-success-500"></span>
                                        <span v-else class="w-2 h-2 rounded-full bg-gray-300 dark:bg-surface-600"></span>
                                    </div>
                                    <div class="text-[10px] text-gray-500 uppercase font-mono mt-1">{{ block.id }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <!-- Settings Button (if block has config) -->
                                <button v-if="Object.keys(block.config || {}).length > 0" @click="selectedBlockIndex = Number(index)" type="button" class="bg-white dark:bg-surface-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:hover:bg-surface-700 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm">
                                    SETTINGS
                                </button>

                                <!-- Toggle Enable/Disable -->
                                <button @click="block.enabled = !block.enabled" type="button" :class="block.enabled ? 'bg-success-600 text-white shadow-success-600/20 shadow-md' : 'bg-gray-200 dark:bg-surface-700 text-gray-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all w-20 text-center">
                                    {{ block.enabled ? 'ON' : 'OFF' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block Configuration Panel (Modal) -->
                <div v-if="selectedBlockIndex !== null" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                    <div class="bg-white dark:bg-surface-900 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200 border border-gray-100 dark:border-surface-700">
                        <div class="flex justify-between items-center p-6 border-b border-gray-100 dark:border-surface-800">
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-widest text-xs">Configure: {{ getBlockLabel(form.blocks[selectedBlockIndex].type) }}</h3>
                            <button @click="selectedBlockIndex = null" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                            <!-- Navigation Config -->
                            <div v-if="form.blocks[selectedBlockIndex].type === 'navigation'">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Select Menu Source</label>
                                <select v-model="form.blocks[selectedBlockIndex].config.menu_slug" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:outline-none">
                                    <option v-for="menu in menus" :key="menu.id" :value="menu.slug">{{ menu.name }}</option>
                                </select>
                            </div>

                            <!-- CTA Config -->
                            <div v-if="form.blocks[selectedBlockIndex].type === 'cta_button'" class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Button Text</label>
                                    <input v-model="form.blocks[selectedBlockIndex].config.text" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">URL / Link</label>
                                    <input v-model="form.blocks[selectedBlockIndex].config.link" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none font-mono" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Button Style</label>
                                    <select v-model="form.blocks[selectedBlockIndex].config.style" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                        <option value="filled">Filled (Solid)</option>
                                        <option value="outline">Outline</option>
                                        <option value="ghost">Ghost (Text only)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Color Theme</label>
                                    <select v-model="form.blocks[selectedBlockIndex].config.color" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                                        <option value="primary">Primary</option>
                                        <option value="accent">Accent</option>
                                        <option value="success">Success</option>
                                        <option value="dark">Dark</option>
                                        <option value="light">Light</option>
                                    </select>
                                </div>
                            </div>

                            <!-- User Menu Config -->
                            <div v-if="form.blocks[selectedBlockIndex].type === 'user_menu'" class="space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" v-model="form.blocks[selectedBlockIndex].config.show_avatar" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Show User Avatar</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" v-model="form.blocks[selectedBlockIndex].config.show_credits" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Show Credit Balance in Dropdown</span>
                                </label>
                            </div>

                            <!-- Logo Config -->
                            <div v-if="form.blocks[selectedBlockIndex].type === 'logo'" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Logo Text (Fallback)</label>
                                    <input v-model="form.blocks[selectedBlockIndex].config.text" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-lg px-3 py-2 text-sm focus:border-primary-500 focus:outline-none" />
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" v-model="form.blocks[selectedBlockIndex].config.show_text" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Show Text next to Logo</span>
                                </label>
                            </div>

                        </div>

                        <!-- Modal Footer -->
                        <div class="p-6 bg-gray-50 dark:bg-surface-800 border-t border-gray-100 dark:border-surface-700 flex justify-end">
                            <button @click="selectedBlockIndex = null" type="button" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-sm">
                                DONE
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
