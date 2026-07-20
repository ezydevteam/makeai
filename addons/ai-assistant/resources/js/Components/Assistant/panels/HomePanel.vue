<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AssistantLoader from '../AssistantLoader.vue'
import AssistantSocialRow from '../AssistantSocialRow.vue'
import { fetchHelpArticles } from '../../../Composables/useAssistantPanels'
import { relativeTime } from '../../../Composables/useAssistantFormat'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings, ConversationSummary, HelpArticle, PanelId } from '../../../types'

const props = defineProps<{
    settings: AssistantSettings
    /** Owned by the shell — Home only shows the most recent few. */
    conversations: ConversationSummary[]
    loadingConversations: boolean
}>()

const emit = defineEmits<{
    (e: 'go', panel: PanelId): void
    (e: 'open-article', slug: string): void
    (e: 'open-conversation', sessionId: string): void
    (e: 'show-history'): void
    (e: 'new-chat'): void
}>()

const { t } = useTranslate()

const hasChannels = computed(() => Object.keys(props.settings.channels ?? {}).length > 0)

// Personalised for a signed-in visitor ("Hi Jane!"), generic for guests.
const greeting = computed(() =>
    props.settings.greeting_name
        ? t('Hi :name!', { name: props.settings.greeting_name })
        : t('Hi there!'),
)

// Home is a launcher, not the history screen — three is enough to be useful without
// turning the landing view into a list. The full set lives behind "See all".
const recent = computed(() => props.conversations.slice(0, 3))

const featured = ref<HelpArticle[]>([])
const loadingFeatured = ref(false)

onMounted(async () => {
    if (!props.settings.panels.help) return
    loadingFeatured.value = true
    const result = await fetchHelpArticles(props.settings.endpoints.help_articles)
    featured.value = result.articles.slice(0, 3)
    loadingFeatured.value = false
})
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
        <!-- Greeting. The hero artwork behind it is rendered by the shell (AssistantHero) so
             it can also run behind the transparent header — hence no background of its own
             here. shrink-0 keeps this block at its natural height instead of being squeezed
             by the scrolling column, which is what clipped the greeting before. -->
        <div class="shrink-0 px-4 pb-6 pt-2">
            <div class="flex flex-col items-center text-center">
                <div class="mb-3 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <img
                        v-if="settings.avatar_url"
                        :src="settings.avatar_url"
                        :alt="settings.assistant_name ?? ''"
                        class="h-full w-full object-cover"
                    />
                    <i v-else class="ti ti-robot text-2xl text-gray-400"></i>
                </div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ greeting }} 👋
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ settings.greeting_message || t('How can we help you today?') }}
                </p>
            </div>
        </div>

        <div class="shrink-0 px-4 pb-5">
            <!-- Quick actions: new chat, then browse help. -->
            <div class="space-y-2">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl border border-gray-100 px-3 py-3 text-left transition-colors hover:border-transparent hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                    @click="emit('new-chat')"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg text-white" style="background: var(--ai-accent, #1F75FE);">
                        <i class="ti ti-message-chatbot text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('Start a new chat') }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ t('Ask the assistant anything') }}</span>
                    </span>
                    <i class="ti ti-chevron-right text-gray-400"></i>
                </button>

                <button
                    v-if="settings.panels.help"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl border border-gray-100 px-3 py-3 text-left transition-colors hover:border-transparent hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                    @click="emit('go', 'help')"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        <i class="ti ti-help-circle text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ t('Browse help') }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ t('Search articles and guides') }}</span>
                    </span>
                    <i class="ti ti-chevron-right text-gray-400"></i>
                </button>
            </div>

            <!-- Social channels. Same card anatomy as the quick actions above — icon tile,
                 then title over a second line — so all three rows are exactly the same
                 height. The social icons take the place of the subtitle. -->
            <div
                v-if="hasChannels"
                class="mt-2 flex items-center gap-3 rounded-xl border border-gray-100 px-3 py-3 dark:border-gray-800"
            >
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <i class="ti ti-headset text-lg"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ t('Reach us on') }}</div>
                    <AssistantSocialRow :channels="settings.channels" align="start" class="mt-1" />
                </div>
            </div>

            <!-- Recent conversations -->
            <div v-if="loadingConversations || recent.length" class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        {{ t('Recent conversations') }}
                    </p>
                    <button
                        v-if="conversations.length > recent.length"
                        type="button"
                        class="text-xs font-medium text-[var(--ai-accent,#1F75FE)] hover:underline"
                        @click="emit('show-history')"
                    >
                        {{ t('See all') }}
                    </button>
                </div>

                <div v-if="loadingConversations" class="py-3">
                    <AssistantLoader variant="inline" class="mx-auto block" />
                </div>

                <ul v-else class="space-y-1">
                    <li v-for="item in recent" :key="item.session_id">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                            @click="emit('open-conversation', item.session_id)"
                        >
                            <i class="ti ti-message-2 shrink-0 text-gray-400"></i>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm text-gray-700 dark:text-gray-300">
                                    {{ item.title }}
                                </span>
                                <span class="block text-xs text-gray-400">
                                    {{ item.last_message_at ? relativeTime(new Date(item.last_message_at).getTime()) : '' }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Popular articles -->
            <div v-if="settings.panels.help && (loadingFeatured || featured.length)" class="mt-6">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Popular articles') }}</p>

                <div v-if="loadingFeatured" class="py-3">
                    <AssistantLoader variant="inline" class="mx-auto block" />
                </div>

                <ul v-else class="space-y-1">
                    <li v-for="item in featured" :key="item.slug">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-[var(--ai-accent,#1F75FE)] dark:hover:text-[var(--ai-accent,#1F75FE)] dark:text-gray-300 dark:hover:bg-gray-800"
                            @click="emit('open-article', item.slug)"
                        >
                            <i class="ti ti-file-text text-gray-400"></i>
                            <span class="truncate">{{ item.title }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
