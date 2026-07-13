<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

interface CookieConsent {
    necessary: boolean
    functional: boolean
    analytics: boolean
    marketing: boolean
}

interface GdprConfig {
    banner_position?: string
    banner_title?: string
    banner_description?: string
    banner_accept_all_text?: string
    banner_customize_text?: string
    banner_necessary_text?: string
    banner_save_text?: string
    banner_bg_color?: string
    banner_text_color?: string
    banner_button_color?: string
    banner_button_text_color?: string
    show_policy_links?: boolean
    privacy_policy_url?: string
    cookie_policy_url?: string
}

const props = withDefaults(defineProps<{
    config?: GdprConfig
}>(), { config: () => ({}) })

const emit = defineEmits<{
    (e: 'accept', consent: CookieConsent): void
    (e: 'accept-all'): void
    (e: 'accept-necessary'): void
}>()

const showCustomize = ref(false)

const consent = ref<CookieConsent>({
    necessary: true,
    functional: true,
    analytics: false,
    marketing: false,
})

const bannerPosition = computed(() => props.config?.banner_position ?? 'bottom')
const bannerTitle = computed(() => props.config?.banner_title ?? t('Cookie Preferences'))
const bannerDescription = computed(() => props.config?.banner_description ?? t('We use cookies to enhance your experience, analyze site usage, and show relevant content.'))
const acceptAllText = computed(() => props.config?.banner_accept_all_text ?? t('Accept All'))
const customizeText = computed(() => props.config?.banner_customize_text ?? t('Customize'))
const necessaryText = computed(() => props.config?.banner_necessary_text ?? t('Necessary Only'))
const saveText = computed(() => props.config?.banner_save_text ?? t('Save Preferences'))
const bgColor = computed(() => props.config?.banner_bg_color ?? '#ffffff')
const textColor = computed(() => props.config?.banner_text_color ?? '#374151')
const buttonColor = computed(() => props.config?.banner_button_color ?? '#4f46e5')
const buttonTextColor = computed(() => props.config?.banner_button_text_color ?? '#ffffff')
const showPolicyLinks = computed(() => props.config?.show_policy_links ?? true)
const privacyUrl = computed(() => props.config?.privacy_policy_url ?? '/privacy-policy')
const cookiePolicyUrl = computed(() => props.config?.cookie_policy_url ?? '/cookie-policy')

const positionClass = computed(() => {
    const map: Record<string, string> = {
        top: 'items-start',
        bottom: 'items-end',
        center: 'items-center',
    }
    return map[bannerPosition.value] ?? 'items-end'
})

function acceptAll() {
    consent.value = { necessary: true, functional: true, analytics: true, marketing: true }
    saveConsent()
    emit('accept-all')
    emit('accept', { ...consent.value })
}

function acceptSelected() {
    saveConsent()
    emit('accept', { ...consent.value })
}

function acceptNecessary() {
    consent.value = { necessary: true, functional: false, analytics: false, marketing: false }
    saveConsent()
    emit('accept-necessary')
    emit('accept', { ...consent.value })
}

function saveConsent() {
    localStorage.setItem('cookie_consent', JSON.stringify(consent.value))
    document.cookie = `cookie_consent=${JSON.stringify(consent.value)}; path=/; max-age=${365 * 24 * 60 * 60}; SameSite=Lax`
}
</script>

<template>
    <div class="fixed inset-0 z-[9999] flex justify-center p-4 bg-black/40 backdrop-blur-sm" :class="positionClass">
        <div class="w-full max-w-lg rounded-2xl shadow-2xl border overflow-hidden" :style="{ backgroundColor: bgColor, color: textColor, borderColor: textColor + '20' }">
            <div class="p-6">
                <div class="flex items-start gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :style="{ backgroundColor: buttonColor + '15' }">
                        <svg class="w-5 h-5" :style="{ color: buttonColor }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold">{{ bannerTitle }}</h3>
                        <p class="mt-1 text-sm opacity-70">{{ bannerDescription }}</p>
                    </div>
                </div>

                <div v-if="showCustomize" class="space-y-3 mb-5">
                    <label class="flex items-start gap-3 p-3 rounded-lg opacity-50 cursor-not-allowed" :style="{ backgroundColor: textColor + '08' }">
                        <input type="checkbox" checked disabled class="mt-0.5 w-4 h-4 rounded border-current opacity-40">
                        <div>
                            <span class="text-sm font-medium">{{ t('Necessary') }}</span>
                            <p class="text-xs mt-0.5 opacity-60">{{ t('Required for the site to function. Includes session management, CSRF protection, and authentication. Always enabled.') }}</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-lg cursor-pointer transition-colors" :style="{ backgroundColor: textColor + '08' }" @mouseenter="($el as HTMLElement).style.backgroundColor = textColor + '14'" @mouseleave="($el as HTMLElement).style.backgroundColor = textColor + '08'">
                        <input v-model="consent.functional" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-current" :style="{ accentColor: buttonColor }">
                        <div>
                            <span class="text-sm font-medium">{{ t('Functional') }}</span>
                            <p class="text-xs mt-0.5 opacity-60">{{ t('Language preferences, theme settings, and display customization.') }}</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-lg cursor-pointer transition-colors" :style="{ backgroundColor: textColor + '08' }" @mouseenter="($el as HTMLElement).style.backgroundColor = textColor + '14'" @mouseleave="($el as HTMLElement).style.backgroundColor = textColor + '08'">
                        <input v-model="consent.analytics" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-current" :style="{ accentColor: buttonColor }">
                        <div>
                            <span class="text-sm font-medium">{{ t('Analytics') }}</span>
                            <p class="text-xs mt-0.5 opacity-60">{{ t('Usage tracking, performance measurement, and visitor statistics. Google Analytics only loads with this consent.') }}</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-lg cursor-pointer transition-colors" :style="{ backgroundColor: textColor + '08' }" @mouseenter="($el as HTMLElement).style.backgroundColor = textColor + '14'" @mouseleave="($el as HTMLElement).style.backgroundColor = textColor + '08'">
                        <input v-model="consent.marketing" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-current" :style="{ accentColor: buttonColor }">
                        <div>
                            <span class="text-sm font-medium">{{ t('Marketing') }}</span>
                            <p class="text-xs mt-0.5 opacity-60">{{ t('Ad tracking, retargeting pixels, and personalized promotional content.') }}</p>
                        </div>
                    </label>
                </div>

                <div class="flex flex-col sm:flex-row gap-2.5">
                    <button @click="acceptAll" class="flex-1 rounded-xl px-5 py-2.5 text-sm font-bold transition-colors hover:opacity-90" :style="{ backgroundColor: buttonColor, color: buttonTextColor }">
                        {{ acceptAllText }}
                    </button>
                    <button v-if="showCustomize" @click="acceptSelected" class="flex-1 rounded-xl border px-5 py-2.5 text-sm font-bold transition-colors hover:opacity-80" :style="{ borderColor: buttonColor, color: buttonColor }">
                        {{ saveText }}
                    </button>
                    <button v-if="!showCustomize" @click="showCustomize = true" class="flex-1 rounded-xl border px-5 py-2.5 text-sm font-bold transition-colors hover:opacity-80" :style="{ borderColor: textColor + '40', color: textColor }">
                        {{ customizeText }}
                    </button>
                    <button v-if="!showCustomize" @click="acceptNecessary" class="rounded-xl border px-5 py-2.5 text-sm font-medium transition-colors hover:opacity-80" :style="{ borderColor: textColor + '20', color: textColor, opacity: 0.7 }">
                        {{ necessaryText }}
                    </button>
                </div>

                <p v-if="showPolicyLinks" class="mt-3 text-center text-xs" :style="{ color: textColor, opacity: 0.5 }">
                    <a :href="privacyUrl" class="underline hover:opacity-80">{{ t('Privacy Policy') }}</a>
                    <span class="mx-1">·</span>
                    <a :href="cookiePolicyUrl" class="underline hover:opacity-80">{{ t('Cookie Policy') }}</a>
                </p>
            </div>
        </div>
    </div>
</template>
