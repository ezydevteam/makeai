<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()
</script>

<template>
    <Head title="AI Content Repurposer - Overview" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ t('AI Content Repurposer') }}
                </h1>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Total Jobs') }}</h3>
                    <p class="mt-2 text-3xl font-bold text-primary-600 dark:text-primary-400">0</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Repurposed content jobs') }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Completed') }}</h3>
                    <p class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">0</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Successfully processed') }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Pending') }}</h3>
                    <p class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">0</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Awaiting processing') }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Quick Links') }}</h3>
                <div class="flex flex-wrap gap-4">
                    <a :href="route('addon.rp.admin.settings')" class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                        {{ t('Configure Settings') }}
                    </a>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
