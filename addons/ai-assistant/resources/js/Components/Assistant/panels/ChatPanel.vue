<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from 'vue'
import AssistantMessages from '../AssistantMessages.vue'
import AssistantInput from '../AssistantInput.vue'
import { csrfHeaders } from '../../../Composables/useAssistantApi'
import { toastAssistantError } from '../../../Composables/useAssistantErrors'
import { useTranslate } from '@/Composables/useTranslate'
import type {
    AssistantAttachment,
    AssistantFrame,
    AssistantMessageItem,
    AssistantSettings,
    AssistantSource,
    SlashCommand,
} from '../../../types'

const props = defineProps<{
    settings: AssistantSettings
    sessionId: string
}>()

const { t } = useTranslate()

const storageKey = `ai_assistant_history_${props.sessionId}`
const HISTORY_TTL_MS = 24 * 60 * 60 * 1000

interface StoredHistory {
    timestamp?: number
    messages?: AssistantMessageItem[]
}

const loadHistory = (): AssistantMessageItem[] => {
    try {
        const now = Date.now()

        // Drop expired histories (>24h) so localStorage doesn't grow without bound.
        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i)
            if (!key || !key.startsWith('ai_assistant_history_')) continue

            try {
                const item = localStorage.getItem(key)
                if (!item) continue
                const parsed = JSON.parse(item) as StoredHistory
                if (now - (parsed.timestamp ?? 0) > HISTORY_TTL_MS) {
                    localStorage.removeItem(key)
                }
            } catch {
                localStorage.removeItem(key)
            }
        }

        const stored = localStorage.getItem(storageKey)
        if (stored) {
            const data = JSON.parse(stored) as StoredHistory
            const isRecent = now - (data.timestamp ?? 0) < HISTORY_TTL_MS
            if (isRecent && Array.isArray(data.messages)) {
                const list = [...data.messages]
                while (list.length > 0 && list[list.length - 1].role === 'assistant' && !list[list.length - 1].content) {
                    list.pop()
                }
                return list
            }
        }
    } catch {
        // storage unavailable (private mode / quota) — start empty
    }

    return []
}

const messages = ref<AssistantMessageItem[]>(loadHistory())
const isStreaming = ref(false)
const error = ref<string | null>(null)
const messagesContainer = ref<InstanceType<typeof AssistantMessages> | null>(null)
const abortController = ref<AbortController | null>(null)


const isAdmin = computed(() => props.settings.scope === 'admin')
const showFileUpload = computed(() => isAdmin.value || props.settings.allow_file_upload)
const allowedFileTypes = computed(() => props.settings.allowed_file_types || 'pdf,docx,txt,csv,png,jpg')
const commands = computed<SlashCommand[]>(() => props.settings.commands ?? [])

// The chat greeting. An admin-set greeting wins as-is; otherwise personalise by first
// name for a signed-in visitor and fall back to the generic line for guests.
const chatGreeting = computed(() => {
    if (props.settings.greeting_message) return props.settings.greeting_message
    return props.settings.greeting_name
        ? t('Hi :name! How can I help you today?', { name: props.settings.greeting_name })
        : t('Hi there! How can I help you today?')
})

const scrollToBottom = () => {
    nextTick(() => {
        const el = messagesContainer.value?.container
        if (el) el.scrollTop = el.scrollHeight
    })
}

/** Mirrors SlashCommandRegistry::parse() — a lone "/" or "/admin/users" is not a command. */
function parseCommand(message: string): { name: string; args: string } | null {
    const match = /^\/([a-z][a-z0-9_-]*)(?:\s+([\s\S]*))?$/i.exec(message.trimStart())
    if (!match) return null

    return { name: match[1].toLowerCase(), args: (match[2] ?? '').trim() }
}

function runClientCommand(name: string): boolean {
    if (name !== 'clear') return false

    stopStreaming()
    messages.value = []
    error.value = null
    try {
        localStorage.removeItem(storageKey)
    } catch {
        // ignore
    }

    return true
}

async function sendMessage(text: string, file?: AssistantAttachment) {
    if ((!text.trim() && !file) || isStreaming.value) return

    const parsed = parseCommand(text)
    if (parsed && !file) {
        const command = commands.value.find(c => c.name === parsed.name)
        if (command?.client_only && runClientCommand(command.name)) return
    }

    error.value = null

    const displayMsg = file ? `${text}\n\n📎 *${file.name}*` : text
    messages.value.push({ role: 'user', content: displayMsg, createdAt: Date.now() })
    messages.value.push({ role: 'assistant', content: '', createdAt: Date.now() })
    const assistantIdx = messages.value.length - 1
    isStreaming.value = true
    scrollToBottom()

    // No history is sent: the server rebuilds multi-turn context from the persisted
    // conversation (keyed by session_id), so a client-supplied transcript would be both
    // redundant and untrusted. This is the spoofable-history hole closed at the source.
    const payloadMessage = file
        ? `[Attached File: ${file.name}]\n---\n${file.text}\n---\n\n${text}`
        : text

    const controller = new AbortController()
    abortController.value = controller

    try {
        const response = await fetch(props.settings.endpoints.chat, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                ...csrfHeaders(),
            },
            credentials: 'same-origin',
            signal: controller.signal,
            body: JSON.stringify({
                message: payloadMessage,
                session_id: props.sessionId,
                context_page: window.location.pathname,
            }),
        })

        if (!response.ok) {
            error.value = await httpErrorMessage(response)
            dropPlaceholder(assistantIdx)
            return
        }

        const reader = response.body?.getReader()
        if (!reader) {
            error.value = t('Unable to read response.')
            dropPlaceholder(assistantIdx)
            return
        }

        await readStream(reader, assistantIdx)

        const finalContent = messages.value[assistantIdx]?.content ?? ''
        if (finalContent) {
            messages.value[assistantIdx].messageHash = await sha256(finalContent)
        } else if (!error.value) {
            error.value = t('The assistant did not return a response. Please try again.')
            dropPlaceholder(assistantIdx)
        }
    } catch (e: unknown) {
        const aborted = (e as { name?: string } | null)?.name === 'AbortError' || controller.signal.aborted

        if (aborted) {
            const content = messages.value[assistantIdx]?.content ?? ''
            if (content) {
                messages.value[assistantIdx].messageHash = await sha256(content)
            } else {
                dropPlaceholder(assistantIdx)
            }
        } else {
            error.value = t('Connection lost. Please try again.')
            dropPlaceholder(assistantIdx)
        }
    } finally {
        isStreaming.value = false
        abortController.value = null
        scrollToBottom()
    }
}

async function readStream(reader: ReadableStreamDefaultReader<Uint8Array>, assistantIdx: number): Promise<void> {
    const decoder = new TextDecoder()
    let buffer = ''

    try {
        while (true) {
            const { done, value } = await reader.read()
            if (done) break

            buffer += decoder.decode(value, { stream: true })

            const lines = buffer.split('\n')
            buffer = lines.pop() ?? ''

            for (const line of lines) {
                const frame = parseFrame(line)
                if (!frame) continue
                if (applyFrame(frame, assistantIdx)) return
            }
        }
    } finally {
        reader.releaseLock()
    }
}

function parseFrame(line: string): AssistantFrame | null {
    const trimmed = line.trimEnd()
    if (!trimmed.startsWith('data:')) return null

    const payload = trimmed.slice(5).trim()
    if (!payload) return null

    try {
        return JSON.parse(payload) as AssistantFrame
    } catch {
        return null
    }
}

/** Returns true when the stream is finished (done/error). */
function applyFrame(frame: AssistantFrame, assistantIdx: number): boolean {
    const message = messages.value[assistantIdx]

    switch (frame.type) {
        case 'ready':
            return false

        case 'token':
            if (message) {
                message.content += frame.content
                scrollToBottom()
            }
            return false

        case 'sources':
            if (message && frame.sources?.length) {
                message.sources = frame.sources
            }
            return false

        case 'error':
            error.value = frame.message || t('Something went wrong. Please try again.')
            if (!message?.content) dropPlaceholder(assistantIdx)
            return true

        case 'done':
            return true

        default:
            return false
    }
}

function dropPlaceholder(index: number) {
    const message = messages.value[index]
    if (message && message.role === 'assistant' && !message.content) {
        messages.value.splice(index, 1)
    }
}

// Kept as a thin wrapper so the inline error line still gets the text, while the shared
// helper raises the toast — the widget can be scrolled out of view or collapsed, and a
// refusal only written into the panel goes unseen.
async function httpErrorMessage(response: Response): Promise<string> {
    return toastAssistantError(response)
}

function stopStreaming() {
    abortController.value?.abort()
}

async function sha256(message: string): Promise<string> {
    const data = new TextEncoder().encode(message)
    const hashBuffer = await crypto.subtle.digest('SHA-256', data)
    return Array.from(new Uint8Array(hashBuffer))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('')
}

function retryLast() {
    const lastUserIdx = messages.value.map(m => m.role).lastIndexOf('user')
    if (lastUserIdx < 0) return

    const lastContent = messages.value[lastUserIdx].content

    messages.value.splice(lastUserIdx)
    error.value = null

    void sendMessage(lastContent)
}

watch(messages, (list) => {
    try {
        localStorage.setItem(storageKey, JSON.stringify({ timestamp: Date.now(), messages: list }))
    } catch {
        // ignore storage quota errors
    }
}, { deep: true })

/**
 * Restore the thread from the server on open. The server is the source of truth —
 * localStorage above is only an instant-paint cache and an offline fallback.
 */
const restoreFromServer = async () => {
    const url = props.settings.endpoints.transcript
    if (!url) return

    try {
        const response = await fetch(`${url}?session_id=${encodeURIComponent(props.sessionId)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
        if (!response.ok) return

        const data = await response.json() as {
            messages?: Array<{ role: 'user' | 'assistant'; content: string; hash?: string; sources?: AssistantSource[]; created_at?: string }>
        }

        if (Array.isArray(data.messages) && data.messages.length > 0) {
            messages.value = data.messages.map((m) => ({
                role: m.role,
                content: m.content,
                messageHash: m.hash,
                sources: m.sources ?? [],
                createdAt: m.created_at ? new Date(m.created_at).getTime() : undefined,
            }))
            scrollToBottom()
        }
    } catch {
        // Offline or persistence disabled — keep whatever localStorage gave us.
    }
}

onMounted(() => {
    if (messages.value.length > 0) scrollToBottom()
    if (!isStreaming.value) void restoreFromServer()
})

onBeforeUnmount(() => {
    abortController.value?.abort()
})

defineExpose({ sendMessage })
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <AssistantMessages
            ref="messagesContainer"
            :messages="messages"
            :error="error"
            :greeting="chatGreeting"
            :is-streaming="isStreaming"
            :session-id="sessionId"
            :feedback-endpoint="settings.endpoints.feedback"
            :avatar-url="settings.avatar_url"
            :assistant-name="settings.assistant_name"
            @retry="retryLast"
        />

        <AssistantInput
            :disabled="isStreaming"
            :is-streaming="isStreaming"
            :allow-upload="showFileUpload"
            :allow-emoji="settings.enable_emoji"
            :allowed-types="allowedFileTypes"
            :extract-endpoint="settings.endpoints.extract"
            :commands="commands"
            @send="sendMessage"
            @stop="stopStreaming"
        />

        <!-- Legal note. Sits under the input (it's about sending a message, so this is where
             it belongs), and the server sends `legal: null` — so this renders nothing at all —
             when it's disabled, on the admin surface, or when neither link is configured.

             The empty HTML comments below join the links tightly, keeping a stray space out
             from before the closing full stop. The separator's spaces then have to live INSIDE
             its interpolation: Vue's whitespace: 'condense' strips leading and trailing
             whitespace within an element, so writing the spaces as plain text around the
             mustache compiles them away and the line reads "Privacy PolicyandTerms of Use".
             Interpolated text is never condensed. Do not move them back out. -->
        <p
            v-if="settings.legal"
            class="shrink-0 px-4 pb-2.5 text-center text-[11px] leading-snug text-gray-400 dark:text-gray-500"
        >
            {{ t('By continuing, you agree to our') }}
            <a
                v-if="settings.legal.privacy_url"
                :href="settings.legal.privacy_url"
                target="_blank"
                rel="noopener noreferrer"
                class="underline hover:text-gray-600 dark:hover:text-gray-300"
            >{{ t('Privacy Policy') }}</a><!--
            --><span v-if="settings.legal.privacy_url && settings.legal.terms_url">{{ ` ${t('and')} ` }}</span><!--
            --><a
                v-if="settings.legal.terms_url"
                :href="settings.legal.terms_url"
                target="_blank"
                rel="noopener noreferrer"
                class="underline hover:text-gray-600 dark:hover:text-gray-300"
            >{{ t('Terms of Use') }}</a>.
        </p>
    </div>
</template>
