<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import FlagIcon from '@/Components/FlagIcon.vue'
import { useTranslate } from '@/Composables/useTranslate'

interface LocaleProp {
    code: string
    name: string
    flag?: string | null
    is_rtl?: boolean | number | string | null
}

interface LanguageOption {
    code: string
    name: string
    flag?: string | null
    is_rtl?: boolean | number | string | null
}

const props = withDefaults(defineProps<{
    variant?: 'default' | 'bottom'
    showFlag?: boolean
    showName?: boolean
}>(), {
    variant: 'default',
    showFlag: true,
    showName: true,
})

const page = usePage()
const { t } = useTranslate()
const open = ref(false)
const switching = ref(false)
const languages = computed(() => (page.props.languages as LanguageOption[]) ?? [])
const locale = computed(() => page.props.locale as LocaleProp)
const currentLanguage = computed(() => languages.value.find((language) => language.code === locale.value?.code) ?? {
    code: locale.value?.code ?? 'en',
    name: locale.value?.name ?? 'English',
    flag: locale.value?.flag,
})
const buttonClass = computed(() => props.variant === 'bottom'
    ? 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-gray-500 transition-colors hover:bg-primary-50 hover:text-primary-600 disabled:cursor-wait disabled:opacity-60 dark:text-gray-300 dark:hover:bg-primary-900/20 dark:hover:text-primary-300'
    : 'inline-flex min-w-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 disabled:cursor-wait disabled:opacity-60 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white')
const dropdownClass = computed(() => props.variant === 'bottom'
    ? 'absolute bottom-full start-1/2 z-50 mb-2 max-h-72 w-56 max-w-[calc(100vw-2rem)] -translate-x-1/2 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-800 dark:bg-gray-900 rtl:translate-x-1/2'
    : 'absolute end-0 top-full z-50 mt-2 max-h-72 w-56 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-800 dark:bg-gray-900')

const isRtlValue = (value?: boolean | number | string | null) => value === true || value === 1 || value === '1'

const switchLanguage = (code: string) => {
    if (switching.value || code === locale.value?.code) {
        open.value = false
        return
    }

    const targetLanguage = languages.value.find((language) => language.code === code)
    const directionChanged = isRtlValue(locale.value?.is_rtl) !== isRtlValue(targetLanguage?.is_rtl)

    switching.value = true
    open.value = false
    router.post(route('locale.switch'), { language: code }, {
        preserveScroll: !directionChanged,
        ...(directionChanged ? {} : { only: ['translations', 'locale', 'languages'] }),
        onSuccess: () => {
            if (directionChanged) {
                window.location.reload()
            }
        },
        onFinish: () => {
            if (!directionChanged) {
                switching.value = false
            }
        },
    })
}

const close = () => {
    open.value = false
}

const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        close()
    }
}

onMounted(() => {
    document.addEventListener('click', close)
    document.addEventListener('keydown', closeOnEscape)
})
onUnmounted(() => {
    document.removeEventListener('click', close)
    document.removeEventListener('keydown', closeOnEscape)
})
</script>

<template>
    <div v-if="languages.length > 1" :class="props.variant === 'bottom' ? 'relative flex min-w-0 flex-1' : 'relative'" @click.stop>
        <button
            type="button"
            :class="buttonClass"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :disabled="switching"
            @click="open = !open"
        >
            <FlagIcon v-if="props.showFlag" :flag="currentLanguage.flag" :language-code="currentLanguage.code" :language-name="currentLanguage.name" :size="props.variant === 'bottom' ? 'md' : 'sm'" />
            <span v-if="props.showName" :class="props.variant === 'bottom' ? 'max-w-full truncate text-[11px] leading-none' : 'max-w-28 truncate'">{{ currentLanguage.name }}</span>
            <svg v-if="props.variant !== 'bottom'" :class="{ 'rotate-180': open }" class="h-4 w-4 shrink-0 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </button>

        <div v-if="open" :class="dropdownClass" role="listbox">
            <button
                v-for="language in languages"
                :key="language.code"
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 disabled:cursor-default dark:text-gray-300 dark:hover:bg-primary-900/20"
                :class="{ 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/20 dark:text-primary-300': language.code === locale.code }"
                :aria-selected="language.code === locale.code"
                role="option"
                :disabled="switching"
                @click="switchLanguage(language.code)"
            >
                <span class="inline-flex min-w-0 items-center gap-2">
                    <FlagIcon :flag="language.flag" :language-code="language.code" :language-name="language.name" size="sm" />
                    <span class="truncate">{{ language.name }}</span>
                    <span v-if="language.is_rtl" class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ t('RTL') }}</span>
                </span>
                <span v-if="language.code === locale.code" class="shrink-0 text-xs text-primary-600">{{ t('Active') }}</span>
            </button>
        </div>
    </div>
</template>
