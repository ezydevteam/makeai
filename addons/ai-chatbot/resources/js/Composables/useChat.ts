import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { sanitizeErrorMessage } from '@/Composables/useErrorSanitizer'
import { toastChatError } from './useChatErrors'

export interface ChatMode {
    id: number; slug: string; name: string; icon: string; color_hex: string
    system_prompt: string; preferred_models: string[]; default_model: string
    starter_prompts: string[]
}

export interface Conversation {
    ulid: string; title: string | null; mode_slug: string | null
    model: string | null; last_message_at: string; project_id: number | null
    is_pinned?: boolean; share_token?: string | null
    tags?: ConversationTag[]
}

export interface ChatMessage {
    id: number | string; role: 'user' | 'assistant'; content: string
    model?: string; input_tokens: number; output_tokens: number; credits_charged: number
    created_at?: string; kb_sources?: KbSource[]; attachments?: ChatAttachment[]
}

export interface KbSource {
    ulid: string; title: string; slug: string
}

export interface ChatProject {
    id: number; name: string; color_hex: string | null; conversations_count: number
}

export interface ConversationTag {
    id: number; name: string; color: string; conversations_count?: number
}

export interface ChatAttachment {
    id: string; name: string; mime_type: string; size: number; url?: string
}

function csrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

/**
 * A failure the visitor has already been told about, via the toast raised in `failed()`.
 *
 * Every write goes through the four helpers below, so reporting a refusal there covers the
 * whole feature at once — rename, pin, share, branch, projects, tags, custom instructions
 * and deletes included. Reads (apiGet) stay quiet: a failed background list refresh is not
 * something to interrupt the visitor over.
 *
 * The distinction matters: these still throw so the calling mutation abandons its follow-up
 * work (no optimistic list refresh after a refused delete), but nothing further should
 * report them. `mutate()` below swallows exactly this class, which is what stops a blocked
 * write surfacing a second time as an unhandled promise rejection in the console.
 */
class HandledChatError extends Error {
    readonly handled = true
}

async function failed(res: Response): Promise<HandledChatError> {
    return new HandledChatError(await toastChatError(res))
}

/**
 * Run a mutation whose only failure handling is the toast. Anything unexpected still
 * propagates, so genuine bugs are not buried.
 */
async function mutate<T>(run: () => Promise<T>): Promise<T | undefined> {
    try {
        return await run()
    } catch (e: unknown) {
        if (e instanceof HandledChatError) return undefined
        throw e
    }
}

async function apiGet<T>(url: string): Promise<T> {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() }, credentials: 'same-origin' })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    return res.json()
}

async function apiPost<T>(url: string, body?: Record<string, unknown>): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    })
    if (!res.ok) throw await failed(res)
    return res.json()
}

async function apiDelete(url: string): Promise<void> {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        credentials: 'same-origin',
    })

    // Previously the response was not even inspected: a blocked delete resolved as success,
    // the row vanished from the sidebar, and it reappeared on the next load.
    if (!res.ok) throw await failed(res)
}

async function apiPut<T>(url: string, body: Record<string, unknown>): Promise<T> {
    const res = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    })
    if (!res.ok) throw await failed(res)
    return res.json()
}

function syncChatUrl(ulid: string | null) {
    if (typeof window === 'undefined') return

    const url = ulid ? `/chat/${ulid}` : '/chat'
    window.history.replaceState(window.history.state, '', url)
}

export function useChat() {
    const modes = ref<ChatMode[]>([])
    const conversations = ref<Conversation[]>([])
    const groupedConversations = computed(() => {
        const groups: Record<string, Conversation[]> = { today: [], yesterday: [], last_7_days: [], older: [] }
        for (const c of conversations.value) {
            const ts = new Date(c.last_message_at || '')
            const now = new Date()
            const diff = now.getTime() - ts.getTime()
            const days = Math.floor(diff / 86400000)
            if (days === 0) groups.today.push(c)
            else if (days === 1) groups.yesterday.push(c)
            else if (days < 7) groups.last_7_days.push(c)
            else groups.older.push(c)
        }
        return groups
    })
    const projects = ref<ChatProject[]>([])
    const tags = ref<ConversationTag[]>([])
    const selectedTag = ref<ConversationTag | null>(null)

    const defaultChatModel = computed(() => {
        const chatbot = usePage().props.chatbot as any
        return (usePage().props.default_chat_model as string) || chatbot?.defaultChatModel || 'gpt-4o-mini'
    })

    const allowModelSelect = computed(() => {
        const chatbot = usePage().props.chatbot as any
        if (usePage().props.allow_model_select !== undefined) {
            return (usePage().props.allow_model_select as boolean)
        }
        if (chatbot?.allowModelSelect !== undefined) {
            return (chatbot.allowModelSelect as boolean)
        }
        return true
    })

    const selectedMode = ref<ChatMode | null>(null)
    const activeConversation = ref<Conversation | null>(null)
    const selectedProject = ref<ChatProject | null>(null)
    const messages = ref<ChatMessage[]>([])
    const isStreaming = ref(false)
    const loading = ref(false)
    const error = ref('')
    // Load-state for the two async surfaces that previously swallowed errors and fell
    // back to an empty state (looked like "no conversations" / a blank message panel).
    const conversationsLoading = ref(false)
    const conversationsError = ref('')
    const messagesLoading = ref(false)
    const messagesError = ref('')
    const selectedModel = ref<string | null>(allowModelSelect.value ? null : defaultChatModel.value)
    const abortController = ref<AbortController | null>(null)
    const pendingConversationUlid = ref<string | null>(null)
    const hasMoreMessages = ref(false)
    const loadingOlder = ref(false)
    const useKnowledgeBase = ref(false)
    // Mirrors the server-side gate (Addons\AiChatbot\Support\KnowledgeBase::available):
    // the KB addon must be present, installed AND activated, and the chatbot's own
    // KB toggle must be on. Defaults to false so a missing share never opens the door.
    const kbAvailable = (() => {
        const chatbot = usePage().props.chatbot as any
        return (chatbot?.kbAvailable as boolean) ?? false
    })()
    const availableModels = computed(() => {
        const chatbot = usePage().props.chatbot as any
        const models = (usePage().props.available_models || usePage().props.available_chat_models) as string[] | undefined
        const sharedModels = chatbot?.availableModels as string[] | undefined
        return models ?? sharedModels ?? [defaultChatModel.value]
    })

    async function loadModes() {
        try { const data = await apiGet<{ success: boolean; data: ChatMode[] }>('/api/v1/chat/modes'); modes.value = data.data } catch {}
    }
    async function loadConversations(projectId?: number | null) {
        conversationsLoading.value = true
        conversationsError.value = ''
        try {
            const params = new URLSearchParams()
            if (projectId) params.append('project_id', projectId.toString())
            if (selectedTag.value) params.append('tag_id', selectedTag.value.id.toString())
            const url = params.toString() ? `/api/v1/chat?${params}` : '/api/v1/chat'
            const data = await apiGet<{ success: boolean; data: Conversation[] }>(url)
            conversations.value = data.data
        } catch (e) {
            conversationsError.value = sanitizeErrorMessage(e instanceof Error ? e.message : 'Failed to load conversations')
        } finally {
            conversationsLoading.value = false
        }
    }
    async function loadProjects() {
        try { const data = await apiGet<{ success: boolean; data: ChatProject[] }>('/api/v1/chat/projects'); projects.value = data.data } catch {}
    }

    async function loadTags() {
        try { const data = await apiGet<{ success: boolean; data: ConversationTag[] }>('/api/v1/chat/tags'); tags.value = data.data } catch {}
    }

    async function createTag(name: string, color?: string): Promise<ConversationTag> {
        const data = await apiPost<{ success: boolean; data: ConversationTag }>('/api/v1/chat/tags', { name, color })
        tags.value = [...tags.value, data.data]
        return data.data
    }

    async function updateTag(id: number, name: string, color?: string): Promise<void> {
        const data = await apiPut<{ success: boolean; data: ConversationTag }>(`/api/v1/chat/tags/${id}`, { name, color })
        const idx = tags.value.findIndex(t => t.id === id)
        if (idx !== -1) tags.value[idx] = data.data
    }

    async function deleteTag(id: number): Promise<void> {
        await mutate(async () => {
            await apiDelete(`/api/v1/chat/tags/${id}`)
            tags.value = tags.value.filter(t => t.id !== id)
            if (selectedTag.value?.id === id) {
                selectedTag.value = null
                await loadConversations()
            }
        })
    }

    async function tagConversation(ulid: string, tagIds: number[]): Promise<void> {
        await mutate(async () => {
            await apiPut(`/api/v1/chat/${ulid}/tags`, { tag_ids: tagIds })
            await loadConversations()
        })
    }

    async function filterByTag(tag: ConversationTag | null) {
        selectedTag.value = tag
        await loadConversations(selectedProject.value?.id ?? null)
    }

    async function loadMessages(ulid: string) {
        messagesLoading.value = true
        messagesError.value = ''
        try {
            const data = await apiGet<{ success: boolean; data: { messages: ChatMessage[]; has_more: boolean } }>(`/api/v1/chat/${ulid}`)
            messages.value = data.data.messages || []
            hasMoreMessages.value = data.data.has_more || false
        } catch (e) {
            messagesError.value = sanitizeErrorMessage(e instanceof Error ? e.message : 'Failed to load messages')
        } finally {
            messagesLoading.value = false
        }
    }

    // Retry helper for the message panel error state.
    async function retryLoadMessages() {
        if (activeConversation.value) {
            await loadMessages(activeConversation.value.ulid)
        }
    }

    async function loadOlderMessages(ulid: string) {
        if (loadingOlder.value || !hasMoreMessages.value || messages.value.length === 0) return

        loadingOlder.value = true
        try {
            const oldestMessage = messages.value[0]
            const data = await apiGet<{ success: boolean; data: { messages: ChatMessage[]; has_more: boolean } }>(
                `/api/v1/chat/${ulid}?before=${oldestMessage.id}`
            )
            const olderMessages = data.data.messages || []
            if (olderMessages.length > 0) {
                messages.value = [...olderMessages, ...messages.value]
            }
            hasMoreMessages.value = data.data.has_more || false
        } catch {} finally {
            loadingOlder.value = false
        }
    }

    function newChat(mode?: ChatMode) {
        selectedMode.value = mode ?? null
        activeConversation.value = null
        messages.value = []
        error.value = ''
        selectedModel.value = mode?.default_model ?? null
        syncChatUrl(null)
    }

    async function selectProject(project: ChatProject | null) {
        selectedProject.value = project
        await loadConversations(project?.id ?? null)
    }

    async function selectConversation(conv: Conversation) {
        activeConversation.value = conv
        messages.value = []
        error.value = ''
        selectedModel.value = conv.model
        if (conv.mode_slug) {
            selectedMode.value = modes.value.find(m => m.slug === conv.mode_slug) ?? null
        }
        syncChatUrl(conv.ulid)
        await loadMessages(conv.ulid)
    }

    async function selectConversationByUlid(ulid: string) {
        const existing = conversations.value.find(conv => conv.ulid === ulid)
        if (existing) {
            await selectConversation(existing)
            return
        }

        pendingConversationUlid.value = ulid
        const fallback: Conversation = {
            ulid,
            title: null,
            mode_slug: null,
            model: null,
            last_message_at: new Date().toISOString(),
            project_id: null,
        }

        activeConversation.value = fallback
        messages.value = []
        error.value = ''
        syncChatUrl(ulid)
        await loadMessages(ulid)
    }

    watch(conversations, (list) => {
        if (!pendingConversationUlid.value) return

        const match = list.find(conv => conv.ulid === pendingConversationUlid.value)
        if (!match) return

        pendingConversationUlid.value = null
        activeConversation.value = match
        selectedModel.value = match.model
        if (match.mode_slug) {
            selectedMode.value = modes.value.find(m => m.slug === match.mode_slug) ?? null
        }
    })

    async function sendMessage(content: string, mode_slug?: string, attachments?: ChatAttachment[]) {
        if (!content.trim() || isStreaming.value) return

        const model = selectedModel.value
            ?? selectedMode.value?.default_model
            ?? defaultChatModel.value
            ?? availableModels.value[0]
            ?? ''

        const userMsg: ChatMessage = {
            id: Date.now(), role: 'user', content,
            input_tokens: 0, output_tokens: 0, credits_charged: 0,
            attachments: attachments?.length ? attachments : undefined,
        }
        messages.value = [...messages.value, userMsg]
        isStreaming.value = true
        error.value = ''

        const assistantMsg: ChatMessage = {
            id: Date.now() + 1, role: 'assistant', content: '',
            input_tokens: 0, output_tokens: 0, credits_charged: 0,
        }
        messages.value = [...messages.value, assistantMsg]

        abortController.value = new AbortController()

        try {
            if (!activeConversation.value) {
                const data = await apiPost<{ success: boolean; data: Conversation }>('/api/v1/chat', {
                    mode_slug: mode_slug ?? selectedMode.value?.slug ?? null,
                    model,
                    project_id: selectedProject.value?.id ?? null,
                })
                activeConversation.value = data.data
                syncChatUrl(data.data.ulid)
            }

            if (!activeConversation.value) {
                isStreaming.value = false
                return
            }

            await streamMessage(
                activeConversation.value.ulid,
                content,
                mode_slug ?? selectedMode.value?.slug ?? undefined,
                model,
                attachments,
                useKnowledgeBase.value,
            )

            await loadConversations(selectedProject.value?.id ?? null)
            await loadMessages(activeConversation.value.ulid)

        } catch (e: any) {
            // A user-initiated stop rejects with a DOMException named 'AbortError'
            // (message "The operation was aborted"), NOT the string 'Aborted' — and a
            // DOMException is not `instanceof Error`. Detect the abort robustly so
            // stopping keeps the partial reply instead of showing "Failed to send message".
            const aborted = e?.name === 'AbortError' || !!abortController.value?.signal.aborted
            if (aborted) {
                // Keep whatever streamed in; drop an empty assistant bubble so a stop
                // before the first token doesn't leave a blank message behind.
                const msgs = [...messages.value]
                const last = msgs[msgs.length - 1]
                if (last && last.role === 'assistant' && !last.content) {
                    msgs.pop()
                    messages.value = msgs
                }
            } else {
                if (e instanceof Error && e.message === 'Unauthorized') {
                    error.value = 'Please sign in to use the chatbot.'
                } else {
                    error.value = sanitizeErrorMessage(e instanceof Error ? e.message : 'Failed to send message')
                }
                const msgs = [...messages.value]
                const last = msgs[msgs.length - 1]
                if (last && last.role === 'assistant' && !last.content) {
                    msgs[msgs.length - 1] = { ...last, content: 'Error: ' + error.value }
                    messages.value = msgs
                }
            }
        } finally {
            isStreaming.value = false
            abortController.value = null
        }
    }

    function stopStreaming() {
        if (abortController.value) {
            abortController.value.abort()
        }
    }

    async function streamMessage(ulid: string, content: string, mode_slug?: string, model?: string, attachments?: ChatAttachment[], use_kb?: boolean) {
        const body: Record<string, unknown> = { content, mode_slug: mode_slug ?? undefined, model: model ?? undefined }
        if (attachments?.length) body.attachments = attachments
        if (use_kb) body.use_knowledge_base = true

        const res = await fetch(`/api/v1/chat/${ulid}/message`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-XSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            signal: abortController.value?.signal,
            body: JSON.stringify(body),
        })

        if (!res.ok) {
            const err = await res.json().catch(() => ({ message: 'Request failed' }))
            throw new Error(err.message || 'Request failed')
        }

        const reader = res.body?.getReader()
        if (!reader) throw new Error('Streaming not supported')

        const decoder = new TextDecoder()
        let buffer = ''

        while (true) {
            const { done, value } = await reader.read()
            if (done) break

            buffer += decoder.decode(value, { stream: true })
            const lines = buffer.split('\n')
            buffer = lines.pop() || ''

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue
                try {
                    const event = JSON.parse(line.slice(6))
                    if (event.type === 'token') {
                        const msgs = [...messages.value]
                        const last = msgs[msgs.length - 1]
                        if (last.role === 'assistant') {
                            msgs[msgs.length - 1] = { ...last, content: last.content + event.content }
                            messages.value = msgs
                        }
                    } else if (event.type === 'usage') {
                        const msgs = [...messages.value]
                        const last = msgs[msgs.length - 1]
                        if (last.role === 'assistant') {
                            msgs[msgs.length - 1] = {
                                ...last,
                                input_tokens: event.input_tokens,
                                output_tokens: event.output_tokens,
                                credits_charged: event.credits,
                                model: event.model,
                            }
                            messages.value = msgs
                        }
                    } else if (event.type === 'kb_sources') {
                        const msgs = [...messages.value]
                        const last = msgs[msgs.length - 1]
                        if (last.role === 'assistant' && event.sources?.length) {
                            msgs[msgs.length - 1] = { ...last, kb_sources: event.sources }
                            messages.value = msgs
                        }
                    } else if (event.type === 'error') {
                        throw new Error(event.message)
                    }
                } catch (e) {
                    if (e instanceof SyntaxError) continue
                    throw e
                }
            }
        }
    }

    async function deleteConversation(ulid: string) {
        await mutate(async () => {
            await apiDelete(`/api/v1/chat/${ulid}`)
            if (activeConversation.value?.ulid === ulid) {
                newChat()
            }
            await loadConversations()
        })
    }

    async function togglePin(ulid: string) {
        await mutate(async () => {
            const data = await apiPut<{ success: boolean; data: Conversation }>(`/api/v1/chat/${ulid}/pin`, {})
            const idx = conversations.value.findIndex(c => c.ulid === ulid)
            if (idx !== -1) conversations.value[idx] = data.data
            if (activeConversation.value?.ulid === ulid) {
                activeConversation.value = data.data
            }
        })
    }

    async function shareConversation(ulid: string): Promise<string> {
        const data = await apiPost<{ success: boolean; data: { share_url: string; share_token: string } }>(`/api/v1/chat/${ulid}/share`, {})
        const idx = conversations.value.findIndex(c => c.ulid === ulid)
        if (idx !== -1) {
            conversations.value[idx] = { ...conversations.value[idx], share_token: data.data.share_token }
        }
        if (activeConversation.value?.ulid === ulid) {
            activeConversation.value = { ...activeConversation.value, share_token: data.data.share_token }
        }
        return data.data.share_url
    }

    async function unshareConversation(ulid: string) {
      await mutate(async () => {
        await apiDelete(`/api/v1/chat/${ulid}/share`)
        const idx = conversations.value.findIndex(c => c.ulid === ulid)
        if (idx !== -1) {
            conversations.value[idx] = { ...conversations.value[idx], share_token: null }
        }
        if (activeConversation.value?.ulid === ulid) {
            activeConversation.value = { ...activeConversation.value, share_token: null }
        }
      })
    }

    async function createProject(name: string, color_hex?: string) {
        const data = await apiPost<{ success: boolean; data: ChatProject }>('/api/v1/chat/projects', { name, color_hex })
        projects.value = [...projects.value, data.data]
        return data.data
    }

    // Fire-and-forget from the sidebar (Enter/blur), so it must not reject: the toast from
    // `failed()` is the whole error story here.
    async function renameProject(id: number, name: string) {
        await mutate(async () => {
            const data = await apiPut<{ success: boolean; data: ChatProject }>(`/api/v1/chat/projects/${id}`, { name })
            const idx = projects.value.findIndex(p => p.id === id)
            if (idx !== -1) projects.value[idx] = data.data
            if (selectedProject.value?.id === id) selectedProject.value = data.data
        })
    }

    async function deleteProject(id: number) {
      await mutate(async () => {
        await apiDelete(`/api/v1/chat/projects/${id}`)
        projects.value = projects.value.filter(p => p.id !== id)
        if (selectedProject.value?.id === id) {
            selectProject(null)
        } else {
            await loadConversations(selectedProject.value?.id ?? null)
        }
      })
    }

    async function moveToProject(convUlid: string, projectId: number | null) {
        await mutate(async () => {
            await apiPut(`/api/v1/chat/${convUlid}`, { project_id: projectId })
            await loadConversations(selectedProject.value?.id ?? null)
        })
    }

    async function renameConversation(convUlid: string, title: string | null) {
        await mutate(async () => {
            await apiPut(`/api/v1/chat/${convUlid}`, { title })
            await loadConversations(selectedProject.value?.id ?? null)
            if (activeConversation.value?.ulid === convUlid) {
                activeConversation.value = {
                    ...activeConversation.value,
                    title,
                }
            }
        })
    }

    async function branchConversation(convUlid: string, messageId: number | string) {
        const data = await apiPost<{ success: boolean; data: Conversation }>(`/api/v1/chat/${convUlid}/branch`, {
            message_id: messageId,
        })
        await loadConversations(selectedProject.value?.id ?? null)
        await selectConversation(data.data)
        return data.data
    }

    async function editMessage(convUlid: string, messageId: number | string, newContent: string) {
        const res = await apiPut<{ success: boolean; data: { message: ChatMessage; should_regenerate: boolean } }>(
            `/api/v1/chat/${convUlid}/message/${messageId}`,
            { content: newContent }
        )

        // Reload messages to reflect the edit and deletion of subsequent messages
        await loadMessages(convUlid)

        // If should_regenerate is true, trigger a new AI response
        if (res.data.should_regenerate) {
            const editedMessage = res.data.message
            await sendMessage(editedMessage.content, selectedMode.value?.slug ?? undefined)
        }

        return res.data
    }

    // Called straight from a template click handler, so it must never reject.
    async function exportConversation(ulid: string, format: string = 'md'): Promise<void> {
        await mutate(async () => {
            const res = await fetch(`/api/v1/chat/${ulid}/export?format=${format}`)
            if (!res.ok) throw await failed(res)
            const blob = await res.blob()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = `conversation-${ulid}.${format}`
            a.click()
            URL.revokeObjectURL(url)
        })
    }

    async function updateChatSettings(customInstructions: string | null): Promise<void> {
        await mutate(() => apiPut('/api/v1/chat/settings', { custom_instructions: customInstructions }))
    }

    const isGuest = !usePage().props.auth?.user
    const allowGuest = (usePage().props.allow_guest_messages as boolean) ?? false

    if (!isGuest) {
        loadModes()
        loadConversations()
        loadProjects()
        loadTags()
    } else if (allowGuest) {
        loadModes()
        loadConversations()
        loadProjects()
        loadTags()
    }

    return {
        modes, conversations, groupedConversations, projects, tags, selectedTag,
        selectedMode, activeConversation, selectedProject, messages,
        isStreaming, loading, error, selectedModel,
        conversationsLoading, conversationsError, messagesLoading, messagesError, retryLoadMessages,
        useKnowledgeBase, kbAvailable,
        newChat, selectConversation, selectProject, sendMessage, stopStreaming,
        loadConversations, loadProjects, loadTags, loadOlderMessages, hasMoreMessages, loadingOlder,
        deleteConversation, togglePin, shareConversation, unshareConversation, createProject, renameProject, deleteProject, moveToProject,
        createTag, updateTag, deleteTag, tagConversation, filterByTag,
        renameConversation, selectConversationByUlid, branchConversation, editMessage, exportConversation,
        updateChatSettings,
    }
}
