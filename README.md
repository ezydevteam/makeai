# MakeAI — One Platform. Every AI Tool.

A premium, production-ready AI SaaS platform script designed for seamless white-labeling, high monetization, and extreme performance. Built for Envato CodeCanyon.

---

## 🏛️ Architecture & Tech Stack

MakeAI is engineered on a modern, decoupled architecture designed to deliver extreme speed, modularity, and scalability:

| Component | Technology | Description |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel 12+ (PHP 8.3+) | High-performance enterprise-ready MVC architecture. |
| **Frontend Framework** | Vue 3 (Composition API) + TypeScript | Type-safe, reactive components for an ultra-smooth UX. |
| **SSR / Routing** | Inertia.js (Node SSR Enabled) | Fast client-side routing combined with SEO-friendly Server-Side Rendering. |
| **Design / Styling** | Tailwind CSS v4 | Cutting-edge, utility-first styling for rich dark-modes and dynamic animations. |
| **AI Framework** | Laravel AI SDK (`laravel/ai`) | Powering advanced Retrieval-Augmented Generation (RAG) and Agentic structures. |
| **WebSockets** | Laravel Reverb + Laravel Echo | Zero-config, first-party, high-performance real-time communication. |
| **Queues / Caching** | Redis + Laravel Horizon | Seamless job execution, background streaming, and high-throughput caching. |
| **Payments** | Stripe, PayPal, Razorpay, Paddle, CoinGate, SSLCommerz | Comprehensive multi-gateway monetization out of the box. |

---

## ✨ Core Product Highlights

### ⚡ White-Label & Custom Branding
Every brand asset—app name, tagline, description, light/dark mode logo, favicon, primary theme colors, copyright details, and SEO metadata—is dynamic and fully controllable from the Admin Panel. Zero hardcoded assets.

### 🤖 Laravel AI SDK Core
- **RAG & Vector Storage**: Native support for semantic search and document indexing.
- **Multiple Providers**: Pre-integrated with OpenAI, Anthropic, Gemini, and local LLMs.
- **250+ AI Templates**: Ready-to-go templates covering text, images, video, speech-to-text, TTS, and conversational AI.

### 🛡️ Sliding-Window Rate Limiting
Custom Redis-backed sliding-window rate limiting. Ensures security and prevents boundary-burst issues. Customizable limits are defined per user plan (Guest, Free, and Pro Tiers) directly in the Admin dashboard.

### 🎟️ Envato License Gateway
Built-in license check validating Envato Regular and Extended Licenses. Automatically gates premium features such as subscription plans and payments, providing a secure foundation for digital sellers.

### 💬 Passwordless & Secure Auth
A robust, OTP-only (One-Time Password) auth system. No static passwords or magic link dependencies. Features multi-digit inputs, temporary email validation, and adaptive security locks.

### 🎨 Customizable Builders
Dynamic builders for custom pages, homepages, collapsible admin sidebars, headers, and footers. Built-in blog system, dynamic FAQs, and customer testimonials manager.

---

## 🚀 Quick Start Guide

### Prerequisites
Ensure your local environment meets the following specifications:
- **PHP** >= 8.3
- **Node.js** >= 20.x
- **Redis** >= 6.x
- **MySQL** >= 8.0

### Installation

1. **Clone & Setup Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Run the Installer Wizard**
   Launch your local web server (e.g., Laragon, Herd, or artisan serve) and visit:
   ```http
   http://localhost/install
   ```
   *The installer checks system requirements, manages database credentials, registers your Envato license, and initializes your administrative profile.*

3. **Development Command**
   We package custom concurrently runners to coordinate servers, logs, queues, and assets:
   ```bash
   composer run dev
   ```

4. **Production Build**
   ```bash
   npm run build
   ```

---

## 🦾 Agentic Development

MakeAI is designed to be highly compatible with AI coding agents (Claude Code, Cursor, Windsurf, etc.). We include robust, auto-synced rule files `.windsurfrules`, `CLAUDE.md`, and `AGENT.md` that sync memory databases automatically.

Install the development helper tools:
```bash
composer require laravel/boost --dev
php artisan boost:install
```

---

## 📄 License

MakeAI is commercial software sold through Envato Market (CodeCanyon). Use is
governed by the Envato Regular or Extended Licence purchased with it — see
[LICENSE](LICENSE). It is not open-source.
