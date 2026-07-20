<script setup lang="ts">
import { computed, ref } from 'vue'
import AssistantLoader from './AssistantLoader.vue'
import AssistantCsatCard from './AssistantCsatCard.vue'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import Tooltip from '@/Components/UI/Tooltip.vue'
import { deleteConversation } from '../../Composables/useAssistantPanels'
import { relativeTime } from '../../Composables/useAssistantFormat'
import { useTranslate } from '@/Composables/useTranslate'
import type { AssistantSettings, ConversationSummary } from '../../types'

/**
 * The chat history list: every past conversation, newest first, click to reopen.
 *
 * Purely presentational — the shell owns the conversation list, because it also needs it
 * to decide the landing view (recent chats vs. straight into a new chat). Fetching here
 * too would mean two requests for the same data and a race over which one wins.
 *
 * Guests can chat but their threads aren't kept (canSave is false), so instead of an empty
 * list they're told why — mirroring "Temporary chat — sign in to save your history".
 */
const props = defineProps<{
    settings: AssistantSettings
    currentSessionId: string
    conversations: ConversationSummary[]
    canSave: boolean
    loading: boolean
    /** Hide the back arrow when history IS the landing view and there's no chat behind it. */
    canGoBack?: boolean
}>()

const emit = defineEmits<{
    (e: 'open', sessionId: string): void
    (e: 'new'): void
    (e: 'close'): void
    (e: 'deleted', sessionId: string): void
}>()

const { t } = useTranslate()

const deleting = ref<string | null>(null)

/*
 | Experience rating, asked ONCE — about the most recent conversation, here in the history
 | list rather than inside the chat.
 |
 | It used to render after every assistant answer, which meant a user having a long back-
 | and-forth got nagged repeatedly. Asking about the thread they just finished, at the
 | moment they come back to the list, is both less intrusive and a better question: they're
 | rating the conversation, not one reply.
 */
const latest = computed<ConversationSummary | null>(() => props.conversations[0] ?? null)

const ratedSessions = ref<Set<string>>(loadRated())

const csatTarget = computed<ConversationSummary | null>(() => {
    if (!props.settings.enable_csat || !latest.value) return null
    return ratedSessions.value.has(latest.value.session_id) ? null : latest.value
})

function loadRated(): Set<string> {
    try {
        const raw = localStorage.getItem('ai_assistant_csat_rated')
        return new Set<string>(raw ? JSON.parse(raw) as string[] : [])
    } catch {
        return new Set<string>()
    }
}

function onRated(sessionId: string) {
    const next = new Set(ratedSessions.value)
    next.add(sessionId)
    ratedSessions.value = next

    try {
        localStorage.setItem('ai_assistant_csat_rated', JSON.stringify([...next]))
    } catch {
        // storage unavailable — the rating still reached the server; we just can't
        // remember locally that we asked.
    }
}

/*
 | Deletion is confirmed, not immediate. A conversation is unrecoverable once gone (the
 | messages cascade with it), and the trash icon sits right next to the row you click to
 | open a chat — one slip and the thread is gone.
 */
const pendingDelete = ref<ConversationSummary | null>(null)

function askDelete(item: ConversationSummary) {
    pendingDelete.value = item
}

async function confirmDelete() {
    const item = pendingDelete.value
    if (!item) return

    deleting.value = item.session_id

    const ok = await deleteConversation(props.settings.endpoints.conversations, item.session_id)
    if (ok) {
        emit('deleted', item.session_id)
        // Deleting the thread you're currently in drops you into a fresh one.
        if (item.session_id === props.currentSessionId) emit('new')
    }

    deleting.value = null
    pendingDelete.value = null
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="flex shrink-0 items-center justify-between px-4 py-2.5">
            <button
                v-if="canGoBack"
                type="button"
                class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                @click="emit('close')"
            >
                <i class="ti ti-chevron-left"></i>
                {{ t('Back to chat') }}
            </button>
            <span v-else class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ t('Recent conversations') }}
            </span>

            <button
                type="button"
                class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-white transition-opacity hover:opacity-90"
                style="background: var(--ai-accent, #1F75FE);"
                @click="emit('new')"
            >
                <i class="ti ti-plus text-[13px]"></i>
                {{ t('New chat') }}
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 pb-4">
            <AssistantLoader v-if="loading" :label="t('Loading conversations…')" />

            <!-- Guests: chat works, history doesn't. Say so rather than showing "nothing here". -->
            <div
                v-else-if="!canSave"
                class="mt-8 rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center dark:border-gray-700"
            >
                <i class="ti ti-history mb-2 block text-2xl text-gray-300 dark:text-gray-600"></i>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ t('This is a temporary chat.') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ t('Sign in to save your chat history.') }}
                </p>
            </div>

            <div
                v-else-if="conversations.length === 0"
                class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t('No past conversations yet.') }}
            </div>

            <ul v-else class="space-y-1">
                <li
                    v-for="item in conversations"
                    :key="item.session_id"
                    class="group flex items-center gap-1 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                    :class="{ 'bg-gray-50 dark:bg-gray-800': item.session_id === currentSessionId }"
                >
                    <button
                        type="button"
                        class="min-w-0 flex-1 px-2 py-2 text-left"
                        @click="emit('open', item.session_id)"
                    >
                        <div class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ item.title }}
                        </div>
                        <div class="mt-0.5 text-xs text-gray-400">
                            {{ item.last_message_at ? relativeTime(new Date(item.last_message_at).getTime()) : '' }}
                        </div>
                    </button>

                    <Tooltip :content="t('Delete conversation')" placement="left">
                        <button
                            type="button"
                            class="mr-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-gray-300 opacity-0 transition hover:bg-gray-200 hover:text-red-500 focus:opacity-100 group-hover:opacity-100 dark:hover:bg-gray-700"
                            :disabled="deleting === item.session_id"
                            :aria-label="t('Delete conversation')"
                            @click.stop="askDelete(item)"
                        >
                            <AssistantLoader v-if="deleting === item.session_id" variant="inline" />
                            <i v-else class="ti ti-trash text-sm"></i>
                        </button>
                    </Tooltip>
                </li>
            </ul>

            <!-- Rate the most recent conversation. Asked once, about the thread the user
                 just finished — not after every answer inside the chat. -->
            <div v-if="csatTarget && !loading" class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-800">
                <AssistantCsatCard
                    :key="csatTarget.session_id"
                    :endpoint="settings.endpoints.csat"
                    :session-id="csatTarget.session_id"
                    @rated="onRated(csatTarget.session_id)"
                />
            </div>
        </div>

        <ActionConfirmModal
            :open="pendingDelete !== null"
            :title="t('Delete conversation?')"
            :message="t('“:title” and all of its messages will be permanently deleted. This cannot be undone.', { title: pendingDelete?.title ?? '' })"
            :confirm-label="t('Delete')"
            :cancel-label="t('Cancel')"
            :processing-label="t('Deleting…')"
            :processing="deleting !== null"
            variant="danger"
            @cancel="pendingDelete = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
