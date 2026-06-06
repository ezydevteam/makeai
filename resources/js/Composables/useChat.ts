import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export interface ChatProduct {
    id: number; slug: string; name: string; icon: string; color_hex: string
    system_prompt: string; preferred_models: string[]; default_model: string
    starter_prompts: string[]
}

export interface Conversation {
    ulid: string; title: string | null; product_slug: string | null
    model: string | null; last_message_at: string; project_id: number | null
}

export interface ChatMessage {
    id: number | string; role: 'user' | 'assistant'; content: string
    model?: string; input_tokens: number; output_tokens: number; credits_charged: number
    created_at?: string
}

export interface ChatProject {
    id: number; name: string; color_hex: string | null; conversations_count: number
}

function csrf(): string {
    const cookie = document.cookie.match('(^|;)\\s*XSRF-TOKEN\\s*=\\s*([^;]+)')
    return cookie ? decodeURIComponent(cookie.pop() || '') : ''
}

function friendlyError(res: Response): Error {
    if (res.status === 401) return new Error('Unauthorized')
    if (res.status === 403) return new Error('Forbidden')
    if (res.status === 429) return new Error('Too many requests')
    return new Error(`HTTP ${res.status}`)
}

async function apiGet<T>(url: string): Promise<T> {
    const res = await fetch(url, { headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() }, credentials: 'same-origin' })
    if (!res.ok) throw friendlyError(res)
    return res.json()
}

async function apiPost<T>(url: string, body?: Record<string, unknown>): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : undefined,
    })
    if (!res.ok) throw friendlyError(res)
    return res.json()
}

async function apiDelete(url: string): Promise<void> {
    await fetch(url, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        credentials: 'same-origin',
    })
}

async function apiPut<T>(url: string, body: Record<string, unknown>): Promise<T> {
    const res = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    })
    if (!res.ok) throw friendlyError(res)
    return res.json()
}

export function useChat() {
    const products = ref<ChatProduct[]>([])
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

    const defaultChatModel = (usePage().props.default_chat_model as string) || 'gpt-4o-mini'
    const allowModelSelect = (usePage().props.allow_model_select as boolean) ?? true

    const selectedProduct = ref<ChatProduct | null>(null)
    const activeConversation = ref<Conversation | null>(null)
    const selectedProject = ref<ChatProject | null>(null)
    const messages = ref<ChatMessage[]>([])
    const isStreaming = ref(false)
    const loading = ref(false)
    const error = ref('')
    const selectedModel = ref<string | null>(allowModelSelect ? null : defaultChatModel)
    const abortController = ref<AbortController | null>(null)

    async function loadProducts() {
        try { const data = await apiGet<{ success: boolean; data: ChatProduct[] }>('/api/v1/chat/products'); products.value = data.data } catch {}
    }
    async function loadConversations(projectId?: number | null) {
        try {
            const url = projectId ? `/api/v1/chat?project_id=${projectId}` : '/api/v1/chat'
            const data = await apiGet<{ success: boolean; data: Conversation[] }>(url)
            conversations.value = data.data
        } catch {}
    }
    async function loadProjects() {
        try { const data = await apiGet<{ success: boolean; data: ChatProject[] }>('/api/v1/chat/projects'); projects.value = data.data } catch {}
    }

    async function loadMessages(ulid: string) {
        try {
            const data = await apiGet<{ success: boolean; data: { messages: ChatMessage[] } }>(`/api/v1/chat/${ulid}`)
            messages.value = data.data.messages || []
        } catch {}
    }

    function newChat(product?: ChatProduct) {
        selectedProduct.value = product ?? null
        activeConversation.value = null
        messages.value = []
        error.value = ''
        selectedModel.value = product?.default_model ?? null
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
        if (conv.product_slug) {
            selectedProduct.value = products.value.find(p => p.slug === conv.product_slug) ?? null
        }
        await loadMessages(conv.ulid)
    }

    async function sendMessage(content: string, product_slug?: string) {
        if (!content.trim() || isStreaming.value) return

        const model = selectedModel.value
            ?? selectedProduct.value?.default_model
            ?? (usePage().props.default_chat_model as string)
            ?? 'gpt-4o-mini'

        const userMsg: ChatMessage = {
            id: Date.now(), role: 'user', content,
            input_tokens: 0, output_tokens: 0, credits_charged: 0,
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
                    product_slug: product_slug ?? selectedProduct.value?.slug ?? null,
                    model,
                    project_id: selectedProject.value?.id ?? null,
                })
                activeConversation.value = data.data
            }

            if (!activeConversation.value) {
                isStreaming.value = false
                return
            }

            await streamMessage(
                activeConversation.value.ulid,
                content,
                product_slug ?? selectedProduct.value?.slug ?? undefined,
                model,
            )

            await loadConversations(selectedProject.value?.id ?? null)
            await loadMessages(activeConversation.value.ulid)

        } catch (e) {
            if (e instanceof Error && e.message === 'Aborted') {
                // user cancelled, leave partial message
            } else if (e instanceof Error && e.message === 'Unauthorized') {
                error.value = 'Please sign in to use the chatbot.'
            } else {
                error.value = e instanceof Error ? e.message : 'Failed to send message'
            }
            const msgs = [...messages.value]
            const last = msgs[msgs.length - 1]
            if (last.role === 'assistant' && !last.content) {
                msgs[msgs.length - 1] = { ...last, content: 'Error: ' + error.value }
                messages.value = msgs
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

    async function streamMessage(ulid: string, content: string, product_slug?: string, model?: string) {
        const res = await fetch(`/api/v1/chat/${ulid}/message`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-XSRF-TOKEN': csrf(),
            },
            credentials: 'same-origin',
            signal: abortController.value?.signal,
            body: JSON.stringify({ content, product_slug: product_slug ?? undefined, model: model ?? undefined }),
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
        await apiDelete(`/api/v1/chat/${ulid}`)
        if (activeConversation.value?.ulid === ulid) {
            newChat()
        }
        await loadConversations()
    }

    async function createProject(name: string, color_hex?: string) {
        const data = await apiPost<{ success: boolean; data: ChatProject }>('/api/v1/chat/projects', { name, color_hex })
        projects.value = [...projects.value, data.data]
        return data.data
    }

    async function renameProject(id: number, name: string) {
        const data = await apiPut<{ success: boolean; data: ChatProject }>(`/api/v1/chat/projects/${id}`, { name })
        const idx = projects.value.findIndex(p => p.id === id)
        if (idx !== -1) projects.value[idx] = data.data
        if (selectedProject.value?.id === id) selectedProject.value = data.data
    }

    async function deleteProject(id: number) {
        await apiDelete(`/api/v1/chat/projects/${id}`)
        projects.value = projects.value.filter(p => p.id !== id)
        if (selectedProject.value?.id === id) {
            selectProject(null)
        } else {
            await loadConversations(selectedProject.value?.id ?? null)
        }
    }

    async function moveToProject(convUlid: string, projectId: number | null) {
        await apiPut(`/api/v1/chat/${convUlid}`, { project_id: projectId })
        await loadConversations(selectedProject.value?.id ?? null)
    }

    const isGuest = !usePage().props.auth?.user
    const allowGuest = (usePage().props.allow_guest_messages as boolean) ?? false

    if (!isGuest) {
        loadProducts()
        loadConversations()
        loadProjects()
    } else if (allowGuest) {
        loadProducts()
        loadConversations()
        loadProjects()
    }

    return {
        products, conversations, groupedConversations, projects,
        selectedProduct, activeConversation, selectedProject, messages,
        isStreaming, loading, error, selectedModel,
        newChat, selectConversation, selectProject, sendMessage, stopStreaming,
        loadConversations, loadProjects,
        deleteConversation, createProject, renameProject, deleteProject, moveToProject,
    }
}
