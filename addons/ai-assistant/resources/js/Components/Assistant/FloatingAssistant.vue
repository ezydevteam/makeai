<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AssistantTrigger from './AssistantTrigger.vue'
import AssistantHeaderButton from './AssistantHeaderButton.vue'
import AssistantShell from './AssistantShell.vue'
import type { AssistantSettings } from '../../types'

const page = usePage()

// Null whenever the assistant is not available to this visitor (see AddonServiceProvider).
// Nothing renders in that case — not even the bubble.
const settings = computed(() => (page.props.aiAssistant as AssistantSettings | null) ?? null)

const isOpen = ref(false)

const sessionId = ref('')
if (typeof sessionStorage !== 'undefined') {
    const existing = sessionStorage.getItem('ai_assistant_session_id')
    if (existing) {
        sessionId.value = existing
    } else {
        sessionId.value = 'ai_' + Math.random().toString(36).slice(2) + Date.now().toString(36)
        sessionStorage.setItem('ai_assistant_session_id', sessionId.value)
    }
}

/*
 | HEADER-BUTTON MODE
 |
 | The button belongs in the site header — markup the addon doesn't own and that differs
 | per theme. Rather than patching every theme, the layout offers a slot element and we
 | <Teleport> into it. A theme that provides no slot simply doesn't get the button, so we
 | fall back to the floating bubble instead of silently losing the assistant entirely.
 */
const HEADER_SLOT = '#ai-assistant-header-slot'
const MOBILE_QUERY = '(max-width: 767px)'

const headerSlotReady = ref(false)
const isMobile = ref(false)

const wantsHeaderButton = computed(() => settings.value?.position === 'header-button')

/*
 | Header mode needs BOTH a slot to teleport into AND room for it.
 |
 | On phones the header collapses to a burger and a logo — there's no space for another
 | labelled button, and a full-height rail beside a 375px viewport is the whole screen
 | anyway. Below 768px we always fall back to the floating bubble.
 */
const headerMode = computed(() => wantsHeaderButton.value && headerSlotReady.value && !isMobile.value)

/*
 | Bubble clearance for the theme's mobile bottom bar.
 |
 | The default theme can render a bottom nav on small screens, and the bubble sits exactly
 | where it does — so it would cover the nav (or be covered by it). The theme already
 | derives that bar's height from the same Inertia prop, so read it rather than guess:
 | 0 when disabled, 48 without menu labels, 60 with. Admin pages don't ship the prop, so
 | this is simply 0 there.
 */
const mobileBottomBarHeight = computed(() => {
    const mobileBottom = (page.props.frontendHeaderSettings as { mobile_bottom?: { enabled?: boolean; hide_menu_labels?: boolean } } | undefined)?.mobile_bottom

    if (!isMobile.value || mobileBottom?.enabled !== true) return 0

    return mobileBottom.hide_menu_labels === true ? 48 : 60
})

/** The bubble's own 1.5rem gap, plus any bottom bar it has to clear. */
const bubbleBottom = computed(() => 24 + mobileBottomBarHeight.value)

/*
 | In header mode the panel is a full-viewport-height rail pinned to the right edge.
 |
 | It deliberately does NOT offset itself below the header. Measuring the header's height
 | was unreliable in practice — sticky/transparent headers, top bars and scroll states all
 | move it, so the panel drifted or left a gap. Running the rail from the very top is both
 | simpler and steadier; the panel's own header sits above the page's, which is exactly how
 | a side drawer is expected to behave.
 */
const anchorClass = computed(() => {
    if (headerMode.value) {
        return 'inset-y-0 right-0 items-end'
    }

    // `header-button` has no left/right of its own, so its mobile fallback is the right —
    // only an explicit bottom-left stays on the left.
    return settings.value?.position === 'bottom-left'
        ? 'left-6 items-start'
        : 'right-6 items-end'
})

const accentStyle = computed(() => ({ '--ai-accent': settings.value?.accent_color ?? '#1F75FE' }))

const rootStyle = computed(() => ({
    ...accentStyle.value,
    // Bottom is inline (not a `bottom-6` class) so it can clear the theme's mobile bar.
    ...(headerMode.value ? {} : { bottom: `${bubbleBottom.value}px` }),
}))

function toggle() {
    isOpen.value = !isOpen.value
}

function close() {
    isOpen.value = false
}

/**
 * Public entry point so ANY header button — in a theme we don't ship, in Blade, in plain
 * HTML — can open the assistant without importing anything:
 *     window.dispatchEvent(new CustomEvent('ai-assistant:open'))
 */
function onExternalOpen() {
    isOpen.value = true
}

function onExternalToggle() {
    toggle()
}

let mobileQuery: MediaQueryList | null = null

function onMobileChange(event: MediaQueryListEvent | MediaQueryList) {
    isMobile.value = event.matches
}

onMounted(() => {
    headerSlotReady.value = typeof document !== 'undefined' && document.querySelector(HEADER_SLOT) !== null

    if (typeof window !== 'undefined' && window.matchMedia) {
        mobileQuery = window.matchMedia(MOBILE_QUERY)
        isMobile.value = mobileQuery.matches
        mobileQuery.addEventListener('change', onMobileChange)
    }

    window.addEventListener('ai-assistant:open', onExternalOpen)
    window.addEventListener('ai-assistant:toggle', onExternalToggle)
    window.addEventListener('ai-assistant:close', close)

    if (!settings.value?.auto_open) return
    if (typeof sessionStorage === 'undefined') return

    if (!sessionStorage.getItem('ai_assistant_auto_opened')) {
        isOpen.value = true
        sessionStorage.setItem('ai_assistant_auto_opened', '1')
    }
})

onBeforeUnmount(() => {
    mobileQuery?.removeEventListener('change', onMobileChange)
    window.removeEventListener('ai-assistant:open', onExternalOpen)
    window.removeEventListener('ai-assistant:toggle', onExternalToggle)
    window.removeEventListener('ai-assistant:close', close)
})
</script>

<template>
    <template v-if="settings">
        <!-- The panel itself is the same popover in both modes; only its anchor changes. -->
        <div
            class="ai-assistant-root fixed z-50 flex flex-col gap-3"
            :class="anchorClass"
            :style="rootStyle"
        >
            <AssistantShell
                v-if="isOpen"
                :settings="settings"
                :session-id="sessionId"
                :full-height="headerMode"
                @close="close"
            />

            <!-- Floating bubble — hidden in header mode, where the header button replaces it. -->
            <AssistantTrigger
                v-if="!headerMode"
                :is-open="isOpen"
                :settings="settings"
                :unread-count="0"
                :style="accentStyle"
                @click="toggle"
            />
        </div>

        <Teleport v-if="headerMode" :to="HEADER_SLOT">
            <AssistantHeaderButton
                :settings="settings"
                :is-open="isOpen"
                :style="accentStyle"
                @click="toggle"
            />
        </Teleport>
    </template>
</template>
