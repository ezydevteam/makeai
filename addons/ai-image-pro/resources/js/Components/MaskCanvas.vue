<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch, nextTick } from 'vue'
import * as fabric from 'fabric'
import { useTranslate } from '@/Composables/useTranslate'

const { t } = useTranslate()

const props = withDefaults(
    defineProps<{
        imageUrl: string
        brushSize?: number
        brushColor?: string
        maxDisplayHeight?: number
    }>(),
    { brushSize: 30, brushColor: '#ffffff', maxDisplayHeight: 460 },
)

const containerEl = ref<HTMLDivElement | null>(null)
const canvasEl = ref<HTMLCanvasElement | null>(null)
const loading = ref(true)
const errorMessage = ref('')

let canvas: fabric.Canvas | null = null
// Uniform scale from the displayed canvas back up to the source image, so the
// exported mask lines up 1:1 with the pixels the server operates on.
let exportMultiplier = 1

function configureBrush(): void {
    if (!canvas) return
    canvas.isDrawingMode = true
    const brush = new fabric.PencilBrush(canvas)
    brush.width = props.brushSize
    brush.color = props.brushColor
    canvas.freeDrawingBrush = brush
}

async function build(): Promise<void> {
    loading.value = true
    errorMessage.value = ''
    dispose()

    await nextTick()
    if (!canvasEl.value || !containerEl.value) return

    try {
        const image = await fabric.Image.fromURL(props.imageUrl, { crossOrigin: 'anonymous' })
        const naturalWidth = image.width ?? 1
        const naturalHeight = image.height ?? 1

        const boxWidth = containerEl.value.clientWidth || 640
        const scale = Math.min(boxWidth / naturalWidth, props.maxDisplayHeight / naturalHeight, 1)
        const displayWidth = Math.round(naturalWidth * scale)
        const displayHeight = Math.round(naturalHeight * scale)
        exportMultiplier = displayWidth > 0 ? naturalWidth / displayWidth : 1

        canvas = new fabric.Canvas(canvasEl.value, {
            width: displayWidth,
            height: displayHeight,
            selection: false,
        })

        image.set({ left: 0, top: 0, selectable: false, evented: false })
        image.scale(scale)
        canvas.backgroundImage = image
        canvas.renderAll()

        configureBrush()
    } catch {
        errorMessage.value = t('We could not load this image for editing.')
    } finally {
        loading.value = false
    }
}

function dispose(): void {
    if (canvas) {
        void canvas.dispose()
        canvas = null
    }
}

/** True once the user has painted at least one stroke. */
function hasMask(): boolean {
    return (canvas?.getObjects().length ?? 0) > 0
}

function clear(): void {
    if (!canvas) return
    canvas.getObjects().forEach((object) => canvas?.remove(object))
    canvas.renderAll()
}

/**
 * Export the painted region as a PNG mask: white strokes on a solid black
 * field, upscaled back to the source image resolution. The background image is
 * hidden for the export so only the mask survives, then restored.
 */
async function exportMask(): Promise<Blob | null> {
    if (!canvas || !hasMask()) return null

    const backgroundImage = canvas.backgroundImage
    const backgroundColor = canvas.backgroundColor

    canvas.backgroundImage = undefined
    canvas.backgroundColor = '#000000'
    canvas.renderAll()

    const dataUrl = canvas.toDataURL({ format: 'png', multiplier: exportMultiplier })

    canvas.backgroundImage = backgroundImage
    canvas.backgroundColor = backgroundColor
    canvas.renderAll()

    const response = await fetch(dataUrl)

    return response.blob()
}

defineExpose({ exportMask, clear, hasMask })

onMounted(build)
onBeforeUnmount(dispose)

watch(() => props.imageUrl, build)
watch(
    () => props.brushSize,
    (size) => {
        if (canvas?.freeDrawingBrush) canvas.freeDrawingBrush.width = size
    },
)
watch(
    () => props.brushColor,
    (color) => {
        if (canvas?.freeDrawingBrush) canvas.freeDrawingBrush.color = color
    },
)
</script>

<template>
    <div ref="containerEl" class="relative flex w-full items-center justify-center rounded-xl bg-gray-100 p-2 dark:bg-surface-950">
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center">
            <i class="ti ti-loader-2 animate-spin text-2xl text-gray-400"></i>
        </div>

        <div v-if="errorMessage" class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center">
            <i class="ti ti-photo-x text-2xl text-red-400"></i>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ errorMessage }}</p>
            <button
                type="button"
                class="rounded-lg bg-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-300 dark:bg-surface-800 dark:text-gray-200"
                @click="build"
            >
                {{ t('Retry') }}
            </button>
        </div>

        <canvas ref="canvasEl" class="max-w-full touch-none rounded-lg" :class="{ 'opacity-0': loading || errorMessage }"></canvas>
    </div>
</template>
