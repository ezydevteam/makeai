<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { CSSProperties } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import FlagIcon from '@/Components/Utility/FlagIcon.vue'
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
    display?: 'default' | 'icon' | 'icon_label' | 'bottom'
    placement?: 'down' | 'up'
    ui?: {
        buttonClass?: string
        buttonStyle?: CSSProperties
        iconStyle?: CSSProperties
    }
}>(), {
    display: 'default',
    placement: 'down',
    ui: () => ({}),
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
const shouldRender = computed(() => Boolean(currentLanguage.value?.code || currentLanguage.value?.name))
const isBottomDisplay = computed(() => props.display === 'bottom')
const opensUpward = computed(() => isBottomDisplay.value || props.placement === 'up')
const showFlag = computed(() => props.display === 'default' || props.display === 'bottom')
const showName = computed(() => props.display === 'default' || props.display === 'icon_label' || props.display === 'bottom')
const showChevron = computed(() => props.display === 'default' || props.display === 'icon_label')

const buttonClass = computed(() => isBottomDisplay.value
    ? 'flex min-w-0 flex-1 flex-col items-center justify-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold transition-colors disabled:cursor-wait disabled:opacity-60'
    : props.display === 'icon'
        ? 'inline-flex h-9 w-9 min-w-9 items-center justify-center gap-0 rounded-lg p-0 text-sm font-semibold transition-all duration-200 disabled:cursor-wait disabled:opacity-60'
        : 'inline-flex min-w-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition disabled:cursor-wait disabled:opacity-60')
const dropdownClass = computed(() => {
    const base = 'absolute z-50 max-h-72 min-w-48 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-800 dark:bg-gray-900'
    if (isBottomDisplay.value) {
        // Mobile bottom navigation: centre the menu above the trigger.
        return `${base} bottom-full start-1/2 mb-2 -translate-x-1/2 rtl:translate-x-1/2`
    }
    if (opensUpward.value) {
        // Footer / upward placement: align to the trigger's edge so it never overflows the viewport.
        return `${base} bottom-full end-0 mb-2`
    }
    return `${base} top-full end-0 mt-2`
})

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
    <div v-if="shouldRender" :class="isBottomDisplay ? 'relative flex min-w-0 flex-1' : 'relative'" @click.stop>
        <button
            type="button"
            :class="['language-switcher-button', buttonClass, props.ui.buttonClass]"
            :style="props.ui.buttonStyle"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :disabled="switching"
            @click="open = !open"
        >
            <FlagIcon v-if="showFlag" :flag="currentLanguage.flag" :language-code="currentLanguage.code" :language-name="currentLanguage.name" :size="isBottomDisplay ? 'md' : 'sm'" />
            <i v-else class="ti ti-language text-[18px] leading-none" :style="props.ui.iconStyle" aria-hidden="true" />
            <span v-if="showName" :class="isBottomDisplay ? 'max-w-full truncate text-[11px] leading-none' : 'max-w-28 truncate'">{{ currentLanguage.name }}</span>
            <svg v-if="!isBottomDisplay && showChevron" :class="{ 'rotate-180': open }" :style="props.ui.iconStyle" class="h-4 w-4 shrink-0 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </button>

        <div v-if="open" :class="dropdownClass" role="listbox">
            <button
                v-for="language in languages"
                :key="language.code"
                type="button"
                class="language-switcher-option flex w-full items-center justify-between gap-3 px-3 py-2 text-sm transition disabled:cursor-default"
                :class="{ 'language-switcher-option-active font-semibold': language.code === locale.code }"
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
                <i v-if="language.code === locale.code" class="ti ti-check shrink-0 text-base text-primary-600 dark:text-primary-300" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</template>

<style scoped>
.language-switcher-button {
    color: var(--header-control-text-color, var(--header-action-text-color, inherit));
    border-color: var(--header-soft-icon-border, transparent);
    background: var(--header-soft-icon-bg, transparent);
}
.language-switcher-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-control-hover-color, var(--header-action-hover-color, inherit)));
    background: var(--header-soft-icon-hover-bg, var(--header-control-hover-bg, var(--header-action-hover-bg, var(--header-menu-hover-bg, color-mix(in srgb, var(--header-action-hover-color, var(--color-primary-500)) 10%, transparent)))));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.language-switcher-button.header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg, var(--color-gray-100)) !important;
    border-color: transparent !important;
}
.dark .language-switcher-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-control-hover-color, var(--header-action-hover-color, inherit)));
    background: var(--header-soft-icon-hover-bg-dark, var(--header-control-hover-bg-dark, var(--header-action-hover-bg-dark, var(--header-menu-hover-bg-dark, color-mix(in srgb, var(--header-action-hover-color, var(--color-primary-500)) 14%, transparent)))));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.dark .language-switcher-button.header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08)) !important;
    border-color: transparent !important;
}
.language-switcher-option {
    color: var(--color-gray-700);
}
.dark .language-switcher-option {
    color: var(--color-gray-300);
}
.language-switcher-option:hover,
.language-switcher-option-active {
    color: var(--color-primary-700);
    background: var(--color-primary-50);
}
.dark .language-switcher-option:hover,
.dark .language-switcher-option-active {
    color: var(--color-primary-300);
    background: rgb(16 185 129 / 0.14);
}
</style>
