<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import UserLayout from '@themes/default/js/Layouts/UserLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { renderMarkdown } from '@/Composables/useMarkdown'

defineOptions({ layout: UserLayout })

interface Source {
    doc: string
    chunk?: number
    score?: number
    snippet?: string
    doc_label?: string
}

interface SharedMessage {
    id: string | number
    role: 'user' | 'assistant'
    content: string
    sources?: Source[] | null
}

const props = defineProps<{
    session: {
        title: string | null
        tool_slug: string
        source_meta: Record<string, unknown> | null
        created_at: string
    }
    messages: SharedMessage[]
}>()

const { t } = useTranslate()

function formatDate(iso: string): string {
    try {
        return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
    } catch {
        return iso
    }
}
</script>

<template>
    <Head :title="session.title || t('Shared Conversation')" />

    <div class="mx-auto w-full max-w-3xl px-4 py-10">
        <!-- Header -->
        <div class="mb-8 rounded-2xl border border-gray-200 bg-white p-6 dark:border-surface-800 dark:bg-surface-900">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                        {{ t('Shared Conversation') }}
                    </p>
                    <h1 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                        {{ session.title || t('Untitled Session') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ formatDate(session.created_at) }}
                    </p>
                </div>
                <Link href="/" class="btn-primary shrink-0 rounded-lg px-4 py-2 text-sm font-semibold">
                    {{ t('Try it yourself') }}
                </Link>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="!messages.length"
            class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-surface-700 dark:bg-surface-900"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('This conversation has no messages yet.') }}</p>
        </div>

        <!-- Messages -->
        <div v-else class="space-y-4">
            <div v-for="msg in messages" :key="msg.id" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                <div
                    class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed"
                    :class="msg.role === 'user'
                        ? 'bg-primary-600 text-white'
                        : 'border border-gray-200 bg-white text-gray-800 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-200'"
                >
                    <p v-if="msg.role === 'user'" class="whitespace-pre-wrap">{{ msg.content }}</p>
                    <div v-else class="prose prose-sm max-w-none dark:prose-invert" v-html="renderMarkdown(msg.content)"></div>

                    <div v-if="msg.role === 'assistant' && msg.sources?.length" class="mt-3 border-t border-gray-100 pt-2 dark:border-surface-800">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ t('Sources') }}</p>
                        <ul class="mt-1 space-y-0.5">
                            <li v-for="(source, i) in msg.sources" :key="i" class="truncate text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="source.doc_label" class="font-semibold">[{{ source.doc_label }}]</span>
                                {{ source.doc }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
