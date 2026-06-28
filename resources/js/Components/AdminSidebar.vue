<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useTranslate } from '@/Composables/useTranslate'
import { useTheme } from '@/Composables/useTheme'
import Tooltip from '@/Components/UI/Tooltip.vue'
import LiveSearch from '@/Components/LiveSearch.vue'

const page = usePage()
const { t } = useTranslate()
const { isDark } = useTheme()

type Branding = {
    site_name?: string
    site_logo_light?: string
    site_logo_dark?: string
}
const branding = computed<Branding>(() => (page.props.branding as Branding) ?? {})
const sidebarLogo = computed(() => isDark.value
    ? (branding.value.site_logo_dark || branding.value.site_logo_light || '')
    : (branding.value.site_logo_light || branding.value.site_logo_dark || '')
)

type SidebarBroadcastingConfig = {
    redis_available?: boolean
}

const props = defineProps<{
    collapsed: boolean
}>()

const emit = defineEmits<{
    (e: 'toggle'): void
}>()

const menuGroups = ref<Record<string, boolean>>({})
const mobileSearchOpen = ref(false)

const isSuperAdmin = computed(() => (page.props.admin as any)?.isSuperAdmin ?? false)
const permissions = computed(() => (page.props.admin as any)?.permissions ?? [])
const pendingCommentsCount = computed(() => Number((page.props.admin as any)?.pendingCommentsCount ?? 0))
const isProAvailable = computed(() => Boolean(page.props.isProAvailable))
const affiliateEnabled = computed(() => Boolean(page.props.affiliateEnabled))
const ticketsEnabled = computed(() => Boolean(page.props.ticketsEnabled))
const contactEnabled = computed(() => Boolean(page.props.contactEnabled))
const blogEnabled = computed(() => Boolean(page.props.blogEnabled))
const addonMenuItems = computed(() => (page.props.addonMenuItems as any[]) ?? [])

// Group addon menu items by slug, flatten single-item addons
const addonGroups = computed(() => {
  const groups: Record<string, any> = {}
  for (const item of addonMenuItems.value) {
    if (!item?.slug) continue
    if (!groups[item.slug]) {
      groups[item.slug] = { name: item.addon_name || item.label, slug: item.slug, icon: '', items: [] }
    }
    if (!groups[item.slug].icon && item.icon) {
      groups[item.slug].icon = item.icon
    }
    groups[item.slug].items.push(item)
  }
  return groups
})

const broadcasting = computed<SidebarBroadcastingConfig>(() => (page.props.broadcasting as SidebarBroadcastingConfig) ?? {})

const can = (perm: string) => isSuperAdmin.value || permissions.value.includes(perm)
const canAny = (perms: string[]) => isSuperAdmin.value || perms.some((perm) => permissions.value.includes(perm))
const isActive = (name: string) => route().current(name)
const currentPath = computed(() => {
    const url = String(page.url ?? '')
    const [path] = url.split('?')

    return path.startsWith('/') ? path : `/${path}`
})
const currentQueryTab = computed(() => {
    const url = String(page.url ?? '')
    const [, query = ''] = url.split('?')
    return new URLSearchParams(query).get('tab') ?? ''
})
const isCurrentRoute = (...names: string[]) => names.some((name) => isActive(name))
const isCurrentPath = (...prefixes: string[]) => prefixes.some((prefix) => currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`))
const isAiManagementActive = () =>
    isCurrentRoute(
        'admin.ai.index',
        'admin.ai.provider',
        'admin.ai.integrations.*',
        'admin.ai.categories.*',
        'admin.ai.tools.*',
        'admin.ai.templates.*',
        'admin.ai.access.*',
        'admin.ai.logs.*',
        'admin.ai.rag.*',
    )
const isExportCenterActive = () => isCurrentPath('/admin/reports/export-center')
const isRateLimitsActive = () => isCurrentPath('/admin/system/rate-limits')
const isContactMessagesActive = () => isCurrentPath('/admin/contact/messages', '/admin/contact/settings')
const isSupportTicketsActive = () => isCurrentPath('/admin/support/tickets', '/admin/support/departments', '/admin/support/canned-responses', '/admin/support/settings')
const isLanguagesActive = () => isCurrentPath('/admin/localization/languages', '/admin/localization/translations')
const isOAuthActive = () => isCurrentRoute('admin.oauth.settings.edit', 'admin.oauth.settings.update')
const isMarketingAdsActive = () => isCurrentPath('/admin/marketing/ads')
const isSocialCountersActive = () => isCurrentRoute('admin.social-counters.settings.edit', 'admin.social-counters.settings.update')
const isNewsletterActive = () => isCurrentPath('/admin/marketing/newsletter')
const isDefaultThemeSettingsActive = (tab?: string) => {
    if (!isCurrentPath('/admin/appearance/themes/default/settings')) {
        return false
    }

    if (!tab) {
        return true
    }

    return currentQueryTab.value === tab
}
const isSystemGroupActive = () => isCurrentPath('/admin/system', '/admin/system/style', '/admin/license', '/admin/activity')
const isSystemHealthActive = () => isCurrentPath('/admin/system/health')
const isSystemUpdatesActive = () => isCurrentPath('/admin/system/updates')
const isSystemCronActive = () => isCurrentPath('/admin/system/cron-jobs')
const isSystemMaintenanceActive = () => isCurrentPath('/admin/system/maintenance')
const isCustomStyleActive = () => isCurrentPath('/admin/system/style')

function toggleGroup(group: string) {
    const nextState = !menuGroups.value[group]
    menuGroups.value = nextState ? { [group]: true } : {}
}

function isGroupOpen(group: string) {
    return menuGroups.value[group] ?? false
}

function autoExpandOnRoute(group: string, patterns: string[]) {
    for (const p of patterns) {
        if (route().current(p)) {
            menuGroups.value[group] = true
            return
        }
    }
}

autoExpandOnRoute('users', ['admin.users.*', 'admin.admins.*', 'admin.roles.*'])
autoExpandOnRoute('ai', ['admin.ai.index', 'admin.ai.provider', 'admin.ai.integrations.*', 'admin.ai.categories.*', 'admin.ai.tools.*', 'admin.ai.templates.*', 'admin.ai.access.*', 'admin.ai.logs.*', 'admin.ai.rag.*'])
autoExpandOnRoute('premium', ['admin.plans.*', 'admin.payment-gateways.*', 'admin.subscriptions.*', 'admin.coupons.*', 'admin.credit-settings.*'])
autoExpandOnRoute('blog', ['admin.blog.posts.*', 'admin.blog.categories.*', 'admin.blog.tags.*', 'admin.blog.settings.*', 'admin.comments.*'])
autoExpandOnRoute('appearance', ['admin.themes*', 'admin.addons*', 'admin.menus.*', 'admin.sidebar.*'])
autoExpandOnRoute('system', ['admin.system.*', 'admin.activity.*', 'admin.appearance.*', 'admin.license.*'])
autoExpandOnRoute('system-settings', ['admin.settings.index', 'admin.settings.update', 'admin.features.settings*', 'admin.gdpr.settings*', 'admin.oauth.settings.*', 'admin.notifications.settings*', 'admin.social-counters.settings.*'])

if (route().current('admin.themes.settings')) {
    menuGroups.value['appearance'] = true
}

// Auto-expand addon groups when on their pages
for (const item of addonMenuItems.value) {
  if (item?.slug && item?.route_pattern && route().current(item.route_pattern)) {
    menuGroups.value['addon-' + item.slug] = true
  }
}
</script>

<template>
  <aside :class="[collapsed ? 'sidebar-mini' : 'sidebar-expanded']" class="admin-sidebar">
    <!-- Logo + Collapse -->
    <div class="sidebar-logo-wrap">
    <div class="sidebar-logo">
      <div class="flex items-center gap-3 overflow-hidden flex-1 min-w-0">
        <img v-if="sidebarLogo" :src="sidebarLogo" :alt="branding.site_name || $page.props.appName" class="h-8 w-auto max-w-32 object-contain shrink-0" />
        <span v-else class="text-[15px] font-semibold text-[#111827] dark:text-white truncate">{{ $page.props.appName }}</span>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="shrink-0 w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-all dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-white/5 lg:hidden"
          :aria-label="mobileSearchOpen ? t('Hide search') : t('Open search')"
          @click="mobileSearchOpen = !mobileSearchOpen"
        >
          <i class="ti ti-search text-[18px]" aria-hidden="true"></i>
        </button>
        <Tooltip :content="t('Toggle sidebar')" placement="right" class="inline-flex">
          <button
            @click="emit('toggle')"
            class="shrink-0 w-7 h-7 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-all dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-white/5"
          >
            <i
              :class="collapsed ? 'ti ti-layout-sidebar-left-expand' : 'ti ti-layout-sidebar-left-collapse'"
              class="text-[18px]"
              aria-hidden="true"
            ></i>
          </button>
        </Tooltip>
      </div>
    </div>
    <div v-if="mobileSearchOpen" class="border-t border-gray-100 px-4 py-3 lg:hidden dark:border-surface-700">
      <LiveSearch context="admin" compact />
    </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
      <!-- Dashboard -->
      <Tooltip v-if="can('dashboard.view')" :content="t('Dashboard')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.dashboard')" class="sidebar-item" :class="{ active: isActive('admin.dashboard') }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
          <span v-show="!collapsed">{{ t('Dashboard') }}</span>
        </Link>
      </Tooltip>

      <!-- Roles -->
      <div v-if="canAny(['users.view', 'admins.view', 'roles.view'])">
        <Tooltip :content="t('Roles')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('users')" class="sidebar-item w-full" :class="{ 'active !font-medium': isGroupOpen('users') || isActive('admin.users.*') || isActive('admin.admins.*') || isActive('admin.roles.*'), 'open': isGroupOpen('users') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('Roles') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('users') }">
          <Link v-if="can('users.view')" :href="route('admin.users.index')" class="sidebar-subitem" :class="{ active: isActive('admin.users.*') }">{{ t('Users') }}</Link>
          <Link v-if="canAny(['admins.view', 'roles.view'])" :href="route('admin.admins.index')" class="sidebar-subitem" :class="{ active: isActive('admin.admins.*') || isActive('admin.roles.*') }">{{ t('Admins') }}</Link>
        </div>
      </div>

      <!-- AI Mangement -->
      <div v-if="can('ai.tools')">
        <Tooltip :content="t('AI Mangement')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('ai')" class="sidebar-item w-full" :class="{ 'active !font-medium': isGroupOpen('ai') || isAiManagementActive(), 'open': isGroupOpen('ai') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456z" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('AI Mangement') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('ai') }">
          <Link :href="route('admin.ai.tools.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.tools.*') }">{{ t('Tools') }}</Link>
          <Link :href="route('admin.ai.templates.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.templates.*') }">{{ t('Templates') }}</Link>
          <Link :href="route('admin.ai.categories.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.categories.*') }">{{ t('Categories') }}</Link>
          <Link :href="route('admin.ai.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.index') || isActive('admin.ai.provider') }">{{ t('Providers') }}</Link>
          <Link :href="route('admin.ai.integrations.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.integrations.*') }">{{ t('Integrations') }}</Link>
          <Link :href="route('admin.ai.access.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.access.*') }">{{ t('Access Control') }}</Link>
          <Link :href="route('admin.ai.rag.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.rag.*') }">{{ t('RAG Settings') }}</Link>
          <Link :href="route('admin.ai.logs.index')" class="sidebar-subitem" :class="{ active: isActive('admin.ai.logs.*') }">{{ t('Usage & Logs') }}</Link>
        </div>
      </div>

      <!-- Premium (Pro only) -->
      <div v-if="isProAvailable && canAny(['plans.view', 'payments.view', 'payments.gateways'])">
        <Tooltip :content="t('Premium')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('premium')" class="sidebar-item w-full" :class="{ 'active !font-medium': isGroupOpen('premium') || isActive('admin.plans.*') || isActive('admin.payment-gateways.*') || isActive('admin.subscriptions.*') || isActive('admin.coupons.*'), 'open': isGroupOpen('premium') }">
            <svg class="sidebar-icon text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('Premium') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('premium') }">
          <Link v-if="can('payments.view')" :href="route('admin.subscriptions.index')" class="sidebar-subitem" :class="{ active: isActive('admin.subscriptions.*') }">{{ t('Subscriptions') }}</Link>
          <Link v-if="can('plans.view')" :href="route('admin.plans.index')" class="sidebar-subitem" :class="{ active: isActive('admin.plans.*') }">{{ t('Plans') }}</Link>
          <Link v-if="can('settings.manage')" :href="route('admin.credit-settings.index')" class="sidebar-subitem" :class="{ active: isActive('admin.credit-settings.*') }">{{ t('Credits') }}</Link>
          <Link v-if="can('payments.gateways')" :href="route('admin.coupons.index')" class="sidebar-subitem" :class="{ active: isActive('admin.coupons.*') }">{{ t('Coupons') }}</Link>
          <Link v-if="can('payments.view')" :href="route('admin.payment-gateways.index')" class="sidebar-subitem" :class="{ active: isActive('admin.payment-gateways.*') }">{{ t('Gateways') }}</Link>
        </div>
      </div>

      <!-- === Content === -->
      <div v-show="!collapsed" class="sidebar-group-label">{{ t('Content') }}</div>
      <Tooltip v-if="can('content.pages')" :content="t('Pages')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.pages.index')" class="sidebar-item" :class="{ active: isActive('admin.pages.*') }">
        <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        <span v-show="!collapsed">{{ t('Pages') }}</span>
        </Link>
      </Tooltip>
      <div v-if="blogEnabled && canAny(['content.blog', 'content.comments'])">
        <Tooltip :content="t('Blog')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('blog')" class="sidebar-item w-full" :class="{ 'active !font-medium': isGroupOpen('blog') || isActive('admin.blog.*') || isActive('admin.comments.*'), 'open': isGroupOpen('blog') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.25c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('Blog') }}</span>
            <span v-if="!collapsed && pendingCommentsCount > 0" class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">{{ pendingCommentsCount }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('blog') }">
          <Link v-if="can('content.blog')" :href="route('admin.blog.posts.index')" class="sidebar-subitem" :class="{ active: isActive('admin.blog.posts.*') }">{{ t('Posts') }}</Link>
          <Link v-if="can('content.blog')" :href="route('admin.blog.categories.index')" class="sidebar-subitem" :class="{ active: isActive('admin.blog.categories.*') }">{{ t('Categories') }}</Link>
          <Link v-if="can('content.blog')" :href="route('admin.blog.tags.index')" class="sidebar-subitem" :class="{ active: isActive('admin.blog.tags.*') }">{{ t('Tags') }}</Link>
          <Link v-if="can('content.blog')" :href="route('admin.blog.settings.edit')" class="sidebar-subitem" :class="{ active: isActive('admin.blog.settings.*') }">{{ t('Settings') }}</Link>
          <Link v-if="canAny(['content.comments', 'content.blog', 'content.pages'])" :href="route('admin.comments.index')" class="sidebar-subitem" :class="{ active: isActive('admin.comments.*') }">{{ t('Comments') }}</Link>
        </div>
      </div>
      <Tooltip v-if="can('content.testimonials')" :content="t('Testimonials')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.testimonials.index')" class="sidebar-item" :class="{ active: isActive('admin.testimonials.*') }">
        <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
        <span v-show="!collapsed">{{ t('Testimonials') }}</span>
        </Link>
      </Tooltip>
      <Tooltip v-if="can('content.faq')" :content="t('FAQs')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.faqs.index')" class="sidebar-item" :class="{ active: isActive('admin.faqs.*') }">
        <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span v-show="!collapsed">{{ t('FAQs') }}</span>
        </Link>
      </Tooltip>
      <div v-show="!collapsed" class="sidebar-group-label !pt-5">{{ t('Communications') }}</div>
      <Tooltip v-if="can('content.pages') && contactEnabled" :content="t('Messages')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.contact.messages.index')" class="sidebar-item" :class="{ active: isContactMessagesActive() }">
        <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5A2.25 2.25 0 0119.5 19.5h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0l-7.5-4.615A2.25 2.25 0 012.25 6.993V6.75" /></svg>
        <span v-show="!collapsed">{{ t('Messages') }}</span>
        </Link>
      </Tooltip>
      <Tooltip v-if="can('support.tickets') && ticketsEnabled" :content="t('Support')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.support.tickets.index')" class="sidebar-item" :class="{ active: isSupportTicketsActive() }">
        <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
        <span v-show="!collapsed">{{ t('Support') }}</span>
        </Link>
      </Tooltip>

      <!-- === Appearance === -->
      <div v-if="canAny(['addons.view', 'settings.manage'])">
        <div v-show="!collapsed" class="sidebar-group-label !pt-5">{{ t('Appearance') }}</div>
        <Tooltip :content="t('Appearance')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('appearance')" class="sidebar-item w-full" :class="{ 'active !font-medium': isGroupOpen('appearance') || isActive('admin.themes*') || isActive('admin.addons*') || isActive('admin.menus.*') || isActive('admin.sidebar.*'), 'open': isGroupOpen('appearance') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122l9.37-9.37a2.85 2.85 0 114.03 4.03l-9.37 9.37a4.5 4.5 0 01-1.897 1.13L4.14 23.29a.75.75 0 01-.944-.944l.673-3.133a4.5 4.5 0 011.13-1.897l9.37-9.37z" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('Appearance') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('appearance') }">
          <Link v-if="can('settings.manage')" :href="route('admin.themes')" class="sidebar-subitem" :class="{ active: isActive('admin.themes*') }">{{ t('Themes') }}</Link>
          <Link v-if="can('settings.manage')" :href="route('admin.menus.index')" class="sidebar-subitem" :class="{ active: isActive('admin.menus.*') }">{{ t('Menus') }}</Link>
          <Link v-if="can('settings.manage')" :href="route('admin.sidebar.index')" class="sidebar-subitem" :class="{ active: isActive('admin.sidebar.*') }">{{ t('Sidebar') }}</Link>
          <Link v-if="can('addons.view')" :href="route('admin.addons')" class="sidebar-subitem" :class="{ active: isActive('admin.addons*') }">{{ t('Addons') }}</Link>
        </div>
      </div>

      <!-- System -->
      <div v-if="can('settings.manage')">
        <Tooltip :content="t('Settings')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('system-settings')" class="sidebar-item w-full" :class="{ 'active !font-medium': isActive('admin.settings.index') || isActive('admin.settings.update') || isActive('admin.features.settings*') || isActive('admin.gdpr.settings*') || isOAuthActive() || isActive('admin.notifications.settings*') || isSocialCountersActive(), 'open': isGroupOpen('system-settings') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.213m5.102-4c0-1.315-.152-2.593-.44-3.815a.75.75 0 01.527-.895l.896-.24a.75.75 0 01.917.54C19.875 9.92 21 12.33 21 15s-1.125 5.08-3.264 7.605a.75.75 0 01-.917.54l-.896-.24a.75.75 0 01-.527-.895A21.036 21.036 0 0015.5 15c0-1.315-.152-2.593-.44-3.815z" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('Settings') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('system-settings') }">
          <Link :href="route('admin.settings.index')" class="sidebar-subitem" :class="{ active: isActive('admin.settings.index') }">{{ t('General') }}</Link>
          <Link :href="route('admin.features.settings')" class="sidebar-subitem" :class="{ active: isActive('admin.features.settings*') }">{{ t('Features') }}</Link>
          <Link :href="route('admin.notifications.settings')" class="sidebar-subitem" :class="{ active: isActive('admin.notifications.settings*') }">{{ t('Notifications') }}</Link>
          <Link :href="route('admin.social-counters.settings.edit')" class="sidebar-subitem" :class="{ active: isSocialCountersActive() }">{{ t('Social') }}</Link>
          <Link :href="route('admin.gdpr.settings')" class="sidebar-subitem" :class="{ active: isActive('admin.gdpr.settings*') }">{{ t('GDPR') }}</Link>
          <Link :href="route('admin.oauth.settings.edit')" class="sidebar-subitem" :class="{ active: isOAuthActive() }">{{ t('OAuth') }}</Link>
        </div>
        <Tooltip :content="t('System')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('system')" class="sidebar-item w-full" :class="{ 'active !font-medium': isSystemGroupActive(), 'open': isGroupOpen('system') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('System') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('system') }">
          <Link :href="route('admin.system.health')" class="sidebar-subitem" :class="{ active: isSystemHealthActive() }">{{ t('Health Check') }}</Link>
          <Link :href="route('admin.system.updates')" class="sidebar-subitem" :class="{ active: isSystemUpdatesActive() }">
            {{ t('Update') }}
            <span v-if="page.props.updateAvailable" class="ml-1.5 inline-flex items-center rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">1</span>
          </Link>
          <Link :href="route('admin.system.cron-jobs')" class="sidebar-subitem" :class="{ active: isSystemCronActive() }">{{ t('Cron Jobs') }}</Link>
          <Link :href="route('admin.system.maintenance')" class="sidebar-subitem" :class="{ active: isSystemMaintenanceActive() }">{{ t('Maintenance') }}</Link>
          <Link :href="route('admin.system.rate-limits.index')" class="sidebar-subitem" :class="{ active: isRateLimitsActive() }">{{ t('Rate Limits') }}</Link>
          <Link :href="route('admin.appearance.index')" class="sidebar-subitem" :class="{ active: isCustomStyleActive() }">{{ t('Custom Style') }}</Link>
          <Link v-if="isSuperAdmin" :href="route('admin.activity.admin-logs.index')" class="sidebar-subitem" :class="{ active: isActive('admin.activity.admin-logs.*') || isActive('admin.activity.user-logs.*') }">{{ t('Activity Logs') }}</Link>
          <a v-if="broadcasting.redis_available" href="/horizon" class="sidebar-subitem" target="_blank" rel="noopener">
            {{ t('Horizon') }}
            <svg class="ml-1 inline-block h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
          </a>
        </div>
      </div>
       <!-- === Mail === -->
      <div v-if="can('settings.manage')">
        <Tooltip :content="t('Mail')" placement="right" :full-width="true" :disabled="!collapsed">
          <button @click="toggleGroup('mail')" class="sidebar-item w-full" :class="{ 'active !font-medium': isGroupOpen('mail') || isActive('admin.mail.*'), 'open': isGroupOpen('mail') }">
            <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
            <span v-show="!collapsed" class="flex-1 text-left">{{ t('Mail') }}</span>
            <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
          </button>
        </Tooltip>
        <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('mail') }">
          <Link :href="route('admin.mail.templates.index')" class="sidebar-subitem" :class="{ active: isActive('admin.mail.templates.*') }">{{ t('Templates') }}</Link>
          <Link :href="route('admin.mail.index')" class="sidebar-subitem" :class="{ active: isActive('admin.mail.index') }">{{ t('Settings') }}</Link>
          <Link :href="route('admin.mail.logs.index')" class="sidebar-subitem" :class="{ active: isActive('admin.mail.logs.*') }">{{ t('Logs') }}</Link>
        </div>
      </div>
      <Tooltip v-if="can('translations.manage')" :content="t('Languages')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.languages.index')" class="sidebar-item" :class="{ active: isLanguagesActive() }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 016-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 01-3.827-5.802" /></svg>
          <span v-show="!collapsed">{{ t('Languages') }}</span>
        </Link>
      </Tooltip>
      <Tooltip v-if="can('settings.manage')" :content="t('Export Center')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.reports.export-center')" class="sidebar-item" :class="{ active: isExportCenterActive() }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5m-4.5 4.5L7.5 12m-3 6.75h15A2.25 2.25 0 0021.75 16.5v-.75a.75.75 0 00-.75-.75h-18a.75.75 0 00-.75.75v.75A2.25 2.25 0 004.5 18.75z" /></svg>
          <span v-show="!collapsed">{{ t('Export Center') }}</span>
        </Link>
      </Tooltip>

      <!-- === Marketing === -->
      <div v-show="!collapsed" class="sidebar-group-label !pt-5">{{ t('Marketing') }}</div>
      <Tooltip v-if="can('payments.gateways') && affiliateEnabled" :content="t('Affiliate')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.affiliate.index')" class="sidebar-item" :class="{ active: isActive('admin.affiliate.*') }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
          <span v-show="!collapsed">{{ t('Affiliate') }}</span>
        </Link>
      </Tooltip>
      <Tooltip v-if="can('users.manage')" :content="t('Newsletter')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.newsletter.index')" class="sidebar-item" :class="{ active: isNewsletterActive() }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
          <span v-show="!collapsed">{{ t('Newsletter') }}</span>
        </Link>
      </Tooltip>
      <Tooltip v-if="can('settings.manage')" :content="t('Advertisements')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.ads.index')" class="sidebar-item" :class="{ active: isMarketingAdsActive() }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.213m5.102-4c0-1.315-.152-2.593-.44-3.815a.75.75 0 01.527-.895l.896-.24a.75.75 0 01.917.54C19.875 9.92 21 12.33 21 15s-1.125 5.08-3.264 7.605a.75.75 0 01-.917.54l-.896-.24a.75.75 0 01-.527-.895A21.036 21.036 0 0015.5 15c0-1.315-.152-2.593-.44-3.815z" /></svg>
          <span v-show="!collapsed">{{ t('Advertisements') }}</span>
        </Link>
      </Tooltip>
      <Tooltip v-if="can('content.pages')" :content="t('Announcements')" placement="right" :full-width="true" :disabled="!collapsed">
        <Link :href="route('admin.announcements.index')" class="sidebar-item" :class="{ active: isActive('admin.announcements.*') }">
          <svg class="sidebar-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" /></svg>
          <span v-show="!collapsed">{{ t('Announcements') }}</span>
        </Link>
      </Tooltip>

      <!-- Addon menu items -->
      <template v-if="addonMenuItems.length">
        <div v-show="!collapsed" class="sidebar-group-label !pt-5">{{ t('Addons') }}</div>
        <template v-for="(group, slug) in addonGroups" :key="slug">
          <template v-if="group.items.length > 1">
            <Tooltip :content="group.name" placement="right" :full-width="true" :disabled="!collapsed">
              <button
                @click="toggleGroup('addon-' + slug)"
                class="sidebar-item w-full"
                :class="{
                  'active !font-medium': isGroupOpen('addon-' + slug)
                    || group.items.some((i: any) => i?.route_pattern && isActive(i.route_pattern)),
                  'open': isGroupOpen('addon-' + slug)
                }"
              >
                <i v-if="group.icon" :class="group.icon" class="sidebar-icon"></i>
                <span v-show="!collapsed" class="flex-1 text-left">{{ group.name }}</span>
                <svg v-show="!collapsed" class="sidebar-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
              </button>
            </Tooltip>
            <div v-show="!collapsed" class="sidebar-submenu" :class="{ open: isGroupOpen('addon-' + slug) }">
              <Link
                v-for="item in group.items"
                :key="item.label ?? slug"
                :href="route(item.route, item.route_params || {})"
                class="sidebar-subitem"
                :class="{ active: item.route_pattern ? isActive(item.route_pattern) : false }"
              >{{ item.label }}</Link>
            </div>
          </template>
          <Tooltip v-else v-for="item in group.items" :key="item.route ?? slug" :content="item.label" placement="right" :full-width="true" :disabled="!collapsed">
            <Link :href="route(item.route, item.route_params || {})" class="sidebar-item" :class="{ active: item.route_pattern ? isActive(item.route_pattern) : false }">
              <i v-if="item.icon" :class="item.icon" class="sidebar-icon"></i>
              <span v-show="!collapsed">{{ item.label }}</span>
            </Link>
          </Tooltip>
        </template>
      </template>
    </nav>
  </aside>
</template>

<style>
/* === Sidebar Shell === */
.admin-sidebar {
  width: 260px;
  height: 100vh;
  background: #ffffff;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease;
  position: sticky;
  top: 0;
  z-index: 40;
  overflow: hidden;
}
.sidebar-mini {
  width: 64px !important;
}
html.dark .admin-sidebar {
  background: #1a1a2e;
  border-right-color: #30363d;
}

/* === Logo === */
.sidebar-logo {
  height: 60px;
  display: flex;
  align-items: center;
  padding: 0 1.25rem;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}
html.dark .sidebar-logo {
  border-bottom-color: #30363d;
}

/* === Nav scroll area === */
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0.5rem 0 1rem;
}

/* Thin scrollbar */
.sidebar-nav {
  scrollbar-width: thin;
  scrollbar-color: rgba(156, 163, 175, 0.22) transparent;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-nav::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.22); border-radius: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(156, 163, 175, 0.38); }
html.dark .sidebar-nav {
  scrollbar-color: rgba(255, 255, 255, 0.14) transparent;
}
html.dark .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.14); }
html.dark .sidebar-nav::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.24); }

/* === Group Label === */
.sidebar-group-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #9ca3af;
  padding: 1rem 1.25rem 0.375rem;
}
html.dark .sidebar-group-label {
  color: rgba(255, 255, 255, 0.3);
}

/* === Nav Item === */
.sidebar-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 1.25rem;
  color: #4b5563;
  font-size: 0.875rem;
  font-weight: 450;
  border-radius: 0;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
  position: relative;
  border: none;
  background: transparent;
}
.sidebar-item:hover {
  color: #111827;
  background: #f3f4f6;
}
html.dark .sidebar-item {
  color: #9ca3af;
}
html.dark .sidebar-item:hover {
  color: #e6edf3;
  background: rgba(255, 255, 255, 0.03);
}

/* Active state — blue left bar + blue tint */
.sidebar-item.active {
  color: #1F75FE;
  background: rgba(31, 117, 254, 0.08);
}
.sidebar-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 3px;
  background: #1F75FE;
  border-radius: 0 2px 2px 0;
}

/* === Sidebar Icon === */
.sidebar-icon {
  width: 18px;
  height: 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  line-height: 1;
  flex-shrink: 0;
  opacity: 0.8;
}
.sidebar-item.active .sidebar-icon {
  opacity: 1;
  color: #1F75FE;
}

/* === Chevron === */
.sidebar-chevron {
  margin-left: auto;
  width: 14px;
  height: 14px;
  opacity: 0.5;
  transition: transform 0.2s ease;
}
.sidebar-item.open .sidebar-chevron {
  transform: rotate(180deg);
}

/* === Submenu (dot style) === */
.sidebar-submenu {
  overflow: hidden;
  max-height: 0;
  transition: max-height 0.25s ease;
}
.sidebar-submenu.open {
  max-height: 600px;
}

.sidebar-subitem {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 1.25rem 0.375rem 3.25rem;
  color: #6b7280;
  font-size: 0.8125rem;
  cursor: pointer;
  transition: color 0.15s;
  text-decoration: none;
}
.sidebar-subitem:hover {
  color: #111827;
}
html.dark .sidebar-subitem {
  color: #8b949e;
}
html.dark .sidebar-subitem:hover {
  color: #e6edf3;
}
.sidebar-subitem.active {
  color: #1F75FE;
}
.sidebar-subitem::before {
  content: '';
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}
</style>
