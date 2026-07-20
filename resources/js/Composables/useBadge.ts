export function badgeClass(value: string): string {
    const map: Record<string, string> = {
        open: 'bg-blue-100 text-blue-700',
        in_progress: 'bg-violet-100 text-violet-700',
        waiting_user: 'bg-amber-100 text-amber-700',
        resolved: 'bg-primary-100 text-primary-700',
        closed: 'bg-gray-100 text-gray-600',
        low: 'bg-gray-100 text-gray-600',
        medium: 'bg-blue-100 text-blue-700',
        high: 'bg-amber-100 text-amber-700',
        urgent: 'bg-red-100 text-red-700',
    }
    return map[value] ?? 'bg-gray-100 text-gray-600'
}
