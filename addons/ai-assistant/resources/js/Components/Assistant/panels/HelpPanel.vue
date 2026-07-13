<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import HelpArticle from './HelpArticle.vue'
import AssistantLoader from '../AssistantLoader.vue'
import { fetchHelpArticles, searchHelpArticles } from '../../../Composables/useAssistantPanels'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings, HelpArticle as HelpArticleType } from '../../../types'

const props = defineProps<{
    settings: AssistantSettings
    initialSlug?: string | null
}>()

const emit = defineEmits<{
    (e: 'leave-message'): void
}>()

const { t } = useTranslate()

const query = ref('')
const articles = ref<HelpArticleType[]>([])
const loading = ref(true)
const searching = ref(false)
const canLeaveMessage = ref(false)
// Deep-link: when Home opens a specific article, start in the reader.
const openSlug = ref<string | null>(props.initialSlug ?? null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

async function loadList() {
    loading.value = true
    const result = await fetchHelpArticles(props.settings.endpoints.help_articles)
    articles.value = result.articles
    canLeaveMessage.value = result.leave_message
    loading.value = false
}

watch(query, (value) => {
    if (searchTimer) clearTimeout(searchTimer)

    const trimmed = value.trim()
    if (trimmed.length < 2) {
        searching.value = false
        void loadList()
        return
    }

    searching.value = true
    searchTimer = setTimeout(async () => {
        articles.value = await searchHelpArticles(props.settings.endpoints.help_search, trimmed)
        searching.value = false
    }, 300)
})

onMounted(loadList)
</script>

<template>
    <!-- Article reader -->
    <HelpArticle
        v-if="openSlug"
        :settings="settings"
        :slug="openSlug"
        @open="openSlug = $event"
        @back="openSlug = null"
    />

    <!-- Article browser -->
    <div v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <!-- shrink-0: the search header is a fixed chrome row — only the list below it
             scrolls. Without this it compresses as the list grows. -->
        <div class="shrink-0 px-4 pt-3">
            <button
                v-if="canLeaveMessage"
                type="button"
                class="mb-3 flex w-full items-center justify-center gap-1.5 text-sm text-gray-600 hover:text-[var(--ai-accent,#1F75FE)] dark:hover:text-[var(--ai-accent,#1F75FE)] dark:text-gray-300"
                @click="emit('leave-message')"
            >
                <i class="ti ti-message-2"></i>
                {{ t('Need help? Leave a message') }}
            </button>

            <div class="relative">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input
                    v-model="query"
                    type="text"
                    :placeholder="t('Search for articles')"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-9 pr-9 text-sm text-gray-900 placeholder-gray-400 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[var(--ai-accent,#1F75FE)] dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                />
                <!-- Search-in-progress spinner, inside the field so results stay put. -->
                <AssistantLoader
                    v-if="searching"
                    variant="inline"
                    class="absolute right-3 top-1/2 -translate-y-1/2"
                />
            </div>
        </div>

        <div class="mt-2 flex-1 overflow-y-auto px-4 pb-4">
            <!-- Initial / list-reload loader (a clear spinner, not just shimmer). -->
            <AssistantLoader v-if="loading" :label="t('Loading articles…')" />

            <!-- Empty -->
            <div v-else-if="articles.length === 0" class="pt-10 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ query.trim().length >= 2 ? t('No articles found.') : t('No help articles yet.') }}
            </div>

            <!-- List. Kept visible (dimmed) during a search so results don't flash away. -->
            <ul v-else class="space-y-1 transition-opacity" :class="{ 'opacity-50': searching }">
                <li v-for="item in articles" :key="item.slug">
                    <button
                        type="button"
                        class="w-full rounded-lg px-2 py-2 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                        @click="openSlug = item.slug"
                    >
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.title }}</div>
                        <div v-if="item.excerpt" class="mt-0.5 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ item.excerpt }}
                        </div>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
