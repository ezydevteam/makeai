<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

/**
 * One admin-authored example ("Get started with" card).
 *
 * Kept structurally identical to the Studio's own declaration so the emitted
 * payload type-checks without importing a type across single-file components.
 * The list is 100% admin-editable (`landing_examples`) — nothing here is fixed.
 */
interface StudioExample {
    title: string
    description: string
    image: string
    prompt: string
}

const props = withDefaults(
    defineProps<{
        examples: StudioExample[]
        heading?: string
        /** Cards are inert while a generation is in flight. */
        busy?: boolean
    }>(),
    { heading: '', busy: false },
)

const emit = defineEmits<{
    (e: 'select', example: StudioExample): void
}>()

const title = computed(() => props.heading || t('Get started with'))

function pick(example: StudioExample): void {
    if (props.busy) return
    if (!example.prompt?.trim()) return

    emit('select', example)
}
</script>

<template>
    <section v-if="examples.length > 0" :aria-label="title">
        <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
            {{ title }}
        </h2>

        <div class="grid gap-3 sm:grid-cols-2">
            <button
                v-for="(example, index) in examples"
                :key="`${index}-${example.title}`"
                type="button"
                class="group flex items-stretch gap-3 overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 text-left transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-[0_12px_30px_-18px_rgba(15,23,42,0.35)] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-700/60"
                :disabled="busy"
                @click="pick(example)"
            >
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="flex items-center gap-1">
                        <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                            {{ example.title }}
                        </span>
                        <i
                            class="ti ti-chevron-right shrink-0 text-sm text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-500"
                        ></i>
                    </span>

                    <span class="mt-1.5 line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ example.description }}
                    </span>
                </span>

                <span
                    v-if="example.image"
                    class="relative hidden h-16 w-24 shrink-0 overflow-hidden rounded-xl border border-gray-100 bg-gray-50 sm:block dark:border-surface-800 dark:bg-surface-950"
                >
                    <img
                        :src="example.image"
                        :alt="example.title"
                        loading="lazy"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    />
                </span>
            </button>
        </div>
    </section>
</template>
