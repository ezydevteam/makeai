<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { VueDraggable } from 'vue-draggable-plus'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AppSelect from '@/Components/AppSelect.vue'
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
        position?: 'left' | 'right';
        sticky?: boolean;
        show_on_pages?: string[];
    } | null;
    availablePages: { key: string; label: string }[];
    embed?: boolean;
}>();

const { t } = useTranslate()
const toast = useToastr()
const form = useForm({
    blocks: props.config?.blocks || [],
    position: props.config?.position || 'right',
    sticky: props.config?.sticky ?? true,
    show_on_pages: props.config?.show_on_pages || [],
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

const positionOptions = [
    { value: 'left', label: 'Left' },
    { value: 'right', label: 'Right' },
];

const getBlockLabel = (type: string) => {
    return blockTypes.find((b) => b.type === type)?.label || type;
};

const addBlockModalOpen = ref(false);
const blockModalOpen = ref(false);
const editingBlock = ref<{ index: number; data: SidebarBlock } | null>(null);
const confirmRemoveOpen = ref(false);
const removeIndex = ref<number | null>(null);
const resetProcessing = ref(false);
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
    form.position = 'right';
    form.sticky = true;
    form.show_on_pages = [];
};

const resetToDefaults = () => {
    resetProcessing.value = true;

    applyDefaults();

    form.post(route('admin.sidebar.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('Sidebar settings reset to defaults.'));
        },
        onFinish: () => {
            resetProcessing.value = false;
        },
    });
};

const togglePage = (page: string) => {
    const idx = form.show_on_pages.indexOf(page);
    if (idx >= 0) {
        form.show_on_pages.splice(idx, 1);
    } else {
        form.show_on_pages.push(page);
    }
};

const importJsonText = ref('');
const importModalOpen = ref(false);

const exportConfig = () => {
    const data = JSON.stringify({ blocks: form.blocks, position: form.position, sticky: form.sticky, show_on_pages: form.show_on_pages }, null, 2);
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
        if (data.position) form.position = data.position;
        if (typeof data.sticky === 'boolean') form.sticky = data.sticky;
        if (Array.isArray(data.show_on_pages)) form.show_on_pages = data.show_on_pages;
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
            <section v-if="!props.embed" class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Sidebar Builder') }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Configure widgets, visibility, and layout behavior for the application sidebar.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Tooltip :content="t('Export JSON')" placement="top">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Export JSON')" @click="exportConfig">
                            <i class="ti ti-file-export text-base"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Import JSON')" placement="top">
                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Import JSON')" @click="importModalOpen = true">
                            <i class="ti ti-file-import text-base"></i>
                        </button>
                    </Tooltip>
                    <button type="button" @click="resetToDefaults" :disabled="form.processing || resetProcessing" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700">
                        <svg v-if="resetProcessing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <i v-else class="ti ti-restore text-sm"></i>
                        <span>{{ resetProcessing ? t('Resetting...') : t('Reset') }}</span>
                    </button>
                    <button type="button" @click="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg btn-primary px-5 py-2.5 text-sm font-semibold disabled:opacity-50">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>{{ form.processing ? t('Saving...') : t('Save Sidebar') }}</span>
                    </button>
                </div>
            </section>

            <div :class="props.embed ? 'grid grid-cols-1 gap-5' : 'mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[320px_minmax(0,1fr)]'">
                <aside v-if="!props.embed" class="space-y-6">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                        <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Layout') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose where the sidebar appears and when it stays visible.') }}</p>

                        <div class="mt-5 space-y-5">
                            <AppSelect
                                v-model="form.position"
                                :label="t('Position')"
                                :options="positionOptions.map((option) => ({ value: option.value, label: t(option.label) }))"
                            />

                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Sticky Sidebar') }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the sidebar visible while the page scrolls.') }}</div>
                                </div>
                                <button type="button" role="switch" :aria-checked="form.sticky" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition" :class="form.sticky ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="form.sticky = !form.sticky">
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.sticky ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Show On Pages') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Leave all pages unselected to show the sidebar everywhere.') }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ form.show_on_pages.length }}</span>
                        </div>

                        <div class="mt-4 flex max-h-72 flex-wrap gap-2 overflow-y-auto">
                            <button
                                v-for="page in availablePages"
                                :key="page.key"
                                type="button"
                                class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition"
                                :class="form.show_on_pages.includes(page.key)
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300'
                                    : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:border-primary-900/40'"
                                @click="togglePage(page.key)"
                            >
                                {{ page.label }}
                            </button>
                        </div>
                    </div>
                </aside>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-heading text-md font-bold text-gray-700 dark:text-white">{{ t('Active Widgets') }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Drag to reorder widgets and open each item to configure its content.') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button v-if="props.embed" type="button" @click="settingsModalOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                                <i class="ti ti-adjustments text-base"></i>
                                {{ t('Settings') }}
                            </button>
                            <button type="button" @click="openAddBlockModal" class="inline-flex items-center gap-2 rounded-lg bg-gray-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-gray-800">
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
                            <article v-for="(block, index) in form.blocks" :key="block.id" class="group flex items-center gap-4 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-surface-700 dark:bg-surface-800/60 dark:hover:border-primary-900/40 dark:hover:bg-primary-900/10">
                                <button type="button" class="drag-handle inline-flex h-10 w-10 shrink-0 cursor-grab items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-400 transition hover:text-primary-600 active:cursor-grabbing dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300">
                                    <i class="ti ti-grip-vertical text-lg"></i>
                                </button>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ t(getBlockLabel(block.type)) }}</h3>
                                    </div>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ block.config.title || t('No title set') }}</p>
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    <Tooltip :content="t('Settings')" placement="top">
                                        <button type="button" @click="editBlock(Number(index))" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800">
                                            <i class="ti ti-settings text-base"></i>
                                        </button>
                                    </Tooltip>
                                    <Tooltip :content="t('Remove')" placement="top">
                                        <button type="button" @click="removeBlock(Number(index))" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300">
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

        <div v-if="addBlockModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-md font-bold text-gray-900 dark:text-white">{{ t('Add Widget') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Choose a widget to add to the sidebar layout.') }}</p>
                    </div>
                    <button type="button" class="rounded-full w-8 h-8 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" @click="addBlockModalOpen = false">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
                <div class="grid max-h-[70vh] grid-cols-1 gap-3 overflow-y-auto p-6 sm:grid-cols-2">
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
            </div>
        </div>

        <div v-if="settingsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Sidebar Settings') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Configure sidebar layout, sticky behavior, and page visibility.') }}</p>
                    </div>
                    <button type="button" class="rounded-full w-8 h-8 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" @click="settingsModalOpen = false">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
                <div class="grid gap-6 overflow-y-auto p-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                        <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Layout') }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Choose where the sidebar appears and when it stays visible.') }}</p>

                        <div class="mt-5 space-y-5">
                            <AppSelect
                                v-model="form.position"
                                :label="t('Position')"
                                :options="positionOptions.map((option) => ({ value: option.value, label: t(option.label) }))"
                            />

                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Sticky Sidebar') }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Keep the sidebar visible while the page scrolls.') }}</div>
                                </div>
                                <button type="button" role="switch" :aria-checked="form.sticky" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition" :class="form.sticky ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="form.sticky = !form.sticky">
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="form.sticky ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Show On Pages') }}</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Leave all pages unselected to show the sidebar everywhere.') }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-300">{{ form.show_on_pages.length }}</span>
                        </div>

                        <div class="mt-4 flex max-h-72 flex-wrap gap-2 overflow-y-auto">
                            <button
                                v-for="page in availablePages"
                                :key="page.key"
                                type="button"
                                class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition"
                                :class="form.show_on_pages.includes(page.key)
                                    ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300'
                                    : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:border-primary-900/40'"
                                @click="togglePage(page.key)"
                            >
                                {{ page.label }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button type="button" @click="settingsModalOpen = false" class="btn-primary">{{ t('Done') }}</button>
                </div>
            </div>
        </div>

        <div v-if="blockModalOpen && editingBlock" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t(getBlockLabel(editingBlock.data.type)) }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Adjust the widget content and behavior for this sidebar block.') }}</p>
                    </div>
                    <button type="button" class="rounded-full w-8 h-8 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" @click="blockModalOpen = false">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <div class="space-y-4 overflow-y-auto p-6">
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
                            <button type="button" role="switch" :aria-checked="editingBlock.data.config.show_count" class="relative inline-flex h-6 w-11 rounded-full transition" :class="editingBlock.data.config.show_count ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="editingBlock.data.config.show_count = !editingBlock.data.config.show_count">
                                <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="editingBlock.data.config.show_count ? 'translate-x-5' : 'translate-x-0.5'"></span>
                            </button>
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

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button type="button" @click="blockModalOpen = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="button" @click="saveBlockSettings" class="rounded-lg btn-primary px-4 py-2 text-sm font-semibold">{{ t('Done') }}</button>
                </div>
            </div>
        </div>

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

        <div v-if="importModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Import Sidebar Configuration') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Paste a previously exported JSON file to replace the current sidebar setup.') }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" @click="importModalOpen = false">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>
                <div class="p-6">
                    <textarea v-model="importJsonText" rows="10" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Paste JSON here...')"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-950">
                    <button type="button" @click="importModalOpen = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800">{{ t('Cancel') }}</button>
                    <button type="button" @click="importConfig" class="rounded-lg btn-primary px-4 py-2 text-sm font-semibold">{{ t('Import') }}</button>
                </div>
            </div>
        </div>
</template>
