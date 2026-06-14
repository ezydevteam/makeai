<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

const props = defineProps<{
    initial_month: string
    accounts: { id: number; platform: string; platform_label: string }[]
}>()

const currentDate = ref(new Date(props.initial_month + '-01'))
const events = ref<any[]>([])
const loading = ref(false)
const activePlatforms = ref<string[]>([])

const daysInMonth = computed(() => new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() + 1,
    0
).getDate())

const monthLabel = computed(() =>
    currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
)

const startOfMonth = computed(() =>
    new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
)

const firstDayOffset = computed(() => startOfMonth.value.getDay())

const dayDate = (day: number) => {
    const d = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day)
    return d.toISOString().split('T')[0]
}

const eventsForDay = (day: number) => {
    const dateStr = dayDate(day)
    return events.value.filter((e: any) => e.scheduled_at?.startsWith(dateStr))
}

const platformIcon = (p: string) => p === 'instagram' ? '📸' : p === 'facebook' ? '👍' : p === 'twitter' ? '🐦' : p === 'linkedin' ? '💼' : '📱'

const statusColor = (s: string) =>
    s === 'published' ? 'bg-emerald-500' : s === 'scheduled' ? 'bg-blue-500' :
    s === 'draft' ? 'bg-gray-400' : s === 'partial' ? 'bg-amber-500' : 'bg-red-500'

function fetchEvents() {
    loading.value = true
    const start = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
    const end = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0)
    const params: any = {
        start: start.toISOString().split('T')[0],
        end: end.toISOString().split('T')[0],
    }
    if (activePlatforms.value.length) params.platforms = activePlatforms.value
    axios.get(route('addon.social.user.calendar.events'), { params })
        .then(r => { events.value = r.data })
        .finally(() => { loading.value = false })
}

function prevMonth() {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
    fetchEvents()
}

function nextMonth() {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
    fetchEvents()
}

function togglePlatform(p: string) {
    const i = activePlatforms.value.indexOf(p)
    if (i >= 0) activePlatforms.value.splice(i, 1)
    else activePlatforms.value.push(p)
    fetchEvents()
}

function onDrop(day: number, e: DragEvent) {
    const ulid = e.dataTransfer?.getData('text/plain')
    if (!ulid) return
    const newDate = dayDate(day) + 'T12:00:00Z'
    axios.patch(route('addon.social.user.posts.reschedule', ulid), { scheduled_at: newDate })
        .then(() => {
            const ev = events.value.find((x: any) => x.ulid === ulid)
            if (ev) ev.scheduled_at = newDate
        })
}

onMounted(() => fetchEvents())
</script>

<template>
    <Head :title="t('Calendar')" />

    <div class="p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('Content Calendar') }}</h1>
            <Link :href="route('addon.social.user.posts.create')" class="btn btn-sm btn-emerald">
                + {{ t('New Post') }}
            </Link>
        </div>

        <!-- Platform Filters -->
        <div class="flex flex-wrap gap-2">
            <button v-for="acct in accounts" :key="acct.platform"
                    @click="togglePlatform(acct.platform)"
                    class="btn btn-xs" :class="activePlatforms.includes(acct.platform) ? 'btn-emerald' : 'btn-ghost'">
                {{ platformIcon(acct.platform) }} {{ acct.platform_label }}
            </button>
        </div>

        <!-- Calendar Controls -->
        <div class="flex items-center gap-4">
            <button @click="prevMonth" class="btn btn-ghost btn-sm">&larr;</button>
            <h2 class="font-medium">{{ monthLabel }}</h2>
            <button @click="nextMonth" class="btn btn-ghost btn-sm">&rarr;</button>
        </div>

        <div v-if="loading" class="text-center text-gray-400 py-8">{{ t('Loading...') }}</div>

        <!-- Calendar Grid -->
        <div v-else class="grid grid-cols-7 gap-px bg-gray-200 rounded overflow-hidden">
            <div v-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"
                 class="bg-gray-100 px-2 py-1 text-xs font-medium text-center">{{ d }}</div>

            <div v-for="offset in firstDayOffset" :key="'e'+offset" class="bg-white p-1"></div>

            <div v-for="day in daysInMonth" :key="day"
                 class="bg-white min-h-[80px] p-1"
                 @dragover.prevent
                 @drop="onDrop(day, $event)">
                <span class="text-xs text-gray-400">{{ day }}</span>
                <div class="space-y-0.5 mt-0.5">
                    <div v-for="ev in eventsForDay(day)" :key="ev.ulid"
                         class="text-[10px] leading-tight px-1 py-0.5 rounded truncate cursor-move"
                         :class="statusColor(ev.status)"
                         :title="ev.title"
                         draggable="true"
                         @dragstart="(e: DragEvent) => e.dataTransfer?.setData('text/plain', ev.ulid)">
                        {{ ev.title }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
