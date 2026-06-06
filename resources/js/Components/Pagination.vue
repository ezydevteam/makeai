<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    links: Array<{
        url: string | null
        label: string
        active: boolean
    }>
}>()

const isPrev = (label: string) => label.includes('Previous') || label.includes('&laquo;')
const isNext = (label: string) => label.includes('Next') || label.includes('&raquo;')

const processedLinks = computed(() =>
    props.links.map((link) => ({
        ...link,
        isPrev: isPrev(link.label),
        isNext: isNext(link.label),
    })),
)
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-2">
        <template v-for="(link, key) in processedLinks" :key="key">
            <div
                v-if="link.url === null"
                class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gray-50 text-gray-300"
            >
                <svg v-if="link.isPrev" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                <svg v-else-if="link.isNext" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span v-else v-html="link.label" />
            </div>

            <Link
                v-else
                :href="link.url"
                class="inline-flex h-9 items-center justify-center rounded-xl text-xs font-black uppercase tracking-widest transition-all"
                :class="{
                    'bg-primary-400 text-white shadow-lg shadow-primary-400/20 pointer-events-none cursor-default': link.active,
                    'bg-white text-gray-500 hover:bg-gray-50 border border-gray-100': !link.active,
                    'w-9 px-0': link.isPrev || link.isNext,
                    'px-4 min-w-[36px]': !link.isPrev && !link.isNext,
                }"
            >
                <svg v-if="link.isPrev" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                <svg v-else-if="link.isNext" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span v-else v-html="link.label" />
            </Link>
        </template>
    </div>
</template>
