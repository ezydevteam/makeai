export const COLOR_SCHEME_KEY = 'vueuse-color-scheme'

// Which `theme_default_mode` the stored scheme above was last aligned to.
const APPLIED_DEFAULT_KEY = 'theme-default-mode'

/**
 * Point the stored color scheme at the site's configured default mode.
 *
 * The default used to be seeded only when nothing was stored yet, which meant the
 * first toggle a visitor ever made outlived every later change to the setting: an
 * operator switching the site to a dark preset (Midnight) still served them light,
 * forever, with no way back short of clearing site data. Recording which default the
 * stored choice was aligned to fixes that — a *changed* default wins once, and the
 * visitor's own toggle wins again until it changes.
 *
 * A visitor carrying a stored choice from before this bookkeeping existed has it
 * realigned to the site default on their next load. That is deliberate: it is the one
 * pass that brings everyone onto the operator's current preset, and `theme_allow_user_toggle`
 * hands the choice straight back.
 */
export function syncDefaultColorScheme(mode: unknown): void {
    if (mode !== 'dark' && mode !== 'light') return

    const stored = localStorage.getItem(COLOR_SCHEME_KEY)
    if (stored !== null && localStorage.getItem(APPLIED_DEFAULT_KEY) === mode) return

    // useDark() is configured with valueLight: '', so light is the empty string.
    const value = mode === 'dark' ? 'dark' : ''
    localStorage.setItem(COLOR_SCHEME_KEY, value)
    localStorage.setItem(APPLIED_DEFAULT_KEY, mode)

    // localStorage writes never fire `storage` in the document that made them, so an
    // already-mounted useDark() would keep painting the old scheme until a full reload
    // — which is exactly the case when this runs on an Inertia navigation. Re-dispatch
    // it ourselves; useDark's underlying useStorage listens for nothing else.
    window.dispatchEvent(new StorageEvent('storage', {
        key: COLOR_SCHEME_KEY,
        newValue: value,
        storageArea: localStorage,
    }))
}
