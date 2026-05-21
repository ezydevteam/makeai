<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
    config: any;
}>();

const form = useForm({
    blocks: props.config?.blocks || [],
    position: props.config?.position || 'right',
    sticky: props.config?.sticky ?? true,
});

const submit = () => {
    form.post(route('admin.sidebar.update'), {
        preserveScroll: true,
    });
};

const blockTypes = [
    { type: 'search_box', label: 'Search Box', desc: 'A search input field' },
    { type: 'categories_list', label: 'Categories List', desc: 'List of categories' },
    { type: 'recent_posts', label: 'Recent Posts', desc: 'List of recent articles' },
    { type: 'tag_cloud', label: 'Tag Cloud', desc: 'Cloud of popular tags' },
    { type: 'newsletter', label: 'Newsletter Subscribe', desc: 'Email subscription form' },
    { type: 'ad_zone', label: 'Ad Zone', desc: 'Select from defined ad zones' },
    { type: 'social_follow', label: 'Social Follow', desc: 'Social media links' },
    { type: 'custom_html', label: 'Custom HTML', desc: 'Embed custom code' },
];

const getBlockLabel = (type: string) => {
    return blockTypes.find(b => b.type === type)?.label || type;
};

const addBlockModalOpen = ref(false);
const blockModalOpen = ref(false);
const editingBlock = ref<any>(null);

const openAddBlockModal = () => {
    addBlockModalOpen.value = true;
};

const addBlock = (type: string) => {
    const newBlock = {
        id: 'b_' + Math.random().toString(36).substr(2, 9),
        type,
        config: { title: getBlockLabel(type) } as Record<string, any>
    };
    
    // Set default config based on type
    if (type === 'search_box') newBlock.config['placeholder'] = 'Search...';
    if (type === 'categories_list') newBlock.config['show_count'] = true;
    if (type === 'recent_posts') newBlock.config['count'] = 3;
    if (type === 'newsletter') newBlock.config['description'] = 'Subscribe to our newsletter.';
    if (type === 'ad_zone') newBlock.config['zone_id'] = '';
    if (type === 'social_follow') newBlock.config['title'] = 'Follow Us';
    if (type === 'custom_html') newBlock.config['content'] = '';

    form.blocks.push(newBlock);
    addBlockModalOpen.value = false;
};

const editBlock = (index: number) => {
    editingBlock.value = {
        index,
        data: JSON.parse(JSON.stringify(form.blocks[index]))
    };
    blockModalOpen.value = true;
};

const saveBlockSettings = () => {
    if (editingBlock.value) {
        form.blocks[editingBlock.value.index] = editingBlock.value.data;
    }
    blockModalOpen.value = false;
};

const removeBlock = (index: number) => {
    if (confirm('Remove this widget?')) {
        form.blocks.splice(index, 1);
    }
};

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
</script>

<template>
    <AdminLayout>
        <Head :title="$t('Sidebar Builder — Admin')" />
        
        <div class="max-w-6xl mx-auto px-6 py-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sidebar Builder</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure widgets and layout for the application sidebar.</p>
                </div>
                <button @click="submit" :disabled="form.processing" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-600/20 disabled:opacity-50 flex items-center gap-2">
                    <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Save Sidebar</span>
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Sidebar Layout Settings -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                            Layout Configuration
                        </h2>
                        
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-3">Position</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <button type="button" @click="form.position = 'left'" :class="form.position === 'left' ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/20 ring-1 ring-primary-500' : 'border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 hover:border-gray-300'" class="p-3 rounded-xl border text-center transition-all">
                                        <div class="w-full h-12 bg-white dark:bg-surface-900 rounded border border-gray-200 dark:border-surface-700 flex mb-2">
                                            <div class="w-1/3 h-full bg-primary-100 dark:bg-primary-900/40 rounded-l border-r border-gray-200 dark:border-surface-700"></div>
                                        </div>
                                        <span class="text-sm font-bold text-gray-900 dark:text-white">Left</span>
                                    </button>
                                    <button type="button" @click="form.position = 'right'" :class="form.position === 'right' ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/20 ring-1 ring-primary-500' : 'border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 hover:border-gray-300'" class="p-3 rounded-xl border text-center transition-all">
                                        <div class="w-full h-12 bg-white dark:bg-surface-900 rounded border border-gray-200 dark:border-surface-700 flex mb-2">
                                            <div class="w-2/3 h-full"></div>
                                            <div class="w-1/3 h-full bg-primary-100 dark:bg-primary-900/40 rounded-r border-l border-gray-200 dark:border-surface-700"></div>
                                        </div>
                                        <span class="text-sm font-bold text-gray-900 dark:text-white">Right</span>
                                    </button>
                                </div>
                            </div>
                            
                            <label class="flex items-center justify-between cursor-pointer group">
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Sticky Sidebar</span>
                                <div class="relative">
                                    <input type="checkbox" v-model="form.sticky" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-surface-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Widgets Builder Area -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-sm border border-gray-100 dark:border-surface-800 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25z" /></svg>
                                Active Widgets
                            </h2>
                            <button @click="openAddBlockModal" class="px-4 py-2 bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 rounded-lg text-sm font-bold hover:bg-primary-100 dark:hover:bg-primary-900/40 transition-colors">
                                + Add Widget
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-if="form.blocks.length === 0" class="border-2 border-dashed border-gray-200 dark:border-surface-700 rounded-2xl p-8 text-center bg-gray-50/50 dark:bg-surface-800/50">
                                <div class="w-12 h-12 bg-white dark:bg-surface-900 rounded-xl shadow-sm flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" /></svg>
                                </div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-1">No widgets added</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Add widgets to build your sidebar.</p>
                            </div>

                            <div v-for="(block, index) in form.blocks" :key="block.id" class="group flex items-center gap-3 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-700 p-3 rounded-xl hover:border-primary-500 dark:hover:border-primary-500 transition-all shadow-sm">
                                <div class="flex flex-col gap-1 text-gray-400 cursor-move pl-1">
                                    <button @click.prevent="moveBlockUp(Number(index))" :disabled="index === 0" class="hover:text-primary-500 disabled:opacity-30 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg></button>
                                    <button @click.prevent="moveBlockDown(Number(index))" :disabled="index === form.blocks.length - 1" class="hover:text-primary-500 disabled:opacity-30 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg></button>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-bold text-sm text-gray-900 dark:text-white truncate flex items-center gap-2">
                                        {{ getBlockLabel(block.type) }}
                                        <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-surface-800 text-gray-500">{{ block.type }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ block.config.title || 'No Title' }}</div>
                                </div>
                                <div class="flex items-center gap-2 pr-2">
                                    <button @click="editBlock(Number(index))" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 dark:bg-surface-800 text-gray-600 dark:text-gray-400 hover:text-primary-600 hover:bg-primary-50 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg></button>
                                    <button @click="removeBlock(Number(index))" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 dark:bg-surface-800 text-danger-500 hover:bg-danger-50 transition-colors"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Widget Modal -->
        <div v-if="addBlockModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Add Widget to Sidebar</h3>
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

        <!-- Edit Widget Modal -->
        <div v-if="blockModalOpen && editingBlock" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-6 overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {{ getBlockLabel(editingBlock.data.type) }} Settings
                    </h3>

                    <div class="space-y-4">
                        <!-- Widget Title (Shared) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Widget Title</label>
                            <input v-model="editingBlock.data.config.title" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                        </div>

                        <!-- Search Box Settings -->
                        <template v-if="editingBlock.data.type === 'search_box'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Placeholder Text</label>
                                <input v-model="editingBlock.data.config.placeholder" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                        </template>

                        <!-- Categories List Settings -->
                        <template v-if="editingBlock.data.type === 'categories_list'">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" v-model="editingBlock.data.config.show_count" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500" />
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">Show Post Count</span>
                            </label>
                        </template>

                        <!-- Recent Posts Settings -->
                        <template v-if="editingBlock.data.type === 'recent_posts'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Number of Posts to Show</label>
                                <input v-model.number="editingBlock.data.config.count" type="number" min="1" max="10" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                        </template>
                        
                        <!-- Newsletter Settings -->
                        <template v-if="editingBlock.data.type === 'newsletter'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Description</label>
                                <input v-model="editingBlock.data.config.description" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                            </div>
                        </template>

                        <!-- Ad Zone Settings -->
                        <template v-if="editingBlock.data.type === 'ad_zone'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Ad Zone ID</label>
                                <input v-model="editingBlock.data.config.zone_id" type="text" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all">
                                <p class="text-[10px] text-gray-500 mt-1">Enter the ID of the ad zone configured in Ads System.</p>
                            </div>
                        </template>

                        <!-- Custom HTML Settings -->
                        <template v-if="editingBlock.data.type === 'custom_html'">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Custom HTML Content</label>
                                <textarea v-model="editingBlock.data.config.content" rows="4" class="w-full bg-gray-50 dark:bg-surface-800 border border-gray-200 dark:border-surface-700 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-all font-mono"></textarea>
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
