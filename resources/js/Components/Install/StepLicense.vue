<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

const props = defineProps<{
    formData: Record<string, any>
}>()

const page = usePage()
const licenseTestMode = computed(() => !!page.props.licenseTestMode)

const purchaseCode = ref(props.formData?.step_4?.purchase_code ?? '')
const { t } = useTranslate()

function applyMask(value: string) {
    if (licenseTestMode.value) {
        return value.replace(/[^a-z0-9-]/gi, '').slice(0, 50).toUpperCase()
    }
    return value.replace(/[^a-f0-9-]/gi, '').slice(0, 36).toLowerCase()
}

function onInput(e: Event) {
    purchaseCode.value = applyMask((e.target as HTMLInputElement).value)
}

defineExpose({ getData: () => ({ purchase_code: purchaseCode.value.trim() }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ t('License Activation') }}</h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ t('Enter your Envato purchase code to activate :app. Find it in your', { app: t('Application') }) }}
            <a href="https://codecanyon.net/downloads" target="_blank" rel="noopener" class="text-blue-600 underline hover:text-blue-500">{{ t('CodeCanyon downloads') }}</a>.
        </p>

        <div class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Purchase Code') }}</span>
                <input
                    :value="purchaseCode"
                    @input="onInput"
                    type="text"
                    autocomplete="off"
                    :placeholder="t('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')"
                    class="mt-1.5 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                />
            </label>

            <div v-if="licenseTestMode" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                <p class="font-semibold">{{ t('Developer test mode active') }}</p>
                <p class="mt-1 text-xs">{{ t('Use one of these fake codes to test license-gated features without contacting Envato:') }}</p>
                <ul class="mt-2 list-disc list-inside space-y-1 text-xs font-mono">
                    <li>TEST-LICENSE-0000-REGULAR</li>
                    <li>TEST-LICENSE-0000-EXTENDED</li>
                </ul>
            </div>

            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                <p class="font-semibold">{{ t('Why activate?') }}</p>
                <ul class="mt-2 list-disc list-inside space-y-1 text-xs">
                    <li>{{ t('Unlock all AI features and tools') }}</li>
                    <li>{{ t('Receive automatic updates') }}</li>
                    <li>{{ t('Access premium support') }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>
