<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import hljs from 'highlight.js/lib/core'
import javascript from 'highlight.js/lib/languages/javascript'
import typescript from 'highlight.js/lib/languages/typescript'
import python from 'highlight.js/lib/languages/python'
import php from 'highlight.js/lib/languages/php'
import css from 'highlight.js/lib/languages/css'
import bash from 'highlight.js/lib/languages/bash'
import json from 'highlight.js/lib/languages/json'
import xml from 'highlight.js/lib/languages/xml'

hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('js', javascript)
hljs.registerLanguage('typescript', typescript)
hljs.registerLanguage('ts', typescript)
hljs.registerLanguage('python', python)
hljs.registerLanguage('py', python)
hljs.registerLanguage('php', php)
hljs.registerLanguage('css', css)
hljs.registerLanguage('bash', bash)
hljs.registerLanguage('sh', bash)
hljs.registerLanguage('json', json)
hljs.registerLanguage('xml', xml)
hljs.registerLanguage('html', xml)

interface SharedMessage {
    role: string
    content: string
    model?: string
    created_at?: string
}

interface SharedData {
    title: string | null
    model: string | null
    messages: SharedMessage[]
}

const props = defineProps<{
    share_token: string
}>()

const loading = ref(true)
const error = ref('')
const data = ref<SharedData | null>(null)

onMounted(async () => {
    try {
        const res = await fetch(`/api/v1/share/${props.share_token}`)
        if (!res.ok) {
            throw new Error('Failed to load shared conversation')
        }
        const json = await res.json()
        if (json.success) {
            data.value = json.data
        } else {
            throw new Error(json.message || 'Failed to load shared conversation')
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An error occurred'
    } finally {
        loading.value = false
    }
})

function renderMarkdown(content: string): string {
    let html = marked.parse(content) as string
    
    html = html.replace(/<pre><code(?:\s+class="language-([^"]*)")?>([\s\S]*?)<\/code><\/pre>/g, (_m, lang, code) => {
        const decoded = code.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"')
        let highlighted = ''
        if (lang && hljs.getLanguage(lang)) {
            highlighted = hljs.highlight(decoded, { language: lang }).value
        } else {
            highlighted = hljs.highlightAuto(decoded).value
        }
        return `<pre class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 overflow-x-auto"><code class="text-sm">${highlighted}</code></pre>`
    })

    // Sanitize — this view is PUBLIC and renders attacker-controllable message
    // content, so raw marked output must never reach v-html.
    return DOMPurify.sanitize(html, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ['style', 'form', 'input', 'button'],
        FORBID_ATTR: ['style'],
        ADD_ATTR: ['target', 'rel'],
    })
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950">
        <!-- Header -->
        <div class="border-b border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm sticky top-0 z-10">
            <div class="max-w-3xl mx-auto px-4 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ data?.title || 'Shared Conversation' }}
                        </h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Shared conversation
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-3xl mx-auto px-4 py-8">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="text-center py-20">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-red-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Unable to load conversation</h2>
                <p class="text-gray-500 dark:text-gray-400">{{ error }}</p>
            </div>

            <!-- Messages -->
            <div v-else-if="data" class="space-y-6">
                <div
                    v-for="(message, index) in data.messages"
                    :key="index"
                    class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden"
                >
                    <!-- Message header -->
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                    </svg>
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">Assistant</span>
                            </div>
                            <span v-if="message.model" class="text-xs text-gray-500 dark:text-gray-400">
                                {{ message.model }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Message content -->
                    <div class="px-4 py-4">
                        <div 
                            class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300"
                            v-html="renderMarkdown(message.content)"
                        ></div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center pt-8 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        This is a shared conversation. 
                        <a href="/" class="text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300">
                            Start your own conversation
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.prose :deep(pre) {
    margin: 1rem 0;
    border-radius: 0.5rem;
}

.prose :deep(code) {
    font-size: 0.875rem;
}

.prose :deep(p) {
    margin-bottom: 0.75rem;
}

.prose :deep(p:last-child) {
    margin-bottom: 0;
}

.prose :deep(ul), .prose :deep(ol) {
    margin-bottom: 0.75rem;
    padding-left: 1.5rem;
}

.prose :deep(li) {
    margin-bottom: 0.25rem;
}
</style>
