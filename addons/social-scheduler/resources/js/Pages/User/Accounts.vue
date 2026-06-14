<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{
    accounts: { id: number; platform: string; platform_label: string; platform_username: string | null; platform_name: string | null; avatar_url: string | null; account_type: string; is_active: boolean; is_token_expired: boolean; follower_count: number; connected_at: string }[]
    platforms_list: { slug: string; label: string; icon: string }[]
}>()

const platformColor = (p: string) => ({
    instagram: 'border-pink-500', facebook: 'border-blue-500',
    twitter: 'border-sky-500', linkedin: 'border-blue-700',
}[p] ?? 'border-gray-400')
</script>

<template>
    <Head :title="t('Connected Accounts')" />

    <div class="p-6 space-y-6">
        <h1 class="text-xl font-semibold">{{ t('Connected Social Accounts') }}</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="pl in platforms_list" :key="pl.slug"
                 class="card p-4 border-l-4" :class="platformColor(pl.slug)">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium">
                            <i :class="pl.icon" class="mr-2 inline-block"></i>
                            {{ pl.label }}
                        </h3>
                        <template v-if="accounts.find(a => a.platform === pl.slug && a.is_active)">
                            <div v-for="a in accounts.filter(a => a.platform === pl.slug && a.is_active)" :key="a.id"
                                 class="mt-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <img v-if="a.avatar_url" :src="a.avatar_url" class="w-6 h-6 rounded-full" />
                                    <span class="font-medium">{{ a.platform_name || a.platform_username }}</span>
                                    <span v-if="a.account_type !== 'personal'" class="text-xs text-gray-400">({{ a.account_type }})</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    {{ a.follower_count.toLocaleString() }} {{ t('followers') }}
                                    <span v-if="a.is_token_expired" class="text-amber-600 ml-2">{{ t('Token expired') }}</span>
                                </div>
                            </div>
                            <Link v-for="a in accounts.filter(a => a.platform === pl.slug && a.is_active)" :key="'disc'+a.id"
                                  :href="route('addon.social.user.accounts.disconnect', a.id)"
                                  method="delete" as="button" class="text-xs text-red-500 mt-2 inline-block">
                                {{ t('Disconnect') }}
                            </Link>
                        </template>
                        <template v-else>
                            <p class="text-sm text-gray-400 mt-1">{{ t('Not connected') }}</p>
                            <Link :href="route('addon.social.user.accounts.redirect', pl.slug)"
                                  class="btn btn-sm btn-emerald mt-2">
                                {{ t('Connect') }}
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-4 text-sm text-gray-500">
            <p>{{ t('Your credentials are stored encrypted and never shared.') }}</p>
        </div>
    </div>
</template>
