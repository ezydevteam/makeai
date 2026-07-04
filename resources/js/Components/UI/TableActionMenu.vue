<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'

const props = withDefaults(defineProps<{
    disabled?: boolean
    offset?: number
}>(), {
    disabled: false,
    offset: 8,
})

const isOpen = ref(false)
const triggerRef = ref<HTMLElement | null>(null)
const menuRef = ref<HTMLElement | null>(null)
const menuStyle = ref({ top: '0px', left: '0px', transform: 'translateX(-100%)' })

const toggle = async () => {
    if (props.disabled) return

    if (isOpen.value) {
        close()
        return
    }

    isOpen.value = true

    await nextTick()

    updatePosition()
}

const close = () => {
    isOpen.value = false
}

const updatePosition = () => {
    const trigger = triggerRef.value
    const menu = menuRef.value
    if (!trigger || !menu) return

    const triggerRect = trigger.getBoundingClientRect()
    const menuRect = menu.getBoundingClientRect()

    const spaceBelow = window.innerHeight - triggerRect.bottom
    const spaceAbove = triggerRect.top
    const openUpward = spaceBelow < menuRect.height && spaceAbove > spaceBelow

    const top = openUpward
        ? triggerRect.top - menuRect.height - props.offset
        : triggerRect.bottom + props.offset

    // Align right side of menu with right side of trigger, unless it overflows left edge of viewport
    let left = triggerRect.right
    if (left - menuRect.width < 16) {
        left = triggerRect.left + menuRect.width
    }

    menuStyle.value = {
        top: `${top}px`,
        left: `${left}px`,
        transform: 'translateX(-100%)',
    }
}

const handleDocumentClick = (event: MouseEvent) => {
    const target = event.target
    if (!(target instanceof HTMLElement)) return

    // If clicked inside trigger or menu, don't close immediately (toggle handles click on trigger)
    if (triggerRef.value?.contains(target) || menuRef.value?.contains(target)) {
        return
    }

    close()
}

const handleViewportChange = () => {
    if (isOpen.value) {
        close()
    }
}

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        close()
    }
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick)
    window.addEventListener('keydown', handleKeydown)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick)
    window.removeEventListener('keydown', handleKeydown)
    window.removeEventListener('resize', handleViewportChange)
    window.removeEventListener('scroll', handleViewportChange, true)
})
</script>

<template>
    <div ref="triggerRef" class="relative inline-flex justify-end">
        <slot name="trigger" :toggle="toggle" :isOpen="isOpen">
            <button
                type="button"
                :disabled="disabled"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-300 dark:hover:bg-surface-800 dark:hover:text-white disabled:opacity-50"
                @click.stop="toggle"
            >
                <slot name="trigger-icon">
                    <i class="ti ti-dots-vertical text-base"></i>
                </slot>
            </button>
        </slot>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="menuRef"
                class="fixed z-[80] min-w-40 max-w-48 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-surface-700 dark:bg-surface-900"
                :style="menuStyle"
            >
                <slot :close="close"></slot>
            </div>
        </Teleport>
    </div>
</template>
