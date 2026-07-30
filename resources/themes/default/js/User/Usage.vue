<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import UserDashboardLayout from '@themes/default/js/Layouts/UserDashboardLayout.vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import { useToastr } from '@/Composables/useToastr'
import Pagination from '@/Components/UI/Pagination.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const { t } = useTranslate()
const { formatNumber } = useNumberFormat()
const { formatRelative } = useDateFormat()
const { isDark } = useTheme()
const toast = useToastr()
const isPro = computed(() => !!page.props.isProAvailable)

interface Stats {
  plan_credits_used_month: number
  // Optional so a page rendered before these existed cannot produce "NaN" — every read
  // of them is coerced with `?? 0`.
  topup_credits_used?: number
  topup_credits_total?: number
  credits_remaining: number
  credits_used_today: number
  credits_used_month: number
  plan_credit_limit: number
  topup_credits: number
  plan_credits_remaining: number
  total_generations: number
  total_tokens: number
  total_credits_used: number
  daily_usage: Array<{ date: string; credits: number }>
  top_tools: Array<{ tool_slug: string; tool_name: string; count: number }>
  peak_hour: number | null
  most_active_day: string | null
  avg_tokens_per_gen: number
  recent_history: Array<{ ulid: string; tool_slug: string | null; tool_name: string; output_preview: string; created_at: string }>
}

interface WalletTransaction {
  id: number
  amount: number
  balance_after: number
  type: string
  description: string | null
  created_at: string | null
}

const stats = page.props.stats as Stats
const transactions = computed(() => page.props.transactions as {
  data: WalletTransaction[]
  links: Array<{ url: string | null; label: string; active: boolean }>
  current_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
})
const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: InstanceType<typeof import('chart.js').Chart> | null = null
const parseDateValue = (value: string) => (value.length === 10 ? new Date(`${value}T00:00:00`) : new Date(value))
const formatShortDate = (value: string) => new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
}).format(parseDateValue(value))

const selectedPeriod = ref('1M')
const loadingChart = ref(false)
const exporting = ref(false)
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
  {
    key: 'credits_remaining',
    label: t('Credits left'),
    value: formatNumber(stats.credits_remaining),
    icon: 'ti ti-coins',
    iconClass: 'bg-sky-500/12 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    cardClass: 'border-sky-100/80 bg-white/95 dark:border-sky-900/60 dark:!bg-surface-900'
  },
  {
    key: 'credits_used_today',
    label: t('Used today'),
    value: formatNumber(stats.credits_used_today),
    icon: 'ti ti-flame',
    iconClass: 'bg-amber-500/12 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    cardClass: 'border-amber-100/80 bg-white/95 dark:border-amber-900/60 dark:!bg-surface-900'
  },
  {
    key: 'credits_used_month',
    label: t('Used this month'),
    value: formatNumber(stats.credits_used_month),
    icon: 'ti ti-calendar-stats',
    iconClass: 'bg-emerald-500/12 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    cardClass: 'border-emerald-100/80 bg-white/95 dark:border-emerald-900/60 dark:!bg-surface-900'
  },
  {
    key: 'total_generations',
    label: t('Total generations'),
    value: formatNumber(stats.total_generations),
    icon: 'ti ti-sparkles',
    iconClass: 'bg-violet-500/12 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    cardClass: 'border-violet-100/80 bg-white/95 dark:border-violet-900/60 dark:!bg-surface-900'
  },
])

/**
 * The plan bar measures the PLAN's spend against the PLAN's allowance.
 *
 * It used to divide total monthly consumption by the plan limit, but consumption includes
 * credits spent out of a top-up — so a user who bought extra credits and spent them saw
 * their plan usage read past its own ceiling ("3,226 / 2,000") and their purchased spend
 * silently counted against the allowance. The server now splits the month's spend between
 * the two wallets; each bar shows only its own.
 */
const creditPercent = computed(() => {
  if (!stats.plan_credit_limit) return 0
  return Math.min(100, Math.round((stats.plan_credits_used_month / stats.plan_credit_limit) * 100))
})

const creditBarColor = computed(() => {
  if (creditPercent.value >= 90) return '#ef4444'
  if (creditPercent.value >= 60) return '#f59e0b'
  return '#10b981'
})

/**
 * Purchased credits, shown apart from the plan allowance.
 *
 * The two behave differently and the difference only shows up at renewal: the allowance
 * is topped back up to the plan figure every period, while a top-up is the user's own
 * money and survives untouched. A single number cannot say which part of a balance is
 * about to reset, so the card shows both.
 *
 * The remaining figure comes from `users.topup_credits`, the column kept for exactly this
 * balance. A ratio is only shown once that column has actually gone down — the plan
 * allowance is spent first, so a month that merely exceeded its limit has not necessarily
 * touched the top-up at all (demo mode inflates usage while deducting nothing). Until it
 * has, the card shows the plain balance.
 *
 * Every read is coerced with `?? 0`: an older cached page without these props rendered
 * `formatNumber(undefined)` as "NaN" right next to a real figure.
 */
const topupUsed = computed(() => Number(stats.topup_credits_used ?? 0))
const topupTotal = computed(() => Number(stats.topup_credits_total ?? 0))
const topupBalance = computed(() => Number(stats.topup_credits ?? 0))

const hasTopupCredits = computed(() => topupBalance.value > 0 || topupTotal.value > 0)

/** Only once credits have genuinely come out of the top-up does a ratio mean anything. */
const showTopupUsage = computed(() => topupUsed.value > 0 && topupTotal.value > 0)

const topupPercent = computed(() => {
  if (!showTopupUsage.value) return 0

  return Math.min(100, Math.round((topupUsed.value / topupTotal.value) * 100))
})

const hasChartData = computed(() => {
  return chartData.value.length > 0 && chartData.value.some(d => d.value > 0)
})

async function loadChart() {
  await nextTick()
  if (!chartCanvas.value) return
  const dataPoints = chartData.value
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
  if (!hasChartData.value) return

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
  if (exporting.value) return
  exporting.value = true

  try {
    const res = await fetch(route('user.dashboard.usage.export'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    })

    // Without this the error body (a JSON message, or an HTML page) was downloaded as
    // "my-ai-usage.xlsx" — a corrupt spreadsheet instead of a visible failure.
    if (!res.ok) {
      const message = await res.json().then(body => body?.message).catch(() => null)

      toast.error(message || t('The export could not be generated. Please try again.'))

      return
    }

    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'my-ai-usage.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.error(t('The export could not be generated. Please try again.'))
  } finally {
    exporting.value = false
  }
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

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('My Usage') }}</h1>
        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ t('Track credits used, generation patterns, and recent activity.') }}</p>
      </div>
      <button
        @click="exportCsv"
        :disabled="exporting"
        class="shrink-0 btn-primary disabled:cursor-not-allowed disabled:opacity-60"
      >
        <i :class="exporting ? 'ti ti-loader animate-spin' : 'ti ti-download'" class="mr-1"></i>
        {{ exporting ? t('Preparing...') : t('Export') }}
      </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article
        v-for="card in summaryCards"
        :key="card.key"
        class="group rounded-2xl border p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] transition hover:-translate-y-0.5 hover:shadow-lg"
        :class="card.cardClass"
      >
        <div class="flex flex-col items-start justify-between gap-4 w-full">
          <div :class="card.iconClass" class="shrink-0 flex h-10 w-10 items-center justify-center rounded-2xl">
            <i :class="['text-2xl', card.icon]"></i>
          </div>
          <div class="min-w-0 w-full">
            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 truncate">{{ card.label }}</p>
            <p class="mt-3 font-heading text-xl font-bold text-gray-950 dark:!text-white tracking-tight sm:text-xl xl:text-2xl" :title="card.value">{{ card.value }}</p>
          </div>
        </div>
      </article>
    </div>

    <!--
      Also renders for someone with top-ups but no plan allowance. Free-tier users are the
      likeliest top-up buyers, and gating the whole card on plan_credit_limit would hide
      their purchased balance from the one page that exists to explain it.
    -->
    <div v-if="isPro && (stats.plan_credit_limit > 0 || hasTopupCredits)" class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <template v-if="stats.plan_credit_limit > 0">
        <div class="mb-3 flex items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Monthly credit usage') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Your current plan usage for this month.') }}</p>
          </div>
          <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/15 dark:text-primary-500">{{ formatNumber(stats.plan_credits_used_month) }} / {{ formatNumber(stats.plan_credit_limit) }} <span class="font-normal">{{ t('credits') }}</span></span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
          <div
            class="h-full rounded-full transition-all duration-500"
            :style="{ width: creditPercent + '%', backgroundColor: creditBarColor }"
          />
        </div>
      </template>

      <!--
        Purchased credits, kept visually separate from the allowance above. They are the
        only part of the wallet that does NOT reset at renewal, and a single combined
        figure gives a user no way to tell how much of their balance is about to be
        refreshed and how much is theirs to keep.
      -->
      <div v-if="hasTopupCredits" :class="stats.plan_credit_limit > 0 ? 'mt-4 border-t border-gray-100 pt-4 dark:border-surface-800' : ''">
        <div class="mb-2 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <i class="ti ti-wallet text-base text-sky-600 dark:text-sky-400"></i>
            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ t('Top-up credits') }}</span>
          </div>
          <!-- A ratio only once the top-up has actually been spent from; otherwise the
               balance on its own, because nothing has come out of it to be a fraction. -->
          <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-400">
            <template v-if="showTopupUsage">{{ formatNumber(topupUsed) }} / {{ formatNumber(topupTotal) }}</template>
            <template v-else>{{ formatNumber(topupBalance) }}</template>
            <span class="font-normal"> {{ t('credits') }}</span>
          </span>
        </div>

        <!-- No bar while nothing has been drawn: an empty track reads as "all spent" and a
             full one as "none left", and neither is true. -->
        <div v-if="showTopupUsage" class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
          <div
            class="h-full rounded-full bg-sky-500 transition-all duration-500"
            :style="{ width: topupPercent + '%' }"
          />
        </div>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          {{ t('Credits you purchased. These do not expire and are not reset when your plan renews — your plan allowance is used first.') }}
        </p>
      </div>
    </div>

    <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-5">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Credit usage chart') }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            <span v-if="selectedPeriod === '1D'">{{ t('Hourly usage over the last 24 hours.') }}</span>
            <span v-else-if="selectedPeriod === '7D'">{{ t('Daily usage over the last 7 days.') }}</span>
            <span v-else-if="selectedPeriod === '1Y'">{{ t('Monthly usage over the last 12 months.') }}</span>
            <span v-else>{{ t('Daily usage over the last 30 days.') }}</span>
          </p>
        </div>
        <div class="flex rounded-full border border-gray-200 bg-gray-50 p-1 text-xs dark:border-gray-700 dark:bg-gray-800 sm:w-auto shrink-0 self-start sm:self-center">
          <button
            v-for="p in ['1D', '7D', '1M', '1Y']"
            :key="p"
            type="button"
            class="rounded-full px-3 py-1.5 transition-colors"
            :class="selectedPeriod === p
              ? 'bg-white dark:bg-gray-700 shadow-sm font-semibold text-gray-900 dark:text-white'
              : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
            @click="changePeriod(p)"
          >
            {{ p }}
          </button>
        </div>
      </div>
      <div class="relative h-64">
        <div v-if="!hasChartData" class="flex h-full flex-col items-center justify-center border border-dashed border-gray-200 dark:border-surface-800 rounded-2xl bg-gray-50/30 dark:bg-surface-900/30">
          <i class="ti ti-chart-bar-off text-3xl text-gray-300 dark:text-gray-600 mb-2"></i>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ t('No data found') }}</p>
        </div>
        <canvas v-else ref="chartCanvas"></canvas>
        <!-- Loading overlay — covers the plot only, so the heading and the period switch
             stay legible while the new range loads. -->
        <div v-if="loadingChart" class="absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-[2px] transition-all dark:bg-gray-900/70">
          <div class="flex flex-col items-center gap-2">
            <i class="ti ti-loader animate-spin text-2xl text-primary-500"></i>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Updating...') }}</span>
          </div>
        </div>
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
            <span class="text-gray-500 dark:text-gray-400">{{ t('Total credits usage') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(stats.total_credits_used) }}</span>
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
            <span class="text-gray-500 dark:text-gray-400">{{ t('Total tokens') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatNumber(stats.total_tokens) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">{{ t('Most active day') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.most_active_day || '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="mb-3 flex items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Recent generations') }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Your latest credit purchases, usage, and output activity.') }}</p>
        </div>
        <!-- Only the newest 10 land here; the History page is the full, searchable list. -->
        <Link
          v-if="stats.recent_history.length"
          :href="route('user.dashboard.history.index')"
          class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary-700 transition hover:text-primary-800 dark:!text-primary-500 dark:hover:text-primary-400"
        >
          {{ t('View more') }}
          <i class="ti ti-arrow-right text-base"></i>
        </Link>
      </div>
      <div v-if="stats.recent_history.length === 0" class="py-6 text-center text-sm text-gray-400">{{ t('No generations yet') }}</div>
      <div v-else class="space-y-2">
        <div v-for="item in stats.recent_history" :key="item.ulid" class="flex items-start justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50/60 px-4 py-3 transition hover:border-primary-200 hover:bg-primary-50/40 dark:border-surface-800 dark:bg-gray-950/40 dark:hover:border-primary-800/40 dark:hover:bg-gray-900/60">
          <div class="min-w-0">
            <Link v-if="item.tool_slug" :href="route('ai.tools.show', { slug: item.tool_slug })" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">{{ item.tool_name }}</Link>
            <span v-else class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ item.tool_name || t('Direct') }}</span>
            <p class="mt-0.5 text-xs text-gray-500 line-clamp-1 dark:text-gray-400">{{ item.output_preview }}</p>
          </div>
          <span class="shrink-0 text-[10px] text-gray-400 dark:text-gray-500">{{ item.created_at ? formatShortDate(item.created_at) : '' }}</span>
        </div>
      </div>
    </div>

    <!-- Wallet activity — the credit ledger the dashboard's "Wallet activity" panel links to.
         Separate from the charts above: those count GENERATIONS, this is money in and out. -->
    <div id="wallet-activity" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] dark:border-surface-800 dark:bg-gray-900">
      <div class="p-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ t('Wallet activity') }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('Every credit purchase, bonus, and deduction on your account.') }}</p>
      </div>

      <div v-if="transactions.data.length === 0" class="px-6 pb-6 text-center text-sm text-gray-400">
        {{ t('No wallet activity yet.') }}
      </div>

      <div v-else class="min-w-0 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50/80 text-xs uppercase tracking-wide text-gray-700 dark:bg-surface-800/60 dark:text-gray-400">
            <tr>
              <th class="px-4 py-3 font-semibold">{{ t('Description') }}</th>
              <th class="px-4 py-3 text-center font-semibold">{{ t('Amount') }}</th>
              <th class="px-4 py-3 text-center font-semibold">{{ t('Balance') }}</th>
              <th class="whitespace-nowrap px-4 py-3 text-center font-semibold">{{ t('Date') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-surface-800">
            <tr v-for="tx in transactions.data" :key="tx.id" class="transition hover:bg-gray-50/60 dark:hover:bg-surface-800/40">
              <td class="px-4 py-3">
                <p class="font-medium text-gray-900 dark:text-white">{{ tx.description || t('Credit adjustment') }}</p>
                <p class="mt-0.5 text-xs capitalize text-gray-500 dark:text-gray-400">{{ tx.type.replace('_', ' ') }}</p>
              </td>
              <!-- Signed and coloured: a ledger where a spend and a top-up look identical is
                   unreadable at a glance. -->
              <td class="px-4 py-3 text-center">
                <span
                  class="block font-bold"
                  :class="tx.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'"
                >
                  {{ tx.amount < 0 ? '−' : '+' }}{{ formatNumber(Math.abs(tx.amount)) }}
                </span>
                <!-- Named as well as signed: the sign alone relies on colour, which a
                     colour-blind reader cannot use and a printed page loses entirely. -->
                <span
                  class="text-[10px] font-medium tracking-wide"
                  :class="tx.amount < 0
                    ? 'text-red-600 dark:text-red-400'
                    : 'text-emerald-600 dark:text-emerald-400'"
                >
                  {{ tx.amount < 0 ? t('debit') : t('credit') }}
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <p class="font-medium text-gray-700 dark:text-gray-200">{{ formatNumber(tx.balance_after) }}</p>
                <!-- Derived, not stored: balance_after minus the movement IS the balance the
                     row started from, so it can never disagree with the ledger. -->
                <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                  {{ t('prev: :balance', { balance: formatNumber(tx.balance_after - tx.amount) }) }}
                </p>
              </td>
              <td class="whitespace-nowrap px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                <!-- Date first, relative underneath: the exact date is the record, "3 days
                     ago" is the quick read. Same stacked shape as the balance column. -->
                <template v-if="tx.created_at">
                  <p class="text-gray-600 dark:text-gray-300">{{ formatShortDate(tx.created_at) }}</p>
                  <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">{{ formatRelative(tx.created_at) }}</p>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination
        v-if="transactions.last_page > 1"
        :links="transactions.links"
        :from="transactions.from"
        :to="transactions.to"
        :total="transactions.total"
        :current-page="transactions.current_page"
        :last-page="transactions.last_page"
        class="p-5"
      />
    </div>
  </div>
</template>
