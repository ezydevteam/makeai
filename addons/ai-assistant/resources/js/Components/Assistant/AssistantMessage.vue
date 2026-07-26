<script setup lang="ts">
import { computed, ref } from 'vue'
import { renderMarkdown, handleMarkdownCopy, copyText } from '../../Composables/useAssistantMarkdown'
import { relativeTime } from '../../Composables/useAssistantFormat'
import { csrfHeaders } from '../../Composables/useAssistantApi'
import { toastAssistantError, toastAssistantFailure } from '../../Composables/useAssistantErrors'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantMessageItem } from '../../types'

const props = defineProps<{
    message: AssistantMessageItem
    sessionId: string
    feedbackEndpoint: string
    showFeedback: boolean
    avatarUrl?: string | null
    assistantName?: string | null
}>()

const { t } = useTranslate()

const rating = ref<1 | -1 | null>(null)
const feedbackFailed = ref(false)
const sending = ref(false)
const copied = ref(false)

const rendered = computed(() => renderMarkdown(props.message.content))
const timestamp = computed(() => relativeTime(props.message.createdAt))

// Citation links are resolved SERVER-side and arrive on the source as `url`. They used to be
// built here from a hardcoded '/help/article', which 404'd on any install that changed the
// Knowledge Base's public prefix (it is admin-configurable) — and would be flatly wrong for a
// MakeAI documentation citation, which does not live on this site at all.
//
// A source without a url still renders, as a plain label rather than a dead link: that covers
// both a docs install with no public docs site configured, and citations persisted by an older
// version of the addon.

async function rate(value: 1 | -1) {
    if (sending.value) return

    // Feedback is keyed on (session_id, message_hash) server-side; without a hash the
    // row cannot be stored, so don't pretend it worked.
    if (!props.message.messageHash) {
        feedbackFailed.value = true
        return
    }

    const previous = rating.value
    rating.value = value
    feedbackFailed.value = false
    sending.value = true

    try {
        const response = await fetch(props.feedbackEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                session_id: props.sessionId,
                message_hash: props.message.messageHash,
                rating: value,
                context_page: window.location.pathname,
            }),
        })

        if (!response.ok) {
            // The server says why — demo mode, an expired session, a rate limit. Toast it
            // rather than reducing every refusal to the same silent thumb reset.
            await toastAssistantError(response, t('Your feedback could not be saved.'))
            throw new Error(`HTTP ${response.status}`)
        }
    } catch (e: unknown) {
        // The admin widget used to POST to the frontend route, 401, and swallow it — the
        // thumb stayed lit and nothing was ever recorded. Surface the failure instead.
        rating.value = previous
        feedbackFailed.value = true

        // A network failure never reached the toast above, so raise one here.
        if (!(e instanceof Error) || !e.message.startsWith('HTTP ')) {
            toastAssistantFailure(t('Your feedback could not be saved.'))
        }
    } finally {
        sending.value = false
    }
}

async function onCopy() {
    const ok = await copyText(props.message.content)
    if (!ok) return

    copied.value = true
    window.setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
    <div class="ai-message flex gap-2" :class="message.role === 'user' ? 'justify-end' : ''">
        <!-- User message (right-aligned) -->
        <div v-if="message.role === 'user'" class="flex flex-col items-end max-w-[80%]">
            <div
                class="rounded-xl px-4 py-2 text-sm whitespace-pre-wrap break-words"
                style="background: var(--ai-accent, #1F75FE); color: #fff;"
            >
                {{ message.content }}
            </div>
            <span v-if="timestamp" class="mt-0.5 px-1 text-[10px] text-gray-400 dark:text-gray-500">{{ timestamp }}</span>
        </div>

        <!-- Assistant message (left-aligned) -->
        <template v-else>
            <div class="w-6 h-6 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 shrink-0 flex items-center justify-center mt-0.5">
                <img
                    v-if="avatarUrl"
                    :src="avatarUrl"
                    :alt="assistantName ?? ''"
                    class="w-full h-full object-cover"
                />
                <svg v-else class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                </svg>
            </div>

            <div class="flex-1 min-w-0">
                <div class="bg-gray-100 dark:bg-gray-800 rounded-xl px-4 py-2 text-sm text-gray-900 dark:text-white">
                    <div
                        v-if="message.content"
                        class="ai-markdown"
                        v-html="rendered"
                        @click="handleMarkdownCopy"
                    />
                    <span v-else class="ai-typing-dots text-gray-500 dark:text-gray-400">{{ t('Thinking') }}</span>
                </div>

                <!-- Citations: the site's Knowledge Base, or the MakeAI docs in the admin panel.
                     Gated on `message.content` — the same condition the actions row below uses —
                     so references can never appear under a message that is still thinking. The
                     server now sends them only after the answer completes; this makes it true on
                     the client regardless of frame order. -->
                <div v-if="message.sources?.length && message.content" class="mt-1.5 flex flex-wrap items-center gap-1.5 px-1">
                    <span class="text-[11px] text-gray-400 dark:text-gray-500">{{ t('Sources:') }}</span>
                    <component
                        v-for="source in message.sources"
                        :is="source.url ? 'a' : 'span'"
                        :key="source.slug"
                        :href="source.url"
                        :target="source.url ? '_blank' : undefined"
                        :rel="source.url ? 'noopener noreferrer' : undefined"
                        class="inline-flex max-w-[160px] items-center gap-1 truncate rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                        :class="source.url ? 'transition-colors hover:text-[var(--ai-accent,#1F75FE)] dark:hover:text-[var(--ai-accent,#1F75FE)]' : ''"
                    >
                        <i class="ti ti-book text-[11px] leading-none"></i>
                        <span class="truncate">{{ source.title }}</span>
                    </component>
                </div>

                <!-- Actions: copy + thumbs -->
                <div v-if="showFeedback && message.content" class="flex items-center gap-1 mt-1 px-1">
                    <button
                        type="button"
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                        :title="copied ? t('Copied!') : t('Copy')"
                        @click="onCopy"
                    >
                        <i v-if="!copied" class="ti ti-copy text-[14px] leading-none block"></i>
                        <i v-else class="ti ti-check text-[14px] leading-none block text-green-500"></i>
                    </button>

                    <button
                        type="button"
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-50"
                        :class="rating === 1 ? 'text-green-500' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                        :title="t('Helpful')"
                        :disabled="sending"
                        @click="rate(1)"
                    >
                        <i class="ti ti-thumb-up text-[14px] leading-none block"></i>
                    </button>

                    <button
                        type="button"
                        class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors disabled:opacity-50"
                        :class="rating === -1 ? 'text-red-500' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                        :title="t('Not helpful')"
                        :disabled="sending"
                        @click="rate(-1)"
                    >
                        <i class="ti ti-thumb-down text-[14px] leading-none block"></i>
                    </button>

                    <span v-if="feedbackFailed" class="text-[11px] text-red-500">
                        {{ t("Couldn't save your feedback.") }}
                    </span>

                    <span v-if="timestamp" class="ml-auto text-[10px] text-gray-400 dark:text-gray-500">{{ timestamp }}</span>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.ai-typing-dots::after {
    content: '';
    animation: ai-dots 1.4s infinite;
}

@keyframes ai-dots {
    0% { content: ''; }
    25% { content: '.'; }
    50% { content: '..'; }
    75% { content: '...'; }
}
</style>

<!--
    Unscoped on purpose: this styles the HTML produced by renderMarkdown() and injected
    with v-html, which carries no scope attribute. Every selector is namespaced under
    .ai-markdown so nothing leaks into the host application.
-->
<style>
.ai-markdown > *:first-child { margin-top: 0; }
.ai-markdown > *:last-child { margin-bottom: 0; }
.ai-markdown p { margin: 0 0 0.6em; }
.ai-markdown h1,
.ai-markdown h2,
.ai-markdown h3,
.ai-markdown h4 {
    font-weight: 600;
    line-height: 1.3;
    margin: 0.9em 0 0.4em;
}
.ai-markdown h1 { font-size: 1.15em; }
.ai-markdown h2 { font-size: 1.08em; }
.ai-markdown h3,
.ai-markdown h4 { font-size: 1em; }
.ai-markdown ul,
.ai-markdown ol {
    margin: 0 0 0.6em;
    padding-left: 1.25em;
}
.ai-markdown ul { list-style: disc; }
.ai-markdown ol { list-style: decimal; }
.ai-markdown li { margin-bottom: 0.2em; }
.ai-markdown strong { font-weight: 600; }
.ai-markdown em { font-style: italic; }
.ai-markdown a {
    color: var(--ai-accent, #1F75FE);
    text-decoration: underline;
    word-break: break-word;
}
.ai-markdown blockquote {
    border-left: 3px solid rgba(0, 0, 0, 0.1);
    padding-left: 0.7em;
    margin: 0 0 0.6em;
    opacity: 0.85;
}
.dark .ai-markdown blockquote { border-left-color: rgba(255, 255, 255, 0.15); }
.ai-markdown table {
    width: 100%;
    display: block;
    overflow-x: auto;
    border-collapse: collapse;
    margin: 0 0 0.6em;
}
.ai-markdown th,
.ai-markdown td {
    border: 1px solid rgba(0, 0, 0, 0.08);
    padding: 0.25em 0.5em;
    text-align: left;
}
.dark .ai-markdown th,
.dark .ai-markdown td { border-color: rgba(255, 255, 255, 0.12); }
.ai-markdown code {
    font-family: ui-monospace, 'SF Mono', 'Fira Code', monospace;
    font-size: 0.85em;
}
.ai-markdown :not(.ai-code-block) > code {
    background: rgba(0, 0, 0, 0.06);
    padding: 0.1em 0.35em;
    border-radius: 4px;
}
.dark .ai-markdown :not(.ai-code-block) > code { background: rgba(255, 255, 255, 0.1); }
.ai-markdown hr {
    border: 0;
    border-top: 1px solid rgba(0, 0, 0, 0.08);
    margin: 0.8em 0;
}
.dark .ai-markdown hr { border-top-color: rgba(255, 255, 255, 0.12); }

/* Fenced code blocks */
.ai-code-block {
    margin: 0 0 0.6em;
    border-radius: 8px;
    overflow: hidden;
    background: #1e1e1e;
}
.ai-code-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.3em 0.6em;
    background: #2d2d2d;
}
.ai-code-lang {
    font-size: 10px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
}
.ai-code-copy {
    background: rgba(255, 255, 255, 0.1);
    border: 0;
    border-radius: 4px;
    color: rgba(255, 255, 255, 0.65);
    cursor: pointer;
    font-size: 10px;
    padding: 2px 6px;
    transition: all 0.15s ease;
}
.ai-code-copy:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.ai-code-block pre {
    margin: 0;
    padding: 0.7em;
    overflow-x: auto;
}
.ai-code-block code {
    background: transparent;
    color: #e6e6e6;
    padding: 0;
    white-space: pre;
}
</style>
