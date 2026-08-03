import { useDark, useToggle } from '@vueuse/core'
import { usePage } from '@inertiajs/vue3'
import { syncDefaultColorScheme } from '../lib/colorScheme'

export function useTheme() {
    const page = usePage()

    // Align the stored scheme with theme_default_mode BEFORE useDark initializes, so the
    // server-configured default (not the system preference) is what it reads. app.ts does
    // the same on load and on every navigation; this covers a component reaching for the
    // theme first. Both routes are idempotent.
    const settings = (page.props.appearanceThemeSettings as Record<string, string>) || {}
    syncDefaultColorScheme(settings.theme_default_mode)

    const isDark = useDark({
        selector: 'html',
        attribute: 'class',
        valueDark: 'dark',
        valueLight: '',
    })

    const toggleDark = useToggle(isDark)

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
