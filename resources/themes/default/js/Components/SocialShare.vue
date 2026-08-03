<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToastr } from '@/Composables/useToastr'
import { useTranslate } from '@/Composables/useTranslate'

type ShareStyle = 'icon' | 'icon-label' | 'icon-count'
type ShareNetwork = 'facebook' | 'x' | 'linkedin' | 'whatsapp' | 'telegram' | 'pinterest' | 'reddit' | 'email' | 'copy'

interface ShareLink {
    key: ShareNetwork
    label: string
    url: string | null
    external: boolean
}

const props = withDefaults(defineProps<{
    url: string
    title?: string
    urls?: Partial<Record<Exclude<ShareNetwork, 'copy'>, string>>
    networks?: ShareNetwork[]
    counts?: Partial<Record<ShareNetwork, number>>
    showCounts?: boolean
    style?: ShareStyle
    layout?: 'horizontal' | 'vertical'
    collapseAfter?: number
}>(), {
    title: '',
    urls: () => ({}),
    networks: () => ['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'pinterest', 'reddit', 'email', 'copy'],
    counts: () => ({}),
    showCounts: false,
    style: 'icon-label',
    layout: 'horizontal',
    // 0 = show every network, which is what the dropdown and any other caller want. Only a
    // caller that sets it gets the More/Less toggle.
    collapseAfter: 0,
})

const { t } = useTranslate()
const toast = useToastr()

const networkLabels: Record<ShareNetwork, string> = {
    facebook: 'Facebook',
    x: 'Twitter X',
    linkedin: 'LinkedIn',
    whatsapp: 'WhatsApp',
    telegram: 'Telegram',
    pinterest: 'Pinterest',
    reddit: 'Reddit',
    email: 'Email',
    copy: 'Copy link',
}

const networkClasses: Record<ShareNetwork, string> = {
    facebook: 'hover:!border-blue-500 hover:!bg-blue-600 hover:!text-white',
    x: 'hover:!border-gray-900 hover:!bg-gray-900 hover:!text-white dark:hover:!border-white dark:hover:!bg-white dark:hover:!text-gray-950',
    linkedin: 'hover:!border-sky-700 hover:!bg-sky-700 hover:!text-white',
    whatsapp: 'hover:!border-emerald-500 hover:!bg-emerald-500 hover:!text-white',
    telegram: 'hover:!border-sky-500 hover:!bg-sky-500 hover:!text-white',
    pinterest: 'hover:!border-red-600 hover:!bg-red-600 hover:!text-white',
    reddit: 'hover:!border-orange-500 hover:!bg-orange-500 hover:!text-white',
    email: 'hover:!border-primary-500 hover:!bg-primary-600 hover:!text-white',
    copy: 'hover:!border-gray-900 hover:!bg-gray-900 hover:!text-white dark:hover:!border-white dark:hover:!bg-white dark:hover:!text-gray-950',
}

const encodedUrl = computed(() => encodeURIComponent(props.url))
const encodedTitle = computed(() => encodeURIComponent(props.title))

const fallbackUrls = computed<Record<Exclude<ShareNetwork, 'copy'>, string>>(() => ({
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`,
    x: `https://twitter.com/intent/tweet?url=${encodedUrl.value}&text=${encodedTitle.value}`,
    linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl.value}`,
    whatsapp: `https://api.whatsapp.com/send?text=${encodedTitle.value}%20${encodedUrl.value}`,
    telegram: `https://t.me/share/url?url=${encodedUrl.value}&text=${encodedTitle.value}`,
    pinterest: `https://www.pinterest.com/pin/create/button/?url=${encodedUrl.value}&description=${encodedTitle.value}`,
    reddit: `https://www.reddit.com/submit?url=${encodedUrl.value}&title=${encodedTitle.value}`,
    email: `mailto:?subject=${encodedTitle.value}&body=${encodedTitle.value}%0A${encodedUrl.value}`,
}))

const shareLinks = computed<ShareLink[]>(() => props.networks
    .filter((network) => network in networkLabels)
    .map((network) => ({
        key: network,
        label: networkLabels[network],
        url: network === 'copy' ? null : (props.urls[network] ?? fallbackUrls.value[network]),
        external: network !== 'email' && network !== 'copy',
    })))

const expanded = ref(false)
const isCollapsible = computed(() => props.collapseAfter > 0 && shareLinks.value.length > props.collapseAfter)
const visibleShareLinks = computed(() => (isCollapsible.value && ! expanded.value)
    ? shareLinks.value.slice(0, props.collapseAfter)
    : shareLinks.value)

const showLabel = computed(() => props.style === 'icon-label')
const showCount = (network: ShareNetwork) => props.style === 'icon-count' && props.showCounts && props.counts[network] !== undefined

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.url)
        toast.success(t('Link copied to clipboard.'))
    } catch {
        toast.error(t('Unable to copy link.'))
    }
}

const shareTitle = (label: string) => t('Share on :network', { network: t(label) })
</script>

<template>
    <div class="flex gap-2" :class="layout === 'vertical' ? 'flex-col' : 'flex-wrap'" :aria-label="t('Social share buttons')">
        <template v-for="link in visibleShareLinks" :key="link.key">
            <button
                v-if="link.key === 'copy'"
                type="button"
                :title="t('Copy link')"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"
                :class="[networkClasses[link.key], { 'w-full': layout === 'vertical' }]"
                @click="copyToClipboard"
            >
                <span class="inline-flex h-5 w-5 items-center justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 7V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
                </span>
                <span v-if="showLabel">{{ t(link.label) }}</span>
                <span v-if="showCount(link.key)" class="text-xs">{{ counts[link.key] }}</span>
            </button>

            <a
                v-else
                :href="link.url ?? '#'"
                :target="link.external ? '_blank' : undefined"
                :rel="link.external ? 'noopener noreferrer' : undefined"
                :title="shareTitle(link.label)"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"
                :class="[networkClasses[link.key], { 'w-full': layout === 'vertical' }]"
            >
                <span class="inline-flex h-5 w-5 items-center justify-center">
                    <svg v-if="link.key === 'facebook'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.2-1.5 1.5-1.5h1.7V4.9c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1V11H7.6v3h2.7v8h3.2Z"/></svg>
                    <svg v-else-if="link.key === 'x'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.2 2.3h3.3l-7.2 8.2 8.5 11.2h-6.7l-5.2-6.8-6 6.8H1.7l7.7-8.8L1.3 2.3h6.9l4.7 6.2 5.3-6.2Zm-1.2 17.5h1.8L7.1 4.1H5.1L17 19.8Z"/></svg>
                    <svg v-else-if="link.key === 'linkedin'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6.9 20.5H3.5V9h3.4v11.5ZM5.2 7.4a2 2 0 1 1 0-4 2 2 0 0 1 0 4Zm15.3 13.1h-3.4v-5.6c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9v5.7H9.8V9h3.3v1.6h.1c.5-.9 1.6-1.9 3.4-1.9 3.6 0 4.2 2.4 4.2 5.5v6.3Z"/></svg>
                    <svg v-else-if="link.key === 'whatsapp'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.5 3.5A11.8 11.8 0 0 0 12.1 0C5.5 0 .2 5.3.2 11.9c0 2.1.5 4.1 1.6 5.9L.1 24l6.3-1.7a11.9 11.9 0 0 0 5.7 1.5c6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.5-8.4Zm-8.4 18.3c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4a9.8 9.8 0 0 1-1.5-5.3c0-5.4 4.4-9.9 9.9-9.9 2.6 0 5.1 1 7 2.9a9.8 9.8 0 0 1 2.9 7c0 5.5-4.5 9.9-10 9.9Zm5.4-7.4c-.3-.1-1.8-.9-2-.9-.3-.1-.5-.2-.7.1-.2.3-.8 1-.9 1.2-.2.2-.4.2-.7.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.7-2c-.2-.3 0-.5.1-.6l.5-.5.3-.5c.1-.2.1-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.4Z"/></svg>
                    <svg v-else-if="link.key === 'telegram'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M21.9 4.2 18.7 19c-.2 1-.8 1.2-1.6.8l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.4-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.6 12.8l-4.7-1.5c-1-.3-1-1 .2-1.4L20.4 2.8c.9-.3 1.7.2 1.5 1.4Z"/></svg>
                    <svg v-else-if="link.key === 'pinterest'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.2 0C5.5 0 2.1 4.8 2.1 8.8c0 2.4.9 4.6 2.9 5.4.3.1.6 0 .7-.4l.3-1.2c.1-.4.1-.5-.2-.9-.6-.7-1-1.6-1-2.8 0-3.6 2.7-6.8 7-6.8 3.8 0 5.9 2.3 5.9 5.4 0 4.1-1.8 7.5-4.5 7.5-1.5 0-2.6-1.2-2.2-2.7.4-1.8 1.3-3.8 1.3-5.1 0-1.2-.6-2.2-2-2.2-1.6 0-2.8 1.6-2.8 3.8 0 1.4.5 2.3.5 2.3l-1.9 8c-.6 2.4-.1 5.3 0 5.6 0 .2.3.2.4.1.2-.2 2.6-3.2 3.4-6.1l1-3.7c.5.9 1.8 1.7 3.2 1.7 4.2 0 7-3.8 7-8.9C21.1 3.9 17.8 0 12.2 0Z"/></svg>
                    <svg v-else-if="link.key === 'reddit'" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 11.8a2.7 2.7 0 0 0-4.6-1.9 13.3 13.3 0 0 0-6.5-2l1.1-5 3.5.8a2.2 2.2 0 1 0 .3-1.4l-4.2-.9a.7.7 0 0 0-.8.5l-1.3 6a13.4 13.4 0 0 0-6.9 2 2.7 2.7 0 1 0-3 4.4c0 .2-.1.5-.1.8 0 4 4.7 7.2 10.5 7.2s10.5-3.2 10.5-7.2c0-.3 0-.6-.1-.8 1-.5 1.6-1.4 1.6-2.5ZM7.5 14a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm8.1 4.3c-1 .9-2.3 1.3-3.6 1.3s-2.7-.4-3.6-1.3a.7.7 0 0 1 1-1c.6.6 1.6.9 2.6.9s1.9-.3 2.6-.9a.7.7 0 0 1 1 1Zm.9-2.8a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/></svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 8 9 6 9-6"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 6h14a2 2 0 0 1 2 2"/></svg>
                </span>
                <span v-if="showLabel">{{ t(link.label) }}</span>
                <span v-if="showCount(link.key)" class="text-xs">{{ counts[link.key] }}</span>
            </a>
        </template>

        <!-- Styled as a share button rather than a plain link so the row keeps one rhythm.
             aria-expanded, because it discloses the rest of the group rather than navigating. -->
        <button
            v-if="isCollapsible"
            type="button"
            :aria-expanded="expanded"
            :title="expanded ? t('Show fewer sharing options') : t('Show more sharing options')"
            class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:border-gray-300 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-300"
            :class="{ 'w-full': layout === 'vertical' }"
            @click="expanded = ! expanded"
        >
            <span class="inline-flex h-5 w-5 items-center justify-center">
                <i :class="['ti', expanded ? 'ti-chevron-up' : 'ti-dots']" class="text-base leading-none"></i>
            </span>
            <span v-if="showLabel">{{ expanded ? t('Less') : t('More') }}</span>
        </button>
    </div>
</template>
