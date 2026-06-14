<?php

namespace Addons\AiAssistant\Services;

use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AiAssistantService
{
    public function __construct(
        private AiService $ai,
    ) {}

    // ─── message automation ──────────────────────────────

    public function findMatchingAutomationRule(string $message): ?\Addons\AiAssistant\Models\AiAssistantRule
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ai_assistant_rules')) {
            return null;
        }

        $rules = \Addons\AiAssistant\Models\AiAssistantRule::where('is_active', true)->get();
        $messageLower = mb_strtolower(trim($message));

        foreach ($rules as $rule) {
            $triggerLower = mb_strtolower(trim($rule->trigger));
            if ($rule->match_type === 'exact') {
                if ($messageLower === $triggerLower) {
                    return $rule;
                }
            } elseif ($rule->match_type === 'contains') {
                if (mb_strpos($messageLower, $triggerLower) !== false) {
                    return $rule;
                }
            }
        }

        return null;
    }

    // ─── system prompts ────────────────────────────────────

    public function buildFrontendSystemPrompt(?User $user, string $page): string
    {
        $base = addon_setting('ai-assistant', 'system_prompt_frontend')
            ?: 'You are a helpful AI assistant. Reply concisely and accurately. You are embedded in the {site_name} platform. Current page: {current_page}';

        $appName = settings('site_name', 'MakeAI');
        
        $replacements = [
            '{site_name}' => $appName,
            '{current_page}' => $page ?: 'unknown',
            '{user_name}' => $user ? $user->name : 'Guest',
            '{user_email}' => $user ? $user->email : 'Guest Email',
        ];

        $base = str_replace(array_keys($replacements), array_values($replacements), $base);

        $base .= "\n\nYou are embedded in the {$appName} platform.";
        $base .= "\nCurrent page: " . ($page ?: 'unknown');

        if ($user) {
            $plan = $user->plan;
            $base .= "\nUser plan: " . ($plan?->name ?? 'Free');
            $base .= "\nCredits remaining: " . ($user->credits ?? 0);
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

    // ─── visibility / limits ──────────────────────────────

    public function isVisibleForUser(?User $user, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return (bool) addon_setting('ai-assistant', 'admin_enabled', true);
        }

        if (! (bool) addon_setting('ai-assistant', 'enabled', false)) {
            return false;
        }

        $showTo = addon_setting('ai-assistant', 'show_to', 'all');

        return match ($showTo) {
            'logged_in' => $user !== null,
            'pro_only' => $user !== null && isProAvailable(),
            default => true,
        };
    }

    public function checkDailyLimit(?User $user, string $sessionId): bool
    {
        $limit = (int) addon_setting('ai-assistant', 'daily_message_limit', 20);
        if ($limit === 0) return true;

        $key = $user
            ? "addon_ai_assistant.limit.user.{$user->id}." . today()->toDateString()
            : "addon_ai_assistant.limit.session.{$sessionId}." . today()->toDateString();

        try {
            $count = (int) Redis::get($key);
            return $count < $limit;
        } catch (\Throwable) {
            return true; // allow on Redis failure
        }
    }

    public function incrementDailyCount(?User $user, string $sessionId): void
    {
        $limit = (int) addon_setting('ai-assistant', 'daily_message_limit', 20);
        if ($limit === 0) return;

        $key = $user
            ? "addon_ai_assistant.limit.user.{$user->id}." . today()->toDateString()
            : "addon_ai_assistant.limit.session.{$sessionId}." . today()->toDateString();

        try {
            Redis::incr($key);
            Redis::expire($key, 86400);
        } catch (\Throwable) {
            // silent
        }
    }

    public function interpolateVariables(string $text, \Illuminate\Contracts\Auth\Authenticatable|null $user, string $page): string
    {
        $appName = settings('site_name', 'MakeAI');
        
        $replacements = [
            '{site_name}' => $appName,
            '{current_page}' => $page ?: 'unknown',
            '{user_name}' => $user ? $user->name : 'Guest',
            '{user_email}' => $user ? $user->email : 'Guest Email',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
