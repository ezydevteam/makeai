<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

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
    license?: LicenseInfo | null
    settings?: Array<{ key: string; label: string; type: string; default: any }>
}

const props = defineProps<{ addons: AddonConfig[] }>()
const activate = (slug: string) => router.post(route('admin.addons.activate', { slug }))
const deactivate = (slug: string) => router.post(route('admin.addons.deactivate', { slug }))

const showUploadModal = ref(false)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)

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
            alert(data.message ?? 'Upload failed.')
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
            licenseError.value = data.error ?? 'Verification failed.'
            licenseErrorCode.value = data.error_code ?? ''
        }
    } catch {
        licenseError.value = 'Could not reach server. Please try again.'
        licenseErrorCode.value = 'connection_error'
    } finally {
        licenseVerifying.value = false
    }
}

// ─── Activation entry point ───
function handleActivate(addon: AddonConfig) {
    // Addons with envato_item_id AND no valid license → show license modal first
    if (addon.envato_item_id && !addon.license?.license_type) {
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
</script>

<template>
    <Head :title="$t('Addons — Admin')" />
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-1">Addon Manager</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Install, activate, and configure platform addons.</p>
            </div>
            <button @click="showUploadModal = true" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                Upload Addon
            </button>
        </div>

        <div class="space-y-4">
            <div v-for="addon in addons" :key="addon.slug" :class="[addon.is_active ? 'border-primary-500/30 bg-primary-50/30 dark:bg-primary-500/5' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800']" class="border rounded-xl p-5 flex flex-col gap-4 shadow-sm">
                <div class="flex items-center gap-5">
                    <div :class="[addon.is_active ? 'bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400']" class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.959.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z" /></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <h3 class="text-gray-900 dark:text-white font-semibold">{{ addon.name }}</h3>
                            <span class="text-xs text-gray-500 dark:text-gray-400">v{{ addon.version }}</span>
                            <span v-if="addon.is_active" class="px-2 py-0.5 bg-emerald-500/15 text-emerald-500 text-[10px] font-bold rounded-full">ACTIVE</span>
                            <span v-if="addon.license?.status === 'grace'" class="px-2 py-0.5 bg-amber-500/15 text-amber-500 text-[10px] font-bold rounded-full">⚠ GRACE</span>
                            <span v-if="!addon.license_ok" class="px-2 py-0.5 bg-red-500/15 text-red-500 text-[10px] font-bold rounded-full">🔒 LICENSE</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ addon.description || 'No description' }}</p>

                        <!-- License info row -->
                        <div v-if="addon.license?.buyer" class="flex items-center gap-3 mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <span>Licensed to {{ addon.license.buyer }}</span>
                            <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600" />
                            <span>{{ addon.license.license_type_label }}</span>
                            <span v-if="!addon.license.domain_ok" class="text-amber-500">⚠ Domain changed</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <Link v-if="addon.is_active && addon.settings?.length" :href="route('admin.addons.settings', { slug: addon.slug })" class="px-3 py-2 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 text-sm">Settings</Link>
                        <button v-if="addon.is_active" @click="deactivate(addon.slug)" class="px-4 py-2 bg-red-500/10 text-red-500 hover:bg-red-500/20 rounded-lg text-sm font-medium transition-colors">Deactivate</button>
                        <button v-else-if="addon.license_ok" @click="handleActivate(addon)" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors">Activate</button>
                        <span v-else class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 rounded-lg text-sm">Locked</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!addons.length" class="text-center py-16 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm">
            <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
            </svg>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">No addons installed yet</p>
            <p class="text-gray-400 dark:text-gray-500 text-xs mb-6">Upload an addon zip or place it manually in the <code class="text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">addons/</code> directory</p>
            <button @click="showUploadModal = true" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                Upload Addon
            </button>
        </div>
    </div>

    <!-- Upload Modal -->
    <Teleport to="body">
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showUploadModal = false" />
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload Addon</h2>
                    <button @click="showUploadModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Upload a <code class="text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">.zip</code> file containing the addon.</p>
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors" :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-500/10': selectedFile }" @click="fileInput?.click()">
                    <input ref="fileInput" type="file" name="addon_zip" accept=".zip" required class="hidden" @change="onFilePicked" />
                    <template v-if="!selectedFile">
                        <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" /></svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Click to select a <code class="text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded text-xs">.zip</code> file</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Max 20 MB</p>
                    </template>
                    <template v-else>
                        <svg class="w-10 h-10 text-blue-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ selectedFile.name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ (selectedFile.size / 1024).toFixed(1) }} KB</p>
                    </template>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="showUploadModal = false" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                    <button @click="handleUpload" :disabled="uploading || !selectedFile" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-sm font-medium transition-colors flex items-center gap-2">
                        <svg v-if="uploading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        {{ uploading ? 'Installing...' : 'Install Addon' }}
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
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activate {{ licenseAddonName }}</h2>
                    <button @click="closeLicenseModal" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Enter your Envato purchase code for this addon. Find it in Envato → Downloads → License certificate & purchase code.</p>

                <!-- Error -->
                <div v-if="licenseError" class="mb-4 p-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg text-sm text-red-600 dark:text-red-400 flex items-start gap-2">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    <span>{{ licenseError }}</span>
                </div>

                <!-- Purchase code input -->
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Purchase Code</label>
                <input
                    :value="purchaseCode"
                    @input="onPurchaseCodeInput"
                    type="text"
                    placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                    maxlength="36"
                    class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 font-mono focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                />

                <!-- Help link -->
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 mb-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                    This code is different from your MakeAI core purchase code.
                </p>
                <a
                    href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code"
                    target="_blank"
                    class="text-xs text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300 underline"
                >Where do I find this?</a>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="closeLicenseModal" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Cancel</button>
                    <button
                        @click="verifyLicense"
                        :disabled="licenseVerifying || !purchaseCode.trim()"
                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-xl text-sm font-medium transition-colors flex items-center gap-2"
                    >
                        <svg v-if="licenseVerifying" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        {{ licenseVerifying ? 'Verifying...' : 'Verify & Activate' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
