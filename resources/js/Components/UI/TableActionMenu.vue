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

    /* Measure the actual control, not the wrapper. `triggerRef` is on a wrapper div, and
       where that wrapper is a child of a `flex` row (the addons page puts it in
       `flex flex-wrap`) the default align-items:stretch makes it as tall as the whole row.
       Positioning off the wrapper's bottom edge then dropped the menu far below the
       three-dots icon. */
    const triggerEl = trigger.querySelector('button, a, [role="button"]') ?? trigger
    const triggerRect = triggerEl.getBoundingClientRect()
    const menuRect = menu.getBoundingClientRect()

    const viewportWidth = document.documentElement.clientWidth
    const viewportHeight = window.innerHeight
    const MARGIN = 8

    const spaceBelow = viewportHeight - triggerRect.bottom
    const spaceAbove = triggerRect.top
    const openUpward = spaceBelow < menuRect.height && spaceAbove > spaceBelow

    let top = openUpward
        ? triggerRect.top - menuRect.height - props.offset
        : triggerRect.bottom + props.offset

    /* Clamp to the viewport. Flipping upward only needs MORE room above than below — it
       does not require enough room, so on a short screen (a phone in landscape) the menu
       was placed at a negative top and cut off. Same on the way down when neither side
       fits. A menu taller than the screen keeps its top edge visible and scrolls. */
    if (top + menuRect.height > viewportHeight - MARGIN) {
        top = viewportHeight - menuRect.height - MARGIN
    }
    if (top < MARGIN) {
        top = MARGIN
    }

    // `left` is the menu's RIGHT edge — the panel is shifted by translateX(-100%).
    // Right-align to the trigger, flip to left-aligned if that would overflow the left
    // edge, then clamp so neither flip can push it off the opposite side.
    let left = triggerRect.right
    if (left - menuRect.width < MARGIN) {
        left = triggerRect.left + menuRect.width
    }
    if (left > viewportWidth - MARGIN) {
        left = viewportWidth - MARGIN
    }
    if (left - menuRect.width < MARGIN) {
        left = Math.min(menuRect.width + MARGIN, viewportWidth - MARGIN)
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
    <!-- self-start: as a flex child this wrapper would otherwise stretch to the row height. -->
    <div ref="triggerRef" class="relative inline-flex self-start justify-end">
        <slot name="trigger" :toggle="toggle" :isOpen="isOpen">
            <button
                type="button"
                :disabled="disabled"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white disabled:opacity-50"
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
                class="table-action-menu-panel fixed z-[80] min-w-40 max-w-48 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-800"
                :style="menuStyle"
            >
                <slot :close="close"></slot>
            </div>
        </Teleport>
    </div>
</template>
