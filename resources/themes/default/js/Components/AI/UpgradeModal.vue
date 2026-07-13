<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppModal from '@/Components/UI/AppModal.vue'

const props = withDefaults(defineProps<{
    open: boolean
}>(), {
    open: false,
})

const emit = defineEmits<{
    close: []
}>()

const { t } = useTranslate()
const upgradeBtnRef = ref<HTMLButtonElement | null>(null)

const page = usePage()

const isProAvailable = computed(() => Boolean(page.props.isProAvailable))

const goToPricing = () => {
    router.visit(route('pricing'))
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        nextTick(() => upgradeBtnRef.value?.focus())
    }
})
</script>

<template>
    <AppModal
        :open="open"
        max-width="max-w-sm"
        :title="isProAvailable ? t('Pro feature') : t('Feature unavailable')"
        :subtitle="isProAvailable ? t('This tool requires a Pro subscription. Upgrade to unlock all advanced features.') : t('Premium features are not available on this installation.')"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-accent-500/10">
                <svg class="h-7 w-7 text-accent-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
            </div>

            <template v-if="isProAvailable">
                <button
                    ref="upgradeBtnRef"
                    class="w-full rounded-xl bg-gradient-to-r from-accent-500 to-amber-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-accent-500/25 transition-all hover:-translate-y-0.5 hover:shadow-accent-500/35"
                    @click="goToPricing"
                >
                    {{ t('View Plans') }}
                </button>
            </template>

            <div class="text-center">
                <button
                    class="text-xs text-gray-500 hover:text-gray-400"
                    @click="emit('close')"
                >
                    {{ t('Go back') }}
                </button>
            </div>
        </div>
    </AppModal>
</template>
