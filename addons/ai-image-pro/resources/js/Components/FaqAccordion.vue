<script setup lang="ts">
import { ref } from 'vue'

/* ------------------------------------------------------------------ *
 * Accessible FAQ accordion — one panel open at a time.
 *
 * Each header is a real <button> wired to its panel with
 * aria-expanded / aria-controls, and the panel is a labelled region,
 * so a screen reader announces the state exactly as a sighted user
 * sees it. The open/close animation is a grid-rows trick: it works on
 * content of any height without measuring it in JS.
 * ------------------------------------------------------------------ */

interface FaqItem {
    question: string
    answer: string
}

const props = defineProps<{
    items: FaqItem[]
    /** Unique per instance — keeps the aria ids stable if the page ever shows two accordions. */
    idPrefix?: string
}>()

const openIndex = ref<number | null>(null)

const prefix = props.idPrefix ?? 'aip-faq'

function toggle(index: number): void {
    openIndex.value = openIndex.value === index ? null : index
}

function isOpen(index: number): boolean {
    return openIndex.value === index
}
</script>

<template>
    <div class="divide-y divide-gray-100 border-y border-gray-100 dark:divide-surface-800 dark:border-surface-800">
        <div v-for="(item, index) in items" :key="`${prefix}-${index}`">
            <h3>
                <button
                    :id="`${prefix}-trigger-${index}`"
                    type="button"
                    class="flex w-full items-center justify-between gap-6 py-5 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950"
                    :aria-expanded="isOpen(index)"
                    :aria-controls="`${prefix}-panel-${index}`"
                    @click="toggle(index)"
                >
                    <span
                        class="text-sm font-medium text-gray-900 transition group-hover:text-primary-600 sm:text-base dark:text-white"
                    >
                        {{ item.question }}
                    </span>

                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-gray-400 transition duration-300 dark:text-gray-500"
                        :class="isOpen(index) ? 'rotate-45 bg-gray-100 text-gray-900 dark:bg-surface-800 dark:text-white' : ''"
                        aria-hidden="true"
                    >
                        <i class="ti ti-plus text-base"></i>
                    </span>
                </button>
            </h3>

            <div
                :id="`${prefix}-panel-${index}`"
                role="region"
                :aria-labelledby="`${prefix}-trigger-${index}`"
                class="grid transition-all duration-300 ease-out"
                :class="isOpen(index) ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
            >
                <div class="overflow-hidden">
                    <!-- Answers are authored by the site admin in the core FAQ manager and may contain rich text. -->
                    <div
                        class="max-w-3xl pb-6 pr-10 text-sm leading-relaxed text-gray-600 [&_a]:text-primary-600 [&_a]:underline dark:text-gray-400 dark:[&_a]:text-primary-400"
                        v-html="item.answer"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>
