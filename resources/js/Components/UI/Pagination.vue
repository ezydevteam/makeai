<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    links: PaginationLink[]
    from?: number | null
    to?: number | null
    total?: number | null
    currentPage?: number | null
    lastPage?: number | null
}>()

const { t } = useTranslate()

const isPrev = (label: string) => label.includes('Previous') || label.includes('&laquo;')
const isNext = (label: string) => label.includes('Next') || label.includes('&raquo;')
const isNumeric = (label: string) => /^\d+$/.test(label.replace(/<[^>]+>/g, '').trim())

const processedLinks = computed(() =>
    props.links.map((link) => {
        const cleanLabel = link.label.replace(/<[^>]+>/g, '').trim()

        return {
            ...link,
            cleanLabel,
            isPrev: isPrev(link.label),
            isNext: isNext(link.label),
            isNumeric: isNumeric(link.label),
            pageNumber: isNumeric(link.label) ? Number(cleanLabel) : null,
        }
    }),
)

const prevLink = computed(() => processedLinks.value.find((link) => link.isPrev) ?? null)
const nextLink = computed(() => processedLinks.value.find((link) => link.isNext) ?? null)
const numericLinks = computed(() => processedLinks.value.filter((link) => link.isNumeric && link.pageNumber !== null))

const activePage = computed(() => {
    if (props.currentPage) {
        return props.currentPage
    }

    return numericLinks.value.find((link) => link.active)?.pageNumber ?? 1
})

const finalPage = computed(() => {
    if (props.lastPage) {
        return props.lastPage
    }

    return numericLinks.value.at(-1)?.pageNumber ?? 1
})

const visibleLinks = computed(() => {
    const visible: typeof numericLinks.value = []
    const used = new Set<number>()

    const addPage = (page: number) => {
        const match = numericLinks.value.find((link) => link.pageNumber === page)
        if (!match || used.has(page)) {
            return
        }

        used.add(page)
        visible.push(match)
    }

    if (finalPage.value <= 7) {
        for (let page = 1; page <= finalPage.value; page += 1) {
            addPage(page)
        }

        return visible.sort((a, b) => (a.pageNumber ?? 0) - (b.pageNumber ?? 0))
    }

    addPage(1)

    const middleWindowSize = Math.min(5, Math.max(finalPage.value - 2, 0))
    const maxStartPage = Math.max(finalPage.value - middleWindowSize, 2)
    const startPage = Math.min(Math.max(activePage.value - 2, 2), maxStartPage)
    const endPage = Math.min(startPage + middleWindowSize - 1, finalPage.value - 1)

    for (let page = startPage; page <= endPage; page += 1) {
        addPage(page)
    }

    addPage(finalPage.value)

    return visible.sort((a, b) => (a.pageNumber ?? 0) - (b.pageNumber ?? 0))
})

const paginationItems = computed(() => {
    const items: Array<
        | { type: 'page'; pageNumber: number; url: string | null; active: boolean }
        | { type: 'ellipsis'; key: string }
    > = []

    visibleLinks.value.forEach((link, index) => {
        const pageNumber = link.pageNumber ?? 0
        const previous = visibleLinks.value[index - 1]?.pageNumber ?? null

        if (previous !== null && pageNumber - previous > 1) {
            items.push({ type: 'ellipsis', key: `ellipsis-${previous}-${pageNumber}` })
        }

        items.push({
            type: 'page',
            pageNumber,
            url: link.url,
            active: link.active,
        })
    })

    return items
})

const summaryText = computed(() => {
    if (!props.total || !props.from || !props.to) {
        return ''
    }

    return t('Showing :start to :end of :count', {
        start: String(props.from),
        end: String(props.to),
        count: String(props.total),
    }).replace(/(\d)([A-Za-z])/g, '$1 $2')
})
</script>

<template>
    <div
        v-if="links.length > 3"
        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
    >
        <p v-if="summaryText" class="text-sm text-gray-500 dark:text-gray-400">
            {{ summaryText }}
        </p>
        <div v-else class="hidden md:block"></div>

        <div class="flex flex-wrap items-center justify-start gap-2 md:justify-end">
            <component
                :is="prevLink?.url ? Link : 'div'"
                v-if="prevLink"
                :href="prevLink.url ?? undefined"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border text-sm transition-all"
                :class="prevLink.url
                    ? 'border-gray-200 bg-white text-gray-500 shadow-sm hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
                    : 'border-gray-100 bg-gray-50 text-gray-300 dark:border-surface-800 dark:bg-surface-900/60 dark:text-gray-600'"
            >
                <i class="ti ti-chevron-left text-base"></i>
            </component>

            <template v-for="item in paginationItems" :key="item.type === 'page' ? item.pageNumber : item.key">
                <div
                    v-if="item.type === 'ellipsis'"
                    class="inline-flex h-9 min-w-[36px] items-center justify-center px-2 text-sm font-semibold text-gray-400 dark:text-gray-500"
                >
                    ...
                </div>
                <component
                    v-else
                    :is="item.url && !item.active ? Link : 'div'"
                    :href="item.url ?? undefined"
                    class="inline-flex h-9 min-w-[36px] items-center justify-center rounded-full px-4 text-sm font-semibold transition-all"
                    :class="item.active
                        ? 'border border-primary-500 bg-primary-500 text-white shadow-lg shadow-primary-500/20'
                        : 'border border-gray-200 bg-white text-gray-600 shadow-sm hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'"
                >
                    {{ item.pageNumber }}
                </component>
            </template>

            <component
                :is="nextLink?.url ? Link : 'div'"
                v-if="nextLink"
                :href="nextLink.url ?? undefined"
                class="inline-flex h-9 w-9 items-center justify-center rounded-full border text-sm transition-all"
                :class="nextLink.url
                    ? 'border-gray-200 bg-white text-gray-500 shadow-sm hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
                    : 'border-gray-100 bg-gray-50 text-gray-300 dark:border-surface-800 dark:bg-surface-900/60 dark:text-gray-600'"
            >
                <i class="ti ti-chevron-right text-base"></i>
            </component>
        </div>
    </div>
</template>
