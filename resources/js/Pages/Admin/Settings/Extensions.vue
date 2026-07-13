<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import IntegrationsManager from '@/Components/Admin/IntegrationsManager.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: AdminLayout })

defineProps<{
    integrations: Record<string, any>
}>()

const { t } = useTranslate()
</script>

<template>
    <Head :title="t('Extensions')" />

    <IntegrationsManager
        :integrations="integrations"
        :title="t('Extensions')"
        :description="t('Connect platform and system services — security, spam filtering, analytics, messaging, and content safety. Billed to you by the provider, not per user.')"
        update-route-name="admin.settings.integrations.update"
        test-route-name="admin.settings.integrations.test-connection"
    />
</template>
