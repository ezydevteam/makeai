<script setup lang="ts">
import { computed, reactive } from 'vue'
import ErrorAlert from './ErrorAlert.vue'
import AppSelect, { type SelectOption } from '@/Components/UI/AppSelect.vue'

const props = defineProps<{
    formData: Record<string, any>
    error?: string | null
    timezones?: string[]
}>()

/**
 * Detect the URL the application is actually served from, subfolder included.
 *
 * Origin alone is wrong whenever the buyer installs into a subdirectory
 * (example.com/ai): APP_URL would become "https://example.com", and every
 * absolute URL the app generates afterwards — assets, uploaded media, email
 * links, password resets — would drop the /ai prefix and 404.
 *
 * The wizard is always reached at <base>/install, so stripping that suffix from
 * the current path yields the base URL in both layouts.
 */
const detectSiteUrl = (): string => {
    if (typeof window === 'undefined') {
        return 'http://localhost'
    }

    const base = window.location.pathname.replace(/\/install(?:\/.*)?$/, '')

    return `${window.location.origin}${base}`.replace(/\/+$/, '')
}

/**
 * The zone this browser is in, e.g. "Asia/Dhaka".
 *
 * The application stores every timestamp in UTC and renders it through the
 * app_timezone setting, so a fresh install with no zone chosen shows all dates in
 * UTC — which reads as hours wrong to everyone outside it. There is no way to ask
 * the server where the buyer is, and the host's own zone is usually UTC anyway, so
 * the browser is the only real signal available during an install.
 *
 * Guarded because resolvedOptions().timeZone is undefined on some older browsers,
 * and the server independently rejects anything it cannot resolve.
 */
const detectTimezone = (): string => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
    } catch {
        return 'UTC'
    }
}

const site = reactive({
    site_name: props.formData?.step_4?.site_name ?? '',
    site_tagline: props.formData?.step_4?.site_tagline ?? '',
    site_url: props.formData?.step_4?.site_url ?? detectSiteUrl(),
    site_timezone: props.formData?.step_4?.site_timezone ?? detectTimezone(),
})

// A detected zone the server would not accept is worse than no guess: it would be
// silently replaced by UTC after the install with nothing shown here. Fall back in
// the field itself so what the buyer sees is what gets saved.
if (props.timezones?.length && !props.timezones.includes(site.site_timezone)) {
    site.site_timezone = 'UTC'
}

/**
 * Falls back to the detected zone as the sole option so the field still shows what
 * will be saved when the server sends no list — the same guarantee the plain
 * <select> gave with its `v-if="!timezones?.length"` option.
 */
const timezoneOptions = computed<SelectOption[]>(() =>
    (props.timezones?.length ? props.timezones : [site.site_timezone])
        .map((zone) => ({ value: zone, label: zone })),
)

defineExpose({ getData: () => ({ ...site }) })
</script>

<template>
    <div>
        <h2 class="text-xl font-bold text-slate-900">Site Setup</h2>
        <p class="mt-1 text-sm text-slate-500">Configure the basic details of your MakeAI installation.</p>

        <ErrorAlert :message="error" />

        <div class="mt-6 space-y-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">Site Name</span>
                <input
                    v-model="site.site_name"
                    type="text"
                    placeholder="My MakeAI Site"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                />
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Site Tagline</span>
                <input
                    v-model="site.site_tagline"
                    type="text"
                    placeholder="One platform. Every AI tool."
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"
                />
                <span class="mt-1 block text-xs text-slate-400">A short slogan shown in the header and browser title.</span>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">Site URL</span>
                <input
                    v-model="site.site_url"
                    type="text"
                    placeholder="https://example.com"
                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm"
                />
                <span class="mt-1 block text-xs text-slate-400">Auto-detected from your current URL — change if needed.</span>
            </label>

            <!-- Plain <label> would make the whole block a control for AppSelect's
                 inner <button>, so clicking the hint text below would reopen it. -->
            <div class="block">
                <span class="text-sm font-medium text-slate-700">Timezone</span>
                <div class="mt-1.5">
                    <AppSelect
                        v-model="site.site_timezone"
                        :options="timezoneOptions"
                        live-search
                        search-placeholder="Search timezones..."
                        placeholder="Select a timezone"
                    />
                </div>
                <span class="mt-1 block text-xs text-slate-400">
                    Auto-detected from your browser — change if needed. Dates are stored in UTC and
                    displayed in this zone.
                </span>
            </div>
        </div>
    </div>
</template>
