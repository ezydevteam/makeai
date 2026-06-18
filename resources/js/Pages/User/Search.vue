<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'

defineOptions({ layout: UserDashboardLayout })

interface Conversation {
    id: number
    ulid: string
    title: string
    model: string
    message_count: number
    last_message_at: string | null
}

interface Document {
    id: number
    title: string
    tool_slug: string
    word_count: number | null
    created_at: string
}

interface Tool {
    name: string
    slug: string
    description: string
    icon: string
    color: string
    requires_pro: boolean
}

const props = defineProps<{
    query: string
    conversations: Conversation[]
    documents: Document[]
    tools: Tool[]
}>()

const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { formatNumber } = useNumberFormat()

const searchQuery = ref(props.query)

const performSearch = () => {
    if (searchQuery.value.trim().length >= 2) {
        router.get(route('user.dashboard.search'), { q: searchQuery.value }, { preserveState: true })
    }
}

const totalResults = props.conversations.length + props.documents.length + props.tools.length
</script>

<template>
    <Head :title="t('Search')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Search') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Search your conversations, documents, and tools.') }}</p>
        </div>

        <!-- Search Input -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400">
                    <i class="ti ti-search text-xl"></i>
                </span>
                <input
                    v-model="searchQuery"
                    @keyup.enter="performSearch"
                    type="text"
                    class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-12 pr-4 text-sm text-gray-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    :placeholder="t('Search conversations, documents, tools...')"
                />
            </div>
        </div>

        <!-- Results Summary -->
        <div v-if="query" class="text-sm text-gray-600 dark:text-gray-400">
            {{ t('Found :count results for ":query"', { count: totalResults, query }) }}
        </div>

        <!-- Conversations -->
        <div v-if="conversations.length > 0" class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Conversations') }} ({{ conversations.length }})</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <Link v-for="conv in conversations" :key="conv.id" :href="route('chat.show', conv.ulid)" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ conv.title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ conv.model }} · {{ conv.message_count }} {{ t('messages') }}</p>
                    </div>
                    <span class="text-xs text-gray-400 shrink-0 ml-3">{{ conv.last_message_at ? formatDate(conv.last_message_at) : '' }}</span>
                </Link>
            </div>
        </div>

        <!-- Documents -->
        <div v-if="documents.length > 0" class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Documents') }} ({{ documents.length }})</h2>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <Link v-for="doc in documents" :key="doc.id" :href="route('documents.edit', doc.id)" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ doc.title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ doc.tool_slug }} · {{ formatNumber(doc.word_count ?? 0) }} {{ t('words') }}</p>
                    </div>
                    <span class="text-xs text-gray-400 shrink-0 ml-3">{{ formatDate(doc.created_at) }}</span>
                </Link>
            </div>
        </div>

        <!-- Tools -->
        <div v-if="tools.length > 0" class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('AI Tools') }} ({{ tools.length }})</h2>
            </div>
            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="tool in tools"
                    :key="tool.slug"
                    :href="route('ai.tools.show', tool.slug)"
                    class="group flex items-start gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-500 hover:shadow-md dark:bg-gray-900 dark:border-gray-800"
                >
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg" :style="{ background: (tool.color || '#1F75FE') + '18', color: tool.color || '#1F75FE' }">
                        <i :class="tool.icon || 'ti ti-wand'" class="text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ tool.name }}</h3>
                            <span v-if="tool.requires_pro" class="shrink-0 rounded-full bg-violet-100 px-1.5 py-px text-[10px] font-bold text-violet-700 uppercase">{{ t('Pro') }}</span>
                        </div>
                        <p class="mt-0.5 text-xs text-gray-500 line-clamp-2">{{ tool.description }}</p>
                    </div>
                </Link>
            </div>
        </div>

        <!-- No Results -->
        <div v-if="query && totalResults === 0" class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-10 text-center">
            <i class="ti ti-search-off text-4xl text-gray-300 dark:text-gray-600"></i>
            <p class="mt-4 text-gray-500 dark:text-gray-400">{{ t('No results found for ":query"', { query }) }}</p>
            <p class="text-sm text-gray-400 dark:text-gray-500 mt-2">{{ t('Try different keywords or check your spelling.') }}</p>
        </div>
    </div>
</template>
