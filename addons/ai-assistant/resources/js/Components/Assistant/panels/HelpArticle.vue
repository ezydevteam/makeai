<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import DOMPurify from 'dompurify'
import AssistantLoader from '../AssistantLoader.vue'
import { fetchHelpArticle } from '../../../Composables/useAssistantPanels'
import { handleMarkdownCopy } from '../../../Composables/useAssistantMarkdown'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings, HelpArticleDetail } from '../../../types'

const props = defineProps<{
    settings: AssistantSettings
    slug: string
}>()

const emit = defineEmits<{
    (e: 'open', slug: string): void
    (e: 'back'): void
}>()

const { t } = useTranslate()

const article = ref<HelpArticleDetail | null>(null)
const loading = ref(true)
const failed = ref(false)

// The body is sanitised server-side, but it reaches v-html, so run it through the same
// DOMPurify path the rest of the widget uses — defence in depth against a future change
// upstream.
const safeBody = computed(() =>
    article.value ? DOMPurify.sanitize(article.value.body, { USE_PROFILES: { html: true } }) : '',
)

async function load() {
    loading.value = true
    failed.value = false
    article.value = await fetchHelpArticle(props.settings.endpoints.help_article, props.slug)
    loading.value = false
    if (!article.value) failed.value = true
}

onMounted(load)
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <button
            type="button"
            class="flex shrink-0 items-center gap-1 px-4 py-2.5 text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
            @click="emit('back')"
        >
            <i class="ti ti-chevron-left"></i>
            {{ t('Back to Help') }}
        </button>

        <div class="flex-1 overflow-y-auto px-4 pb-4">
            <AssistantLoader v-if="loading" :label="t('Loading article…')" />

            <div v-else-if="failed" class="pt-10 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ t('This article could not be loaded.') }}
            </div>

            <template v-else-if="article">
                <h1 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">{{ article.title }}</h1>
                <div class="ai-markdown text-sm text-gray-700 dark:text-gray-300" v-html="safeBody" @click="handleMarkdownCopy" />

                <div v-if="article.related.length" class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('Related articles') }}</p>
                    <ul class="space-y-1">
                        <li v-for="rel in article.related" :key="rel.slug">
                            <button
                                type="button"
                                class="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-[var(--ai-accent,#1F75FE)] dark:hover:text-[var(--ai-accent,#1F75FE)] dark:text-gray-300 dark:hover:bg-gray-800"
                                @click="emit('open', rel.slug)"
                            >
                                <i class="ti ti-file-text text-gray-400"></i>
                                <span class="truncate">{{ rel.title }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </template>
        </div>
    </div>
</template>
