<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { mediaUrl } from '@/lib/media'
import AssistantTrigger from './AssistantTrigger.vue'
import AssistantHeaderButton from './AssistantHeaderButton.vue'
import AssistantShell from './AssistantShell.vue'
import type { AssistantSettings } from '../../types'

const page = usePage()
const { t } = useTranslate()

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
 | The admin panel shares the SAME `frontendHeaderSettings` prop as the front end — an
 | earlier comment here assumed it did not — so the bottom-bar clearance below was being
 | applied on admin pages, which render no such bar. That floated the launcher 60px above
 | where it belonged. Admin gets its own flat offset instead.
 */
// Keyed off the Inertia component name ("Admin/Dashboard/Index"), NOT page.props.admin —
// that prop is shared on every page whenever an admin is signed in, so using it would
// change the front end too.
const isAdminPage = computed(() => String(page.component ?? '').startsWith('Admin/'))
const ADMIN_BUBBLE_BOTTOM = 30

/*
 | Bubble clearance for the theme's mobile bottom bar.
 |
 | The default theme can render a bottom nav on small screens, and the bubble sits exactly
 | where it does — so it would cover the nav (or be covered by it). The theme already
 | derives that bar's height from the same Inertia prop, so read it rather than guess:
 | 0 when disabled, 48 without menu labels, 60 with.
 */
const mobileBottomBarHeight = computed(() => {
    if (isAdminPage.value) return 0

    const mobileBottom = (page.props.frontendHeaderSettings as { mobile_bottom?: { enabled?: boolean; hide_menu_labels?: boolean } } | undefined)?.mobile_bottom

    if (!isMobile.value || mobileBottom?.enabled !== true) return 0

    return mobileBottom.hide_menu_labels === true ? 48 : 60
})

/** The bubble's own 1.5rem gap, plus any bottom bar it has to clear. */
const bubbleBottom = computed(() => (isAdminPage.value
    ? ADMIN_BUBBLE_BOTTOM
    : 24 + mobileBottomBarHeight.value))

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

// The greeting is teleported to <body> so it can outrank corner overlays (e.g. a
// bottom-popup announcement at z-[95]) that would otherwise cover it — a child of the
// z-50 root can never escape that stacking context. It's positioned by hand to sit just
// above the launcher: bubble bottom + the 3.5rem (56px) bubble + a 12px gap.
const GREETING_LAUNCHER_CLEARANCE = 56 + 12

const greetingStyle = computed(() => ({
    bottom: `${bubbleBottom.value + GREETING_LAUNCHER_CLEARANCE}px`,
    ...(settings.value?.position === 'bottom-left' ? { left: '24px' } : { right: '24px' }),
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

/*
 | FIRST-VISIT GREETING
 |
 | A lighter alternative to auto-open: instead of expanding the whole panel, a small
 | greeting bubble peeks out above the launcher with a notification chime, once per
 | browser session. Clicking it opens the assistant.
 */
const showGreeting = ref(false)
let greetingTimer: number | null = null
let greetingFallbackTimer: number | null = null
let stopWaitingForInteraction: (() => void) | null = null

// Discrete gestures that grant "user activation", so a chime is allowed to play. mousemove
// and scroll deliberately excluded — they don't count as activation for audio in Chrome,
// so greeting on those would still be silent.
const ACTIVATION_EVENTS = ['pointerdown', 'keydown', 'touchend'] as const

const greetingText = computed(() => {
    const s = settings.value
    if (!s) return ''
    // An admin-set greeting wins verbatim; otherwise personalise by first name for a
    // signed-in visitor and fall back to the generic line for guests — same rule the
    // chat panel uses, so the two never disagree.
    if (s.greeting_message) return s.greeting_message
    return s.greeting_name
        ? t('Hi :name! How can I help you today?', { name: s.greeting_name })
        : t('Hi there! How can I help you today?')
})

const avatarUrl = computed(() => (settings.value?.avatar_url ? mediaUrl(settings.value.avatar_url) : ''))

/**
 * A short two-note chime synthesised with the Web Audio API, so no audio asset has to be
 * bundled. Browsers may block audio until the page has seen a user gesture; when that
 * happens the greeting still shows, just silently — the sound is a nicety, not critical.
 */
function playNotificationSound() {
    if (typeof window === 'undefined') return

    try {
        const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
        if (!Ctx) return

        const ctx = new Ctx()

        const chime = () => {
            const start = ctx.currentTime
            const notes: Array<[number, number]> = [[880, 0], [1174.66, 0.12]]

            for (const [freq, offset] of notes) {
                const osc = ctx.createOscillator()
                const gain = ctx.createGain()
                osc.type = 'sine'
                osc.frequency.value = freq
                gain.gain.setValueAtTime(0.0001, start + offset)
                gain.gain.exponentialRampToValueAtTime(0.15, start + offset + 0.02)
                gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.35)
                osc.connect(gain).connect(ctx.destination)
                osc.start(start + offset)
                osc.stop(start + offset + 0.4)
            }

            window.setTimeout(() => ctx.close().catch(() => {}), 1000)
        }

        if (ctx.state === 'suspended') {
            ctx.resume().then(chime).catch(() => ctx.close().catch(() => {}))
        } else {
            chime()
        }
    } catch {
        /* Audio is best-effort; never let it break the greeting. */
    }
}

function openFromGreeting() {
    showGreeting.value = false
    isOpen.value = true
}

function dismissGreeting() {
    showGreeting.value = false
}

function maybeGreet() {
    if (!settings.value?.greeting_on_first_visit) return
    // No launcher to anchor to in header mode, and a redundant nudge once the panel is
    // already open (e.g. auto-open is also on) is just noise.
    if (headerMode.value || isOpen.value) return
    if (typeof sessionStorage === 'undefined') return
    if (sessionStorage.getItem('ai_assistant_greeted')) return

    sessionStorage.setItem('ai_assistant_greeted', '1')
    showGreeting.value = true
    playNotificationSound()
}

/**
 * Hold the greeting until the visitor's first real interaction, so the page has "user
 * activation" and the chime is actually audible instead of being silently blocked. A long
 * fallback still shows it (without sound) for a visitor who never interacts.
 */
function armGreeting() {
    if (!settings.value?.greeting_on_first_visit) return
    if (headerMode.value || isOpen.value) return
    if (typeof sessionStorage === 'undefined') return
    if (sessionStorage.getItem('ai_assistant_greeted')) return

    const onFirstInteraction = () => {
        teardown()
        // A short beat after the gesture reads as a deliberate hello, not a reaction to
        // the click itself. Activation is sticky, so the chime still plays after it.
        greetingTimer = window.setTimeout(maybeGreet, 400)
    }

    const teardown = () => {
        for (const evt of ACTIVATION_EVENTS) window.removeEventListener(evt, onFirstInteraction)
        if (greetingFallbackTimer !== null) {
            window.clearTimeout(greetingFallbackTimer)
            greetingFallbackTimer = null
        }
        stopWaitingForInteraction = null
    }

    for (const evt of ACTIVATION_EVENTS) window.addEventListener(evt, onFirstInteraction, { passive: true })
    stopWaitingForInteraction = teardown

    greetingFallbackTimer = window.setTimeout(() => {
        teardown()
        maybeGreet()
    }, 15000)
}

function maybeAutoOpen() {
    if (!settings.value?.auto_open) return
    if (typeof sessionStorage === 'undefined') return

    if (!sessionStorage.getItem('ai_assistant_auto_opened')) {
        isOpen.value = true
        sessionStorage.setItem('ai_assistant_auto_opened', '1')
    }
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

    maybeAutoOpen()

    // Wait for the visitor's first interaction before greeting, so the notification chime
    // has user activation and can actually be heard.
    armGreeting()
})

onBeforeUnmount(() => {
    mobileQuery?.removeEventListener('change', onMobileChange)
    window.removeEventListener('ai-assistant:open', onExternalOpen)
    window.removeEventListener('ai-assistant:toggle', onExternalToggle)
    window.removeEventListener('ai-assistant:close', close)
    if (greetingTimer !== null) window.clearTimeout(greetingTimer)
    stopWaitingForInteraction?.()
})
</script>

<template>
    <template v-if="settings">
        <!-- The panel itself is the same popover in both modes; only its anchor changes. -->
        <!--
            z-index: the launcher idles at z-50, but the OPEN panel has to clear the theme's
            sticky top stack (z-[60], which wraps the announcement banner) and the mobile menu
            (z-[80]). A child can't escape its parent's stacking context, so the raise has to
            happen here on the root rather than on the panel. z-[90] keeps it under the layers
            that are deliberately higher: bottom-popup announcement (z-[95]), announcement
            popup modal (z-[100]) and the blocking app-modal layer (z-[120]/z-[125]).
        -->
        <div
            class="ai-assistant-root fixed flex flex-col gap-3"
            :class="[anchorClass, isOpen ? 'z-[90]' : 'z-50']"
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

        <!--
          First-visit greeting bubble. Teleported to <body> with z-[99] so it clears corner
          overlays such as a bottom-popup announcement (z-[95]); it stays below full-screen
          modal popups (z-[100]) so those still win. Positioned by hand above the launcher.
        -->
        <Teleport to="body">
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-2 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-2 scale-95"
            >
                <div
                    v-if="showGreeting && !isOpen && !headerMode"
                    class="fixed z-[99] w-[16.5rem] max-w-[calc(100vw-3rem)] cursor-pointer rounded-2xl bg-white p-3.5 pr-8 shadow-2xl ring-1 ring-gray-900/5 dark:bg-surface-800 dark:ring-white/10"
                    :style="greetingStyle"
                    role="button"
                    tabindex="0"
                    @click="openFromGreeting"
                    @keydown.enter="openFromGreeting"
                >
                    <button
                        type="button"
                        class="absolute right-2 top-2 inline-flex h-6 w-6 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-surface-700 dark:hover:text-gray-200"
                        :aria-label="t('Dismiss')"
                        @click.stop="dismissGreeting"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                    </button>

                    <div class="flex items-start gap-2.5">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full text-white"
                            :style="{ backgroundColor: settings.accent_color ?? '#1F75FE' }"
                        >
                            <img v-if="avatarUrl" :src="avatarUrl" :alt="settings.assistant_name ?? ''" class="h-full w-full object-cover" />
                            <i v-else class="ti ti-robot text-base"></i>
                        </span>
                        <div class="min-w-0">
                            <p v-if="settings.assistant_name" class="text-xs font-semibold text-gray-900 dark:text-white">
                                {{ settings.assistant_name }}
                            </p>
                            <p class="mt-0.5 text-sm leading-snug text-gray-600 dark:text-gray-300">{{ greetingText }}</p>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </template>
</template>
