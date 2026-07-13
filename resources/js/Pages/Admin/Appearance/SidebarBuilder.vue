<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { VueDraggable } from 'vue-draggable-plus'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

type SidebarBlockType =
    | 'search_box'
    | 'categories_list'
    | 'recent_posts'
    | 'popular_tools'
    | 'recently_added'
    | 'tag_cloud'
    | 'newsletter'
    | 'ad_zone'
    | 'social_follow'
    | 'custom_html'

type SidebarBlockConfig = {
    title?: string
    placeholder?: string
    show_count?: boolean
    count?: number
    description?: string
    zone_id?: string
    content?: string
}

type SidebarBlock = {
    id: string
    type: SidebarBlockType
    config: SidebarBlockConfig
}

const props = defineProps<{
    config: {
        blocks?: SidebarBlock[];
        sticky?: boolean;
    } | null;
    embed?: boolean;
}>();

const { t } = useTranslate()
const toast = useToastr()
const form = useForm({
    blocks: props.config?.blocks || [],
    sticky: props.config?.sticky ?? true,
});

const submit = () => {
    form.post(route('admin.sidebar.update'), {
        preserveScroll: true,
    });
};

const blockTypes: { type: SidebarBlockType; label: string; desc: string }[] = [
    { type: 'search_box', label: 'Search Box', desc: 'A search input field' },
    { type: 'categories_list', label: 'Categories List', desc: 'List of AI tool categories' },
    { type: 'recent_posts', label: 'Recent Blog Posts', desc: 'List of recent articles' },
    { type: 'popular_tools', label: 'Popular Tools', desc: 'Most-used AI tools' },
    { type: 'recently_added', label: 'Recently Added', desc: 'Recently published tools' },
    { type: 'tag_cloud', label: 'Tag Cloud', desc: 'Cloud of popular tags' },
    { type: 'newsletter', label: 'Newsletter Subscribe', desc: 'Email subscription form' },
    { type: 'ad_zone', label: 'Ad Zone', desc: 'Select from defined ad zones' },
    { type: 'social_follow', label: 'Social Follow', desc: 'Social media links' },
    { type: 'custom_html', label: 'Custom HTML', desc: 'Embed custom code' },
];

const getBlockLabel = (type: string) => {
    return blockTypes.find((b) => b.type === type)?.label || type;
};

const addBlockModalOpen = ref(false);
const blockModalOpen = ref(false);
const editingBlock = ref<{ index: number; data: SidebarBlock } | null>(null);
const confirmRemoveOpen = ref(false);
const removeIndex = ref<number | null>(null);
const settingsModalOpen = ref(false);

const openAddBlockModal = () => {
    addBlockModalOpen.value = true;
};

const addBlock = (type: SidebarBlockType) => {
    const newBlock: SidebarBlock = {
        id: 'b_' + Math.random().toString(36).substring(2, 9),
        type,
        config: { title: getBlockLabel(type) }
    };

    if (type === 'search_box') newBlock.config['placeholder'] = t('Search...');
    if (type === 'categories_list') newBlock.config['show_count'] = true;
    if (type === 'recent_posts') newBlock.config['count'] = 3;
    if (type === 'popular_tools') newBlock.config['count'] = 5;
    if (type === 'recently_added') newBlock.config['count'] = 3;
    if (type === 'newsletter') newBlock.config['description'] = t('Subscribe to our newsletter.');
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
    removeIndex.value = index
    confirmRemoveOpen.value = true
};

const executeRemoveBlock = () => {
    if (removeIndex.value === null) return
    form.blocks.splice(removeIndex.value, 1)
    confirmRemoveOpen.value = false
    removeIndex.value = null
}

const buildDefaultBlocks = (): SidebarBlock[] => [
    { id: 'b1', type: 'search_box', config: { title: t('Search'), placeholder: t('Search articles...') } },
    { id: 'b2', type: 'categories_list', config: { title: t('Categories'), show_count: true } },
    { id: 'b3', type: 'recent_posts', config: { title: t('Recent Posts'), count: 3 } },
];

const applyDefaults = () => {
    form.blocks = JSON.parse(JSON.stringify(buildDefaultBlocks()));
    form.sticky = true;
};

// resetToDefaults removed to match Menus structure

const importJsonText = ref('');
const importModalOpen = ref(false);

const exportConfig = () => {
    const data = JSON.stringify({ blocks: form.blocks, sticky: form.sticky }, null, 2);
    const blob = new Blob([data], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sidebar-config.json';
    a.click();
    URL.revokeObjectURL(url);
};

const importConfig = () => {
    try {
        const data = JSON.parse(importJsonText.value);
        if (Array.isArray(data.blocks)) form.blocks = data.blocks;
        if (typeof data.sticky === 'boolean') form.sticky = data.sticky;
        importJsonText.value = '';
        importModalOpen.value = false;
    } catch (e) {
        toast.error(t('Invalid JSON format.'));
    }
};
</script>

<template>
        <Head v-if="!props.embed" :title="t('Sidebar Builder — Admin')" />

        <div :class="props.embed ? 'w-full' : 'w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10'">
            <section v-if="!props.embed" class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Sidebar Builder') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure widgets, visibility, and layout behavior for the application sidebar.') }}</p>
                </div>
                <div class="shrink-0 flex items-center gap-3">
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 font-semibold border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" :aria-label="t('Export JSON')" @click="exportConfig">
                        <i class="ti ti-file-export text-base"></i>
                        {{ t('Export') }}
                    </button>
                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 font-semibold border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" :aria-label="t('Import JSON')" @click="importModalOpen = true">
                        <i class="ti ti-file-import text-base"></i>
                        {{ t('Import') }}
                    </button>
                    <button type="button" @click="submit" :disabled="form.processing" class="inline-flex items-center gap-2 btn-primary-admin disabled:opacity-60">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <i v-else class="ti ti-device-floppy text-sm"></i>
                        <span>{{ form.processing ? t('Saving...') : t('Save Sidebar') }}</span>
                    </button>
                </div>
            </section>

            <div :class="props.embed ? 'grid grid-cols-1 gap-5' : 'mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[320px_minmax(0,1fr)]'">
                <aside v-if="!props.embed" class="space-y-6">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                        <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Layout') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control how the sidebar behaves while the page scrolls.') }}</p>

                        <div class="mt-5 space-y-5">
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Sticky Sidebar') }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the sidebar visible while the page scrolls.') }}</div>
                                </div>
                                <AppSwitch v-model="form.sticky" />
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-heading text-md font-bold text-gray-700 dark:text-white">{{ t('Active Widgets') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Drag to reorder widgets and open each item to configure its content.') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button v-if="props.embed" type="button" @click="settingsModalOpen = true" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                                <i class="ti ti-adjustments text-base"></i>
                                {{ t('Settings') }}
                            </button>
                            <button type="button" @click="openAddBlockModal" class="inline-flex items-center gap-2 rounded-xl bg-primary-100 px-3 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-200 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/40">
                                <i class="ti ti-plus text-base"></i>
                                {{ t('Add Widget') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div v-if="form.blocks.length === 0" class="rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/70 p-10 text-center dark:border-surface-700 dark:bg-surface-800/50">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white shadow-sm dark:bg-surface-900">
                                <i class="ti ti-layout-sidebar text-2xl text-gray-400"></i>
                            </div>
                            <h3 class="mt-4 text-sm font-bold text-gray-900 dark:text-white">{{ t('No widgets added') }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Start by adding a widget to build the sidebar experience.') }}</p>
                        </div>

                        <VueDraggable v-else v-model="form.blocks" handle=".drag-handle" ghostClass="opacity-50" :animation="150" class="space-y-3">
                            <article v-for="(block, index) in form.blocks" :key="block.id" class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50/70 px-4 py-2 transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-surface-700 dark:bg-surface-800/60 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10">
                                <button type="button" class="drag-handle inline-flex h-9 w-9 shrink-0 cursor-grab items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-400 transition hover:text-primary-600 active:cursor-grabbing dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300">
                                    <i class="ti ti-menu"></i>
                                </button>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ t(getBlockLabel(block.type)) }}</h3>
                                    </div>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ block.config.title || t('No title set') }}</p>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <Tooltip :content="t('Settings')" placement="top">
                                        <button type="button" @click="editBlock(Number(index))" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                                            <i class="ti ti-settings text-base"></i>
                                        </button>
                                    </Tooltip>
                                    <Tooltip :content="t('Remove')" placement="top">
                                        <button type="button" @click="removeBlock(Number(index))" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300">
                                            <i class="ti ti-trash text-base"></i>
                                        </button>
                                    </Tooltip>
                                </div>
                            </article>
                        </VueDraggable>
                    </div>
                </section>
            </div>
        </div>

        <AppModal
            :open="addBlockModalOpen"
            max-width="max-w-2xl"
            :title="t('Add Widget')"
            :subtitle="t('Choose a widget to add to the sidebar layout.')"
            @close="addBlockModalOpen = false"
        >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button v-for="bt in blockTypes" :key="bt.type" type="button" @click="addBlock(bt.type)" class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left transition hover:border-primary-300 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                        <i class="ti ti-plus text-base"></i>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ t(bt.label) }}</span>
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t(bt.desc) }}</span>
                    </span>
                </button>
            </div>
        </AppModal>

        <AppModal
            :open="settingsModalOpen"
            max-width="max-w-2xl"
            :title="t('Sidebar Settings')"
            :subtitle="t('Configure sticky behavior for the sidebar.')"
            cancel-text="Cancel"
            @close="settingsModalOpen = false"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-surface-700 dark:bg-surface-900">
                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Layout') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Control how the sidebar behaves while the page scrolls.') }}</p>

                <div class="mt-5 space-y-5">
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Sticky Sidebar') }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the sidebar visible while the page scrolls.') }}</div>
                        </div>
                        <AppSwitch v-model="form.sticky" />
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="settingsModalOpen = false" class="btn-primary-admin px-4 py-2 text-sm font-semibold rounded-xl">{{ t('Done') }}</button>
                </div>
            </template>
        </AppModal>

        <AppModal
            :open="blockModalOpen && !!editingBlock"
            max-width="max-w-lg"
            :title="editingBlock ? t(getBlockLabel(editingBlock.data.type)) : ''"
            :subtitle="t('Adjust the widget content and behavior for this sidebar block.')"
            :confirm-text="t('Done')"
            confirm-variant="admin"
            @close="blockModalOpen = false"
            @confirm="saveBlockSettings"
        >
            <div v-if="editingBlock" class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Widget Title') }}</label>
                    <input v-model="editingBlock.data.config.title" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                </div>

                <template v-if="editingBlock.data.type === 'search_box'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Placeholder Text') }}</label>
                        <input v-model="editingBlock.data.config.placeholder" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'categories_list'">
                    <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Show Tool Count') }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Display category totals beside each item.') }}</div>
                        </div>
                        <AppSwitch v-model="editingBlock.data.config.show_count" />
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'recent_posts'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Number of Posts to Show') }}</label>
                        <input v-model.number="editingBlock.data.config.count" type="number" min="1" max="10" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('3')">
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'popular_tools'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Number of Tools to Show') }}</label>
                        <input v-model.number="editingBlock.data.config.count" type="number" min="1" max="20" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('5')">
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'recently_added'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Number of Tools to Show') }}</label>
                        <input v-model.number="editingBlock.data.config.count" type="number" min="1" max="10" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('3')">
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'newsletter'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Description') }}</label>
                        <input v-model="editingBlock.data.config.description" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'ad_zone'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Ad Zone ID') }}</label>
                        <input v-model="editingBlock.data.config.zone_id" type="text" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        <p class="mt-1 text-[11px] text-gray-500">{{ t('Enter the ID of the ad zone configured in Ads System.') }}</p>
                    </div>
                </template>

                <template v-if="editingBlock.data.type === 'custom_html'">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">{{ t('Custom HTML Content') }}</label>
                        <textarea v-model="editingBlock.data.config.content" rows="5" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 font-mono text-sm transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-surface-700 dark:bg-surface-800 dark:text-white"></textarea>
                    </div>
                </template>
            </div>
        </AppModal>

        <ActionConfirmModal
            :open="confirmRemoveOpen"
            :title="t('Remove widget?')"
            :message="t('This widget will be removed from the sidebar configuration.')"
            :confirm-label="t('Remove')"
            :processing-label="t('Removing...')"
            variant="danger"
            @cancel="confirmRemoveOpen = false; removeIndex = null"
            @confirm="executeRemoveBlock"
        />

        <AppModal
            :open="importModalOpen"
            max-width="max-w-xl"
            :title="t('Import Sidebar Configuration')"
            :subtitle="t('Paste a previously exported JSON file to replace the current sidebar setup.')"
            :confirm-text="t('Import')"
            confirm-variant="admin"
            @close="importModalOpen = false"
            @confirm="importConfig"
        >
            <textarea v-model="importJsonText" rows="10" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Paste JSON here...')"></textarea>
        </AppModal>
</template>
