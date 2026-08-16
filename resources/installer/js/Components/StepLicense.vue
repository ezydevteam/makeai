<script setup lang="ts">
import { ref } from 'vue'
import { applyPurchaseCodeMask } from '@/lib/purchaseCode'
import ErrorAlert from './ErrorAlert.vue'

const props = defineProps<{
    formData: Record<string, any>
    error?: string | null
}>()

const purchaseCode = ref(props.formData?.step_2?.purchase_code ?? '')

function onInput(e: Event) {
    purchaseCode.value = applyPurchaseCodeMask((e.target as HTMLInputElement).value, true)
}

defineExpose({ getData: () => ({ purchase_code: purchaseCode.value.trim() }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">License Activation</h2>
        <p class="mt-1 text-sm text-slate-500">
            Enter your Envato purchase code to activate MakeAI. Find it in your
            <a href="https://codecanyon.net/downloads" target="_blank" rel="noopener" class="text-[#1F75FE] underline hover:text-[#1b65da]">CodeCanyon downloads</a>.
        </p>

        <ErrorAlert :message="error" />

        <div class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Purchase Code</span>
                <input
                    :value="purchaseCode"
                    @input="onInput"
                    type="text"
                    autocomplete="off"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm"
                />
            </label>

            <div class="rounded-xl border border-[#b7d3ff] bg-[#edf4ff] p-4 text-sm text-[#1757bc]">
                <p class="font-semibold">Why activate?</p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
                    <li>Unlock all AI features and tools</li>
                    <li>Receive automatic updates</li>
                    <li>Access premium support</li>
                </ul>
            </div>
        </div>
    </div>
</template>
