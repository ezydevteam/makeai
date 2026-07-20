<script setup lang="ts">
import { ref } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

const emit = defineEmits<{ save: [name: string]; close: [] }>()
const { t } = useTranslate()
const name = ref('')
const loading = ref(false)

async function save() {
    if (!name.value.trim()) return
    loading.value = true
    emit('save', name.value.trim())
    loading.value = false
}
</script>

<template>
    <div class="modal modal-open" @click.self="emit('close')">
        <div class="modal-box rounded-2xl max-w-sm">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-3 top-3" @click="emit('close')">
                <i class="ti ti-x"></i>
            </button>
            <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mb-4">
                <i class="ti ti-books text-2xl text-primary"></i>
            </div>
            <h3 class="text-lg font-bold mb-1">{{ t('Save to Knowledge Base') }}</h3>
            <p class="text-sm text-base-content/50 mb-4">
                {{ t('Save this document and its chat history to your permanent Knowledge Base for future reference.') }}
            </p>
            <div class="form-control">
                <label class="label pb-1"><span class="label-text font-medium">{{ t('Collection name') }}</span></label>
                <input
                    v-model="name"
                    type="text"
                    class="input input-bordered w-full rounded-xl"
                    :placeholder="t('e.g. Annual Reports 2025')"
                    @keydown.enter="save"
                />
            </div>
            <div class="modal-action mt-5">
                <button class="btn btn-ghost btn-sm rounded-lg" @click="emit('close')" :disabled="loading">{{ t('Cancel') }}</button>
                <button class="btn btn-primary btn-sm rounded-lg" @click="save" :disabled="!name.trim() || loading">
                    <span v-if="loading" class="loading loading-spinner loading-xs"></span>
                    {{ t('Save') }}
                </button>
            </div>
        </div>
    </div>
</template>
