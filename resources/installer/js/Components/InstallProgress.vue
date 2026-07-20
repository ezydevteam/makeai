<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps<{
    // 'running' while the finalize request is in flight, 'done' once it resolves
    // successfully. The bar caps below 100% until 'done' so it never claims to
    // have finished before the server actually has.
    status: 'running' | 'done'
}>()

// Ordered to mirror the real finalize phases in InstallController::finalize().
const phases = [
    'Configuring environment',
    'Running database migrations',
    'Seeding application data',
    'Creating admin & activating license',
    'Optimizing & clearing caches',
]

const RUN_CAP = 99 // hold here until the server confirms completion
const progress = ref(0)
let raf = 0
let last = 0

function frame(now: number) {
    if (!last) last = now
    const dt = Math.min(0.05, (now - last) / 1000)
    last = now

    if (props.status === 'done') {
        progress.value += (100 - progress.value) * Math.min(1, dt * 6)
    } else {
        // Brisk at first, decelerating as it approaches the cap — reads as real
        // work rather than a linear timer.
        const remaining = RUN_CAP - progress.value
        const rate = Math.min(10, Math.max(0.5, remaining * 0.5))
        progress.value = Math.min(RUN_CAP, progress.value + rate * dt)
    }

    raf = requestAnimationFrame(frame)
}

onMounted(() => { raf = requestAnimationFrame(frame) })
onBeforeUnmount(() => cancelAnimationFrame(raf))

watch(() => props.status, () => { last = 0 })

const pct = computed(() => Math.min(100, Math.round(progress.value)))

const activeIndex = computed(() => {
    if (props.status === 'done') return phases.length
    const seg = RUN_CAP / phases.length
    return Math.min(phases.length - 1, Math.floor(progress.value / seg))
})

const currentLabel = computed(() =>
    props.status === 'done' ? 'Almost there…' : (phases[activeIndex.value] ?? 'Finishing up'),
)

function phaseState(i: number): 'done' | 'active' | 'pending' {
    if (props.status === 'done' || i < activeIndex.value) return 'done'
    if (i === activeIndex.value) return 'active'
    return 'pending'
}
</script>

<template>
    <div class="my-8 animate-fade-in rounded-2xl border border-slate-200 bg-white p-8 shadow-xl">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-500/30">
                <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                </svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-slate-900">Installing MakeAI</h2>
                <p class="truncate text-sm text-slate-500">{{ currentLabel }}</p>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="mt-7">
            <div class="mb-2 flex items-end justify-between">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Progress</span>
                <span class="font-mono text-2xl font-extrabold tabular-nums text-slate-900">
                    {{ pct }}<span class="text-base font-bold text-slate-400">%</span>
                </span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    class="relative h-full rounded-full bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-500 bg-[length:200%_100%] transition-[width] duration-200 ease-out animate-gradient-x"
                    :style="{ width: pct + '%' }"
                >
                    <span class="progress-shimmer absolute inset-0"></span>
                </div>
            </div>
        </div>

        <!-- Phase checklist -->
        <ul class="mt-8 space-y-3.5">
            <li
                v-for="(p, i) in phases"
                :key="p"
                class="flex items-center gap-3 transition-opacity"
                :class="phaseState(i) === 'pending' ? 'opacity-50' : 'opacity-100'"
            >
                <!-- Done -->
                <span
                    v-if="phaseState(i) === 'done'"
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
                <!-- Active -->
                <span
                    v-else-if="phaseState(i) === 'active'"
                    class="flex h-6 w-6 shrink-0 items-center justify-center"
                >
                    <svg class="h-5 w-5 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                </span>
                <!-- Pending -->
                <span
                    v-else
                    class="flex h-6 w-6 shrink-0 items-center justify-center"
                >
                    <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                </span>

                <span
                    class="text-sm transition-colors"
                    :class="phaseState(i) === 'active' ? 'font-semibold text-slate-900'
                        : phaseState(i) === 'done' ? 'font-medium text-slate-700' : 'text-slate-500'"
                >
                    {{ p }}
                </span>
            </li>
        </ul>

        <p class="mt-8 text-center text-xs text-slate-400">
            Please keep this window open — this may take a few minutes to complete.
        </p>
    </div>
</template>

<style scoped>
/* Sweeping highlight across the filled portion of the bar. */
.progress-shimmer {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.45) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    transform: translateX(-100%);
    animation: progress-shimmer 1.6s ease-in-out infinite;
}

@keyframes progress-shimmer {
    100% {
        transform: translateX(100%);
    }
}

/* Slow drift of the gradient so the fill feels alive even when width is static. */
.animate-gradient-x {
    animation: gradient-x 3s ease infinite;
}

@keyframes gradient-x {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
</style>
