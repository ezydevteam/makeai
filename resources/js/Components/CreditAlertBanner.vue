<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'

const page = usePage()
const dismissed = ref(false)

const user = computed(() => page.props.auth?.user as any)
const threshold = computed(() => (page.props.creditAlertThreshold as number) ?? 100)
const isPro = computed(() => !!page.props.isProAvailable)

const showAlert = computed(() => {
  if (!isPro.value) return false
  if (dismissed.value) return false
  if (!user.value) return false
  if (user.value.credits > threshold.value) return false

  const prefs = user.value.preferences
  if (prefs?.credit_alert_dismissed_at) {
    const dismissedAt = new Date(prefs.credit_alert_dismissed_at).getTime()
    const now = Date.now()
    if (now - dismissedAt < 24 * 60 * 60 * 1000) return false
  }

  return true
})

function dismissAlert() {
  dismissed.value = true
  router.put(route('user.dashboard.profile.update'), { preferences: { credit_alert_dismissed_at: new Date().toISOString() } }, {
    preserveState: true,
    preserveScroll: true,
  })
}
</script>

<template>
  <div
    v-if="showAlert"
    class="mx-auto mb-4 max-w-7xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950"
  >
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 text-sm">
        <span class="text-amber-600 dark:text-amber-400">⚡</span>
        <span class="text-amber-800 dark:text-amber-200">
          Your credits are running low — <strong>{{ user?.credits }}</strong> remaining.
        </span>
        <Link
          :href="route('pricing')"
          class="ml-1 font-semibold text-amber-700 underline hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100"
        >
          Top Up →
        </Link>
      </div>
      <button
        @click="dismissAlert"
        class="shrink-0 text-amber-500 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-200"
        title="Dismiss"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
  </div>
</template>
