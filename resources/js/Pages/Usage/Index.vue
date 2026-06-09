<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
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
  const daily = Array.isArray(stats.daily_usage) ? stats.daily_usage : []
  if (daily.length === 0) return

  const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler } = await import('chart.js')
  Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler)

  const fmtFull = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' })

  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels: daily.map((d) => fmtFull.format(new Date(d.date + 'T00:00:00'))),
      datasets: [{
        label: 'Credits',
        data: daily.map((d) => d.credits),
        backgroundColor: '#10b981',
        borderRadius: 4,
        maxBarThickness: 24,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } },
        y: { beginAtZero: true, grid: { color: '#e5e7eb' } },
      },
    },
  })
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

onBeforeUnmount(() => {
  chartInstance?.destroy()
})
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Usage</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Your AI generation activity</p>
      </div>
      <button
        @click="exportCsv"
        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
      >
        Export Excel
      </button>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Credits Left</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.credits_remaining.toLocaleString() }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Used Today</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.credits_used_today.toLocaleString() }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Used This Month</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.credits_used_month.toLocaleString() }}</p>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Gens</p>
        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_generations.toLocaleString() }}</p>
      </div>
    </div>

    <div v-if="isPro && stats.plan_credit_limit > 0" class="mb-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <div class="mb-1 flex justify-between text-sm">
        <span class="text-gray-500 dark:text-gray-400">Monthly Credit Usage</span>
        <span class="font-semibold text-gray-900 dark:text-white">{{ stats.credits_used_month.toLocaleString() }} / {{ stats.plan_credit_limit.toLocaleString() }}</span>
      </div>
      <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div
          class="h-full rounded-full transition-all duration-500"
          :style="{ width: creditPercent + '%', backgroundColor: creditBarColor }"
        />
      </div>
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Daily Usage (Last 30 Days)</h3>
      <div class="h-64">
        <canvas ref="chartCanvas"></canvas>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Top Tools</h3>
        <div v-if="stats.top_tools.length === 0" class="py-4 text-center text-sm text-gray-400">No data yet</div>
        <div v-else class="space-y-2">
          <div v-for="(tool, idx) in stats.top_tools" :key="tool.tool_slug || idx" class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ idx + 1 }}</span>
              <Link v-if="tool.tool_slug" :href="route('ai.tools.show', { slug: tool.tool_slug })" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">{{ tool.tool_name }}</Link>
              <span v-else class="text-sm font-medium text-gray-500 dark:text-gray-400">Direct</span>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ tool.count }} runs</span>
          </div>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Insights</h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Most used model</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.most_used_model || '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Peak hour</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.peak_hour !== null ? formatHour(stats.peak_hour) + ' – ' + formatHour(stats.peak_hour + 1) : '—' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Avg tokens / gen</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.avg_tokens_per_gen.toLocaleString() }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500 dark:text-gray-400">Most active day</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ stats.most_active_day || '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
      <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Generations</h3>
      <div v-if="stats.recent_history.length === 0" class="py-4 text-center text-sm text-gray-400">No generations yet</div>
      <div v-else class="space-y-2">
        <div v-for="item in stats.recent_history" :key="item.ulid" class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2 last:border-0 last:pb-0 dark:border-gray-800">
          <div class="min-w-0">
            <Link v-if="item.tool_slug" :href="route('ai.tools.show', { slug: item.tool_slug })" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">{{ item.tool_name }}</Link>
            <span v-else class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ item.tool_name || 'Direct' }}</span>
            <p class="mt-0.5 text-xs text-gray-500 line-clamp-1 dark:text-gray-400">{{ item.output_preview }}</p>
          </div>
          <span class="shrink-0 text-[10px] text-gray-400">{{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
