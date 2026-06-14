<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import IconClassSelect from '@/Components/IconClassSelect.vue'
import MenuTreeDraggable from '@/Components/MenuTreeDraggable.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

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
const toast = useToastr()

const selectedMenuId = ref<number | null>(props.menus[0]?.id ?? null)
const showMenuModal = ref(false)
const showItemModal = ref(false)
const editingMenuId = ref<number | null>(null)
const editingItemId = ref<number | null>(null)
const menuSlugTouched = ref(false)
const deleteTarget = ref<{ type: 'menu' | 'item'; id: number; label: string } | null>(null)
const workingTree = ref<MenuItemNode[]>([])
const isDraggingMenuItem = ref(false)
const importJsonText = ref('')
const showImportModal = ref(false)
const deleteForm = useForm({})
const reorderForm = useForm<{ items: ReorderItem[] }>({ items: [] })
const importForm = useForm<{ json: string }>({ json: '' })

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

const linkTypeOptions = computed(() => [
    { value: 'url', label: t('Custom URL') },
    { value: 'page', label: t('CMS Page') },
    { value: 'route', label: t('Named Route') },
])

const pageOptions = computed(() => [
    { value: '', label: t('Choose a page') },
    ...props.pages.map((page) => ({ value: String(page.id), label: page.title })),
])

const routeSelectOptions = computed(() => [
    { value: '', label: t('Choose a route') },
    ...props.routeOptions.map((option) => ({ value: option.name, label: `${option.label} - ${option.name}` })),
])

const parentSelectOptions = computed(() => [
    { value: '', label: t('Top level') },
    ...parentOptions.value.map((item) => ({ value: String(item.id), label: item.label })),
])

const targetOptions = computed(() => [
    { value: '_self', label: t('Same Tab') },
    { value: '_blank', label: t('New Tab') },
])

const badgeColorOptions = computed(() => [
    { value: '', label: t('No badge color') },
    ...badgeColors.map((badge) => ({ value: badge.value, label: t(badge.label) })),
])

const visibilitySelectOptions = computed(() =>
    visibilityOptions.map((option) => ({ value: option.value, label: t(option.label) }))
)

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

const addQuickShortcut = (shortcut: { label: string; url: string; icon: string }) => {
    if (!selectedMenu.value) return

    itemForm.label = shortcut.label
    itemForm.type = 'url'
    itemForm.url = shortcut.url
    itemForm.page_id = ''
    itemForm.route_name = ''
    itemForm.parent_id = ''
    itemForm.target = '_self'
    itemForm.icon = shortcut.icon
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

const addCategoryShortcut = (category: CategoryOption, source: 'blog' | 'ai') => {
    addQuickShortcut({
        label: category.name,
        url: source === 'blog'
            ? `/blog/category/${category.slug}`
            : `/ai-tools/category/${category.slug}`,
        icon: source === 'blog' ? 'ti ti-news' : 'ti ti-sparkles',
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

const saveChanges = () => {
    if (!selectedMenu.value) return
    persistTreeOrder()
}

const persistTreeOrder = () => {
    if (!selectedMenu.value) return

    reorderForm.items = flattenTreeForPayload(workingTree.value)
    reorderForm.post(route('admin.menus.items.reorder', selectedMenu.value.id), {
        preserveScroll: true,
    })
}

const exportMenu = () => {
    if (!selectedMenu.value) return
    const data = {
        name: selectedMenu.value.name,
        slug: selectedMenu.value.slug,
        items: selectedMenu.value.items.map((item) => ({
            id: item.id,
            label: item.label,
            type: item.type,
            url: item.url,
            page_id: item.page_id,
            route_name: item.route_name,
            parent_id: item.parent_id,
            target: item.target,
            icon: item.icon,
            badge_text: item.badge_text,
            badge_color: item.badge_color,
            is_active: item.is_active,
            requires_auth: item.requires_auth,
            mega_menu: item.mega_menu,
            mega_menu_content: item.mega_menu_content,
            sort_order: item.sort_order,
        })),
    }
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${data.slug || 'menu'}-config.json`
    a.click()
    URL.revokeObjectURL(url)
}

const importMenu = () => {
    if (!selectedMenu.value || !importJsonText.value.trim()) return
    importForm.json = importJsonText.value
    importForm.post(route('admin.menus.import', selectedMenu.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            importJsonText.value = ''
            showImportModal.value = false
            toast.success(t('Menu items imported successfully.'))
        },
        onError: (errors: Record<string, string>) => {
            toast.error(errors.json || t('Import failed.'))
        },
    })
}
</script>

<template>
    <Head :title="t('Menus - Admin')" />

    <div class="mx-auto max-w-7xl px-6 py-8">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Menu Builder') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Structure public navigation menus for headers, footers, mobile drawers, and sidebars.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <Tooltip v-if="selectedMenu" :content="t('Export JSON')" placement="top">
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Export JSON')" @click="exportMenu">
                        <i class="ti ti-file-export text-base"></i>
                    </button>
                </Tooltip>
                <Tooltip v-if="selectedMenu" :content="t('Import JSON')" placement="top">
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('Import JSON')" @click="showImportModal = true">
                        <i class="ti ti-file-import text-base"></i>
                    </button>
                </Tooltip>
                <button type="button" :disabled="reorderForm.processing || !selectedMenu" class="inline-flex items-center gap-2 rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60" @click="saveChanges">
                    <svg v-if="reorderForm.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <i v-else class="ti ti-device-floppy text-sm"></i>
                    <span>{{ reorderForm.processing ? t('Saving...') : t('Save Changes') }}</span>
                </button>
            </div>
        </section>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Navigation Menus') }}</h2>
                        </div>
                        <Tooltip :content="t('New Menu')" placement="top">
                            <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-primary-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-primary-900/20" :aria-label="t('New Menu')" @click="openCreateMenu">
                                <i class="ti ti-plus text-base"></i>
                            </button>
                        </Tooltip>
                    </div>

                    <div v-if="menus.length" class="mt-5 space-y-2">
                    <button
                        v-for="menu in menus"
                        :key="menu.id"
                        type="button"
                        class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left text-sm transition dark:border-surface-700 rtl:text-right"
                        :class="selectedMenu?.id === menu.id ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-900/40 dark:bg-primary-900/20 dark:text-primary-300' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-primary-200 hover:bg-white dark:bg-surface-800/70 dark:text-gray-300 dark:hover:border-primary-900/40 dark:hover:bg-surface-800'"
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
                    <div v-else class="mt-5 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/70 p-8 text-center text-sm text-gray-500 dark:border-surface-700 dark:bg-surface-800/50">
                        {{ t('No menus yet.') }}
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <h3 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Quick Add Shortcuts') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Create ready-made links from your active blog and AI categories.') }}</p>

                    <div class="mt-5 space-y-5">
                        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Featured Pages') }}</h4>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300"
                                    @click="addQuickShortcut({ label: t('AI Templates'), url: '/ai-tools', icon: 'ti ti-layout-grid' })"
                                >
                                    {{ t('AI Templates') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300"
                                    @click="addQuickShortcut({ label: t('Chat'), url: '/chat', icon: 'ti ti-message-circle' })"
                                >
                                    {{ t('Chat') }}
                                </button>
                            </div>
                        </section>

                        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Blog Categories') }}</h4>
                            <div class="mt-3 flex max-h-40 flex-wrap gap-2 overflow-y-auto">
                                <button v-for="category in blogCategories" :key="category.id" type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="addCategoryShortcut(category, 'blog')">
                                    {{ category.name }}
                                </button>
                            </div>
                            <p v-if="!blogCategories.length" class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ t('No active blog categories found.') }}</p>
                        </section>

                        <section class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-surface-800 dark:bg-surface-800/70">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('AI Categories') }}</h4>
                            <div class="mt-3 flex max-h-40 flex-wrap gap-2 overflow-y-auto">
                                <button v-for="category in aiCategories" :key="category.id" type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300" @click="addCategoryShortcut(category, 'ai')">
                                    {{ category.name }}
                                </button>
                            </div>
                            <p v-if="!aiCategories.length" class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ t('No active AI categories found.') }}</p>
                        </section>
                    </div>
                </section>
            </aside>

            <section class="min-w-0">
                <div v-if="selectedMenu" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ selectedMenu.name }}</h2>
                            <p class="mt-1 font-mono text-xs uppercase tracking-wide text-gray-400">{{ t('Slug') }}: {{ selectedMenu.slug }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Tooltip :content="t('Edit Menu')" placement="top">
                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700 transition hover:bg-gray-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800" :aria-label="t('Edit Menu')" @click="openEditMenu(selectedMenu)">
                                    <i class="ti ti-pencil text-base"></i>
                                </button>
                            </Tooltip>
                            <Tooltip :content="t('Delete')" placement="top">
                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-danger-600 transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-300" :aria-label="t('Delete')" @click="requestDeleteMenu(selectedMenu)">
                                    <i class="ti ti-trash text-base"></i>
                                </button>
                            </Tooltip>
                            <button type="button" class="inline-flex h-10 px-4 items-center gap-2 rounded-lg bg-gray-600 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-gray-800" @click="openCreateItem">
                                <i class="ti ti-plus text-sm"></i>
                                {{ t('Add Link') }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="xl:col-span-2">
                            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Menu Structure') }}</h3>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ t('Drag by the handle, drop between items, or drop into a child zone to nest links.') }}</p>
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
                    </div>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-gray-200 bg-white py-16 text-center shadow-card dark:border-surface-700 dark:bg-surface-900">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Create your first menu') }}</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ t('Menus can be assigned later in the header, footer, mobile drawer, and sidebar builders.') }}</p>
                    <button type="button" class="mt-5 inline-flex items-center gap-2 rounded-lg btn-primary px-4 py-2.5 text-sm font-semibold" @click="openCreateMenu">
                        <i class="ti ti-plus text-sm"></i>
                        {{ t('New Menu') }}
                    </button>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="showMenuModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showMenuModal = false">
                <div class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ editingMenuId ? t('Edit Menu') : t('New Menu') }}</h2>
                        <Tooltip :content="t('Close')" placement="top">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showMenuModal = false">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </Tooltip>
                    </div>
                    <form class="space-y-4 p-6" @submit.prevent="submitMenu">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Menu Name') }}
                            <input v-model="menuForm.name" type="text" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Footer Links')" required @input="syncMenuSlug">
                            <span v-if="menuForm.errors.name" class="mt-1 block text-xs text-danger-600">{{ menuForm.errors.name }}</span>
                        </label>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Slug') }}
                            <input v-model="menuForm.slug" type="text" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('footer_links')" required @input="menuSlugTouched = true" @blur="markMenuSlugTouched">
                            <span v-if="menuForm.errors.slug" class="mt-1 block text-xs text-danger-600">{{ menuForm.errors.slug }}</span>
                        </label>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-surface-800">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800" @click="showMenuModal = false">{{ t('Cancel') }}</button>
                            <button type="submit" :disabled="menuForm.processing" class="rounded-lg btn-primary disabled:opacity-60">
                                {{ menuForm.processing ? t('Saving...') : t('Save Menu') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showItemModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showItemModal = false">
                <div class="max-h-[92vh] w-full max-w-3xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ editingItemId ? t('Edit Menu Item') : t('Add Menu Item') }}</h2>
                        <Tooltip :content="t('Close')" placement="top">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showItemModal = false">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </Tooltip>
                    </div>
                    <form class="grid max-h-[calc(92vh-72px)] grid-cols-1 gap-4 overflow-y-auto p-6 md:grid-cols-2" @submit.prevent="submitItem">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Label') }}
                            <input v-model="itemForm.label" type="text" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('About Us')" required>
                            <span v-if="itemForm.errors.label" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.label }}</span>
                        </label>
                        <AppSelect v-model="itemForm.type" :label="t('Link Type')" :options="linkTypeOptions" />
                        <label v-if="itemForm.type === 'url'" class="block text-sm font-medium text-gray-700 dark:text-gray-300 md:col-span-2">
                            {{ t('URL') }}
                            <input v-model="itemForm.url" type="text" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('https://example.com or /menu-name')">
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ t('Use /menu-name for internal links and a full URL like https://example.com for external links.') }}</span>
                            <span v-if="itemForm.errors.url" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.url }}</span>
                        </label>
                        <div v-if="itemForm.type === 'page'" class="block md:col-span-2">
                            <AppSelect v-model="itemForm.page_id" :label="t('Select Page')" :options="pageOptions" :placeholder="t('Choose a page')" live-search />
                            <span v-if="itemForm.errors.page_id" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.page_id }}</span>
                        </div>
                        <div v-if="itemForm.type === 'route'" class="block md:col-span-2">
                            <AppSelect v-model="itemForm.route_name" :label="t('Named Route')" :options="routeSelectOptions" :placeholder="t('Choose a route')" live-search />
                            <span v-if="itemForm.errors.route_name" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.route_name }}</span>
                        </div>
                        <div class="block">
                            <AppSelect v-model="itemForm.parent_id" :label="t('Parent Item')" :options="parentSelectOptions" :placeholder="t('Top level')" live-search />
                            <span v-if="itemForm.errors.parent_id" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.parent_id }}</span>
                        </div>
                        <AppSelect v-model="itemForm.target" :label="t('Target')" :options="targetOptions" />
                        <IconClassSelect
                            v-model="itemForm.icon"
                            :label="t('Icon')"
                            :placeholder="t('Choose an icon')"
                            :error="itemForm.errors.icon"
                        />
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Badge Text') }}
                            <input v-model="itemForm.badge_text" type="text" maxlength="50" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('New')">
                            <span v-if="itemForm.errors.badge_text" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.badge_text }}</span>
                        </label>
                        <div class="block">
                            <AppSelect v-model="itemForm.badge_color" :label="t('Badge Color')" :options="badgeColorOptions" :placeholder="t('No badge color')" />
                            <span v-if="itemForm.errors.badge_color" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.badge_color }}</span>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800">
                            <div class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Status') }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">{{ itemForm.is_active ? t('This menu item is visible.') : t('This menu item is currently hidden.') }}</span>
                                </span>
                                <button type="button" role="switch" :aria-checked="itemForm.is_active" class="relative inline-flex h-6 w-11 rounded-full transition" :class="itemForm.is_active ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="itemForm.is_active = !itemForm.is_active">
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="itemForm.is_active ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                            </div>
                            <span v-if="itemForm.errors.is_active" class="mt-2 block text-xs text-danger-600">{{ itemForm.errors.is_active }}</span>
                        </div>
                        <div class="block">
                            <AppSelect v-model="itemForm.requires_auth" :label="t('Visibility')" :options="visibilitySelectOptions" />
                            <span v-if="itemForm.errors.requires_auth" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.requires_auth }}</span>
                        </div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ t('Sort Order') }}
                            <input v-model.number="itemForm.sort_order" type="number" min="0" class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white">
                        </label>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-surface-700 dark:bg-surface-800 md:col-span-2">
                            <label class="flex items-center justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ t('Mega Menu') }}</span>
                                    <span class="mt-1 block text-xs text-gray-500">{{ t('Enable a larger dropdown panel for this menu item.') }}</span>
                                </span>
                                <button type="button" role="switch" :aria-checked="itemForm.mega_menu" class="relative inline-flex h-6 w-11 rounded-full transition" :class="itemForm.mega_menu ? 'bg-primary-600' : 'bg-gray-300 dark:bg-surface-700'" @click="itemForm.mega_menu = !itemForm.mega_menu">
                                    <span class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white transition" :class="itemForm.mega_menu ? 'translate-x-5' : 'translate-x-0.5'"></span>
                                </button>
                            </label>
                            <label v-if="itemForm.mega_menu" class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ t('Mega Menu Content') }}
                                <textarea v-model="itemForm.mega_menu_content" rows="5" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm dark:border-surface-700 dark:bg-surface-900 dark:text-white" :placeholder="t('Optional HTML or structured content for the theme renderer.')"></textarea>
                                <span v-if="itemForm.errors.mega_menu_content" class="mt-1 block text-xs text-danger-600">{{ itemForm.errors.mega_menu_content }}</span>
                            </label>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-surface-800 md:col-span-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800" @click="showItemModal = false">{{ t('Cancel') }}</button>
                            <button type="submit" :disabled="itemForm.processing" class="rounded-lg btn-primary disabled:opacity-60">
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

        <!-- Import Modal -->
        <Teleport to="body">
            <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm" @click.self="showImportModal = false">
                <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-surface-800">
                        <h2 class="font-heading text-lg font-bold text-gray-900 dark:text-white">{{ t('Import Menu Items') }}</h2>
                        <Tooltip :content="t('Close')" placement="top">
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-800" :aria-label="t('Close')" @click="showImportModal = false">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </Tooltip>
                    </div>
                    <div class="p-6 space-y-4">
                        <textarea v-model="importJsonText" rows="10" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-xs dark:border-surface-700 dark:bg-surface-800 dark:text-white" :placeholder="t('Paste exported menu JSON here...')"></textarea>
                        <div class="flex items-center justify-end gap-3">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-surface-800" @click="showImportModal = false">{{ t('Cancel') }}</button>
                            <button type="button" :disabled="importForm.processing" class="rounded-lg btn-primary disabled:opacity-60" @click="importMenu">
                                {{ importForm.processing ? t('Importing...') : t('Import') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
