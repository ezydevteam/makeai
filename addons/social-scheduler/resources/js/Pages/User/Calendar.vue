<script setup lang="ts">
import axios from 'axios'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'

defineOptions({ layout: UserDashboardLayout })

const { t } = useTranslate()

type Account = {
    id: number
    platform: string
    platform_label: string
}

type CalendarEvent = {
    id: number
    ulid: string
    title: string
    platforms: string[]
    status: string
    scheduled_at: string | null
}

const props = defineProps<{
    initial_month: string
    accounts: Account[]
}>()

const currentDate = ref(new Date(`${props.initial_month}-01`))
const events = ref<CalendarEvent[]>([])
const loading = ref(false)
const activePlatforms = ref<string[]>([])
const currentMonthKey = ref(props.initial_month)

const platformOptions = computed(() =>
    props.accounts.map((account) => ({
        value: account.platform,
        label: account.platform_label,
    })),
)

const monthOptions = computed(() => {
    const base = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
    const options = []

    for (let offset = -12; offset <= 12; offset += 1) {
        const date = new Date(base.getFullYear(), base.getMonth() + offset, 1)
        const year = date.getFullYear()
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const key = `${year}-${month}`
        options.push({
            value: key,
            label: date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }),
        })
    }

    return options
})

const weekdayLabels = computed(() => {
    const referenceSunday = new Date(2024, 0, 7)
    return Array.from({ length: 7 }, (_, index) =>
        new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(new Date(referenceSunday.getFullYear(), referenceSunday.getMonth(), referenceSunday.getDate() + index)),
    )
})

const daysInMonth = computed(() =>
    new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0).getDate(),
)

const calendarDays = computed(() => Array.from({ length: daysInMonth.value }, (_, index) => index + 1))

const startOfMonth = computed(() => new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1))
const firstDayOffset = computed(() => startOfMonth.value.getDay())

function dayDate(day: number) {
    const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day)
    return date.toISOString().split('T')[0]
}

function eventsForDay(day: number) {
    const dateStr = dayDate(day)
    return events.value.filter((event) => event.scheduled_at?.startsWith(dateStr))
}

function platformIcon(platform: string) {
    return platform === 'instagram'
        ? 'ti ti-brand-instagram'
        : platform === 'facebook'
            ? 'ti ti-brand-facebook'
            : platform === 'twitter'
                ? 'ti ti-brand-x'
                : platform === 'linkedin'
                    ? 'ti ti-brand-linkedin'
                    : 'ti ti-device-mobile'
}

function statusClass(status: string) {
    if (status === 'published') return 'bg-emerald-500'
    if (status === 'scheduled') return 'bg-blue-500'
    if (status === 'draft') return 'bg-gray-400'
    if (status === 'partial') return 'bg-amber-500'
    return 'bg-red-500'
}

function fetchEvents() {
    loading.value = true

    const start = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1)
    const end = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0)
    const params: Record<string, string[]> | { start: string; end: string; platforms?: string[] } = {
        start: start.toISOString().split('T')[0],
        end: end.toISOString().split('T')[0],
    }

    if (activePlatforms.value.length > 0) {
        params.platforms = activePlatforms.value
    }

    axios.get(route('addon.social.user.calendar.events'), { params })
        .then((response) => {
            events.value = response.data
        })
        .finally(() => {
            loading.value = false
        })
}

function prevMonth() {
    const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    currentMonthKey.value = `${year}-${month}`
}

function nextMonth() {
    const date = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    currentMonthKey.value = `${year}-${month}`
}

function onDrop(day: number, event: DragEvent) {
    const ulid = event.dataTransfer?.getData('text/plain')
    if (!ulid) return

    const newDate = `${dayDate(day)}T12:00:00Z`
    axios.patch(route('addon.social.user.posts.reschedule', ulid), { scheduled_at: newDate })
        .then(() => {
            const scheduled = events.value.find((item) => item.ulid === ulid)
            if (scheduled) {
                scheduled.scheduled_at = newDate
            }
        })
}

watch([currentDate, activePlatforms], () => {
    fetchEvents()
}, { deep: true })

watch(currentMonthKey, (monthKey) => {
    currentDate.value = new Date(`${monthKey}-01`)
})

onMounted(() => {
    fetchEvents()
})
</script>

<template>
    <Head :title="t('Calendar')" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Content Calendar') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Keep your scheduled posts visible in one simple monthly view.') }}</p>
            </div>

            <Link
                :href="route('addon.social.user.posts.create')"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-500 px-5 py-2.5 text-sm font-semibold !text-white shadow-sm transition hover:bg-primary-600 sm:w-auto"
            >
                <i class="ti ti-plus text-sm"></i>
                {{ t('New Post') }}
            </Link>
        </div>

        <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] ring-1 ring-black/5 dark:border-gray-700/70 dark:bg-gray-900 dark:ring-white/5 sm:p-6 space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0 flex-1 sm:max-w-md">
                    <label class="shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Platforms') }}</label>
                    <div class="flex-1 min-w-0">
                        <AppSelect
                            v-model="activePlatforms"
                            :options="platformOptions"
                            :placeholder="t('All platforms')"
                            :search-placeholder="t('Search platforms...')"
                            :label="''"
                            multiple
                            compact-multiple
                            live-search
                            :size="6"
                        />
                    </div>
                </div>

                <div class="shrink-0">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ t('Month') }}</label>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                            @click="prevMonth"
                            :aria-label="t('Previous month')"
                        >
                            <i class="ti ti-chevron-left text-sm"></i>
                        </button>

                        <div class="w-40">
                            <AppSelect
                                v-model="currentMonthKey"
                                :options="monthOptions"
                                :label="''"
                            />
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-gray-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                            @click="nextMonth"
                            :aria-label="t('Next month')"
                        >
                            <i class="ti ti-chevron-right text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="rounded-2xl border border-dashed border-gray-200 px-6 py-16 text-center text-sm text-gray-500 dark:border-surface-700 dark:text-gray-400">
                {{ t('Loading...') }}
            </div>

            <div v-else class="overflow-x-auto">
                <div class="min-w-[52rem] overflow-hidden rounded-2xl border border-gray-200 dark:border-surface-800">
                    <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 px-2 mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-surface-800 dark:bg-surface-800 dark:text-gray-300">
                        <div v-for="day in weekdayLabels" :key="day" class="px-3 py-3 text-center">
                            {{ day }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 bg-white px-2 dark:bg-surface-800">
                        <div v-for="offset in firstDayOffset" :key="`empty-${offset}`" class="min-h-[128px] bg-white dark:bg-surface-900"></div>

                        <div
                            v-for="day in calendarDays"
                            :key="day"
                            class="min-h-[128px] bg-white p-3 transition hover:bg-gray-50 dark:bg-surface-900 dark:hover:bg-surface-800/60"
                            @dragover.prevent
                            @drop="onDrop(day, $event)"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <span class="text-sm font-semibold leading-none text-gray-700 dark:text-gray-200">{{ day }}</span>
                                <span v-if="eventsForDay(day).length" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-surface-800 dark:text-gray-400">
                                    {{ eventsForDay(day).length }}
                                </span>
                            </div>

                            <div class="mt-3 space-y-1">
                                <div
                                    v-for="eventItem in eventsForDay(day)"
                                    :key="eventItem.ulid"
                                    :class="statusClass(eventItem.status)"
                                    class="group flex cursor-move items-center gap-2 rounded-xl px-2.5 py-1.5 text-[11px] font-medium text-white shadow-sm"
                                    :title="eventItem.title"
                                    draggable="true"
                                    @dragstart="(dragEvent: DragEvent) => dragEvent.dataTransfer?.setData('text/plain', eventItem.ulid)"
                                >
                                    <i v-if="eventItem.platforms?.length" :class="platformIcon(eventItem.platforms[0])" class="shrink-0 text-xs opacity-90"></i>
                                    <span class="min-w-0 truncate">{{ eventItem.title }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
