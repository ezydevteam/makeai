<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import Layout from '@/Layouts/AppLayout.vue';

interface Plan {
    id: number; name: string; slug: string; description: string
    price_monthly: number; price_yearly: number; credits: number
    features: string[]; is_featured: boolean; is_free: boolean
    yearly_savings: number
}
interface CreditPack {
    id: number; name: string; credits: number; price: number; is_popular: boolean
}

const props = defineProps<{ plans: Plan[]; creditPacks: CreditPack[] }>()
const billing = ref<'monthly' | 'yearly'>('monthly')
</script>

<template>
    <Head title="Pricing" />

    <Layout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
            <!-- Title -->
            <div class="text-center mb-16">
                <h1 class="text-4xl sm:text-5xl font-black text-gray-900 mb-4 tracking-tight">
                    Simple, transparent <span class="bg-gradient-to-r from-primary-600 to-accent-600 bg-clip-text text-transparent">pricing</span>
                </h1>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto font-medium">Choose the plan that fits your needs. Upgrade or downgrade at any time.</p>

                <!-- Billing toggle -->
                <div class="flex items-center justify-center gap-4 mt-10">
                    <span :class="billing === 'monthly' ? 'text-gray-900' : 'text-gray-400'" class="text-sm font-bold">Monthly</span>
                    <button @click="billing = billing === 'monthly' ? 'yearly' : 'monthly'" class="relative w-14 h-8 rounded-full transition-all duration-300 shadow-inner" :class="billing === 'yearly' ? 'bg-primary-600' : 'bg-gray-200'">
                        <span class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow-md transition-transform duration-300" :class="billing === 'yearly' ? 'translate-x-6' : 'translate-x-0'"></span>
                    </button>
                    <span :class="billing === 'yearly' ? 'text-gray-900' : 'text-gray-400'" class="text-sm font-bold">Yearly</span>
                    <span v-if="billing === 'yearly'" class="px-3 py-1 bg-success-100 text-success-600 text-[10px] font-black rounded-full uppercase tracking-wider">Save 20%</span>
                </div>
            </div>

            <!-- Plan Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-24">
                <div v-for="plan in plans" :key="plan.id" :class="[plan.is_featured ? 'border-primary-200 bg-white shadow-2xl shadow-primary-500/10 scale-105 z-10' : 'border-gray-100 bg-white hover:border-gray-200']" class="relative border rounded-3xl p-8 flex flex-col transition-all duration-300">
                    <!-- Featured badge -->
                    <div v-if="plan.is_featured" class="absolute -top-4 left-1/2 -translate-x-1/2 px-5 py-1.5 bg-gradient-to-r from-primary-600 to-accent-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg">
                        Recommended
                    </div>

                    <h3 class="text-xl font-black text-gray-900 mb-1">{{ plan.name }}</h3>
                    <p class="text-sm text-gray-500 font-medium mb-6 leading-relaxed">{{ plan.description }}</p>

                    <!-- Price -->
                    <div class="mb-6">
                        <div class="flex items-end gap-1">
                            <span class="text-4xl font-black text-gray-900 tracking-tight">${{ billing === 'monthly' ? plan.price_monthly : (plan.price_yearly / 12).toFixed(2) }}</span>
                            <span v-if="!plan.is_free" class="text-sm text-gray-400 font-bold mb-1">/month</span>
                        </div>
                        <p v-if="!plan.is_free && billing === 'yearly'" class="text-xs text-primary-600 font-bold mt-1">Billed ${{ plan.price_yearly }} annually</p>
                    </div>

                    <!-- Credits -->
                    <div class="flex items-center gap-3 mb-6 px-4 py-3 bg-gray-50 rounded-2xl border border-gray-100">
                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center text-primary-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                        </div>
                        <div>
                            <p class="text-lg font-black text-gray-900 leading-none">{{ plan.credits.toLocaleString() }}</p>
                            <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mt-1">Credits / Mo</p>
                        </div>
                    </div>

                    <!-- Features -->
                    <ul class="space-y-3.5 flex-1 mb-8">
                        <li v-for="f in (typeof plan.features === 'string' ? JSON.parse(plan.features) : plan.features)" :key="f" class="flex items-start gap-3 text-sm text-gray-600 font-medium leading-tight">
                            <svg class="w-5 h-5 text-success-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                            {{ f }}
                        </li>
                    </ul>

                    <!-- CTA -->
                    <Link :href="route('register')" :class="[plan.is_featured ? 'bg-primary-600 text-white shadow-xl shadow-primary-600/20 hover:bg-primary-500' : 'bg-gray-100 text-gray-900 hover:bg-gray-200']" class="block text-center py-4 rounded-2xl font-black text-sm transition-all hover:-translate-y-1">
                        {{ plan.is_free ? 'Start Free' : 'Choose ' + plan.name }}
                    </Link>
                </div>
            </div>

            <!-- Credit Packs -->
            <div class="text-center mb-12">
                <h2 class="text-3xl font-black text-gray-900 mb-3">Need more power?</h2>
                <p class="text-gray-500 font-medium">Top up your credits instantly with our one-time packs.</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto">
                <div v-for="pack in creditPacks" :key="pack.id" :class="[pack.is_popular ? 'border-primary-200 bg-primary-50/30' : 'border-gray-100 bg-white shadow-sm']" class="relative border rounded-3xl p-6 text-center hover:border-primary-300 transition-all group">
                    <div v-if="pack.is_popular" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-primary-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full">Popular</div>
                    <p class="text-3xl font-black text-gray-900 mb-1 group-hover:scale-110 transition-transform">{{ pack.credits.toLocaleString() }}</p>
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-4">credits</p>
                    <p class="text-xl font-black text-primary-600">${{ pack.price }}</p>
                </div>
            </div>
        </div>
    </Layout>
</template>
