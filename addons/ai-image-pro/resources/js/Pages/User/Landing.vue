<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@themes/default/js/Layouts/AppLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import LandingPromptPanel from '../../Components/LandingPromptPanel.vue'
import ExampleCard from '../../Components/ExampleCard.vue'
import FaqAccordion from '../../Components/FaqAccordion.vue'
import type { Op, Preset } from '../../Composables/useImageJobs'

/* ------------------------------------------------------------------ *
 * The public marketing page for the image generator.
 *
 * Not one word of copy lives in this file: every heading, paragraph,
 * list item, image and CTA arrives as a prop from the admin-editable
 * settings. The only strings we own are UI chrome (button labels,
 * alt-text fallbacks), and those go through t().
 *
 * Consequence: any list can legitimately be empty, and an empty list
 * must remove its whole section rather than leave a bare heading over
 * a void. Every section below is guarded accordingly.
 *
 * Two admin switches change the whole page's skin — the column width
 * (`content.pageWidth`) and the purple→blue scheme (`content.gradient`).
 * Both are resolved into shared computed classes right here and applied
 * everywhere from those, so the page can never end up half-boxed or
 * half-gradient.
 * ------------------------------------------------------------------ */

defineOptions({ layout: AppLayout })

const { t } = useTranslate()

type PageWidth = 'default' | 'boxed'

/**
 * Admin-authored copy. Laravel's ConvertEmptyStringsToNull turns a blank field
 * of the settings form into NULL, and `withDefaults` only covers `undefined` —
 * so every text field here is genuinely nullable and must be read through
 * `text()` before anything is done with it.
 */
interface LandingContent {
    pageWidth: PageWidth
    gradient: boolean
    showBreadcrumb: boolean
    heroBadge: string | null
    heroHeading: string | null
    heroSubheading: string | null
    examplesEnabled: boolean
    examplesHeading: string | null
    featuresEnabled: boolean
    featuresHeading: string | null
    featuresSubheading: string | null
    usecasesEnabled: boolean
    usecasesHeading: string | null
    usecasesSubheading: string | null
    benefitsEnabled: boolean
    benefitsHeading: string | null
    benefitsSubheading: string | null
    stepsEnabled: boolean
    stepsHeading: string | null
    stepsSubheading: string | null
    aboutEnabled: boolean
    aboutHeading: string | null
    aboutBody: string | null
    faqEnabled: boolean
    faqHeading: string | null
    ctaEnabled: boolean
    ctaHeading: string | null
    ctaSubheading: string | null
    ctaButtonText: string | null
    ctaButtonLink: string | null
}

interface Example {
    title: string
    description: string
    image: string
    prompt: string
}

interface Feature {
    title: string
    body: string
    image: string
    cta_text: string
    cta_link: string
    /**
     * Icon revealed inside the button on hover. Admin-picked; optional because a row
     * saved before this field existed simply has no key for it.
     */
    cta_icon?: string
}

interface IconCard {
    icon: string
    title: string
    body: string
}

interface Step {
    title: string
    body: string
    image: string
}

interface FaqItem {
    question: string
    answer: string
}

interface Model {
    slug: string
    name: string
}

interface AspectRatio {
    key: string
    label: string
    width: number
    height: number
}

interface Seo {
    title: string
    description: string
}

interface PromptSubmit {
    prompt: string
    model: string | null
    aspect: string | null
    preset: string | null
}

const props = defineProps<{
    content: LandingContent
    examples: Example[]
    features: Feature[]
    usecases: IconCard[]
    benefits: IconCard[]
    steps: Step[]
    faqs: FaqItem[]
    models: Model[]
    defaultModel: string | null
    allowModelChoice: boolean
    aspectRatios: AspectRatio[]
    /** The Studio's full tool rail — shown, never run, on this page. */
    operations: Op[]
    /** The Studio's style presets — shown, and carried over on submit. */
    presets: Preset[]
    studioUrl: string
    isGuest: boolean
    toolName: string
    seo: Seo
}>()

/** A settings field, safe to measure and compare. */
function text(value: string | null | undefined): string {
    return (value ?? '').trim()
}

/* ------------------------------------------------------------------ *
 * Skin — derived once, used everywhere.
 * ------------------------------------------------------------------ */
const isBoxed = computed(() => props.content.pageWidth === 'boxed')
const isGradient = computed(() => props.content.gradient === true)

/** The page background; boxed mode needs something for the card to sit on. */
const pageClass = computed(() =>
    isBoxed.value ? 'bg-gray-50 dark:bg-gray-950' : 'bg-white dark:bg-gray-950',
)

/**
 * Boxed mode wraps the entire page in one card — from the hero to the CTA — so
 * the tinted sections and hairlines stay inside the column instead of bleeding
 * to the viewport edge. On a phone the card would just be a border around the
 * whole screen, so it only appears from `sm` up.
 */
const shellClass = computed(() =>
    isBoxed.value
        ? 'mx-auto w-full max-w-5xl overflow-hidden bg-white dark:bg-gray-950'
        : '',
)

/**
 * One column for every section — the page must read as a single ribbon.
 *
 * Default mode uses the site-wide container: `max-w-7xl px-6`, the exact class the
 * homepage Sections and every other frontend page use. app.ts clamps `max-w-7xl`
 * to `--page-width`, so the landing honours the theme's container-width setting and
 * lines up pixel-for-pixel with the rest of the site. Boxed mode keeps its own fixed
 * `max-w-5xl` card (defended against that clamp by the `.aip-landing` <style> below).
 */
const sectionClass = computed(() =>
    isBoxed.value
        ? 'mx-auto w-full max-w-5xl px-6'
        : 'mx-auto w-full max-w-7xl px-6',
)

/** Long-form copy (about, FAQ) keeps a readable measure in either width — matches FaqSection. */
const proseClass = 'mx-auto w-full max-w-3xl px-6'

/**
 * The tinted bands (use-cases, how-it-works) used to be a solid rectangle with a
 * hard `border-t` — boxy at the sides in both widths, and clipped flat against the
 * card wall in boxed mode. This is the tint moved to its own background layer with
 * a horizontal mask so the fill *and* the hairline fade to transparent at the left
 * and right, blending into the page instead of ending on a hard line. Content sits
 * above it untouched. `-webkit-mask-image` for Safari/Chromium precedence.
 */
const tintBandClass =
    'pointer-events-none absolute inset-0 border-t border-gray-100/80 bg-gray-50/50 ' +
    'dark:border-surface-800 dark:bg-surface-950/40 ' +
    '[mask-image:linear-gradient(to_right,transparent,#000_14%,#000_86%,transparent)] ' +
    '[-webkit-mask-image:linear-gradient(to_right,transparent,#000_14%,#000_86%,transparent)]'

/** Every section heading on the page. */
const headingClass = computed(() =>
    isGradient.value
        ? 'bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent dark:from-purple-400 dark:to-blue-400'
        : 'text-gray-900 dark:text-white',
)

/** The CTA banner is dark in both themes, so it needs the light gradient stops. */
const ctaHeadingClass = computed(() =>
    isGradient.value
        ? 'bg-gradient-to-r from-purple-400 to-blue-400 bg-clip-text !text-transparent'
        : '!text-white',
)

/**
 * `group` is what lets a button's icon react to a hover on the button rather than on
 * the icon itself. `gap-1.5` is deliberately NOT here any more: the reveal icons
 * collapse to zero width, and a fixed gap would leave a dead 6px hole next to a
 * hidden icon. Each icon owns its own spacing instead (see btnIconReveal).
 */
const primaryBtnBase =
    'group inline-flex items-center justify-center rounded-full font-semibold shadow-sm transition'

/**
 * An icon that is hidden until the button is hovered, then slides out from the label.
 *
 * Width and margin animate together with opacity, so the button grows into the icon
 * rather than reserving a gap for something invisible. `overflow-hidden` keeps the
 * glyph clipped while it is collapsed. Everything is transform/opacity/size — no
 * layout thrash beyond the button itself.
 */
const btnIconReveal =
    'pointer-events-none inline-block w-0 -translate-x-1 overflow-hidden opacity-0 ' +
    'transition-all duration-300 ease-out ' +
    'group-hover:ml-1.5 group-hover:w-4 group-hover:translate-x-0 group-hover:opacity-100 ' +
    'group-focus-visible:ml-1.5 group-focus-visible:w-4 group-focus-visible:translate-x-0 group-focus-visible:opacity-100'

/** An always-visible trailing icon that nudges forward on hover. */
const btnIconNudge =
    'ml-1.5 inline-block transition-transform duration-300 ease-out group-hover:translate-x-1 group-focus-visible:translate-x-1'

/** An always-visible leading icon that comes alive on hover. */
const btnIconLead =
    'mr-1.5 inline-block transition-transform duration-300 ease-out group-hover:scale-110 group-hover:-rotate-12 group-focus-visible:scale-110'

/** Every primary button/CTA on the page. */
const primaryBtnClass = computed(() =>
    isGradient.value
        ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-500 hover:to-blue-500'
        : 'bg-gray-900 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100',
)

/** The banner sits on near-black: an inverted white pill unless gradient wins. */
const ctaBtnClass = computed(() =>
    isGradient.value
        ? 'bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-500 hover:to-blue-500'
        : 'bg-white text-gray-900 hover:bg-gray-100',
)

/** Ambient washes follow the scheme too, or the page fights itself. */
const heroGlowClass = computed(() =>
    isGradient.value
        ? 'bg-gradient-to-b from-purple-200/60 via-blue-100/40 to-transparent dark:from-purple-600/10 dark:via-blue-900/10'
        : 'bg-gradient-to-b from-primary-100/70 via-primary-50/40 to-transparent dark:from-primary-600/10 dark:via-primary-900/10',
)

/**
 * Boxed mode clips the page at max-w-5xl (64rem) with overflow-hidden. A glow
 * as wide as the column gets its soft edges sliced flat against the card wall —
 * a hard seam. Narrowing it in boxed mode lets the blur fade out inside the
 * column instead of at the clip boundary. Full-bleed mode keeps the wide wash.
 */
const heroGlowSizeClass = computed(() =>
    isBoxed.value ? 'w-[42rem]' : 'w-[64rem]',
)

const ctaWashClass = computed(() =>
    isGradient.value
        ? 'bg-gradient-to-t from-blue-600/40 via-purple-900/20 to-transparent'
        : 'bg-gradient-to-t from-primary-600/40 via-primary-900/20 to-transparent',
)

const ctaBlobClass = computed(() =>
    isGradient.value ? 'bg-purple-500/30' : 'bg-primary-500/30',
)

/* ------------------------------------------------------------------ *
 * Navigation — the one behaviour on this page.
 * ------------------------------------------------------------------ */

/**
 * The prompt panel and the example cards both do the same thing: hand a
 * prompt to the studio, which picks it up from `?prompt=` and starts the
 * generation. Model / aspect / preset ride along as query params when the
 * visitor explicitly chose them, so their intent survives the hop.
 */
function goToStudio(prompt: string, extra: Record<string, string> = {}): void {
    const trimmed = prompt.trim()
    if (!trimmed) return

    router.get(props.studioUrl, { prompt: trimmed, ...extra })
}

function onPromptSubmit(payload: PromptSubmit): void {
    const extra: Record<string, string> = {}
    if (payload.model) extra.model = payload.model
    if (payload.aspect) extra.aspect = payload.aspect
    if (payload.preset) extra.preset = payload.preset

    goToStudio(payload.prompt, extra)
}

const promptPanel = ref<HTMLElement | null>(null)

function scrollToPrompt(): void {
    promptPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

/* ------------------------------------------------------------------ *
 * Images — admin-supplied URLs, so treat every one as untrustworthy.
 * An empty URL renders a soft placeholder; a URL that fails to load is
 * remembered and downgraded to the same placeholder. Never a broken
 * image icon on a page we are selling.
 * ------------------------------------------------------------------ */
const brokenImages = ref<Record<string, true>>({})

function hasImage(url: string | undefined): boolean {
    return Boolean(url && url.trim().length > 0 && !brokenImages.value[url])
}

function onImageError(url: string): void {
    brokenImages.value = { ...brokenImages.value, [url]: true }
}

/* ------------------------------------------------------------------ *
 * Icons — admin types either "ti ti-bulb" or the bare "ti-bulb" or even
 * "bulb". Normalise all three, and always land on something valid.
 * ------------------------------------------------------------------ */
function iconClass(icon: string | undefined): string {
    const raw = (icon ?? '').trim()
    if (!raw) return 'ti ti-sparkles'
    if (raw.startsWith('ti ti-')) return raw
    if (raw.startsWith('ti-')) return `ti ${raw}`

    return `ti ti-${raw}`
}

/**
 * The icon revealed in a feature's button. The admin picks one per row; an arrow is
 * the fallback, so a row saved before this field existed still animates sensibly
 * rather than hovering to reveal nothing.
 */
function featureIcon(feature: Feature): string {
    const raw = (feature.cta_icon ?? '').trim()

    return raw ? iconClass(raw) : 'ti ti-arrow-right'
}

/* ------------------------------------------------------------------ *
 * Section visibility
 * ------------------------------------------------------------------ */
// Every section shows only when the admin has it enabled AND it has content to show.
const showExamples = computed(() => props.content.examplesEnabled && props.examples.length > 0)
const showFeatures = computed(() => props.content.featuresEnabled && props.features.length > 0)
const showUsecases = computed(() => props.content.usecasesEnabled && props.usecases.length > 0)
const showBenefits = computed(() => props.content.benefitsEnabled && props.benefits.length > 0)
const showSteps = computed(() => props.content.stepsEnabled && props.steps.length > 0)
const showAbout = computed(() => props.content.aboutEnabled && text(props.content.aboutBody).length > 0)
const showFaqs = computed(() => props.content.faqEnabled && props.faqs.length > 0)
const showCta = computed(() => props.content.ctaEnabled && text(props.content.ctaHeading).length > 0)
const ctaLink = computed(() => text(props.content.ctaButtonLink) || props.studioUrl)
</script>

<template>
    <Head>
        <title>{{ seo.title || toolName }}</title>
        <meta v-if="seo.description" name="description" :content="seo.description" />
    </Head>

    <div :class="pageClass" class="aip-landing">
        <div :class="shellClass">
            <!-- ========================================================== *
             * HERO — badge, headline, sub, and the prompt card.
             * ========================================================== -->
            <section class="relative overflow-hidden">
                <!-- Ambient glow behind the hero: calm, not loud. -->
                <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-[42rem] overflow-hidden">
                    <div
                        class="absolute -top-64 left-1/2 h-[36rem] -translate-x-1/2 rounded-full blur-3xl"
                        :class="[heroGlowClass, heroGlowSizeClass]"
                    ></div>
                </div>

                <div :class="sectionClass" class="relative pb-16 pt-8 sm:pb-20 lg:pt-12">
                    <!-- Breadcrumb -->
                    <nav
                        v-if="content.showBreadcrumb"
                        :aria-label="t('Breadcrumb')"
                        class="mb-10 flex items-center justify-center"
                    >
                        <ol class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <li>
                                <Link href="/" class="transition hover:text-gray-700 dark:hover:text-gray-300">
                                    {{ t('Home') }}
                                </Link>
                            </li>
                            <li aria-hidden="true" class="text-gray-300 dark:text-surface-700">/</li>
                            <li>{{ t('Tools') }}</li>
                            <li aria-hidden="true" class="text-gray-300 dark:text-surface-700">/</li>
                            <li aria-current="page" class="font-medium text-gray-600 dark:text-gray-400">
                                {{ toolName }}
                            </li>
                        </ol>
                    </nav>

                    <div class="text-center">
                        <p
                            v-if="content.heroBadge"
                            class="mb-6 inline-flex items-center gap-1.5 rounded-full border border-gray-200/80 bg-white/70 px-3.5 py-1.5 text-xs font-medium text-gray-600 shadow-sm backdrop-blur dark:border-surface-700 dark:bg-surface-900/70 dark:text-gray-300"
                        >
                            <i class="ti ti-sparkles text-sm text-primary-500" aria-hidden="true"></i>
                            {{ content.heroBadge }}
                        </p>

                        <h1
                            class="mx-auto max-w-4xl text-balance pb-1 text-3xl font-semibold leading-[1.15] tracking-tight sm:text-5xl sm:leading-[1.12]"
                            :class="headingClass"
                        >
                            {{ content.heroHeading }}
                        </h1>

                        <p
                            v-if="content.heroSubheading"
                            class="mx-auto mt-5 max-w-2xl text-pretty text-sm leading-relaxed text-gray-500 sm:text-base dark:text-gray-400"
                        >
                            {{ content.heroSubheading }}
                        </p>
                    </div>

                    <!-- Prompt panel -->
                    <div ref="promptPanel" class="mx-auto mt-10 max-w-3xl scroll-mt-24">
                        <LandingPromptPanel
                            :models="models"
                            :default-model="defaultModel"
                            :allow-model-choice="allowModelChoice"
                            :aspect-ratios="aspectRatios"
                            :operations="operations"
                            :presets="presets"
                            :studio-url="studioUrl"
                            :gradient="isGradient"
                            @submit="onPromptSubmit"
                        />

                        <!-- ===== Get started with ===== -->
                        <div v-if="showExamples" class="mt-10">
                            <h2 v-if="content.examplesHeading" class="mb-4 text-sm font-semibold" :class="headingClass">
                                {{ content.examplesHeading }}
                            </h2>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <ExampleCard
                                    v-for="(example, index) in examples"
                                    :key="`example-${index}`"
                                    :title="example.title"
                                    :description="example.description"
                                    :image="example.image"
                                    @select="goToStudio(example.prompt)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========================================================== *
             * FEATURES — alternating zigzag rows, hairline separated.
             * ========================================================== -->
            <section v-if="showFeatures" class="py-16 sm:py-24">
                <div :class="sectionClass">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            v-if="content.featuresHeading"
                            class="text-balance pb-1 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl"
                            :class="headingClass"
                        >
                            {{ content.featuresHeading }}
                        </h2>
                        <p
                            v-if="content.featuresSubheading"
                            class="mx-auto mt-4 max-w-xl text-pretty text-sm text-gray-500 sm:text-base dark:text-gray-400"
                        >
                            {{ content.featuresSubheading }}
                        </p>
                    </div>

                    <div class="mt-14 divide-y divide-gray-100 dark:divide-surface-800">
                        <div
                            v-for="(feature, index) in features"
                            :key="`feature-${index}`"
                            class="grid items-center gap-8 py-12 first:pt-0 last:pb-0 lg:grid-cols-2 lg:gap-16 lg:py-16"
                        >
                            <!-- Image (visually flips side on every other row).
                                 Fixed, modest height — a full-bleed 4:3 made every
                                 row a screenful and buried the copy below the fold. -->
                            <div :class="index % 2 === 1 ? 'lg:order-2' : 'lg:order-1'">
                                <div
                                    class="relative h-44 overflow-hidden rounded-2xl bg-gray-50 shadow-[0_10px_40px_-16px_rgba(15,23,42,0.25)] ring-1 ring-gray-900/5 sm:h-52 lg:h-56 dark:bg-surface-900 dark:ring-white/5"
                                >
                                    <img
                                        v-if="hasImage(feature.image)"
                                        :src="feature.image"
                                        :alt="feature.title"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover"
                                        @error="onImageError(feature.image)"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 dark:from-surface-900 dark:to-surface-800"
                                        aria-hidden="true"
                                    >
                                        <i class="ti ti-photo text-3xl text-gray-300 dark:text-surface-600"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Copy -->
                            <div :class="index % 2 === 1 ? 'lg:order-1' : 'lg:order-2'">
                                <h3
                                    class="text-balance pb-0.5 text-2xl font-semibold tracking-tight sm:text-3xl"
                                    :class="headingClass"
                                >
                                    {{ feature.title }}
                                </h3>
                                <p
                                    v-if="feature.body"
                                    class="mt-4 max-w-lg text-pretty text-sm leading-relaxed text-gray-500 sm:text-base dark:text-gray-400"
                                >
                                    {{ feature.body }}
                                </p>

                                <template v-if="feature.cta_text">
                                    <a
                                        v-if="feature.cta_link"
                                        :href="feature.cta_link"
                                        class="mt-7 px-5 py-2.5 text-xs"
                                        :class="[primaryBtnBase, primaryBtnClass]"
                                    >
                                        {{ feature.cta_text }}
                                        <i :class="[btnIconReveal, featureIcon(feature)]" aria-hidden="true"></i>
                                    </a>
                                    <button
                                        v-else
                                        type="button"
                                        class="mt-7 px-5 py-2.5 text-xs"
                                        :class="[primaryBtnBase, primaryBtnClass]"
                                        @click="scrollToPrompt"
                                    >
                                        {{ feature.cta_text }}
                                        <i :class="[btnIconReveal, featureIcon(feature)]" aria-hidden="true"></i>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========================================================== *
             * USE CASES — 2x2 bordered icon cards.
             * ========================================================== -->
            <section v-if="showUsecases" class="relative py-16 sm:py-24">
                <div aria-hidden="true" :class="tintBandClass"></div>
                <div :class="sectionClass" class="relative">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            v-if="content.usecasesHeading"
                            class="text-balance pb-1 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl"
                            :class="headingClass"
                        >
                            {{ content.usecasesHeading }}
                        </h2>
                        <p
                            v-if="content.usecasesSubheading"
                            class="mx-auto mt-4 max-w-xl text-pretty text-sm text-gray-500 sm:text-base dark:text-gray-400"
                        >
                            {{ content.usecasesSubheading }}
                        </p>
                    </div>

                    <div class="mt-12 grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="(usecase, index) in usecases"
                            :key="`usecase-${index}`"
                            class="rounded-2xl border border-gray-200/80 bg-white p-6 transition duration-200 hover:border-gray-300 hover:shadow-[0_10px_30px_-16px_rgba(15,23,42,0.2)] sm:p-7 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-surface-600"
                        >
                            <div class="flex items-center gap-2.5">
                                <i
                                    :class="iconClass(usecase.icon)"
                                    class="text-lg text-primary-500"
                                    aria-hidden="true"
                                ></i>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ usecase.title }}
                                </h3>
                            </div>
                            <p
                                v-if="usecase.body"
                                class="mt-3 text-pretty text-xs leading-relaxed text-gray-500 sm:text-sm dark:text-gray-400"
                            >
                                {{ usecase.body }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========================================================== *
             * BENEFITS — 3 columns, big icons, centered.
             * ========================================================== -->
            <section v-if="showBenefits" class="border-t border-gray-100/80 py-16 sm:py-24 dark:border-surface-800">
                <div :class="sectionClass">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            v-if="content.benefitsHeading"
                            class="text-balance pb-1 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl"
                            :class="headingClass"
                        >
                            {{ content.benefitsHeading }}
                        </h2>
                        <p
                            v-if="content.benefitsSubheading"
                            class="mx-auto mt-4 max-w-xl text-pretty text-sm text-gray-500 sm:text-base dark:text-gray-400"
                        >
                            {{ content.benefitsSubheading }}
                        </p>
                    </div>

                    <div class="mt-14 grid gap-10 sm:grid-cols-3 sm:gap-8">
                        <div
                            v-for="(benefit, index) in benefits"
                            :key="`benefit-${index}`"
                            class="flex flex-col items-center px-2 text-center"
                        >
                            <span
                                class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-500 ring-1 ring-primary-100 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-500/20"
                            >
                                <i :class="iconClass(benefit.icon)" class="text-2xl" aria-hidden="true"></i>
                            </span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ benefit.title }}
                            </h3>
                            <p
                                v-if="benefit.body"
                                class="mt-2.5 max-w-xs text-pretty text-xs leading-relaxed text-gray-500 sm:text-sm dark:text-gray-400"
                            >
                                {{ benefit.body }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-14 text-center">
                        <button
                            type="button"
                            class="group inline-flex items-center rounded-full border border-gray-200 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:bg-surface-800"
                            @click="scrollToPrompt"
                        >
                            {{ t('Experience the difference') }}
                            <!-- Diagonal arrow: travels the way it points. -->
                            <i
                                class="ti ti-arrow-up-right ml-1.5 inline-block text-sm transition-transform duration-300 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-0.5"
                                aria-hidden="true"
                            ></i>
                        </button>
                    </div>
                </div>
            </section>

            <!-- ========================================================== *
             * HOW IT WORKS — 3 step cards, image on top.
             * ========================================================== -->
            <section v-if="showSteps" class="relative py-16 sm:py-24">
                <div aria-hidden="true" :class="tintBandClass"></div>
                <div :class="sectionClass" class="relative">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2
                            v-if="content.stepsHeading"
                            class="text-balance pb-1 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl"
                            :class="headingClass"
                        >
                            {{ content.stepsHeading }}
                        </h2>
                        <p
                            v-if="content.stepsSubheading"
                            class="mx-auto mt-4 max-w-xl text-pretty text-sm text-gray-500 sm:text-base dark:text-gray-400"
                        >
                            {{ content.stepsSubheading }}
                        </p>
                    </div>

                    <ol class="mt-14 grid gap-6 md:grid-cols-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="`step-${index}`"
                            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-[0_8px_30px_-18px_rgba(15,23,42,0.2)] dark:border-surface-700 dark:bg-surface-900"
                        >
                            <!-- Short banner, not a hero: the step's words carry it. -->
                            <div class="h-40 overflow-hidden bg-gray-50 sm:h-44 dark:bg-surface-800">
                                <img
                                    v-if="hasImage(step.image)"
                                    :src="step.image"
                                    :alt="step.title"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-full w-full object-cover"
                                    @error="onImageError(step.image)"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 dark:from-surface-900 dark:to-surface-800"
                                    aria-hidden="true"
                                >
                                    <i class="ti ti-photo text-2xl text-gray-300 dark:text-surface-600"></i>
                                </div>
                            </div>

                            <div class="p-6">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-500">
                                    {{ t('Step :number', { number: index + 1 }) }}
                                </p>
                                <h3 class="mt-1.5 text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ step.title }}
                                </h3>
                                <p
                                    v-if="step.body"
                                    class="mt-2 text-pretty text-xs leading-relaxed text-gray-500 sm:text-sm dark:text-gray-400"
                                >
                                    {{ step.body }}
                                </p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-12 text-center">
                        <button
                            type="button"
                            class="px-6 py-3 text-xs"
                            :class="[primaryBtnBase, primaryBtnClass]"
                            @click="scrollToPrompt"
                        >
                            <i class="ti ti-sparkles text-sm" :class="btnIconLead" aria-hidden="true"></i>
                            {{ t('Start creating') }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- ========================================================== *
             * ABOUT — SEO copy block.
             * ========================================================== -->
            <section v-if="showAbout" class="border-t border-gray-100/80 py-16 sm:py-20 dark:border-surface-800">
                <div :class="proseClass">
                    <h2
                        v-if="content.aboutHeading"
                        class="text-balance pb-1 text-2xl font-semibold tracking-tight sm:text-3xl"
                        :class="headingClass"
                    >
                        {{ content.aboutHeading }}
                    </h2>
                    <p
                        class="mt-5 whitespace-pre-line text-pretty text-sm leading-relaxed text-gray-500 sm:text-base dark:text-gray-400"
                    >
                        {{ content.aboutBody }}
                    </p>
                </div>
            </section>

            <!-- ========================================================== *
             * FAQ
             * ========================================================== -->
            <section v-if="showFaqs" class="border-t border-gray-100/80 py-16 sm:py-24 dark:border-surface-800">
                <div :class="proseClass">
                    <h2
                        v-if="content.faqHeading"
                        class="mx-auto mb-12 max-w-xl text-balance pb-1 text-center text-3xl font-semibold leading-tight tracking-tight sm:text-4xl"
                        :class="headingClass"
                    >
                        {{ content.faqHeading }}
                    </h2>

                    <FaqAccordion :items="faqs" id-prefix="aip-faq" />
                </div>
            </section>

            <!-- ========================================================== *
             * CTA BANNER
             * ========================================================== -->
            <section v-if="showCta" class="pb-20 pt-4 sm:pb-28">
                <div :class="sectionClass">
                    <div
                        class="relative overflow-hidden rounded-3xl bg-gray-950 px-6 py-20 text-center shadow-2xl sm:px-12 sm:py-24"
                    >
                        <!-- Gradient wash, echoing the hero glow at the other end of the page. -->
                        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                            <div class="absolute inset-x-0 bottom-0 h-2/3" :class="ctaWashClass"></div>
                            <div
                                class="absolute -bottom-24 left-1/2 h-64 w-[40rem] -translate-x-1/2 rounded-full blur-3xl"
                                :class="ctaBlobClass"
                            ></div>
                        </div>

                        <div class="relative">
                            <h2
                                class="text-balance pb-1 text-3xl font-bold tracking-tight sm:text-4xl"
                                :class="ctaHeadingClass"
                            >
                                {{ content.ctaHeading }}
                            </h2>
                            <p
                                v-if="content.ctaSubheading"
                                class="mx-auto mt-4 max-w-lg text-pretty text-sm !text-gray-400 sm:text-base"
                            >
                                {{ content.ctaSubheading }}
                            </p>

                            <a
                                :href="ctaLink"
                                class="mt-9 px-7 py-3 text-sm shadow-lg hover:scale-[1.02]"
                                :class="[primaryBtnBase, ctaBtnClass]"
                            >
                                {{ content.ctaButtonText || t('Start creating') }}
                                <i class="ti ti-arrow-right text-base" :class="btnIconNudge" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<!--
    Two theme-level overrides live here because they cannot be won from utility
    classes alone. Global, but every rule is namespaced under `.aip-landing`, so
    nothing leaks to the rest of the app.
-->
<style>
/*
 * The app injects `main .mx-auto { max-width: var(--page-width) !important }`
 * (see resources/js/app.ts) to clamp every centred container to the theme width.
 * That clamp is CORRECT for the default-mode section (max-w-7xl → --page-width,
 * exactly like every other page), so it is deliberately NOT overridden here. What
 * it breaks is (a) the boxed-mode fixed card and (b) narrow blocks (prose, the
 * prompt card) it would otherwise stretch full-width. Re-assert only those at a
 * higher specificity so they keep their intended measure.
 */
.aip-landing .max-w-5xl { max-width: 64rem !important; }
.aip-landing .max-w-3xl { max-width: 48rem !important; }
.aip-landing .max-w-2xl { max-width: 42rem !important; }
.aip-landing .max-w-xl  { max-width: 36rem !important; }

/*
 * A bg-clip-text gradient shows through the text only while the glyph fill is
 * transparent. `text-transparent` sets `color`, but WebKit gives
 * `-webkit-text-fill-color` precedence — and theme/base styles set it on
 * headings, which paints them solid and hides the gradient. Force it transparent
 * wherever we clip a gradient to text.
 */
.aip-landing .bg-clip-text { -webkit-text-fill-color: transparent !important; }
</style>
