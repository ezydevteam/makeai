<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import IntegrationsManager from '@/Components/Admin/IntegrationsManager.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

defineProps<{
    integrations: Record<string, any>
    defaultAiProvider: string
    configuredAiProviders: Record<string, { name: string; key_count: number }>
}>()

const { t } = useTranslate()
</script>

<template>
    <Head :title="t('Integrations')" />

    <IntegrationsManager
        :integrations="integrations"
        :title="t('Integrations')"
        :description="t('Configure paid AI generation and analysis tools — image, voice, video, and content utilities. Set a per-use credit cost for each.')"
        :default-ai-provider="defaultAiProvider"
        :configured-ai-providers="configuredAiProviders"
        update-route-name="admin.settings.integrations.update"
        test-route-name="admin.settings.integrations.test-connection"
    />
</template>
