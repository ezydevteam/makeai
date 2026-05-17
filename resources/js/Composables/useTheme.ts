import { useDark, useToggle } from '@vueuse/core'
import { usePage } from '@inertiajs/vue3'

const isDark = useDark({
    selector: 'html',
    attribute: 'class',
    valueDark: 'dark',
    valueLight: '',
})

const toggleDark = useToggle(isDark)

export function useTheme() {
    const page = usePage()

    const toggle = async () => {
        toggleDark()
        
        // Only sync with backend if the user is authenticated
        // This prevents 401 Unauthorized errors for guests on the home page
        if (page.props.auth && (page.props.auth as any).user) {
            try {
                await fetch('/profile/theme', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        theme: isDark.value ? 'dark' : 'light'
                    })
                })
            } catch (error) {
                console.error('Failed to sync theme preference:', error)
            }
        }
    }

    return { isDark, toggleDark: toggle }
}
