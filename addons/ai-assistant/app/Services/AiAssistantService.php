<?php

namespace Addons\AiAssistant\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AiAssistantService
{
    // ─── message automation ──────────────────────────────

    public function findMatchingAutomationRule(string $message): ?\Addons\AiAssistant\Models\AiAssistantRule
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ai_assistant_rules')) {
            return null;
        }

        $rules = \Addons\AiAssistant\Models\AiAssistantRule::where('is_active', true)->get();
        $message = $this->normalizeForMatch($message);

        foreach ($rules as $rule) {
            $trigger = $this->normalizeForMatch($rule->trigger);
            if ($trigger === '') {
                continue;
            }

            $matched = match ($rule->match_type) {
                'exact' => $message === $trigger,
                'contains' => mb_strpos($message, $trigger) !== false,
                'fuzzy' => levenshtein($message, $trigger) <= 2,
                default => false,
            };

            if ($matched) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Normalise text before comparing a message to a rule trigger.
     *
     * Real-world triggers fail to match for reasons that have nothing to do with intent:
     * a phone/OS inserts a curly apostrophe (') where the admin typed a straight one ('),
     * double spaces creep in, or trailing punctuation differs. We lowercase, fold the
     * common "smart" quotes/dashes to their ASCII form, and collapse whitespace so
     * "What I'd pick up next?" matches "what I'd pick up next?" and "what  I'd pick up next".
     */
    private function normalizeForMatch(string $text): string
    {
        $text = mb_strtolower(trim($text));

        $text = strtr($text, [
            "\u{2019}" => "'", "\u{2018}" => "'",   // curly single quotes → '
            "\u{201C}" => '"', "\u{201D}" => '"',   // curly double quotes → "
            "\u{2013}" => '-', "\u{2014}" => '-',   // en/em dash → -
            "\u{00A0}" => ' ',                        // non-breaking space → space
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    // ─── system prompts ────────────────────────────────────

    public function buildFrontendSystemPrompt(?User $user, string $page): string
    {
        $base = addon_setting('ai-assistant', 'system_prompt_frontend')
            ?: 'You are a helpful AI assistant. Reply concisely and accurately. You are embedded in the {site_name} platform. Current page: {current_page}';

        $appName = settings('site_name', 'MakeAI');

        $base = $this->interpolateVariables($base, $user, $page);

        $base .= "\n\nYou are embedded in the {$appName} platform.";
        $base .= "\nCurrent page: " . ($page ?: 'unknown');

        if ($user) {
            $base .= "\nUser plan: " . ($user->plan?->name ?? 'Free');

            // Only surface a credit balance when credits are a spendable wallet. In quota
            // mode the number is a resetting allowance, not something the user can top up,
            // so quoting a "balance" at them would be misleading.
            if (! credit_quota_mode()) {
                $base .= "\nCredits remaining: " . ($user->credits ?? 0);
            }
        } else {
            $base .= "\nThe user is a guest (not signed in).";
        }

        return $base;
    }

    public function buildAdminSystemPrompt(): string
    {
        $base = addon_setting('ai-assistant', 'system_prompt_admin')
            ?: 'You are an expert admin assistant. Help manage the {site_name} platform efficiently.';

        $appName = settings('site_name', 'MakeAI');

        $base = str_replace('{site_name}', $appName, $base);

        $context = $this->buildSiteContext();

        $base .= "\n\n--- {$appName} Admin Context ---\n";
        $base .= "Total users: {$context['total_users']}\n";
        $base .= "Active subscriptions: {$context['active_subscriptions']}\n";
        $base .= "AI calls today: {$context['ai_calls_today']}\n";
        $base .= "Open support tickets: {$context['open_tickets']}\n";
        $base .= "Cron running: " . ($context['cron_running'] ? 'Yes' : 'No') . "\n";

        return $base;
    }

    public function buildSiteContext(): array
    {
        return Cache::remember('addon_ai_assistant.admin.site_context', 120, function () {
            return [
                'total_users' => $this->safeCount(\App\Models\User::class),
                'active_subscriptions' => $this->safeCount(\App\Models\Subscription::class, fn ($q) => $q->where('status', 'active')),
                'ai_calls_today' => $this->safeCount(\App\Models\UsageLog::class, fn ($q) => $q->whereDate('created_at', today())),
                'open_tickets' => $this->safeCount(\App\Models\SupportTicket::class, fn ($q) => $q->where('status', 'open')),
                'cron_running' => $this->checkCron(),
            ];
        });
    }

    private function safeCount(string $class, ?callable $filter = null): int
    {
        try {
            $q = $class::query();
            if ($filter) $filter($q);
            return $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function checkCron(): bool
    {
        $lastRun = settings('last_scheduler_run');
        if (! $lastRun) return false;
        try {
            return \Illuminate\Support\Carbon::parse($lastRun)->greaterThan(now()->subMinutes(5));
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── visibility ──────────────────────────────────────────

    /**
     * Whether the assistant should be offered to this visitor at all.
     *
     * This is the single source of truth for the admin buyer's access decision, and it is
     * consulted in BOTH places that matter: the Inertia share (so the widget never even
     * renders) and every chat request (so a hand-crafted POST can't bypass the widget).
     * They must not drift apart — a client-only gate is decoration, not access control.
     */
    public function isVisibleForUser(?User $user, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return (bool) addon_setting('ai-assistant', 'admin_enabled', true);
        }

        if (! (bool) addon_setting('ai-assistant', 'enabled', false)) {
            return false;
        }

        return match (addon_setting('ai-assistant', 'show_to', 'all')) {
            'logged_in' => $user !== null,
            'pro_only' => $user !== null && $this->isProUser($user),
            default => true, // 'all' — guests included
        };
    }

    /**
     * Is this user "pro" for gating purposes?
     *
     * The previous implementation called isProAvailable(), which is a GLOBAL INSTALL FLAG
     * (Extended license + subscriptions enabled) — not a property of the user. Every free
     * user passed the "pro only" gate on any Extended install.
     *
     * The correct check is User::isPro(), which honours active subscriptions, lifetime
     * plans and admin-granted plans. But it is only *meaningful* when the install can
     * actually sell plans: on a Regular license (or Extended with subscriptions off) there
     * is no paid tier to belong to, so 'pro_only' would hide the assistant from literally
     * everyone. In that mode we degrade it to "any signed-in user" — the closest honest
     * reading of the admin's intent. The admin UI hides the option there anyway; this is
     * the safety net for an install that changes license tier after the fact.
     */
    private function isProUser(User $user): bool
    {
        if (! isProAvailable()) {
            return true; // no paid tier exists in this mode; 'pro_only' collapses to 'logged_in'
        }

        return $user->isPro();
    }

    // ─── daily message limit ─────────────────────────────────

    /**
     * The per-day message allowance for this visitor.
     *
     * Three tiers, all admin-configurable, so the buyer controls access in either credit
     * mode. The pro tier is only consulted when the install can actually sell plans.
     * 0 means unlimited.
     */
    public function dailyLimitFor(?User $user): int
    {
        if (! $user) {
            return (int) addon_setting('ai-assistant', 'guest_daily_message_limit', 5);
        }

        if (isProAvailable() && $user->isPro()) {
            return (int) addon_setting('ai-assistant', 'pro_daily_message_limit', 0);
        }

        return (int) addon_setting('ai-assistant', 'daily_message_limit', 20);
    }

    public function checkDailyLimit(?User $user, string $sessionId): bool
    {
        $limit = $this->dailyLimitFor($user);
        if ($limit <= 0) {
            return true; // unlimited
        }

        try {
            return (int) Cache::get($this->limitKey($user), 0) < $limit;
        } catch (\Throwable) {
            // Fail CLOSED. The old code failed open on a cache error, which on any install
            // without the expected cache backend turned this into an uncapped AI endpoint.
            // Refusing a message is recoverable; an unmetered spend surface is not.
            return false;
        }
    }

    public function incrementDailyCount(?User $user, string $sessionId): void
    {
        if ($this->dailyLimitFor($user) <= 0) {
            return;
        }

        try {
            $key = $this->limitKey($user);
            // add() seeds the counter and establishes the TTL only if absent; increment()
            // thereafter. Doing it this way keeps the expiry anchored to the first message
            // of the day rather than sliding forward on every call.
            Cache::add($key, 0, now()->endOfDay());
            Cache::increment($key);
        } catch (\Throwable) {
            // Counting is best-effort; checkDailyLimit() already fails closed if the cache
            // is unreachable, so a lost increment cannot open the gate.
        }
    }

    /**
     * Uses the Cache facade rather than the Redis facade so the limit works on whatever
     * backend the install actually runs (this codebase ships CACHE_STORE=database).
     *
     * Guests are keyed by IP, never by the client-supplied session id: a spoofed session
     * id would otherwise mint a fresh quota on every single request, making the guest
     * limit trivially bypassable.
     */
    private function limitKey(?User $user): string
    {
        $day = today()->toDateString();

        if ($user) {
            return "addon_ai_assistant.limit.user.{$user->id}.{$day}";
        }

        $ip = request()?->ip() ?? 'unknown';

        return 'addon_ai_assistant.limit.ip.' . sha1($ip) . ".{$day}";
    }

    public function remainingToday(?User $user): ?int
    {
        $limit = $this->dailyLimitFor($user);
        if ($limit <= 0) {
            return null; // unlimited
        }

        try {
            return max(0, $limit - (int) Cache::get($this->limitKey($user), 0));
        } catch (\Throwable) {
            return 0;
        }
    }

    // ─── helpers ─────────────────────────────────────────────

    public function interpolateVariables(string $text, \Illuminate\Contracts\Auth\Authenticatable|null $user, string $page): string
    {
        $replacements = [
            '{site_name}' => settings('site_name', 'MakeAI'),
            '{current_page}' => $page ?: 'unknown',
            '{user_name}' => $user->name ?? 'Guest',
            '{user_email}' => $user->email ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
