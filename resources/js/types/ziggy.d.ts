interface ZiggyRouter {
    current(name?: string, params?: unknown): boolean
    has(name: string): boolean
    params: Record<string, string>
}

interface RouteFunction {
    (): ZiggyRouter
    (name: string, params?: unknown, absolute?: boolean): string
    current(name?: string, params?: unknown): boolean
    has(name: string): boolean
    params: Record<string, string>
}

declare const route: RouteFunction
