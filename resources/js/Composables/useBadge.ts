export function badgeClass(value: string): string {
    const map: Record<string, string> = {
        open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        in_progress: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
        waiting_user: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        resolved: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
        closed: 'bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-300',
        low: 'bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-300',
        medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        high: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        urgent: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    }
    return map[value] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-300'
}
