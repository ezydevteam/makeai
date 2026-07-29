<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/UI/ActionConfirmModal.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import TableActionMenu from '@/Components/UI/TableActionMenu.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'
import { applyPurchaseCodeMask } from '@/lib/purchaseCode'

defineOptions({ layout: AdminLayout })

interface LicenseInfo {
    license_type: number
    license_type_label: string
    buyer: string
    purchase_code_masked: string
    purchased_at: string
    supported_until: string | null
    verified_at: string
    status: string
    domain_ok: boolean
    grace_started_at: string | null
}

interface AddonConfig {
    name: string; slug: string; version: string; description?: string
    requires_license: number; is_active: boolean; license_ok: boolean
    envato_item_id?: number
    logo_url?: string | null
    installed_at?: string | null
    activated_at?: string | null
    license?: LicenseInfo | null
    settings?: Array<{ key: string; label: string; type: string; default: any }>
    update_available?: boolean
    latest_version?: string | null
    update_changelog?: string | null
    update_checked_at?: string | null
}

const props = defineProps<{ addons: AddonConfig[] }>()
const { t } = useTranslate()
const { error: toastError } = useToastr()

const processing = ref<Record<string, boolean>>({})
const selectedAddons = ref<string[]>([])
const bulkProcessing = ref(false)
const searchInputRef = ref<HTMLInputElement | null>(null)

function toggleSelectAll() {
    if (selectedAddons.value.length === filteredAddons.value.length) {
        selectedAddons.value = []
    } else {
        selectedAddons.value = filteredAddons.value.map((a) => a.slug)
    }
}

function toggleSelect(slug: string) {
    const idx = selectedAddons.value.indexOf(slug)
    if (idx === -1) {
        selectedAddons.value.push(slug)
    } else {
        selectedAddons.value.splice(idx, 1)
    }
}

const allSelected = computed(() => filteredAddons.value.length > 0 && selectedAddons.value.length === filteredAddons.value.length)

async function bulkActivate() {
    if (!selectedAddons.value.length) return
    bulkProcessing.value = true
    router.post(route('admin.addons.bulk-activate'), {
        slugs: selectedAddons.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            bulkProcessing.value = false
            selectedAddons.value = []
            closeConfirmModal(true)
        },
    })
}

async function bulkDeactivate() {
    if (!selectedAddons.value.length) return
    bulkProcessing.value = true
    router.post(route('admin.addons.bulk-deactivate'), {
        slugs: selectedAddons.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            bulkProcessing.value = false
            selectedAddons.value = []
            closeConfirmModal(true)
        },
    })
}

const confirmBulkActivate = () => {
    if (!selectedAddons.value.length) {
        return
    }

    openConfirmModal({
        title: t('Activate selected addons?'),
        message: t('This will activate :count selected addon(s).', { count: selectedAddons.value.length }),
        confirmLabel: t('Activate Selected'),
        processingLabel: t('Activating...'),
        variant: 'primary',
        action: () => {
            bulkActivate()
        },
    })
}

const confirmBulkDeactivate = () => {
    if (!selectedAddons.value.length) {
        return
    }

    openConfirmModal({
        title: t('Deactivate selected addons?'),
        message: t('This will deactivate :count selected addon(s).', { count: selectedAddons.value.length }),
        confirmLabel: t('Deactivate Selected'),
        processingLabel: t('Deactivating...'),
        variant: 'danger',
        action: () => {
            bulkDeactivate()
        },
    })
}

const activate = (slug: string) => {
    processing.value[slug] = true
    router.post(route('admin.addons.activate', { slug }), {}, {
        onFinish: () => {
            processing.value[slug] = false
        }
    })
}
const deactivate = (slug: string) => {
    processing.value[slug] = true
    router.post(route('admin.addons.deactivate', { slug }), {}, {
        onFinish: () => {
            processing.value[slug] = false
        }
    })
}

const checkingUpdates = ref(false)
function checkUpdates() {
    checkingUpdates.value = true
    router.post(route('admin.addons.check-updates'), {}, {
        preserveScroll: true,
        onFinish: () => { checkingUpdates.value = false },
    })
}

const showUploadModal = ref(false)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const searchQuery = ref('')
const statusFilter = ref('')
const searchFocused = ref(false)
const hiddenLogos = ref<string[]>([])
const confirmModal = ref({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    processingLabel: '',
    processing: false,
    variant: 'danger' as 'danger' | 'primary',
    action: null as null | (() => void),
})

const statusOptions = computed(() => [
    { value: '', label: t('All Status') },
    { value: 'active', label: t('Active') },
    { value: 'inactive', label: t('Inactive') },
    { value: 'licensed', label: t('Licensed') },
    { value: 'locked', label: t('Locked') },
])

const filteredAddons = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    return props.addons.filter((addon) => {
        const matchesQuery = !query || [
            addon.name,
            addon.slug,
            addon.version,
            addon.description ?? '',
        ].some((value) => value.toLowerCase().includes(query))

        const matchesStatus =
            statusFilter.value === ''
            || (statusFilter.value === 'active' && addon.is_active)
            || (statusFilter.value === 'inactive' && !addon.is_active)
            || (statusFilter.value === 'licensed' && addon.license_ok)
            || (statusFilter.value === 'locked' && !addon.license_ok)

        return matchesQuery && matchesStatus
    })
})

const hasActiveFilters = computed(() => Boolean(searchQuery.value || statusFilter.value))

const shouldShowLogo = (addon: AddonConfig) => Boolean(addon.logo_url) && !hiddenLogos.value.includes(addon.slug)
const hideLogo = (addon: AddonConfig) => {
    if (!hiddenLogos.value.includes(addon.slug)) {
        hiddenLogos.value.push(addon.slug)
    }
}

const openConfirmModal = (config: Omit<typeof confirmModal.value, 'open' | 'processing'>) => {
    confirmModal.value = {
        ...config,
        open: true,
        processing: false,
    }
}

const closeConfirmModal = (force = false) => {
    if (confirmModal.value.processing && !force) {
        return
    }

    confirmModal.value = {
        open: false,
        title: '',
        message: '',
        confirmLabel: '',
        processingLabel: '',
        processing: false,
        variant: 'danger',
        action: null,
    }
}

const runConfirmedAction = () => {
    confirmModal.value.processing = true
    confirmModal.value.action?.()
}

const confirmDelete = (addon: AddonConfig) => {
    openConfirmModal({
        title: t('Delete addon?'),
        message: t('This will delete all :name data, remove the addon record, and delete the addon directory. This action cannot be undone.', { name: addon.name }),
        confirmLabel: t('Delete'),
        processingLabel: t('Deleting...'),
        variant: 'danger',
        action: () => {
            router.delete(route('admin.addons.delete', { slug: addon.slug }), {
                preserveScroll: true,
                onFinish: () => closeConfirmModal(true),
            })
        },
    })
}

function onFilePicked(e: Event) {
    const target = e.target as HTMLInputElement
    if (target.files?.[0]) {
        selectedFile.value = target.files[0]
    }
}

async function handleUpload() {
    if (!selectedFile.value) return
    uploading.value = true
    const formData = new FormData()
    formData.append('addon_zip', selectedFile.value)
    formData.append('_token', document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '')
    try {
        const response = await fetch(route('admin.addons.upload'), {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
        })
        if (response.ok) {
            showUploadModal.value = false
            selectedFile.value = null
            window.location.reload()
        } else {
            const data = await response.json()
            toastError(data.message ?? t('Upload failed.'))
        }
    } finally {
        uploading.value = false
    }
}

// â”€â”€â”€ License Modal State â”€â”€â”€
const licenseModal = ref(false)
const licenseAddonSlug = ref('')
const licenseAddonName = ref('')
const purchaseCode = ref('')
const licenseError = ref('')
const licenseErrorCode = ref('')
const licenseVerifying = ref(false)

function openLicenseModal(addon: AddonConfig) {
    licenseModal.value = true
    licenseAddonSlug.value = addon.slug
    licenseAddonName.value = addon.name
    purchaseCode.value = ''
    licenseError.value = ''
    licenseErrorCode.value = ''
}

function closeLicenseModal() {
    licenseModal.value = false
    licenseAddonSlug.value = ''
    licenseAddonName.value = ''
    purchaseCode.value = ''
    licenseError.value = ''
    licenseErrorCode.value = ''
}

async function verifyLicense() {
    if (!purchaseCode.value.trim()) return

    licenseVerifying.value = true
    licenseError.value = ''
    licenseErrorCode.value = ''

    try {
        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
        const response = await fetch(route('admin.addons.verify-license', { slug: licenseAddonSlug.value }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ purchase_code: purchaseCode.value.trim() }),
        })

        const data = await response.json()

        if (response.ok && data.success) {
            // License verified — activate the addon
            closeLicenseModal()
            router.post(route('admin.addons.activate', { slug: licenseAddonSlug.value }), {}, {
                onFinish: () => window.location.reload(),
            })
        } else {
            licenseError.value = data.error ?? t('Verification failed.')
            licenseErrorCode.value = data.error_code ?? ''
        }
    } catch {
        licenseError.value = t('Could not reach server. Please try again.')
        licenseErrorCode.value = 'connection_error'
    } finally {
        licenseVerifying.value = false
    }
}

// â”€â”€â”€ Activation entry point â”€â”€â”€
function handleActivate(addon: AddonConfig) {
    // Addons with requires_license AND no valid license â†’ show license modal first
    if (addon.requires_license && !addon.license?.license_type) {
        openLicenseModal(addon)
        return
    }
    // Already licensed â†’ direct activation
    activate(addon.slug)
}

// Purchase code mask — allows the fake TEST-... codes while LICENSE_TEST_MODE is on
function onPurchaseCodeInput(e: Event) {
    purchaseCode.value = applyPurchaseCodeMask((e.target as HTMLInputElement).value, true)
}

async function focusSearch() {
    await nextTick()
    searchInputRef.value?.focus()
    searchInputRef.value?.select()
}

function clearFilters() {
    searchQuery.value = ''
    statusFilter.value = ''
}

function handleKeydown(event: KeyboardEvent) {
    const target = event.target
    const tagName = target instanceof HTMLElement ? target.tagName : ''
    const isTypingTarget = target instanceof HTMLElement && (
        target.isContentEditable ||
        tagName === 'INPUT' ||
        tagName === 'TEXTAREA' ||
        tagName === 'SELECT'
    )

    if (event.key === '/' && !isTypingTarget) {
        event.preventDefault()
        focusSearch()
        return
    }

    if (event.key === 'Escape' && !confirmModal.value.open && !showUploadModal.value && !licenseModal.value && hasActiveFilters.value) {
        clearFilters()
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
    <Head :title="t('Addons')" />
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Addon Manager') }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Install, activate, and configure platform addons.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button @click="checkUpdates" :disabled="checkingUpdates" class="grow inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-green-50 hover:border-green-200 hover:text-green-700 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:!border-green-800 dark:hover:!bg-green-900/20 dark:hover:!text-green-300">
                    <i class="ti text-base" :class="checkingUpdates ? 'ti-loader animate-spin' : 'ti-refresh'"></i>
                    {{ checkingUpdates ? t('Checking...') : t('Check for Updates') }}
                </button>
                <button @click="showUploadModal = true" class="grow inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-white btn-primary-admin">
                    <i class="ti ti-upload text-base"></i>
                    {{ t('Upload Addon') }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-surface-800 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex-1 min-w-[240px] sm:max-w-md">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <i class="ti ti-search text-base"></i>
                            </span>
                            <input
                                ref="searchInputRef"
                                v-model="searchQuery"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2 pl-10 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-surface-700 dark:bg-surface-800 dark:text-white"
                                :placeholder="t('Search addons...')"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                            />
                            <span
                                v-if="!searchQuery && !searchFocused"
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium text-gray-400 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-500"
                            >
                                /
                            </span>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                                :aria-label="t('Clear search')"
                                :title="t('Clear search')"
                                @click="searchQuery = ''"
                            >
                                <i class="ti ti-x text-base"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:flex-grow sm:w-auto sm:justify-end lg:flex-grow-0">
                        <div class="w-full sm:w-48 sm:flex-none">
                            <AppSelect
                                v-model="statusFilter"
                                :options="statusOptions"
                                :placeholder="t('All Status')"
                            />
                        </div>

                        <div v-if="filteredAddons.length" class="flex items-center gap-2 shrink-0">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleSelectAll"
                                id="bulkAddonSelect"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                            />
                            <label class="text-sm text-gray-600 dark:text-gray-400" for="bulkAddonSelect">{{ t('Select All') }}</label>
                        </div>

                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-surface-700 dark:bg-surface-800 dark:text-gray-300 dark:hover:bg-surface-700 w-full sm:w-auto"
                            @click="clearFilters"
                        >
                            <i class="ti ti-rotate-clockwise text-base"></i>
                            {{ t('Reset') }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="filteredAddons.length" class="space-y-4 p-4 sm:p-6">
                <!-- Bulk Actions Bar -->
                <div v-if="selectedAddons.length" class="flex flex-col gap-3 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-900/20 lg:flex-row lg:items-center lg:justify-between">
                    <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                        {{ t(':count selected', { count: selectedAddons.length }) }}
                    </span>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            @click="confirmBulkActivate"
                            :disabled="bulkProcessing"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-600 disabled:opacity-50"
                        >
                            <i v-if="bulkProcessing" class="ti ti-loader-2 animate-spin"></i>
                            {{ t('Activate Selected') }}
                        </button>
                        <button
                            @click="confirmBulkDeactivate"
                            :disabled="bulkProcessing"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-500/10 px-3 py-1.5 text-sm font-medium text-red-500 hover:bg-red-500/20 disabled:opacity-50"
                        >
                            <i v-if="bulkProcessing" class="ti ti-loader-2 animate-spin"></i>
                            {{ t('Deactivate Selected') }}
                        </button>
                        <button
                            @click="selectedAddons = []"
                            class="inline-flex items-center gap-2 rounded-lg bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        >
                            {{ t('Clear Selection') }}
                        </button>
                    </div>
                </div>

        <div v-for="addon in filteredAddons" :key="addon.slug" :class="[addon.is_active ? 'border-primary-500/30 bg-primary-50/30 dark:bg-primary-500/5' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40']" class="rounded-xl border p-5 shadow-sm transition-colors">
            <div class="flex gap-4">
                <div class="min-w-0 flex flex-col sm:flex-row sm:items-start gap-4">
                <!-- Checkbox -->
                <input
                    type="checkbox"
                    :checked="selectedAddons.includes(addon.slug)"
                    @change="toggleSelect(addon.slug)"
                    class="mt-3 h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                />
                <div :class="[addon.is_active ? 'bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400']" class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0">
                    <img
                        v-if="shouldShowLogo(addon)"
                        :src="addon.logo_url ?? undefined"
                        :alt="t(':name logo', { name: addon.name })"
                        class="h-8 w-8 rounded-md object-contain"
                        @error="hideLogo(addon)"
                    />
                    <i v-else class="ti ti-puzzle text-2xl"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="mb-0.5 flex flex-wrap items-center gap-2">
                        <h3 class="text-gray-900 dark:text-white font-semibold">{{ addon.name }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">v{{ addon.version }}</span>
                        <span
                            v-if="addon.update_available"
                            class="px-2 py-0.5 bg-amber-500/15 text-amber-600 dark:text-amber-400 text-[10px] font-bold rounded-full cursor-help"
                            :title="(addon.update_changelog ? addon.update_changelog + '\n\n' : '') + t('Download v:v from CodeCanyon, then use Upload Addon to install it.', { v: addon.latest_version ?? '' })"
                        >{{ t('UPDATE v:v', { v: addon.latest_version ?? '' }) }}</span>
                        <span v-if="addon.is_active" class="px-2 py-0.5 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold rounded-full">{{ t('ACTIVE') }}</span>
                        <span v-else class="px-2 py-0.5 bg-gray-100 text-gray-500 dark:bg-surface-800 dark:text-gray-400 text-[10px] font-bold rounded-full">{{ t('INACTIVE') }}</span>
                        <span v-if="addon.license?.status === 'grace'" class="px-2 py-0.5 bg-amber-500/15 text-amber-500 text-[10px] font-bold rounded-full">{{ t('GRACE') }}</span>
                        <span v-if="!addon.license_ok" class="px-2 py-0.5 bg-red-500/15 text-red-500 text-[10px] font-bold rounded-full">{{ t('LICENSE') }}</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ addon.description || t('No description') }}</p>

                    <!-- License info row -->
                    <div v-if="addon.license?.buyer" class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                        <span>{{ t('Licensed to :buyer', { buyer: addon.license.buyer }) }}</span>
                        <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                        <span>{{ addon.license.license_type_label }}</span>
                        <template v-if="addon.license.purchased_at">
                            <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                            <span>{{ t('Purchased :date', { date: addon.license.purchased_at }) }}</span>
                        </template>
                        <template v-if="addon.license.supported_until">
                            <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                            <span>{{ t('Supported until :date', { date: addon.license.supported_until }) }}</span>
                        </template>
                        <span v-if="!addon.license.domain_ok" class="text-amber-500">{{ t('Domain changed') }}</span>
                    </div>

                    <!-- Install / activation timeline -->
                    <div v-if="addon.installed_at || addon.activated_at" class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                        <span v-if="addon.installed_at">{{ t('Installed :date', { date: addon.installed_at }) }}</span>
                        <template v-if="addon.activated_at">
                            <span v-if="addon.installed_at" class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                            <span>{{ t('Activated :date', { date: addon.activated_at }) }}</span>
                        </template>
                    </div>
                </div>
                </div>
                <div class="ml-auto flex flex-wrap sm:items-center gap-2 xl:justify-end overflow-visible">
                    <TableActionMenu>
                        <template #default="{ close }">
                            <Link
                                v-if="addon.settings?.length"
                                :href="route('admin.addons.settings', { slug: addon.slug })"
                                :aria-label="t('Settings for :name addon', { name: addon.name })"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                @click="close"
                            >
                                <i class="ti ti-settings text-base" />
                                <span>{{ t('Settings') }}</span>
                            </Link>

                            <button
                                v-if="addon.is_active"
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                :disabled="processing[addon.slug]"
                                @click="deactivate(addon.slug); close()"
                            >
                                <i v-if="processing[addon.slug]" class="ti ti-loader-2 animate-spin text-base" />
                                <i v-else class="ti ti-circle-x text-base" />
                                <span>{{ processing[addon.slug] ? t('Deactivating...') : t('Deactivate') }}</span>
                            </button>

                            <button
                                v-else-if="addon.license_ok"
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                :disabled="processing[addon.slug]"
                                @click="handleActivate(addon); close()"
                            >
                                <i v-if="processing[addon.slug]" class="ti ti-loader-2 animate-spin text-base" />
                                <i v-else class="ti ti-circle-check text-base" />
                                <span>{{ processing[addon.slug] ? t('Activating...') : t('Activate') }}</span>
                            </button>

                            <button
                                v-else
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition hover:bg-primary-50 hover:text-primary-700 dark:text-gray-200 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                :disabled="processing[addon.slug]"
                                @click="handleActivate(addon); close()"
                            >
                                <i class="ti ti-lock text-base" />
                                <span>{{ t('Enter License') }}</span>
                            </button>

                            <hr v-if="!addon.is_active" class="my-1 border-gray-200 dark:border-surface-700" />

                            <button
                                v-if="!addon.is_active"
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2.5 text-sm text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-900/20"
                                @click="confirmDelete(addon); close()"
                            >
                                <i class="ti ti-trash text-base" />
                                <span>{{ t('Delete') }}</span>
                            </button>
                        </template>
                    </TableActionMenu>
                </div>
            </div>
        </div>
    </div>

        <div v-else class="px-6 py-16 text-center">
            <i class="ti ti-puzzle-off text-5xl text-gray-300 dark:text-gray-600"></i>
            <p class="mt-4 text-gray-500 dark:text-gray-400 text-sm mb-2">{{ t('No addons installed yet') }}</p>
            <p class="text-gray-400 dark:text-gray-500 text-xs mb-6">{{ t('Upload an addon zip or place it manually in the addons directory.') }}</p>
            <button @click="showUploadModal = true" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary-admin">
            <i class="ti ti-upload text-base"></i>
                    {{ t('Upload Addon') }}
                </button>
        </div>
    </div>
</div>


    <ActionConfirmModal
        :open="confirmModal.open"
        :title="confirmModal.title"
        :message="confirmModal.message"
        :confirm-label="confirmModal.confirmLabel"
        :processing-label="confirmModal.processingLabel"
        :processing="confirmModal.processing"
        :variant="confirmModal.variant"
        @cancel="closeConfirmModal"
        @confirm="runConfirmedAction"
    />

    <!-- Upload Modal -->
    <AppModal
        :open="showUploadModal"
        max-width="max-w-md"
        :title="t('Upload Addon')"
        :subtitle="t('Upload a .zip file containing the addon.')"
        :confirm-text="t('Install Addon')"
        :confirm-loading="uploading"
        :confirm-disabled="!selectedFile"
        confirm-variant="admin"
        @close="showUploadModal = false"
        @confirm="handleUpload"
    >
        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors" :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-500/10': selectedFile }" @click="fileInput?.click()">
            <input ref="fileInput" type="file" name="addon_zip" accept=".zip" required class="hidden" @change="onFilePicked" />
            <template v-if="!selectedFile">
                <i class="ti ti-cloud-upload text-4xl text-gray-300 dark:text-gray-600 mx-auto mb-3"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Click to select a .zip file') }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ t('Max 20 MB') }}</p>
            </template>
            <template v-else>
                <i class="ti ti-file-zip text-4xl text-blue-500 mx-auto mb-3"></i>
                <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ selectedFile.name }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ (selectedFile.size / 1024).toFixed(1) }} KB</p>
            </template>
        </div>
    </AppModal>

    <!-- License Verification Modal -->
    <AppModal
        :open="licenseModal"
        max-width="max-w-md"
        :title="t('Activate :name', { name: licenseAddonName })"
        :subtitle="t('Enter your Envato purchase code for this addon. Find it in Envato Downloads and the license certificate purchase code section.')"
        :confirm-text="t('Verify & Activate')"
        :confirm-loading="licenseVerifying"
        :confirm-disabled="!purchaseCode.trim()"
        confirm-variant="admin"
        @close="closeLicenseModal"
        @confirm="verifyLicense"
    >
        <!-- Error -->
        <div v-if="licenseError" class="mb-4 p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg text-sm text-red-600 dark:text-red-400 flex items-start gap-2">
            <i class="ti ti-alert-circle text-base shrink-0 mt-0.5"></i>
            <span>{{ licenseError }}</span>
        </div>

        <!-- Purchase code input -->
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ t('Purchase Code') }}</label>
        <input
            :value="purchaseCode"
            @input="onPurchaseCodeInput"
            type="text"
            :placeholder="t('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')"
            maxlength="36"
            class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
        />

        <!-- Help link -->
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 mb-1 flex items-center gap-1">
            <i class="ti ti-info-circle text-sm"></i>
            {{ t('This code is different from your MakeAI core purchase code.') }}
        </p>
        <a
            href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code"
            target="_blank"
            class="text-xs text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 underline"
        >{{ t('Where do I find this?') }}</a>
    </AppModal>
</template>
