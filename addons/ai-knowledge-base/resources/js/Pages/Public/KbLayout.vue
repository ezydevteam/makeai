<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { usePage, Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import SocialFollow from '@themes/default/js/Components/SocialFollow.vue'
import { mediaUrl } from '@/lib/media'

const { t } = useTranslate()

const page = usePage()
const kbSettings = computed(() => (page.props as any).kbSettings || {})
const user = computed(() => page.props.auth?.user as any)

const searchQuery = ref('')
const profileOpen = ref(false)

const siteFavicon = computed(() => page.props.branding?.site_favicon_png || page.props.branding?.site_favicon_ico)
const siteName = computed(() => page.props.branding?.site_name || t('MakeAI'))

const isHomepage = computed(() => {
  const path = window.location.pathname
  const homePath = '/' + (kbSettings.value.public_slug || 'help')
  const hasCategory = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('category') : false
  return (path === homePath || path === homePath + '/') && !hasCategory
})

function handleHeaderSearch() {
  if (searchQuery.value.trim().length >= 2) {
    const slug = kbSettings.value.public_slug || 'help'
    window.location.href = `/${slug}?q=${encodeURIComponent(searchQuery.value)}`
  }
}

const userAvatar = computed(() => {
  if (!user.value?.avatar) return null

  return mediaUrl(user.value.avatar)
})

const kbLogo = computed(() => kbSettings.value.logo || null)

// Header/footer navigation come from admin-selected site menus (globalMenus is shared
// to every page). Respect each item's active flag and auth visibility.
const globalMenus = computed<any[]>(() => (page.props as any).globalMenus || [])
const isLoggedIn = computed(() => !!user.value)

function menuVisible(item: any): boolean {
  if (item.is_active === false) return false
  const auth = item.requires_auth || 'none'
  if (auth === 'auth') return isLoggedIn.value
  if (auth === 'guest') return !isLoggedIn.value
  return true
}
function resolveMenu(slug: string): any[] {
  if (!slug) return []
  const menu = globalMenus.value.find((m) => m.slug === slug)
  return menu ? (menu.items || []).filter(menuVisible) : []
}
const headerMenuItems = computed(() => resolveMenu(kbSettings.value.header_menu || 'main'))
const footerMenuItems = computed(() => resolveMenu(kbSettings.value.footer_menu || 'footer'))

// User dropdown — mirror the main site header's account menu so the KB feels native.
const isProAvailable = computed(() => !!(page.props as any).isProAvailable)
const affiliateEnabled = computed(() => !!(page.props as any).affiliateEnabled)
const hasPremium = computed(() => !!user.value?.is_pro)
const isAdmin = computed(() => {
  const u = user.value
  return !!(u && (u.role === 'admin' || u.is_admin || u.is_superuser))
})

const userMenuLinks = computed(() => {
  const links: Array<{ href: string; label: string; icon: string; tone?: string }> = [
    { href: route('user.dashboard'), label: t('Dashboard'), icon: 'ti ti-layout-dashboard' },
    { href: route('user.dashboard.profile'), label: t('My Profile'), icon: 'ti ti-user-circle' },
    { href: route('user.dashboard.favorites.index'), label: t('My Favorites'), icon: 'ti ti-heart' },
    { href: route('user.dashboard.history.index'), label: t('History'), icon: 'ti ti-history' },
  ]
  if (affiliateEnabled.value) links.push({ href: route('user.dashboard.affiliate'), label: t('Affiliate'), icon: 'ti ti-affiliate' })
  if (isProAvailable.value && !hasPremium.value) links.push({ href: route('user.dashboard.billing'), label: t('Upgrade'), icon: 'ti ti-rocket', tone: 'success' })
  if (isProAvailable.value && hasPremium.value) links.push({ href: route('user.dashboard.credit-topup'), label: t('Buy Credits'), icon: 'ti ti-coins', tone: 'success' })
  return links
})

const closeProfile = () => { profileOpen.value = false }
onMounted(() => document.addEventListener('click', closeProfile))
onUnmounted(() => document.removeEventListener('click', closeProfile))
</script>

<template>
  <!-- `frontend-theme` is required: /css/theme-variables.css scopes every
       --color-primary-* override to that class, so without it the KB ignores
       the admin's theme color and falls back to the compiled default. -->
  <div class="frontend-theme min-h-screen flex flex-col bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-200 selection:bg-primary-500 selection:text-white">
    <!-- Navigation Header -->
    <nav class="border-b border-gray-200 dark:border-surface-850 bg-white/70 dark:bg-surface-900/70 backdrop-blur-md sticky top-0 z-40 transition-all duration-300">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
        <!-- Logo & Menu Links -->
        <div class="flex items-center gap-6 lg:gap-8 min-w-0">
          <a :href="'/' + kbSettings.public_slug" class="flex items-center gap-2.5 group shrink-0">
            <img v-if="kbLogo" :src="kbLogo" :alt="kbSettings.page_title || siteName" class="h-8 w-auto max-w-[170px] object-contain" />
            <template v-else>
              <div v-if="siteFavicon" class="w-8 h-8 flex items-center justify-center shrink-0">
                <img :src="siteFavicon" :alt="siteName" class="w-7 h-7 object-contain rounded-lg" />
              </div>
              <div v-else class="w-8 h-8 rounded-xl bg-gradient-to-tr from-primary-500 to-primary-700 flex items-center justify-center text-white shadow-md shadow-primary-500/10 group-hover:scale-105 transition-transform duration-200 shrink-0">
                <i class="ti ti-lifebuoy text-lg"></i>
              </div>
              <span class="font-bold text-lg tracking-tight text-gray-900 dark:text-white truncate max-w-[150px] sm:max-w-none">
                {{ kbSettings.page_title || t('Help Center') }}
              </span>
            </template>
          </a>

          <!-- Header menu (admin-selected site menu) -->
          <div v-if="headerMenuItems.length" class="hidden md:flex items-center gap-5 shrink-0">
            <a
              v-for="item in headerMenuItems"
              :key="item.id"
              :href="item.final_url"
              :target="item.target || '_self'"
              class="text-sm font-semibold text-gray-500 hover:text-primary-500 dark:text-gray-400 dark:hover:text-primary-400 transition-colors"
            >
              {{ item.label }}
            </a>
          </div>
        </div>

        <!-- Search, Actions & User Profile -->
        <div class="flex items-center gap-4 shrink-0">
          <!-- Desktop Search Form (hidden on homepage) -->
          <form v-if="!isHomepage" @submit.prevent="handleHeaderSearch" class="relative hidden sm:block">
            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="t('Search help...')"
              class="w-40 focus:w-56 pl-9 pr-4 py-1.5 text-sm rounded-xl border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-300"
            />
          </form>

          <!-- User Section -->
          <div class="flex items-center gap-3">
            <!-- Guest Mode (Sign In) -->
            <a v-if="!user" href="/login" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold rounded-xl bg-primary-600 hover:bg-primary-700 text-white shadow-md shadow-primary-500/10 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200">
              <i class="ti ti-login-2"></i>
              <span>{{ t('Sign In') }}</span>
            </a>

            <!-- Logged In User Avatar Dropdown -->
            <div v-else class="relative" @click.stop>
              <button @click="profileOpen = !profileOpen" class="flex items-center gap-1.5 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-surface-800 transition-all">
                <div class="w-8 h-8 rounded-full overflow-hidden bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300 flex items-center justify-center font-bold text-sm border border-primary-200/10 shrink-0">
                  <img v-if="userAvatar" :src="userAvatar" :alt="user.name" class="w-full h-full object-cover" />
                  <span v-else>{{ user.name.charAt(0).toUpperCase() }}</span>
                </div>
                <i class="ti ti-chevron-down text-[10px] text-gray-400 dark:text-gray-500 pr-1 transition-transform" :class="{ 'rotate-180': profileOpen }"></i>
              </button>

              <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
              >
                <div v-if="profileOpen" class="absolute right-0 mt-2 w-60 bg-white dark:bg-surface-900 border border-gray-200 dark:border-surface-800 rounded-2xl shadow-xl py-1.5 z-50">
                  <div class="px-4 py-2.5 border-b border-gray-100 dark:border-surface-850">
                    <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ user.email }}</p>
                  </div>

                  <a
                    v-for="link in userMenuLinks"
                    :key="link.href"
                    :href="link.href"
                    class="flex items-center gap-2 px-4 py-2 text-sm transition-colors hover:bg-gray-50 dark:hover:bg-surface-850"
                    :class="link.tone === 'success' ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-700 dark:text-gray-300'"
                  >
                    <i :class="link.icon" class="text-base"></i>
                    <span>{{ link.label }}</span>
                  </a>

                  <a v-if="isAdmin" href="/admin" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-surface-850 transition-colors">
                    <i class="ti ti-settings text-base"></i>
                    <span>{{ t('Admin Panel') }}</span>
                  </a>

                  <hr class="my-1 border-gray-100 dark:border-surface-850" />

                  <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors text-left font-medium border-none bg-transparent cursor-pointer">
                    <i class="ti ti-logout text-base"></i>
                    <span>{{ t('Sign Out') }}</span>
                  </Link>
                </div>
              </Transition>
            </div>
          </div>

          <!-- Back Button -->
          <a href="/" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-surface-800 dark:hover:bg-surface-700 text-gray-600 dark:text-gray-300 transition-all shrink-0" :title="t('Back to site')">
            <i class="ti ti-arrow-left"></i>
            <span>{{ t('Back') }}</span>
          </a>
        </div>
      </div>

      <!-- Mobile Search Form (hidden on homepage) -->
      <div v-if="!isHomepage" class="sm:hidden px-4 pb-3">
        <form @submit.prevent="handleHeaderSearch" class="relative">
          <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('Search help...')"
            class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 text-gray-800 dark:text-gray-200 focus:!ring-1 focus:!ring-primary-500/20 transition-all"
          />
        </form>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-10">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200/60 dark:border-surface-850/60 py-5 text-xs text-gray-400 dark:text-gray-500 bg-white/40 dark:bg-surface-900/40 transition-colors">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row items-center justify-between gap-6">
        <span>&copy; {{ new Date().getFullYear() }} {{ siteName }} {{ t('Help Center') }}. All rights reserved.</span>

        <div class="flex flex-col sm:flex-row items-center gap-6">
          <div v-if="footerMenuItems.length" class="flex flex-wrap items-center justify-center gap-4">
            <template v-for="(item, i) in footerMenuItems" :key="item.id">
              <a :href="item.final_url" :target="item.target || '_self'" class="hover:text-primary-500 transition-colors">{{ item.label }}</a>
              <span v-if="i < footerMenuItems.length - 1" class="text-gray-300 dark:text-gray-800">|</span>
            </template>
          </div>
          <div v-else class="flex gap-4">
            <a href="/" class="hover:text-primary-500 transition-colors">{{ t('Back to Site') }}</a>
          </div>

          <SocialFollow displayMode="icons" />
        </div>
      </div>
    </footer>
  </div>
</template>
