<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

const show = ref(false)
const consent = reactive({
    necessary: true,
    functional: true,
    analytics: false,
    marketing: false,
})

onMounted(() => {
    const saved = localStorage.getItem('cookie_consent')
    if (saved) {
        try {
            const parsed = JSON.parse(saved)
            if (parsed.functional !== undefined) consent.functional = parsed.functional
            if (parsed.analytics !== undefined) consent.analytics = parsed.analytics
            if (parsed.marketing !== undefined) consent.marketing = parsed.marketing
        } catch {}
    }
})

function open() { show.value = true }
function close() { show.value = false }

defineExpose({ open, close })

function save() {
    const data = { ...consent }
    localStorage.setItem('cookie_consent', JSON.stringify(data))
    document.cookie = `cookie_consent=${JSON.stringify(data)}; path=/; max-age=${365 * 24 * 60 * 60}; SameSite=Lax`
    show.value = false
}

function acceptAll() {
    consent.functional = true
    consent.analytics = true
    consent.marketing = true
    save()
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="close">
                <div class="w-full max-w-md bg-white dark:bg-surface-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-surface-700 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 dark:border-surface-700 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ t('Cookie Preferences') }}</h3>
                        <button @click="close" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-5 space-y-3">
                        <label class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-surface-800 opacity-60 cursor-not-allowed">
                            <input type="checkbox" checked disabled class="mt-0.5 w-4 h-4 rounded border-gray-300 text-gray-400">
                            <div>
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('Necessary') }}</span>
                                <p class="text-xs text-gray-400 mt-0.5">{{ t('Required for the site to function. Always enabled.') }}</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-surface-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                            <input v-model="consent.functional" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Functional') }}</span>
                                <p class="text-xs text-gray-500 mt-0.5">{{ t('Language preferences, theme settings, and display customization.') }}</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-surface-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                            <input v-model="consent.analytics" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Analytics') }}</span>
                                <p class="text-xs text-gray-500 mt-0.5">{{ t('Usage tracking and performance measurement. Analytics scripts only load with this consent.') }}</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-surface-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-surface-700 transition-colors">
                            <input v-model="consent.marketing" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Marketing') }}</span>
                                <p class="text-xs text-gray-500 mt-0.5">{{ t('Ad tracking, retargeting pixels, and personalized content.') }}</p>
                            </div>
                        </label>
                    </div>

                    <div class="p-5 border-t border-gray-100 dark:border-surface-700 flex flex-col gap-2.5">
                        <button @click="acceptAll" class="w-full rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-700 transition-colors">{{ t('Accept All') }}</button>
                        <button @click="save" class="w-full rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 transition-colors">{{ t('Save Preferences') }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
