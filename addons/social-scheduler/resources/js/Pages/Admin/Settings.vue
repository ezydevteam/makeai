<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import AppSelect from '@/Components/AppSelect.vue'

defineOptions({ layout: AdminLayout })

const { t } = useTranslate()

const props = defineProps<{
    settings: Record<string, any>
}>()

const form = useForm({
    enabled: props.settings.enabled,
    approval_required: props.settings.approval_required,
    max_accounts_per_user: props.settings.max_accounts_per_user,
    max_media_mb: props.settings.max_media_mb,
    ai_model: props.settings.ai_model || '',
    best_time_model: props.settings.best_time_model || '',
    rss_poll_interval_minutes: props.settings.rss_poll_interval_minutes,
    analytics_pull_enabled: props.settings.analytics_pull_enabled,
    carousel_max_slides: props.settings.carousel_max_slides,
    first_comment_enabled: props.settings.first_comment_enabled,
    provider: props.settings.provider || '',
})

function save() {
    form.put(route('addon.social.admin.settings'), {
        preserveScroll: true,
        onSuccess: () => {},
    })
}
</script>

<template>
    <Head :title="t('Social Scheduler Settings')" />

    <div class="p-6 max-w-2xl space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('Social Scheduler Settings') }}</h1>
            <button @click="save" :disabled="form.processing" class="btn btn-sm btn-emerald">
                {{ t('Save') }}
            </button>
        </div>

        <section class="card p-4 space-y-4">
            <h3 class="font-medium">{{ t('General') }}</h3>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.enabled" class="toggle" />
                <span class="text-sm">{{ t('Enable Social Scheduler') }}</span>
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.approval_required" class="toggle" />
                <span class="text-sm">{{ t('Require approval before publishing') }}</span>
            </label>
            <div>
                <label class="block text-sm mb-1">{{ t('Max Accounts Per User') }}</label>
                <input type="number" v-model.number="form.max_accounts_per_user" min="1" max="50" class="input w-24" />
            </div>
            <div>
                <label class="block text-sm mb-1">{{ t('Max Media Upload (MB)') }}</label>
                <input type="number" v-model.number="form.max_media_mb" min="1" max="500" class="input w-24" />
            </div>
            <div>
                <label class="block text-sm mb-1">{{ t('Max Carousel Slides') }}</label>
                <input type="number" v-model.number="form.carousel_max_slides" min="2" max="20" class="input w-24" />
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.first_comment_enabled" class="toggle" />
                <span class="text-sm">{{ t('Enable first comment (Instagram)') }}</span>
            </label>
        </section>

        <section class="card p-4 space-y-4">
            <h3 class="font-medium">{{ t('AI Configuration') }}</h3>
            <div>
                <label class="block text-sm mb-1">{{ t('AI Provider') }}</label>
                <AppSelect v-model="form.provider" :options="[]" :placeholder="t('Default')" />
            </div>
            <div>
                <label class="block text-sm mb-1">{{ t('AI Model (Captions)') }}</label>
                <input type="text" v-model="form.ai_model" class="input w-full" :placeholder="t('Default')" />
            </div>
            <div>
                <label class="block text-sm mb-1">{{ t('AI Model (Best Time)') }}</label>
                <input type="text" v-model="form.best_time_model" class="input w-full" :placeholder="t('Default')" />
            </div>
        </section>

        <section class="card p-4 space-y-4">
            <h3 class="font-medium">{{ t('RSS & Analytics') }}</h3>
            <div>
                <label class="block text-sm mb-1">{{ t('RSS Poll Interval (minutes)') }}</label>
                <input type="number" v-model.number="form.rss_poll_interval_minutes" min="5" max="1440" class="input w-24" />
            </div>
            <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.analytics_pull_enabled" class="toggle" />
                <span class="text-sm">{{ t('Pull post analytics daily') }}</span>
            </label>
        </section>
    </div>
</template>
