<script setup lang="ts">
import { computed, ref } from 'vue'

/* ------------------------------------------------------------------ *
 * A "Get started with" card: copy on the left, thumbnail on the right.
 * The whole card is one button — clicking it sends its prompt to the
 * studio, so there is no secondary click target to miss.
 * ------------------------------------------------------------------ */

const props = withDefaults(
    defineProps<{
        title: string
        // Nullable on purpose. Laravel's ConvertEmptyStringsToNull rewrites blank fields
        // of the admin's settings form to NULL, and withDefaults only substitutes for
        // `undefined` — so a null genuinely can arrive here. The server normalises these
        // now, but the component must not be one bad row away from a white screen.
        description?: string | null
        image?: string | null
    }>(),
    {
        description: '',
        image: '',
    },
)

const emit = defineEmits<{
    (event: 'select'): void
}>()

const failed = ref(false)

/** The URL as a plain string, whatever shape the setting arrived in. */
const imageUrl = computed(() => (props.image ?? '').trim())

/** No URL, or a URL that 404s, must never surface as a broken image. */
const showImage = computed(() => imageUrl.value.length > 0 && !failed.value)
</script>

<template>
    <button
        type="button"
        class="group flex w-full items-center gap-4 rounded-2xl border border-gray-200/90 bg-white p-3 text-left transition duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-[0_10px_30px_-12px_rgba(15,23,42,0.18)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-surface-600 dark:focus-visible:ring-offset-gray-950"
        @click="emit('select')"
    >
        <span class="min-w-0 flex-1 py-1 pl-1">
            <span class="flex items-center gap-1.5">
                <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                    {{ title }}
                </span>
                <i
                    class="ti ti-arrow-up-right shrink-0 text-sm text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-primary-500 dark:text-surface-600"
                ></i>
            </span>

            <span
                v-if="description"
                class="mt-1 line-clamp-2 block text-xs leading-relaxed text-gray-500 dark:text-gray-400"
            >
                {{ description }}
            </span>
        </span>

        <!-- Deliberately short: the thumbnail is a hint, not the subject. A tall
             tile pushed the copy around and made the grid feel like a gallery. -->
        <span
            class="relative flex h-10 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 sm:h-11 sm:w-16 dark:bg-surface-800"
        >
            <img
                v-if="showImage"
                :src="imageUrl"
                :alt="title"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                @error="failed = true"
            />
            <i v-else class="ti ti-photo text-base text-gray-300 dark:text-surface-600" aria-hidden="true"></i>
        </span>
    </button>
</template>
