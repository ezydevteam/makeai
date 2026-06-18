<script setup lang="ts">
import { toastStore, removeToast } from '@/Composables/useToastr'
import Toast from '@/Components/Toast.vue'

function onPause(id: number) {
    const t = toastStore.toasts.find(toast => toast.id === id)
    if (t) t.paused = true
}

function onResume(id: number) {
    const t = toastStore.toasts.find(toast => toast.id === id)
    if (t) t.paused = false
}
</script>

<template>
    <Teleport to="body">
        <TransitionGroup
            tag="div"
            name="toast-container"
            class="fixed top-4 right-4 z-[99999] flex flex-col gap-3 pointer-events-none"
            move-class="toast-move"
        >
            <div
                v-for="toast in toastStore.toasts"
                :key="toast.id"
                class="pointer-events-auto"
            >
                <Toast :toast="toast" @close="removeToast" @pause="onPause" @resume="onResume" />
            </div>
        </TransitionGroup>
    </Teleport>
</template>
