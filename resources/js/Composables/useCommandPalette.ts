import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Fuse from 'fuse.js'
import { t } from '@/Composables/useTranslate'

export interface PaletteItem {
    id: string
    group: string
    label: string
    sublabel?: string
    icon?: string
    color?: string
    action: () => void
}

export interface PaletteTab {
    key: string
    label: string
    icon: string
    /** How many of the currently matching items live in this tab. */
    count: number
}

/**
 * Tab definitions, in display order. `groups` is the set of item groups a tab
 * owns; `null` means "everything" (the All tab). A tab whose groups have no
 * loaded items at all is dropped from the bar — e.g. Admin for a normal user.
 *
 * Group names and `label` stay in English: they are lookup keys (and translation
 * source strings). The view translates them at render time.
 */
const TAB_DEFS: { key: string; label: string; icon: string; groups: string[] | null }[] = [
    { key: 'all', label: 'All', icon: 'ti ti-layout-grid-add', groups: null },
    { key: 'actions', label: 'Actions', icon: 'ti ti-bolt', groups: ['Actions'] },
    { key: 'navigation', label: 'Navigate', icon: 'ti ti-compass', groups: ['Navigation'] },
    { key: 'tools', label: 'AI Tools', icon: 'ti ti-sparkles', groups: ['AI Tools'] },
    { key: 'documents', label: 'Documents', icon: 'ti ti-file-text', groups: ['Recent Documents'] },
    { key: 'chats', label: 'Chats', icon: 'ti ti-message-circle', groups: ['Recent Chats'] },
    { key: 'admin', label: 'Admin', icon: 'ti ti-shield', groups: ['Admin'] },
]

/** Section headers are injected as pseudo-items; they are never selectable. */
export function isSectionHeader(item: PaletteItem | undefined): boolean {
    return !!item && item.id.startsWith('header-')
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
    const activeTab = ref('all')
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
        { id: 'nav-dashboard', group: 'Navigation', label: t('Dashboard'), icon: 'ti ti-dashboard', action: () => router.visit('/user/dashboard') },
        { id: 'nav-tools', group: 'Navigation', label: t('AI Tools'), icon: 'ti ti-tools', action: () => router.visit('/ai-tools') },
        { id: 'nav-documents', group: 'Navigation', label: t('Documents'), icon: 'ti ti-file-text', action: () => router.visit('/user/dashboard/documents') },
        { id: 'nav-history', group: 'Navigation', label: t('History'), icon: 'ti ti-history', action: () => router.visit('/user/dashboard/history') },
        { id: 'nav-usage', group: 'Navigation', label: t('My Usage'), icon: 'ti ti-chart-bar', action: () => router.visit('/user/dashboard/usage') },
        { id: 'nav-playground', group: 'Navigation', label: t('Playground'), icon: 'ti ti-building-factory', action: () => router.visit('/user/dashboard/playground') },
        { id: 'nav-chains', group: 'Navigation', label: t('Tool Chains'), icon: 'ti ti-link', action: () => router.visit('/user/dashboard/chains') },
        { id: 'nav-favorites', group: 'Navigation', label: t('Favorites'), icon: 'ti ti-star', action: () => router.visit('/user/dashboard/favorites') },
        { id: 'nav-collections', group: 'Navigation', label: t('Collections'), icon: 'ti ti-books', action: () => router.visit('/user/dashboard/collections') },
        { id: 'nav-settings', group: 'Navigation', label: t('Settings'), icon: 'ti ti-settings', action: () => router.visit('/user/dashboard/profile') },
        { id: 'nav-chat', group: 'Navigation', label: t('Chat'), icon: 'ti ti-message-circle', action: () => router.visit('/chat') },
    ]

    const quickActions: PaletteItem[] = [
        { id: 'action-copy-last', group: 'Actions', label: t('Copy last output'), icon: 'ti ti-copy', action: () => {
            const outputs = document.querySelectorAll('[data-last-output]')
            if (outputs.length > 0) {
                navigator.clipboard.writeText((outputs[0] as HTMLElement).innerText).catch(() => {})
            }
        }},
        { id: 'action-dark-mode', group: 'Actions', label: t('Toggle dark mode'), icon: 'ti ti-moon', action: () => {
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
        { id: 'action-new-doc', group: 'Actions', label: t('New document'), icon: 'ti ti-file-plus', action: () => router.visit('/ai-tools') },
        { id: 'action-new-chat', group: 'Actions', label: t('New chat'), icon: 'ti ti-message-plus', action: () => router.visit('/chat') },
        { id: 'action-shortcuts', group: 'Actions', label: t('View all shortcuts'), icon: 'ti ti-keyboard', action: () => window.dispatchEvent(new CustomEvent('shortcuts:show')) },
    ]

    const adminItems: PaletteItem[] = isAdmin.value ? [
        { id: 'admin-dashboard', group: 'Admin', label: t('Admin Dashboard'), icon: 'ti ti-shield', action: () => router.visit('/admin/dashboard') },
        { id: 'admin-users', group: 'Admin', label: t('Manage Users'), icon: 'ti ti-users', action: () => router.visit('/admin/users') },
        { id: 'admin-tools', group: 'Admin', label: t('AI Tools'), icon: 'ti ti-tools', action: () => router.visit('/admin/ai/tools') },
        { id: 'admin-settings', group: 'Admin', label: t('Admin Settings'), icon: 'ti ti-settings-cog', action: () => router.visit('/admin/settings/general') },
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
                label: c.title || t('Untitled'),
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

    /** Everything matching the current query, still ungrouped and untabbed. */
    const matchedItems = computed<PaletteItem[]>(() => {
        const q = query.value.trim()
        return q ? fuse.value.search(q).map(r => r.item) : allItems.value
    })

    function itemsForTab(items: PaletteItem[], key: string): PaletteItem[] {
        const def = TAB_DEFS.find(t => t.key === key)
        if (!def || !def.groups) return items
        return items.filter(item => def.groups!.includes(item.group))
    }

    /**
     * Counts track the *query matches*, but a tab stays in the bar as long as it
     * has any loaded item — otherwise the bar would reshuffle on every keystroke.
     */
    const tabs = computed<PaletteTab[]>(() =>
        TAB_DEFS
            .filter(def => !def.groups || itemsForTab(allItems.value, def.key).length > 0)
            .map(def => ({
                key: def.key,
                label: def.label,
                icon: def.icon,
                count: itemsForTab(matchedItems.value, def.key).length,
            })),
    )

    const results = computed<PaletteItem[]>(() => {
        const items = itemsForTab(matchedItems.value, activeTab.value)

        // Only the unfiltered All tab needs group headers — every other view is a
        // single group already, and search results are ranked, not grouped.
        if (activeTab.value !== 'all' || query.value.trim()) return items

        const grouped = new Map<string, PaletteItem[]>()
        for (const item of items) {
            if (!grouped.has(item.group)) grouped.set(item.group, [])
            grouped.get(item.group)!.push(item)
        }

        const out: PaletteItem[] = []
        for (const [group, groupItems] of grouped) {
            out.push({
                id: `header-${group}`,
                group,
                label: group,
                icon: '',
                action: () => {},
                sublabel: t('One item|:count items', { count: groupItems.length }),
            } as any)
            out.push(...groupItems.slice(0, 6))
        }
        return out
    })

    const flatItems = computed(() => results.value.filter(item => !isSectionHeader(item)))

    /** Total matches across every tab — drives the "try another tab" hint. */
    const totalMatches = computed(() => matchedItems.value.length)

    function firstSelectable(): number {
        return results.value.findIndex(item => !isSectionHeader(item))
    }

    // Any change to what is on screen re-anchors the cursor to the first real row.
    watch([query, activeTab], () => {
        selectedIndex.value = firstSelectable()
    })

    function open() {
        isOpen.value = true
        query.value = ''
        activeTab.value = 'all'
        selectedIndex.value = firstSelectable()
    }

    function close() {
        isOpen.value = false
    }

    function setTab(key: string) {
        if (tabs.value.some(tab => tab.key === key)) activeTab.value = key
    }

    function cycleTab(step: number) {
        const list = tabs.value
        if (list.length < 2) return
        const current = list.findIndex(tab => tab.key === activeTab.value)
        const next = (current + step + list.length) % list.length
        activeTab.value = list[next].key
    }

    function selectNext() {
        const list = results.value
        for (let i = selectedIndex.value + 1; i < list.length; i++) {
            if (!isSectionHeader(list[i])) { selectedIndex.value = i; return }
        }
    }

    function selectPrev() {
        const list = results.value
        for (let i = selectedIndex.value - 1; i >= 0; i--) {
            if (!isSectionHeader(list[i])) { selectedIndex.value = i; return }
        }
    }

    function execute() {
        const item = results.value[selectedIndex.value]
        if (item && item.action && !isSectionHeader(item)) {
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
        // Tab walks the tab bar; arrows only do so while the query is empty, so
        // they stay available for editing the search text.
        if (e.key === 'Tab') { e.preventDefault(); cycleTab(e.shiftKey ? -1 : 1); return }
        if (!query.value && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
            e.preventDefault()
            cycleTab(e.key === 'ArrowRight' ? 1 : -1)
            return
        }
        // Only a shortcut while nothing is typed — otherwise "?" belongs in the query.
        if (e.key === '?' && !query.value) {
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

    return {
        query, isOpen, selectedIndex, activeTab, tabs, results, flatItems, totalMatches,
        open, close, setTab, cycleTab, selectNext, selectPrev, execute,
    }
}
