<script setup lang="ts">
import { computed, ref, markRaw, type Component } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import InstallLayout from './InstallLayout.vue'
import StepIndicator from './Components/StepIndicator.vue'
import StepSystemCheck from './Components/StepSystemCheck.vue'
import StepDatabase from './Components/StepDatabase.vue'
import StepSiteSetup from './Components/StepSiteSetup.vue'
import StepLicense from './Components/StepLicense.vue'
import StepAdminAccount from './Components/StepAdminAccount.vue'
import StepFinal from './Components/StepFinal.vue'
import InstallProgress from './Components/InstallProgress.vue'

defineOptions({ layout: InstallLayout })

declare const route: (name: string, params?: Record<string, any>) => string

interface InstallProps {
    currentStep: number
    totalSteps: number
    stepsCompleted: number[]
    formData: Record<string, any>
    systemCheck?: Record<string, any[]>
    allPass?: boolean
    cronCommand?: string
    // Flashed by the controller when it refuses a populated database on submit,
    // so the Database step can reveal the reset card on the Next click alone.
    dbState?: string | null
}

const props = defineProps<InstallProps>()

const page = usePage()
const installing = ref(false)
// Set while the step POST is in flight. Steps 2/3 hit the network (license
// check, DB connection test) and can take a few seconds with no other signal.
const submitting = ref(false)
const installError = ref<string | null>(null)
const showSuccess = ref(false)
const successMessage = ref('')

// Drives the premium progress panel shown during finalize: 'running' while the
// request is in flight, 'done' once it succeeds (lets the bar fill to 100%
// before the success card takes over). Reverts to 'idle' on error.
const installStatus = ref<'idle' | 'running' | 'done'>('idle')
const installActive = computed(() => installStatus.value !== 'idle')

const stepComponents: Record<number, Component> = {
    1: markRaw(StepSystemCheck),
    2: markRaw(StepLicense),
    3: markRaw(StepDatabase),
    4: markRaw(StepSiteSetup),
    5: markRaw(StepAdminAccount),
    6: markRaw(StepFinal),
}

const activeComponent = computed(() => stepComponents[props.currentStep])

// One error string for the active step, sourced from (in order): the finalize
// call, a flashed controller error (DB/license), or Laravel field-validation
// errors returned as a 422. Rendered inline inside the step — no toast.
const errorMessage = computed<string | null>(() => {
    if (installError.value) return installError.value

    const flash = (page.props.flash as any)?.error
    if (flash) return String(flash)

    const errors = page.props.errors as Record<string, string> | undefined
    if (errors && Object.keys(errors).length) {
        return Object.values(errors)[0]
    }

    return null
})

const stepRef = ref<InstanceType<typeof StepSystemCheck> | null>(null)

const isFirstStep = computed(() => props.currentStep === 1)
const isLastStep = computed(() => props.currentStep === 6)

const canGoNext = computed(() => {
    if (submitting.value) return false
    if (props.currentStep === 1 && !props.allPass) return false
    return true
})

function goBack() {
    if (submitting.value) return

    router.post(
        route('install.goto-step', { step: props.currentStep - 1 }),
        {},
        { preserveScroll: true },
    )
}

function goNext() {
    if (submitting.value) return

    const data = (stepRef.value as any)?.getData?.() ?? {}

    router.post(
        route('install.step', { step: props.currentStep }),
        data,
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
        },
    )
}

async function finalizeInstall() {
    installing.value = true
    installError.value = null
    installStatus.value = 'running'

    try {
        const response = await axios.post(route('install.finalize'))
        if (response.data.success) {
            // Let the progress bar animate to 100% before swapping to the
            // success card.
            installStatus.value = 'done'
            successMessage.value = response.data.message
            setTimeout(() => {
                showSuccess.value = true
                installing.value = false
            }, 900)
            setTimeout(() => {
                window.location.href = response.data.redirect
            }, 3900)
        } else {
            installError.value = response.data.message || 'Installation failed. Check the logs for details.'
            installing.value = false
            installStatus.value = 'idle'
        }
    } catch (e: any) {
        installError.value = e.response?.data?.message || 'Installation failed. Please try again.'
        installing.value = false
        installStatus.value = 'idle'
    }
}
</script>

<template>
    <Head title="MakeAI - Installation Wizard" />

    <div>
        <!-- Step Indicator -->
        <StepIndicator
            v-if="!showSuccess && !installActive"
            :current-step="currentStep"
            :steps-completed="stepsCompleted"
            :total-steps="totalSteps"
        />

        <!-- Install Progress -->
        <InstallProgress
            v-if="installActive && !showSuccess"
            :status="installStatus === 'done' ? 'done' : 'running'"
        />

        <!-- Success State -->
        <div
            v-if="showSuccess"
            class="my-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center text-emerald-800 shadow-md animate-fade-in"
        >
            <div class="flex flex-col items-center justify-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Installation Completed!</h3>
                <p class="max-w-md text-sm text-slate-600">{{ successMessage }}</p>
                <div class="mt-4 flex items-center gap-2 text-xs text-slate-500">
                    <svg class="h-4 w-4 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>Redirecting to the login page in 3 seconds...</span>
                </div>
            </div>
        </div>

        <template v-if="!showSuccess && !installActive">
            <!-- Active Step — each step renders the error inline after its title/description -->
            <div class="min-h-[300px]">
                <component
                    :is="activeComponent"
                    ref="stepRef"
                    :form-data="formData"
                    :system-check="systemCheck"
                    :all-pass="allPass"
                    :cron-command="cronCommand"
                    :db-state="dbState"
                    :error="errorMessage"
                />
            </div>

            <!-- Navigation Buttons -->
            <div class="mt-8 flex items-center justify-between">
                <!-- Back -->
                <button
                    v-if="!isFirstStep"
                    type="button"
                    :disabled="submitting"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="goBack"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back
                </button>
                <div v-else />

                <!-- Next / Install -->
                <button
                    v-if="!isLastStep"
                    type="button"
                    :disabled="!canGoNext"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-600 bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="goNext"
                >
                    {{ submitting ? 'Please wait...' : 'Next' }}
                    <svg v-if="submitting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <button
                    v-else
                    type="button"
                    :disabled="installing"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-lg transition-all hover:bg-emerald-500 hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-60"
                    @click="finalizeInstall"
                >
                    <svg v-if="installing" class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                    </svg>
                    {{ installing ? 'Installing...' : 'Install Now' }}
                </button>
            </div>
        </template>
    </div>
</template>
