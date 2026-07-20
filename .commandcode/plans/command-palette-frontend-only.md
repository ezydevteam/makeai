# Plan: Command Palette — Frontend-Only with Live-Search Integration

## Summary
Remove the global CommandPalette from the admin panel. On the **frontend**, replace the header's `LiveSearch` component with a simple search trigger input that opens the `CommandPalette` overlay. `Ctrl+K` on frontend opens the CommandPalette.

---

## What Exists Today

| File | Role |
|------|------|
| `resources/js/Components/CommandPalette.vue` | Full-screen command palette overlay (Fuse.js search across tools, docs, nav, etc.) |
| `resources/js/Composables/useCommandPalette.ts` | Composable — state management, Ctrl+K handler, `palette:open` event listener, builds item lists |
| `resources/js/Composables/useKeyboardShortcuts.ts` | Global shortcuts composable — registers `Ctrl+K` → dispatches `palette:open` |
| `resources/js/Components/LiveSearch.vue` | AJAX live search dropdown (fetches `/live-search` endpoint) |
| `resources/js/Components/AppHeader.vue` | Frontend header — has its own `Ctrl+K` handler to open live-search, renders `LiveSearch` |
| `resources/js/Layouts/AdminLayout.vue` | Admin layout — renders `LiveSearch` in the header bar (no CommandPalette import) |
| `resources/js/app.ts` | App entry — mounts `CommandPalette` **globally**, calls `useGlobalShortcuts()` |

**Problem:** `CommandPalette` is mounted globally for all pages (admin + frontend). `useGlobalShortcuts` fires `palette:open` on Ctrl+K everywhere. `AppHeader` also intercepts Ctrl+K to open its own live-search.

---

## Changes Required

### 1. `resources/js/app.ts`
- **Remove** the global `CommandPalette` mount from `render: () => [h(App, props), h(ToastContainer), h(CommandPalette), h(ShortcutsReferenceModal)]`
- **Keep** `useGlobalShortcuts()` — it's needed for other shortcuts (`?`, `Ctrl+Shift+D`, etc.)

### 2. `resources/js/Composables/useKeyboardShortcuts.ts`
- **Remove** the `Ctrl+K` / `palette:open` shortcut from the `useGlobalShortcuts` handlers array.
- This prevents the shortcut from being active globally (including admin).

### 3. `resources/js/Composables/useCommandPalette.ts`
- **Remove** the internal `handleKeydown` listener that captures `Ctrl+K` — it will be handled by `AppHeader` instead.
- **Keep** the `palette:open` event listener (it stays for programmatic open).
- **Keep** everything else (Fuse search, navigation items, admin items, etc.).

### 4. `resources/js/Components/AppHeader.vue`
- **Remove** the `LiveSearch` import and usage in the header (the inline box-style search block).
- **Replace** it with a simple search trigger button that:
  - Shows a search icon + `Ctrl+K` kbd hint
  - On click or `Ctrl+K` press: dispatches `palette:open` → opens the CommandPalette overlay
- **Keep** the `handleKeydown` that listens for `Ctrl+K` — but change it to dispatch `palette:open` instead of focusing the old LiveSearch.
- **Mount** `CommandPalette` locally within `AppHeader` (it's no longer global).
- The search-icon style version (`block.type === 'search'` with `searchStyle !== 'box'`) and mobile search can stay as-is since they already use the icon pattern.

### 5. `resources/js/Layouts/AdminLayout.vue`
- **No changes** — it already doesn't import `CommandPalette`, and after removing the global mount + shortcut, admin won't see the palette at all.
- The `LiveSearch` in admin header stays untouched.

### 6. `resources/js/Components/CommandPalette.vue`
- **No functional changes** needed. It works as-is.
- It must be importable/locally mountable from `AppHeader.vue`.

---

## Verification

1. **Admin panel** — Navigate to any admin page, press `Ctrl+K`. Nothing should happen (no palette opens).
2. **Frontend** — Navigate to any frontend page. Press `Ctrl+K` or click the new search trigger in the header. The CommandPalette overlay opens.
3. **Type in palette** — Search for tools, documents, navigate with arrow keys, press Enter to go to a result.
4. **Other shortcuts** — `?` (shortcuts ref), `Ctrl+Shift+D` (dark mode), `Ctrl+/` (focus search) still work.
5. **Mobile** — Mobile search icon still triggers the mobile live search overlay (unchanged).
6. **Non-search header blocks** — Menu, user menu, CTA buttons all render normally.
