<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'

defineOptions({ inheritAttrs: false })

type TooltipPlacement = 'top' | 'bottom' | 'left' | 'right'

const props = withDefaults(defineProps<{
    content?: string
    placement?: TooltipPlacement
    offset?: number
    disabled?: boolean
    fullWidth?: boolean
}>(), {
    placement: 'top',
    offset: 8,
    disabled: false,
    fullWidth: false,
})

const triggerRef = ref<HTMLElement | null>(null)
const tooltipRef = ref<HTMLElement | null>(null)
const visible = ref(false)
const appliedPlacement = ref<TooltipPlacement>(props.placement)
const tooltipStyle = ref<Record<string, string>>({})
const tooltipId = `tooltip-${Math.random().toString(36).slice(2, 10)}`

const text = computed(() => props.content?.trim() ?? '')
const hasTooltip = computed(() => Boolean(text.value) && !props.disabled)

function clamp(value: number, min: number, max: number) {
    return Math.min(Math.max(value, min), max)
}

function getPlacement(): TooltipPlacement {
    const trigger = triggerRef.value?.getBoundingClientRect()
    const tooltip = tooltipRef.value?.getBoundingClientRect()

    if (!trigger || !tooltip) {
        return props.placement
    }

    const viewportWidth = window.innerWidth
    const viewportHeight = window.innerHeight
    const margin = 8

    const fits = {
        top: trigger.top - tooltip.height - props.offset >= margin,
        bottom: trigger.bottom + tooltip.height + props.offset <= viewportHeight - margin,
        left: trigger.left - tooltip.width - props.offset >= margin,
        right: trigger.right + tooltip.width + props.offset <= viewportWidth - margin,
    }

    if (props.placement === 'top' && !fits.top && fits.bottom) return 'bottom'
    if (props.placement === 'bottom' && !fits.bottom && fits.top) return 'top'
    if (props.placement === 'left' && !fits.left && fits.right) return 'right'
    if (props.placement === 'right' && !fits.right && fits.left) return 'left'

    return props.placement
}

function updatePosition() {
    const trigger = triggerRef.value?.getBoundingClientRect()
    const tooltip = tooltipRef.value?.getBoundingClientRect()

    if (!trigger || !tooltip) {
        return
    }

    const placement = getPlacement()
    const margin = 8

    let top = 0
    let left = 0

    if (placement === 'top') {
        top = trigger.top - tooltip.height - props.offset
        left = trigger.left + (trigger.width / 2) - (tooltip.width / 2)
    } else if (placement === 'bottom') {
        top = trigger.bottom + props.offset
        left = trigger.left + (trigger.width / 2) - (tooltip.width / 2)
    } else if (placement === 'left') {
        top = trigger.top + (trigger.height / 2) - (tooltip.height / 2)
        left = trigger.left - tooltip.width - props.offset
    } else {
        top = trigger.top + (trigger.height / 2) - (tooltip.height / 2)
        left = trigger.right + props.offset
    }

    top = clamp(top, margin, window.innerHeight - tooltip.height - margin)
    left = clamp(left, margin, window.innerWidth - tooltip.width - margin)

    tooltipStyle.value = {
        top: `${Math.round(top)}px`,
        left: `${Math.round(left)}px`,
    }
    appliedPlacement.value = placement
}

function addListeners() {
    window.addEventListener('scroll', updatePosition, true)
    window.addEventListener('resize', updatePosition)
}

function removeListeners() {
    window.removeEventListener('scroll', updatePosition, true)
    window.removeEventListener('resize', updatePosition)
}

async function show() {
    if (!hasTooltip.value) {
        return
    }

    visible.value = true
    await nextTick()
    updatePosition()
    addListeners()
}

function hide() {
    visible.value = false
    removeListeners()
}

onBeforeUnmount(() => {
    removeListeners()
})
</script>

<template>
    <span
        ref="triggerRef"
        v-bind="$attrs"
        :class="['tooltip-trigger', props.fullWidth && 'tooltip-full-width']"
        :style="{ display: props.fullWidth ? 'block' : 'inline-flex' }"
        :aria-describedby="visible ? tooltipId : undefined"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="hide"
        @keydown.esc.stop.prevent="hide"
    >
        <slot />
    </span>

    <Teleport to="body">
        <Transition name="tooltip-fade">
            <div
                v-if="visible && text"
                ref="tooltipRef"
                :id="tooltipId"
                :class="['tooltip-bubble', `tooltip-${appliedPlacement}`]"
                :style="tooltipStyle"
                role="tooltip"
            >
                <span class="tooltip-text">{{ text }}</span>
                <span class="tooltip-arrow" aria-hidden="true"></span>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.tooltip-trigger {
    position: relative;
}

.tooltip-full-width {
    width: 100%;
}

.tooltip-bubble {
    position: fixed;
    z-index: 9999;
    max-width: 16rem;
    padding: 0.35rem 0.6rem;
    border-radius: 0.375rem;
    background: rgba(17, 24, 39, 0.96);
    color: #fff;
    font-size: 0.75rem;
    line-height: 1.25;
    font-weight: 500;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.24);
    pointer-events: none;
}

.tooltip-text {
    position: relative;
    z-index: 1;
}

.tooltip-arrow {
    position: absolute;
    width: 8px;
    height: 8px;
    background: rgba(17, 24, 39, 0.96);
    transform: rotate(45deg);
}

.tooltip-top .tooltip-arrow {
    left: 50%;
    bottom: -4px;
    transform: translateX(-50%) rotate(45deg);
}

.tooltip-bottom .tooltip-arrow {
    left: 50%;
    top: -4px;
    transform: translateX(-50%) rotate(45deg);
}

.tooltip-left .tooltip-arrow {
    right: -4px;
    top: 50%;
    transform: translateY(-50%) rotate(45deg);
}

.tooltip-right .tooltip-arrow {
    left: -4px;
    top: 50%;
    transform: translateY(-50%) rotate(45deg);
}

.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
    opacity: 0;
    transform: translateY(2px) scale(0.98);
}
</style>
