<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { applyPurchaseCodeMask } from '@/lib/purchaseCode'

defineOptions({ layout: AdminLayout })

interface ThemeLicense {
    license_type_label: string
    buyer: string
    purchase_code_masked: string
    purchased_at?: string | null
    supported_until?: string | null
    domain_ok?: boolean
    status: string
}

interface ThemeConfig {
    name: string; slug: string; version: string; author: string
    description: string; requires_license: number; thumbnail?: string
    is_active: boolean; license_ok: boolean
    is_premium?: boolean
    license?: ThemeLicense | null
    update_available?: boolean
    latest_version?: string | null
    update_changelog?: string | null
    settings?: Array<{ key: string; label: string; type: string; default: any }>
}

const props = defineProps<{ themes: ThemeConfig[]; activeTheme: string }>()
const { t } = useTranslate()

const activating = ref<string | null>(null)
const canOpenSettings = (theme: ThemeConfig): boolean => theme.slug === 'default' || Boolean(theme.settings?.length)

const activate = (slug: string) => {
    activating.value = slug
    router.post(route('admin.themes.activate', { slug }), {}, { onFinish: () => { activating.value = null } })
}

// â”€â”€ Check for updates â”€â”€
const checkingUpdates = ref(false)
function checkUpdates() {
    checkingUpdates.value = true
    router.post(route('admin.themes.check-updates'), {}, { preserveScroll: true, onFinish: () => { checkingUpdates.value = false } })
}

// â”€â”€ Upload theme â”€â”€
const showUpload = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const uploadForm = useForm<{ theme_zip: File | null }>({ theme_zip: null })

function onFilePicked(e: Event) {
    const target = e.target as HTMLInputElement
    if (target.files?.[0]) {
        uploadForm.theme_zip = target.files[0]
    }
}

function submitUpload() {
    uploadForm.post(route('admin.themes.upload'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; uploadForm.reset('theme_zip') },
    })
}

// â”€â”€ License verification modal â”€â”€
const licenseModal = ref<ThemeConfig | null>(null)
const licenseCode = ref('')
const licenseError = ref('')
const licenseBusy = ref(false)
function openLicense(theme: ThemeConfig) {
    licenseModal.value = theme
    licenseCode.value = ''
    licenseError.value = ''
}
async function submitLicense() {
    if (!licenseModal.value) return
    licenseBusy.value = true
    licenseError.value = ''
    try {
        const res = await fetch(route('admin.themes.verify-license', { slug: licenseModal.value.slug }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ purchase_code: licenseCode.value }),
        })
        const data = await res.json()
        if (!res.ok) { licenseError.value = data.error || t('Verification failed.'); return }
        licenseModal.value = null
        router.reload({ only: ['themes'] })
    } catch {
        licenseError.value = t('Verification failed. Please try again.')
    } finally {
        licenseBusy.value = false
    }
}
function onCodeInput(e: Event) {
    licenseCode.value = applyPurchaseCodeMask((e.target as HTMLInputElement).value, true)
}
</script>

<template>
    <Head :title="t('Themes')" />

    <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-8">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Theme Manager') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ t('Upload, license, activate, and update themes.') }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                <button @click="checkUpdates" :disabled="checkingUpdates" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-green-50 hover:border-green-200 hover:text-green-700 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:!border-green-800 dark:hover:!bg-green-900/20 dark:hover:!text-green-300">
                    <i class="ti text-base" :class="checkingUpdates ? 'ti-loader-2 animate-spin' : 'ti-refresh'"></i>
                    {{ checkingUpdates ? t('Checking...') : t('Check for Updates') }}
                </button>
                <button @click="showUpload = true" class="inline-flex items-center gap-2 btn-primary-admin">
                    <i class="ti ti-upload text-base"></i>
                    {{ t('Upload Theme') }}
                </button>
            </div>
        </div>

        <!-- Theme Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div v-for="theme in themes" :key="theme.slug" :class="[theme.is_active ? 'border-primary-500 ring-1 ring-primary-500/20' : 'border-gray-200']" class="relative bg-white border rounded-2xl overflow-hidden shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <div class="h-40 bg-gradient-to-br from-primary-500/10 to-accent-500/10 flex items-center justify-center">
                    <div v-if="theme.is_active" class="absolute top-3 right-3 px-2.5 py-0.5 bg-success-500/15 text-success-500 text-xs font-bold rounded-full">{{ t('ACTIVE') }}</div>
                    <div v-if="theme.update_available" class="absolute top-3 left-3 px-2.5 py-0.5 bg-amber-500/15 text-amber-600 dark:text-amber-400 text-[10px] font-bold rounded-full" :title="(theme.update_changelog || '') + '\n\n' + t('Download v:v from CodeCanyon, then Upload Theme to install it.', { v: theme.latest_version ?? '' })">{{ t('UPDATE v:v', { v: theme.latest_version ?? '' }) }}</div>
                    <div v-else-if="theme.is_premium && !theme.license_ok" class="absolute top-3 left-3 px-2.5 py-0.5 bg-danger-500/15 text-danger-500 text-[10px] font-bold rounded-full flex items-center gap-1">
                        <i class="ti ti-lock text-xs"></i> {{ t('LICENSE REQUIRED') }}
                    </div>
                    <svg class="w-12 h-12 text-primary-400/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" /></svg>
                </div>

                <div class="p-5">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="text-gray-900 dark:text-white font-semibold">{{ theme.name }}</h3>
                        <span class="text-xs text-gray-600 dark:text-gray-400">v{{ theme.version }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-1">{{ t('by :author', { author: theme.author }) }}</p>
                    <p class="text-sm text-gray-400 mb-4">{{ theme.description }}</p>

                    <div v-if="theme.license?.buyer" class="mb-3 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                        <p>{{ theme.license.license_type_label }} · {{ theme.license.buyer }}</p>
                        <p v-if="theme.license.purchased_at">{{ t('Purchased :date', { date: theme.license.purchased_at }) }}</p>
                        <p v-if="theme.license.supported_until">{{ t('Supported until :date', { date: theme.license.supported_until }) }}</p>
                        <p v-if="theme.license.domain_ok === false" class="text-amber-500">{{ t('Domain changed') }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button v-if="theme.is_premium && !theme.license_ok" @click="openLicense(theme)" class="inline-flex items-center justify-center gap-2 px-4 py-2 btn-primary-admin text-sm font-medium rounded-xl grow">
                            <i class="ti ti-shield-lock"></i>
                            {{ t('Verify License') }}
                        </button>
                        <button v-else-if="!theme.is_active && theme.license_ok" @click="activate(theme.slug)" :disabled="activating === theme.slug" class="inline-flex items-center justify-center gap-2 px-4 py-2 btn-primary-admin text-sm font-medium rounded-xl grow disabled:opacity-50">
                            <span v-if="activating === theme.slug" class="inline-flex items-center justify-center gap-2"><i class="ti ti-loader-2 animate-spin"></i>{{ t('Activating...') }}</span>
                            <span v-else class="inline-flex items-center justify-center gap-2"><i class="ti ti-check"></i>{{ t('Activate') }}</span>
                        </button>
                        <span v-else-if="theme.is_active" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-50 text-gray-500 text-sm font-medium rounded-xl grow text-center dark:bg-gray-800"><i class="ti ti-circle-check"></i>{{ t('Currently Active') }}</span>
                        <span v-else class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-50 text-gray-400 text-sm rounded-xl grow text-center dark:bg-gray-800">{{ t('Requires Extended License') }}</span>

                        <Link v-if="canOpenSettings(theme)" :href="route('admin.themes.settings', { slug: theme.slug })" class="grow inline-flex items-center gap-2 justify-center px-3 py-2 bg-primary-50 text-primary-500 rounded-xl hover:bg-primary-100/80 transition-colors dark:bg-primary-900/20 dark:hover:bg-primary-900/30">
                            <i class="ti ti-settings"></i>
                            {{ t('Settings') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!themes.length" class="text-center py-16">
            <p class="text-gray-500 text-sm">{{ t('No themes found in') }} <code class="text-gray-400">resources/themes/</code></p>
        </div>

        <!-- Upload Modal -->
        <AppModal
            :open="showUpload"
            max-width="max-w-md"
            :title="t('Upload Theme')"
            :subtitle="t('Upload a theme .zip (one root folder containing settings.json). It extracts to resources/themes/.')"
            :confirm-text="t('Install Theme')"
            :confirm-loading="uploadForm.processing"
            :confirm-disabled="!uploadForm.theme_zip"
            confirm-variant="admin"
            has-form
            @close="showUpload = false"
            @submit="submitUpload"
        >
            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors" :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-500/10': uploadForm.theme_zip }" @click="fileInput?.click()">
                <input ref="fileInput" type="file" name="theme_zip" accept=".zip" required class="hidden" @change="onFilePicked" />
                <template v-if="!uploadForm.theme_zip">
                    <i class="ti ti-cloud-upload text-4xl text-gray-300 dark:text-gray-600 mx-auto mb-3"></i>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Click to select a .zip file') }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ t('Max 20 MB') }}</p>
                </template>
                <template v-else>
                    <i class="ti ti-file-zip text-4xl text-blue-500 mx-auto mb-3"></i>
                    <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ uploadForm.theme_zip.name }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ (uploadForm.theme_zip.size / 1024).toFixed(1) }} KB</p>
                </template>
            </div>
            <p v-if="uploadForm.errors.theme_zip" class="mt-2 text-xs text-red-600">{{ uploadForm.errors.theme_zip }}</p>
        </AppModal>

        <!-- License modal -->
        <AppModal
            :open="!!licenseModal"
            max-width="max-w-md"
            :title="t('Verify Theme License')"
            :subtitle="licenseModal ? t('Enter your Envato purchase code for :name.', { name: licenseModal.name }) : ''"
            :confirm-text="t('Verify')"
            :confirm-loading="licenseBusy"
            :confirm-disabled="!licenseCode"
            confirm-variant="admin"
            has-form
            @close="licenseModal = null"
            @submit="submitLicense"
        >
            <input :value="licenseCode" @input="onCodeInput" type="text" spellcheck="false" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            <p v-if="licenseError" class="mt-2 text-xs text-red-600">{{ licenseError }}</p>
        </AppModal>
    </div>
</template>
