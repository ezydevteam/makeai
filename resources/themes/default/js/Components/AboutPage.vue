<script setup lang="ts">
/**
 * The About page, rendered from the CMS page's own content.
 *
 * Nothing here is hardcoded copy: the hero reads the page's title and excerpt, the body is
 * the page's HTML, and the numbers and quotes come from this install's tables. What the
 * component adds is structure — it reads the content the way a designer would and lays it
 * out accordingly:
 *
 *   <h2>            starts a new section and earns an entry in the sticky index
 *   <ul> of <li>    every item opening with <strong> becomes a card grid
 *   <ol> of <li>    the same, but rendered as a milestone timeline
 *   anything else   stays ordinary prose
 *
 * Those four are all plain rich-text constructs, so a buyer can restructure the page in the
 * admin editor without knowing the rules exist — bold the lead-in of a list item and it
 * becomes a card; unbold it and it goes back to being a list.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

type CtaLink = { label: string; href: string } | null
type Stat = { icon: string; label: string; value: string }
type Quote = { name: string; role: string; avatar: string | null; content: string; rating: number }
type AboutData = { stats: Stat[]; testimonials: Quote[]; cta: { primary: CtaLink; secondary: CtaLink } }

type Item = { title: string; body: string }
type Block =
    | { kind: 'html'; html: string }
    | { kind: 'cards'; items: Item[] }
    | { kind: 'timeline'; items: Item[] }
type Section = { id: string; title: string; blocks: Block[] }

const props = defineProps<{
    page: any
    about?: AboutData | null
    /** Page content with any shortcode block already stripped out (those render separately). */
    content: string
}>()

const { t } = useTranslate()
const siteName = computed(() => (usePage().props as any).appName ?? '')

const cta = computed(() => props.about?.cta ?? { primary: null, secondary: null })
const stats = computed<Stat[]>(() => props.about?.stats ?? [])
const quotes = computed<Quote[]>(() => props.about?.testimonials ?? [])

const slugify = (value: string): string =>
    value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'section'

/**
 * A list becomes cards/timeline only when EVERY item leads with bold text — one stray
 * plain item and the whole list stays a list, which keeps the rule predictable rather
 * than leaving a grid with a hole in it.
 */
const listItems = (list: Element): Item[] | null => {
    const items = Array.from(list.children).filter((child) => child.tagName === 'LI')

    if (items.length < 2) return null

    const parsed: Item[] = []

    for (const item of items) {
        const lead = item.firstElementChild
        const leadText = lead?.textContent?.trim() ?? ''

        // Read only — the list still has to render as plain markup if a later item fails
        // this check, and stripping the lead-ins as we went would silently eat them.
        if (!lead || !['STRONG', 'B'].includes(lead.tagName) || leadText === '') return null

        const full = (item.textContent ?? '').trim()
        // Whatever the author typed between title and body (an em dash, a colon) was
        // punctuation joining the two, and has no job once they are separate elements.
        const body = full.slice(full.indexOf(leadText) + leadText.length).replace(/^[\s—–:-]+/, '').trim()

        parsed.push({ title: leadText, body })
    }

    return parsed
}

const sections = computed<Section[]>(() => {
    const html = props.content ?? ''

    // No DOMParser during SSR — fall back to the untouched content rather than a blank page.
    if (typeof DOMParser === 'undefined' || html.trim() === '') {
        return html.trim() === '' ? [] : [{ id: '', title: '', blocks: [{ kind: 'html', html }] }]
    }

    const body = new DOMParser().parseFromString(html, 'text/html').body
    const parsed: Section[] = []
    let current: Section = { id: '', title: '', blocks: [] }
    const seen = new Set<string>()

    const pushHtml = (markup: string) => {
        const last = current.blocks[current.blocks.length - 1]
        if (last?.kind === 'html') last.html += markup
        else current.blocks.push({ kind: 'html', html: markup })
    }

    const close = () => {
        if (current.title !== '' || current.blocks.length > 0) parsed.push(current)
    }

    for (const node of Array.from(body.children)) {
        if (node.tagName === 'H2') {
            close()

            // Anchors have to be unique for the index links to land in the right place.
            let id = slugify(node.textContent ?? '')
            let suffix = 2
            while (seen.has(id)) id = `${slugify(node.textContent ?? '')}-${suffix++}`
            seen.add(id)

            current = { id, title: (node.textContent ?? '').trim(), blocks: [] }
            continue
        }

        if (node.tagName === 'UL' || node.tagName === 'OL') {
            const items = listItems(node)

            if (items) {
                current.blocks.push({ kind: node.tagName === 'OL' ? 'timeline' : 'cards', items })
                continue
            }
        }

        pushHtml(node.outerHTML)
    }

    close()

    return parsed
})

/** Sticky "on this page" nav — only worth showing once there are a few places to go. */
const index = computed(() => sections.value.filter((section) => section.title !== ''))
const showIndex = computed(() => index.value.length >= 3)

/**
 * Display number for a section, counted over the titled ones only — content sitting above
 * the first <h2> is an intro, not chapter one.
 */
const sectionNumber = (section: Section): string =>
    String(index.value.indexOf(section) + 1).padStart(2, '0')

// Written out rather than interpolated so Tailwind sees the class names.
const STAT_COLUMNS: Record<number, string> = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-3',
}
const statColumns = computed(() => STAT_COLUMNS[stats.value.length] ?? 'md:grid-cols-4')

const activeId = ref('')

// Card accents cycle through the palette. Written out in full because Tailwind reads this
// file as text — a class assembled at runtime would never make it into the stylesheet.
const ACCENTS = [
    'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400',
    'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
    'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
    'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
    'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
    'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
]
const accent = (i: number): string => ACCENTS[i % ACCENTS.length]

const rootRef = ref<HTMLElement | null>(null)
let gsapCtx: any = null
let observer: IntersectionObserver | null = null

onMounted(() => {
    // Highlights the index entry for whichever section is in view.
    if (showIndex.value && typeof IntersectionObserver !== 'undefined') {
        observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((entry) => entry.isIntersecting)
                if (visible.length > 0) activeId.value = visible[0].target.id
            },
            { rootMargin: '-96px 0px -70% 0px' },
        )
        rootRef.value?.querySelectorAll('[data-about-section]').forEach((el) => observer!.observe(el))
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    import('gsap').then(async ({ gsap }) => {
        const { ScrollTrigger } = await import('gsap/ScrollTrigger')
        gsap.registerPlugin(ScrollTrigger)

        gsapCtx = gsap.context(() => {
            gsap.utils.toArray<HTMLElement>('.about-reveal').forEach((el) => {
                gsap.from(el, {
                    opacity: 0,
                    y: 32,
                    duration: 0.7,
                    ease: 'power2.out',
                    scrollTrigger: { trigger: el, start: 'top 88%', once: true },
                })
            })

            // Count up to the number in the label, keeping whatever unit follows it ("2.4K").
            gsap.utils.toArray<HTMLElement>('.about-counter').forEach((el, i) => {
                const raw = el.getAttribute('data-value') ?? ''
                const match = raw.match(/^([\d.]+)(.*)$/)
                if (!match) return

                const target = parseFloat(match[1])
                const dot = match[1].indexOf('.')
                const decimals = dot === -1 ? 0 : match[1].length - dot - 1
                const counter = { value: 0 }

                gsap.to(counter, {
                    value: target,
                    duration: 1.6,
                    delay: i * 0.1,
                    ease: 'power2.out',
                    onUpdate: () => { el.textContent = counter.value.toFixed(decimals) + match[2] },
                    scrollTrigger: { trigger: el, start: 'top 92%', once: true },
                })
            })
        }, rootRef.value!)
    })
})

onUnmounted(() => {
    gsapCtx?.revert()
    observer?.disconnect()
})
</script>

<template>
    <div ref="rootRef" class="bg-white dark:bg-surface-950">

        <!-- ─── Hero ─────────────────────────────────────────────────── -->
        <section class="relative isolate overflow-hidden border-b border-gray-100 dark:border-surface-800">
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute -left-20 -top-32 h-[28rem] w-[28rem] rounded-full bg-primary-500/15 blur-3xl dark:bg-primary-500/10"></div>
                <div class="absolute -right-24 top-10 h-[26rem] w-[26rem] rounded-full bg-violet-500/15 blur-3xl dark:bg-violet-500/10"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(15,23,42,0.05)_1px,transparent_0)] [background-size:26px_26px] dark:bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.06)_1px,transparent_0)]"></div>
            </div>

            <div class="mx-auto max-w-5xl px-6 pb-16 pt-14 text-center md:pb-24 md:pt-20">
                <nav v-if="page.show_breadcrumbs" class="mb-8 flex items-center justify-center gap-2 text-xs text-gray-400">
                    <Link href="/" class="transition-colors hover:text-primary-600">{{ t('Home') }}</Link>
                    <i class="ti ti-chevron-right text-[10px]"></i>
                    <span class="text-gray-600 dark:text-gray-300">{{ page.title }}</span>
                </nav>

                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white/70 px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-gray-600 backdrop-blur dark:border-surface-700 dark:bg-surface-900/70 dark:text-gray-300">
                    <i class="ti ti-sparkles text-primary-500"></i>
                    {{ t('About :name', { name: siteName }) }}
                </span>

                <h1 class="mt-6 text-4xl font-black leading-[1.1] tracking-tight text-gray-900 dark:text-white md:text-6xl">
                    {{ page.title }}
                </h1>

                <p v-if="page.excerpt" class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-gray-500 dark:text-gray-400 md:text-lg">
                    {{ page.excerpt }}
                </p>

                <div v-if="cta.primary || cta.secondary" class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    <Link v-if="cta.primary" :href="cta.primary.href"
                        class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-7 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary-600/25 transition hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-xl hover:shadow-primary-600/30">
                        {{ cta.primary.label }}
                        <i class="ti ti-arrow-right"></i>
                    </Link>
                    <Link v-if="cta.secondary" :href="cta.secondary.href"
                        class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-7 py-3.5 text-sm font-bold text-gray-700 transition hover:-translate-y-0.5 hover:border-gray-300 hover:text-gray-900 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-surface-600 dark:hover:text-white">
                        {{ cta.secondary.label }}
                    </Link>
                </div>
            </div>

            <!-- Stats sit astride the hero's bottom edge, half in the band and half out. -->
            <div v-if="stats.length" class="mx-auto max-w-6xl px-6">
                <div :class="statColumns"
                    class="grid translate-y-px grid-cols-2 gap-px overflow-hidden rounded-t-3xl border border-b-0 border-gray-100 bg-gray-100 dark:border-surface-800 dark:bg-surface-800">
                    <div v-for="stat in stats" :key="stat.label"
                        class="about-reveal bg-white px-5 py-7 text-center dark:bg-surface-950">
                        <i :class="stat.icon" class="text-xl text-primary-500"></i>
                        <p class="mt-3 text-3xl font-black tracking-tight text-gray-900 dark:text-white md:text-4xl">
                            <span class="about-counter" :data-value="stat.value">{{ stat.value }}</span>
                        </p>
                        <p class="mt-1.5 text-[11px] font-black uppercase tracking-widest text-gray-400">{{ stat.label }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─── Story ────────────────────────────────────────────────── -->
        <div class="mx-auto max-w-6xl px-6 py-16 md:py-24">
            <div class="flex flex-col gap-12 lg:flex-row lg:gap-16">

                <aside v-if="showIndex" class="lg:w-56 lg:shrink-0">
                    <div class="lg:sticky lg:top-28">
                        <p class="mb-4 text-[11px] font-black uppercase tracking-widest text-gray-400">{{ t('On this page') }}</p>
                        <ul class="space-y-1 border-l border-gray-100 dark:border-surface-800">
                            <li v-for="section in index" :key="section.id">
                                <a :href="`#${section.id}`"
                                    class="-ml-px block border-l-2 py-1.5 pl-4 text-sm font-semibold transition-colors"
                                    :class="activeId === section.id
                                        ? 'border-primary-600 text-primary-600 dark:text-primary-400'
                                        : 'border-transparent text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                                    {{ section.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </aside>

                <div class="min-w-0 flex-1 space-y-16 md:space-y-20">
                    <section v-for="(section, si) in sections" :key="si" :id="section.id || undefined"
                        :data-about-section="section.id || undefined" class="about-reveal scroll-mt-28">

                        <h2 v-if="section.title" class="mb-6 flex items-baseline gap-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white md:text-3xl">
                            <span class="hidden text-sm font-black text-primary-500/60 md:inline">{{ sectionNumber(section) }}</span>
                            {{ section.title }}
                        </h2>

                        <template v-for="(block, bi) in section.blocks" :key="bi">
                            <!-- Prose -->
                            <div v-if="block.kind === 'html'" v-html="block.html"
                                class="about-prose text-gray-600 dark:text-gray-300"></div>

                            <!-- Bold-led <ul> → card grid -->
                            <div v-else-if="block.kind === 'cards'" class="my-8 grid gap-4 sm:grid-cols-2">
                                <div v-for="(item, ii) in block.items" :key="ii"
                                    class="group rounded-2xl border border-gray-100 bg-white p-6 transition hover:-translate-y-1 hover:border-transparent hover:shadow-xl hover:shadow-gray-200/60 dark:border-surface-800 dark:bg-surface-900/40 dark:hover:shadow-black/30">
                                    <span :class="accent(ii)" class="mb-4 inline-flex h-9 w-9 items-center justify-center rounded-xl text-xs font-black">
                                        {{ String(ii + 1).padStart(2, '0') }}
                                    </span>
                                    <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ item.title }}</h3>
                                    <p v-if="item.body" class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ item.body }}</p>
                                </div>
                            </div>

                            <!-- Bold-led <ol> → milestone timeline -->
                            <ol v-else class="my-8 space-y-8 border-l border-gray-100 pl-8 dark:border-surface-800">
                                <li v-for="(item, ii) in block.items" :key="ii" class="relative">
                                    <span class="absolute -left-[2.3rem] top-1 flex h-4 w-4 items-center justify-center rounded-full border-2 border-white bg-primary-600 ring-4 ring-primary-500/10 dark:border-surface-950"></span>
                                    <p class="text-xs font-black uppercase tracking-widest text-primary-600 dark:text-primary-400">{{ item.title }}</p>
                                    <p v-if="item.body" class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-300 md:text-base">{{ item.body }}</p>
                                </li>
                            </ol>
                        </template>
                    </section>

                    <slot name="after-content" />
                </div>
            </div>
        </div>

        <!-- ─── Social proof ─────────────────────────────────────────── -->
        <section v-if="quotes.length" class="border-y border-gray-100 bg-gray-50/60 py-16 dark:border-surface-800 dark:bg-surface-900/30 md:py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="about-reveal mb-10 text-center text-2xl font-black tracking-tight text-gray-900 dark:text-white md:text-3xl">
                    {{ t('What people build with us') }}
                </h2>
                <div class="grid gap-5 md:grid-cols-3">
                    <figure v-for="quote in quotes" :key="quote.name"
                        class="about-reveal flex flex-col rounded-2xl border border-gray-100 bg-white p-6 dark:border-surface-800 dark:bg-surface-950">
                        <div v-if="quote.rating" class="mb-4 flex gap-0.5 text-amber-400">
                            <i v-for="star in quote.rating" :key="star" class="ti ti-star-filled text-sm"></i>
                        </div>
                        <blockquote class="flex-1 text-sm leading-relaxed text-gray-600 dark:text-gray-300">“{{ quote.content }}”</blockquote>
                        <figcaption class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-5 dark:border-surface-800">
                            <img v-if="quote.avatar" :src="quote.avatar" :alt="quote.name" class="h-10 w-10 rounded-full object-cover" loading="lazy">
                            <span v-else class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-sm font-black text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                {{ quote.name.charAt(0) }}
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold text-gray-900 dark:text-white">{{ quote.name }}</span>
                                <span v-if="quote.role" class="block truncate text-xs text-gray-400">{{ quote.role }}</span>
                            </span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ─── Closing call to action ───────────────────────────────── -->
        <section v-if="cta.primary || cta.secondary" class="px-6 py-16 md:py-24">
            <!-- The ring is what separates the band from the page in dark mode, where a
                 near-black card sits on a near-black background. -->
            <div class="about-reveal relative isolate mx-auto max-w-6xl overflow-hidden rounded-3xl bg-gray-900 px-8 py-14 text-center ring-1 ring-white/10 dark:bg-surface-900 md:px-16 md:py-20">
                <div class="pointer-events-none absolute inset-0 -z-10">
                    <div class="absolute -left-16 -top-16 h-72 w-72 rounded-full bg-primary-600/30 blur-3xl"></div>
                    <div class="absolute -bottom-20 -right-10 h-72 w-72 rounded-full bg-violet-600/30 blur-3xl"></div>
                </div>
                <!-- The theme paints every heading with --color-heading, which is near-black on a
                     light palette and would vanish against this band, so the override is forced. -->
                <h2 class="text-2xl font-black tracking-tight !text-white md:text-4xl">
                    {{ t('Build your next idea with :name', { name: siteName }) }}
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-gray-300 md:text-base">
                    {{ t('Every tool on the platform is one click away — no setup, no credit card to look around.') }}
                </p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <Link v-if="cta.primary" :href="cta.primary.href"
                        class="inline-flex items-center gap-2 rounded-full bg-white px-7 py-3.5 text-sm font-bold text-gray-900 transition hover:-translate-y-0.5 hover:bg-gray-100">
                        {{ cta.primary.label }}
                        <i class="ti ti-arrow-right"></i>
                    </Link>
                    <Link v-if="cta.secondary" :href="cta.secondary.href"
                        class="inline-flex items-center gap-2 rounded-full border border-white/20 px-7 py-3.5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:border-white/40 hover:bg-white/5">
                        {{ cta.secondary.label }}
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
@reference "../../../../css/app.css";

/* Prose inside a story section. Deliberately narrower than the .cms-content rules on
   Page.vue: an About page reads better at a measure, and the headings it does keep sit
   under an <h2> the component already rendered. */
.about-prose :deep(p) { @apply mb-5 text-base leading-[1.8] md:text-[1.0625rem]; }
.about-prose :deep(p:first-child) { @apply text-lg leading-[1.75] text-gray-700 dark:text-gray-200; }
.about-prose :deep(p:last-child) { @apply mb-0; }
.about-prose :deep(h3) { @apply mb-3 mt-8 text-lg font-bold text-gray-900 dark:text-white; }
.about-prose :deep(h4) { @apply mb-2 mt-6 text-base font-bold text-gray-900 dark:text-white; }
.about-prose :deep(ul) { @apply mb-5 list-disc space-y-2 pl-5 marker:text-primary-500; }
.about-prose :deep(ol) { @apply mb-5 list-decimal space-y-2 pl-5 marker:font-bold marker:text-primary-500; }
.about-prose :deep(a) { @apply font-bold text-primary-600 underline decoration-primary-200 underline-offset-4 transition-colors hover:decoration-primary-600 dark:text-primary-400 dark:decoration-primary-900; }
.about-prose :deep(strong) { @apply font-bold text-gray-900 dark:text-white; }
.about-prose :deep(blockquote) { @apply my-8 rounded-2xl border-l-4 border-primary-500 bg-gray-50 px-6 py-5 text-lg font-medium italic leading-relaxed text-gray-700 dark:bg-surface-900/60 dark:text-gray-200; }
.about-prose :deep(img) { @apply my-8 w-full rounded-2xl shadow-lg; }
.about-prose :deep(hr) { @apply my-10 border-gray-100 dark:border-surface-800; }
.about-prose :deep(table) { @apply my-6 w-full border-collapse text-sm; }
.about-prose :deep(th) { @apply border border-gray-100 bg-gray-50 px-4 py-3 text-left font-bold text-gray-700 dark:border-surface-800 dark:bg-surface-900 dark:text-gray-200; }
.about-prose :deep(td) { @apply border border-gray-100 px-4 py-3 dark:border-surface-800; }
</style>
