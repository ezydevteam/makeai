<script setup lang="ts">
import { computed } from 'vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import type { AssistantChannels } from '../../types'

const props = withDefaults(defineProps<{
    channels: AssistantChannels
    /** 'start' inside a card (Home); 'center' as a standalone strip (Message panel). */
    align?: 'start' | 'center'
}>(), {
    align: 'center',
})

// Only channels the admin filled in are shared, so this list is exactly what to render.
const items = computed(() => {
    const order: Array<{ key: keyof AssistantChannels; icon: string; label: string }> = [
        { key: 'whatsapp', icon: 'ti ti-brand-whatsapp', label: 'WhatsApp' },
        { key: 'telegram', icon: 'ti ti-brand-telegram', label: 'Telegram' },
        { key: 'facebook', icon: 'ti ti-brand-facebook', label: 'Facebook' },
        { key: 'instagram', icon: 'ti ti-brand-instagram', label: 'Instagram' },
        { key: 'website', icon: 'ti ti-world', label: 'Website' },
    ]
    return order
        .filter((o) => Boolean(props.channels[o.key]))
        .map((o) => ({ ...o, url: props.channels[o.key] as string }))
})
</script>

<template>
    <div
        v-if="items.length"
        class="flex shrink-0 items-center gap-3"
        :class="align === 'start' ? 'justify-start' : 'justify-center'"
    >
        <!-- Bare icons: no padding or hover background, so the row adds no height of its own
             and the card it sits in matches the other quick actions exactly. Hover just
             tints to the accent colour. -->
        <Tooltip v-for="item in items" :key="item.key" :content="item.label">
            <a
                :href="item.url"
                target="_blank"
                rel="noopener noreferrer"
                :aria-label="item.label"
                class="ai-social-link flex items-center text-gray-400 transition-colors dark:text-gray-500"
            >
                <i :class="[item.icon, 'text-[17px] leading-none']"></i>
            </a>
        </Tooltip>
    </div>
</template>

<style scoped>
/*
 * Hover colour lives here rather than in `hover:text-[var(--ai-accent,#1F75FE)]`.
 *
 * That utility DOES compile — but Tailwind emits the `dark:` variant AFTER the `hover:`
 * one, and both are single-class specificity. So in dark mode `dark:text-gray-500` simply
 * wins and the icons never take the accent colour on hover. (Writing `dark:hover:…` as well
 * would fix it too, but every consumer would have to remember that.)
 *
 * A scoped rule carries the extra [data-v-*] attribute, so it outranks both and works in
 * light and dark alike.
 */
.ai-social-link:hover,
.ai-social-link:focus-visible {
    color: var(--ai-accent, #1F75FE);
}
</style>
