<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const emit = defineEmits<{
    export: [format: 'md' | 'pdf']
    close: []
}>()

const { t } = useTranslate()
const format = ref<'md' | 'pdf'>('md')

function doExport() {
    emit('export', format.value)
    emit('close')
}
</script>

<template>
    <div class="modal modal-open">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ t('Export Chat') }}</h3>
            <p class="py-2 text-sm text-base-content/70">{{ t('Download this conversation as a file.') }}</p>

            <div class="space-y-2 mt-3">
                <label class="flex items-center gap-3 p-3 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200">
                    <input v-model="format" type="radio" value="md" class="radio radio-sm" />
                    <div>
                        <p class="font-medium text-sm">Markdown</p>
                        <p class="text-xs text-base-content/60">{{ t('Plain text with formatting. Open in any text editor.') }}</p>
                    </div>
                </label>
            </div>

            <div class="modal-action">
                <button class="btn btn-ghost" @click="emit('close')">{{ t('Cancel') }}</button>
                <button class="btn btn-primary" @click="doExport">{{ t('Download') }}</button>
            </div>
        </div>
    </div>
</template>
