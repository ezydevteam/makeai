<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import * as fabric from 'fabric'
import axios from 'axios'

const { t } = useTranslate()
const page = usePage()

const props = defineProps<{
    session: {
        id: number
        ulid: string
        source_path: string
        source_url: string
        current_path: string
        current_url: string
        width: number | null
        height: number | null
    }
    history: {
        id: number
        ulid: string
        operation: string
        status: string
        output_url: string | null
        version_number: number
        is_current: boolean
        created_at: string
        error_message: string | null
    }[]
    operationsAvailable: Record<string, { available: boolean; credits: number }>
    maxOutputDimension: number
}>()

const creditCosts = (page.props.imageEditor as any)?.creditCosts || {}
const isProcessing = ref(false)
const processingOperation = ref('')
const currentVersion = ref(props.session.width && props.session.height ? `${props.session.width}×${props.session.height}px` : '')
const currentImageUrl = ref(props.session.current_url || props.session.source_url)
const canvasRef = ref<HTMLCanvasElement | null>(null)
const fabricCanvas: any = ref(null)

// Operation state
const activeOperation = ref('')
const brushMode = ref(false)
const brushSize = ref(30)
const maskColor = ref('#ffffff')

// Inpaint
const inpaintPrompt = ref('')
const inpaintNegativePrompt = ref('')

// Outpaint
const outpaintExpand = ref({ left: 0, right: 0, up: 0, down: 0 })
const outpaintPrompt = ref('')

// Upscale
const upscaleFactor = ref(4)

// Style Transfer
const styleFile = ref<File | null>(null)
const stylePreview = ref('')
const styleFidelity = ref(0.5)

// Color Correction
const colorParams = ref({
    brightness: 0,
    contrast: 0,
    saturation: 0,
    hue: 0,
    sharpness: 0,
})

// Text Overlay
const textOverlay = ref({
    text: '',
    font_size: 48,
    font_color: '#FFFFFF',
    x: 0,
    y: 50,
})

const operations = [
    { key: 'inpaint', label: t('Inpainting'), icon: 'ti ti-brush', credits: creditCosts.inpaint },
    { key: 'outpaint', label: t('Outpainting'), icon: 'ti ti-arrows-diagonal', credits: creditCosts.outpaint },
    { key: 'bg_remove', label: t('Remove Background'), icon: 'ti ti-eraser', credits: creditCosts.bg_remove },
    { key: 'upscale', label: t('Upscale 4×'), icon: 'ti ti-zoom-in', credits: creditCosts.upscale },
    { key: 'style_transfer', label: t('Style Transfer'), icon: 'ti ti-palette', credits: creditCosts.style_transfer },
    { key: 'object_remove', label: t('Remove Object'), icon: 'ti ti-trash', credits: creditCosts.object_remove },
    { key: 'color_correction', label: t('Color Correction'), icon: 'ti ti-adjustments', credits: creditCosts.color_correction },
    { key: 'text_overlay', label: t('Text Overlay'), icon: 'ti ti-typography', credits: creditCosts.text_overlay },
]

const activeTab = ref('operations')

function applyPreset(preset: string) {
    if (preset === 'restore_old_photo') {
        selectOperation('upscale')
        upscaleFactor.value = 4
        applyOperation()
    } else if (preset === 'enhance_details') {
        selectOperation('upscale')
        upscaleFactor.value = 2
        applyOperation()
    }
}

function getOperationLabel(operation: string): string {
    const map: Record<string, string> = {
        inpaint: t('Inpainting'),
        outpaint: t('Outpainting'),
        bg_remove: t('Background Removal'),
        upscale: t('Upscaling'),
        style_transfer: t('Style Transfer'),
        object_remove: t('Object Removal'),
        color_correction: t('Color Correction'),
        text_overlay: t('Text Overlay'),
    }
    return map[operation] || operation
}

function getOperationIcon(operation: string): string {
    const op = operations.find(o => o.key === operation)
    return op?.icon || 'ti ti-photo'
}

function getStatusBadge(status: string): string {
    return status === 'completed' ? 'badge-green' : status === 'processing' ? 'badge-blue' : status === 'failed' ? 'badge-red' : 'badge-gray'
}

function relativeTime(date: string): string {
    const diff = Date.now() - new Date(date).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1) return t('Just now')
    if (mins < 60) return `${mins} min ago`
    const hours = Math.floor(mins / 60)
    return `${hours}h ago`
}

const sortedHistory = computed(() => [...props.history].sort((a, b) => b.version_number - a.version_number))

// Upload style image
function onStyleFileChange(e: Event) {
    const target = e.target as HTMLInputElement
    if (target.files?.[0]) {
        styleFile.value = target.files[0]
        stylePreview.value = URL.createObjectURL(target.files[0])
    }
}

// Fabric.js initialization
async function initCanvas() {
    if (!canvasRef.value) return
    
    const container = document.getElementById('canvas-container')
    const width = container?.clientWidth || 800
    const height = container?.clientHeight || 600

    const canvas = new fabric.Canvas(canvasRef.value, {
        width,
        height,
    })
    fabricCanvas.value = canvas

    if (currentImageUrl.value) {
        const img = await fabric.Image.fromURL(currentImageUrl.value)
        if (!img) return

        const scale = Math.min(width / img.width!, height / img.height!, 1)
        img.scale(scale)
        img.set({ 
            left: (width - img.width! * scale) / 2, 
            top: (height - img.height! * scale) / 2,
            selectable: false 
        })
        canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas))
    }
}

function toggleBrush() {
    brushMode.value = !brushMode.value
    if (fabricCanvas.value) {
        fabricCanvas.value.isDrawingMode = brushMode.value
        if (brushMode.value) {
            fabricCanvas.value.freeDrawingBrush = new fabric.PencilBrush(fabricCanvas.value)
            fabricCanvas.value.freeDrawingBrush.width = brushSize.value
            fabricCanvas.value.freeDrawingBrush.color = maskColor.value
        }
    }
}

async function exportMask(): Promise<Blob | null> {
    if (!fabricCanvas.value) return null
    const dataUrl = fabricCanvas.value.toDataURL({ format: 'png', multiplier: 1 })
    const resp = await fetch(dataUrl)
    return await resp.blob()
}

function clearBrush() {
    if (brushMode.value) {
        toggleBrush()
    }
    if (fabricCanvas.value) {
        fabricCanvas.value.getObjects().forEach((obj: any) => {
            if (obj !== fabricCanvas.value.backgroundImage) fabricCanvas.value.remove(obj)
        })
        fabricCanvas.value.renderAll()
    }
}

async function applyOperation() {
    if (!activeOperation.value || isProcessing.value) return

    isProcessing.value = true
    processingOperation.value = activeOperation.value

    const fd = new FormData()
    fd.append('operation', activeOperation.value)

    if (activeOperation.value === 'inpaint') {
        fd.append('params[prompt]', inpaintPrompt.value)
        fd.append('params[negative_prompt]', inpaintNegativePrompt.value)
        const mask = await exportMask()
        if (mask) fd.append('mask', mask, 'mask.png')
    } else if (activeOperation.value === 'outpaint') {
        fd.append('params[left]', String(outpaintExpand.value.left))
        fd.append('params[right]', String(outpaintExpand.value.right))
        fd.append('params[up]', String(outpaintExpand.value.up))
        fd.append('params[down]', String(outpaintExpand.value.down))
        if (outpaintPrompt.value) fd.append('params[prompt]', outpaintPrompt.value)
    } else if (activeOperation.value === 'upscale') {
        fd.append('params[scale]', String(upscaleFactor.value))
    } else if (activeOperation.value === 'style_transfer') {
        fd.append('params[fidelity]', String(styleFidelity.value))
        if (styleFile.value) fd.append('style_image', styleFile.value)
    } else if (activeOperation.value === 'object_remove') {
        const mask = await exportMask()
        if (mask) fd.append('mask', mask, 'mask.png')
    } else if (activeOperation.value === 'color_correction') {
        fd.append('params[brightness]', String(colorParams.value.brightness))
        fd.append('params[contrast]', String(colorParams.value.contrast))
        fd.append('params[saturation]', String(colorParams.value.saturation))
        fd.append('params[hue]', String(colorParams.value.hue))
        fd.append('params[sharpness]', String(colorParams.value.sharpness))
    } else if (activeOperation.value === 'text_overlay') {
        fd.append('params[text]', textOverlay.value.text)
        fd.append('params[font_size]', String(textOverlay.value.font_size))
        fd.append('params[font_color]', textOverlay.value.font_color)
        fd.append('params[x]', String(textOverlay.value.x))
        fd.append('params[y]', String(textOverlay.value.y))
    }

    try {
        const resp = await axios.post(route('addon.ie.user.apply'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        const editUlid = resp.data.edit_ulid
        
        // Listen for completion via Echo
        const userId = page.props.auth.user?.id
        if (window.Echo && userId) {
            window.Echo.private(`user.${userId}`)
                .listen('ImageEditCompleted', (e: any) => {
                    if (e.edit_ulid === editUlid) {
                        if (e.status === 'completed') {
                            currentImageUrl.value = e.output_url
                            isProcessing.value = false
                            window.location.reload()
                        } else if (e.status === 'failed') {
                            isProcessing.value = false
                            alert(e.error || t('Edit failed'))
                            window.location.reload()
                        }
                    }
                })
        } else {
            // Fallback for polling if Echo is not available
            pollStatus(editUlid)
        }
    } catch (e: any) {
        isProcessing.value = false
        alert(e.response?.data?.message || t('Failed to apply operation'))
    }
}

function pollStatus(editUlid: string) {
    const interval = setInterval(async () => {
        try {
            const resp = await axios.get(route('addon.ie.user.edits.status', { edit: editUlid }))
            const status = resp.data.status
            if (status === 'completed') {
                currentImageUrl.value = resp.data.output_url
                isProcessing.value = false
                clearInterval(interval)
                window.location.reload()
            } else if (status === 'failed') {
                isProcessing.value = false
                clearInterval(interval)
                alert(resp.data.error || t('Edit failed'))
                window.location.reload()
            }
        } catch (e) {
            // continue polling
        }
    }, 3000)
}

async function revertToVersion(edit: any) {
    try {
        await axios.post(route('addon.ie.user.edits.revert', { edit: edit.id }))
        currentImageUrl.value = edit.output_url
    } catch (e: any) {
        alert(e.response?.data?.message || t('Failed to revert'))
    }
}

async function saveToLibrary(edit: any) {
    try {
        await axios.post(route('addon.ie.user.edits.save', { edit: edit.id }))
        alert(t('Saved to library'))
    } catch (e: any) {
        alert(e.response?.data?.message || t('Failed to save'))
    }
}

onMounted(() => {
    nextTick(() => {
        initCanvas()
    })
})

onUnmounted(() => {
    if (fabricCanvas.value) {
        fabricCanvas.value.dispose()
    }
})

watch(brushSize, (size) => {
    if (fabricCanvas.value?.freeDrawingBrush) {
        fabricCanvas.value.freeDrawingBrush.width = size
    }
})
</script>

<template>
    <Head :title="t('AI Image Editor')" />

    <div class="flex flex-col h-screen bg-gray-900">
        <!-- Top Bar -->
        <div class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700 shrink-0">
            <div class="flex items-center gap-3">
                <Link :href="route('user.dashboard')" class="text-gray-400 hover:text-white text-sm">← {{ t('Back') }}</Link>
                <span class="text-gray-500">|</span>
                <span class="text-gray-300 text-sm">{{ currentVersion }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="badge badge-blue text-xs">{{ t('Credits') }}: {{ page.props.auth.user?.credits || 0 }}</span>
            </div>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <!-- Left Panel — Operations -->
            <div class="w-[280px] bg-gray-800 border-r border-gray-700 overflow-y-auto shrink-0">
                <div class="flex border-b border-gray-700">
                    <button @click="activeTab = 'operations'" :class="['flex-1 py-2 text-xs font-semibold uppercase', activeTab === 'operations' ? 'text-white border-b-2 border-blue-500' : 'text-gray-500']">
                        {{ t('Operations') }}
                    </button>
                    <button @click="activeTab = 'presets'" :class="['flex-1 py-2 text-xs font-semibold uppercase', activeTab === 'presets' ? 'text-white border-b-2 border-blue-500' : 'text-gray-500']">
                        {{ t('Presets') }}
                    </button>
                </div>

                <!-- Operations Tab -->
                <div v-if="activeTab === 'operations'" class="p-3 space-y-3">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('AI Operations') }}</h3>
                    <!-- ... (Existing operation list) ... -->
                </div>

                <!-- Presets Tab -->
                <div v-else class="p-3 space-y-3">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ t('Quick Presets') }}</h3>
                    <button @click="applyPreset('restore_old_photo')" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm bg-gray-700 text-gray-200 hover:bg-gray-600">
                        <i class="ti ti-camera-up"></i> <span>{{ t('Restore Old Photo') }}</span>
                    </button>
                    <button @click="applyPreset('enhance_details')" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm bg-gray-700 text-gray-200 hover:bg-gray-600">
                        <i class="ti ti-zoom-in"></i> <span>{{ t('Enhance Details') }}</span>
                    </button>
                </div>


                <!-- Operation Parameter Forms -->
                <div v-if="activeOperation" class="p-3 border-t border-gray-700 space-y-3">
                    <h4 class="text-sm font-medium text-gray-300">{{ getOperationLabel(activeOperation) }}</h4>

                    <!-- Inpaint Form -->
                    <template v-if="activeOperation === 'inpaint'">
                        <p class="text-xs text-gray-500">{{ t('Brush over the area you want to regenerate') }}</p>
                        <button @click="toggleBrush" :class="['btn w-full text-sm py-1.5', brushMode ? 'btn-danger' : 'btn-secondary']">
                            {{ brushMode ? t('Stop Brush') : t('Start Brush') }}
                        </button>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Brush Size') }}</label>
                            <input type="range" v-model.number="brushSize" min="10" max="100" class="w-full" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Prompt') }}</label>
                            <textarea v-model="inpaintPrompt" class="input w-full text-sm" rows="2" :placeholder="t('What to fill in?')"></textarea>
                        </div>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Applying...') : t('Apply Inpaint') }}
                        </button>
                    </template>

                    <!-- Outpaint Form -->
                    <template v-if="activeOperation === 'outpaint'">
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs text-gray-400">{{ t('Left') }}</label><input type="number" v-model.number="outpaintExpand.left" min="0" max="2000" class="input w-full text-sm" /></div>
                            <div><label class="text-xs text-gray-400">{{ t('Right') }}</label><input type="number" v-model.number="outpaintExpand.right" min="0" max="2000" class="input w-full text-sm" /></div>
                            <div><label class="text-xs text-gray-400">{{ t('Up') }}</label><input type="number" v-model.number="outpaintExpand.up" min="0" max="2000" class="input w-full text-sm" /></div>
                            <div><label class="text-xs text-gray-400">{{ t('Down') }}</label><input type="number" v-model.number="outpaintExpand.down" min="0" max="2000" class="input w-full text-sm" /></div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Prompt (optional)') }}</label>
                            <input v-model="outpaintPrompt" class="input w-full text-sm" :placeholder="t('What should appear in the extended area?')" />
                        </div>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Applying...') : t('Apply Outpaint') }}
                        </button>
                    </template>

                    <!-- Background Removal -->
                    <template v-if="activeOperation === 'bg_remove'">
                        <p class="text-xs text-gray-500">{{ t('Removes the background and creates a transparent PNG.') }}</p>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Removing...') : t('Remove Background') }}
                        </button>
                    </template>

                    <!-- Upscale -->
                    <template v-if="activeOperation === 'upscale'">
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Scale Factor') }}</label>
                            <div class="flex gap-2">
                                <button @click="upscaleFactor = 2" :class="['btn text-sm py-1 px-3', upscaleFactor === 2 ? 'btn-primary' : 'btn-secondary']">2×</button>
                                <button @click="upscaleFactor = 4" :class="['btn text-sm py-1 px-3', upscaleFactor === 4 ? 'btn-primary' : 'btn-secondary']">4×</button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">
                            {{ t('Estimated output') }}: {{ (props.session.width || 0) * upscaleFactor }}×{{ (props.session.height || 0) * upscaleFactor }}px
                        </p>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Upscaling...') : t('Upscale Image') }}
                        </button>
                    </template>

                    <!-- Style Transfer -->
                    <template v-if="activeOperation === 'style_transfer'">
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Style Image') }}</label>
                            <input type="file" accept=".jpg,.jpeg,.png,.webp" @change="onStyleFileChange" class="input w-full text-sm" />
                            <img v-if="stylePreview" :src="stylePreview" class="mt-2 rounded max-h-20" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Fidelity') }}</label>
                            <input type="range" v-model.number="styleFidelity" min="0" max="1" step="0.1" class="w-full" />
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>{{ t('Low') }}</span>
                                <span>{{ t('High') }}</span>
                            </div>
                        </div>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Applying...') : t('Apply Style') }}
                        </button>
                    </template>

                    <!-- Object Removal -->
                    <template v-if="activeOperation === 'object_remove'">
                        <p class="text-xs text-gray-500">{{ t('Brush over the object you want to remove') }}</p>
                        <button @click="toggleBrush" :class="['btn w-full text-sm py-1.5', brushMode ? 'btn-danger' : 'btn-secondary']">
                            {{ brushMode ? t('Stop Brush') : t('Start Brush') }}
                        </button>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Brush Size') }}</label>
                            <input type="range" v-model.number="brushSize" min="10" max="100" class="w-full" />
                        </div>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Removing...') : t('Remove Object') }}
                        </button>
                    </template>

                    <!-- Color Correction -->
                    <template v-if="activeOperation === 'color_correction'">
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Brightness') }}: {{ colorParams.brightness }}</label>
                            <input type="range" v-model.number="colorParams.brightness" min="-100" max="100" class="w-full" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Contrast') }}: {{ colorParams.contrast }}</label>
                            <input type="range" v-model.number="colorParams.contrast" min="-100" max="100" class="w-full" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Saturation') }}: {{ colorParams.saturation }}</label>
                            <input type="range" v-model.number="colorParams.saturation" min="-100" max="100" class="w-full" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Hue') }}: {{ colorParams.hue }}°</label>
                            <input type="range" v-model.number="colorParams.hue" min="0" max="360" class="w-full" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Sharpness') }}: {{ colorParams.sharpness }}</label>
                            <input type="range" v-model.number="colorParams.sharpness" min="0" max="100" class="w-full" />
                        </div>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Applying...') : t('Apply Color Correction') }}
                        </button>
                    </template>

                    <!-- Text Overlay -->
                    <template v-if="activeOperation === 'text_overlay'">
                        <div>
                            <label class="text-xs text-gray-400">{{ t('Text') }}</label>
                            <input v-model="textOverlay.text" class="input w-full text-sm" :placeholder="t('Enter text')" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs text-gray-400">{{ t('Font Size') }}</label><input type="number" v-model.number="textOverlay.font_size" min="8" max="200" class="input w-full text-sm" /></div>
                            <div><label class="text-xs text-gray-400">{{ t('Color') }}</label><input type="color" v-model="textOverlay.font_color" class="w-full h-8 rounded" /></div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs text-gray-400">{{ t('X Position') }}</label><input type="number" v-model.number="textOverlay.x" min="0" class="input w-full text-sm" /></div>
                            <div><label class="text-xs text-gray-400">{{ t('Y Position') }}</label><input type="number" v-model.number="textOverlay.y" min="0" class="input w-full text-sm" /></div>
                        </div>
                        <button @click="applyOperation" :disabled="isProcessing" class="btn btn-primary w-full text-sm py-1.5">
                            {{ isProcessing ? t('Applying...') : t('Apply Text Overlay') }}
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex-1 bg-[#1a1a2e] flex items-center justify-center overflow-hidden p-4 relative">
        <div id="canvas-container" class="w-full h-full flex items-center justify-center">
            <canvas ref="canvasRef"></canvas>

            <!-- Status overlay -->
            <div v-if="isProcessing" class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center">
                <div class="spinner mb-3"></div>
                <span class="text-white text-sm">{{ t('Applying') }} {{ getOperationLabel(processingOperation) }}...</span>
            </div>
        </div>
    </div>

            <!-- Right Panel — History -->
            <div class="w-[280px] bg-gray-800 border-l border-gray-700 overflow-y-auto shrink-0">
                <div class="p-3">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">{{ t('Edit History') }}</h3>

                    <div v-if="sortedHistory.length === 0" class="text-xs text-gray-500 text-center py-8">
                        {{ t('Apply an operation to start your edit history') }}
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="edit in sortedHistory"
                            :key="edit.id"
                            class="p-2 rounded-lg bg-gray-750 border border-gray-700 text-xs"
                            :class="{ 'border-blue-500/50': edit.is_current }"
                        >
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="badge text-[10px]" :class="getStatusBadge(edit.status)">
                                    {{ edit.status }}
                                </span>
                                <i :class="getOperationIcon(edit.operation)" class="text-gray-400 text-[13px]"></i>
                                <span class="text-gray-300 text-[11px]">{{ getOperationLabel(edit.operation) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-gray-500">
                                <span>v{{ edit.version_number }}</span>
                                <span>{{ relativeTime(edit.created_at) }}</span>
                            </div>
                            <div v-if="edit.error_message" class="mt-1 text-[10px] text-red-400">{{ edit.error_message }}</div>

                            <div class="flex gap-1 mt-1.5">
                                <button
                                    v-if="edit.status === 'completed' && !edit.is_current"
                                    @click="revertToVersion(edit)"
                                    class="btn btn-secondary text-[10px] py-0.5 px-2"
                                >
                                    {{ t('Revert') }}
                                </button>
                                <button
                                    v-if="edit.status === 'completed' && edit.is_current"
                                    @click="saveToLibrary(edit)"
                                    class="btn btn-primary text-[10px] py-0.5 px-2"
                                >
                                    {{ t('Save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-gray-750 {
    background: #1f2937;
}
.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(255,255,255,0.2);
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
