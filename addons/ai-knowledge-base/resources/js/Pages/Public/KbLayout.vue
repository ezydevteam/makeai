<script setup lang="ts">
import { useTranslate } from '@/Composables/useTranslate'
import { usePage, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import SocialFollow from '@themes/default/js/Components/SocialFollow.vue'
// The assistant bubble lives in the theme's AppLayout, which the Help Center does not use —
// so it simply never rendered here, on the pages where a visitor is most likely to be stuck
// and want to ask. The loader decides for itself whether to show: it keys off the global
// `aiAssistant` prop, which the addon sets to null when it is inactive, when the visitor
// fails the admin's show_to rule, or when the page is excluded.
import AiAssistantLoader from '../../../../../ai-assistant/resources/js/Components/AiAssistantLoader.vue'
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
  // Demo mode folds its own layout switcher (Home ▸ Home 1/2/3, Tool Page ▸ Page 1-4) into
  // the main menu. That belongs in the site header it previews, not in the Help Center,
  // where the links lead back out of the KB and preview nothing on this page.
  if (item.is_demo || String(item.id ?? '').startsWith('demo-')) return false
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

// globalMenus ships a FLAT item list; nesting lives in each item's parent_id. Rendering
// that list as-is put every child link in the top row beside its own parent, which is why
// a menu with submenus looked duplicated here but correct in the site header.
const menuParentId = (item: any) => item.parent_id ?? item.parentId ?? null

function menuTree(slug: string): any[] {
  const items = resolveMenu(slug)

  return items
    .filter((item) => !menuParentId(item))
    .map((item) => ({
      ...item,
      children: items.filter((child) => String(menuParentId(child)) === String(item.id)),
    }))
}

const headerMenuItems = computed(() => menuTree(kbSettings.value.header_menu || 'main'))
// The footer is a single row of links, so it takes the top level only — a child rendered
// there reads as a sibling of its own parent.
const footerMenuItems = computed(() => menuTree(kbSettings.value.footer_menu || 'footer'))

// Which top-level item has its submenu open. Click, not hover: this menu now renders on
// touch screens too, where there is no hover to open it with.
const openMenuId = ref<string | number | null>(null)

function toggleMenu(item: any): void {
  openMenuId.value = openMenuId.value === item.id ? null : item.id
}

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

// The homepage hero carries its own big search box, so the header's is redundant while the
// hero is on screen — but the moment it scrolls away the visitor has no search at all. Watch
// the hero itself rather than guessing a pixel offset: its height changes with the heading,
// the settings-driven subheading and the viewport.
const heroPassed = ref(false)
let heroObserver: IntersectionObserver | null = null

// Show the header search on every page except the homepage, and there only once the hero
// has gone. If the hero is missing (a layout that never renders one), fall back to showing.
const showHeaderSearch = computed(() => !isHomepage.value || heroPassed.value)

const watchHero = () => {
  const hero = document.querySelector('.kb-hero')

  if (!hero) {
    heroPassed.value = true
    return
  }

  heroObserver = new IntersectionObserver(
    ([entry]) => { heroPassed.value = !entry.isIntersecting },
    // Fire against the sticky header's own band, so the swap happens as the hero slides
    // under it rather than after it has fully cleared the viewport.
    { rootMargin: '-64px 0px 0px 0px', threshold: 0 },
  )

  heroObserver.observe(hero)
}

const closeOverlays = () => {
  profileOpen.value = false
  openMenuId.value = null
}

const onEscape = (event: KeyboardEvent) => {
  if (event.key === 'Escape') closeOverlays()
}

onMounted(() => {
  document.addEventListener('click', closeOverlays)
  document.addEventListener('keydown', onEscape)
  watchHero()
})
onUnmounted(() => {
  document.removeEventListener('click', closeOverlays)
  document.removeEventListener('keydown', onEscape)
  heroObserver?.disconnect()
})
</script>

<template>
  <!-- `frontend-theme` is required: /css/theme-variables.css scopes every
       --color-primary-* override to that class, so without it the KB ignores
       the admin's theme color and falls back to the compiled default. -->
  <div class="frontend-theme min-h-screen flex flex-col bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-200 selection:bg-primary-500 selection:text-white">
    <!-- Navigation Header -->
    <nav class="border-b border-gray-200 dark:border-surface-850 bg-white/70 dark:bg-surface-900/70 backdrop-blur-md sticky top-0 z-40 transition-all duration-300">
      <!-- min-h rather than a fixed h-16: once the nav can wrap, the bar has to be allowed
           to grow with it instead of clipping the second row. -->
      <div class="max-w-6xl mx-auto px-4 sm:px-6 min-h-16 py-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <!-- Logo -->
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

        <!-- Header menu (admin-selected site menu), centred between the logo and the
             actions. Below md it drops to its own full-width row (order-last) rather than
             hiding, since a help centre's nav is how visitors get back to the rest of the
             site — and it stays centred there too. -->
        <div
          v-if="headerMenuItems.length"
          class="order-last w-full md:order-none md:w-auto md:flex-1 flex flex-wrap items-center justify-center gap-x-5 gap-y-1.5 min-w-0"
        >
            <template v-for="item in headerMenuItems" :key="item.id">
              <!-- Leaf -->
              <a
                v-if="!item.children.length"
                :href="item.final_url"
                :target="item.target || '_self'"
                :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined"
                class="text-sm font-semibold text-gray-500 hover:text-primary-500 dark:text-gray-400 dark:hover:text-primary-400 transition-colors"
              >
                {{ item.label }}
              </a>

              <!-- Parent with a submenu -->
              <div v-else class="relative" @click.stop>
                <button
                  type="button"
                  class="flex items-center gap-1 text-sm font-semibold transition-colors"
                  :class="openMenuId === item.id
                    ? 'text-primary-600 dark:text-primary-400'
                    : 'text-gray-500 hover:text-primary-500 dark:text-gray-400 dark:hover:text-primary-400'"
                  :aria-expanded="openMenuId === item.id"
                  aria-haspopup="true"
                  @click="toggleMenu(item)"
                >
                  {{ item.label }}
                  <i class="ti ti-chevron-down text-[10px] transition-transform" :class="{ 'rotate-180': openMenuId === item.id }"></i>
                </button>

                <Transition
                  enter-active-class="transition ease-out duration-200"
                  enter-from-class="opacity-0 scale-95"
                  enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition ease-in duration-150"
                  leave-from-class="opacity-100 scale-100"
                  leave-to-class="opacity-0 scale-95"
                >
                  <div
                    v-if="openMenuId === item.id"
                    class="absolute left-0 mt-2 min-w-52 max-w-[calc(100vw-2rem)] origin-top-left rounded-2xl border border-gray-200 bg-white py-1.5 shadow-xl dark:border-surface-800 dark:bg-surface-900 z-50"
                  >
                    <a
                      v-for="child in item.children"
                      :key="child.id"
                      :href="child.final_url"
                      :target="child.target || '_self'"
                      :rel="child.target === '_blank' ? 'noopener noreferrer' : undefined"
                      class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-primary-600 dark:text-gray-300 dark:hover:bg-surface-850 dark:hover:text-primary-400"
                    >
                      <i v-if="child.icon" :class="child.icon" class="text-base"></i>
                      <span class="truncate">{{ child.label }}</span>
                    </a>
                  </div>
                </Transition>
              </div>
            </template>
        </div>

        <!-- Search, Actions & User Profile -->
        <div class="flex items-center gap-4 shrink-0">
          <!-- Desktop search. On the homepage it stays out of the way until the hero's own
               search has scrolled off, then fades in to take over. -->
          <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
          >
          <form v-if="showHeaderSearch" @submit.prevent="handleHeaderSearch" class="relative hidden sm:block">
            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="t('How can we help you?')"
              class="w-52 focus:w-56 pl-9 pr-4 py-1.5 text-sm rounded-xl border border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-300"
            />
          </form>
          </Transition>

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
                  <div class="flex items-center gap-2.5 px-4 py-2.5 border-b border-gray-100 dark:border-surface-850">
                    <div class="w-9 h-9 rounded-full overflow-hidden bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300 flex items-center justify-center font-bold text-sm border border-primary-200/10 shrink-0">
                      <img v-if="userAvatar" :src="userAvatar" :alt="user.name" class="w-full h-full object-cover" />
                      <span v-else>{{ user.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                      <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ user.email }}</p>
                    </div>
                  </div>

                  <a
                    v-for="link in userMenuLinks"
                    :key="link.href"
                    :href="link.href"
                    class="flex items-center gap-2 px-4 py-2 text-sm transition-colors hover:bg-gray-50 dark:hover:bg-surface-850"
                    :class="link.tone === 'success'
                      ? 'text-emerald-600 dark:text-emerald-400 font-semibold'
                      : 'text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400'"
                  >
                    <i :class="link.icon" class="text-base"></i>
                    <span>{{ link.label }}</span>
                  </a>

                  <a v-if="isAdmin" href="/admin" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-surface-850 transition-colors">
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

      <!-- Mobile search — same rule as the desktop one above. -->
      <div v-if="showHeaderSearch" class="sm:hidden px-4 pb-3">
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

    <!-- Renders nothing unless the addon is active and this visitor is allowed it. -->
    <AiAssistantLoader />
  </div>
</template>
