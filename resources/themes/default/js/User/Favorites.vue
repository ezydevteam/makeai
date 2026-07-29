<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, nextTick, ref, onMounted, onUnmounted } from 'vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import FavoriteButton from '@themes/default/js/Components/FavoriteButton.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

declare const route: (name: string, params?: unknown) => string

interface FavoriteItem {
    id: number
    type: string
    model_id: number
    slug?: string
    title: string
    description: string | null
    url: string
    image: string | null
    icon: string
    color: string
    favorited_at: string | null
}

interface FavoriteGroup {
    type: string
    label: string
    items: FavoriteItem[]
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface Collection {
    ulid: string
    name: string
    icon: string | null
    tool_count: number
}

const props = defineProps<{
    groups: FavoriteGroup[]
    collections: Collection[]
    pagination: {
        current_page: number
        last_page: number
        total: number
        from: number | null
        to: number | null
        links: PaginationLink[]
    }
}>()

const { t } = useTranslate()
const activeType = ref('all')
const searchQuery = ref('')
const viewMode = ref<'grid' | 'list'>('grid')

/* The grid/list toggle is desktop-only: below md the grid is already a single column,
   so list view just gives a cramped row. `effectiveViewMode` — not `viewMode` — drives
   the layout, so someone who picked list on desktop and then narrows the window isn't
   left in list view with no visible control to leave it. Their choice is remembered and
   comes back when the toggle does. Same 767px breakpoint the theme uses elsewhere. */
const isMobileView = ref(false)
let mobileQuery: MediaQueryList | null = null
const syncIsMobileView = (e: MediaQueryList | MediaQueryListEvent) => { isMobileView.value = e.matches }

const effectiveViewMode = computed<'grid' | 'list'>(() => (isMobileView.value ? 'grid' : viewMode.value))

const openDropdown = ref<number | null>(null)
const addingToCollection = ref<string | null>(null)

const allItems = computed(() => props.groups.flatMap((group) => group.items))

const visibleItems = computed(() => {
    const items = activeType.value === 'all'
        ? allItems.value
        : props.groups.find((group) => group.type === activeType.value)?.items ?? []

    const query = searchQuery.value.trim().toLowerCase()

    if (query === '') {
        return items
    }

    return items.filter((item) =>
        item.title.toLowerCase().includes(query)
        || (item.description || '').toLowerCase().includes(query))
})

const tabs = computed(() => [
    { type: 'all', label: t('All'), count: props.pagination.total },
    ...props.groups.map((group) => ({
        type: group.type,
        label: group.label,
        count: group.items.length,
    })),
])

const formatDate = (value: string | null) => {
    if (!value) return ''

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
}

/* The panel is anchored `right-0`, but "Collect" is the first control in a
   justify-between row, so it sits at the LEFT of the card and a right-anchored 200px
   panel runs off the left edge of a phone screen. Only one panel is open at a time
   (v-if is keyed to openDropdown), so a single ref covers every card: measure once open
   and nudge it back inside. Same approach as the share menus in ToolPage/Blog Show. */
const collectionMenu = ref<HTMLElement | null>(null)
const collectionShift = ref(0)
const COLLECTION_MENU_GUTTER = 12

/* A function ref, not `ref="collectionMenu"`: a string ref declared inside a v-for is
   populated by Vue as an ARRAY of elements, which would blow up getBoundingClientRect.
   Nulls (fired when the previous card's panel unmounts) are ignored so they can't wipe
   the entry that was just mounted; toggleDropdown clears it explicitly on close. */
const setCollectionMenu = (el: Element | { $el?: Element } | null) => {
    if (el) collectionMenu.value = (el as HTMLElement)
}

const collectionMenuStyle = computed(() => (collectionShift.value
    ? { transform: `translateX(${collectionShift.value}px)` }
    : undefined))

const positionCollectionMenu = (viewportWidth: number) => {
    const el = collectionMenu.value
    if (!el) return

    const rect = el.getBoundingClientRect()
    let shift = 0
    if (rect.right > viewportWidth - COLLECTION_MENU_GUTTER) shift = viewportWidth - COLLECTION_MENU_GUTTER - rect.right
    // A panel wider than the screen can't satisfy both edges — keep the left one.
    if (rect.left + shift < COLLECTION_MENU_GUTTER) shift = COLLECTION_MENU_GUTTER - rect.left
    collectionShift.value = shift
}

function toggleDropdown(itemId: number) {
    openDropdown.value = openDropdown.value === itemId ? null : itemId
    collectionShift.value = 0
    collectionMenu.value = null
    if (openDropdown.value === null) return

    /* Read the viewport width *before* the panel mounts: an absolutely positioned panel
       spilling past the right edge adds scrollable overflow, which widens the layout
       viewport and would poison the measurement. */
    const viewportWidth = document.documentElement.clientWidth
    nextTick(() => positionCollectionMenu(viewportWidth))
}

function addToCollection(collectionUlid: string, toolSlug: string) {
    addingToCollection.value = collectionUlid
    router.post(route('user.dashboard.collections.tools.add', collectionUlid), { tool_slug: toolSlug }, {
        onFinish: () => {
            addingToCollection.value = null
            openDropdown.value = null
        },
    })
}

function handleClickOutside(event: MouseEvent) {
    if (openDropdown.value === null) return
    const target = event.target as HTMLElement
    if (!target.closest('[data-collection-dropdown]')) {
        openDropdown.value = null
        collectionShift.value = 0
    }
}

// Re-measuring on resize hits the same inflated-viewport problem, so just close.
function closeDropdownOnResize() {
    if (openDropdown.value === null) return
    openDropdown.value = null
    collectionShift.value = 0
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    window.addEventListener('resize', closeDropdownOnResize)

    mobileQuery = window.matchMedia('(max-width: 767px)')
    syncIsMobileView(mobileQuery)
    mobileQuery.addEventListener('change', syncIsMobileView)
})

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside)
    window.removeEventListener('resize', closeDropdownOnResize)
    mobileQuery?.removeEventListener('change', syncIsMobileView)
})
</script>

<template>
    <Head :title="t('Favorites')" />

    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Favorites') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Keep your saved tools, posts, and documents in one place.') }}
                </p>
            </div>

            <div class="hidden md:inline-flex rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-white/5">
                <button
                    type="button"
                    :class="viewMode === 'grid' ? 'bg-primary-50 text-primary-700 dark:!bg-primary-500/20 dark:text-primary-500' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    class="rounded-full px-3 py-1 text-sm font-semibold transition"
                    :aria-label="t('Grid view')"
                    :title="t('Grid view')"
                    @click="viewMode = 'grid'"
                >
                    <i class="ti ti-layout-grid"></i>
                </button>
                <button
                    type="button"
                    :class="viewMode === 'list' ? 'bg-primary-50 text-primary-700 dark:!bg-primary-500/20 dark:text-primary-500' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    class="rounded-full px-3 py-1 text-sm font-semibold transition"
                    :aria-label="t('List view')"
                    :title="t('List view')"
                    @click="viewMode = 'list'"
                >
                    <i class="ti ti-menu-4"></i>
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.type"
                    type="button"
                    :class="activeType === tab.type ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/20 dark:text-primary-400' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:text-primary-200'"
                    class="inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-semibold shadow-sm transition"
                    @click="activeType = tab.type"
                >
                    <span>{{ tab.label }}</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-white/10 dark:text-gray-300">{{ tab.count }}</span>
                </button>
            </div>

            <!-- Filters the rows already loaded, like the History page's search. -->
            <div class="relative w-full shrink-0 lg:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="ti ti-search text-gray-400"></i>
                </span>
                <input
                    v-model="searchQuery"
                    type="search"
                    :placeholder="t('Search favorites...')"
                    :aria-label="t('Search favorites')"
                    class="w-full !rounded-full border border-gray-200 bg-white py-2 pl-9 pr-8 text-sm outline-none transition focus:border-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500"
                />
                <span v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <button
                        type="button"
                        :aria-label="t('Clear search')"
                        class="text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-200"
                        @click="searchQuery = ''"
                    >
                        <i class="ti ti-x text-xs"></i>
                    </button>
                </span>
            </div>
        </div>

        <div v-if="visibleItems.length" :class="effectiveViewMode === 'grid' ? 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3' : 'space-y-3'">
            <article
                v-for="item in visibleItems"
                :key="`${item.type}-${item.model_id}`"
                :class="effectiveViewMode === 'grid' ? 'flex flex-col' : 'flex items-center gap-4'"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:border-primary-200 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <Link :href="item.url" :class="effectiveViewMode === 'grid' ? 'block flex-1' : 'flex min-w-0 flex-1 items-center gap-4'">
                    <div
                        v-if="item.image"
                        :class="effectiveViewMode === 'grid' ? 'mb-4 aspect-[16/9] w-full' : 'h-16 w-24 shrink-0'"
                        class="overflow-hidden rounded-lg bg-gray-100 dark:bg-white/5"
                    >
                        <img :src="item.image" :alt="item.title" class="h-full w-full object-cover">
                    </div>
                    <div
                        v-else
                        :class="effectiveViewMode === 'grid' ? 'mb-4 h-10 w-10' : 'h-10 w-10 shrink-0'"
                        class="flex items-center justify-center rounded-xl border"
                        :style="{ background: `${item.color}15`, borderColor: `${item.color}30`, color: item.color }"
                    >
                        <i :class="[item.icon, 'text-xl']"></i>
                    </div>

                    <div class="min-w-0">
                        <h2 class="line-clamp-2 text-base font-bold text-gray-900 dark:text-white">{{ item.title }}</h2>
                        <p v-if="item.description" class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                        <p v-if="item.favorited_at" class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                            {{ t('Saved on :date', { date: formatDate(item.favorited_at) }) }}
                        </p>
                    </div>
                </Link>

                <div :class="effectiveViewMode === 'grid' ? 'mt-4 flex justify-between gap-2 border-t border-gray-100 pt-4 dark:border-white/10' : 'shrink-0 flex items-center gap-2'">
                    <div v-if="item.type === 'ai_templates' && item.slug && props.collections.length > 0" class="relative" data-collection-dropdown>
                        <button
                            type="button"
                            @click="toggleDropdown(item.id)"
                            :aria-label="t('Add to collection')"
                            class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-xs font-semibold text-gray-600 transition hover:border-primary-200 hover:text-primary-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:text-primary-300"
                        >
                            <i class="ti ti-folders text-sm"></i>
                            <span>{{ t('Collect') }}</span>
                            <i class="ti ti-chevron-down text-xs transition" :class="{ 'rotate-180': openDropdown === item.id }"></i>
                        </button>
                        <div v-if="openDropdown === item.id" :ref="setCollectionMenu" :style="collectionMenuStyle" class="absolute bottom-full right-0 z-50 mb-1 min-w-[200px] max-w-[calc(100vw-1.5rem)] rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <p class="px-3 py-1.5 text-xs font-semibold text-gray-400 dark:text-gray-500">{{ t('Add to collection') }}</p>
                            <button
                                v-for="col in props.collections"
                                :key="col.ulid"
                                type="button"
                                :disabled="addingToCollection === col.ulid"
                                @click="addToCollection(col.ulid, item.slug!)"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <i v-if="col.icon" :class="col.icon" class="text-base shrink-0"></i>
                                <i v-else class="ti ti-folders text-base shrink-0 text-gray-400"></i>
                                <span class="flex-1 truncate">{{ col.name }}</span>
                                <span v-if="addingToCollection === col.ulid" class="text-xs text-primary-500">{{ t('...') }}</span>
                                <span v-else class="text-xs text-gray-400">{{ col.tool_count }}</span>
                            </button>
                        </div>
                    </div>
                    <FavoriteButton
                        :model-type="item.type"
                        :model-id="item.model_id"
                        :is-favorited="true"
                        size="sm"
                    />
                </div>
            </article>
        </div>

        <!-- All tab only, and not while searching: both the type tabs and the search filter
             client-side over the page already loaded, so a pager beside either would describe
             a different set than the one on screen. -->
        <Pagination
            v-if="activeType === 'all' && !searchQuery.trim()"
            :links="pagination.links"
            :from="pagination.from"
            :to="pagination.to"
            :total="pagination.total"
            :current-page="pagination.current_page"
            :last-page="pagination.last_page"
        />

        <div v-if="!visibleItems.length" class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-14 text-center shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
            <!-- A search that matched nothing is not an empty account: keep the two apart so
                 "No favorites yet" never appears next to a list the user has just filtered. -->
            <template v-if="searchQuery.trim()">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500">
                    <i class="ti ti-search-off text-2xl"></i>
                </div>
                <h2 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('No matching favorites') }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Nothing on this page matches your search. Try a different term or clear the search.') }}
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-primary-200 hover:text-primary-700 dark:border-white/10 dark:text-gray-200"
                    @click="searchQuery = ''"
                >
                    <i class="ti ti-x"></i>
                    <span>{{ t('Clear search') }}</span>
                </button>
            </template>
            <template v-else>
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300">
                    <i class="ti-heart text-2xl"></i>
                </div>
                <h2 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">{{ t('No favorites yet') }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ t('Save useful tools, articles, and documents so you can return to them quickly.') }}
                </p>
                <Link :href="route('ai.tools.index')" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">
                    <i class="ti-wand"></i>
                    <span>{{ t('Browse AI Tools') }}</span>
                </Link>
            </template>
        </div>
    </section>
</template>
