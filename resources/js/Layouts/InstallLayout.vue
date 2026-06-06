<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()
const page = usePage()
const appName = computed(() => String(page.props.appName || t('Application')))
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-6"
        style="background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 50%, #2563eb 100%)">
        <div class="w-full max-w-2xl">
            <!-- Header -->
            <div class="text-center mb-4">
                <h1 class="text-3xl font-bold text-white tracking-tight">{{ appName }}</h1>
                <p class="mt-1 text-blue-200 text-sm">{{ t('Installation Wizard') }}</p>
            </div>

            <!-- Card -->
            <div class="bg-white dark:bg-surface-900 rounded-2xl shadow-2xl p-6 sm:p-8">
                <slot />
            </div>

            <!-- Footer -->
            <p class="mt-4 text-center text-xs text-blue-300">
                &copy; {{ new Date().getFullYear() }} {{ appName }}. {{ t('All rights reserved.') }}
            </p>
        </div>
    </div>
</template>
