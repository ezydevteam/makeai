<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()
const { formatNumber } = useNumberFormat()
const { isDark } = useTheme()
const isPro = computed(() => !!page.props.isProAvailable)

interface Stats {
  credits_remaining: number
  credits_used_today: number
  credits_used_month: number
  plan_credit_limit: number
  total_generations: number
  total_tokens: number
  daily_usage: Array<{ date: string; credits: number }>
  top_tools: Array<{ tool_slug: string; tool_name: string; count: number }>
  most_used_model: string | null
  peak_hour: number | null
  most_active_day: string | null
  avg_tokens_per_gen: number
  recent_history: Array<{ ulid: string; tool_slug: string | null; tool_name: string; output_preview: string; created_at: string }>
}

const stats = page.props.stats as Stats
const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: InstanceType<typeof import('chart.js').Chart> | null = null
const parseDateValue = (value: string) => (value.length === 10 ? new Date(`${value}T00:00:00`) : new Date(value))
const formatShortDate = (value: string) => new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
}).format(parseDateValue(value))

const selectedPeriod = ref('1M')
const loadingChart = ref(false)
const chartData = ref<Array<{ label: string; value: number; is_current?: boolean }>>([])

if (Array.isArray(stats.daily_usage)) {
  chartData.value = stats.daily_usage.map(d => {
    const itemDate = new Date(`${d.date}T00:00:00`)
    const today = new Date()
    const isToday = itemDate.getDate() === today.getDate() &&
                    itemDate.getMonth() === today.getMonth() &&
                    itemDate.getFullYear() === today.getFullYear()
    return {
      label: formatShortDate(d.date),
      value: d.credits,
      is_current: isToday
    }
  })
}

const summaryCards = computed(() => [
  { key: 'credits_remaining', label: t('Credits left'), value: stats.credits_remaining, icon: 'ti ti-coins', tone: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' },
  { key: 'credits_used_today', label: t('Used today'), value: stats.credits_used_today, icon: 'ti ti-flame', tone: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' },
  { key: 'credits_used_month', label: t('Used this month'), value: stats.credits_used_month, icon: 'ti ti-calendar-stats', tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
  { key: 'total_generations', label: t('Total generations'), value: stats.total_generations, icon: 'ti ti-sparkles', tone: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' },
])

const creditPercent = computed(() => {
  if (!stats.plan_credit_limit) return 0
  return Math.min(100, Math.round((stats.credits_used_month / stats.plan_credit_limit) * 100))
})

const creditBarColor = computed(() => {
  if (creditPercent.value >= 90) return '#ef4444'
  if (creditPercent.value >= 60) return '#f59e0b'
  return '#10b981'
})

async function loadChart() {
  if (!chartCanvas.value) return
  const dataPoints = chartData.value
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
  if (dataPoints.length === 0) return

  const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler } = await import('chart.js')
  Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler)

  const gridColor = isDark.value ? 'rgba(148, 163, 184, 0.18)' : 'rgba(148, 163, 184, 0.22)'
  const tickColor = isDark.value ? '#9ca3af' : '#64748b'
  const axisBorder = isDark.value ? 'rgba(148, 163, 184, 0.18)' : 'rgba(203, 213, 225, 0.8)'

  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels: dataPoints.map((d) => d.label),
      datasets: [{
        label: t('Credits'),
        data: dataPoints.map((d) => d.value),
        backgroundColor: dataPoints.map((d) => d.is_current ? '#1f75fe' : '#93c5fd'),
        borderRadius: 20,
        borderSkipped: 'bottom',
        maxBarThickness: 24,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: {
          grid: { display: false },
          ticks: { maxTicksLimit: 12, color: tickColor },
          border: { color: axisBorder },
        },
        y: {
          beginAtZero: true,
          grid: { color: gridColor },
          ticks: { color: tickColor },
          border: { color: axisBorder },
        },
      },
    },
  })
}

async function changePeriod(period: string) {
  if (loadingChart.value) return
  selectedPeriod.value = period
  loadingChart.value = true
  try {
    const response = await axios.get(route('user.dashboard.usage.chart'), {
      params: { period }
    })
    chartData.value = response.data
    await loadChart()
  } catch (e) {
    console.error(e)
  } finally {
    loadingChart.value = false
  }
}

function formatHour(h: number): string {
  const ampm = h >= 12 ? 'PM' : 'AM'
  const hour = h % 12 || 12
  return `${hour}${ampm}`
}

async function exportCsv() {
  try {
    const res = await fetch(route('user.dashboard.usage.export'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    })
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'my-ai-usage.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
}

onMounted(() => {
  loadChart()
})

watch(isDark, async () => {
  await nextTick()
  await loadChart()
})

onBeforeUnmount(() => {
  chartInstance?.destroy()
})
</script>

<template>
  <div class="space-y-6">
    <Head :title="t('My Usage')" />

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('My Usage') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Track credits used, generation patterns, and recent activity.') }}</p>
      </div>
      <button
        @click="exportCsv"
        class="inline-flex items-center justify-center rounded-full bg-primary-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-600"
      >
        <i class="ti ti-download mr-2"></i>
        {{ t('Export xlsx') }}
      </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div
        v-for="card in summaryCards"
        :key="card.key"
        class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900"
      >
        <div class="absolute -right-8 -top-8 h-20 w-20 rounded-full bg-sky-100/50 blur-2xl dark:bg-sky-500/10"></div>
        <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl shadow-sm" :class="card.tone">
          <i :class="['text-lg', card.icon]"></i>
        </div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ card.label }}</p>
        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ formatNumber(Number(card.value)) }}</p>
      </div>
    </div>

    <div v-if="isPro && stats.plan_credit_limit > 0" class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="mb-3 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Monthly credit usage') }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Your current plan usage for this month.') }}</p>
        </div>
        <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-500">{{ formatNumber(stats.credits_used_month) }} / {{ formatNumber(stats.plan_credit_limit) }}</span>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div
          class="h-full rounded-full transition-all duration-500"
          :style="{ width: creditPercent + '%', backgroundColor: creditBarColor }"
        />
      </div>
    </div>

    <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div v-if="loadingChart" class="absolute inset-0 z-10 flex items-center justify-center bg-white/50 backdrop-blur-[1px] dark:bg-gray-900/50">
        <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2 shadow-lg dark:bg-surface-800">
          <svg class="h-4 w-4 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
          </svg>
          <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ t('Updating...') }}</span>
        </div>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-5">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Credit usage chart') }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            <span v-if="selectedPeriod === '1D'">{{ t('Hourly usage over the last 24 hours.') }}</span>
            <span v-else-if="selectedPeriod === '7D'">{{ t('Daily usage over the last 7 days.') }}</span>
            <span v-else-if="selectedPeriod === '1Y'">{{ t('Monthly usage over the last 12 months.') }}</span>
            <span v-else>{{ t('Daily usage over the last 30 days.') }}</span>
          </p>
        </div>
        <div class="flex items-center gap-1 rounded-xl bg-gray-100 p-1 dark:bg-surface-800 self-start sm:self-center shrink-0">
          <button
            v-for="p in ['1D', '7D', '1M', '1Y']"
            :key="p"
            type="button"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200"
            :class="selectedPeriod === p 
              ? 'bg-white text-gray-900 shadow-sm dark:bg-surface-700 dark:text-white' 
              : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            @click="changePeriod(p)"
          >
            {{ p }}
          </button>
        </div>
      </div>
      <div class="h-64">
        <canvas ref="chartCanvas"></canvas>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Top tools') }}</h2>
        <div v-if="stats.top_tools.length === 0" class="py-6 text-center text-sm text-gray-400">{{ t('No data yet') }}</div>
        <div v-else class="space-y-2">
          <div v-for="(tool, idx) in stats.top_tools" :key="tool.tool_slug || idx" class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-300/20 text-[10px] font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ idx + 1 }}</span>
              <Link v-if="tool.tool_slug" :href="route('ai.tools.show', { slug: tool.tool_slug })" class="text-sm font-medium text-gray-700 transition hover:text-primary-500 hover:underline  dark:text-gray-400 dark:hover:text-primary-300">{{ tool.tool_name }}</Link>
              <span v-else class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('Direct') }}</span>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ formatNumber(tool.count) }} {{ t('runs') }}</span>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">{{ t('Insights') }}</h2>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ t('Most used model') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.most_used_model || '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ t('Peak hour') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.peak_hour !== null ? formatHour(stats.peak_hour) + ' – ' + formatHour(stats.peak_hour + 1) : '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ t('Avg tokens / gen') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(stats.avg_tokens_per_gen) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ t('Most active day') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.most_active_day || '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Recent generations') }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Your latest credit purchases, usage, and output activity.') }}</p>
        </div>
      </div>
      <div v-if="stats.recent_history.length === 0" class="py-6 text-center text-sm text-gray-400">{{ t('No generations yet') }}</div>
      <div v-else class="space-y-2">
        <div v-for="item in stats.recent_history" :key="item.ulid" class="flex items-start justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50/60 px-4 py-3 transition hover:border-primary-200 hover:bg-primary-50/40 dark:border-surface-800 dark:bg-gray-950/40 dark:hover:border-primary-800/40 dark:hover:bg-gray-900/60">
          <div class="min-w-0">
            <Link v-if="item.tool_slug" :href="route('ai.tools.show', { slug: item.tool_slug })" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ item.tool_name }}</Link>
            <span v-else class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ item.tool_name || t('Direct') }}</span>
            <p class="mt-0.5 text-xs text-gray-500 line-clamp-1 dark:text-gray-400">{{ item.output_preview }}</p>
          </div>
          <span class="shrink-0 text-[10px] text-gray-400 dark:text-gray-500">{{ item.created_at ? formatShortDate(item.created_at) : '' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
