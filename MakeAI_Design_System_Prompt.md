# MakeAI — UI Design System Prompt

> For use with any AI code generator (Claude, GPT-4, Cursor, v0.dev)
> Stack: Vue 3 + Tailwind CSS v4 + Inertia.js + Tabler Icons

---

## DESIGN PHILOSOPHY

MakeAI's visual identity is **modern, trustworthy, and energetic** — not generic SaaS gray.
The palette centers on **royal blue (#1F75FE) + charcoal black (#111)**, with clean cards, subtle glass effects,
and a premium feel that justifies the price point on Envato.

Key principles:
- **Blue = trust, AI action, primary brand** (#1F75FE)
- **Black = strength, secondary actions** (#111111)
- **White sidebar** with light content area (admin panel)
- **Clean white cards** with subtle shadows — not flat, not neumorphic, just crisp
- Generous whitespace, consistent 8px grid
- Rounded corners throughout (12px cards, 8px inputs, 6px badges)

---

## COLOR PALETTE

### CSS Custom Properties (injected via `/css/theme-variables.css`)

```css
:root {
  /* === PRIMARY — Royal Blue === */
  --color-primary-50:  #eff6ff;
  --color-primary-100: #dbeafe;
  --color-primary-200: #bfdbfe;
  --color-primary-300: #93c5fd;
  --color-primary-400: #60a5fa;
  --color-primary-500: #1F75FE;   /* main brand blue */
  --color-primary-600: #1a65e0;   /* buttons, active states */
  --color-primary-700: #1554c0;   /* hover */
  --color-primary-800: #1044a0;
  --color-primary-900: #0a3480;

  /* === SECONDARY — Charcoal Black === */
  --color-secondary-50:  #f8f8f8;
  --color-secondary-100: #e8e8e8;
  --color-secondary-200: #c8c8c8;
  --color-secondary-300: #a0a0a0;
  --color-secondary-400: #707070;
  --color-secondary-500: #444444;
  --color-secondary-600: #2a2a2a;   /* secondary buttons */
  --color-secondary-700: #1a1a1a;
  --color-secondary-800: #111111;   /* main secondary — #111 */
  --color-secondary-900: #050505;

  /* === ACCENT — Violet (AI/premium indicator) === */
  --color-accent-400: #a78bfa;
  --color-accent-500: #8b5cf6;
  --color-accent-600: #7c3aed;

  /* === NEUTRALS === */
  --color-gray-50:  #f9fafb;
  --color-gray-100: #f3f4f6;
  --color-gray-200: #e5e7eb;
  --color-gray-300: #d1d5db;
  --color-gray-400: #9ca3af;
  --color-gray-500: #6b7280;
  --color-gray-600: #4b5563;
  --color-gray-700: #374151;
  --color-gray-800: #1f2937;
  --color-gray-900: #111827;

  /* === SEMANTIC === */
  --color-success: #22c55e;
  --color-warning: #f59e0b;
  --color-danger:  #ef4444;
  --color-info:    #1F75FE;

  /* === SURFACES (light mode) === */
  --surface-bg:       #f5f6fa;    /* light neutral page background */
  --surface-card:     #ffffff;
  --surface-input:    #ffffff;
  --surface-sidebar:  #ffffff;   /* white sidebar (light mode) */
  --surface-header:   #ffffff;
  --surface-modal:    #ffffff;

  /* === SURFACES (dark mode) === */
  /* toggled via .dark class on <html> */

  /* === BORDERS === */
  --border-color:      #e5e7eb;
  --border-color-dark: #374151;

  /* === RADIUS === */
  --radius-sm:   6px;
  --radius-md:   8px;
  --radius-lg:   12px;
  --radius-xl:   16px;
  --radius-full: 9999px;

  /* === SHADOWS === */
  --shadow-sm:  0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-md:  0 4px 6px -1px rgb(0 0 0 / 0.07), 0 2px 4px -2px rgb(0 0 0 / 0.05);
  --shadow-lg:  0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.05);
  --shadow-card: 0 1px 3px rgb(0 0 0 / 0.06), 0 1px 2px rgb(0 0 0 / 0.04);
  --shadow-glow-primary: 0 0 20px rgb(31 117 254 / 0.25);
  --shadow-glow-dark:    0 0 20px rgb(17 17 17 / 0.15);
}

.dark {
  --surface-bg:      #0d1117;
  --surface-card:    #161b22;
  --surface-input:   #1c2333;
  --surface-sidebar: #1a1a2e;
  --surface-header:  #161b22;
  --border-color:    #30363d;
}
```

---

## TYPOGRAPHY

```css
/* Font: 'Plus Jakarta Sans' for headings, 'Inter' for body */
/* Load from Google Fonts or self-host */

--font-heading: 'Plus Jakarta Sans', system-ui, sans-serif;
--font-body:    'Inter', system-ui, sans-serif;
--font-mono:    'JetBrains Mono', 'Fira Code', monospace;

/* Scale */
--text-xs:   0.75rem;    /* 12px */
--text-sm:   0.875rem;   /* 14px */
--text-base: 0.9375rem;  /* 15px — slightly tighter than 16px */
--text-lg:   1.0625rem;  /* 17px */
--text-xl:   1.25rem;    /* 20px */
--text-2xl:  1.5rem;     /* 24px */
--text-3xl:  1.875rem;   /* 30px */
--text-4xl:  2.25rem;    /* 36px */
```

---

## COMPONENT DESIGNS

### 1. CARD

Standard white card used everywhere: dashboard stats, tool cards, settings panels.

```vue
<!-- BaseCard.vue -->
<template>
  <div class="card" :class="[variant, { hoverable }]">
    <slot />
  </div>
</template>

<style scoped>
.card {
  background: var(--surface-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);    /* 12px */
  box-shadow: var(--shadow-card);
  padding: 1.25rem 1.5rem;            /* 20px 24px */
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.card.hoverable:hover {
  box-shadow: var(--shadow-lg);
  border-color: var(--color-primary-300);
}
/* Blue accent left border variant */
.card.accent-primary {
  border-left: 3px solid var(--color-primary-500);
}
/* Dark accent variant */
.card.accent-dark {
  border-left: 3px solid var(--color-secondary-800);
}
/* Glass card (for hero sections) */
.card.glass {
  background: rgba(255, 255, 255, 0.72);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.5);
}
</style>
```

**Stat card design:**
- Top-left: icon in colored rounded square (40×40px, color matches stat type)
- Top-right: trend badge (↑12% green or ↓3% red)
- Center: large number (text-3xl, font-bold, font-heading)
- Bottom: label (text-sm, text-gray-500)
- Hover: lifts with shadow + green border highlight

---

### 2. BUTTONS

```vue
<!-- BaseButton.vue — variants: primary | secondary | outline | ghost | danger -->

/* PRIMARY — Royal Blue */
.btn-primary {
  background: var(--color-primary-500);   /* #1F75FE */
  color: white;
  border: none;
  border-radius: var(--radius-md);
  padding: 0.5rem 1.25rem;    /* 8px 20px */
  font-size: var(--text-sm);
  font-weight: 500;
  font-family: var(--font-body);
  box-shadow: 0 1px 2px rgb(0 0 0 / 0.12), 0 0 0 1px var(--color-primary-600);
  transition: all 0.15s ease;
  cursor: pointer;
}
.btn-primary:hover {
  background: var(--color-primary-600);
  box-shadow: var(--shadow-glow-primary), 0 2px 4px rgb(0 0 0 / 0.15);
  transform: translateY(-1px);
}
.btn-primary:active {
  transform: translateY(0);
  box-shadow: none;
}

/* SECONDARY — Charcoal Black */
.btn-secondary {
  background: var(--color-secondary-800);   /* #111111 */
  color: white;
  border: none;
  border-radius: var(--radius-md);
  padding: 0.5rem 1.25rem;
  font-size: var(--text-sm);
  font-weight: 500;
  font-family: var(--font-body);
  transition: all 0.15s ease;
  cursor: pointer;
}
.btn-secondary:hover {
  background: var(--color-secondary-700);
  transform: translateY(-1px);
}

/* OUTLINE — Blue border, transparent bg */
.btn-outline {
  background: transparent;
  border: 1.5px solid var(--color-primary-500);
  color: var(--color-primary-500);
  border-radius: var(--radius-md);
  padding: 0.5rem 1.25rem;
}
.btn-outline:hover {
  background: var(--color-primary-50);
}

/* GHOST — No border, just text */
.btn-ghost {
  background: transparent;
  color: var(--color-gray-600);
  border: none;
  padding: 0.5rem 1rem;
}
.btn-ghost:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-800);
}

/* DANGER */
.btn-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

/* Sizes */
.btn-sm  { padding: 0.375rem 0.875rem; font-size: var(--text-xs); }
.btn-lg  { padding: 0.75rem 1.75rem;   font-size: var(--text-base); }
.btn-xl  { padding: 1rem 2.25rem;      font-size: var(--text-lg); font-weight: 600; }

/* Icon button */
.btn-icon {
  padding: 0.5rem;
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Loading state: spinning icon inside, button dims */
.btn-loading { opacity: 0.75; cursor: not-allowed; pointer-events: none; }
```

---

### 3. FORM INPUTS

```css
/* Base input */
.input {
  width: 100%;
  padding: 0.5rem 0.875rem;          /* 8px 14px */
  background: var(--surface-input);
  border: 1.5px solid var(--border-color);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-family: var(--font-body);
  color: var(--color-gray-800);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  outline: none;
}
.input::placeholder {
  color: var(--color-gray-400);
}
.input:focus {
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 3px rgb(31 117 254 / 0.12);  /* blue ring */
}
.input.error {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px rgb(239 68 68 / 0.10);
}

/* Input group (icon left) */
.input-group {
  position: relative;
}
.input-group .input-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-gray-400);
  pointer-events: none;
}
.input-group .input {
  padding-left: 2.5rem;
}

/* Label */
.label {
  display: block;
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-gray-700);
  margin-bottom: 0.375rem;
}
.label .required { color: var(--color-danger); margin-left: 2px; }

/* Helper text */
.helper-text { font-size: var(--text-xs); color: var(--color-gray-500); margin-top: 0.25rem; }
.error-text   { font-size: var(--text-xs); color: var(--color-danger);   margin-top: 0.25rem; }

/* Select */
.select {
  /* Same as .input + */
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24'%3E%3Cpath fill='%239ca3af' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  padding-right: 2.5rem;
}

/* Toggle / Switch */
.toggle {
  width: 44px; height: 24px;
  background: var(--color-gray-300);
  border-radius: var(--radius-full);
  transition: background 0.2s;
  cursor: pointer;
  position: relative;
}
.toggle.on { background: var(--color-primary-500); }
.toggle::after {
  content: '';
  width: 18px; height: 18px;
  background: white;
  border-radius: 50%;
  position: absolute;
  top: 3px; left: 3px;
  transition: transform 0.2s;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.15);
}
.toggle.on::after { transform: translateX(20px); }

/* Checkbox */
.checkbox {
  width: 16px; height: 16px;
  border: 1.5px solid var(--border-color);
  border-radius: var(--radius-sm);
  cursor: pointer;
  accent-color: var(--color-primary-500);
}
```

---

### 4. BADGES & TAGS

```css
/* Base badge */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.125rem 0.625rem;
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 500;
  white-space: nowrap;
}

/* Color variants */
.badge-primary { background: var(--color-primary-100);   color: var(--color-primary-700); }
.badge-blue    { background: var(--color-secondary-100); color: var(--color-secondary-700); }
.badge-violet  { background: #ede9fe;                    color: #5b21b6; }
.badge-amber   { background: #fef3c7;                    color: #92400e; }
.badge-red     { background: #fee2e2;                    color: #991b1b; }
.badge-gray    { background: var(--color-gray-100);      color: var(--color-gray-600); }

/* Pro badge */
.badge-pro {
  background: linear-gradient(135deg, var(--color-primary-500), var(--color-secondary-800));
  color: white;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 0.125rem 0.5rem;
}

/* Status dot badge */
.badge-dot::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
}
```

---

### 5. ADMIN SIDEBAR

```css
/* Sidebar shell */
.admin-sidebar {
  width: 260px;
  min-height: 100vh;
  background: var(--surface-sidebar);   /* #ffffff — white sidebar */
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease;
  position: fixed;
  left: 0; top: 0;
  z-index: 40;
  overflow: hidden;
}
/* Mini mode */
.admin-sidebar.mini { width: 64px; }

/* Logo area */
.sidebar-logo {
  height: 60px;
  display: flex;
  align-items: center;
  padding: 0 1.25rem;
  border-bottom: 1px solid var(--border-color);
}

/* Nav group label */
.sidebar-group-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--color-gray-400);
  padding: 1rem 1.25rem 0.375rem;
}

/* Nav item */
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1.25rem;
  color: var(--color-gray-600);
  font-size: var(--text-sm);
  font-weight: 450;
  border-radius: 0;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
  position: relative;
}
.sidebar-item:hover {
  color: var(--color-gray-900);
  background: var(--color-gray-100);
}
/* Active state — blue left bar + blue tint */
.sidebar-item.active {
  color: var(--color-primary-500);
  background: rgba(31, 117, 254, 0.08);
}
.sidebar-item.active::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--color-primary-500);
  border-radius: 0 2px 2px 0;
}

/* Icon in sidebar */
.sidebar-item .sidebar-icon {
  width: 18px; height: 18px;
  flex-shrink: 0;
  opacity: 0.8;
}
.sidebar-item.active .sidebar-icon { opacity: 1; color: var(--color-primary-500); }

/* Chevron */
.sidebar-chevron {
  margin-left: auto;
  width: 14px; height: 14px;
  opacity: 0.5;
  transition: transform 0.2s ease;
}
.sidebar-item.open .sidebar-chevron { transform: rotate(180deg); }

/* Submenu */
.sidebar-submenu {
  overflow: hidden;
  max-height: 0;
  transition: max-height 0.25s ease;
}
.sidebar-submenu.open { max-height: 600px; }

.sidebar-subitem {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 1.25rem 0.375rem 3.25rem;  /* indented */
  color: var(--color-gray-500);
  font-size: var(--text-xs);
  cursor: pointer;
  transition: color 0.15s;
}
.sidebar-subitem:hover  { color: var(--color-gray-900); }
.sidebar-subitem.active { color: var(--color-primary-500); }
.sidebar-subitem::before {
  content: '';
  width: 4px; height: 4px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}
```

---

### 6. TABLES (Admin Data Tables)

```css
.data-table-wrapper {
  background: var(--surface-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-card);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.data-table thead {
  background: var(--color-gray-50);
  border-bottom: 1px solid var(--border-color);
}

.data-table th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-gray-500);
  white-space: nowrap;
}
.data-table th.sortable { cursor: pointer; }
.data-table th.sortable:hover { color: var(--color-primary-500); }

.data-table td {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid var(--color-gray-100);
  color: var(--color-gray-700);
  vertical-align: middle;
}

.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover td     { background: #eff6ff; }   /* blue-50 */

/* Selected row */
.data-table tbody tr.selected td { background: #dbeafe; }   /* blue-100 */

/* Checkbox column */
.data-table .col-check { width: 40px; text-align: center; }

/* Actions column */
.data-table .col-actions { text-align: right; white-space: nowrap; }
```

---

### 7. MODALS

```css
/* Backdrop */
.modal-backdrop {
  position: fixed; inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(2px);
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

/* Modal box */
.modal {
  background: var(--surface-modal);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-xl);
  box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
  width: 100%;
  max-width: 540px;
  overflow: hidden;
  animation: modal-in 0.2s ease;
}
@keyframes modal-in {
  from { opacity: 0; transform: scale(0.96) translateY(8px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-title {
  font-size: var(--text-lg);
  font-weight: 600;
  font-family: var(--font-heading);
  color: var(--color-gray-900);
}
.modal-body    { padding: 1.5rem; }
.modal-footer  {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-color);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: var(--color-gray-50);
}

/* Sizes */
.modal.sm  { max-width: 400px; }
.modal.lg  { max-width: 720px; }
.modal.xl  { max-width: 960px; }
.modal.full{ max-width: calc(100vw - 2rem); max-height: calc(100vh - 2rem); }
```

---

### 8. AI TOOL CARDS (User Dashboard)

```vue
<!-- ToolCard.vue -->
<template>
  <div class="tool-card">
    <div class="tool-icon-wrap" :style="{ background: tool.color + '18', color: tool.color }">
      <component :is="tool.icon" size="22" />
    </div>
    <div class="tool-info">
      <div class="tool-name">{{ tool.name }}</div>
      <div class="tool-desc">{{ tool.description }}</div>
    </div>
    <div class="tool-meta">
      <span class="badge badge-green" v-if="tool.is_new">New</span>
      <span class="badge badge-violet" v-if="tool.requires_pro">Pro</span>
      <span class="tool-usage">{{ formatCount(tool.usage_count) }} uses</span>
    </div>
  </div>
</template>

<style scoped>
.tool-card {
  background: var(--surface-card);
  border: 1.5px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1rem 1.25rem;
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
  cursor: pointer;
  transition: all 0.18s ease;
  position: relative;
  overflow: hidden;
}
.tool-card::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, var(--color-primary-500), var(--color-secondary-800));
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}
.tool-card:hover {
  border-color: var(--color-primary-500);
  box-shadow: var(--shadow-md), var(--shadow-glow-primary);
  transform: translateY(-2px);
}
.tool-icon-wrap {
  width: 44px; height: 44px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.tool-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-900);
  margin-bottom: 0.125rem;
}
.tool-desc {
  font-size: var(--text-xs);
  color: var(--color-gray-500);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.tool-meta {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.5rem;
  flex-wrap: wrap;
}
.tool-usage { font-size: 10px; color: var(--color-gray-400); margin-left: auto; }
</style>
```

---

### 9. STAT CARDS (Dashboard)

```css
.stat-card {
  background: var(--surface-card);
  border: 1px solid var(--border-color);
  border-radius: var(--radius-lg);
  padding: 1.25rem 1.5rem;
  box-shadow: var(--shadow-card);
  position: relative;
  overflow: hidden;
}
/* Subtle gradient corner accent */
.stat-card::before {
  content: '';
  position: absolute;
  top: -20px; right: -20px;
  width: 80px; height: 80px;
  border-radius: 50%;
  background: var(--card-accent-color, var(--color-primary-500));
  opacity: 0.07;
}

.stat-icon {
  width: 40px; height: 40px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 0.75rem;
  background: var(--card-accent-color, var(--color-primary-500));
  opacity: 0.12;  /* icon container */
}
/* Actual icon inside has full opacity via separate element */

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  font-family: var(--font-heading);
  color: var(--color-gray-900);
  letter-spacing: -0.02em;
  line-height: 1;
  margin-bottom: 0.25rem;
}
.stat-label {
  font-size: var(--text-xs);
  font-weight: 500;
  color: var(--color-gray-500);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.stat-trend {
  position: absolute;
  top: 1rem; right: 1rem;
  font-size: var(--text-xs);
  font-weight: 600;
  display: flex; align-items: center; gap: 2px;
  padding: 0.125rem 0.5rem;
  border-radius: var(--radius-full);
}
.stat-trend.up   { background: #dcfce7; color: #15803d; }
.stat-trend.down { background: #fee2e2; color: #991b1b; }
```

---

### 10. TOASTR STYLE OVERRIDE

```css
/* Override toastr.js default styles to match MakeAI design */
#toast-container > div {
  border-radius: var(--radius-md) !important;
  box-shadow: var(--shadow-lg) !important;
  padding: 0.875rem 1rem !important;
  font-family: var(--font-body) !important;
  font-size: var(--text-sm) !important;
  opacity: 1 !important;
  backdrop-filter: blur(8px);
}
#toast-container .toast-success {
  background: rgba(34, 197, 94, 0.95) !important;
  border-left: 4px solid #15803d !important;
}
#toast-container .toast-error {
  background: rgba(239, 68, 68, 0.95) !important;
  border-left: 4px solid #991b1b !important;
}
#toast-container .toast-warning {
  background: rgba(245, 158, 11, 0.95) !important;
  border-left: 4px solid #92400e !important;
}
#toast-container .toast-info {
  background: rgba(31, 117, 254, 0.95) !important;
  border-left: 4px solid #1a65e0 !important;
}
```

---

### 11. DARK MODE

All dark mode styles applied via `.dark` class on `<html>`:

```css
.dark .card            { background: #161b22; border-color: #30363d; }
.dark .input           { background: #1c2333; border-color: #30363d; color: #e6edf3; }
.dark .data-table thead{ background: #0d1117; }
.dark .data-table td   { border-color: #21262d; color: #8b949e; }
.dark .data-table tbody tr:hover td { background: rgba(31,117,254,0.07); }
.dark .modal           { background: #161b22; border-color: #30363d; }
.dark .modal-footer    { background: #0d1117; }
.dark .stat-value      { color: #e6edf3; }
.dark .tool-name       { color: #e6edf3; }
.dark .label           { color: #8b949e; }
.dark .sidebar-group-label { color: rgba(255,255,255,0.3); }
```

---

### 12. LOADING & SKELETON STATES

```css
/* Skeleton shimmer */
@keyframes shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position: 400px 0; }
}
.skeleton {
  background: linear-gradient(90deg,
    var(--color-gray-200) 25%,
    var(--color-gray-100) 50%,
    var(--color-gray-200) 75%
  );
  background-size: 800px 100%;
  animation: shimmer 1.4s infinite;
  border-radius: var(--radius-md);
}
.dark .skeleton {
  background: linear-gradient(90deg, #21262d 25%, #30363d 50%, #21262d 75%);
  background-size: 800px 100%;
}

/* Page loading bar (top of page — like NProgress) */
.page-loader {
  position: fixed; top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--color-primary-500), var(--color-secondary-800));
  z-index: 9999;
  animation: page-load 1.2s ease infinite;
}
@keyframes page-load {
  0%   { width: 0%; }
  50%  { width: 70%; }
  100% { width: 100%; opacity: 0; }
}
```

---

### 13. OTP INPUT (6-Box)

```vue
<style scoped>
.otp-wrap {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
}
.otp-box {
  width: 52px; height: 60px;
  text-align: center;
  font-size: 1.5rem;
  font-weight: 700;
  font-family: var(--font-heading);
  border: 2px solid var(--border-color);
  border-radius: var(--radius-md);
  background: var(--surface-input);
  color: var(--color-gray-900);
  transition: border-color 0.15s, box-shadow 0.15s;
  outline: none;
  caret-color: transparent;
}
.otp-box:focus {
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 3px rgb(31 117 254 / 0.15);
}
.otp-box.filled {
  border-color: var(--color-primary-400);
  background: var(--color-primary-50);
  color: var(--color-primary-700);
}
.otp-box.error {
  border-color: var(--color-danger);
  animation: shake 0.4s ease;
}
@keyframes shake {
  0%,100% { transform: translateX(0); }
  20%      { transform: translateX(-6px); }
  40%      { transform: translateX(6px); }
  60%      { transform: translateX(-4px); }
  80%      { transform: translateX(4px); }
}
</style>
```

---

### 14. STREAMING TEXT ANIMATION

```css
/* Blinking cursor while AI is streaming */
.streaming-text::after {
  content: '▋';
  display: inline-block;
  color: var(--color-primary-500);
  animation: blink 0.8s step-end infinite;
  margin-left: 1px;
}
@keyframes blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0; }
}
```

---

### 15. GENERAL LAYOUT RULES

```
Page background:    var(--surface-bg)      — #f5f6fa (light neutral)
Content max-width:  1280px (xl)            — centered with mx-auto px-6
Admin layout:       260px sidebar + flex-1 content
Content padding:    1.5rem (24px)
Section gap:        1.5rem (24px) between cards
Grid cols (admin):  1 / 2 / 3 / 4 depending on viewport
Card padding:       1.25rem 1.5rem (20px 24px)
Input height:       38px (0.5rem padding top+bottom + line-height)
Button height:      36px (default), 32px (sm), 42px (lg)
Sidebar width:      260px (expanded), 64px (mini)
Top header height:  60px (admin), 64px (frontend)
Mobile breakpoint:  1024px (lg) — below this sidebar becomes drawer
```

---

## QUICK REFERENCE — TAILWIND CLASS MAPPING

```
Primary blue:    bg-[#1F75FE]  text-[#1F75FE]  border-[#1F75FE]  ring-[#1F75FE]/20
Secondary dark:  bg-[#111111]  text-[#111111]  border-[#111111]
Accent violet:   bg-violet-500   text-violet-600
Success:         text-green-600    bg-green-50
Warning:         text-amber-600    bg-amber-50
Danger:          text-red-600      bg-red-50
Info:            text-blue-600     bg-blue-50

Card:            bg-white border border-gray-200 rounded-xl shadow-sm
Input focus:     focus:border-[#1F75FE] focus:ring-2 focus:ring-[#1F75FE]/10
Button primary:  bg-[#1F75FE] text-white hover:bg-[#1a65e0]
Button secondary: bg-[#111111] text-white hover:bg-[#1a1a1a]
Sidebar bg:      bg-white border-r border-gray-200
Active item:     text-[#1F75FE] bg-[#1F75FE]/8 border-l-2 border-[#1F75FE]
```

---

*MakeAI Design System v2.0 — Blue/Black palette (#1F75FE / #111), card-first, white sidebar, light default*
