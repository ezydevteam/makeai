# AI Tools — Catalog Audit & Expansion Proposal

_makeai · prepared 2026-07-10 · scope: text-generation tool catalog_

---

## 1. Current inventory

| Surface | Count | Where | Notes |
|---|---|---|---|
| Text-generation tools | **280** | `database/seeders/AiToolSeeder.php` (19 categories) | LLM prompt tools, guided forms |
| Document AI (RAG) | **8** | `database/seeders/RagToolSeeder.php` | Chat-with-PDF/Doc/Sheet/Site/YouTube, compare, summarize, KB-write |
| Utility engines (non-LLM) | 4 types | `UtilityToolRunner` + `config/external-tools.php` | grammar, plagiarism, AI-detector, translation |
| **Core total** | **288 tools** | | |
| Addons (separate products) | — | `addons/*` | Voiceover, Image Editor, Video Creator, Repurposer, Chatbot, Knowledge Base |

### The 19 text categories (verified tool counts)

Blog & Content (23) · Social Media (32) · Advertising (18) · Email Marketing (17) · Ecommerce (17) · Business (20) · Academic (21) · Development (19) · Website & SEO (21) · Creative Writing (14) · Personal & Career (10) · Health & Fitness (7) · Real Estate (5) · Entertainment (8) · Language (7) · Marketing Strategy (14) · Customer Support (8) · Legal & Finance (7) · Productivity (12) — **= 280**.

**Verdict:** the catalog is broad and mature for content marketing. The real gaps are in **B2B sales workflows, HR/recruiting, legal document coverage, AI‑art prompting, SaaS/product‑launch copy, and podcasting** — all high-demand on competing CodeCanyon scripts (MagicAI, AIKeedo, Nova), and all cheap, single-output text tools that fit the existing seeder pattern with **zero new infrastructure**.

---

## 2. How the two license tiers map to tools

Tools are already gated by `access_level` (`guest` / `login` / `premium` / `plan:*`). Crucially:

- Under a **Regular license**, `isProAvailable()` is false → `premium`/`plan:*` tools resolve to *"not available on this site."* So Regular installs should ship a large **free** catalog (`guest`/`login`).
- Under an **Extended license**, subscriptions turn on → `premium` tools become the **upsell** that justifies a paid plan.

So the proposal is tiered:

- **🆓 Regular tier** — light, broadly useful, single-output, low token cost. Access `guest`/`login`. Works on every install; grows the free catalog and the demo's perceived value.
- **🔒 Extended tier** — heavier / long-form / high-value B2B documents. Access `premium`. Meaningful only where subscriptions exist, so they double as the reason to upgrade.

Tier is a **default suggestion** — every tool stays admin-editable, and the seeder *already* auto-assigns `pro_plan` to `*-analysis` tools in business/legal/dev, so this simply extends an established convention.

---

## 3. Proposed new categories

Four net-new categories (no overlap with the existing 19); everything else folds into current categories.

| Category | Slug | Icon | Rationale |
|---|---|---|---|
| Sales | `sales` | `ti ti-businessplan` | Outbound/closing workflows; today only cold-email/sales-email exist, scattered |
| HR & Recruiting | `hr-recruiting` | `ti ti-users-group` | Hiring, onboarding, people-ops; today only `job-description` |
| Podcast & Audio | `podcast` | `ti ti-microphone-2` | Fast-growing medium with zero coverage today |
| AI Prompts | `ai-prompts` | `ti ti-sparkles` | One of the highest-traffic tool types on competitor sites; pairs with the Image Editor addon |

---

## 4. Proposed tools (77 new)

**Legend:** 🔒 = **Extended** (premium) tier · everything else = **Regular** (free, `guest`/`login`).
All slugs below are checked collision-free against the existing 288. Fields reuse the standard guided-form schema unless a tool obviously needs a paste/textarea input (e.g. Email Reply, Case Study From Interview, Meeting Summary).

### Sales
| Tool | Slug | Description |
|---|---|---|
| Cold Call Script | `cold-call-script` | Pre-written phone script customized for your target buyer persona |
| Sales Proposal 🔒 | `sales-proposal` | Proposal document with pricing, terms, and customized implementation plan |
| Objection Handling | `objection-handling` | Tested rebuttals converting prospect concerns into buying decisions |
| Discovery Call Questions | `discovery-call-questions` | Strategic conversation guides uncovering buyer pain and budget authority |
| LinkedIn Outreach DM | `linkedin-outreach-dm` | Personalized LinkedIn direct message designed to book sales meetings |
| Sales Follow-Up | `sales-follow-up` | Follow-up sequence re-engaging prospects at optimal timing |
| Sales Pitch | `sales-pitch` | Compelling 30-second elevator pitch emphasizing measurable customer outcomes |
| Upsell & Cross-Sell Script | `upsell-crosssell-script` | Conversation framework identifying expansion and upsell pathways |
| RFP Response 🔒 | `rfp-response` | Winning proposal response highlighting competitive advantages and value |
| Case Study From Interview | `case-study-interview` | Success story extracting client wins from interview transcripts |

### HR & Recruiting
| Tool | Slug | Description |
|---|---|---|
| Job Ad | `job-ad` | Compelling job posting optimized for talent sourcing and applications |
| Interview Questions (Hiring) | `interview-questions-hiring` | Role-specific behavioral questions identifying top performers and cultural fit |
| Offer Letter | `offer-letter` | Professional employment offer with compensation package and start details |
| Candidate Rejection Email | `candidate-rejection-email` | Professional rejection maintaining relationship and future-opportunity door |
| Performance Review 🔒 | `performance-review` | Structured feedback detailing achievements, gaps, and growth goals |
| Employee Onboarding Email | `employee-onboarding-email` | Welcome sequence covering first-day logistics and company culture |
| Employee Handbook Section 🔒 | `employee-handbook-section` | Policy documentation addressing benefits, expectations, and compliance |
| Promotion Announcement | `promotion-announcement` | Celebratory message announcing an employee's new role and impact |
| Reference Check Questions | `reference-check-questions` | Targeted questions gathering verified performance and culture-fit insights |
| Recruiter Outreach Message | `recruiter-outreach` | Compelling message attracting passive candidates to career opportunities |

### Podcast & Audio
| Tool | Slug | Description |
|---|---|---|
| Podcast Episode Outline | `podcast-outline` | Structured episode blueprint with talking points and segment timing |
| Podcast Show Notes | `podcast-show-notes` | SEO-optimized episode summary with timestamps and key takeaways |
| Podcast Title Ideas | `podcast-title-ideas` | Searchable episode titles designed for listener interest and discovery |
| Podcast Description | `podcast-description` | Compelling show description attracting listeners and improving discoverability |
| Podcast Interview Questions | `podcast-interview-questions` | Engaging conversation starters drawing out guest expertise naturally |
| Podcast Episode Summary | `podcast-episode-summary` | Quick recap highlighting key insights and shareable takeaways |

### AI Prompts
| Tool | Slug | Description |
|---|---|---|
| Midjourney Prompt | `midjourney-prompt` | Detailed visual prompt optimized for Midjourney's image generation |
| Stable Diffusion Prompt | `stable-diffusion-prompt` | Technical prompt syntax maximizing Stable Diffusion model performance |
| DALL·E Prompt | `dalle-prompt` | Natural-language prompt crafted for DALL·E image generation |
| ChatGPT Prompt Optimizer | `chatgpt-prompt-optimizer` | Refine vague queries into precise prompts for better responses |
| System Prompt Generator | `system-prompt-generator` | Foundational prompt defining an AI agent's personality and behavior |
| Negative Prompt Generator | `negative-prompt-generator` | Exclusion terms preventing unwanted elements in AI images |

### Naming & Branding _(→ marketing-strategy)_
| Tool | Slug | Description |
|---|---|---|
| Business Name Generator | `business-name-generator` | Unique, marketable business names aligned with your industry vision |
| Brand Name Generator | `brand-name-generator` | Distinctive brand names standing out in your market |
| Slogan Generator | `slogan-generator` | Catchy slogans capturing your unique value proposition |
| Domain Name Ideas | `domain-name-ideas` | Available-sounding domain suggestions with quality alternatives |
| Startup Name Generator | `startup-name-generator` | Tech-forward startup names signaling innovation and market position |
| Tagline Generator | `tagline-generator` | Memorable taglines reinforcing brand identity and promise |

### SaaS & Product _(→ business / website-seo)_
| Tool | Slug | Description |
|---|---|---|
| App Store Description | `app-store-description` | Conversion-optimized app description highlighting features and benefits |
| Release Notes | `release-notes` | Professional feature-release documentation driving user adoption |
| Changelog Generator | `changelog-generator` | Version update notes highlighting improvements and features |
| Product Hunt Launch | `product-hunt-launch` | Launch post maximizing Product Hunt visibility and engagement |
| Feature Announcement | `feature-announcement` | Buzz-generating announcement marketing a new product capability |
| Push Notification Copy | `push-notification-copy` | Concise, compelling notifications driving daily app engagement |
| Pricing Page Copy | `pricing-page-copy` | Persuasive pricing narrative justifying value and driving conversion |
| Onboarding Email Sequence 🔒 | `onboarding-email-sequence` | Multi-step welcome series guiding new users to success |

### Legal expansion _(→ legal-finance)_
| Tool | Slug | Description |
|---|---|---|
| NDA Generator 🔒 | `nda-generator` | Legally sound non-disclosure agreement protecting confidential information |
| Cookie Policy | `cookie-policy` | Compliance-ready policy disclosing all tracking practices clearly |
| Refund Policy | `refund-policy` | Clear refund terms protecting customer rights and revenue |
| Return Policy | `return-policy` | Structured return procedures balancing satisfaction and efficiency |
| GDPR Statement | `gdpr-statement` | Privacy compliance statement addressing EU regulations |
| Affiliate Disclosure | `affiliate-disclosure` | FTC-compliant affiliate disclosure ensuring marketing transparency |
| Service Agreement 🔒 | `service-agreement` | Professional service contract defining scope and obligations |
| Shipping Policy | `shipping-policy` | Clear shipping terms covering costs, timelines, and liability |

### Video expansion _(→ social-media / creative)_
| Tool | Slug | Description |
|---|---|---|
| YouTube Script | `youtube-script` | Engaging on-camera script with natural pacing and hooks |
| YouTube Chapters | `youtube-chapters` | Timestamped chapter markers improving viewer navigation and SEO |
| Video Sales Letter 🔒 | `video-sales-letter` | Persuasive on-camera pitch converting viewers into customers |
| Explainer Video Script | `explainer-video-script` | Clear script breaking down complex concepts visually |
| Video Hook Generator | `video-hook-generator` | Opening lines capturing viewer attention in seconds |
| Shorts / Reels Script | `shorts-script` | Viral-optimized short-form script for TikTok and Reels |

### Ecommerce expansion _(→ ecommerce)_
| Tool | Slug | Description |
|---|---|---|
| Etsy Listing | `etsy-listing` | SEO-optimized Etsy product listing with keywords included |
| eBay Listing | `ebay-listing` | Listing with persuasive copy and search positioning |
| Product Bullet Points | `product-bullet-points` | Benefit-focused feature bullets converting browsers into buyers |
| Product Size Guide | `size-guide` | Accurate sizing reference reducing returns and confusion |
| Image Alt Text | `image-alt-text` | SEO and accessibility descriptions for product images |

### SEO expansion _(→ website-seo)_
| Tool | Slug | Description |
|---|---|---|
| People Also Ask | `people-also-ask` | FAQ section capturing long-tail search queries |
| Featured Snippet Optimizer | `featured-snippet` | Content structure targeting Google featured-snippet positions |
| SEO Content Audit 🔒 | `seo-content-audit` | Competitive analysis revealing content gaps and opportunities |
| Topic Cluster Planner 🔒 | `topic-cluster-planner` | Content strategy mapping pillar and cluster topics |

### Productivity & Personal _(→ productivity)_
| Tool | Slug | Description |
|---|---|---|
| Email Reply Generator | `email-reply-generator` | Quick professional responses to a pasted email |
| Meeting Summary From Notes | `meeting-summary-notes` | Concise recap with action items and owners |
| Daily Standup Update | `daily-standup` | Brief status update highlighting progress and blockers |
| Journaling Prompts | `journaling-prompts` | Reflective questions sparking personal and professional growth |

### Startup & Fundraising _(→ business)_
| Tool | Slug | Description |
|---|---|---|
| Elevator Pitch | `elevator-pitch` | Compelling 60-second pitch attracting investor interest |
| One-Line Pitch | `one-line-pitch` | Memorable one-liner capturing your core value proposition |
| Lean Canvas | `lean-canvas` | Single-page business-model canvas for strategy iteration |
| YC Application Answers 🔒 | `yc-application` | Compelling responses to Y Combinator application questions |

---

## 5. Totals & tier split

| | Count |
|---|---|
| New tools proposed | **77** |
| — 🆓 Regular (free) | 66 |
| — 🔒 Extended (premium upsell) | 11 |
| New categories | 4 (Sales, HR & Recruiting, Podcast & Audio, AI Prompts) |
| **Core catalog after** | **280 → 357 text tools** (+ 8 RAG + utilities) |

**The 11 Extended (premium) tools:** Sales Proposal, RFP Response, Performance Review, Employee Handbook Section, Onboarding Email Sequence, NDA Generator, Service Agreement, Video Sales Letter, SEO Content Audit, Topic Cluster Planner, YC Application Answers. These are the long-form or high-value B2B documents that most credibly justify a subscription on an Extended install.

---

## 6. Implementation notes (no new infrastructure)

Every proposal is a standard `type = text` prompt tool — it drops straight into the existing seeder pipeline:

1. **Categories** — add the 4 new rows to `AiToolCategorySeeder::run()` (`[name, slug, icon, color, description]`), matching the existing `updateOrCreate(['slug','type'=>'ai_tool'])` shape.
2. **Tools** — add each slug array to the matching key in `AiToolSeeder::catalog()`; add friendly names to `nameFor()` where `Str::headline($slug)` isn't ideal (e.g. `dalle-prompt` → "DALL·E Prompt", `rfp-response` → "RFP Response").
3. **Guard counter** — bump `$expectedTotal` in `AiToolSeeder::run()` (currently `280`) by the number added, or the seeder throws by design.
4. **Tier** — the 🔒 tools should seed with `access_level = 'premium'` (or `pro_plan`, consistent with the existing `*-analysis` convention). Extend the `access_level` ternary in `payload()`, or set it per-slug. All others inherit (`guest`/`login`).
5. **Paste-input tools** — `email-reply-generator`, `case-study-interview`, `meeting-summary-notes`, `podcast-episode-summary`, and the AI-Prompt optimizers benefit from a leading `textarea` "source text" field; add a small `fieldsFor()` branch (same pattern already used for `development` and `language`).
6. **Fields/prompts, SEO, About/FAQ** — auto-generated by the existing `payload()` helpers; no manual authoring required. Featured flags via `featuredSlugs()`.
7. **Run** — `php artisan db:seed --class=AiToolCategorySeeder && php artisan db:seed --class=AiToolSeeder` (idempotent `updateOrCreate`; `ToolCatalogCacheService::invalidateAll()` runs automatically).

> These are proposals only — no code has been changed. Say the word and I can wire any subset (or all 77) into the seeders with the tier defaults above, then run the suite.
