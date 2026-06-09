import { computed, ref, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useHeaderHeight() {
    const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024)
    const isMobile = computed(() => windowWidth.value < 768)

    const rawConfig = computed(() => (usePage().props.headerConfig as any) ?? {})

    const topConfig = computed(() => rawConfig.value?.top)
    const mainConfig = computed(() => rawConfig.value?.main ?? rawConfig.value)
    const mobileConfig = computed(() => rawConfig.value?.mobile)
    const mobileBottomConfig = computed(() => rawConfig.value?.mobile_bottom)

    const headerHeight = computed(() => {
        if (isMobile.value) {
            const mobileH = mobileConfig.value?.enabled ? (Number(mobileConfig.value?.height) || 64) : 0
            const bottomH = mobileBottomConfig.value?.enabled ? (Number(mobileBottomConfig.value?.height) || 64) : 0
            return mobileH + bottomH
        }

        const topH = topConfig.value?.enabled ? (Number(topConfig.value?.height) || 40) : 0
        const mainH = mainConfig.value?.enabled ? (Number(mainConfig.value?.height) || 72) : 0
        return topH + mainH
    })

    const topOffset = computed(() => {
        return `${headerHeight.value}px`
    })

    // Debounced resize handler
    let resizeTimer: ReturnType<typeof setTimeout>
    const onResize = () => {
        clearTimeout(resizeTimer)
        resizeTimer = setTimeout(() => {
            windowWidth.value = window.innerWidth
        }, 100)
    }

    onMounted(() => window.addEventListener('resize', onResize))
    onUnmounted(() => window.removeEventListener('resize', onResize))

    return { headerHeight, topOffset }
}
