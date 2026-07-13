<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
    defineProps<{
        modelValue?: boolean
        disabled?: boolean
        activeIcon?: string
        inactiveIcon?: string
        label?: string
        ariaLabel?: string
    }>(),
    {
        modelValue: false,
        disabled: false,
        activeIcon: 'ti ti-check',
        inactiveIcon: 'ti ti-x',
    }
)

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
}>()

const toggle = () => {
    if (props.disabled) return
    emit('update:modelValue', !props.modelValue)
}
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <span v-if="label" class="text-sm font-medium text-gray-700 dark:text-gray-300 select-none">{{ label }}</span>
        <button
            type="button"
            role="switch"
            :aria-checked="modelValue"
            :aria-label="ariaLabel || label"
            :disabled="disabled"
            class="app-switch w-11 h-6 shrink-0"
            @click="toggle"
        >
            <span class="app-switch__thumb flex items-center justify-center">
                <transition name="scale" mode="out-in">
                    <i
                        v-if="modelValue"
                        :key="'active'"
                        :class="[activeIcon, 'text-[10px] text-primary-600 font-bold select-none leading-none']"
                    />
                    <i
                        v-else
                        :key="'inactive'"
                        :class="[inactiveIcon, 'text-[10px] text-gray-400 font-bold select-none leading-none']"
                    />
                </transition>
            </span>
        </button>
    </div>
</template>

<style scoped>
.app-switch__thumb {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}
.scale-enter-active,
.scale-leave-active {
    transition: transform 0.1s ease, opacity 0.1s ease;
}
.scale-enter-from,
.scale-leave-to {
    opacity: 0;
    transform: scale(0.6);
}
</style>
