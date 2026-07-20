import { computed, onMounted, onUnmounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Fuse from 'fuse.js'

export interface PaletteItem {
    id: string
    group: string
    label: string
    sublabel?: string
    icon?: string
    color?: string
    action: () => void
}

const RECENT_KEY = 'cmd_palette_recent'

function getRecent(): string[] {
    try {
        return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]')
    } catch {
        return []
    }
}

function saveRecent(id: string) {
    try {
        const recent = getRecent().filter(x => x !== id)
        recent.unshift(id)
        localStorage.setItem(RECENT_KEY, JSON.stringify(recent.slice(0, 5)))
    } catch {}
}

export function useCommandPalette() {
    const query = ref('')
    const isOpen = ref(false)
    const selectedIndex = ref(0)
    const page = usePage()

    const user = computed(() => (page.props.auth as any)?.user ?? null)
    const isAdmin = computed(() => !!(page.props.auth as any)?.admin)
    const props = computed(() => page.props as any)

    const authProps = computed(() => (page.props.auth ?? {}) as any)

    const tools = computed<{ name: string; slug: string; description: string; icon: string; color: string; category?: string }[]>(() => {
        return authProps.value.paletteTools ?? []
    })

    const recentDocs = computed<{ id: number; title: string; tool_slug?: string }[]>(() => {
        return authProps.value.paletteDocuments ?? []
    })

    const recentChats = computed<{ id: number; ulid: string; title: string }[]>(() => {
        return authProps.value.paletteChats ?? []
    })

    const navItems: PaletteItem[] = [
        { id: 'nav-dashboard', group: 'Navigation', label: 'Dashboard', icon: 'ti ti-dashboard', action: () => router.visit('/user/dashboard') },
        { id: 'nav-tools', group: 'Navigation', label: 'AI Tools', icon: 'ti ti-tools', action: () => router.visit('/ai-tools') },
        { id: 'nav-documents', group: 'Navigation', label: 'Documents', icon: 'ti ti-file-text', action: () => router.visit('/user/dashboard/documents') },
        { id: 'nav-history', group: 'Navigation', label: 'History', icon: 'ti ti-history', action: () => router.visit('/user/dashboard/history') },
        { id: 'nav-usage', group: 'Navigation', label: 'My Usage', icon: 'ti ti-chart-bar', action: () => router.visit('/user/dashboard/usage') },
        { id: 'nav-playground', group: 'Navigation', label: 'Playground', icon: 'ti ti-building-factory', action: () => router.visit('/user/dashboard/playground') },
        { id: 'nav-chains', group: 'Navigation', label: 'Tool Chains', icon: 'ti ti-link', action: () => router.visit('/user/dashboard/chains') },
        { id: 'nav-favorites', group: 'Navigation', label: 'Favorites', icon: 'ti ti-star', action: () => router.visit('/user/dashboard/favorites') },
        { id: 'nav-collections', group: 'Navigation', label: 'Collections', icon: 'ti ti-books', action: () => router.visit('/user/dashboard/collections') },
        { id: 'nav-settings', group: 'Navigation', label: 'Settings', icon: 'ti ti-settings', action: () => router.visit('/user/dashboard/profile') },
        { id: 'nav-chat', group: 'Navigation', label: 'Chat', icon: 'ti ti-message-circle', action: () => router.visit('/chat') },
    ]

    const quickActions: PaletteItem[] = [
        { id: 'action-copy-last', group: 'Actions', label: 'Copy last output', icon: 'ti ti-copy', action: () => {
            const outputs = document.querySelectorAll('[data-last-output]')
            if (outputs.length > 0) {
                navigator.clipboard.writeText((outputs[0] as HTMLElement).innerText).catch(() => {})
            }
        }},
        { id: 'action-dark-mode', group: 'Actions', label: 'Toggle dark mode', icon: 'ti ti-moon', action: () => {
            const html = document.documentElement
            html.classList.toggle('dark')
            fetch('/profile/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ theme: html.classList.contains('dark') ? 'dark' : 'light' }),
            }).catch(() => {})
        }},
        { id: 'action-new-doc', group: 'Actions', label: 'New document', icon: 'ti ti-file-plus', action: () => router.visit('/ai-tools') },
        { id: 'action-new-chat', group: 'Actions', label: 'New chat', icon: 'ti ti-message-plus', action: () => router.visit('/chat') },
        { id: 'action-shortcuts', group: 'Actions', label: 'View all shortcuts', icon: 'ti ti-keyboard', action: () => window.dispatchEvent(new CustomEvent('shortcuts:show')) },
    ]

    const adminItems: PaletteItem[] = isAdmin.value ? [
        { id: 'admin-dashboard', group: 'Admin', label: 'Admin Dashboard', icon: 'ti ti-shield', action: () => router.visit('/admin/dashboard') },
        { id: 'admin-users', group: 'Admin', label: 'Manage Users', icon: 'ti ti-users', action: () => router.visit('/admin/users') },
        { id: 'admin-tools', group: 'Admin', label: 'AI Tools', icon: 'ti ti-tools', action: () => router.visit('/admin/ai/tools') },
        { id: 'admin-settings', group: 'Admin', label: 'Admin Settings', icon: 'ti ti-settings-cog', action: () => router.visit('/admin/settings/general') },
    ] : []

    function buildItems(): PaletteItem[] {
        const items: PaletteItem[] = [...quickActions, ...navItems, ...adminItems]

        for (const t of tools.value) {
            items.push({
                id: `tool-${t.slug}`,
                group: 'AI Tools',
                label: t.name,
                sublabel: t.category ?? t.description,
                icon: t.icon,
                color: t.color,
                action: () => {
                    saveRecent(`tool-${t.slug}`)
                    router.visit(`/ai-tools/${t.slug}`)
                },
            })
        }

        for (const d of recentDocs.value.slice(0, 20)) {
            items.push({
                id: `doc-${d.id}`,
                group: 'Recent Documents',
                label: d.title,
                sublabel: d.tool_slug,
                icon: 'ti ti-file-text',
                action: () => {
                    saveRecent(`doc-${d.id}`)
                    router.visit(`/documents/${d.id}/edit`)
                },
            })
        }

        for (const c of recentChats.value.slice(0, 10)) {
            items.push({
                id: `chat-${c.id}`,
                group: 'Recent Chats',
                label: c.title || 'Untitled',
                sublabel: c.ulid,
                icon: 'ti ti-message-circle',
                action: () => {
                    saveRecent(`chat-${c.id}`)
                    router.visit(`/chat/${c.ulid}`)
                },
            })
        }

        const recentIds = getRecent()
        const score = (item: PaletteItem) => {
            const idx = recentIds.indexOf(item.id)
            return idx === -1 ? 999 : idx
        }

        items.sort((a, b) => {
            const ga = a.group === 'Actions' ? 1 : a.group === 'Recent Documents' ? 3 : a.group === 'Recent Chats' ? 4 : a.group === 'AI Tools' ? 5 : 2
            const gb = b.group === 'Actions' ? 1 : b.group === 'Recent Documents' ? 3 : b.group === 'Recent Chats' ? 4 : b.group === 'AI Tools' ? 5 : 2
            if (ga !== gb) return ga - gb
            return score(a) - score(b)
        })

        return items
    }

    const allItems = computed(() => buildItems())

    const fuse = computed(() => new Fuse(allItems.value, {
        keys: ['label', 'sublabel', 'group'],
        threshold: 0.4,
        distance: 100,
    }))

    const results = computed<PaletteItem[]>(() => {
        const q = query.value.trim()
        if (!q) {
            const grouped = new Map<string, PaletteItem[]>()
            for (const item of allItems.value) {
                if (!grouped.has(item.group)) grouped.set(item.group, [])
                grouped.get(item.group)!.push(item)
            }
            const out: PaletteItem[] = []
            for (const [group, items] of grouped) {
                out.push({ id: `header-${group}`, group, label: group, icon: '', action: () => {}, sublabel: `${items.length} items` } as any)
                out.push(...items.slice(0, 12))
            }
            return out
        }
        return fuse.value.search(q).map(r => r.item)
    })

    const flatItems = computed(() => results.value)

    function open() {
        isOpen.value = true
        query.value = ''
        selectedIndex.value = 0
    }

    function close() {
        isOpen.value = false
    }

    function selectNext() {
        if (selectedIndex.value < flatItems.value.length - 1) selectedIndex.value++
    }

    function selectPrev() {
        if (selectedIndex.value > 0) selectedIndex.value--
    }

    function execute() {
        const item = flatItems.value[selectedIndex.value]
        if (item && item.action) {
            saveRecent(item.id)
            item.action()
            close()
        }
    }

    function handleKeydown(e: KeyboardEvent) {
        if (!isOpen.value) return
        if (e.key === 'Escape') { e.preventDefault(); close(); return }
        if (e.key === 'ArrowDown') { e.preventDefault(); selectNext(); return }
        if (e.key === 'ArrowUp') { e.preventDefault(); selectPrev(); return }
        if (e.key === 'Enter') { e.preventDefault(); execute(); return }
        if (e.key === '?') {
            e.preventDefault()
            close()
            window.dispatchEvent(new CustomEvent('shortcuts:show'))
            return
        }
    }

    function onPaletteOpen() {
        open()
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown)
        window.addEventListener('palette:open', onPaletteOpen)
    })

    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown)
        window.removeEventListener('palette:open', onPaletteOpen)
    })

    return { query, isOpen, selectedIndex, results, flatItems, open, close, selectNext, selectPrev, execute }
}
