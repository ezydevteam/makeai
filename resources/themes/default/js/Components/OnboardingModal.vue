<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import AppModal from '@/Components/UI/AppModal.vue'

defineEmits<{ close: [] }>()

const props = withDefaults(defineProps<{
    open: boolean
}>(), {
    open: false,
})

const { t } = useTranslate()
const page = usePage()
const user = computed(() => page.props.auth?.user as any)
const firstName = computed(() => String(user.value?.name ?? '').trim().split(/\s+/)[0] ?? '')
const welcomeHeading = computed(() => firstName.value
    ? t('Greetings! :name 👏', { name: firstName.value })
    : t('Greetings 👏'))
const siteName = computed(() => String((page.props.branding as any)?.site_name || page.props.appName || 'MakeAI'))
const siteFavicon = computed(() => (page.props.branding as any)?.site_favicon_png || (page.props.branding as any)?.site_favicon_ico || '')

// Workspace tour item advertises Playground / Chains / Embeds — each is an admin
// feature toggle (missing prop = enabled, mirroring the server default). Only list
// the ones actually on, and hide the whole item when all three are disabled.
const playgroundEnabled = computed(() => page.props.playgroundEnabled !== false)
const chainsEnabled = computed(() => page.props.chainsEnabled !== false)
const toolEmbedsEnabled = computed(() => page.props.toolEmbedsEnabled !== false)
const workspaceFeatures = computed(() => {
    const items: string[] = []
    if (playgroundEnabled.value) items.push(t('Playground'))
    if (chainsEnabled.value) items.push(t('tool chains'))
    if (toolEmbedsEnabled.value) items.push(t('embeddable tools'))
    return items
})
const workspaceEnabled = computed(() => workspaceFeatures.value.length > 0)
const workspaceDesc = computed(() => workspaceFeatures.value.join(', ') + '.')

// Title tracks the current step. Step 0 stays brand-aware; the rest name what
// the user is looking at. Falls back to the welcome title if the step index ever
// lands out of range.
const stepTitles = computed(() => [
    t('Get started with :site_name', { site_name: siteName.value }),
    t('What do you do?'),
    t('Your recommended tools'),
    t('A quick tour'),
    t('You\'re all set'),
])
const modalTitle = computed(() => stepTitles.value[currentStep.value] ?? stepTitles.value[0])

const useCases = [
    { value: 'content_creator', label: t('Content Creator'), icon: 'ti ti-writing', desc: t('Blog posts, articles, SEO content') },
    { value: 'social_media', label: t('Social Influencer'), icon: 'ti ti-brand-instagram', desc: t('Captions, hashtags, reels, threads') },
    { value: 'marketer', label: t('Marketer'), icon: 'ti ti-chart-arrows', desc: t('Campaigns, positioning, go-to-market') },
    { value: 'copywriter', label: t('Copywriter'), icon: 'ti ti-pencil', desc: t('Ad copy, landing pages, CTAs') },
    { value: 'seo_specialist', label: t('SEO Specialist'), icon: 'ti ti-world', desc: t('Meta tags, landing pages, schema') },
    { value: 'developer', label: t('Developer'), icon: 'ti ti-code', desc: t('Code, docs, tests, changelogs') },
    { value: 'ecommerce', label: t('Online Seller'), icon: 'ti ti-shopping-cart', desc: t('Product copy, listings, reviews') },
    { value: 'business_owner', label: t('Business Owner'), icon: 'ti ti-briefcase', desc: t('Plans, reports, SOPs, proposals') },
    { value: 'entrepreneur', label: t('Entrepreneur'), icon: 'ti ti-rocket', desc: t('Pitches, GTM, fundraising') },
    { value: 'sales', label: t('Salesperson'), icon: 'ti ti-businessplan', desc: t('Outreach, proposals, follow-ups') },
    { value: 'customer_support', label: t('Support Agent'), icon: 'ti ti-headset', desc: t('Replies, help docs, macros') },
    { value: 'hr_recruiter', label: t('Recruiter'), icon: 'ti ti-users-group', desc: t('Job ads, interviews, offers') },
    { value: 'student', label: t('Student'), icon: 'ti ti-school', desc: t('Essays, study notes, research') },
    { value: 'educator', label: t('Educator'), icon: 'ti ti-chalkboard', desc: t('Lessons, quizzes, study materials') },
    { value: 'creative_writer', label: t('Creative Writer'), icon: 'ti ti-feather', desc: t('Stories, scripts, poems, lyrics') },
    { value: 'explore', label: t('Just Exploring'), icon: 'ti ti-compass', desc: t('Not sure yet — show me what\'s popular') },
]

const currentStep = ref(0)
const selectedUseCase = ref<string>('')
const recommendedTools = ref<any[]>([])
const loadingTools = ref(false)
const submitting = ref(false)
const isAnimating = ref(false)

const totalSteps = 5

const activeUseCaseLabel = computed(() => {
    const uc = useCases.find(u => u.value === selectedUseCase.value)
    return uc?.label ?? ''
})

const activeUseCaseIcon = computed(() => {
    const uc = useCases.find(u => u.value === selectedUseCase.value)
    return uc?.icon ?? ''
})

function nextStep() {
    if (isAnimating.value || submitting.value) return
    if (currentStep.value === 1 && !selectedUseCase.value) return

    if (currentStep.value === totalSteps - 2) {
        complete()
        return
    }

    isAnimating.value = true
    currentStep.value++
    if (currentStep.value === 2) {
        fetchRecommendedTools()
    }
    setTimeout(() => { isAnimating.value = false }, 350)
}

function prevStep() {
    if (isAnimating.value || submitting.value) return
    isAnimating.value = true
    currentStep.value--
    setTimeout(() => { isAnimating.value = false }, 350)
}

function skip() {
    router.post(route('user.dashboard.onboarding.skip'), {}, {
        preserveScroll: true,
        onSuccess: () => { window.dispatchEvent(new CustomEvent('onboarding:closed')) },
    })
}

async function fetchRecommendedTools() {
    if (!selectedUseCase.value) return
    loadingTools.value = true
    try {
        const response = await fetch(route('user.dashboard.onboarding.tools', selectedUseCase.value))
        const data = await response.json()
        recommendedTools.value = data.tools ?? []
    } catch {
        recommendedTools.value = []
    } finally {
        loadingTools.value = false
    }
}

function complete() {
    submitting.value = true
    router.post(route('user.dashboard.onboarding.complete'), {
        use_case: selectedUseCase.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            window.dispatchEvent(new CustomEvent('onboarding:closed'))
        },
        onFinish: () => { submitting.value = false },
    })
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        currentStep.value = 0
        selectedUseCase.value = ''
        recommendedTools.value = []
    }
})
</script>

<template>
    <AppModal
        :open="open"
        :title="modalTitle"
        max-width="max-w-lg"
        @close="skip"
    >
        <!-- Progress Dots -->
        <div class="flex items-center justify-center gap-2 px-6 pt-5 pb-3">
            <span
                v-for="i in totalSteps"
                :key="i"
                class="transition-all duration-300 rounded-full"
                :class="[
                    i - 1 === currentStep ? 'w-8 h-2.5 bg-primary-500' : i - 1 < currentStep ? 'w-2.5 h-2.5 bg-primary-500/60' : 'w-2.5 h-2.5 bg-gray-300 dark:bg-gray-700'
                ]"
            ></span>
        </div>

        <!-- Step Content -->
        <div class="px-6 py-4 min-h-[300px] flex flex-col items-center justify-center text-center">
            <Transition
                :name="isAnimating ? 'step-slide-out' : 'step-slide-in'"
                mode="out-in"
            >
                <!-- Step 0: Welcome -->
                <div v-if="currentStep === 0" key="step-0" class="space-y-5">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-primary-500/10 flex items-center justify-center">
                        <img v-if="siteFavicon" :src="siteFavicon" :alt="siteName" class="w-10 h-10 object-contain rounded-xl" />
                        <i v-else class="ti ti-sparkles text-3xl text-primary-500"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ welcomeHeading }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                            {{ t('Your all-in-one AI workspace. Answer one quick question and we\'ll tailor the tools to your work — it takes under a minute.') }}
                        </p>
                    </div>
                </div>

                <!-- Step 1: Use Case -->
                <div v-else-if="currentStep === 1" key="step-1" class="space-y-4 w-full">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('What brings you here?') }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Pick what fits best — we\'ll tailor tool recommendations to match. You can always explore the rest later.') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 w-full max-h-[46vh] overflow-y-auto pr-1 -mr-1">
                        <button
                            v-for="uc in useCases"
                            :key="uc.value"
                            @click="selectedUseCase = uc.value"
                            class="flex flex-col items-center gap-1.5 rounded-xl border p-3.5 transition-all duration-200 text-center"
                            :class="selectedUseCase === uc.value
                                ? 'border-primary-500 bg-primary-500/5 ring-1 ring-primary-500/20'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-primary-500/5 dark:border-gray-800 dark:hover:border-gray-700 dark:hover:bg-primary-500/5'"
                        >
                            <i :class="uc.icon" class="text-2xl text-primary-500"></i>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ uc.label }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 leading-snug">{{ uc.desc }}</span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Recommended Tools -->
                <div v-else-if="currentStep === 2" key="step-2" class="space-y-5 w-full">
                    <div>
                        <div class="mx-auto w-12 h-12 rounded-xl bg-primary-500/10 flex items-center justify-center mb-3">
                            <i :class="activeUseCaseIcon || 'ti ti-wand'" class="text-2xl text-primary-500"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Recommended for :label', { label: activeUseCaseLabel }) }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Hand-picked from the tools people like you use most. Tap any to open it after setup.') }}
                        </p>
                    </div>
                    <div v-if="loadingTools" class="flex justify-center py-8">
                        <i class="ti ti-loader-2 animate-spin text-2xl text-primary-500"></i>
                    </div>
                    <div v-else-if="recommendedTools.length" class="flex flex-col gap-2 w-full max-h-[42vh] overflow-y-auto pr-1 -mr-1 text-left">
                        <a
                            v-for="tool in recommendedTools.slice(0, 6)"
                            :key="tool.slug"
                            :href="route('ai.tools.show', tool.slug)"
                            class="group flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3 transition-colors hover:border-primary-300 hover:bg-primary-500/5 dark:border-gray-800 dark:bg-gray-900/50 dark:hover:border-primary-700"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm dark:bg-gray-800">
                                <i :class="tool.icon || 'ti ti-wand'" class="text-lg" :style="{ color: tool.color || 'var(--color-primary-500)' }"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ tool.name }}</span>
                                    <span v-if="tool.requires_pro" class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ t('Pro') }}</span>
                                </span>
                                <span v-if="tool.description" class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400 truncate">{{ tool.description }}</span>
                            </span>
                            <i class="ti ti-arrow-right shrink-0 text-gray-300 transition-transform group-hover:translate-x-0.5 group-hover:text-primary-500 dark:text-gray-600"></i>
                        </a>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-6 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-400">
                        {{ t('We\'ll surface the right tools as you explore your dashboard.') }}
                    </div>
                </div>

                <!-- Step 3: Quick Tour -->
                <div v-else-if="currentStep === 3" key="step-3" class="space-y-5 w-full">
                    <div>
                        <div class="mx-auto w-16 h-16 rounded-2xl bg-primary-500/10 flex items-center justify-center mb-3">
                            <i class="ti ti-compass text-3xl text-primary-500"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Here\'s your quick tour') }}</h2>
                    </div>
                    <div class="space-y-3 text-left max-h-[42vh] overflow-y-auto pr-1 -mr-1">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="ti ti-layout-grid text-sm text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Dashboard') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Your stats, credits, and quick access to tools.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="ti ti-tools text-sm text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('AI Tools') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Browse the full library of AI tools, organized by category.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="ti ti-folder text-sm text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('My Library') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Every generation is saved so you can revisit, edit, and reuse it.') }}</p>
                            </div>
                        </div>
                        <div v-if="workspaceEnabled" class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="ti ti-building-factory text-sm text-purple-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Workspace') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ workspaceDesc }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="ti ti-user-cog text-sm text-rose-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Profile & Preferences') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Set your profession, timezone, and account preferences anytime.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Complete -->
                <div v-else-if="currentStep === 4" key="step-4" class="space-y-5">
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <i class="ti ti-check text-3xl text-green-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('You\'re all set!') }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                            {{ t('Your dashboard is ready. Start exploring AI tools, create content, and boost your productivity.') }}
                        </p>
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="flex items-center justify-between w-full">
                <button
                    v-if="currentStep > 0 && currentStep < totalSteps - 1"
                    @click="prevStep"
                    :disabled="isAnimating"
                    class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                    {{ t('Back') }}
                </button>
                <span v-else></span>

                <div class="flex items-center gap-3">
                    <button
                        v-if="currentStep < totalSteps - 2"
                        @click="skip"
                        class="text-sm font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition"
                    >
                        {{ t('Skip') }}
                    </button>

                    <button
                        v-if="currentStep < totalSteps - 2"
                        @click="nextStep"
                        :disabled="isAnimating || (currentStep === 1 && !selectedUseCase)"
                        class="inline-flex items-center gap-1 btn-primary disabled:opacity-50 shadow-sm"
                    >
                        {{ t('Next') }}
                        <i class="ti ti-arrow-right text-sm"></i>
                    </button>

                    <button
                        v-if="currentStep === totalSteps - 2"
                        @click="nextStep"
                        :disabled="submitting"
                        class="inline-flex items-center gap-1 btn-primary disabled:opacity-50 shadow-sm"
                    >
                        <i v-if="submitting" class="ti ti-loader-2 animate-spin text-sm"></i>
                        {{ submitting ? t('Saving...') : t('Get Started') }}
                    </button>
                </div>
            </div>
        </template>
    </AppModal>
</template>

<style scoped>
.step-slide-in-enter-active,
.step-slide-in-leave-active,
.step-slide-out-enter-active,
.step-slide-out-leave-active {
    transition: all 0.25s ease;
}
.step-slide-in-enter-from {
    opacity: 0;
    transform: translateX(30px);
}
.step-slide-in-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}
.step-slide-out-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}
.step-slide-out-leave-to {
    opacity: 0;
    transform: translateX(30px);
}
</style>
