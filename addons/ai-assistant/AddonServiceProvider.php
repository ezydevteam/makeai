<?php

namespace Addons\AiAssistant;

use Addons\AiAssistant\Services\AiAssistantService;
use Addons\AiAssistant\Services\SlashCommandRegistry;
use App\Models\AiKey;
use App\Models\AiModel;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiAssistantService::class);
        $this->app->singleton(SlashCommandRegistry::class);
        $this->app->singleton(\Addons\AiAssistant\Services\ConversationStore::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        /*
         | Share safe config to Inertia — NEVER expose system_prompt_* or custom_api_key.
         |
         | This returns NULL whenever the assistant must not be offered to the current
         | visitor, which is what actually enforces the admin buyer's access decision on
         | the client: the loader renders nothing without this prop. Previously the prop
         | was shared unconditionally and no component ever read `enabled` or `show_to`,
         | so the bubble appeared for guests and for a disabled addon alike — then 401'd
         | into "Something went wrong" when clicked.
         |
         | The server-side gate in the controller is the real one; this just stops us
         | painting a widget the visitor isn't allowed to use.
         */
        Inertia::share('aiAssistant', function () {
            if (! is_addon_active('ai-assistant')) {
                return null;
            }

            $service = app(AiAssistantService::class);

            // The admin panel and the public site are separate surfaces with separate
            // toggles (admin_enabled vs enabled). Key off the request path rather than the
            // admin guard, so an admin browsing the public site still sees the public
            // widget under the public rules.
            $isAdminArea = request()->is('admin') || request()->is('admin/*');
            $user = $isAdminArea ? null : request()->user();

            if (! $service->isVisibleForUser($user, $isAdminArea)) {
                return null;
            }

            // Page-level exclusions apply to the public site only — the admin panel has its
            // own on/off switch. Returning null here means the widget isn't rendered at all,
            // rather than being drawn and then hidden client-side.
            if (! $isAdminArea && $this->isExcludedPage()) {
                return null;
            }

            $avatar = $this->avatarUrl(addon_setting('ai-assistant', 'avatar_url'));

            $scope = $isAdminArea
                ? SlashCommandRegistry::SCOPE_ADMIN
                : SlashCommandRegistry::SCOPE_USER;

            $apiBase = $isAdminArea ? '/api/admin/assistant' : '/api/assistant';

            // The Help tab needs the KB addon; the admin surface has no Help/Message panels.
            $kbInstalled = \Addons\AiAssistant\Support\KnowledgeBase::installed();
            $panels = [
                'home' => ! $isAdminArea && (bool) addon_setting('ai-assistant', 'enable_home', true),
                'chat' => true,
                'help' => ! $isAdminArea && $kbInstalled && (bool) addon_setting('ai-assistant', 'enable_help', true),
                'message' => ! $isAdminArea && (bool) addon_setting('ai-assistant', 'enable_message', true),
            ];

            // Only non-empty channels are shared, so the client renders exactly what exists.
            $channels = array_filter([
                'whatsapp' => addon_setting('ai-assistant', 'social_whatsapp'),
                'telegram' => addon_setting('ai-assistant', 'social_telegram'),
                'facebook' => addon_setting('ai-assistant', 'social_facebook'),
                'instagram' => addon_setting('ai-assistant', 'social_instagram'),
                'website' => addon_setting('ai-assistant', 'social_website'),
            ]);

            return [
                'scope' => $scope,
                'endpoints' => [
                    'chat' => "{$apiBase}/chat",
                    'extract' => "{$apiBase}/extract",
                    'feedback' => "{$apiBase}/feedback",
                    'transcript' => "{$apiBase}/transcript",
                    'conversations' => "{$apiBase}/conversations",
                    // Help/message/csat live only on the public surface.
                    'help_articles' => '/api/assistant/help/articles',
                    'help_search' => '/api/assistant/help/search',
                    'help_article' => '/api/assistant/help/articles', // + /{slug}
                    'message' => '/api/assistant/message',
                    'csat' => "{$apiBase}/csat",
                ],
                'panels' => $panels,
                'channels' => (object) $channels,
                // Shared inline so the slash-command menu is available on first keystroke
                // without a round-trip.
                'commands' => app(SlashCommandRegistry::class)->list($scope),
                'is_guest' => $user === null && ! $isAdminArea,
                'position' => addon_setting('ai-assistant', 'position', 'bottom-right'),
                'accent_color' => addon_setting('ai-assistant', 'accent_color', '#1F75FE'),
                'allow_file_upload' => (bool) addon_setting('ai-assistant', 'allow_file_upload', false),
                'allowed_file_types' => addon_setting('ai-assistant', 'allowed_file_types', 'pdf,docx,txt,csv,png,jpg'),
                'auto_open' => (bool) addon_setting('ai-assistant', 'auto_open', false),
                'assistant_name' => addon_setting('ai-assistant', 'assistant_name', 'AI Assistant'),
                'avatar_url' => $avatar,
                'designation' => addon_setting('ai-assistant', 'designation', 'Your AI Helper'),
                'greeting_message' => addon_setting('ai-assistant', 'greeting_message'),
                // First name of a signed-in visitor, for a personalised greeting ("Hi Jane!").
                // Null for guests, who get the generic "Hi there!".
                'greeting_name' => $user
                    ? (\Illuminate\Support\Str::of((string) $user->name)->trim()->explode(' ')->first() ?: null)
                    : null,
                'enable_emoji' => (bool) addon_setting('ai-assistant', 'enable_emoji', true),
                'enable_csat' => (bool) addon_setting('ai-assistant', 'enable_csat', true),
                'legal' => $this->legalLinks($isAdminArea),
            ];
        });

        /*
         | Meta for the admin settings screen. Lets the UI hide choices that cannot work on
         | this install rather than offering them and silently doing nothing:
         |  - 'pro_only' / the Pro daily limit are meaningless without a sellable paid tier
         |    (Regular license, or Extended with subscriptions off).
         |  - the /docs command needs the Knowledge Base addon to be installed and active.
         */
        Inertia::share('aiAssistantMeta', function () {
            if (! is_addon_active('ai-assistant')) {
                return null;
            }

            return [
                'pro_available' => isProAvailable(),
                'quota_mode' => credit_quota_mode(),
                'kb_installed' => \Addons\AiAssistant\Support\KnowledgeBase::installed(),
                // The site-wide AI defaults the assistant falls back to when its own
                // provider/model are left empty. The settings screen needs the REAL values:
                // it previously assumed 'openai' and filtered the model list against that,
                // so on an install defaulting to any other provider the "Use the site
                // default" state showed models from the wrong provider (usually none).
                'default_provider' => settings('default_ai_provider', 'openai'),
                'default_model' => settings('default_ai_model', 'gpt-4o-mini'),
            ];
        });

        Inertia::share('aiProviders', function () {
            if (! is_addon_active('ai-assistant')) {
                return [];
            }

            $configuredProviders = AiKey::available()->pluck('provider')->unique();
            $providers = config('ai.providers', []);

            return AiModel::active()
                ->where('type', 'chat')
                ->whereIn('provider', $configuredProviders)
                ->select('provider')
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider')
                ->map(fn (string $provider) => [
                    'value' => $provider,
                    'label' => $providers[$provider]['name'] ?? ucfirst($provider),
                ])
                ->values()
                ->toArray();
        });

        Inertia::share('aiModels', function () {
            if (! is_addon_active('ai-assistant')) {
                return [];
            }

            $configuredProviders = AiKey::available()->pluck('provider')->unique();

            return AiModel::active()
                ->where('type', 'chat')
                ->whereIn('provider', $configuredProviders)
                ->orderBy('provider')
                ->orderBy('name')
                ->get(['slug', 'name', 'provider'])
                ->map(fn (AiModel $model) => [
                    'value' => $model->slug,
                    'label' => $model->name,
                    'provider' => $model->provider,
                ])
                ->toArray();
        });
    }

    /**
     * Paths where the assistant is never shown, regardless of settings.
     *
     * Sign-in, registration, password and verification flows: a chat widget floating over a
     * login form is a distraction at best, and on a password page it sits beside credentials
     * being typed. The installer is excluded for the obvious reason that the app isn't set
     * up yet. Admins can add their own paths on top of these; they cannot remove these.
     */
    private const ALWAYS_EXCLUDED = [
        'login', 'register', 'logout',
        'password', 'password/*',
        'forgot-password', 'reset-password', 'reset-password/*',
        'verify-email', 'verify-email/*', 'email/verify', 'email/verify/*',
        'two-factor', 'two-factor/*', '2fa', '2fa/*',
        'confirm-password',
        'install', 'install/*',
        'admin/login', 'admin/password/*',
    ];

    /**
     * Should the widget be withheld from the page being requested?
     *
     * Patterns are matched with Laravel's request `is()`, so a trailing `*` works as a
     * wildcard ("/blog/*"). Operator-supplied paths are one per line and normalised, so
     * "/pricing", "pricing" and "/pricing/" all behave the same — an admin shouldn't have
     * to guess the exact form.
     */
    private function isExcludedPage(): bool
    {
        $request = request();

        if ($request->is(...self::ALWAYS_EXCLUDED)) {
            return true;
        }

        $configured = (string) addon_setting('ai-assistant', 'excluded_pages', '');
        if (trim($configured) === '') {
            return false;
        }

        $patterns = collect(preg_split('/[\r\n,]+/', $configured))
            ->map(fn ($line) => trim((string) $line))
            // Drop blank lines BEFORE the slashes are stripped. Filtering afterwards would
            // also discard "/" — the home page — because it trims down to an empty string.
            ->filter(fn (string $line) => $line !== '')
            // request->is() matches paths without a leading slash, and a trailing slash would
            // never match; "/" itself normalises back to "/" rather than to nothing.
            ->map(fn (string $line) => trim($line, '/') ?: '/')
            ->unique()
            ->all();

        return $patterns && $request->is(...$patterns);
    }

    /**
     * Public URL for the assistant avatar.
     *
     * Deliberately ROOT-RELATIVE ("/storage/…") for a local disk rather than the absolute
     * URL Storage::url() builds. That method derives its host from APP_URL, so an install
     * whose APP_URL has drifted from the domain it's actually served on (e.g. APP_URL is
     * still http://localhost while the site runs on https://example.test) emits an
     * http://localhost image URL — which the browser blocks as mixed content on an HTTPS
     * page, and the avatar silently fails to load. A root-relative path inherits whatever
     * scheme and host the page was loaded on, so it simply cannot mismatch.
     *
     * A remote disk (S3/CDN) still needs its real absolute URL, so that case defers to
     * Storage as before. A value already stored as a full URL is left untouched.
     */
    private function avatarUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        if (config('filesystems.disks.public.driver') !== 'local') {
            return Storage::disk('public')->url($value);
        }

        return '/storage/' . ltrim($value, '/');
    }

    /**
     * The privacy / terms links shown under the chat input, or null to show nothing.
     *
     * Returns null — so the note disappears entirely rather than rendering a dangling
     * "By continuing, you agree to our ." — when:
     *  - the admin has switched the note off, or
     *  - this is the admin panel (staff don't need to be shown the site's terms), or
     *  - NEITHER link is configured. This was the actual bug: the note assumed the global
     *    site_terms_url / site_privacy_url settings were populated, and rendered an empty
     *    sentence when they weren't.
     *
     * The addon's own setting wins; the site-wide setting is the fallback, so an install
     * that already has these pages configured keeps working without touching the addon.
     * A bare slug ("privacy-policy") is normalised to a root-relative path; a full URL is
     * left alone.
     */
    private function legalLinks(bool $isAdminArea): ?array
    {
        if ($isAdminArea || ! (bool) addon_setting('ai-assistant', 'show_legal_note', true)) {
            return null;
        }

        $privacy = $this->normalizeLink(
            addon_setting('ai-assistant', 'privacy_url') ?: settings('site_privacy_url', '')
        );
        $terms = $this->normalizeLink(
            addon_setting('ai-assistant', 'terms_url') ?: settings('site_terms_url', '')
        );

        if ($privacy === null && $terms === null) {
            return null;
        }

        return ['privacy_url' => $privacy, 'terms_url' => $terms];
    }

    private function normalizeLink(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return '/' . ltrim($value, '/');
    }
}
