<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

defineProps<{
    formData: Record<string, any>
    demoExists: boolean
    demoPreview: string | null
}>()

const installDemo = ref(true)
const demoMethod = ref<'file' | 'upload'>('file')
const demoFile = ref<File | null>(null)
const { t } = useTranslate()

function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement
    if (input.files?.length) {
        demoFile.value = input.files[0]
    }
}

defineExpose({
    getData: () => ({
        install_demo: installDemo.value,
        demo_method: demoMethod.value,
        demo_file: demoFile.value,
    }),
})
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('Demo Content') }}</h2>
        <p class="mt-1 text-sm text-gray-500">{{ t('Jumpstart your site with pre-built demo content, or start from scratch.') }}</p>

        <div class="mt-6 space-y-5">
            <!-- Toggle -->
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    v-model="installDemo"
                    type="checkbox"
                    class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Install demo content') }}</span>
            </label>

            <!-- When demo enabled -->
            <template v-if="installDemo">
                <!-- Built-in option -->
                <div v-if="demoExists" class="rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20 overflow-hidden">
                    <label class="flex items-start gap-4 p-4 cursor-pointer">
                        <input
                            v-model="demoMethod"
                            type="radio"
                            value="file"
                            class="mt-0.5 h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-blue-800 dark:text-blue-200">{{ t('Use built-in demo content') }}</p>
                            <p class="mt-1 text-xs text-primary-600 dark:text-primary-400">
                                {{ t('Installs sample AI tools, blog posts, pages, and pricing plans.') }}
                            </p>

                            <!-- Preview thumbnail -->
                            <img
                                v-if="demoPreview"
                                :src="demoPreview"
                                :alt="t('Demo preview')"
                                class="mt-3 rounded-lg border border-blue-200 dark:border-blue-600 max-w-xs"
                            />
                        </div>
                    </label>
                </div>

                <!-- No built-in demo option -->
                <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                    <p class="font-semibold">{{ t('No demo content file found') }}</p>
                    <p class="mt-1">{{ t('Place a') }} <code class="bg-amber-200 px-1 rounded dark:bg-amber-800">demo.sql</code> {{ t('file in') }} <code class="bg-amber-200 px-1 rounded dark:bg-amber-800">database/demo/</code> {{ t('or upload one below.') }}</p>
                </div>

                <!-- Upload option -->
                <label class="flex items-start gap-4 cursor-pointer rounded-lg border border-gray-200 dark:border-surface-700 p-4">
                    <input
                        v-model="demoMethod"
                        type="radio"
                        value="upload"
                        class="mt-0.5 h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Upload custom SQL file') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ t('Upload a') }} <code>.sql</code> {{ t('file with your demo content (max 50MB).') }}</p>

                        <input
                            v-if="demoMethod === 'upload'"
                            type="file"
                            accept=".sql"
                            class="mt-3 block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/20 dark:file:text-blue-300"
                            @change="onFileChange"
                        />
                    </div>
                </label>

                <!-- Features list -->
                <div class="rounded-lg border border-gray-200 dark:border-surface-700 p-4">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ t('Demo content includes:') }}</p>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ t('Sample AI tools across multiple categories') }}
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ t('Demo blog posts and CMS pages') }}
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ t('Preconfigured pricing plans') }}
                        </li>
                    </ul>
                </div>
            </template>

            <!-- When demo disabled -->
            <div v-else class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-400">
                {{ t('You\'ll start with a clean installation. You can add content later from the admin panel.') }}
            </div>
        </div>
    </div>
</template>
