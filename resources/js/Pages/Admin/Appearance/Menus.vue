<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import MenuTreeDraggable from '@/Components/MenuTreeDraggable.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

declare const route: (name: string, params?: unknown) => string

type MenuItemType = 'url' | 'page' | 'route'
type LinkTarget = '_self' | '_blank'
type VisibilityRule = 'none' | 'guest' | 'auth' | 'pro'
type BadgeColor = 'green' | 'blue' | 'violet' | 'amber' | 'red' | 'gray'

interface PageOption {
    id: number
    title: string
    slug: string
}

interface CategoryOption {
    id: number
    name: string
    slug: string
}

interface RouteOption {
    name: string
    label: string
}

interface MenuItem {
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
}

interface MenuItemNode extends MenuItem {
    children: MenuItemNode[]
}

interface Menu {
    id: number
    name: string
    slug: string
    items: MenuItem[]
}

interface ReorderItem {
    id: number
    parent_id: number | null
    sort_order: number
}

const props = defineProps<{
    menus: Menu[]
    pages: PageOption[]
    blogCategories: CategoryOption[]
    aiCategories: CategoryOption[]
    routeOptions: RouteOption[]
}>()

const { t } = useTranslate()

const selectedMenuId = ref<number | null>(props.menus[0]?.id ?? null)
const showMenuModal = ref(false)
const showItemModal = ref(false)
const editingMenuId = ref<number | null>(null)
const editingItemId = ref<number | null>(null)
const menuSlugTouched = ref(false)
const deleteTarget = ref<{ type: 'menu' | 'item'; id: number; label: string } | null>(null)
const workingTree = ref<MenuItemNode[]>([])
const isDraggingMenuItem = ref(false)
const deleteForm = useForm({})
const reorderForm = useForm<{ items: ReorderItem[] }>({ items: [] })

const menuForm = useForm({
    name: '',
    slug: '',
})

const itemForm = useForm({
    label: '',
    type: 'url' as MenuItemType,
    url: '',
    page_id: '',
    route_name: '',
    parent_id: '',
    target: '_self' as LinkTarget,
    icon: '',
    badge_text: '',
    badge_color: '' as BadgeColor | '',
    is_active: true,
    requires_auth: 'none' as VisibilityRule,
    mega_menu: false,
    mega_menu_content: '',
    sort_order: 0,
})

const badgeColors: Array<{ value: BadgeColor; label: string; classes: string }> = [
    { value: 'green', label: 'Green', classes: 'bg-primary-100 text-primary-700' },
    { value: 'blue', label: 'Blue', classes: 'bg-secondary-100 text-secondary-700' },
    { value: 'violet', label: 'Violet', classes: 'bg-violet-100 text-violet-700' },
    { value: 'amber', label: 'Amber', classes: 'bg-amber-100 text-amber-700' },
    { value: 'red', label: 'Red', classes: 'bg-danger-100 text-danger-700' },
    { value: 'gray', label: 'Gray', classes: 'bg-gray-100 text-gray-600' },
]

const visibilityOptions: Array<{ value: VisibilityRule; label: string }> = [
    { value: 'none', label: 'Everyone' },
    { value: 'guest', label: 'Guests only' },
    { value: 'auth', label: 'Logged-in users' },
    { value: 'pro', label: 'Pro users' },
]

const selectedMenu = computed(() => props.menus.find((menu) => menu.id === selectedMenuId.value) ?? props.menus[0] ?? null)

const buildMenuTree = (menu: Menu | null): MenuItemNode[] => {
    if (!menu) return []

    const nodes = new Map<number, MenuItemNode>()
    menu.items.forEach((item) => nodes.set(item.id, { ...item, children: [] }))

    const roots: MenuItemNode[] = []
    nodes.forEach((node) => {
        if (node.parent_id && nodes.has(node.parent_id)) {
            nodes.get(node.parent_id)?.children.push(node)
            return
        }

        roots.push(node)
    })

    const sortNodes = (items: MenuItemNode[]) => {
        items.sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label))
        items.forEach((item) => sortNodes(item.children))
    }

    sortNodes(roots)
    return roots
}

const parentOptions = computed(() => {
    if (!selectedMenu.value) return []

    return selectedMenu.value.items
        .filter((item) => item.id !== editingItemId.value)
        .sort((a, b) => a.sort_order - b.sort_order || a.label.localeCompare(b.label))
})

watch(() => props.menus, (menus) => {
    if (!menus.length) {
        selectedMenuId.value = null
        return
    }

    if (!menus.some((menu) => menu.id === selectedMenuId.value)) {
        selectedMenuId.value = menus[0].id
    }
})

watch(selectedMenu, (menu) => {
    workingTree.value = buildMenuTree(menu)
}, { immediate: true })

const makeSlug = (value: string) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')

const resetMenuForm = () => {
    editingMenuId.value = null
    menuSlugTouched.value = false
    menuForm.reset()
    menuForm.clearErrors()
}

const resetItemForm = () => {
    editingItemId.value = null
    itemForm.reset()
    itemForm.type = 'url'
    itemForm.target = '_self'
    itemForm.icon = ''
    itemForm.badge_text = ''
    itemForm.badge_color = ''
    itemForm.is_active = true
    itemForm.requires_auth = 'none'
    itemForm.mega_menu = false
    itemForm.mega_menu_content = ''
    itemForm.sort_order = selectedMenu.value?.items.length ?? 0
    itemForm.clearErrors()
}

const openCreateMenu = () => {
    resetMenuForm()
    showMenuModal.value = true
}

const openEditMenu = (menu: Menu) => {
    editingMenuId.value = menu.id
    menuSlugTouched.value = true
    menuForm.name = menu.name
    menuForm.slug = menu.slug
    showMenuModal.value = true
}

const syncMenuSlug = () => {
    if (menuSlugTouched.value) return
    menuForm.slug = makeSlug(menuForm.name)
}

const markMenuSlugTouched = () => {
    menuSlugTouched.value = true
    menuForm.slug = makeSlug(menuForm.slug)
}

const submitMenu = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMenuModal.value = false
            resetMenuForm()
        },
    }

    if (editingMenuId.value) {
        menuForm.post(route('admin.menus.update', editingMenuId.value), options)
        return
    }

    menuForm.post(route('admin.menus.store'), options)
}

const openCreateItem = () => {
    resetItemForm()
    showItemModal.value = true
}

const openEditItem = (item: MenuItem) => {
    editingItemId.value = item.id
    itemForm.label = item.label
    itemForm.type = item.type
    itemForm.url = item.url ?? ''
    itemForm.page_id = item.page_id ? String(item.page_id) : ''
    itemForm.route_name = item.route_name ?? ''
    itemForm.parent_id = item.parent_id ? String(item.parent_id) : ''
    itemForm.target = item.target
    itemForm.icon = item.icon ?? ''
    itemForm.badge_text = item.badge_text ?? ''
    itemForm.badge_color = item.badge_color ?? ''
    itemForm.is_active = item.is_active
    itemForm.requires_auth = item.requires_auth
    itemForm.mega_menu = item.mega_menu
    itemForm.mega_menu_content = item.mega_menu_content ?? ''
    itemForm.sort_order = item.sort_order
    showItemModal.value = true
}

const submitItem = () => {
    if (!selectedMenu.value) return

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showItemModal.value = false
            resetItemForm()
        },
    }

    if (editingItemId.value) {
        itemForm.post(route('admin.menus.item.update', editingItemId.value), options)
        return
    }

    itemForm.post(route('admin.menus.item.store', selectedMenu.value.id), options)
}

const addCategoryShortcut = (category: CategoryOption, source: 'blog' | 'ai') => {
    if (!selectedMenu.value) return

    itemForm.label = category.name
    itemForm.type = 'url'
    itemForm.url = source === 'blog'
        ? route('blog.category', category.slug)
        : route('ai.tools.category', category.slug)
    itemForm.page_id = ''
    itemForm.route_name = ''
    itemForm.parent_id = ''
    itemForm.target = '_self'
    itemForm.icon = source === 'blog' ? 'ti ti-news' : 'ti ti-sparkles'
    itemForm.badge_text = ''
    itemForm.badge_color = ''
    itemForm.is_active = true
    itemForm.requires_auth = 'none'
    itemForm.mega_menu = false
    itemForm.mega_menu_content = ''
    itemForm.sort_order = selectedMenu.value.items.length
    itemForm.post(route('admin.menus.item.store', selectedMenu.value.id), {
        preserveScroll: true,
        onSuccess: resetItemForm,
    })
}

const requestDeleteMenu = (menu: Menu) => {
    deleteTarget.value = { type: 'menu', id: menu.id, label: menu.name }
}

const requestDeleteItem = (item: MenuItem) => {
    deleteTarget.value = { type: 'item', id: item.id, label: item.label }
}

const confirmDelete = () => {
    if (!deleteTarget.value) return

    const target = deleteTarget.value
    const url = target.type === 'menu'
        ? route('admin.menus.delete', target.id)
        : route('admin.menus.item.delete', target.id)

    deleteForm.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null
        },
    })
}

const flattenTreeForPayload = (items: MenuItemNode[], parentId: number | null = null): ReorderItem[] => items.flatMap((item, index) => [
    {
        id: item.id,
        parent_id: parentId,
        sort_order: index,
    },
    ...flattenTreeForPayload(item.children, item.id),
])

const persistTreeOrder = () => {
    if (!selectedMenu.value) return

    reorderForm.items = flattenTreeForPayload(workingTree.value)
    reorderForm.post(route('admin.menus.items.reorder', selectedMenu.value.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="t('Menu Builder - Admin')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Menu Builder') }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ t('Structure public navigation menus for headers, footers, mobile drawers, and sidebars.') }}</p>
            </div>
            <button type="button" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary-500/20 transition-all hover:bg-primary-500" @click="openCreateMenu">
                {{ t('New Menu') }}
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <aside class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <h2 class="px-2 text-xs font-bold uppercase tracking-wide text-gray-400">{{ t('Navigation Menus') }}</h2>
                <div v-if="menus.length" class="mt-4 space-y-2">
                    <button
                        v-for="menu in menus"
                        :key="menu.id"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm transition-all dark:border-surface-700 rtl:text-right"
                        :class="selectedMenu?.id === menu.id ? 'border-primary-200 bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-100 text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-surface-800'"
                        @click="selectedMenuId = menu.id"
                    >
                        <span>
                            <span class="block font-semibold">{{ menu.name }}</span>
                            <span class="mt-1 block font-mono text-[11px] text-gray-400">{{ menu.slug }}</span>
                        </span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:bg-surface-800">
                            {{ menu.items.length }}
                        </span>
                    </button>
                </div>
                <div v-else class="mt-6 rounded-lg border border-dashed border-gray-200 p-5 text-center text-sm text-gray-500 dark:border-surface-700">
                    {{ t('No menus yet.') }}
                </div>
            </aside>

            <section class="lg:col-span-3">
                <div v-if="selectedMenu" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex flex-col gap-4 border-b border-gray-100 bg-gray-50 px-6 py-4 dark:border-surface-800 dark:bg-surface-800/60 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedMenu.name }}</h2>
                            <p class="mt-1 font-mono text-xs uppercase tracking-wide text-gray-400">{{ t('Slug') }}: {{ selectedMenu.slug }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 transition-colors hover:border-primary-200 hover:bg-primary-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200" @click="openEditMenu(selectedMenu)">
                                {{ t('Edit Menu') }}
                            </button>
                            <button type="button" class="rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-primary-500" @click="openCreateItem">
                                {{ t('Add Link') }}
                            </button>
                            <button type="button" class="rounded-lg bg-danger-50 px-4 py-2 text-xs font-semibold text-danger-600 transition-colors hover:bg-danger-100 dark:bg-danger-900/20 dark:text-danger-300" @click="requestDeleteMenu(selectedMenu)">
                                {{ t('Delete') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 p-6 xl:grid-cols-3">
                        <div class="xl:col-span-2">
                            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Menu Structure') }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('Drag by the handle, drop between items, or drop into a child zone to nest links.') }}</p>
                                </div>
                                <span v-if="reorderForm.processing" class="rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-700">{{ t('Saving order...') }}</span>
                            </div>
                            <MenuTreeDraggable
                                v-model="workingTree"
                                :dragging="isDraggingMenuItem"
                                @edit="openEditItem"
                                @delete="requestDeleteItem"
                                @drag-started="isDraggingMenuItem = true"
                                @drag-ended="isDraggingMenuItem = false"
                                @reordered="persistTreeOrder"
                            />
                        </div>

                        <aside class="space-y-4">
                            <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Quick Add Blog Categories') }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="category in blogCategories" :key="category.id" type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="addCategoryShortcut(category, 'blog')">
                                        {{ category.name }}
                                    </button>
                                </div>
                                <p v-if="!blogCategories.length" class="mt-3 text-sm text-gray-500">{{ t('No active blog categories found.') }}</p>
                            </section>

                            <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Quick Add AI Categories') }}</h3>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="category in aiCategories" :key="category.id" type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="addCategoryShortcut(category, 'ai')">
                                        {{ category.name }}
                                    </button>
                                </div>
                                <p v-if="!aiCategories.length" class="mt-3 text-sm text-gray-500">{{ t('No active AI categories found.') }}</p>
                            </section>
                        </aside>
                    </div>
                </div>

                <div v-else class="rounded-xl border border-dashed border-gray-200 bg-white py-16 text-center shadow-sm dark:border-surface-800 dark:bg-surface-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Create your first menu') }}</h2>
                    <p class="mt-2 text-sm text-gray-500">{{ t('Menus can be assigned later in the header, footer, mobile drawer, and sidebar builders.') }}</p>
                    <button type="button" class="mt-5 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-500" @click="openCreateMenu">
                        {{ t('New Menu') }}
                    </button>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="showMenuModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showMenuModal = false">
                <div class="w-full max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingMenuId ? t('Edit Menu') : t('New Menu') }}</h2>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showMenuModal = false">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <form class="space-y-4 p-6" @submit.prevent="submitMenu">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Menu Name') }}
                            <input v-model="menuForm.name" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Footer Links')" required @input="syncMenuSlug">
                            <span v-if="menuForm.errors.name" class="mt-1 block text-xs text-danger-600">{{ menuForm.errors.name }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Slug') }}
                            <input v-model="menuForm.slug" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('footer_links')" required @input="menuSlugTouched = true" @blur="markMenuSlugTouched">
                            <span v-if="menuForm.errors.slug" class="mt-1 block text-xs text-danger-600">{{ menuForm.errors.slug }}</span>
                        </label>
                        <div class="flex items-center justify-end gap-3 rounded-b-xl pt-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800" @click="showMenuModal = false">{{ t('Cancel') }}</button>
                            <button type="submit" :disabled="menuForm.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-60">
                                {{ menuForm.processing ? t('Saving...') : t('Save Menu') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showItemModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showItemModal = false">
                <div class="max-h-[92vh] w-full max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-surface-800 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ editingItemId ? t('Edit Menu Item') : t('Add Menu Item') }}</h2>
                        <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showItemModal = false">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <form class="grid max-h-[calc(92vh-72px)] grid-cols-1 gap-4 overflow-y-auto p-6 md:grid-cols-2" @submit.prevent="submitItem">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Label') }}
                            <input v-model="itemForm.label" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('About Us')" required>
                            <span v-if="itemForm.errors.label" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.label }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Link Type') }}
                            <select v-model="itemForm.type" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="url">{{ t('Custom URL') }}</option>
                                <option value="page">{{ t('CMS Page') }}</option>
                                <option value="route">{{ t('Named Route') }}</option>
                            </select>
                        </label>
                        <label v-if="itemForm.type === 'url'" class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                            {{ t('URL') }}
                            <input v-model="itemForm.url" type="text" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="'https://example.com'">
                            <span v-if="itemForm.errors.url" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.url }}</span>
                        </label>
                        <label v-if="itemForm.type === 'page'" class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                            {{ t('Select Page') }}
                            <select v-model="itemForm.page_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="">{{ t('Choose a page') }}</option>
                                <option v-for="page in pages" :key="page.id" :value="String(page.id)">{{ page.title }}</option>
                            </select>
                            <span v-if="itemForm.errors.page_id" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.page_id }}</span>
                        </label>
                        <label v-if="itemForm.type === 'route'" class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                            {{ t('Named Route') }}
                            <select v-model="itemForm.route_name" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="">{{ t('Choose a route') }}</option>
                                <option v-for="option in routeOptions" :key="option.name" :value="option.name">{{ option.label }} - {{ option.name }}</option>
                            </select>
                            <span v-if="itemForm.errors.route_name" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.route_name }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Parent Item') }}
                            <select v-model="itemForm.parent_id" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="">{{ t('Top level') }}</option>
                                <option v-for="item in parentOptions" :key="item.id" :value="String(item.id)">{{ item.label }}</option>
                            </select>
                            <span v-if="itemForm.errors.parent_id" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.parent_id }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Target') }}
                            <select v-model="itemForm.target" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="_self">{{ t('Same Tab') }}</option>
                                <option value="_blank">{{ t('New Tab') }}</option>
                            </select>
                        </label>
                        <IconClassSelect
                            v-model="itemForm.icon"
                            :label="t('Icon')"
                            :placeholder="t('Choose an icon')"
                            :error="itemForm.errors.icon"
                        />
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Badge Text') }}
                            <input v-model="itemForm.badge_text" type="text" maxlength="50" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('New')">
                            <span v-if="itemForm.errors.badge_text" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.badge_text }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Badge Color') }}
                            <select v-model="itemForm.badge_color" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option value="">{{ t('No badge color') }}</option>
                                <option v-for="badge in badgeColors" :key="badge.value" :value="badge.value">{{ t(badge.label) }}</option>
                            </select>
                            <span v-if="itemForm.errors.badge_color" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.badge_color }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Status') }}
                            <select v-model="itemForm.is_active" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option :value="true">{{ t('Active') }}</option>
                                <option :value="false">{{ t('Inactive') }}</option>
                            </select>
                            <span v-if="itemForm.errors.is_active" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.is_active }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Visibility') }}
                            <select v-model="itemForm.requires_auth" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                                <option v-for="option in visibilityOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option>
                            </select>
                            <span v-if="itemForm.errors.requires_auth" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.requires_auth }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Sort Order') }}
                            <input v-model.number="itemForm.sort_order" type="number" min="0" class="mt-2 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 md:col-span-2">
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Mega Menu') }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">{{ t('Enable a larger dropdown panel for this menu item.') }}</span>
                                </span>
                                <input v-model="itemForm.mega_menu" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            </label>
                            <label v-if="itemForm.mega_menu" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Mega Menu Content') }}
                                <textarea v-model="itemForm.mega_menu_content" rows="5" class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" :placeholder="t('Optional HTML or structured content for the theme renderer.')"></textarea>
                                <span v-if="itemForm.errors.mega_menu_content" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.mega_menu_content }}</span>
                            </label>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-surface-800 md:col-span-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800" @click="showItemModal = false">{{ t('Cancel') }}</button>
                            <button type="submit" :disabled="itemForm.processing" class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-60">
                                {{ itemForm.processing ? t('Saving...') : t('Save Item') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ActionConfirmModal
            :open="Boolean(deleteTarget)"
            :title="deleteTarget?.type === 'menu' ? t('Delete menu?') : t('Delete menu item?')"
            :message="deleteTarget?.type === 'menu' ? t('This will delete the menu and all of its links.') : t('This will remove the selected link from the menu.')"
            :confirm-label="t('Delete')"
            :processing-label="t('Deleting...')"
            :processing="deleteForm.processing"
            @cancel="deleteTarget = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
