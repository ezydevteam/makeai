<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()
const page = usePage()
const user = computed(() => page.props.auth?.user as any)

interface ChecklistItems {
    create_account: boolean
    verify_email: boolean
    complete_profile: boolean
    first_document: boolean
    saved_favorite: boolean
    brand_voice: boolean
}

const items = ref<ChecklistItems>({
    create_account: true,
    verify_email: false,
    complete_profile: false,
    first_document: false,
    saved_favorite: false,
    brand_voice: false,
})
const checked = ref(false)
const loading = ref(true)
const dismissed = ref(false)

const checklistDefs = [
    { key: 'create_account', label: t('Create your account'), route: '', icon: 'ti ti-user-check' },
    { key: 'verify_email', label: t('Verify your email'), route: 'verification.notice', icon: 'ti ti-mail-check' },
    { key: 'complete_profile', label: t('Complete your profile'), route: 'user.dashboard.profile', icon: 'ti ti-user-circle' },
    { key: 'first_document', label: t('Create your first document'), route: 'ai.tools.index', icon: 'ti ti-file-text' },
    { key: 'saved_favorite', label: t('Save a favorite tool'), route: 'ai.tools.index', icon: 'ti ti-star' },
    { key: 'brand_voice', label: t('Set your brand voice'), route: 'user.dashboard.profile', icon: 'ti ti-palette' },
] as const

const total = checklistDefs.length
const completed = computed(() => checklistDefs.filter(d => items.value[d.key as keyof ChecklistItems]).length)
const percent = computed(() => total > 0 ? Math.round((completed.value / total) * 100) : 0)

onMounted(async () => {
    if (checked.value) {
        loading.value = false
        return
    }
    try {
        const response = await fetch(route('user.dashboard.onboarding.checklist'))
        const data = await response.json()
        checked.value = data.checked ?? false
        if (data.items) {
            items.value = { ...items.value, ...data.items }
        }
    } catch {
        // use defaults
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div v-if="!dismissed" class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
            <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Getting Started') }}</h2>
            <button
                @click="dismissed = true"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
                :aria-label="t('Dismiss')"
            >
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-10">
            <i class="ti ti-loader-2 animate-spin text-xl text-[#1F75FE]"></i>
        </div>

        <div v-else class="p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                    <div
                        class="h-full rounded-full bg-[#1F75FE] transition-all duration-500"
                        :style="{ width: `${percent}%` }"
                    ></div>
                </div>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ percent }}%</span>
            </div>

            <ul class="space-y-2">
                <li
                    v-for="def in checklistDefs"
                    :key="def.key"
                    class="flex items-center gap-3"
                >
                    <span
                        class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 transition"
                        :class="items[def.key as keyof ChecklistItems] ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-800'"
                    >
                        <i
                            v-if="items[def.key as keyof ChecklistItems]"
                            class="ti ti-check text-xs text-green-600"
                        ></i>
                        <i v-else :class="def.icon" class="text-xs text-gray-400"></i>
                    </span>
                    <span class="text-sm"
                        :class="items[def.key as keyof ChecklistItems] ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white font-medium'"
                    >
                        {{ def.label }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
