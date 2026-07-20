<script setup lang="ts">
import { computed } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import Tooltip from '@/Components/UI/Tooltip.vue'
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
    description: string | null
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
    toggleActive: [item: MenuItemNode]
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

const chipBase = 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold'

const visibilityMeta = (item: MenuItemNode): { label: string; icon: string } | null => {
    if (item.requires_auth === 'guest') return { label: t('Guests'), icon: 'ti ti-user-off' }
    if (item.requires_auth === 'auth') return { label: t('Members'), icon: 'ti ti-user-check' }
    if (item.requires_auth === 'pro') return { label: t('Pro'), icon: 'ti ti-crown' }
    return null
}

const badgePreviewClasses: Record<BadgeColor, string> = {
    green: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    blue: 'bg-secondary-100 text-secondary-700 dark:bg-secondary-900/30 dark:text-secondary-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    red: 'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-300',
    gray: 'bg-gray-100 text-gray-600 dark:bg-surface-700 dark:text-gray-300',
}

const badgeClassFor = (color: BadgeColor | null) => badgePreviewClasses[color ?? 'gray'] ?? badgePreviewClasses.gray

// The public theme renders at most three menu levels (top → submenu → flyout),
// so nesting is capped at level index 2. Deeper drops are rejected.
const MAX_LEVEL = 2

const subtreeDepth = (item: MenuItemNode): number =>
    item.children?.length ? 1 + Math.max(0, ...item.children.map(subtreeDepth)) : 0

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
    // Block any drop that would push the dragged item (or its own children) past level 3.
    onMove: (evt: { to?: HTMLElement; dragged?: HTMLElement }) => {
        const targetLevel = Number(evt?.to?.dataset?.level ?? 0)
        const draggedDepth = Number(evt?.dragged?.dataset?.depth ?? 0)
        return targetLevel + draggedDepth <= MAX_LEVEL
    },
}))
</script>

<template>
    <VueDraggable
        v-model="list"
        class="menu-dropzone space-y-2 transition-colors"
        :class="[
            level > 0 ? 'menu-subzone' : 'menu-rootzone',
            list.length === 0 ? 'is-empty' : '',
            dragging ? 'is-dragging' : '',
        ]"
        :data-empty-label="level > 0 ? t('Drop here to nest under this item') : t('No links yet. Use the Add Link button above, or drag items here.')"
        :data-level="level"
        v-bind="dragOptions"
        @start="emit('dragStarted')"
        @end="emit('dragEnded'); emit('reordered')"
    >
        <div
            v-for="item in list"
            :key="item.id"
            class="menu-tree-item rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 transition-all sm:px-4 dark:border-surface-700 dark:bg-surface-800"
            :class="!item.is_active ? 'opacity-60' : ''"
            :data-depth="subtreeDepth(item)"
        >
            <div class="group/row flex items-center gap-2.5 sm:gap-3">
                <button
                    type="button"
                    class="menu-drag-handle flex h-9 w-9 shrink-0 cursor-grab items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition hover:border-primary-200 hover:text-primary-600 active:cursor-grabbing dark:border-surface-700 dark:bg-surface-900"
                    :aria-label="t('Drag menu item')"
                >
                    <i class="ti ti-menu"></i>
                </button>
                <span v-if="item.icon" class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 sm:flex dark:border-surface-700 dark:bg-surface-900" :title="item.icon">
                    <i :class="[item.icon, 'text-base leading-none']"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.label }}</span>
                        <span v-if="!item.is_active" :class="chipBase" class="bg-gray-200 text-gray-600 dark:bg-surface-700 dark:text-gray-300">
                            <i class="ti ti-eye-off text-xs"></i>{{ t('Hidden') }}
                        </span>
                        <span v-if="item.badge_text" :class="[chipBase, badgeClassFor(item.badge_color)]">{{ item.badge_text }}</span>
                        <span v-if="visibilityMeta(item)" :class="chipBase" class="bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            <i :class="[visibilityMeta(item)?.icon, 'text-xs']"></i>{{ visibilityMeta(item)?.label }}
                        </span>
                        <span v-if="item.target === '_blank'" class="inline-flex items-center text-gray-400 dark:text-gray-500" :title="t('Opens in a new tab')">
                            <i class="ti ti-external-link text-xs"></i>
                        </span>
                        <span v-if="item.mega_menu" :class="chipBase" class="bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                            <i class="ti ti-layout-columns text-xs"></i>{{ t('Mega') }}
                        </span>
                        <span v-if="item.mega_menu && item.children.length > 4" :class="chipBase" class="bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300" :title="t('Only the first 4 columns are shown on the site.')">
                            <i class="ti ti-alert-triangle text-xs"></i>{{ t('4 max') }}
                        </span>
                    </div>
                    <div v-if="item.description" class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ item.description }}</div>
                    <div class="mt-0.5 truncate font-mono text-xs text-gray-400 dark:text-gray-500">{{ itemDestination(item) }}</div>
                </div>
                <div class="flex shrink-0 items-center gap-0.5 opacity-100 transition-opacity duration-150 sm:opacity-0 sm:focus-within:opacity-100 sm:group-hover/row:opacity-100">
                    <Tooltip :content="item.is_active ? t('Hide') : t('Show')" placement="top">
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-primary-50 hover:text-primary-600 dark:text-gray-500 dark:hover:bg-primary-900/20 dark:hover:text-primary-300"
                            :class="!item.is_active ? 'text-amber-500 dark:text-amber-400' : ''"
                            :aria-label="item.is_active ? t('Hide') : t('Show')"
                            @click="emit('toggleActive', item)"
                        >
                            <i :class="[item.is_active ? 'ti ti-eye' : 'ti ti-eye-off', 'text-base']"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Edit')" placement="top">
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-500 dark:hover:bg-primary-900/20 dark:hover:text-primary-300" :aria-label="t('Edit')" @click="emit('edit', item)">
                            <i class="ti ti-edit text-base"></i>
                        </button>
                    </Tooltip>
                    <Tooltip :content="t('Delete')" placement="top">
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:text-gray-500 dark:hover:bg-red-900/20 dark:hover:text-red-300" :aria-label="t('Delete')" @click="emit('delete', item)">
                            <i class="ti ti-trash text-base"></i>
                        </button>
                    </Tooltip>
                </div>
            </div>

            <MenuTreeDraggable
                v-if="level < MAX_LEVEL"
                v-model="item.children"
                :dragging="dragging"
                :level="level + 1"
                :class="!item.children.length && !dragging ? 'menu-child-dropzone-hidden' : ''"
                @edit="emit('edit', $event)"
                @delete="emit('delete', $event)"
                @toggle-active="emit('toggleActive', $event)"
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

/* Keep the drop placeholder and the floating drag image at row height —
   never let the dragged item's own nested zone inflate them. */
.menu-drag-ghost .menu-subzone,
.menu-drag-active .menu-subzone,
.menu-drag-fallback .menu-subzone {
    display: none !important;
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

.menu-dropzone.menu-child-dropzone-hidden {
    height: 0;
    min-height: 0;
    margin: 0;
    padding: 0;
    border-width: 0;
    overflow: hidden;
    opacity: 0;
    pointer-events: none;
}

/* Empty menu: friendly dashed drop area that stays compact. */
.menu-rootzone.is-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 5rem;
    border: 1.5px dashed rgb(209 213 219);
    border-radius: 0.75rem;
    background: rgb(249 250 251);
}

.dark .menu-rootzone.is-empty {
    border-color: rgb(51 65 85);
    background: rgb(30 41 59 / 0.4);
}

.menu-rootzone.is-empty::before {
    content: attr(data-empty-label);
    padding: 1rem 1.5rem;
    text-align: center;
    font-size: 0.8125rem;
    line-height: 1.25rem;
    color: rgb(107 114 128);
}

.dark .menu-rootzone.is-empty::before {
    color: rgb(148 163 184);
}

/* Nested list: indented with a connector rail so hierarchy is obvious. */
.menu-subzone {
    margin-top: 0.5rem;
    margin-inline-start: 1.25rem;
    padding-inline-start: 0.75rem;
    border-inline-start: 2px solid rgb(229 231 235);
}

.dark .menu-subzone {
    border-inline-start-color: rgb(51 65 85);
}

/* While dragging, reveal an empty nest target only where it's needed. */
.menu-subzone.is-empty.is-dragging {
    display: flex;
    align-items: center;
    min-height: 2.5rem;
    border-radius: 0 0.5rem 0.5rem 0;
    outline: 1.5px dashed var(--color-primary-300);
    outline-offset: -2px;
    background: rgb(99 102 241 / 0.05);
}

.menu-subzone.is-empty.is-dragging::before {
    content: attr(data-empty-label);
    padding-inline-start: 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-primary-600);
}
</style>
