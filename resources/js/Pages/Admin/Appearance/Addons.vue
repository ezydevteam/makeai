<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ActionConfirmModal from '@/Components/ActionConfirmModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useToastr } from '@/Composables/useToastr'

defineOptions({ layout: AdminLayout })

interface LicenseInfo {
    license_type: number
    license_type_label: string
    buyer: string
    purchase_code_masked: string
    purchased_at: string
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
    license?: LicenseInfo | null
    settings?: Array<{ key: string; label: string; type: string; default: any }>
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

const showUploadModal = ref(false)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const searchQuery = ref('')
const statusFilter = ref('')
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

// ─── License Modal State ───
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

// ─── Activation entry point ───
function handleActivate(addon: AddonConfig) {
    // Addons with requires_license AND no valid license → show license modal first
    if (addon.requires_license && !addon.license?.license_type) {
        openLicenseModal(addon)
        return
    }
    // Already licensed → direct activation
    activate(addon.slug)
}

// Purchase code mask
function applyMask(value: string) {
    return value.replace(/[^a-f0-9-]/gi, '').slice(0, 36).toLowerCase()
}

function onPurchaseCodeInput(e: Event) {
    purchaseCode.value = applyMask((e.target as HTMLInputElement).value)
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
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-6 xl:px-8 2xl:px-10">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="mb-1 text-2xl font-bold text-gray-900 dark:text-white">{{ t('Addon Manager') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ t('Install, activate, and configure platform addons.') }}</p>
                </div>
                <button @click="showUploadModal = true" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary">
                    <i class="ti ti-upload text-base"></i>
                    {{ t('Upload Addon') }}
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="w-full xl:max-w-md">
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                    <i class="ti ti-search text-base"></i>
                                </span>
                                <input
                                    ref="searchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pl-9 pr-14 text-sm text-gray-900 focus:border-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                    :placeholder="t('Filter this list by addon name, slug, version, or description...')"
                                />
                                <span
                                    v-if="!searchQuery"
                                    class="pointer-events-none absolute right-3 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md bg-white text-xs font-medium text-gray-400 shadow-sm dark:bg-surface-900 dark:text-gray-500"
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

                        <div class="w-44 md:ml-auto">
                            <AppSelect
                                v-model="statusFilter"
                                :options="statusOptions"
                                :placeholder="t('All Status')"
                            />
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center xl:justify-end">
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                @click="clearFilters"
                            >
                                <i class="ti ti-filter-off text-base"></i>
                                {{ t('Clear') }}
                            </button>
                            <div v-if="filteredAddons.length" class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    id="bulkAddonSelect"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
                                />
                                <label class="text-sm text-gray-600 dark:text-gray-400" for="bulkAddonSelect">{{ t('Select All') }}</label>
                            </div>
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
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
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
                            <span v-if="addon.is_active" class="px-2 py-0.5 bg-emerald-500/15 text-emerald-500 text-[10px] font-bold rounded-full">{{ t('ACTIVE') }}</span>
                            <span v-if="addon.license?.status === 'grace'" class="px-2 py-0.5 bg-amber-500/15 text-amber-500 text-[10px] font-bold rounded-full">{{ t('GRACE') }}</span>
                            <span v-if="!addon.license_ok" class="px-2 py-0.5 bg-red-500/15 text-red-500 text-[10px] font-bold rounded-full">{{ t('LICENSE') }}</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ addon.description || t('No description') }}</p>

                        <!-- License info row -->
                        <div v-if="addon.license?.buyer" class="flex items-center gap-3 mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <span>{{ t('Licensed to :buyer', { buyer: addon.license.buyer }) }}</span>
                            <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                            <span>{{ addon.license.license_type_label }}</span>
                            <span v-if="!addon.license.domain_ok" class="text-amber-500">{{ t('Domain changed') }}</span>
                        </div>
                    </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                        <Link v-if="addon.settings?.length" :href="route('admin.addons.settings', { slug: addon.slug })" :aria-label="t('Settings for :name addon', { name: addon.name })" class="px-3 py-2 bg-white dark:bg-gray-700 text-primary-500 border border-gray-200 dark:border-gray-700 dark:text-primary-500 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 dark:border-gray-600 text-sm">{{ t('Settings') }}</Link>
                        <button
                            v-if="addon.is_active"
                            @click="deactivate(addon.slug)"
                            :disabled="processing[addon.slug]"
                            :aria-label="t('Deactivate :name addon', { name: addon.name })"
                            class="px-4 py-2 bg-red-500/10 text-red-500 hover:bg-red-500/20 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="processing[addon.slug]" class="inline-flex items-center gap-2">
                                <i class="ti ti-loader-2 animate-spin"></i>
                                {{ t('Deactivating...') }}
                            </span>
                            <span v-else>{{ t('Deactivate') }}</span>
                        </button>
                        <button
                            v-else-if="addon.license_ok"
                            @click="handleActivate(addon)"
                            :disabled="processing[addon.slug]"
                            :aria-label="t('Activate :name addon', { name: addon.name })"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="processing[addon.slug]" class="inline-flex items-center gap-2">
                                <i class="ti ti-loader-2 animate-spin"></i>
                                {{ t('Activating...') }}
                            </span>
                            <span v-else>{{ t('Activate') }}</span>
                        </button>
                        <span v-else class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm">{{ t('Locked') }}</span>
                        <button
                            v-if="!addon.is_active"
                            type="button"
                            :aria-label="t('Delete :name addon', { name: addon.name })"
                            class="px-4 py-2 bg-red-500/10 text-red-500 hover:bg-red-500/20 rounded-lg text-sm font-medium transition-colors"
                            @click="confirmDelete(addon)"
                        >
                            {{ t('Delete') }}
                        </button>
                    </div>
                </div>
            </div>
                </div>

                <div v-else class="px-6 py-16 text-center">
                    <i class="ti ti-puzzle-off text-5xl text-gray-300 dark:text-gray-600"></i>
                    <p class="mt-4 text-gray-500 dark:text-gray-400 text-sm mb-2">{{ t('No addons installed yet') }}</p>
                    <p class="text-gray-400 dark:text-gray-500 text-xs mb-6">{{ t('Upload an addon zip or place it manually in the addons directory.') }}</p>
                    <button @click="showUploadModal = true" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white btn-primary">
                        <i class="ti ti-upload text-base"></i>
                        {{ t('Upload Addon') }}
                    </button>
                </div>
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
    <Teleport to="body">
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showUploadModal = false" />
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Upload Addon') }}</h2>
                    <button @click="showUploadModal = false" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ t('Upload a .zip file containing the addon.') }}</p>
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
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showUploadModal = false" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">{{ t('Cancel') }}</button>
                    <button @click="handleUpload" :disabled="uploading || !selectedFile" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-sm font-medium transition-colors flex items-center gap-2">
                        <i v-if="uploading" class="ti ti-loader-2 text-base animate-spin"></i>
                        {{ uploading ? t('Installing...') : t('Install Addon') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- License Verification Modal -->
    <Teleport to="body">
        <div v-if="licenseModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeLicenseModal" />
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Activate :name', { name: licenseAddonName }) }}</h2>
                    <button @click="closeLicenseModal" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ t('Enter your Envato purchase code for this addon. Find it in Envato Downloads and the license certificate purchase code section.') }}</p>

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

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="closeLicenseModal" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">{{ t('Cancel') }}</button>
                    <button
                        @click="verifyLicense"
                        :disabled="licenseVerifying || !purchaseCode.trim()"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-sm font-medium transition-colors flex items-center gap-2"
                    >
                        <i v-if="licenseVerifying" class="ti ti-loader-2 text-base animate-spin"></i>
                        {{ licenseVerifying ? t('Verifying...') : t('Verify & Activate') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
