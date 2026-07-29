<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import Layout from '@themes/default/js/Layouts/AppLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

/**
 * Paddle's default payment link.
 *
 * Paddle is the one gateway here that does not host its own checkout page: it builds a
 * checkout URL by appending `?_ptxn=<transaction>` to a URL on OUR domain, and expects
 * Paddle.js on that page to open the overlay. So this page exists only to load Paddle.js
 * and get out of the way — the buyer should see the overlay, not this.
 */
const props = defineProps<{
    transactionId: string
    clientToken: string
    environment: 'sandbox' | 'production'
    returnUrl: string
    fallbackUrl: string
}>()

const { t } = useTranslate()
const page = usePage()

const branding = computed(() => (page.props.branding as { site_name?: string; site_logo_light?: string; site_logo_dark?: string }) ?? {})
const logoLight = computed(() => String(branding.value.site_logo_light || ''))
const logoDark = computed(() => String(branding.value.site_logo_dark || logoLight.value))

const PADDLE_JS = 'https://cdn.paddle.com/paddle/v2/paddle.js'

const failed = ref(false)
const ready = ref(false)

declare global {
    interface Window {
        Paddle?: {
            Environment: { set: (env: string) => void }
            Initialize: (options: Record<string, unknown>) => void
            Checkout: { open: (options: Record<string, unknown>) => void }
        }
    }
}

const loadPaddleJs = () =>
    new Promise<void>((resolve, reject) => {
        if (window.Paddle) {
            resolve()
            return
        }

        // Reuse the tag if a previous visit to this page already added it, so a back/forward
        // navigation doesn't stack duplicate scripts.
        const existing = document.querySelector<HTMLScriptElement>(`script[src="${PADDLE_JS}"]`)

        if (existing) {
            existing.addEventListener('load', () => resolve(), { once: true })
            existing.addEventListener('error', () => reject(new Error('paddle.js failed')), { once: true })
            return
        }

        const script = document.createElement('script')
        script.src = PADDLE_JS
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('paddle.js failed'))
        document.head.appendChild(script)
    })

/**
 * Opening it by hand rather than leaning on Paddle.js's own `_ptxn` auto-open. Both work,
 * but only this way is there a definite moment to fall back from when something is wrong —
 * a blocked script, a token the dashboard hasn't approved for this domain, a transaction
 * that has already been paid. Silence on a payment page is the one outcome to avoid.
 */
const openCheckout = () => {
    if (!window.Paddle || !props.transactionId) {
        failed.value = true
        return
    }

    try {
        window.Paddle.Checkout.open({
            transactionId: props.transactionId,
            settings: { successUrl: props.returnUrl },
        })
        ready.value = true
    } catch {
        failed.value = true
    }
}

let cancelled = false

onMounted(async () => {
    if (!props.transactionId) {
        failed.value = true
        return
    }

    try {
        await loadPaddleJs()

        if (cancelled || !window.Paddle) return

        window.Paddle.Environment.set(props.environment)
        window.Paddle.Initialize({ token: props.clientToken })

        openCheckout()
    } catch {
        failed.value = true
    }
})

onBeforeUnmount(() => {
    cancelled = true
})
</script>

<template>
    <Head :title="t('Secure checkout')" />

    <Layout>
        <div class="pointer-events-none fixed inset-0 z-0" aria-hidden="true">
            <div class="absolute inset-y-0 left-0 w-1/3 max-w-md bg-gradient-to-r from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
            <div class="absolute inset-y-0 right-0 w-1/3 max-w-md bg-gradient-to-l from-primary-500/10 via-primary-500/5 to-transparent dark:from-primary-500/15 dark:via-primary-500/5"></div>
        </div>

        <div class="relative z-10 w-full pt-8 md:pt-12 pb-16">
            <div class="mb-8 flex justify-center px-4">
                <Link href="/" class="inline-flex items-center">
                    <img v-if="logoLight" :src="logoLight" :alt="branding.site_name || 'Logo'" class="h-9 w-auto dark:hidden" />
                    <img v-if="logoDark" :src="logoDark" :alt="branding.site_name || 'Logo'" class="hidden h-9 w-auto dark:block" />
                    <span v-if="!logoLight && !logoDark" class="text-xl font-black text-gray-900 dark:text-white">{{ branding.site_name }}</span>
                </Link>
            </div>

            <div class="flex justify-center px-4 sm:px-6">
                <div class="w-full max-w-md text-center">
                    <template v-if="!failed">
                        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                            <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                            </svg>
                        </div>

                        <h1 class="text-2xl font-black text-gray-900 dark:text-white">{{ t('Opening secure checkout') }}</h1>
                        <p class="mt-3 text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">
                            {{ t('Your payment window is loading. Please do not close this page.') }}
                        </p>

                        <!-- The overlay can be dismissed by mistake, and nothing on the page
                             would bring it back. -->
                        <button
                            v-if="ready"
                            type="button"
                            class="mt-6 block w-full rounded-xl btn-primary text-center shadow-lg shadow-primary-600/20 transition"
                            @click="openCheckout"
                        >
                            {{ t('Reopen payment window') }}
                        </button>
                    </template>

                    <template v-else>
                        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <h1 class="text-2xl font-black text-gray-900 dark:text-white">{{ t('The payment window could not open') }}</h1>
                        <p class="mt-3 text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">
                            {{ t('No charge has been made. This is usually a browser extension or ad blocker stopping the payment script — try again with it disabled, or use a different browser.') }}
                        </p>

                        <Link :href="fallbackUrl" class="mt-6 block w-full rounded-xl btn-primary text-center shadow-lg shadow-primary-600/20 transition">
                            {{ t('Back to my payment') }}
                            <i class="ti ti-arrow-up-right"></i>
                        </Link>
                    </template>

                    <div class="mt-5">
                        <Link href="/pricing" class="text-sm font-medium text-gray-300 underline-offset-4 transition hover:text-primary-600 hover:underline dark:text-gray-400 dark:hover:text-primary-400">
                            <i class="ti ti-arrow-left"></i>
                            {{ t('Back to pricing') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>
