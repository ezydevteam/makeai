<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    user: any,
    plans: Array<{ id: number, name: string }>
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    credits: props.user.credits,
    plan_id: props.user.plan_id,
    is_active: props.user.is_active,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('admin.users.update', props.user.ulid), {
        onSuccess: () => {
            form.password = '';
            form.password_confirmation = '';
        }
    });
};
</script>

<template>
    <Head :title="`${user.name} — User Details`" />
    <div class="max-w-5xl mx-auto px-6 py-8">
        <div class="flex items-center gap-3 mb-8">
            <Link :href="route('admin.users.index')" class="p-2 bg-gray-100 rounded-lg hover:bg-gray-200 text-gray-500 hover:text-gray-900 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit User: {{ user.name }}</h1>
                <p class="text-sm text-gray-500">ULID: <span class="font-mono text-gray-400">{{ user.ulid }}</span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Profile Info -->
            <div class="lg:col-span-2 space-y-6">
                <form @submit.prevent="submit" class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="font-bold text-gray-900">Account Details</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                <input v-model="form.name" type="text" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input v-model="form.email" type="email" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Credits Balance</label>
                                <input v-model="form.credits" type="number" step="0.01" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Active Plan</label>
                                <select v-model="form.plan_id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:outline-none">
                                    <option :value="null">No Plan (Free)</option>
                                    <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <h4 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider">Change Password</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                    <input v-model="form.password" type="password" placeholder="Leave blank to keep current" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                    <input v-model="form.password_confirmation" type="password" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:outline-none" />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 pt-4">
                            <button type="button" @click="form.is_active = !form.is_active" :class="form.is_active ? 'bg-success-500' : 'bg-gray-300'" class="relative w-12 h-6 rounded-full transition-colors">
                                <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform shadow-sm" :class="form.is_active ? 'translate-x-6' : 'translate-x-0'"></span>
                            </button>
                            <span class="text-sm font-medium text-gray-700">{{ form.is_active ? 'User account is active' : 'User account is disabled' }}</span>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-3">
                        <Link :href="route('admin.users.index')" class="px-5 py-2.5 text-sm font-bold text-gray-600 hover:text-gray-900 transition-colors">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-colors shadow-lg shadow-primary-500/20 disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Update User' }}
                        </button>
                    </div>
                </form>

                <!-- Usage Stats -->
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Today Usage</p>
                        <p class="text-xl font-bold text-gray-900">{{ parseFloat(user.credits_used_today).toFixed(2) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Month Usage</p>
                        <p class="text-xl font-bold text-gray-900">{{ parseFloat(user.credits_used_month).toFixed(2) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Total Referrals</p>
                        <p class="text-xl font-bold text-gray-900">{{ user.referral_count }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Referral Earned</p>
                        <p class="text-xl font-bold text-gray-900">${{ parseFloat(user.referral_earnings).toFixed(2) }}</p>
                    </div>
                </div>
            </div>

            <!-- Right: Activity & Sidebar -->
            <div class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <form @submit.prevent="router.post(route('admin.users.impersonate', user.ulid))">
                            <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 bg-accent-600 text-white font-bold text-sm rounded-xl hover:bg-accent-500 transition-colors shadow-lg shadow-accent-500/20">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                Impersonate User
                            </button>
                        </form>
                        <button class="w-full py-3 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-sm rounded-xl hover:bg-gray-100 transition-colors">
                            Send Reset Email
                        </button>
                        <button class="w-full py-3 bg-gray-50 border border-gray-200 text-gray-700 font-bold text-sm rounded-xl hover:bg-gray-100 transition-colors">
                            Clear Session
                        </button>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="font-bold text-gray-900">Recent Logins</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="log in user.login_history" :key="log.id" class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">{{ log.ip }}</p>
                            <p class="text-[10px] text-gray-500 truncate mb-1">{{ log.user_agent }}</p>
                            <p class="text-[10px] text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</p>
                        </div>
                        <div v-if="!user.login_history?.length" class="px-6 py-8 text-center text-xs text-gray-500 italic">
                            No login history available.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
