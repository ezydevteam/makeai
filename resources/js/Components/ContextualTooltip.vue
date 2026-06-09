<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Tooltip from '@/Components/UI/Tooltip.vue'

const props = withDefaults(defineProps<{
    tooltipKey: string
    content: string
    placement?: 'top' | 'bottom' | 'left' | 'right'
    delay?: number
}>(), {
    placement: 'bottom',
    delay: 500,
})

const page = usePage()
const user = computed(() => page.props.auth?.user as any)
const dismissedKeys = computed<string[]>(() => {
    const d = user.value?.dismissed_tooltips
    return Array.isArray(d) ? d : []
})

const isDismissed = computed(() => dismissedKeys.value.includes(props.tooltipKey))
const visible = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

onMounted(() => {
    if (!isDismissed.value) {
        timer = setTimeout(() => { visible.value = true }, props.delay)
    }
})

watch(visible, (val) => {
    if (!val && timer) {
        clearTimeout(timer)
        timer = null
    }
})

function dismiss() {
    visible.value = false
    if (timer) {
        clearTimeout(timer)
        timer = null
    }
    router.post(route('user.dashboard.onboarding.tooltip.dismiss'), {
        tooltip_key: props.tooltipKey,
    }, {
        preserveScroll: true,
        preserveState: true,
    })
}
</script>

<template>
    <Tooltip
        v-if="visible && !isDismissed"
        :content="content"
        :placement="placement"
    >
        <span class="contextual-tooltip-wrapper">
            <slot />
            <button
                v-if="visible"
                @click.stop.prevent="dismiss"
                class="tooltip-dismiss-btn"
                :aria-label="$t ? $t('Dismiss') : 'Dismiss'"
            >
                <i class="ti ti-x text-[10px]"></i>
            </button>
        </span>
    </Tooltip>
    <span v-else><slot /></span>
</template>

<style scoped>
.contextual-tooltip-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
}
.tooltip-dismiss-btn {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    z-index: 10;
    transition: opacity 0.15s;
}
.tooltip-dismiss-btn:hover {
    background: #dc2626;
}
</style>
