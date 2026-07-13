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

const useCases = [
    { value: 'content_creator', label: t('Content Creator'), icon: 'ti ti-writing', desc: t('Blog posts, social media, SEO content') },
    { value: 'marketer', label: t('Marketer'), icon: 'ti ti-chart-arrows', desc: t('Ad copy, landing pages, email campaigns') },
    { value: 'developer', label: t('Developer'), icon: 'ti ti-code', desc: t('Code generation, docs, API helpers') },
    { value: 'student', label: t('Student'), icon: 'ti ti-school', desc: t('Essays, study notes, research summaries') },
    { value: 'ecommerce', label: t('E-commerce'), icon: 'ti ti-shopping-cart', desc: t('Product descriptions, ad copy, reviews') },
    { value: 'business_owner', label: t('Business Owner'), icon: 'ti ti-briefcase', desc: t('Business plans, proposals, press releases') },
]

const currentStep = ref(0)
const selectedUseCase = ref<string>('')
const recommendedTools = ref<any[]>([])
const loadingTools = ref(false)
const submitting = ref(false)
const isAnimating = ref(false)

const totalSteps = 5
const stepLabels = [t('Welcome'), t('Use Case'), t('Tools'), t('Tour'), t('Done')]

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
                    i - 1 === currentStep ? 'w-8 h-2.5 bg-[#1F75FE]' : i - 1 < currentStep ? 'w-2.5 h-2.5 bg-[#1F75FE]/60' : 'w-2.5 h-2.5 bg-gray-300 dark:bg-gray-700'
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
                    <div class="mx-auto w-16 h-16 rounded-2xl bg-[#1F75FE]/10 flex items-center justify-center">
                        <i class="ti ti-sparkles text-3xl text-[#1F75FE]"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Welcome to MakeAI!') }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                            {{ t('Your all-in-one AI workspace. Let\'s get you set up in under a minute so you can start creating amazing content.') }}
                        </p>
                    </div>
                </div>

                <!-- Step 1: Use Case -->
                <div v-else-if="currentStep === 1" key="step-1" class="space-y-5 w-full">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('What brings you here?') }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('Choose your use case so we can recommend the best tools for you.') }}
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 w-full">
                        <button
                            v-for="uc in useCases"
                            :key="uc.value"
                            @click="selectedUseCase = uc.value"
                            class="flex flex-col items-center gap-2 rounded-xl border p-4 transition-all duration-200 text-left"
                            :class="selectedUseCase === uc.value
                                ? 'border-[#1F75FE] bg-[#1F75FE]/5 ring-1 ring-[#1F75FE]/20'
                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'"
                        >
                            <i :class="uc.icon" class="text-2xl text-[#1F75FE]"></i>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ uc.label }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ uc.desc }}</span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Recommended Tools -->
                <div v-else-if="currentStep === 2" key="step-2" class="space-y-5 w-full">
                    <div>
                        <div class="mx-auto w-12 h-12 rounded-xl bg-[#1F75FE]/10 flex items-center justify-center mb-3">
                            <i :class="activeUseCaseIcon || 'ti ti-writing'" class="text-2xl text-[#1F75FE]"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Recommended for :label', { label: activeUseCaseLabel }) }}</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ t('These AI tools match your use case.') }}
                        </p>
                    </div>
                    <div v-if="loadingTools" class="flex justify-center py-8">
                        <i class="ti ti-loader-2 animate-spin text-2xl text-[#1F75FE]"></i>
                    </div>
                    <div v-else-if="recommendedTools.length" class="grid grid-cols-2 gap-2 w-full">
                        <div
                            v-for="tool in recommendedTools.slice(0, 6)"
                            :key="tool.slug"
                            class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50"
                        >
                            <i :class="tool.icon || 'ti ti-wand'" class="text-lg shrink-0" :style="{ color: tool.color || '#1F75FE' }"></i>
                            <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ tool.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Quick Tour -->
                <div v-else-if="currentStep === 3" key="step-3" class="space-y-5 w-full">
                    <div>
                        <div class="mx-auto w-16 h-16 rounded-2xl bg-[#1F75FE]/10 flex items-center justify-center mb-3">
                            <i class="ti ti-compass text-3xl text-[#1F75FE]"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Here\'s your quick tour') }}</h2>
                    </div>
                    <div class="space-y-3 text-left">
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
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Browse 80+ AI tools organized by category.') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="ti ti-building-factory text-sm text-purple-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Workspace') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ t('Playground, tool chains, and embeddable tools.') }}</p>
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
                    class="text-sm font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition"
                >
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
                        class="inline-flex items-center gap-2 rounded-xl bg-[#1F75FE] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] disabled:opacity-50 transition shadow-sm"
                    >
                        {{ t('Next') }}
                        <i class="ti ti-arrow-right text-sm"></i>
                    </button>

                    <button
                        v-if="currentStep === totalSteps - 2"
                        @click="nextStep"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#1F75FE] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] disabled:opacity-50 transition shadow-sm"
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
