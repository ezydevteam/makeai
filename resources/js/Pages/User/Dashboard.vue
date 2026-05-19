<script setup lang="ts">
import { Head, usePage, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import UserLayout from '@/Layouts/UserLayout.vue'

defineOptions({ layout: UserLayout })

const page = usePage()
const user = computed(() => page.props.auth?.user as any)

const cancelSubscription = () => {
    if (!confirm('Cancel your subscription at the end of the current period?')) return

    router.post('/subscription/cancel', {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-2">Welcome, {{ user?.name }} 👋</h1>
        <p class="text-gray-500 text-sm mb-8">Your AI workspace is ready.</p>

        <div v-if="user?.subscription_status === 'active' || user?.subscription_status === 'trialing'" class="mb-6 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-primary-400">{{ user.subscription_status }}</p>
                    <h2 class="mt-1 text-lg font-bold text-white">{{ user.subscription_features?.plan_name || 'Current plan' }}</h2>
                    <p v-if="user.subscription_ends_at" class="mt-1 text-sm text-gray-500">Access until {{ new Date(user.subscription_ends_at).toLocaleDateString() }}</p>
                </div>
                <button type="button" class="rounded-xl border border-red-500/30 px-4 py-2 text-sm font-bold text-red-300 transition hover:bg-red-500/10" @click="cancelSubscription">
                    Cancel subscription
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Link :href="route('user.dashboard')" class="bg-white/[0.03] border border-white/5 rounded-2xl p-6 hover:border-primary-500/20 transition-all cursor-pointer group">
                <div class="w-10 h-10 bg-primary-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                </div>
                <h3 class="text-white font-semibold mb-1">AI Chat</h3>
                <p class="text-gray-500 text-sm">Start a conversation with AI</p>
            </Link>

            <Link :href="route('ai.tools.index')" class="bg-white/[0.03] border border-white/5 rounded-2xl p-6 hover:border-accent-500/20 transition-all cursor-pointer group">
                <div class="w-10 h-10 bg-accent-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-accent-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                </div>
                <h3 class="text-white font-semibold mb-1">AI Writer</h3>
                <p class="text-gray-500 text-sm">Generate content with templates</p>
            </Link>

            <Link :href="route('user.dashboard')" class="bg-white/[0.03] border border-white/5 rounded-2xl p-6 hover:border-success-500/20 transition-all cursor-pointer group">
                <div class="w-10 h-10 bg-success-500/10 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" /></svg>
                </div>
                <h3 class="text-white font-semibold mb-1">Image Generator</h3>
                <p class="text-gray-500 text-sm">Create stunning visuals with AI</p>
            </Link>
        </div>
    </div>
</template>
