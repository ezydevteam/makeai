<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'

const props = defineProps<{ message?: string | null }>()

const root = ref<HTMLElement | null>(null)

/**
 * Step submits are sent with preserveScroll so the wizard does not jump on every
 * transition. The cost is that a buyer scrolled partway down a long step — Database
 * runs six fields, a test button and the reset card — stays put when the server
 * refuses, and this alert renders above the fold where they never see it: the click
 * reads as having done nothing at all. Pull it into view whenever a message lands.
 */
watch(
    () => props.message,
    async (message) => {
        if (!message) return

        // The alert is v-if'd, so it does not exist yet on the tick the message arrives.
        await nextTick()
        root.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    },
)
</script>

<template>
    <div
        v-if="message"
        ref="root"
        class="mt-5 flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-700"
    >
        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        </svg>
        <span>{{ message }}</span>
    </div>
</template>
