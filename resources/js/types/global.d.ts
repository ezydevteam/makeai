/// <reference types="vite/client" />

import type { SharedPageProps } from './index'

declare module '*.vue' {
    import type { DefineComponent } from 'vue'
    const component: DefineComponent<{}, {}, any>
    export default component
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string
}

interface ImportMeta {
    readonly env: ImportMetaEnv
    readonly glob: <T>(pattern: string, options?: { eager?: boolean }) => Record<string, T>
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: RouteFunction
        $t: (key: string, replace?: Record<string, string | number>) => string
        $page: {
            props: SharedPageProps
        }
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedPageProps {}
}

export {}
