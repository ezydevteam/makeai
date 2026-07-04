<script setup lang="ts">
import { computed } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ name: 'MenuTreeDraggable' })

type MenuItemType = 'url' | 'page' | 'route'
type LinkTarget = '_self' | '_blank'
type VisibilityRule = 'none' | 'guest' | 'auth' | 'pro'
type BadgeColor = 'green' | 'blue' | 'violet' | 'amber' | 'red' | 'gray'

interface PageOption {
    id: number
    title: string
    slug: string
}

interface MenuItemNode {
    id: number
    menu_id: number
    parent_id: number | null
    label: string
    type: MenuItemType
    url: string | null
    page_id: number | null
    route_name: string | null
    target: LinkTarget
    icon: string | null
    badge_text: string | null
    badge_color: BadgeColor | null
    is_active: boolean
    requires_auth: VisibilityRule
    mega_menu: boolean
    mega_menu_content: string | null
    sort_order: number
    page?: PageOption | null
    children: MenuItemNode[]
}

const props = withDefaults(defineProps<{
    modelValue: MenuItemNode[]
    level?: number
    dragging?: boolean
}>(), {
    level: 0,
    dragging: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: MenuItemNode[]]
    edit: [item: MenuItemNode]
    delete: [item: MenuItemNode]
    dragStarted: []
    dragEnded: []
    reordered: []
}>()

const { t } = useTranslate()

const list = computed({
    get: () => props.modelValue,
    set: (value: MenuItemNode[]) => emit('update:modelValue', value),
})

const itemDestination = (item: MenuItemNode) => {
    if (item.type === 'page') return item.page ? `/${item.page.slug}` : t('Selected page')
    if (item.type === 'route') return item.route_name || t('Route')

    return item.url || '#'
}

const dragOptions = computed(() => ({
    group: 'menu-builder-items',
    animation: 180,
    handle: '.menu-drag-handle',
    ghostClass: 'menu-drag-ghost',
    chosenClass: 'menu-drag-chosen',
    dragClass: 'menu-drag-active',
    fallbackClass: 'menu-drag-fallback',
    fallbackOnBody: true,
    swapThreshold: 0.65,
    emptyInsertThreshold: 48,
}))
</script>

<template>
    <VueDraggable
        v-model="list"
        class="menu-dropzone space-y-2 rounded-xl border border-dashed border-gray-200 bg-white/70 p-2 transition-colors dark:border-surface-700 dark:bg-surface-900/50"
        :class="level > 0 ? 'mt-3 min-h-14' : 'min-h-24'"
        v-bind="dragOptions"
        @start="emit('dragStarted')"
        @end="emit('dragEnded'); emit('reordered')"
    >
        <div
            v-for="item in list"
            :key="item.id"
            class="menu-tree-item rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 transition-all dark:border-surface-700 dark:bg-surface-800"
        >
            <div class="flex items-center gap-4">
                <button
                    type="button"
                    class="menu-drag-handle flex h-9 w-9 cursor-grab items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition hover:border-primary-200 hover:text-primary-600 active:cursor-grabbing dark:border-surface-700 dark:bg-surface-900"
                    :aria-label="t('Drag menu item')"
                >
                    <i class="ti ti-menu"></i>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.label }}</span>
                    </div>
                    <div class="mt-1 truncate font-mono text-xs text-gray-500">{{ itemDestination(item) }}</div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800" :aria-label="t('Edit item')" @click="emit('edit', item)">
                        <i class="ti ti-settings text-base"></i>
                    </button>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300" :aria-label="t('Delete item')" @click="emit('delete', item)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                    </button>
                </div>
            </div>

            <MenuTreeDraggable
                v-model="item.children"
                :dragging="dragging"
                :level="level + 1"
                :class="!item.children.length && !dragging ? 'menu-child-dropzone-hidden' : ''"
                @edit="emit('edit', $event)"
                @delete="emit('delete', $event)"
                @drag-started="emit('dragStarted')"
                @drag-ended="emit('dragEnded')"
                @reordered="emit('reordered')"
            />
        </div>
    </VueDraggable>
</template>

<style scoped>
.menu-drag-ghost {
    opacity: 1;
    border-color: var(--color-primary-300);
    background: #fff;
    min-height: 58px;
    box-shadow: inset 0 0 0 2px rgb(99 102 241 / 0.16);
}

.menu-drag-chosen,
.menu-drag-chosen.menu-tree-item,
.menu-tree-item.sortable-chosen,
.menu-tree-item.sortable-drag {
    opacity: 1 !important;
    background: #fff !important;
}

.menu-drag-ghost > * {
    visibility: hidden;
}

.menu-drag-chosen .menu-drag-handle {
    color: var(--color-primary-600);
    border-color: var(--color-primary-300);
}

.menu-drag-active {
    opacity: 1 !important;
    background: #fff !important;
    box-shadow: var(--shadow-md) !important;
}

.menu-drag-fallback {
    opacity: 1 !important;
    background: #fff !important;
    border: 1px solid var(--color-primary-200) !important;
    box-shadow: var(--shadow-md) !important;
}

.menu-child-dropzone-hidden {
    height: 0;
    min-height: 0;
    margin-top: 0;
    padding: 0;
    border-width: 0;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
}

.menu-dropzone:empty::before {
    content: '';
    display: block;
    min-height: 30px;
    border: 1px dashed var(--color-primary-200);
    border-radius: 0.5rem;
    background: rgb(99 102 241 / 0.06);
}
</style>
