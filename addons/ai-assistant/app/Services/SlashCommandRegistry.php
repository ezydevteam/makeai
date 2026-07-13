<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Support\KnowledgeBase;
use Addons\AiAssistant\Support\ProductDocs;
use App\Models\User;

/**
 * Slash commands for the assistant.
 *
 * The manifest has advertised "admin slash commands" since 1.0.0 with no implementing
 * code behind it. This is that code.
 *
 * Two scopes: SCOPE_USER (frontend widget) and SCOPE_ADMIN (admin panel widget). A
 * command declares which scopes it belongs to, and the controller passes the scope of
 * the authenticated surface — so an admin-only command cannot be invoked by POSTing to
 * the frontend endpoint. Scope is enforced server-side; the client list is only a menu.
 *
 * Commands are either DIRECT (answer from local data — no provider call, no credits) or
 * GROUNDING (retrieve context, then let the model answer). Only grounding commands spend.
 */
class SlashCommandRegistry
{
    public const SCOPE_USER = 'user';
    public const SCOPE_ADMIN = 'admin';

    public function __construct(
        private AiAssistantService $assistant,
    ) {}

    /**
     * Commands the given scope may run, shaped for the client's autocomplete menu.
     * `client_only` commands are handled entirely in the browser and never POSTed.
     */
    public function enabled(): bool
    {
        return (bool) addon_setting('ai-assistant', 'enable_slash_commands', true);
    }

    public function list(string $scope): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $all = [
            [
                'name' => 'help',
                'usage' => '/help',
                'description' => 'List the available commands',
                'scopes' => [self::SCOPE_USER, self::SCOPE_ADMIN],
                'client_only' => false,
            ],
            [
                'name' => 'clear',
                'usage' => '/clear',
                'description' => 'Clear this conversation',
                'scopes' => [self::SCOPE_USER, self::SCOPE_ADMIN],
                'client_only' => true,
            ],
            [
                'name' => 'usage',
                'usage' => '/usage',
                'description' => 'How many messages you have left today',
                'scopes' => [self::SCOPE_USER],
                'client_only' => false,
            ],
            [
                'name' => 'credits',
                'usage' => '/credits',
                'description' => credit_quota_mode() ? 'Show your daily AI allowance' : 'Show your credit balance',
                'scopes' => [self::SCOPE_USER],
                'client_only' => false,
            ],
            [
                'name' => 'plan',
                'usage' => '/plan',
                'description' => 'Show your current plan',
                'scopes' => [self::SCOPE_USER],
                'client_only' => false,
            ],
            [
                'name' => 'docs',
                'usage' => '/docs <question>',
                // Same command, different corpus per surface: an admin asking /docs wants the
                // MakeAI manual, a visitor wants the site's own help centre. See docs().
                'description' => $scope === self::SCOPE_ADMIN
                    ? 'Answer from the MakeAI documentation, with sources'
                    : 'Answer from the knowledge base, with sources',
                'scopes' => [self::SCOPE_USER, self::SCOPE_ADMIN],
                'client_only' => false,
            ],
            [
                'name' => 'stats',
                'usage' => '/stats',
                'description' => 'Site statistics at a glance',
                'scopes' => [self::SCOPE_ADMIN],
                'client_only' => false,
            ],
            [
                'name' => 'health',
                'usage' => '/health',
                'description' => 'Cron, AI budget and license status',
                'scopes' => [self::SCOPE_ADMIN],
                'client_only' => false,
            ],
            [
                'name' => 'tickets',
                'usage' => '/tickets',
                'description' => 'Open support tickets',
                'scopes' => [self::SCOPE_ADMIN],
                'client_only' => false,
            ],
        ];

        $visible = array_values(array_filter(
            $all,
            fn (array $c) => in_array($scope, $c['scopes'], true) && $this->isCommandAvailable($c['name'], $scope)
        ));

        return array_map(fn (array $c) => [
            'name' => $c['name'],
            'usage' => $c['usage'],
            'description' => $c['description'],
            'client_only' => $c['client_only'],
        ], $visible);
    }

    /**
     * Commands whose backing feature may not be installed are hidden rather than
     * offered-then-failed.
     *
     * /docs is scope-dependent: in the admin panel it is backed by the bundled MakeAI
     * documentation, which ships with the release and needs no addon, so it is effectively
     * always available. On the public surface it is backed by the buyer's Knowledge Base
     * addon, which frequently is not installed — and there it stays hidden.
     */
    private function isCommandAvailable(string $name, string $scope): bool
    {
        return match ($name) {
            'docs' => $scope === self::SCOPE_ADMIN
                ? ProductDocs::available()
                : KnowledgeBase::available(),
            default => true,
        };
    }

    /**
     * Split "/docs how do I cancel" into ['docs', 'how do I cancel'].
     * Returns null when the message is not a slash command at all.
     */
    public function parse(string $message): ?array
    {
        $trimmed = ltrim($message);

        if (! str_starts_with($trimmed, '/')) {
            return null;
        }

        // A lone "/" or a path-looking string ("/admin/users") is not a command.
        if (! preg_match('#^/([a-z][a-z0-9_-]*)(?:\s+(.*))?$#is', $trimmed, $m)) {
            return null;
        }

        return [
            'name' => strtolower($m[1]),
            'args' => trim($m[2] ?? ''),
        ];
    }

    /**
     * Execute a command. Returns null when the command is unknown or out of scope for
     * this surface — the caller then treats the message as ordinary chat, so an
     * out-of-scope command leaks nothing about its existence.
     */
    public function handle(string $name, string $args, string $scope, ?User $user): ?SlashCommandResult
    {
        $permitted = collect($this->list($scope))->pluck('name')->all();

        if (! in_array($name, $permitted, true)) {
            return null;
        }

        return match ($name) {
            'help' => SlashCommandResult::reply($this->help($scope)),
            'clear' => SlashCommandResult::reply('Conversation cleared.'),
            'usage' => SlashCommandResult::reply($this->usage($user)),
            'credits' => SlashCommandResult::reply($this->credits($user)),
            'plan' => SlashCommandResult::reply($this->plan($user)),
            'docs' => $this->docs($args, $scope),
            'stats' => SlashCommandResult::reply($this->stats()),
            'health' => SlashCommandResult::reply($this->health()),
            'tickets' => SlashCommandResult::reply($this->tickets()),
            default => null,
        };
    }

    // ─── command bodies ──────────────────────────────────────

    private function help(string $scope): string
    {
        $lines = ['**Available commands**', ''];

        foreach ($this->list($scope) as $cmd) {
            $lines[] = "- `{$cmd['usage']}` — {$cmd['description']}";
        }

        $lines[] = '';
        $lines[] = 'Anything else you type is answered by the assistant as usual.';

        return implode("\n", $lines);
    }

    private function usage(?User $user): string
    {
        $limit = $this->assistant->dailyLimitFor($user);

        if ($limit <= 0) {
            return 'You have **unlimited** assistant messages today.';
        }

        $remaining = $this->assistant->remainingToday($user);

        return "You have **{$remaining}** of {$limit} assistant messages left today.";
    }

    /**
     * Mode-aware, and deliberately so. In METERED mode (Extended license + subscriptions)
     * credits are a wallet the user can top up, so a balance is the honest answer. In
     * QUOTA mode they are a resetting allowance with no top-up path — quoting a "balance"
     * there would invite the user to go looking for a buy button that does not exist.
     */
    private function credits(?User $user): string
    {
        if (! $user) {
            return 'Sign in to see your usage.';
        }

        if (credit_quota_mode()) {
            $dailyLimit = (float) ($user->daily_limit ?? settings('user_daily_credit_limit', 0));

            if ($dailyLimit <= 0) {
                return 'This site runs on a **daily allowance** rather than purchasable credits, and no daily cap is set for your account.';
            }

            $used = (float) ($user->credits_used_today ?? 0);
            $left = max(0, $dailyLimit - $used);

            return "**{$left}** of {$dailyLimit} daily AI credits remaining. This allowance resets each day.";
        }

        return '**' . (float) ($user->credits ?? 0) . '** credits remaining in your balance.';
    }

    private function plan(?User $user): string
    {
        if (! $user) {
            return 'Sign in to see your plan.';
        }

        $plan = $user->plan?->name ?? 'Free';

        if (! isProAvailable()) {
            // No paid tier exists in this mode — claiming they're on "Free" and could
            // upgrade would be a dead end.
            return "You are on the **{$plan}** plan.";
        }

        if ($user->isPro()) {
            $until = $user->subscription_ends_at
                ? ' (renews ' . $user->subscription_ends_at->toFormattedDateString() . ')'
                : '';

            return "You are on the **{$plan}** plan{$until}.";
        }

        return "You are on the **{$plan}** plan. Upgrade for higher limits.";
    }

    /**
     * The same command over two different corpora, chosen by surface.
     *
     * In the admin panel /docs searches the MakeAI documentation bundled with the release —
     * the manual for the software the admin is operating. On the public site it searches the
     * buyer's Knowledge Base — the help centre they wrote for their own customers. Handing
     * an admin the customer help centre (which is what this used to do on both surfaces) is
     * not a smaller version of the right answer; it is the wrong corpus entirely.
     */
    private function docs(string $query, string $scope): SlashCommandResult
    {
        $isAdmin = $scope === self::SCOPE_ADMIN;

        if ($query === '') {
            return SlashCommandResult::reply($isAdmin
                ? 'Usage: `/docs <question>` — for example, `/docs how do I add a provider key`'
                : 'Usage: `/docs <question>` — for example, `/docs how do I cancel my subscription`');
        }

        $hit = $isAdmin
            ? ProductDocs::context($query, 5)
            : KnowledgeBase::context($query, 5);

        if (($hit['context'] ?? '') === '') {
            return SlashCommandResult::reply($isAdmin
                ? "I couldn't find anything in the MakeAI documentation about that."
                : "I couldn't find anything in the knowledge base about that.");
        }

        $label = $isAdmin ? 'MakeAI Documentation' : 'Knowledge Base';

        $systemAppend = "\n\n--- {$label} ---\n"
            . "Answer the user's question using ONLY the excerpts below. If they do not contain "
            . "the answer, say so plainly rather than guessing.\n\n"
            . $hit['context'];

        return SlashCommandResult::ground($systemAppend, $query, $hit['sources'] ?? []);
    }

    private function stats(): string
    {
        $c = $this->assistant->buildSiteContext();

        return implode("\n", [
            '**Site statistics**',
            '',
            "- Total users: **{$c['total_users']}**",
            "- Active subscriptions: **{$c['active_subscriptions']}**",
            "- AI calls today: **{$c['ai_calls_today']}**",
            "- Open support tickets: **{$c['open_tickets']}**",
        ]);
    }

    private function health(): string
    {
        $c = $this->assistant->buildSiteContext();

        $cron = $c['cron_running'] ? '✅ running' : '⚠️ not running (last run over 5 minutes ago)';

        $budget = \App\Services\AI\TokenGuard::globalBudgetExceeded()
            ? '⚠️ daily AI budget reached — generation is blocked'
            : '✅ within daily AI budget';

        $license = license_verified()
            ? '✅ verified (' . (is_extended_license() ? 'Extended' : 'Regular') . ')'
            : '⚠️ not verified';

        $mode = credit_quota_mode() ? 'quota (allowance)' : 'metered (wallet)';

        return implode("\n", [
            '**System health**',
            '',
            "- Cron: {$cron}",
            "- AI budget: {$budget}",
            "- License: {$license}",
            "- Credit mode: **{$mode}**",
        ]);
    }

    private function tickets(): string
    {
        $c = $this->assistant->buildSiteContext();
        $open = $c['open_tickets'];

        if ($open === 0) {
            return 'No open support tickets. 🎉';
        }

        return "There are **{$open}** open support tickets.";
    }
}
