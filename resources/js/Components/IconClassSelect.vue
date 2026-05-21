<script setup lang="ts">
import { computed } from 'vue'
import { useTranslate } from '@/Composables/useTranslate'

interface IconOption {
    className: string
    label: string
}

const props = withDefaults(defineProps<{
    modelValue: string
    label?: string
    error?: string
    placeholder?: string
}>(), {
    label: '',
    error: '',
    placeholder: '',
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const { t } = useTranslate()

const iconOptions: IconOption[] = [
    { className: 'ti ti-home', label: 'Home' },
    { className: 'ti ti-layout-dashboard', label: 'Dashboard' },
    { className: 'ti ti-sparkles', label: 'AI Tools' },
    { className: 'ti ti-news', label: 'Blog' },
    { className: 'ti ti-file-text', label: 'Pages' },
    { className: 'ti ti-book-open', label: 'Docs' },
    { className: 'ti ti-help-circle', label: 'Help' },
    { className: 'ti ti-message-circle', label: 'Contact' },
    { className: 'ti ti-users', label: 'Users' },
    { className: 'ti ti-user', label: 'Account' },
    { className: 'ti ti-login', label: 'Login' },
    { className: 'ti ti-user-plus', label: 'Register' },
    { className: 'ti ti-credit-card', label: 'Pricing' },
    { className: 'ti ti-receipt', label: 'Billing' },
    { className: 'ti ti-tags', label: 'Tags' },
    { className: 'ti ti-gift', label: 'Offer' },
    { className: 'ti ti-star', label: 'Featured' },
    { className: 'ti ti-rocket', label: 'Launch' },
    { className: 'ti ti-bell', label: 'Notification' },
    { className: 'ti ti-mail', label: 'Mail' },
    { className: 'ti ti-search', label: 'Search' },
    { className: 'ti ti-globe', label: 'Website' },
    { className: 'ti ti-shield', label: 'Security' },
    { className: 'ti ti-lock', label: 'Protected' },
    { className: 'ti ti-settings', label: 'Settings' },
    { className: 'ti ti-palette', label: 'Appearance' },
    { className: 'ti ti-menu-2', label: 'Menu' },
    { className: 'ti ti-link', label: 'Link' },
    { className: 'ti ti-external-link', label: 'External Link' },
    { className: 'ti ti-shopping-cart', label: 'Checkout' },
    { className: 'ti ti-chart-bar', label: 'Analytics' },
]

const selectedIcon = computed(() => props.modelValue || '')

const updateValue = (event: Event) => {
    emit('update:modelValue', (event.target as HTMLSelectElement).value)
}
</script>

<template>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
        <span v-if="label">{{ label }}</span>
        <div class="mt-2 flex items-center gap-2">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-500 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300">
                <i v-if="selectedIcon" :class="selectedIcon" aria-hidden="true" />
                <i v-else class="ti ti-menu-2" aria-hidden="true" />
            </div>
            <select
                :value="modelValue"
                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                @change="updateValue"
            >
                <option value="">{{ t('No icon') }}</option>
                <option v-for="icon in iconOptions" :key="icon.className" :value="icon.className">
                    {{ t(icon.label) }} - {{ icon.className }}
                </option>
            </select>
        </div>
        <span v-if="error" class="mt-1 block text-xs text-danger-600">{{ error }}</span>
    </label>
</template>
