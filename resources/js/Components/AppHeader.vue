<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { useTheme } from '@/Composables/useTheme'

const { isDark, toggleDark } = useTheme()
const page = usePage()

const user = computed(() => page.props.auth?.user as any)
const headerConfig = computed(() => page.props.headerConfig as any)
const activeBlocks = computed(() => {
    return headerConfig.value?.blocks?.filter((b: any) => b.enabled) || []
})

const profileOpen = ref(false)
const mobileMenuOpen = ref(false)

const logout = () => router.post(route('logout'))

const globalMenus = computed(() => page.props.globalMenus as Array<any> || [])

const getMenu = (slug: string) => {
    return globalMenus.value.find((m: any) => m.slug === slug)
}

const isActive = (url: string) => {
    // Basic match
    if (!url) return false;
    const path = new URL(url, window.location.origin).pathname;
    return window.location.pathname === path;
}

const close = () => { profileOpen.value = false }
onMounted(() => document.addEventListener('click', close))
onUnmounted(() => document.removeEventListener('click', close))
</script>

<template>
    <header :class="[
        headerConfig?.sticky ? 'sticky top-0 z-50' : 'relative z-50',
        headerConfig?.transparent_homepage && route().current('home') ? 'absolute w-full bg-transparent border-none' : 'bg-white/90 dark:bg-surface-900/80 backdrop-blur-md border-b border-gray-200 dark:border-white/5',
    ]" :style="{ height: headerConfig?.height ? headerConfig.height + 'px' : '72px' }" class="shrink-0 transition-all duration-300 w-full">
        <div class="h-full max-w-7xl mx-auto px-4 sm:px-6 flex items-center gap-3 justify-start">
            
            <div v-for="block in activeBlocks" :key="block.id" 
                 :class="[
                    block.type === 'navigation' && headerConfig?.layout !== 'centered' ? 'flex-1 flex justify-center' : '',
                    block.type === 'navigation' && headerConfig?.layout === 'centered' ? 'w-full flex justify-center mt-2' : '',
                    block.type === 'logo' && headerConfig?.layout === 'classic' && !activeBlocks.find((b: any) => b.type === 'navigation') ? 'flex-1' : ''
                 ]" class="flex items-center">
                
                <!-- LOGO -->
                <Link v-if="block.type === 'logo'" :href="block.config.link || '/'" class="flex items-center gap-2.5 group">
                    <div class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-xl flex items-center justify-center shadow-lg shadow-primary-500/20 shrink-0 group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                    </div>
                    <span v-if="block.config.show_text" class="text-lg font-bold text-gray-900 dark:text-white tracking-tight hidden sm:block whitespace-nowrap">{{ block.config.text || 'MakeAI' }}</span>
                </Link>

                <!-- NAVIGATION -->
                <nav v-else-if="block.type === 'navigation'" class="hidden md:flex items-center gap-1">
                    <template v-if="getMenu(block.config.menu_slug)">
                        <a v-for="item in getMenu(block.config.menu_slug).items" :key="item.id" :href="item.url" :target="item.target" :class="[isActive(item.url) ? 'text-primary-600 dark:text-primary-400 bg-primary-500/10' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5']" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap">
                            {{ item.title }}
                        </a>
                    </template>
                </nav>

                <!-- SEARCH BAR -->
                <div v-else-if="block.type === 'search'" class="hidden md:block relative">
                    <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    <input type="text" placeholder="Search..." class="bg-gray-100 dark:bg-surface-800 border-none rounded-lg text-sm pl-9 pr-4 py-2 w-48 focus:w-64 transition-all duration-300 dark:text-white focus:ring-2 focus:ring-primary-500">
                </div>

                <!-- DARK MODE TOGGLE -->
                <button v-else-if="block.type === 'dark_mode'" @click="toggleDark()" class="w-10 h-10 flex items-center justify-center rounded-xl bg-gray-50 dark:bg-surface-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-surface-700 transition-all shrink-0">
                    <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M14 12a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>

                <!-- CTA BUTTON -->
                <Link v-else-if="block.type === 'cta_button'" :href="block.config.link" class="px-5 py-2 rounded-xl text-sm font-bold transition-all whitespace-nowrap shrink-0" :class="[
                    block.config.style === 'filled' ? 'bg-primary-600 text-white hover:bg-primary-500 shadow-lg shadow-primary-600/20' : '',
                    block.config.style === 'outline' ? 'border-2 border-primary-600 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : '',
                    block.config.style === 'ghost' ? 'text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20' : ''
                ]">
                    {{ block.config.text }}
                </Link>

                <!-- USER MENU -->
                <template v-else-if="block.type === 'user_menu'">
                    <div v-if="user" class="relative flex items-center" @click.stop>
                        <div v-if="block.config.show_credits" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-primary-500/10 border border-primary-500/20 rounded-lg mr-2">
                            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                            <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">{{ user.credits ?? 0 }}</span>
                        </div>

                        <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">
                            <div v-if="block.config.show_avatar" class="w-8 h-8 bg-gradient-to-br from-primary-500 to-accent-500 rounded-lg flex items-center justify-center text-white text-sm font-bold shrink-0">
                                {{ user.name?.charAt(0) ?? 'U' }}
                            </div>
                            <svg class="w-4 h-4 text-gray-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>

                        <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="profileOpen" class="absolute right-0 top-full mt-2 w-56 bg-white dark:bg-surface-800 border border-gray-200 dark:border-white/10 rounded-xl shadow-2xl py-1.5 z-50">
                                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-white/5">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                                </div>
                                <div class="px-4 py-2 border-b border-gray-100 dark:border-white/5 sm:hidden" v-if="block.config.show_credits">
                                    <span class="text-xs text-primary-600 dark:text-primary-400 font-semibold">⚡ {{ user.credits ?? 0 }} credits</span>
                                </div>
                                <Link :href="route('user.dashboard')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                    Dashboard
                                </Link>
                                <button @click="logout" class="w-full text-left px-4 py-2.5 text-sm text-danger-500 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" /></svg>
                                    Sign Out
                                </button>
                            </div>
                        </Transition>
                    </div>
                    <div v-else class="flex items-center gap-3">
                        <Link href="/login" class="text-sm font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors px-4 py-2">
                            Sign In
                        </Link>
                        <Link href="/register" class="bg-primary-600 hover:bg-primary-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg shadow-primary-600/20 whitespace-nowrap">
                            Get Started
                        </Link>
                    </div>
                </template>

            </div>
        </div>
    </header>
</template>
