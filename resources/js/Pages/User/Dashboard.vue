<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch, nextTick } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'
import { useTranslate } from '@/Composables/useTranslate'
import { useDateFormat } from '@/Composables/useDateFormat'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import DashboardChecklist from '@/Components/DashboardChecklist.vue'
import ContextualTooltip from '@/Components/ContextualTooltip.vue'

interface ToolCard {
    name: string
    slug: string
    description: string
    icon: string
    color: string
    requires_pro: boolean
    is_new: boolean
}

interface TxItem {
    id: number
    amount: number
    balance_after: number
    type: string
    description: string
    created_at: string
}

interface RecentConv {
    id: number
    ulid: string
    title: string
    model: string
    message_count: number
    last_message_at: string | null
}

interface RecentDoc {
    id: number
    title: string
    tool_slug: string
    word_count: number | null
    created_at: string
}

interface PageProps {
    chartPeriod: string
    stats: {
        credits: number
        credits_used_today: number
        credits_used_month: number
        total_conversations: number
        total_documents: number
    }
    usageChart: { date: string; credits: number }[]
    recentTransactions: TxItem[]
    quickTools: ToolCard[]
    recentConversations: RecentConv[]
    recentDocuments: RecentDoc[]
    plan: {
        name: string
        slug: string
        is_free: boolean
        features: string[] | null
        subscription_status: string
        subscription_ends_at: string | null
        trial_ends_at: string | null
        daily_limit: number | null
        monthly_limit: number | null
    } | null
    referral: {
        code: string | null
        earnings: number
        count: number
        link: string | null
    }
}

const page = usePage()
const { t } = useTranslate()
const { formatDate } = useDateFormat()
const { formatNumber } = useNumberFormat()
const props = page.props as unknown as PageProps

const user = computed(() => page.props.auth?.user as any)

const getProps = () => page.props as unknown as PageProps

const stats = computed(() => getProps().stats)
const usageChart = computed(() => getProps().usageChart)
const chartPeriod = computed(() => getProps().chartPeriod)
const recentTransactions = computed(() => getProps().recentTransactions)
const quickTools = computed(() => getProps().quickTools)
const recentConversations = computed(() => getProps().recentConversations)
const recentDocuments = computed(() => getProps().recentDocuments)
const plan = computed(() => getProps().plan)
const referral = computed(() => getProps().referral)

const txTypeLabel = (type: string) => {
    const map: Record<string, string> = {
        purchase: t('Purchase'),
        usage: t('Usage'),
        refund: t('Refund'),
        bonus: t('Bonus'),
        referral: t('Referral'),
        admin_adjust: t('Adjustment'),
    }
    return map[type] ?? type
}

const txTypeClass = (amount: number) => amount > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'

const copyReferralLink = () => {
    if (referral.value.link) {
        navigator.clipboard.writeText(referral.value.link)
    }
}

const chartTitle = computed(() => {
    if (chartPeriod.value === 'month') return t('Credit usage — this month')
    if (chartPeriod.value === '90d') return t('Credit usage — last 90 days')
    return t('Credit usage — last 7 days')
})

const switchChartPeriod = (period: string) => {
    router.get(route('user.dashboard'), { chart_period: period }, { preserveState: true, replace: true })
}

// Chart
const canvasRef = ref<HTMLCanvasElement | null>(null)
let chartInstance: any = null

const loadChartJs = async () => {
    const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler } = await import('chart.js')
    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler)
    return Chart
}

const drawChart = async () => {
    await nextTick()
    if (!canvasRef.value) return

    const ctx = canvasRef.value.getContext('2d')
    if (!ctx) return

    const labels = usageChart.value.map(d => {
        const dObj = new Date(d.date)
        if (chartPeriod.value === '7d') {
            return dObj.toLocaleDateString('en', { weekday: 'short' })
        }
        return dObj.toLocaleDateString('en', { month: 'short', day: 'numeric' })
    })
    const data = usageChart.value.map(d => d.credits)
    const maxVal = Math.max(...data, 1)

    if (chartInstance) chartInstance.destroy()

    const Chart = await loadChartJs()
    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: (ctx: any) => {
                    const val = data[ctx.dataIndex]
                    return val === maxVal ? '#1F75FE' : '#BFDBFE'
                },
                borderColor: (ctx: any) => {
                    const val = data[ctx.dataIndex]
                    return val === maxVal ? '#1a65e0' : '#93c5fd'
                },
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: (ctx: any) => `${ctx.raw} ${t('credits')}`,
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: Math.max(1, Math.ceil(maxVal / 5)) },
                    grid: { color: '#f3f4f6' },
                },
                x: {
                    border: { display: false },
                    grid: { display: false },
                },
            },
        },
    })
}

onMounted(() => { drawChart() })
watch(usageChart, () => { drawChart() })
</script>

<template>
    <Head :title="t('Dashboard')" />

    <UserDashboardLayout>
        <div class="space-y-6">
            <!-- Welcome -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ t('Welcome, :name', { name: user?.name ?? '' }) }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ t('Your AI workspace is ready.') }}</p>
            </div>

            <!-- Getting Started Checklist -->
            <DashboardChecklist />

            <!-- Stats Row -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <ContextualTooltip tooltip-key="dashboard.stats" :content="t('Track your credits, daily/monthly usage and conversations at a glance.')">
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm dark:bg-gray-900 dark:border-gray-800 relative overflow-hidden">
                    <div class="absolute top-[-20px] right-[-20px] w-[80px] h-[80px] rounded-full bg-[#1F75FE] opacity-[0.07]"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-[#1F75FE]/10 flex items-center justify-center">
                                <i class="ti ti-bolt text-xl text-[#1F75FE]"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white font-heading">{{ formatNumber(stats.credits) }}</div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mt-1">{{ t('Credits') }}</div>
                    </div>
                </div>
                </ContextualTooltip>

                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm dark:bg-gray-900 dark:border-gray-800 relative overflow-hidden">
                    <div class="absolute top-[-20px] right-[-20px] w-[80px] h-[80px] rounded-full bg-amber-500 opacity-[0.07]"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                <i class="ti ti-sunrise text-xl text-amber-600"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white font-heading">{{ formatNumber(stats.credits_used_today) }}</div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mt-1">{{ t('Used today') }}</div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm dark:bg-gray-900 dark:border-gray-800 relative overflow-hidden">
                    <div class="absolute top-[-20px] right-[-20px] w-[80px] h-[80px] rounded-full bg-violet-500 opacity-[0.07]"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center">
                                <i class="ti ti-calendar-month text-xl text-violet-600"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white font-heading">{{ formatNumber(stats.credits_used_month) }}</div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mt-1">{{ t('Used this month') }}</div>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm dark:bg-gray-900 dark:border-gray-800 relative overflow-hidden">
                    <div class="absolute top-[-20px] right-[-20px] w-[80px] h-[80px] rounded-full bg-[#111111] opacity-[0.05]"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                <i class="ti ti-message-circle text-xl text-gray-700 dark:text-gray-300"></i>
                            </div>
                        </div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white font-heading">{{ formatNumber(stats.total_conversations) }}</div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wider mt-1">{{ t('Conversations') }}</div>
                    </div>
                </div>
            </div>

            <!-- Usage Chart + Plan -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ chartTitle }}</h2>
                        <div class="flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
                            <button
                                v-for="opt in [{ label: '7D', value: '7d' }, { label: 'Month', value: 'month' }, { label: '90D', value: '90d' }]"
                                :key="opt.value"
                                @click="switchChartPeriod(opt.value)"
                                :class="chartPeriod === opt.value ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                                class="rounded-md px-3 py-1 text-xs font-semibold transition"
                            >{{ opt.label }}</button>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="h-[220px]">
                            <canvas ref="canvasRef"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Plan / Subscription -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-4">{{ t('Current plan') }}</h2>
                    <template v-if="plan">
                        <div class="flex items-center gap-2 mb-3">
                            <span v-if="plan.subscription_status === 'active'" class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ t('Active') }}
                            </span>
                            <span v-else-if="plan.subscription_status === 'trialing'" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> {{ t('Trialing') }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {{ plan.is_free ? t('Free') : plan.name }}
                            </span>
                        </div>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ plan.name }}</p>
                        <p v-if="plan.trial_ends_at" class="text-sm text-gray-500 mt-1">{{ t('Trial ends :date', { date: formatDate(plan.trial_ends_at) }) }}</p>
                        <p v-if="plan.subscription_ends_at" class="text-sm text-gray-500 mt-1">{{ t('Access until :date', { date: formatDate(plan.subscription_ends_at) }) }}</p>
                        <ul v-if="plan.features?.length" class="mt-4 space-y-2">
                            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <i class="ti ti-check text-green-500 mt-0.5 shrink-0"></i>
                                <span>{{ feature }}</span>
                            </li>
                        </ul>
                        <div class="mt-5 space-y-2">
                            <Link v-if="plan.is_free && !plan.subscription_status || plan.subscription_status === 'none'" :href="route('pricing')" class="btn-primary w-full text-center inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold">
                                <i class="ti ti-arrow-up"></i> {{ t('Upgrade plan') }}
                            </Link>
                        </div>
                    </template>
                    <p v-else class="text-sm text-gray-500">{{ t('No active plan.') }}</p>
                </div>
            </div>

            <!-- Quick AI Tools -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <ContextualTooltip tooltip-key="dashboard.quick-tools" :content="t('Jump into any AI tool right from here. Click \'View all\' to browse the full catalog.')">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Quick AI tools') }}</h2>
                    </ContextualTooltip>
                    <Link :href="route('ai.tools.index')" class="text-sm font-semibold text-[#1F75FE] hover:underline">{{ t('View all') }}</Link>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="tool in quickTools"
                        :key="tool.slug"
                        :href="route('ai.tools.show', tool.slug)"
                        class="group flex items-start gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-[#1F75FE] hover:shadow-md hover:-translate-y-0.5 dark:bg-gray-900 dark:border-gray-800"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg" :style="{ background: (tool.color || '#1F75FE') + '18', color: tool.color || '#1F75FE' }">
                            <i :class="tool.icon || 'ti ti-wand'" class="text-xl"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ tool.name }}</h3>
                                <span v-if="tool.is_new" class="shrink-0 rounded-full bg-[#1F75FE]/10 px-1.5 py-px text-[10px] font-bold text-[#1F75FE] uppercase">{{ t('New') }}</span>
                                <span v-if="tool.requires_pro" class="shrink-0 rounded-full bg-violet-100 px-1.5 py-px text-[10px] font-bold text-violet-700 uppercase">{{ t('Pro') }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500 line-clamp-2">{{ tool.description }}</p>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Recent Items + Referral + Transactions -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Recent Conversations -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Recent conversations') }}</h2>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-if="recentConversations.length === 0" class="px-6 py-10 text-center text-sm text-gray-500">{{ t('No conversations yet.') }}</div>
                        <div v-for="conv in recentConversations" :key="conv.id" class="flex items-center justify-between px-6 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ conv.title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ conv.model }} · {{ conv.message_count }} {{ t('messages') }}</p>
                            </div>
                            <span class="text-xs text-gray-400 shrink-0 ml-3">{{ conv.last_message_at ? formatDate(conv.last_message_at!) : '' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Referral -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Referral') }}</h2>
                        <Link :href="route('user.dashboard.affiliate')" class="text-sm font-semibold text-[#1F75FE] hover:underline">{{ t('Details') }}</Link>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white font-heading">{{ formatNumber(referral.earnings) }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">{{ t('Earnings') }}</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-white font-heading">{{ referral.count }}</div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mt-1">{{ t('Referrals') }}</div>
                            </div>
                        </div>
                        <div v-if="referral.link" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                            <input :value="referral.link" readonly class="flex-1 bg-transparent text-xs text-gray-600 dark:text-gray-400 outline-none" />
                            <button @click="copyReferralLink" class="text-[#1F75FE] hover:text-[#1a65e0] text-xs font-semibold shrink-0">{{ t('Copy') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Documents -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Recent documents') }}</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-if="recentDocuments.length === 0" class="px-6 py-10 text-center text-sm text-gray-500">{{ t('No documents yet.') }}</div>
                    <div v-for="doc in recentDocuments" :key="doc.id" class="flex items-center justify-between px-6 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ doc.title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ doc.tool_slug }} · {{ formatNumber(doc.word_count ?? 0) }} {{ t('words') }}</p>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0 ml-3">{{ formatDate(doc.created_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ t('Recent transactions') }}</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div v-if="recentTransactions.length === 0" class="px-6 py-10 text-center text-sm text-gray-500">{{ t('No transactions yet.') }}</div>
                    <div v-for="tx in recentTransactions" :key="tx.id" class="flex items-center justify-between px-6 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ tx.description }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span :class="txTypeClass(tx.amount)" class="inline-flex items-center rounded-full px-2 py-px text-[10px] font-semibold uppercase">{{ txTypeLabel(tx.type) }}</span>
                                <span class="text-xs text-gray-400">{{ formatDate(tx.created_at) }}</span>
                            </div>
                        </div>
                        <div class="text-right shrink-0 ml-3">
                            <div :class="tx.amount > 0 ? 'text-green-600' : 'text-red-600'" class="text-sm font-bold">{{ tx.amount > 0 ? '+' : '' }}{{ formatNumber(tx.amount) }}</div>
                            <div class="text-xs text-gray-400">{{ t('Bal: :val', { val: formatNumber(tx.balance_after) }) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UserDashboardLayout>
</template>
