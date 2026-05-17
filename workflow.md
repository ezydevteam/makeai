# MakeAI — Development Workflow

> **Inspired by:** [MagicAI](https://codecanyon.net/item/magicai-openai-content-code-chat-image-generator-as-saas/45408109) from CodeCanyon
> **Master Prompt:** [`AI_SaaS_Master_Prompt.md`](AI_SaaS_Master_Prompt.md) — the **single source of truth** for all specs, tables, and architecture
> **Stack:** PHP 8.3+ · Laravel 12 · LLPhant · Inertia.js (SSR) · Vue 3 Composition API · Tailwind CSS v4 · MySQL · Redis · Laravel Horizon

> [!IMPORTANT]
> **Every phase MUST cross-reference the Master Prompt before implementation.**
> Use the Part numbers below to locate exact specifications (table schemas, interfaces, helpers, UI specs).

---

## Build Philosophy

1. **Foundation first** — database, auth, settings, helpers must exist before any feature
2. **Admin before user** — admin panel controls everything, so it must be built first
3. **Core AI engine before AI tools** — provider registry, token guard, LLPhant service are shared
4. **One feature at a time** — each phase produces a testable, working increment
5. **No hardcoded strings** — use `translate()` helper from day 1

---

## Phase Overview

| Phase | Name | Description | Dependencies | Master Prompt |
|-------|------|-------------|--------------|---------------|
| **0** | Project Scaffold | Laravel 12 + Inertia + Vue 3 + Tailwind v4 setup | None | Part 7, 11 |
| **1** | Foundation Layer | Settings, helpers, cache, DB schema, base models | Phase 0 | Part 2 |
| **2** | Admin Auth & RBAC | Admin model, guard, login, roles, permissions | Phase 1 | Part 3.1, 3.2 |
| **3** | Admin Panel Shell | Layout, sidebar, dashboard (empty cards), navigation | Phase 2 | Part 3.3, 3.4 |
| **4** | User Auth System | Registration, login, social auth, 2FA, email verify | Phase 1 | Part 4 |
| **5** | User Dashboard Shell | Layout, sidebar, profile, basic pages | Phase 4 | Part 4.3 |
| **6** | AI Engine Core | ProviderRegistry, LLPhantService, TokenGuard, StreamService | Phase 1 | Part 5 |
| **7** | AI Writer | Templates, editor (Tiptap), brand voice, export | Phase 5, 6 | Part 5.4 (AI Writer) |
| **8** | AI Chat Pro | Multi-model chat, history, file attach, memory | Phase 5, 6 | Part 5.4 (AI Chat) |
| **9** | AI Image Generator | DALL-E, SD, Flux integration, gallery | Phase 5, 6 | Part 5.4 (Image) |
| **10** | AI Code Generator | Code gen, explain, debug, run sandbox | Phase 5, 6 | Part 5.4 (Code) |
| **11** | AI Voice & Audio | TTS, STT, voice clone, music | Phase 5, 6 | Part 5.4 (Voice) |
| **12** | AI Video Generator | Sora, Kling, Veo integration | Phase 5, 6 | Part 5.4 (Video) |
| **13** | Knowledge Base (RAG) | Document upload, chunking, embedding, chat | Phase 5, 6 | Part 5.4 (RAG) |
| **14** | Chatbot Builder | Custom bots, training, embed widget, CRM | Phase 13 | Part 5.4 (Chatbot) |
| **15** | Subscription System | Plans, gateways, billing (Extended License only) | Phase 4, 3 | Part 6 |
| **16** | Theme & Addon System | Theme loader, addon loader, settings.json | Phase 3 | Part 2.2 |
| **17** | Admin AI Management | Provider keys, model settings, cost tracking | Completed ✅ | Part 3.4 (AI Mgmt) |
| **18** | Admin User Management | User CRUD, impersonate, credits, export | Completed ✅ | Part 3.4 (Users) |
| **19** | Translation System | Languages, translations table, admin UI | Completed ✅ | Part 2.5 |
| **20** | Currency System | Currencies, exchange rates, formatting | Completed ✅ | Part 2.6 |
| **21** | Community Features | Newsletter, comments, favorites (Inertia/Vue) | Completed ✅ | Part 13 |
| **22** | CMS & Pages | Page builder, contact form, system pages | Completed ✅ | Part 18 |
| **23** | Menu & Appearance | Menu builder, header/footer builder, colors | Completed ✅ | Part 16, 17 |
| **24** | Ads System | Ad zones, AdSense, tracking | Completed ✅ | Part 14 |
| **25** | Social Features | Share buttons, follow counters | Completed ✅ | Part 20 |
| **26** | AI Workflow Builder | Visual node editor (Vue Flow), triggers | ⏸️ Deferred | Part 5.4 (Workflow) |
| **27** | AI Article Wizard | Step-by-step article generator | ⏸️ Deferred | Part 5.4 (Article) |
| **28** | AI Social Media Suite | Social posting, scheduling, analytics | ⏸️ Deferred | Part 5.4 (Social) |
| **29** | AI Plagiarism Detector | Copyscape/GPTZero integration | ⏸️ Deferred | Part 5.4 (Plagiarism) |
| **30** | AI Presentation Maker | Slide generator, PPTX export | ⏸️ Deferred | Part 5.4 (Presentation) |
| **31** | Admin System Tools | Health monitor, cache, cron, maintenance, updates | Completed ✅ | Part 15 |
| **32** | Demo Mode | Demo seeder, DemoMode middleware, reset | Completed ✅ | Part 21 |
| **33** | Mail System | SMTP, Mailgun, SES, Templates, Editor | Completed ✅ | Part 22 |
| **34** | API (REST) | Sanctum, /api/v1/ endpoints, OpenAPI docs | ⏸️ Deferred | Part 8.3 |
| **35** | Testing & Polish | PestPHP tests, performance, security audit | ⏸️ Deferred |
| **36** | Envato Submission | Build, docs, screenshots, video, zip | ⏸️ Deferred |

---

## Phase Details

### Phase 0 — Project Scaffold
```
Tasks:
- [x] Create Laravel 12 project via composer
- [ ] Install & configure Inertia.js with SSR
- [ ] Install Vue 3 + TypeScript
- [ ] Install Tailwind CSS v4
- [ ] Install Pinia, VueUse
- [ ] Configure MySQL database
- [ ] Configure Redis (cache + queue + session)
- [ ] Install Laravel Horizon
- [ ] Set up base directory structure (see Part 11 of master prompt)
- [ ] Configure composer.json autoload for Helpers
- [ ] Install Tiptap editor packages
- [ ] Install Chart.js
- [ ] Install toastr.js
```

### Phase 1 — Foundation Layer
```
Tasks:
- [ ] Create settings migration + model + cache layer
- [ ] Create settings() and settings_set() helpers
- [ ] Create helpers.php with translate(), format_currency(), credit helpers
- [ ] Create license.php helpers (stubs for now)
- [ ] Create base config files (ai.php, license.php, addons.php)
- [ ] Set up HandleInertiaRequests middleware (shared props)
- [ ] Set up toastr composable + flash integration
```

### Phase 2 — Admin Auth & RBAC
```
Tasks:
- [ ] Create admins migration + Admin model
- [ ] Create admin_roles, admin_permissions, admin_role_permissions migrations
- [ ] Create Admin auth guard + provider in config/auth.php
- [ ] Create AdminAuth middleware
- [ ] Create AdminPermission middleware
- [ ] Create admin login controller + view
- [ ] Create admin 2FA (TOTP + OTP) support
- [ ] Create permission seeder (all permission groups)
- [ ] Create HasRBAC trait for Admin model
```

### Phase 3 — Admin Panel Shell
```
Tasks:
- [ ] Create AdminLayout.vue (sidebar + header + content)
- [ ] Create admin sidebar navigation component
- [ ] Create admin dashboard page with empty stat cards
- [ ] Create StatsCard, Chart components
- [ ] Create admin DataTable component (reusable)
- [ ] Wire up admin routing (Inertia)
- [ ] Add AdminCan Vue directive
```

---

## Architecture Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Admin auth | Separate guard & model | Complete isolation from user auth |
| State management | Pinia | Official Vue 3 state management |
| Rich text editor | Tiptap | Extensible, Vue 3 native, AI sidebar support |
| Charts | Chart.js | Lightweight, well-supported |
| CSS framework | Tailwind v4 | Utility-first, theme-able |
| Realtime community | Livewire v3 | SEO-friendly, no separate Vue build |
| AI library | LLPhant | PHP-native RAG, agents, embeddings |
| Queue | Horizon | Dashboard, monitoring, Redis-backed |
| API auth | Sanctum | Token-based, built into Laravel |
| Public IDs | ULID | Non-sequential, URL-safe |

---

## Key Conventions

1. All user-facing strings → `translate('key')`
2. All settings → `settings('key')` helper (cached via Redis)
3. All API keys → encrypted with `Crypt::encryptString()`
4. All AI requests → must pass through `TokenGuard`
5. All admin routes → `auth:admin` + `AdminPermission` middleware
6. All user IDs in URLs → ULID, never auto-increment
7. All Vue components → `<script setup lang="ts">` (Composition API)
8. All forms → server-side validation primary, client-side for UX only
9. All queued jobs → separate queues: `default`, `ai`, `emails`, `webhooks`

---

*Last updated: 2026-05-13*
*Current Phase: 15 — Subscription System (complete, fixing alignment with Master Prompt)*

---

## Master Prompt Cross-Reference

| Master Prompt Part | Workflow Phase(s) | Key Specs |
|-------------------|-------------------|----------|
| Part 1 — Envato License | Phase 33 | LicenseService, install wizard, anti-nulling |
| Part 2 — Foundation | Phase 1, 16, 19, 20 | Settings, themes/addons, translate(), currencies |
| Part 3 — Admin Panel | Phase 2, 3, 17, 18 | Admin auth, RBAC, dashboard, user/AI management |
| Part 4 — User Model & Auth | Phase 4, 5 | Users table (full spec), credit_transactions, login_history |
| Part 5 — AI Engine | Phase 6–14 | ProviderRegistry, TokenGuard, all AI modules |
| Part 6 — Subscriptions | Phase 15 | Plans, PaymentGatewayInterface, coupons, gating |
| Part 7 — Frontend | Phase 0, all | Inertia SSR, Vue 3, layouts, components, dark mode |
| Part 8 — Architecture | Phase 0, 34 | Horizon queues, Redis caching, API routes, security |
| Part 9 — Migrations | All | Migration ordering |
| Part 10 — Seeders | All | Default data on install |
| Part 11 — File Structure | All | Directory conventions |
| Part 13 — Community | Phase 21 | Newsletter, comments, favorites (Livewire) |
| Part 14 — Ads | Phase 24 | Ad zones, AdSense, targeting |
| Part 15 — System Tools | Phase 31 | Health, cache, cron, maintenance, updates |
| Part 16 — Appearance | Phase 23 | Colors, typography, container, CSS variables |
| Part 17 — Menu & Page Builder | Phase 22, 23 | Menu builder, header/footer, sidebar |
| Part 18 — CMS & Pages | Phase 22 | Page builder, system pages, contact form |
| Part 20 — Social | Phase 25 | Share buttons, follow counters |
| Part 21 — Demo Mode | Phase 32 | DemoMode middleware, DemoSeeder |
