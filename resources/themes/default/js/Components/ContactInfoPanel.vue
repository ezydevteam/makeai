<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import SocialFollow from '@themes/default/js/Components/SocialFollow.vue'

const { t } = useTranslate()
const page = usePage()

interface Channel {
    icon: string
    label: string
    value: string
    href: string
    external: boolean
}

// Resolved server-side (PageController::contactChannels) from real settings and routes,
// so an install with no support address shows fewer cards instead of dead links.
const props = withDefaults(defineProps<{ channels?: Channel[] | null }>(), { channels: null })

const channels = computed<Channel[]>(() => props.channels ?? [])

const hasSocial = computed(() => {
    const follow = page.props.socialFollow as { profiles?: unknown[] } | undefined

    return (follow?.profiles?.length ?? 0) > 0
})
</script>

<template>
    <div v-if="channels.length || hasSocial" class="space-y-4">
        <a
            v-for="channel in channels"
            :key="channel.href"
            :href="channel.href"
            :target="channel.external ? '_blank' : undefined"
            :rel="channel.external ? 'noopener noreferrer' : undefined"
            class="group flex items-start gap-4 rounded-2xl border border-gray-100 bg-white/60 p-4 backdrop-blur-md transition-all hover:-translate-y-0.5 hover:border-primary-500/25 hover:bg-white dark:border-surface-800/80 dark:bg-surface-900/40 dark:hover:border-surface-800 dark:hover:bg-surface-900/60"
        >
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 transition-colors group-hover:bg-primary-100 dark:bg-primary-950/40 dark:text-primary-400 dark:group-hover:bg-primary-950/60">
                <i :class="channel.icon" class="text-xl"></i>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                    {{ channel.label }}
                </span>
                <span class="mt-1 block truncate text-sm font-semibold text-gray-900 dark:text-white">
                    {{ channel.value }}
                </span>
            </span>
            <i
                class="ti mt-1 shrink-0 text-base text-gray-300 transition-all group-hover:translate-x-0.5 group-hover:text-primary-500 dark:text-gray-600"
                :class="channel.external ? 'ti-external-link' : 'ti-arrow-right'"
            ></i>
        </a>

        <div v-if="hasSocial" class="rounded-2xl border border-gray-100 bg-white/60 p-4 backdrop-blur-md dark:border-surface-800/80 dark:bg-surface-900/40">
            <p class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {{ t('Follow us') }}
            </p>
            <!-- Sized to match the channel-card icons above so the column reads as one set.
                 Profiles come from Settings › Social counters; the block hides when none are active. -->
            <SocialFollow
                display-mode="icons"
                icon-item-class="h-11 w-11 rounded-xl border shadow-sm transition-all hover:-translate-y-0.5"
                icon-inner-class="text-xl leading-none"
            />
        </div>
    </div>
</template>
