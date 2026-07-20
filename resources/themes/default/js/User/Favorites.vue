<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, onUnmounted } from 'vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import FavoriteButton from '@themes/default/js/Components/FavoriteButton.vue'
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
        links: PaginationLink[]
    }
}>()

const { t } = useTranslate()
const activeType = ref('all')
const viewMode = ref<'grid' | 'list'>('grid')
const openDropdown = ref<number | null>(null)
const addingToCollection = ref<string | null>(null)

const allItems = computed(() => props.groups.flatMap((group) => group.items))

const visibleItems = computed(() => {
    if (activeType.value === 'all') return allItems.value

    return props.groups.find((group) => group.type === activeType.value)?.items ?? []
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

const paginationLabel = (label: string) => {
    if (label.includes('Previous')) return t('Previous')
    if (label.includes('Next')) return t('Next')

    return label.replace('&laquo;', '').replace('&raquo;', '').trim()
}

function toggleDropdown(itemId: number) {
    openDropdown.value = openDropdown.value === itemId ? null : itemId
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
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
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

            <div class="inline-flex rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-white/5">
                <button
                    type="button"
                    :class="viewMode === 'grid' ? 'bg-primary-50 text-primary-700 dark:!bg-primary-500/20 dark:text-primary-500' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    class="rounded-full px-3 py-1 text-sm font-semibold transition"
                    :aria-label="t('Grid view')"
                    :title="t('Grid view')"
                    @click="viewMode = 'grid'"
                >
                    <i class="ti ti-grid-dots"></i>
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

        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="tab in tabs"
                :key="tab.type"
                type="button"
                :class="activeType === tab.type ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/20 dark:text-primary-400' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:text-primary-200'"
                class="inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold shadow-sm transition"
                @click="activeType = tab.type"
            >
                <span>{{ tab.label }}</span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-white/10 dark:text-gray-300">{{ tab.count }}</span>
            </button>
        </div>

        <div v-if="visibleItems.length" :class="viewMode === 'grid' ? 'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3' : 'space-y-3'">
            <article
                v-for="item in visibleItems"
                :key="`${item.type}-${item.model_id}`"
                :class="viewMode === 'grid' ? 'flex flex-col' : 'flex items-center gap-4'"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:border-primary-200 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <Link :href="item.url" :class="viewMode === 'grid' ? 'block flex-1' : 'flex min-w-0 flex-1 items-center gap-4'">
                    <div
                        v-if="item.image"
                        :class="viewMode === 'grid' ? 'mb-4 aspect-[16/9] w-full' : 'h-16 w-24 shrink-0'"
                        class="overflow-hidden rounded-lg bg-gray-100 dark:bg-white/5"
                    >
                        <img :src="item.image" :alt="item.title" class="h-full w-full object-cover">
                    </div>
                    <div
                        v-else
                        :class="viewMode === 'grid' ? 'mb-4 h-10 w-10' : 'h-10 w-10 shrink-0'"
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

                <div :class="viewMode === 'grid' ? 'mt-4 flex justify-between gap-2 border-t border-gray-100 pt-4 dark:border-white/10' : 'shrink-0 flex items-center gap-2'">
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
                        <div v-if="openDropdown === item.id" class="absolute bottom-full right-0 z-50 mb-1 min-w-[200px] rounded-xl border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
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

        <div v-if="pagination.last_page > 1" class="flex flex-wrap items-center justify-center gap-2">
            <Link
                v-for="link in pagination.links"
                :key="link.label"
                :href="link.url || '#'"
                :class="[
                    link.active ? 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/15 dark:text-primary-200' : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300',
                    !link.url ? 'pointer-events-none opacity-50' : '',
                ]"
                class="rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition"
                preserve-scroll
            >
                {{ paginationLabel(link.label) }}
            </Link>
        </div>

        <div v-if="!visibleItems.length" class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-14 text-center shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-surface-900">
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
        </div>
    </section>
</template>
