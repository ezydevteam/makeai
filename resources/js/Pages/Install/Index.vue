<script setup lang="ts">
import { computed, ref, resolveComponent, markRaw, type Component } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import InstallLayout from '@/Layouts/InstallLayout.vue'
import StepIndicator from '@/Components/Install/StepIndicator.vue'
import StepSystemCheck from '@/Components/Install/StepSystemCheck.vue'
import StepDatabase from '@/Components/Install/StepDatabase.vue'
import StepSiteSetup from '@/Components/Install/StepSiteSetup.vue'
import StepLicense from '@/Components/Install/StepLicense.vue'
import StepAdminAccount from '@/Components/Install/StepAdminAccount.vue'
import StepDemoContent from '@/Components/Install/StepDemoContent.vue'
import StepFinal from '@/Components/Install/StepFinal.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: InstallLayout })

declare const route: (name: string, params?: Record<string, any>) => string

interface InstallProps {
    currentStep: number
    totalSteps: number
    stepsCompleted: number[]
    formData: Record<string, any>
    systemCheck?: Record<string, any[]>
    allPass?: boolean
    demoExists?: boolean
    demoPreview?: string | null
}

const props = defineProps<InstallProps>()

const page = usePage()
const { t } = useTranslate()
const installing = ref(false)
const installError = ref<string | null>(null)
const showSuccess = ref(false)
const successMessage = ref('')

const stepComponents: Record<number, Component> = {
    1: markRaw(StepSystemCheck),
    2: markRaw(StepDatabase),
    3: markRaw(StepSiteSetup),
    4: markRaw(StepLicense),
    5: markRaw(StepAdminAccount),
    6: markRaw(StepDemoContent),
    7: markRaw(StepFinal),
}

const activeComponent = computed(() => stepComponents[props.currentStep])

const stepRef = ref<InstanceType<typeof StepSystemCheck> | null>(null)

const isFirstStep = computed(() => props.currentStep === 1)
const isLastStep = computed(() => props.currentStep === 7)

const canGoNext = computed(() => {
    if (props.currentStep === 1 && !props.allPass) return false
    return true
})

function goBack() {
    router.post(
        route('install.goto-step', { step: props.currentStep - 1 }),
        {},
        { preserveScroll: true },
    )
}

function goNext() {
    const data = (stepRef.value as any)?.getData?.() ?? {}

    router.post(
        route('install.step', { step: props.currentStep }),
        data,
        {
            preserveScroll: true,
            onError: (errors) => {
                console.error(errors)
            },
        },
    )
}

async function finalizeInstall() {
    installing.value = true
    installError.value = null

    try {
        const response = await axios.post(route('install.finalize'))
        if (response.data.success) {
            successMessage.value = response.data.message
            showSuccess.value = true
            installing.value = false
            setTimeout(() => {
                window.location.href = response.data.redirect
            }, 3000)
        } else {
            installError.value = response.data.message || t('Installation failed. Check the logs for details.')
            installing.value = false
        }
    } catch (e: any) {
        installError.value = e.response?.data?.message || t('Installation failed. Please try again.')
        installing.value = false
    }
}
</script>

<template>
    <Head :title="t('Installation Wizard')" />

    <div>
        <!-- Step Indicator -->
        <StepIndicator
            v-if="!showSuccess"
            :current-step="currentStep"
            :steps-completed="stepsCompleted"
            :total-steps="totalSteps"
        />

        <!-- Success State -->
        <div
            v-if="showSuccess"
            class="my-8 rounded-2xl border border-green-200 bg-green-50 p-8 text-center text-green-800 dark:border-green-700 dark:bg-green-950/20 dark:text-green-300 shadow-md animate-fade-in"
        >
            <div class="flex flex-col items-center justify-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Installation Completed!') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 max-w-md">{{ successMessage }}</p>
                <div class="mt-4 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <svg class="h-4 w-4 animate-spin text-green-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>{{ t('Redirecting to the login page in 3 seconds...') }}</span>
                </div>
            </div>
        </div>

        <template v-else>
            <!-- Error Banner -->
            <div
                v-if="page.props.flash?.error || installError"
                class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300"
            >
                {{ page.props.flash?.error || installError }}
            </div>

            <!-- Active Step -->
            <div class="min-h-[300px]">
                <component
                    :is="activeComponent"
                    ref="stepRef"
                    :form-data="formData"
                    :system-check="systemCheck"
                    :all-pass="allPass"
                    :demo-exists="demoExists"
                    :demo-preview="demoPreview"
                />
            </div>

            <!-- Navigation Buttons -->
            <div class="mt-8 flex items-center justify-between">
                <!-- Back -->
                <button
                    v-if="!isFirstStep"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-surface-600 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700"
                    @click="goBack"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ t('Back') }}
                </button>
                <div v-else />

                <!-- Next / Install -->
                <button
                    v-if="!isLastStep"
                    type="button"
                    :disabled="!canGoNext"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-colors hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="goNext"
                >
                    {{ t('Next') }}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <button
                    v-else
                    type="button"
                    :disabled="installing"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition-all hover:bg-blue-500 hover:shadow-xl disabled:opacity-60 disabled:cursor-not-allowed"
                    @click="finalizeInstall"
                >
                    <svg v-if="installing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                    </svg>
                    {{ installing ? t('Installing...') : t('Install Now') }}
                </button>
            </div>
        </template>
    </div>
</template>
