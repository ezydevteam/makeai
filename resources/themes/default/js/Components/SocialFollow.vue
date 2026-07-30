<script setup lang="ts">
import { computed } from 'vue'
import type { CSSProperties } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import Tooltip from '@/Components/UI/Tooltip.vue'
import type { SocialFollowPayload, SocialFollowProfile } from '@/types'

type DisplayMode = 'icons' | 'counts' | 'cards'

const props = withDefaults(defineProps<{
    profiles?: SocialFollowProfile[]
    counts?: Record<string, number>
    displayMode?: DisplayMode
    iconItemClass?: string
    iconItemStyle?: CSSProperties
    iconInnerClass?: string
    iconInnerStyle?: CSSProperties
    iconUsePlatformSurface?: boolean
    iconUsePlatformColor?: boolean
}>(), {
    profiles: undefined,
    counts: undefined,
    displayMode: undefined,
    iconItemClass: '',
    iconItemStyle: () => ({}),
    iconInnerClass: '',
    iconInnerStyle: () => ({}),
    iconUsePlatformSurface: true,
    iconUsePlatformColor: true,
})

const page = usePage()
const { t } = useTranslate()

const sharedFollow = computed(() => (page.props.socialFollow as SocialFollowPayload | undefined) ?? {
    display_mode: 'counts',
    profiles: [],
})

const displayMode = computed<DisplayMode>(() => {
    const mode = props.displayMode ?? sharedFollow.value.display_mode

    return ['icons', 'counts', 'cards'].includes(mode) ? mode as DisplayMode : 'counts'
})

const profiles = computed<SocialFollowProfile[]>(() => {
    if (props.profiles?.length) {
        return props.profiles
    }

    if (props.counts) {
        return Object.entries(props.counts).map(([platform, count]) => ({
            platform,
            label: platform,
            unit: t('Followers'),
            url: '#',
            count,
        }))
    }

    return sharedFollow.value.profiles
})

const hasProfiles = computed(() => profiles.value.length > 0)

const numberFormatter = computed(() => new Intl.NumberFormat(page.props.locale?.code ?? 'en', {
    notation: 'compact',
    maximumFractionDigits: 1,
}))

const platformClasses: Record<string, string> = {
    facebook: 'text-blue-600 hover:text-white',
    x: 'text-gray-900 hover:text-white dark:text-gray-100 dark:hover:text-gray-900',
    instagram: 'text-pink-600 hover:text-white',
    linkedin: 'text-sky-700 hover:text-white',
    youtube: 'text-red-600 hover:text-white',
    tiktok: 'text-gray-900 hover:text-white dark:text-gray-100 dark:hover:text-gray-900',
    github: 'text-gray-800 hover:text-white dark:text-gray-100 dark:hover:text-gray-900',
    discord: 'text-indigo-600 hover:text-white',
}

// Dark-brand platforms (x/tiktok/github) need a dark base + light hover in dark
// mode; otherwise their gray-900/800 hover recedes into the dark surface and
// reads as "no hover state". Colored platforms stay vivid enough as-is.
const platformSurfaceClasses: Record<string, string> = {
    facebook: 'bg-blue-50 border-blue-100 hover:bg-blue-600',
    x: 'bg-gray-50 border-gray-100 hover:bg-gray-900 dark:bg-surface-800 dark:border-surface-700 dark:hover:bg-white',
    instagram: 'bg-pink-50 border-pink-100 hover:bg-pink-600',
    linkedin: 'bg-sky-50 border-sky-100 hover:bg-sky-700',
    youtube: 'bg-red-50 border-red-100 hover:bg-red-600',
    tiktok: 'bg-gray-50 border-gray-100 hover:bg-gray-900 dark:bg-surface-800 dark:border-surface-700 dark:hover:bg-white',
    github: 'bg-gray-50 border-gray-100 hover:bg-gray-800 dark:bg-surface-800 dark:border-surface-700 dark:hover:bg-white',
    discord: 'bg-indigo-50 border-indigo-100 hover:bg-indigo-600',
}

const iconClassMap: Record<string, string> = {
    facebook: 'ti ti-brand-facebook',
    x: 'ti ti-brand-x',
    twitter: 'ti ti-brand-x',
    instagram: 'ti ti-brand-instagram',
    linkedin: 'ti ti-brand-linkedin',
    youtube: 'ti ti-brand-youtube',
    tiktok: 'ti ti-brand-tiktok',
    github: 'ti ti-brand-github',
    discord: 'ti ti-brand-discord',
}

const iconClassFor = (platform: string) => iconClassMap[platform] ?? 'ti ti-world'
const classesFor = (platform: string) => platformClasses[platform] ?? platformClasses.facebook
const surfaceClassesFor = (platform: string) => platformSurfaceClasses[platform] ?? platformSurfaceClasses.facebook
const formatCount = (count: number) => numberFormatter.value.format(count)
const iconItemBaseClass = computed(() => props.iconItemClass
    ? 'inline-flex items-center justify-center'
    : 'inline-flex h-8 w-8 items-center justify-center rounded-full border transition-all shadow-sm hover:-translate-y-0.5')
</script>

<template>
    <div v-if="hasProfiles" :class="[displayMode === 'icons' ? 'flex flex-wrap shrink-0 items-center gap-2.5' : '']">
        <template v-if="displayMode === 'icons'">
            <Tooltip
                v-for="profile in profiles"
                :key="profile.platform"
                :content="profile.label"
                placement="top"
            >
                <a
                    :href="profile.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :aria-label="t('Follow :platform', { platform: profile.label })"
                    :class="['social-follow-icon-button', iconItemBaseClass, props.iconUsePlatformColor ? classesFor(profile.platform) : '', props.iconUsePlatformSurface ? surfaceClassesFor(profile.platform) : '', props.iconItemClass]"
                    :style="props.iconItemStyle"
                >
                    <i :class="[iconClassFor(profile.platform), props.iconInnerClass || 'text-base leading-none']" :style="props.iconInnerStyle" aria-hidden="true" />
                </a>
            </Tooltip>
        </template>

        <div v-else class="grid gap-3 w-full" :class="displayMode === 'cards' ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'">
            <a
                v-for="profile in profiles"
                :key="profile.platform"
                :href="profile.url"
                target="_blank"
                rel="noopener noreferrer"
                class="group flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-3 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <span
                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border transition-all"
                    :class="classesFor(profile.platform)"
                >
                    <i :class="[iconClassFor(profile.platform), 'text-xl leading-none']" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span class="block text-base font-bold leading-none text-gray-900 dark:text-white">
                        {{ formatCount(profile.count) }}
                    </span>
                    <span class="mt-1 block truncate text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ displayMode === 'cards' ? t(':platform :unit', { platform: profile.label, unit: profile.unit }) : t(profile.unit) }}
                    </span>
                </span>
            </a>
        </div>
    </div>
</template>

<style scoped>
.social-follow-icon-button.header-soft-icon-button {
    color: var(--header-soft-icon-color, inherit);
    background: var(--header-soft-icon-bg, transparent);
    border-color: var(--header-soft-icon-border, transparent);
}
.social-follow-icon-button.header-soft-icon-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-soft-icon-color, inherit));
    background: var(--header-soft-icon-hover-bg, var(--color-primary-50));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.social-follow-icon-button.header-soft-icon-button.header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg, var(--color-gray-100)) !important;
    border-color: transparent !important;
}
.dark .social-follow-icon-button.header-soft-icon-button:hover {
    color: var(--header-soft-icon-hover-color, var(--header-soft-icon-color, inherit));
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08));
    border-color: var(--header-soft-icon-hover-border, var(--header-soft-icon-border, transparent));
}
.dark .social-follow-icon-button.header-soft-icon-button.header-soft-icon-button--icon-only:hover {
    background: var(--header-soft-icon-hover-bg-dark, rgb(255 255 255 / 0.08)) !important;
    border-color: transparent !important;
}
</style>
