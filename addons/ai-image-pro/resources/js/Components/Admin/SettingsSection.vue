<script setup lang="ts">
import { ref } from 'vue'
import AppSwitch from '@/Components/UI/AppSwitch.vue'
import { useTranslate } from '@/Composables/useTranslate'

/**
 * A landing-page settings card with a header that carries, inline with the title:
 *  - a collapse chevron (local UI state), so a long form can be tidied away, and
 *  - an optional enable/disable switch (`toggleable`), two-way bound, that turns the
 *    section on or off on the public page.
 *
 * When a section is toggled off the body dims and a "Hidden" badge appears, but the
 * fields stay editable — the operator can prepare a section before switching it on.
 */
const props = withDefaults(defineProps<{
    title: string
    icon?: string
    description?: string
    /** Enable-switch state. Only rendered when `toggleable` is true. */
    modelValue?: boolean
    toggleable?: boolean
    defaultOpen?: boolean
}>(), {
    icon: 'ti ti-adjustments',
    description: '',
    modelValue: true,
    toggleable: false,
    defaultOpen: true,
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
}>()

const { t } = useTranslate()
const open = ref(props.defaultOpen)
</script>

<template>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-gray-900">
        <!-- Header: title + description on the left, switch + collapse chevron on the right. -->
        <div class="flex items-start justify-between gap-3 p-5" :class="open ? 'pb-3' : ''">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-start gap-2 text-left"
                :aria-expanded="open"
                @click="open = !open"
            >
                <i :class="icon" class="mt-0.5 shrink-0 text-violet-500"></i>
                <span class="min-w-0">
                    <span class="flex flex-wrap items-center gap-2 text-base font-bold text-gray-900 dark:text-white">
                        {{ title }}
                        <span
                            v-if="toggleable && !modelValue"
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-surface-800 dark:text-gray-400"
                        >
                            {{ t('Hidden') }}
                        </span>
                    </span>
                    <span v-if="description" class="mt-1 block text-sm font-normal text-gray-500 dark:text-gray-400">
                        {{ description }}
                    </span>
                </span>
            </button>

            <div class="flex shrink-0 items-center gap-2">
                <AppSwitch
                    v-if="toggleable"
                    :model-value="modelValue"
                    :aria-label="t('Enable this section')"
                    @update:model-value="emit('update:modelValue', $event)"
                />
                <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 dark:border-surface-700 dark:text-gray-400 dark:hover:bg-surface-800"
                    :aria-label="open ? t('Collapse section') : t('Expand section')"
                    :aria-expanded="open"
                    @click="open = !open"
                >
                    <i class="ti" :class="open ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                </button>
            </div>
        </div>

        <!-- Body -->
        <div
            v-show="open"
            class="px-5 pb-5"
            :class="toggleable && !modelValue ? 'opacity-60' : ''"
        >
            <slot></slot>
        </div>
    </div>
</template>
