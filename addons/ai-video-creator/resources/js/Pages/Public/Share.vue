<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

const props = defineProps<{
    render: { title: string | null; file_url: string | null; thumbnail_url: string | null; duration_actual: number | null }
}>()
</script>

<template>
    <Head :title="render.title || t('Shared Video')" />

    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-6">
        <div class="max-w-2xl w-full card p-6 space-y-4">
            <h1 class="text-lg font-semibold text-center">{{ render.title || t('Shared Video') }}</h1>
            <video v-if="render.file_url" :src="render.file_url" controls class="w-full rounded-lg bg-black"
                   :poster="render.thumbnail_url ?? undefined"></video>
            <p v-if="render.duration_actual" class="text-center text-sm text-gray-500">{{ render.duration_actual }}s</p>
            <p class="text-center text-xs text-gray-400">
                {{ t('Create AI videos at') }}
                <a :href="'/'" class="text-emerald-600 underline">MakeAI</a>
            </p>
        </div>
    </div>
</template>
