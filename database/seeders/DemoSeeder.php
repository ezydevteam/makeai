<?php

namespace Database\Seeders;

use Addons\AiAssistant\Models\AiAssistantFeedback;
use Addons\AiAssistant\Models\AiAssistantRule;
use Addons\AiAssistant\Models\AssistantConversation;
use Addons\AiAssistant\Models\AssistantCsat;
use Addons\AiAssistant\Models\AssistantMessage;
use App\Models\AddonSetting;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\AffiliateReferral;
use App\Models\Admin;
use App\Models\AdminNote;
use App\Models\AdminRole;
use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Announcement;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Coupon;
use Addons\AiChatbot\Models\ChatMessageFeedback;
use Addons\AiChatbot\Models\ChatProject;
use Addons\AiChatbot\Models\ChatbotMode;
use Addons\AiChatbot\Models\Conversation;
use Addons\AiChatbot\Models\ConversationMessage;
use Addons\AiChatbot\Models\ConversationTag;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipFolder;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Models\AipPreset;
use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbArticleVote;
use Addons\AiKnowledgeBase\Models\KbCategory;
use Addons\AiKnowledgeBase\Models\KbSearch;
use Addons\FakerAi\Models\FakerBatch;
use App\Models\CreditTransaction;
use App\Models\Document;
use App\Models\ExportPreset;
use App\Models\Favorite;
use App\Models\GenerationHistory;
use App\Models\LoginHistory;
use App\Models\Menu;
use App\Models\Payment;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlanCountryPrice;
use App\Models\ScheduledExport;
use App\Models\Setting;
use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Models\SupportDepartment;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\ToolChain;
use App\Models\ToolChainRun;
use App\Models\ToolEmbed;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserCollectionTool;
use App\Services\AffiliateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Fixed PRNG seed, so the demo is the SAME demo on every reset.
     *
     * `demo:reset` re-runs this seeder every six hours. With an unseeded generator each
     * run invented a different business — different revenue, different chart shapes — so
     * a buyer who looked twice in one day saw two unrelated products, and no screenshot or
     * documentation could ever match what they were looking at.
     *
     * Seeding `mt_srand` covers `mt_rand`, `array_rand` and `shuffle`, which all draw from
     * the same Mt19937 engine. It deliberately does NOT cover `mt_rand()` — that is a
     * CSPRNG with no seeding API — which is why this seeder uses `mt_rand()` throughout.
     * Do not "harden" those back to `mt_rand()`: nothing here is a secret, and doing so
     * silently returns the demo to being different on every reset.
     *
     * Rows are still anchored to now(), so the data keeps moving with the calendar while
     * the numbers stay put. Change this constant only to deliberately reshuffle the demo.
     */
    private const RANDOM_SEED = 20260726;

    /**
     * The hourly shape of today's new subscriptions — hour => how many start in it.
     *
     * "Today" is the one dashboard range bucketed by hour rather than by day, and the
     * Subscription Health chart plots those 24 buckets directly. The even spread the
     * other 179 days use puts at most one subscription in any hour, so that chart drew
     * five identical one-high bars in a 24-slot grid: technically populated, visually
     * empty.
     *
     * This is a working day instead — a slow start, a mid-morning peak, the lunch dip,
     * an afternoon run and an evening tail. Twelve rows over eight hours, against the
     * cohort's recent-day baseline of 3-4 (dailyVolume() at scale 0.45). That keeps today
     * the busiest day, which dailyVolume() is tuned to guarantee, without the day-bucketed
     * 7d and 30d ranges ending in a spike — an early draft totalled 20 and drew exactly
     * that cliff.
     *
     * Pair with TODAY_CHURN_HOURS: its hours are deliberately disjoint from these, so the
     * mirrored churn bars land between the new-subscription bars rather than under them.
     */
    private const TODAY_SIGNUP_HOURS = [
        8 => 1, 9 => 2, 11 => 3, 12 => 1,
        14 => 2, 16 => 1, 18 => 1, 20 => 1,
    ];

    /**
     * The hourly shape of today's cancellations — hour => how many land in it.
     *
     * Churn is the downward half of the same chart and was left to chance: a cancellation
     * fell on today only if some earlier signup's random 14-95 day offset happened to
     * point here, which on most resets meant an empty or single-bar churn series under a
     * populated new-subscription one. redateTodayCancellations() moves existing
     * cancellations onto these hours so the mirror always has something to draw.
     *
     * Five against the signup profile's twelve, on purpose — a demo whose churn rivals
     * its growth is a demo of a failing business. The ratio matches the rest of the
     * cohort, where roughly one subscription in four eventually cancels.
     */
    private const TODAY_CHURN_HOURS = [
        10 => 1, 13 => 2, 17 => 1, 19 => 1,
    ];

    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);


        // Passwords come from config (DEMO_*_PASSWORD) and have no default, so a
        // demo site cannot be stood up with a password that ships in the source.
        // These accounts are published on the sign-in page — refuse rather than
        // invent one.
        $adminPassword = config('demo.admin_password');
        $userPassword = config('demo.user_password');

        if (blank($adminPassword) || blank($userPassword)) {
            throw new \RuntimeException(
                'DemoSeeder requires DEMO_ADMIN_PASSWORD and DEMO_USER_PASSWORD to be set.'
                . 'They have no default because the demo credentials are shown publicly on the sign-in page.'
            );
        }

        $adminEmail = config('demo.admin_email');
        $userEmail = config('demo.user_email');

        // Shared avatar for the demo admin across both its User and Admin rows.
        $adminAvatar = $this->demoAvatar('Demo Administrator', 'avatars/demo-admin.svg');

        // ─── 1. Demo Admin ─────────────────────────────────────────────
        User::updateOrCreate(['email' => $adminEmail], [
            'name' => 'Demo Administrator',
            'password' => Hash::make($adminPassword),
            'credits' => 9999,
            'avatar' => $adminAvatar,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Also ensure admin account exists in admins table for blog posts
        $superAdminRoleId = AdminRole::where('slug', 'super-admin')->value('id');
        Admin::updateOrCreate(['email' => $adminEmail], [
            'name' => 'Demo Administrator',
            'password' => Hash::make($adminPassword),
            'role_id' => $superAdminRoleId,
            'avatar' => $adminAvatar,
            'is_active' => true,
        ]);

        // ─── 1b. Staff admins, one per non-super role ──────────────────
        //
        // Administrators listed exactly one account, so the whole RBAC half of the product
        // — roles, per-role permissions, the role column on the list — demonstrated itself
        // against a single super admin, which is the one row where permissions never apply.
        //
        // The roles are AdminSeeder's, not invented here: seeding an admin against a role
        // slug that seeder does not create would leave role_id null and the account
        // permissionless. Skipped individually if a slug is missing rather than failing the
        // reset, since a buyer may have renamed or deleted a non-system role.
        //
        // They share the demo admin password: the sign-in page publishes it, and the point
        // is that a buyer can sign in as each one and watch the sidebar change.
        $staffAdmins = [
            ['email' => 'manager@demo.com', 'name' => 'Marta Keller', 'role' => 'manager', 'hours' => 3],
            ['email' => 'support@demo.com', 'name' => 'Devin Osei', 'role' => 'support', 'hours' => 9],
            ['email' => 'content@demo.com', 'name' => 'Priya Raman', 'role' => 'content-manager', 'hours' => 27],
        ];

        foreach ($staffAdmins as $staff) {
            $roleId = AdminRole::where('slug', $staff['role'])->value('id');

            if (! $roleId) {
                continue;
            }

            Admin::updateOrCreate(['email' => $staff['email']], [
                'name' => $staff['name'],
                'password' => Hash::make($adminPassword),
                'role_id' => $roleId,
                'avatar' => $this->demoAvatar($staff['name'], 'avatars/demo-admin-'.$staff['role'].'.svg'),
                'is_active' => true,
                // Staggered, so the list's "last login" column reads as a team that works
                // rather than three accounts created and never used.
                'last_login_at' => now()->subHours($staff['hours']),
                'last_login_ip' => '203.76.120.'.(40 + $staff['hours']),
            ]);
        }

        // ─── 2. Demo User ──────────────────────────────────────────────
        User::updateOrCreate(['email' => $userEmail], [
            'name' => 'Demo Creator',
            'password' => Hash::make($userPassword),
            'credits' => 500,
            'avatar' => $this->demoAvatar('Demo Creator', 'avatars/demo-creator.svg'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ─── 3. Fetch or create plans ──────────────────────────────────
        $freePlan = Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'description' => 'Basic access',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'credits' => 100,
            'features' => ['5 AI templates', 'Starter AI model', '10 chats', 'Basic support'],
            'is_active' => true,
            'is_free' => true,
            'sort_order' => 1,
        ]);

        $professionalPlan = Plan::firstOrCreate(['slug' => 'professional'], [
            'name' => 'Professional',
            'description' => 'Power user access',
            'price_monthly' => 49.99,
            'price_yearly' => 499.99,
            'credits' => 99999,
            'features' => ['All AI templates', 'All AI models', 'Unlimited chats', 'Priority support', 'API access'],
            'is_active' => true,
            'is_free' => false,
            'is_featured' => true,
            'sort_order' => 3,
        ]);

        /**
         * The plans demo users are spread across, and what a user on each should look like.
         *
         * Kept as ONE structure on purpose. This was previously three parallel things — an
         * array of ids, an array of names, and a match() on the name — which had drifted:
         * the match still handled 'pro' and 'unlimited', plans that no longer exist, so the
         * first 'professional' user threw UnhandledMatchError. Adding a plan here now updates
         * the id, the name and the credits together, so they cannot fall out of step.
         */
        $demoPlans = [
            ['id' => $freePlan->id, 'name' => 'free', 'credits' => fn () => mt_rand(10, 100)],
            ['id' => $professionalPlan->id, 'name' => 'professional', 'credits' => fn () => mt_rand(1000, 5000)],
        ];

        // ─── 3b. Regional pricing for every paid plan ──────────────────
        $this->seedPlanCountryPrices();

        AffiliateProgram::current()->update([
            'allow_custom_alias' => true,
        ]);

        // ─── 4. 50 Sample Users ────────────────────────────────────────
        $demoUsers = [];
        $firstNames = ['Alice', 'Bob', 'Carol', 'David', 'Eve', 'Frank', 'Grace', 'Henry', 'Iris', 'Jack',
            'Karen', 'Leo', 'Maria', 'Nick', 'Olivia', 'Paul', 'Quinn', 'Rachel', 'Sam', 'Tina',
            'Uma', 'Victor', 'Wendy', 'Xander', 'Yuki', 'Zara', 'Aaron', 'Blake', 'Celine', 'Derek',
            'Elsa', 'Finn', 'Gina', 'Hugo', 'Ivy', 'Jake', 'Kira', 'Liam', 'Mona', 'Noah',
            'Oscar', 'Piper', 'Quentin', 'Rosa', 'Steve', 'Tara', 'Ulysses', 'Vera', 'Will', 'Xena'];

        // Rounded-out demographics so the admin user list, profiles and CRM-style
        // screens read like a real customer base rather than a wall of blank cells.
        $userProfiles = [
            ['country' => 'US', 'timezone' => 'America/New_York',  'locale' => 'en'],
            ['country' => 'GB', 'timezone' => 'Europe/London',     'locale' => 'en'],
            ['country' => 'CA', 'timezone' => 'America/Toronto',   'locale' => 'en'],
            ['country' => 'AU', 'timezone' => 'Australia/Sydney',  'locale' => 'en'],
            ['country' => 'DE', 'timezone' => 'Europe/Berlin',     'locale' => 'de'],
            ['country' => 'IN', 'timezone' => 'Asia/Kolkata',      'locale' => 'en'],
            ['country' => 'SG', 'timezone' => 'Asia/Singapore',    'locale' => 'en'],
            ['country' => 'BR', 'timezone' => 'America/Sao_Paulo', 'locale' => 'pt'],
            ['country' => 'FR', 'timezone' => 'Europe/Paris',      'locale' => 'fr'],
            ['country' => 'NL', 'timezone' => 'Europe/Amsterdam',  'locale' => 'en'],
        ];
        $professions = ['Content Marketer', 'Software Developer', 'Startup Founder', 'SEO Specialist',
            'Copywriter', 'Product Manager', 'Digital Agency Owner', 'E-commerce Manager',
            'Social Media Manager', 'Technical Writer', 'Growth Marketer', 'UX Designer',
            'Data Analyst', 'Freelance Consultant', 'Brand Strategist'];
        $useCases = ['marketing', 'development', 'writing', 'business', 'social', 'ecommerce', 'agency', 'personal'];
        $themePrefs = ['light', 'dark', 'system'];

        for ($i = 0; $i < 50; $i++) {
            $name = $firstNames[$i];
            $email = strtolower($name) . ($i + 1) . '@demo.com';
            // Modulo the real plan count. It was hardcoded to 3 against a 2-plan array, so
            // every third user indexed past the end of it.
            $plan = $demoPlans[$i % count($demoPlans)];
            $isFree = $plan['name'] === 'free';
            $profile = $userProfiles[$i % count($userProfiles)];
            $creditsUsedMonth = mt_rand(0, 5000);
            $joinedAt = now()->subDays(mt_rand(1, 365));

            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name . ' Demo',
                // Same configured password as the showcase user — these are
                // background fixtures, but a hardcoded one would still be a known
                // login on a public demo site.
                'password' => Hash::make($userPassword),
                'credits' => ($plan['credits'])(),
                // Free-tier users hold NO plan record. The admin Subscriptions screen lists
                // any user with a plan_id but no billing row as a "synthetic" subscription
                // (cycle "Not set", amount "Not available", status "none"), so putting free
                // users on the Free plan filled that screen with non-subscribers. isPro() and
                // AccessLevelService both guard for a null plan, so this is safe.
                'plan_id' => $isFree ? null : $plan['id'],
                'subscription_status' => $isFree ? 'none' : 'active',
                'subscription_ends_at' => $isFree ? null : now()->addMonths(mt_rand(1, 12)),
                'credits_used_month' => $creditsUsedMonth,
                'credits_used_today' => min($creditsUsedMonth, mt_rand(0, 320)),
                'country' => $profile['country'],
                'profession' => $professions[$i % count($professions)],
                // National number kept globally unique so the users(phone, phone_country)
                // unique index never trips regardless of the paired country.
                'phone' => sprintf('555%07d', 1000 + $i),
                'phone_country' => $profile['country'],
                // ~80% have confirmed their number; the rest model the un-verified state.
                'phone_verified_at' => $i % 5 === 0 ? null : now()->subDays(mt_rand(1, 200)),
                'locale' => $profile['locale'],
                'timezone' => $profile['timezone'],
                'use_case' => $useCases[$i % count($useCases)],
                'theme_preference' => $themePrefs[$i % count($themePrefs)],
                'email_marketing' => $i % 4 !== 0,
                'sms_marketing_opt_in' => $i % 3 !== 0,
                // Unique per user (index guarantees it) so the affiliate/referral UI shows
                // a real code for everyone instead of blanks.
                'referral_code' => strtoupper(substr($name, 0, 3)) . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'onboarding_completed_at' => now()->subDays(mt_rand(1, 300)),
                // Every demo user gets an avatar, so their tool reviews and blog comments
                // (all authored by these accounts) show a face rather than a fallback.
                'avatar' => $this->demoAvatar($name . ' Demo', 'avatars/demo-user-' . ($i + 1) . '.svg'),
                'is_active' => true,
                'last_login_ip' => '203.0.113.' . mt_rand(2, 254),
                'email_verified_at' => now()->subDays(mt_rand(1, 365)),
                // Biased to the last day or two rather than flat across the week: the
                // Active Users card counts last_login_at inside the window, so a uniform
                // spread made today and yesterday tie.
                'last_login_at' => now()->subHours(min(mt_rand(1, 168), mt_rand(1, 168))),
            ]);

            $this->backdate($user, $joinedAt);
            $demoUsers[] = $user;
        }

        // Guarantee today + recent-day signups so the signups chart is continuous at the
        // short ranges (7d/30d), not only monthly. Uses higher-index users the oauth /
        // referral / affiliate passes below never touch, so their timestamps stand.
        // Weighted toward today rather than one-a-day: a flat line gives the signups card
        // a 0% trend at every range, which on a demo reads as a product nobody is joining.
        // Same 17 accounts, redistributed — three today, two on each of the last two days,
        // then thinning out across the month.
        // Reaches back ~8 weeks, not ~2: the 30d signups card compares against days 30-59,
        // and with every seeded signup inside the last 25 days that window held almost
        // nothing, so the card claimed a nonsense +1467% instead of a real trend.
        $recentSignupDays = [0, 0, 1, 2, 3, 5, 7, 9, 12, 16, 20, 25, 31, 37, 43, 50, 57];
        foreach ($recentSignupDays as $index => $daysAgo) {
            $recentUser = $demoUsers[30 + $index] ?? null;
            $recentUser?->forceFill([
                'created_at' => now()->subDays($daysAgo)->setTime(mt_rand(8, 20), mt_rand(0, 59)),
            ])->save();
        }

        $adminUser = User::where('email', $adminEmail)->first();
        $toolSlugs = DB::table('ai_tools')
            ->whereNotNull('slug')
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();

        if ($toolSlugs === []) {
            $toolSlugs = [
                'blog-article-generator',
                'seo-meta-description-generator',
                'email-subject-line-generator',
                'ai-chat',
                'code-explainer',
                'product-description-writer',
            ];
        }

        // ─── 5. AI Usage Logs — continuous timeline ─────────────────────
        // Slugs must match the live ai_models catalog (AiModelSeeder). The retired
        // July-2026 lineup (gpt-4o*, deepseek-v3, llama-4-scout-17b, …) was pulled
        // from the catalog, so usage logs referencing them would point at models an
        // admin can no longer find — a demo discontinuity. Current slugs only.
        $providers = ['openai', 'anthropic', 'google', 'deepseek', 'meta'];
        $models = [
            'openai' => ['gpt-5.6-terra', 'gpt-5.4-mini', 'gpt-5.5'],
            'anthropic' => ['claude-sonnet-4-6', 'claude-haiku-4-5', 'claude-opus-4-8'],
            'google' => ['gemini-3.1-pro', 'gemini-3.5-flash'],
            'deepseek' => ['deepseek-v4-pro', 'deepseek-v4-flash'],
            'meta' => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant'],
        ];
        $types = ['chat', 'text_generation', 'image_generation', 'transcription', 'embedding'];

        // A fixed count scattered randomly over 180 days left day/hour buckets empty —
        // the "today" charts came up blank and the 30d/90d lines were gappy, so a buyer
        // switching Today / Month / All time saw discontinuous data. Instead we guarantee
        // coverage at EVERY granularity the dashboard buckets by: today hour-by-hour, the
        // last 90 days daily, and each of the last ~12 months. "today" is never empty.
        $createUsage = function ($user, $createdAt) use ($providers, $models, $types, $toolSlugs) {
            $provider = $providers[array_rand($providers)];
            $model = $models[$provider][array_rand($models[$provider])];
            $inputTokens = mt_rand(50, 4000);
            $outputTokens = mt_rand(20, 2000);

            $log = AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'model' => $model,
                'type' => $types[array_rand($types)],
                'tool_slug' => $toolSlugs[array_rand($toolSlugs)],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round(($inputTokens * 0.00001) + ($outputTokens * 0.00002), 6),
                'credits_used' => max(1, round(($inputTokens * 0.001) + ($outputTokens * 0.003), 2)),
                'response_time_ms' => mt_rand(750, 9500),
                // A small share fail so the AI failure-rate chart is not flat-zero.
                'status' => mt_rand(1, 22) === 1 ? 'failed' : 'completed',
                'metadata' => ['demo' => true],
            ]);

            $this->backdate($log, $createdAt);
        };

        $pickUser = fn () => $demoUsers[array_rand($demoUsers)];

        // Today — the WHOLE calendar day, not only the hours already elapsed. Seeding
        // stopped at now()->hour, so a reset just after midnight left today nearly empty
        // and every "today vs yesterday" card opened on a fall. See spreadOverDay().
        foreach ($this->spreadOverDay(0, $this->dailyVolume(0), 6, 22) as $moment) {
            $createUsage($pickUser(), $moment);
        }

        // Last 180 days — continuous 7d / 30d / 90d lines, with volume ramping toward the
        // present so each range compares favourably against the range before it.
        //
        // 180 rather than 90: the 90d card compares against days 90-179, and while the
        // daily timeline stopped at 90 that window held only the sparse monthly buckets,
        // so the card read a fake +1200% instead of the real trend.
        for ($day = 1; $day <= 179; $day++) {
            foreach ($this->spreadOverDay($day, $this->dailyVolume($day), 6, 22) as $moment) {
                $createUsage($pickUser(), $moment);
            }
        }

        // Months 7-13 ago — volume per month so the lifetime (monthly) chart is continuous.
        // Starts past the daily loop above so the two never stack on the same days.
        for ($monthsAgo = 7; $monthsAgo <= 13; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            foreach (range(1, mt_rand(8, 15)) as $ignored) {
                $createUsage(
                    $pickUser(),
                    $monthStart->copy()->addDays(mt_rand(0, $monthStart->daysInMonth - 1))->setTime(mt_rand(6, 22), mt_rand(0, 59))
                );
            }
        }

        // Internal (platform) AI usage. The "Internal Usage" and "API Cost" KPI cards read
        // the system account (User::internalAi()) — which the demo never created, so those
        // cards were flat zero and the dashboard floor filled them with per-period noise
        // that didn't scale with the range (today could out-count 30d = the discontinuity).
        // Seed a continuous, lower-volume internal timeline so the cards and their charts
        // rise naturally with the selected period, from real rows.
        $internalUser = User::internalAi();
        // Backdate the system account to the platform's founding so it never surfaces in the
        // "Recent Users" list or skews today's signups — it is infrastructure, not a customer.
        $internalUser->forceFill(['created_at' => now()->subDays(340)])->save();
        $internalTypes = ['text_generation', 'embedding', 'chat'];

        $createInternalUsage = function ($createdAt) use ($internalUser, $providers, $models, $internalTypes) {
            $provider = $providers[array_rand($providers)];
            $model = $models[$provider][array_rand($models[$provider])];
            $inputTokens = mt_rand(200, 6000);
            $outputTokens = mt_rand(50, 1500);

            $log = AiUsageLog::create([
                'user_id' => $internalUser->id,
                'provider' => $provider,
                'model' => $model,
                'type' => $internalTypes[array_rand($internalTypes)],
                'tool_slug' => null, // system usage isn't attributed to a public tool
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round(($inputTokens * 0.00001) + ($outputTokens * 0.00002), 6),
                'credits_used' => 0, // internal AI is exempt from per-user credits
                'response_time_ms' => mt_rand(600, 8000),
                'status' => 'completed',
                'metadata' => ['demo' => true, 'internal' => true],
            ]);

            $this->backdate($log, $createdAt);
        };

        // Platform work runs around the clock, so this one spans 00:00-23:00 rather than
        // waking hours — but it is the same full-day, clock-independent spread.
        foreach ($this->spreadOverDay(0, $this->dailyVolume(0, 0.4), 0, 23) as $moment) {
            $createInternalUsage($moment);
        }
        for ($day = 1; $day <= 179; $day++) {
            foreach ($this->spreadOverDay($day, $this->dailyVolume($day, 0.4), 0, 23) as $moment) {
                $createInternalUsage($moment);
            }
        }
        for ($monthsAgo = 7; $monthsAgo <= 13; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            foreach (range(1, mt_rand(4, 8)) as $ignored) {
                $createInternalUsage($monthStart->copy()->addDays(mt_rand(0, $monthStart->daysInMonth - 1))->setTime(mt_rand(0, 23), mt_rand(0, 59)));
            }
        }

        $showcaseUser = User::where('email', $userEmail)->first();

        if ($showcaseUser) {
            $this->seedShowcaseUserExperience($showcaseUser, $professionalPlan, $toolSlugs);
        }

        // ─── 6. AI Chats (10 chatbots with messages) ────────────────────
        $chatTopics = [
            ['title' => 'Marketing Strategy Planning', 'category' => 'marketing'],
            ['title' => 'Python Script Debugging', 'category' => 'development'],
            ['title' => 'Blog Content Ideas', 'category' => 'writing'],
            ['title' => 'SEO Optimization Tips', 'category' => 'marketing'],
            ['title' => 'Data Analysis Help', 'category' => 'analytics'],
            ['title' => 'Product Launch Plan', 'category' => 'business'],
            ['title' => 'Social Media Calendar', 'category' => 'social'],
            ['title' => 'API Integration Guide', 'category' => 'development'],
            ['title' => 'Email Campaign Copy', 'category' => 'writing'],
            ['title' => 'Financial Report Summary', 'category' => 'finance'],
        ];

        $chatModels = ['gpt-5.4-mini', 'gpt-5.6-terra', 'claude-haiku-4-5', 'gemini-3.5-flash', 'deepseek-v4-flash'];

        foreach ($chatTopics as $i => $topic) {
            $user = $demoUsers[array_rand($demoUsers)];
            $chatStartedAt = now()->subDays(mt_rand(0, 90));
            $chat = AiChat::create([
                'user_id' => $user->id,
                'title' => $topic['title'],
                'model' => $chatModels[$i % count($chatModels)],
                'is_pinned' => $i < 3,
            ]);

            $this->backdate($chat, $chatStartedAt);

            // Add 4-6 messages per chat
            $msgCount = mt_rand(4, 6);
            $userMessages = ['Can you help me with this?', 'What would you suggest?',
                'That sounds great, tell me more.', 'Can you elaborate on that?',
                'Give me some examples.', 'How do I implement this?'];
            $assistantMessages = ['Absolutely! Here are some detailed suggestions...',
                'Based on your requirements, I recommend the following approach...',
                'Let me break this down into actionable steps...',
                'Here are several examples you can work with...',
                'The best practice for this scenario would be...',
                'I have analyzed this and here is my detailed response...'];

            for ($m = 0; $m < $msgCount; $m++) {
                // ->copy() matters: Carbon is mutable here, so addMinutes() on the chat's own
                // created_at would walk the conversation's start time forward on every message.
                $this->backdate(AiChatMessage::create([
                    'chat_id' => $chat->id,
                    'role' => 'user',
                    'content' => $userMessages[$m % count($userMessages)],
                ]), $chatStartedAt->copy()->addMinutes($m * 2));

                $this->backdate(AiChatMessage::create([
                    'chat_id' => $chat->id,
                    'role' => 'assistant',
                    'content' => $assistantMessages[$m % count($assistantMessages)],
                ]), $chatStartedAt->copy()->addMinutes(($m * 2) + 1));
            }
        }

        // ─── 7. Blog Categories & Tags ──────────────────────────────────
        $categories = ['AI News', 'Tutorials', 'Case Studies', 'Product Updates', 'Industry Insights', 'Tips & Tricks'];
        $catModels = [];
        foreach ($categories as $cat) {
            $catModels[] = BlogCategory::firstOrCreate(['slug' => Str::slug($cat)], [
                'name' => $cat,
                'is_active' => true,
            ]);
        }

        $tags = ['AI', 'machine-learning', 'gpt', 'content', 'automation', 'productivity',
            'deep-learning', 'nlp', 'coding', 'design'];
        $tagModels = [];
        foreach ($tags as $tag) {
            $tagModels[] = BlogTag::firstOrCreate(['slug' => $tag], ['name' => ucfirst(str_replace('-', ' ', $tag))]);
        }

        // ─── 8. 30 Blog Posts ───────────────────────────────────────────
        $admin = Admin::where('email', $adminEmail)->first();
        $postTitles = [
            '10 Ways AI Can Transform Your Content Strategy',
            'Getting Started with GPT-5: A Complete Guide',
            'How We Built Our AI-Powered Analytics Dashboard',
            'The Future of Automated Content Creation',
            '5 AI Tools Every Marketer Should Know About',
            'Understanding Large Language Models: A Deep Dive',
            'AI Image Generation: Best Practices for 2026',
            'Building a Chatbot from Scratch: Step by Step',
            'Why Your Business Needs an AI-First Approach',
            'Comparing Top AI Models: Performance Benchmarks',
            'The Rise of Multimodal AI Systems',
            'How AI Is Revolutionizing Customer Support',
            'AI Ethics: Responsible Development Guidelines',
            '10 Prompts That Will Boost Your Productivity',
            'Machine Learning for Non-Technical Founders',
            'How to Fine-Tune Models for Your Industry',
            'The Complete Guide to AI API Integration',
            'AI-Powered SEO: Ranking Higher in 2026',
            'Real-Time AI: Latency Optimization Techniques',
            'Building Scalable AI Infrastructure',
            'How to Choose the Right AI Model for Your Project',
            'The Economics of AI: Cost vs. Value Analysis',
            'AI in Education: Transforming Learning Experiences',
            'Voice AI and Conversational Interfaces Explained',
            'Data Privacy in the Age of AI',
            'AI-Powered Design: From Concept to Creation',
            'The Developer\'s Guide to AI-Assisted Coding',
            'How AI Is Changing the Healthcare Industry',
            'AI for Small Business: Affordable Solutions',
            'The State of AI in 2026: Trends and Predictions',
        ];

        // share_count is not a core column — the FakerAI addon adds it. Writing it
        // unconditionally breaks this seeder on every install that does not have that addon,
        // so the counter is only filled in when the column is actually there.
        $hasShareCount = Schema::hasColumn('blog_posts', 'share_count');

        foreach ($postTitles as $i => $title) {
            // Views were seeded but shares never were, so every post rendered its share
            // counter as absent (Blog/Show.vue hides it at zero). Roughly one reader in
            // sixty shares, which keeps the two numbers in a believable ratio.
            $postViews = mt_rand(50, 5000);

            $attributes = [
                'author_id' => $admin ? $admin->id : 1,
                'title' => $title,
                'content' => '<p>' . $this->loremParagraph() . '</p>'
                    . '<h2>Key Insights</h2>'
                    . '<p>' . $this->loremParagraph() . '</p>'
                    . '<h3>Implementation</h3>'
                    . '<p>' . $this->loremParagraph() . '</p>'
                    . '<blockquote><p>' . $this->loremSentence() . '</p></blockquote>'
                    . '<p>' . $this->loremParagraph() . '</p>',
                'excerpt' => $this->loremSentence(),
                'status' => 'published',
                'is_featured' => $i < 6,
                'published_at' => now()->subDays(365 - ($i * 12)),
                'reading_time' => mt_rand(3, 15),
                'views_count' => $postViews,
                'meta_title' => $title . ' | MakeAI Blog',
                'meta_description' => 'Learn about ' . strtolower($title) . ' in this comprehensive guide.',
            ];

            if ($hasShareCount) {
                $attributes['share_count'] = (int) round($postViews / mt_rand(45, 90));
            }

            $post = BlogPost::updateOrCreate(['slug' => Str::slug($title)], $attributes);

            $this->backdate($post, now()->subDays(365 - ($i * 12)));

            // Attach random categories and tags
            $post->categories()->sync([$catModels[array_rand($catModels)]->id, $catModels[array_rand($catModels)]->id]);
            $post->tags()->sync([$tagModels[array_rand($tagModels)]->id, $tagModels[array_rand($tagModels)]->id, $tagModels[array_rand($tagModels)]->id]);
        }

        // ─── 9. Dashboard Demo Data ───────────────────────────────────
        $generalDepartment = SupportDepartment::where('slug', 'general')->first();
        $technicalDepartment = SupportDepartment::where('slug', 'technical')->first();
        $billingDepartment = SupportDepartment::where('slug', 'billing')->first();
        // Every published post gets comments (see the comment-seeding block below), so this
        // is no longer capped at 6.
        $blogPosts = BlogPost::published()->latest('published_at')->get();

        // 15 social sign-ups across 5 providers, spread over ~60 days, so the Traffic Sources
        // panel shows a real provider breakdown (not just a couple of bars) across periods.
        $recentOauthUsers = collect(array_slice($demoUsers, 0, 15))
            ->filter()
            ->values();

        $oauthProviders = ['google', 'github', 'linkedin', 'facebook', 'twitter'];
        foreach ($recentOauthUsers as $index => $user) {
            $provider = $oauthProviders[$index % count($oauthProviders)];
            $user->forceFill([
                'oauth_provider' => $provider,
                'oauth_id' => $provider . '-demo-' . $user->id,
                'created_at' => now()->subDays(mt_rand(2, 60))->setTime(mt_rand(8, 20), mt_rand(0, 59)),
            ])->save();
        }

        $referralUsers = collect([$demoUsers[6], $demoUsers[7], $demoUsers[8], $demoUsers[9], $demoUsers[10], $demoUsers[11]])
            ->filter()
            ->values();
        foreach ($referralUsers as $index => $user) {
            $referrer = $recentOauthUsers[$index % max(1, $recentOauthUsers->count())] ?? $adminUser;
            $user->forceFill([
                'referred_by' => $referrer?->id,
                'created_at' => now()->subDays(mt_rand(3, 28)),
            ])->save();
        }

        $paymentRows = [
            // The largest labelled sale sits on today, not yesterday. Parked on day 1 it
            // was a 199.00 lump in the comparison window that the current day had to
            // out-earn, which flipped the "today" revenue card negative on its own.
            ['user' => $adminUser, 'plan' => $professionalPlan, 'gateway' => 'stripe', 'amount' => 199.00, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Pro annual renewal', 'days' => 0],
            ['user' => $demoUsers[2], 'plan' => $professionalPlan, 'gateway' => 'stripe', 'amount' => 19.99, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Monthly subscription', 'days' => 2],
            ['user' => $demoUsers[4], 'plan' => $freePlan, 'gateway' => 'paypal', 'amount' => 49.00, 'type' => 'credit_topup', 'status' => 'completed', 'label' => 'Credit top-up', 'days' => 0],
            ['user' => $demoUsers[7], 'plan' => $professionalPlan, 'gateway' => 'stripe', 'amount' => 29.00, 'type' => 'one_time', 'status' => 'completed', 'label' => 'One-time tool bundle', 'days' => 4],
            ['user' => $demoUsers[9], 'plan' => $professionalPlan, 'gateway' => 'stripe', // Day 5, not day 9. At 499.00 this is the largest single sale in the set, and parked
            // on day 9 it sat inside the window the 7d revenue card compares against — enough
            // on its own to cancel out a current week with half as many sales again.
            'amount' => 499.00, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Professional annual plan', 'days' => 5],
            ['user' => $demoUsers[12], 'plan' => $professionalPlan, 'gateway' => 'stripe', 'amount' => 99.00, 'type' => 'subscription', 'status' => 'completed', 'label' => 'Monthly renewal', 'days' => 17],
            ['user' => $demoUsers[15], 'plan' => null, 'gateway' => 'manual', 'amount' => 24.00, 'type' => 'credit_topup', 'status' => 'completed', 'label' => 'Wallet refill', 'days' => 21],
            ['user' => $demoUsers[18], 'plan' => $freePlan, 'gateway' => 'stripe', 'amount' => 39.00, 'type' => 'one_time', 'status' => 'completed', 'label' => 'Premium export pack', 'days' => 26],
            ['user' => $demoUsers[21], 'plan' => $professionalPlan, 'gateway' => 'paypal', 'amount' => 19.99, 'type' => 'subscription', 'status' => 'failed', 'label' => 'Failed monthly charge', 'days' => 3],
        ];

        foreach ($paymentRows as $index => $row) {
            $paidAt = now()->subDays($row['days'])->setTime(mt_rand(9, 19), mt_rand(0, 59));

            $this->backdate(Payment::updateOrCreate(
                ['gateway_payment_id' => 'demo-pay-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $row['user']->id,
                    'plan_id' => $row['plan']?->id,
                    'gateway' => $row['gateway'],
                    'amount' => $row['amount'],
                    'currency' => 'USD',
                    'status' => $row['status'],
                    'type' => $row['type'],
                    'metadata' => ['demo' => true, 'label' => $row['label']],
                ]
            ), $paidAt);
        }

        // Continuous revenue timeline so the revenue / subscription charts have no gaps
        // across Today / Month / All time (the labelled rows above only touch a handful of
        // days). Deterministic ids keep it idempotent under a standalone re-seed.
        $revenueGateways = ['stripe', 'stripe', 'paypal', 'razorpay'];
        $revenueTypes = ['subscription', 'subscription', 'one_time', 'credit_topup'];
        $subscriptionAmounts = [19.99, 29.00, 49.99, 99.00, 199.00];
        $revSeq = 0;

        $makeRevenue = function ($createdAt) use (&$revSeq, $demoUsers, $professionalPlan, $revenueGateways, $revenueTypes, $subscriptionAmounts) {
            $revSeq++;
            $type = $revenueTypes[array_rand($revenueTypes)];
            $amount = $type === 'subscription'
                ? $subscriptionAmounts[array_rand($subscriptionAmounts)]
                : round(mt_rand(9, 89) + 0.99, 2);

            $payment = Payment::updateOrCreate(
                ['gateway_payment_id' => 'demo-rev-' . str_pad((string) $revSeq, 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $demoUsers[array_rand($demoUsers)]->id,
                    'plan_id' => $type === 'subscription' ? $professionalPlan->id : null,
                    'gateway' => $revenueGateways[array_rand($revenueGateways)],
                    'amount' => $amount,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'type' => $type,
                    'metadata' => ['demo' => true, 'continuous' => true],
                ]
            );

            $this->backdate($payment, $createdAt);
        };

        // Today + last 75 days, ramping toward the present so the revenue cards read as
        // growth at every range. Quiet days are confined to the older stretch: a gap two
        // months back is realistic, whereas one inside the current fortnight is what made
        // the 7d and 30d revenue trends flip negative.
        // Full volume rather than a thinned curve. The revenue card sums money, and at
        // 3-4 sales a day a single 199.00 annual plan landing on one side of the boundary
        // swung the comparison by more than the whole trend — the card went red on a day
        // that had MORE sales. Enough transactions per day and the price mix averages out,
        // so the trend follows the volume ramp instead of the luck of the draw.
        for ($day = 0; $day <= 179; $day++) {
            if ($day > 6 && mt_rand(1, 10) <= 2) {
                continue; // ~20% quiet days for realism, outside the current week
            }
            foreach ($this->spreadOverDay($day, $this->dailyVolume($day), 8, 21) as $moment) {
                $makeRevenue($moment);
            }
        }

        // Months 7-13 ago: several sales per month for continuous lifetime revenue.
        for ($monthsAgo = 7; $monthsAgo <= 13; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            foreach (range(1, mt_rand(4, 9)) as $ignored) {
                $makeRevenue($monthStart->copy()->addDays(mt_rand(0, $monthStart->daysInMonth - 1))->setTime(mt_rand(8, 21), mt_rand(0, 59)));
            }
        }

        // Gateway subscriptions (billing_subscriptions) — one for EVERY paying user.
        //
        // The admin Subscriptions screen UNIONs real billing rows with "synthetic" rows for
        // any user holding a plan_id but no billing record. This block used to seed a fixed
        // 19 rows against arbitrary users, so most paid users had no billing row and the
        // screen was dominated by synthetic entries reading cycle "Not set", amount
        // "Not available" and status "none". Driving the rows off the actual paid users keeps
        // users and billing_subscriptions in agreement, so every listed row is complete.
        //
        // Signup dates are spread across ~6 months (newest first) so "Recent Subscriptions"
        // stays fresh and the retention curve is continuous; cancellations drive the churn
        // chart. Deterministic ids keep this idempotent under a standalone re-seed.
        $subGateways = ['stripe', 'stripe', 'paypal', 'razorpay'];
        $subCycles = ['monthly', 'monthly', 'yearly'];
        $subAmounts = ['monthly' => 49.99, 'yearly' => 499.99];

        $paidUsers = collect($demoUsers)
            ->filter(fn ($candidate) => $candidate->subscription_status === 'active')
            ->values();

        foreach ($paidUsers as $index => $subUser) {
            $spread = max(1, $paidUsers->count() - 1);
            $daysAgo = 1 + (int) round($index * 175 / $spread);
            $cycle = $subCycles[$index % count($subCycles)];

            // A realistic book of business: mostly active, a few still trialing, a few churned.
            $status = match (true) {
                $index % 9 === 4 => 'cancelled',
                $index % 7 === 3 => 'trialing',
                default => 'active',
            };

            $createdAt = now()->subDays($daysAgo)->setTime(mt_rand(8, 20), mt_rand(0, 59));
            $periodEnd = $cycle === 'yearly' ? $createdAt->copy()->addYear() : $createdAt->copy()->addMonth();
            $cancelledAt = $status === 'cancelled'
                ? $createdAt->copy()->addDays(mt_rand(3, 20))
                : null;
            $trialEndsAt = $status === 'trialing' ? now()->addDays(mt_rand(3, 12)) : null;

            $subscription = \App\Models\GatewaySubscription::updateOrCreate(
                ['gateway_subscription_id' => 'demo-sub-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $subUser->id,
                    'plan_id' => $professionalPlan->id,
                    'billing_cycle' => $cycle,
                    'status' => $status,
                    'gateway' => $subGateways[$index % count($subGateways)],
                    'amount' => $subAmounts[$cycle],
                    'currency' => 'USD',
                    'trial_ends_at' => $trialEndsAt,
                    'current_period_start' => $createdAt,
                    'current_period_end' => $periodEnd,
                    'cancelled_at' => $cancelledAt,
                ]
            );

            // created_at/updated_at aren't fillable, so stamp them after the write.
            $subscription->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            // Keep the users row in step with its billing row — otherwise a user reads
            // "active" while their only subscription is cancelled.
            $subUser->forceFill([
                'subscription_status' => $status === 'cancelled' ? 'cancelled' : $status,
                'subscription_ends_at' => $cancelledAt ?? $periodEnd,
                'trial_ends_at' => $trialEndsAt,
            ])->save();
        }

        // Subscription volume for the dashboard's Subscription Health and Free vs Premium
        // Conversion charts.
        //
        // The block above issues one billing row per existing paid demo user — about 25
        // rows spread over six months, or roughly one point a week. At 7d and 30d that
        // draws a nearly empty chart, and with three cancellations in the entire dataset
        // the churn series had almost nothing to plot against the new-subscription line.
        // A demo has to look like a business that has customers.
        //
        // Given its own cohort of accounts so the curated demo users keep their profiles,
        // and so each of them still maps to exactly one billing row.
        $this->seedSubscriberCohort($professionalPlan, $subGateways, $subCycles, $subAmounts, $userPassword);

        // A broad country list so "Usage by Countries" shows a real map of markets. The
        // weighted index list front-loads the top markets (US/UK/India appear more often),
        // giving a realistic head-and-tail distribution rather than a flat line.
        $loginCountries = [
            ['country' => 'United States', 'city' => 'New York'],
            ['country' => 'United Kingdom', 'city' => 'London'],
            ['country' => 'India', 'city' => 'Bengaluru'],
            ['country' => 'Germany', 'city' => 'Berlin'],
            ['country' => 'Canada', 'city' => 'Toronto'],
            ['country' => 'Australia', 'city' => 'Sydney'],
            ['country' => 'Singapore', 'city' => 'Singapore'],
            ['country' => 'Brazil', 'city' => 'Sao Paulo'],
            ['country' => 'France', 'city' => 'Paris'],
            ['country' => 'Netherlands', 'city' => 'Amsterdam'],
            ['country' => 'Bangladesh', 'city' => 'Dhaka'],
            ['country' => 'Japan', 'city' => 'Tokyo'],
        ];
        $countryWeights = [0, 0, 0, 0, 1, 1, 1, 2, 2, 2, 3, 3, 4, 4, 5, 6, 7, 8, 9, 10, 11];

        // Every user + admin logs in 2-4 times across the last 90 days: rich geo coverage
        // and a healthy Direct-traffic volume in Traffic Sources, continuous across periods.
        $loginUsers = collect([$adminUser, ...$demoUsers])->filter()->values();
        foreach ($loginUsers as $user) {
            $isOauth = ! empty($user->oauth_provider);

            foreach (range(1, mt_rand(2, 4)) as $ignored) {
                $geo = $loginCountries[$countryWeights[array_rand($countryWeights)]];
                // Recency-biased rather than uniform across the 90 days, so Active Users
                // and the traffic-source panels climb toward today like the rest.
                $createdAt = now()->subDays($this->recentBiasedDaysAgo(89))->setTime(mt_rand(0, 23), mt_rand(0, 59));

                $this->backdate(LoginHistory::create([
                    'user_id' => $user->id,
                    'ip' => '192.168.' . mt_rand(10, 250) . '.' . mt_rand(10, 250),
                    'user_agent' => $isOauth
                        ? 'Mozilla/5.0 Demo OAuth Login'
                        : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) DemoBrowser/1.0',
                    'country' => $geo['country'],
                    'city' => $geo['city'],
                    'success' => true,
                ]), $createdAt);
            }
        }

        $ticketStatuses = ['open', 'in_progress', 'resolved', 'closed'];
        $ticketPriorities = ['low', 'medium', 'high'];
        $ticketSources = ['email', 'web', 'api'];
        $ticketUsers = collect(array_slice($demoUsers, 0, 10))->values();
        // assigned_to and an admin reply's author_id are both FKs to `admins`. $adminUser is
        // the demo admin's row in `users` — a different table with different ids — so it was
        // pointing these tickets at whichever admin happened to share that id.
        $supportAgent = Admin::where('email', $adminEmail)->first();
        $ticketDepartments = collect([$generalDepartment, $technicalDepartment, $billingDepartment])
            ->filter()
            ->keyBy('slug');

        // Every subject gets a full conversation arc — opener, first response, follow-up,
        // resolution — and how much of it is written depends on the ticket's status, so an
        // open ticket really is unanswered and a resolved one reads as finished. Without
        // these the admin ticket view opened on an empty thread for all 12.
        // `internal_note` is admin-only: matching SupportTicketService::addAdminReply(), it
        // never counts as the first response or the last reply.
        $ticketThreads = [
            [
                'subject' => 'Billing question about invoice timing',
                'department' => 'billing',
                'arc' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>My plan says billing happens on the 1st, but the card is charged on the 3rd and the invoice is dated the 3rd as well. Which date is the real renewal date?</p>'],
                    ['author' => 'admin', 'at' => 2, 'content' => '<p>Hello,</p><p>Your renewal date is anchored to the day you first subscribed rather than the 1st of the month, which is why it lands on the 3rd. The invoice is dated when the charge is taken, so both are correct — the "1st" on the plan page is only an example.</p><p>If you would prefer everything to line up with the calendar month, we can move your anchor date to the 1st and credit the difference.</p><p>Best regards,<br>Support Team</p>'],
                    ['author' => 'user', 'at' => 5, 'content' => '<p>That explains it. Yes please — move it to the 1st so it matches how we do our bookkeeping.</p>'],
                    ['author' => 'admin', 'at' => 20, 'content' => '<p>Hello,</p><p>Done. Your renewal is now anchored to the 1st, and a prorated credit for the two-day difference has been applied to your account — you will see it on the next invoice.</p><p>Best regards,<br>Support Team</p>'],
                ],
            ],
            [
                'subject' => 'AI response is slower than expected',
                'department' => 'technical',
                'arc' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>Generations that used to come back in about 10 seconds are now taking 40 to 60. Short tools are fine; it is the long-form ones that crawl.</p><p>Is something wrong on your side or should I change my settings?</p>'],
                    ['author' => 'admin', 'at' => 3, 'content' => '<p>Hello,</p><p>Thanks for reporting it. Long-form generations are the most sensitive to provider load, so before we dig in: which model and which tool are you using, and roughly how long is the output you ask for?</p><p>Best regards,<br>Technical Support</p>'],
                    ['author' => 'user', 'at' => 6, 'content' => '<p>Blog Post Writer, on the large model, asking for around 2,000 words each time.</p>'],
                    ['author' => 'admin', 'at' => 20, 'content' => '<p>Hello,</p><p>Confirmed — the long-context model has been running slower than usual upstream, and our queue was waiting on it rather than streaming partial output. Streaming is now enabled for that path, so text appears as it is written instead of after the full response.</p><p>For drafts, the faster model finishes a 2,000-word post in well under 20 seconds and reads almost identically. Marking this as handled.</p><p>Best regards,<br>Technical Support</p>'],
                ],
                'internal_note' => ['at' => 4, 'content' => '<p>Checked the provider latency dashboard — p95 on the long-context model is up sharply, our queue is healthy. Not an infrastructure issue on our end; streaming rollout covers it.</p>'],
            ],
            [
                'subject' => 'Need help with plan upgrade',
                'department' => 'general',
                'arc' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>I am on the monthly plan and want to switch to annual, but I renewed only a week ago. Will I be charged the full year on top of what I already paid?</p>'],
                    ['author' => 'admin', 'at' => 2, 'content' => '<p>Hello,</p><p>No — upgrades are prorated. The unused part of your current month is credited against the annual price, so you only pay the difference today, and your renewal date moves to a year from now.</p><p>You can do it yourself from <strong>Billing &rarr; Change plan</strong>, or we can switch it for you.</p><p>Best regards,<br>Support Team</p>'],
                    ['author' => 'user', 'at' => 4, 'content' => '<p>Please go ahead and switch it for me. Same card is fine.</p>'],
                    ['author' => 'admin', 'at' => 18, 'content' => '<p>Hello,</p><p>You are on the annual plan as of today. The prorated credit was applied before the charge, so the amount taken was the difference only, and your credit allowance has already been topped up to the annual tier.</p><p>Best regards,<br>Support Team</p>'],
                ],
            ],
            [
                'subject' => 'Login issue on mobile device',
                'department' => 'technical',
                'arc' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>I can sign in on my laptop, but on my phone the two-factor code is rejected every time — even a fresh one straight from the app.</p>'],
                    ['author' => 'admin', 'at' => 1, 'content' => '<p>Hello,</p><p>That pattern almost always means the phone\'s clock has drifted, since the codes are time-based. Please check that automatic date and time is enabled on the device, then try again with a newly generated code.</p><p>If you are locked out in the meantime, one of your recovery codes will get you in.</p><p>Best regards,<br>Technical Support</p>'],
                    ['author' => 'user', 'at' => 6, 'content' => '<p>That was it — the phone clock was a couple of minutes behind. Turned automatic time back on and the code worked first try.</p>'],
                    ['author' => 'admin', 'at' => 19, 'content' => '<p>Hello,</p><p>Glad that sorted it. Nothing was wrong with the account itself, so no changes were needed on our side.</p><p>One tip: keep your recovery codes somewhere safe — they are the fastest way back in if a device is ever lost.</p><p>Best regards,<br>Technical Support</p>'],
                ],
                'internal_note' => ['at' => 2, 'content' => '<p>TOTP drift on the customer\'s device, not an account lock. No failed-login spike on this user — no action needed beyond the clock fix.</p>'],
            ],
        ];

        // How much of the arc is written for each status.
        $ticketThreadDepth = ['open' => 1, 'in_progress' => 3, 'resolved' => 4, 'closed' => 4];

        for ($i = 0; $i < 12; $i++) {
            $user = $ticketUsers[$i % max(1, $ticketUsers->count())] ?? $demoUsers[0];
            $status = $ticketStatuses[$i % count($ticketStatuses)];
            // Statuses and subjects both cycle in fours, so stepping the subject by 3 keeps
            // them from pairing up the same way every time — each subject shows up in a
            // different state across the 12.
            $thread = $ticketThreads[($i * 3) % count($ticketThreads)];
            $department = $ticketDepartments[$thread['department']] ?? $ticketDepartments->first();
            $createdAt = now()->subDays(14 - $i);
            $offset = fn ($hours) => $createdAt->copy()->addHours($hours);

            $messages = array_slice($thread['arc'], 0, $ticketThreadDepth[$status]);
            $publicMessages = $messages;

            // The note only exists once an agent has picked the ticket up.
            if (isset($thread['internal_note']) && count($messages) > 1) {
                $messages[] = [...$thread['internal_note'], 'author' => 'admin', 'internal' => true];
                usort($messages, fn ($a, $b) => $a['at'] <=> $b['at']);
            }

            $lastPublic = $publicMessages[count($publicMessages) - 1];
            $firstAdminPublic = collect($publicMessages)->firstWhere('author', 'admin');
            // Support last saw the thread when it last answered — so an in_progress ticket,
            // where the customer has replied since, shows as unread in the admin queue.
            $lastAdminPublic = collect($publicMessages)->where('author', 'admin')->last();
            $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? $offset(24) : null;

            $ticket = SupportTicket::updateOrCreate(
                ['ticket_number' => 'DEMO-TKT-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'department_id' => $department?->id,
                    // An unanswered ticket has nobody on it yet — that is what the admin
                    // "Unassigned" filter is for.
                    'assigned_to' => $firstAdminPublic === null ? null : $supportAgent?->id,
                    'subject' => $thread['subject'],
                    'status' => $status,
                    'priority' => $ticketPriorities[$i % count($ticketPriorities)],
                    'source' => $ticketSources[$i % count($ticketSources)],
                    'first_response_at' => $firstAdminPublic === null ? null : $offset($firstAdminPublic['at']),
                    'resolved_at' => $resolvedAt,
                    'closed_at' => $status === 'closed' ? $offset(48) : null,
                    'last_reply_at' => $offset($lastPublic['at']),
                    'last_reply_by' => $lastPublic['author'],
                    'satisfaction_rating' => $status === 'closed' ? mt_rand(4, 5) : null,
                    'satisfaction_comment' => $status === 'closed' ? 'Issue was resolved quickly.' : null,
                    'user_last_read_at' => $offset($lastPublic['at'] + 1),
                    'admin_last_read_at' => $lastAdminPublic === null ? null : $offset($lastAdminPublic['at']),
                ]
            );

            // created_at is not fillable, so updateOrCreate silently dropped the backdating
            // these rows were written with — every "14 days ago" ticket was landing on today.
            $ticket->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $offset($lastPublic['at']),
            ])->save();

            // Idempotent on re-seed: the ticket is matched by number and its thread rewritten.
            SupportTicketReply::withTrashed()->where('ticket_id', $ticket->id)->forceDelete();

            foreach ($messages as $message) {
                $writtenAt = $offset($message['at']);

                $ticket->replies()->create([
                    'author_type' => $message['author'],
                    'author_id' => $message['author'] === 'admin' ? $supportAgent?->id : $user->id,
                    'content' => $message['content'],
                    'attachments' => null,
                    'is_internal_note' => $message['internal'] ?? false,
                    'is_ai_draft' => false,
                ])->forceFill([
                    'created_at' => $writtenAt,
                    'updated_at' => $writtenAt,
                ])->save();
            }
        }

        // ─── Support Canned Responses ────────────────────────────────
        $admin = Admin::where('email', $adminEmail)->first();
        $adminId = $admin?->id;

        $cannedResponses = [
            [
                'title' => 'Refund Policy Details',
                'department_slug' => 'billing',
                'content' => '<p>Hello,</p><p>Thank you for reaching out. Under our standard terms of service, subscription payments are non-refundable after credits have been used. However, if you haven\'t consumed any credits on your plan, we\'d be happy to process a full refund within 14 days of purchase. Please let us know if you\'d like to proceed.</p><p>Best regards,<br>Support Team</p>',
                'usage_count' => 15,
            ],
            [
                'title' => 'How to update billing information',
                'department_slug' => 'billing',
                'content' => '<p>Hello,</p><p>You can easily update your payment method directly from your dashboard:</p><ol><li>Navigate to <strong>Billing & Subscriptions</strong>.</li><li>Click on <strong>Manage Payment Method</strong>.</li><li>Enter your new card details and click save.</li></ol><p>Please let us know if you encounter any errors.</p><p>Best regards,<br>Support Team</p>',
                'usage_count' => 8,
            ],
            [
                'title' => 'API Access & Credentials Setup',
                'department_slug' => 'technical',
                'content' => '<p>Hello,</p><p>To generate your API keys, please follow these steps:</p><ul><li>Go to <strong>Settings -> Developer API</strong>.</li><li>Click on <strong>Create API Key</strong>.</li><li>Copy the generated key immediately and store it securely (it will only be shown once).</li></ul><p>Make sure to include this key in the header: <code>Authorization: Bearer YOUR_KEY</code>.</p><p>Best regards,<br>Developer Relations</p>',
                'usage_count' => 24,
            ],
            [
                'title' => 'Troubleshooting Slow Generation Speed',
                'department_slug' => 'technical',
                'content' => '<p>Hello,</p><p>AI generation speed depends on current model traffic and token sizes. If you notice unusually high latency, please try: </p><ul><li>Switching to a faster model (e.g., GPT-5.4 Mini or Gemini 3.5 Flash).</li><li>Reducing the input character length.</li><li>Ensuring your network connection is stable.</li></ul><p>Our server status page is always updated if there are outages.</p><p>Best regards,<br>Technical Support</p>',
                'usage_count' => 12,
            ],
            [
                'title' => 'Welcome & Getting Started Guide',
                'department_slug' => 'general',
                'content' => '<p>Hi there,</p><p>Welcome to MakeAI! We\'re thrilled to have you on board. To get started, you can explore the various pre-built AI templates on the dashboard or try starting an interactive chat session.</p><p>If you have any questions or need custom feature integrations, feel free to ask!</p><p>Cheers,<br>Community Manager</p>',
                'usage_count' => 45,
            ],
            [
                'title' => 'Suggesting a Feature or New AI Tool',
                'department_slug' => 'general',
                'content' => '<p>Hello,</p><p>Thank you for your suggestion! We love hearing from our community about what tools we should build next. I have shared this request directly with our product team for evaluation.</p><p>Feel free to keep suggesting new templates!</p><p>Best regards,<br>MakeAI Product Team</p>',
                'usage_count' => 19,
            ],
        ];

        foreach ($cannedResponses as $cr) {
            $dept = SupportDepartment::where('slug', $cr['department_slug'])->first();
            \App\Models\SupportCannedResponse::updateOrCreate(
                ['title' => $cr['title']],
                [
                    'content' => $cr['content'],
                    'department_id' => $dept?->id,
                    'created_by' => $adminId,
                    'usage_count' => $cr['usage_count'],
                ]
            );
        }

        // Comments on EVERY published post (previously only the newest 6 got one each, so the
        // blog and the post editor's stats card read 0 almost everywhere). Each post gets a
        // handful of threaded discussions: mostly approved, with some pending/spam so the
        // moderation queue has something to show, plus a few guest comments.
        if ($blogPosts->isNotEmpty()) {
            $topLevelComments = [
                'This looks polished and useful.',
                'Great breakdown, thanks for sharing.',
                'Would love to see more examples like this.',
                'The workflow is clear and actionable.',
                'Bookmarked this one — exactly what I needed for a client project.',
                'Solid write-up. The section on implementation answered my main question.',
                'I have been looking for a clear explanation of this for weeks. Thank you!',
                'Any chance you could expand on the cost side of this in a follow-up?',
                'We rolled out something similar last quarter and saw comparable results.',
                'The examples make this much easier to follow than most guides on the topic.',
                'Curious how this holds up at a larger scale — has anyone tried it?',
                'Sharing this with my team, it lines up with what we are planning.',
                'Really practical advice. Implemented the first two steps already.',
                'Good read. Would love a downloadable checklist version of this.',
            ];

            $replyComments = [
                'Agreed — the same approach worked well for us.',
                'Thanks for reading! A follow-up on this is already in the works.',
                'Good question. It scales fine in our experience, just watch the rate limits.',
                'Same here. The setup took about an afternoon end to end.',
                'Glad it helped! Let us know how the rollout goes.',
                'That matches our numbers almost exactly.',
                'You can automate that step — worth doing if you run it weekly.',
                'Great point, I had not considered that trade-off.',
            ];

            $guestAuthors = [
                ['name' => 'Marcus Reed', 'email' => 'marcus.reed@example.com'],
                ['name' => 'Priya Nair', 'email' => 'priya.nair@example.org'],
                ['name' => 'Tomas Vidal', 'email' => 'tomas.vidal@example.net'],
                ['name' => 'Hannah Brooks', 'email' => 'hannah.brooks@example.com'],
            ];

            // Spam rows get their own voice and throwaway senders so the moderation queue
            // and the report reasons actually match the content being flagged.
            $spamComments = [
                'Make $5000/week from home with this ONE simple trick — click my profile!',
                'Best SEO backlinks cheap!!! visit cheap-seo-backlinks-deals dot example now',
                'Free crypto giveaway, send 0.1 BTC and receive 1 BTC back guaranteed!!!',
                'buy followers instagram tiktok cheap fast delivery contact whatsapp +000000',
                'Check out my site for FREE premium accounts, no survey no password!!!',
                'Great post!!! Also visit my page for discount designer bags and watches.',
            ];

            $spamAuthors = [
                ['name' => 'BestDeals2026', 'email' => 'promo@spam-example.test'],
                ['name' => 'CryptoKing', 'email' => 'offers@spam-example.test'],
                ['name' => 'SEO Master', 'email' => 'backlinks@spam-example.test'],
            ];

            // Weighted so the vast majority are visible; the rest exercise moderation.
            $statusPool = array_merge(array_fill(0, 15, 'approved'), ['pending', 'pending', 'spam']);
            $contentIndex = 0;
            $replyIndex = 0;
            $guestIndex = 0;
            $spamIndex = 0;

            foreach ($blogPosts as $postIndex => $post) {
                // Comments must land AFTER the post went live and before now — otherwise a
                // year-old post shows discussion that predates it.
                $publishedAt = now()->parse($post->published_at ?? $post->created_at ?? now()->subDays(30));
                // Offset within the publish→now window (in minutes) rather than picking a
                // date then clamping: clamping could land the comment before publication.
                $windowMinutes = max(1, (int) $publishedAt->diffInMinutes(now()));

                foreach (range(1, mt_rand(2, 6)) as $ignored) {
                    $createdAt = $publishedAt->copy()->addMinutes(mt_rand(1, $windowMinutes));

                    // Status first: a row marked spam must READ like spam, otherwise the
                    // moderation queue shows "Great breakdown, thanks!" flagged as
                    // promotional junk, and the report reasons make no sense against it.
                    $status = $statusPool[array_rand($statusPool)];
                    $isSpam = $status === 'spam';

                    // Spammers post logged-out; roughly one in seven genuine comments is
                    // also from a visitor.
                    $isGuest = $isSpam || mt_rand(1, 7) === 1;
                    $guest = $isSpam
                        ? $spamAuthors[$guestIndex++ % count($spamAuthors)]
                        : $guestAuthors[$guestIndex++ % count($guestAuthors)];

                    $comment = Comment::create([
                        'commentable_type' => BlogPost::class,
                        'commentable_id' => $post->id,
                        'user_id' => $isGuest ? null : $demoUsers[($postIndex + $contentIndex) % count($demoUsers)]->id,
                        'guest_name' => $isGuest ? $guest['name'] : null,
                        'guest_email' => $isGuest ? $guest['email'] : null,
                        'content' => $isSpam
                            ? $spamComments[$spamIndex++ % count($spamComments)]
                            : $topLevelComments[$contentIndex++ % count($topLevelComments)],
                        'status' => $status,
                        // Spam does not organically attract likes.
                        'likes_count' => $isSpam ? 0 : mt_rand(0, 24),
                    ]);

                    $this->backdate($comment, $createdAt);

                    // ~40% of visible comments start a thread.
                    if ($comment->status !== 'approved' || mt_rand(1, 10) > 4) {
                        continue;
                    }

                    // Same rule as above: land the reply inside the parent→now window so it
                    // can never predate the comment it answers (capped at ~60h for realism).
                    $replyWindow = max(1, min((int) $createdAt->diffInMinutes(now()), 3600));

                    foreach (range(1, mt_rand(1, 2)) as $ignored2) {
                        $replyAt = $createdAt->copy()->addMinutes(mt_rand(1, $replyWindow));

                        $this->backdate(Comment::create([
                            'commentable_type' => BlogPost::class,
                            'commentable_id' => $post->id,
                            'parent_id' => $comment->id,
                            'user_id' => $demoUsers[($postIndex + $replyIndex + 5) % count($demoUsers)]->id,
                            'content' => $replyComments[$replyIndex++ % count($replyComments)],
                            'status' => 'approved',
                            'likes_count' => mt_rand(0, 12),
                        ]), $replyAt);
                    }
                }
            }
        }

        // Comment reports. The moderation screen shows a reports count, a red "Reported"
        // badge and the list of reasons, but nothing ever seeded the comment_reports table,
        // so every comment read "0 Reports". Mirrors the real write path in
        // CommentController::report — a logged-in reporter stores user_id with a null
        // ip_hash, a guest stores a hashed IP with a null user_id.
        // Reasons are split by what is actually being flagged, so a promotional spam row is
        // not reported for "harassment" and vice versa.
        $spamReportReasons = [
            'Spam or misleading promotional content.',
            'Contains an unrelated affiliate link.',
            'This looks like an automated bot posting.',
            'Sharing a suspicious external download link.',
            'Advertising an unrelated product or service.',
        ];

        $generalReportReasons = [
            'Off-topic — nothing to do with the article.',
            'Duplicate of a comment already posted below.',
            'Offensive language directed at another commenter.',
            'Harassment of the author in the reply thread.',
            'Misinformation about how the product works.',
        ];

        // Every spam row is reported — reports are what got it moderated in the first place.
        // Approved rows are a random sample: legitimate comments people disputed, which is
        // the queue an admin actually has to triage (only approved comments are reportable
        // in the app; CommentController::report() 404s on anything else).
        $reportableComments = Comment::where('status', 'spam')->get()
            ->merge(Comment::where('status', 'approved')->inRandomOrder()->limit(24)->get());

        $reasonIndex = 0;

        foreach ($reportableComments as $comment) {
            // A comment already flagged as spam drew more complaints than a merely
            // disputed one.
            $reportCount = $comment->status === 'spam' ? mt_rand(2, 4) : mt_rand(1, 3);

            // comment_reports is unique on (comment_id, user_id) AND (comment_id, ip_hash),
            // so each reporter for a given comment must be distinct.
            $reporters = collect($demoUsers)->shuffle()->take($reportCount)->values();
            $commentAt = now()->parse($comment->created_at);
            $window = max(1, (int) $commentAt->diffInMinutes(now()));

            foreach ($reporters as $position => $reporter) {
                // Land the report inside the comment→now window so it can never predate
                // the comment it flags (same rule as the comment/reply timestamps above).
                $reportedAt = $commentAt->copy()->addMinutes(mt_rand(1, min($window, 10080)));

                // Roughly every third report comes from a logged-out visitor.
                $isGuestReport = $position % 3 === 2;

                $this->backdate(CommentReport::updateOrCreate(
                    [
                        'comment_id' => $comment->id,
                        'user_id' => $isGuestReport ? null : $reporter->id,
                        'ip_hash' => $isGuestReport
                            ? hash('sha256', 'demo-reporter-' . $comment->id . '-' . $position . '|' . config('app.key'))
                            : null,
                    ],
                    [
                        'reason' => $comment->status === 'spam'
                            ? $spamReportReasons[$reasonIndex++ % count($spamReportReasons)]
                            : $generalReportReasons[$reasonIndex++ % count($generalReportReasons)],
                    ]
                ), $reportedAt);
            }
        }

        $affiliatePairs = [
            [$recentOauthUsers[0] ?? $adminUser, $demoUsers[13] ?? null, 'AFF-DEMO-001'],
            [$recentOauthUsers[1] ?? $adminUser, $demoUsers[14] ?? null, 'AFF-DEMO-002'],
            [$recentOauthUsers[2] ?? $adminUser, $demoUsers[15] ?? null, 'AFF-DEMO-003'],
            [$recentOauthUsers[3] ?? $adminUser, $demoUsers[16] ?? null, 'AFF-DEMO-004'],
        ];

        foreach ($affiliatePairs as [$referrer, $referred, $code]) {
            if (! $referrer || ! $referred) {
                continue;
            }

            $landedAt = now()->subDays(mt_rand(2, 24));

            $this->backdate(AffiliateReferral::updateOrCreate(
                ['referral_code' => $code],
                [
                    'referrer_id' => $referrer->id,
                    'referred_id' => $referred->id,
                    'ip_address' => '172.16.' . mt_rand(10, 250) . '.' . mt_rand(10, 250),
                    'landed_at' => $landedAt,
                    'converted_at' => $landedAt->copy()->addHours(mt_rand(1, 48)),
                ]
            ), $landedAt);
        }

        // Revenue and signup charts are computed directly from the seeded
        // payments/users rows above (see AdminDashboardController), so no
        // synthetic demo_revenue_*/demo_signups_* settings keys are needed.

        // ─── 10. Newsletter Campaigns (5 sample) ────────────────────────
        $campaigns = [
            ['subject' => 'Welcome to MakeAI — Start Creating with AI', 'status' => 'sent'],
            ['subject' => 'New Feature: Advanced Chat Mode Is Here', 'status' => 'sent'],
            ['subject' => 'Monthly AI Tips & Tricks Newsletter', 'status' => 'sent'],
            ['subject' => 'Exclusive: Early Access to GPT-5 Integration', 'status' => 'sent'],
            ['subject' => 'Holiday Special: 30% Off Pro Plans', 'status' => 'sent'],
        ];

        foreach ($campaigns as $i => $campaign) {
            // Funnel must narrow: recipients >= sent >= opened. Independent random
            // ranges let sent_count exceed recipient_count, which reads as broken
            // analytics on the campaign report.
            $recipientCount = mt_rand(600, 2000);
            $sentCount = mt_rand((int) round($recipientCount * 0.9), $recipientCount);
            $openedCount = mt_rand((int) round($sentCount * 0.25), (int) round($sentCount * 0.6));

            DB::table('newsletter_campaigns')->updateOrInsert(
                ['subject' => $campaign['subject']],
                [
                    'content' => '<h2>' . $campaign['subject'] . '</h2>'
                        . '<p>' . $this->loremParagraph() . '</p>'
                        . '<p>' . $this->loremParagraph() . '</p>',
                    'status' => $campaign['status'],
                    'recipient_count' => $recipientCount,
                    'sent_count' => $sentCount,
                    'opened_count' => $openedCount,
                    'sent_at' => now()->subDays(($i + 1) * 30),
                    'started_at' => now()->subDays(($i + 1) * 30),
                    'finished_at' => now()->subDays(($i + 1) * 30)->addHours(2),
                    'audience' => 'subscribers',
                    'created_at' => now()->subDays(($i + 1) * 30),
                ]
            );
        }

        // ─── 12. Sample Pages ───────────────────────────────────────────
        // Intentionally EMPTY. The CMS pages ship in PageSeeder (about, terms-of-service,
        // privacy-policy, contact, faq, cookie-policy, usage-policy) and are seeded on every
        // install. This seeder used to re-create near-identical copies under DIFFERENT slugs
        // ('about-us' and 'terms'), so a demo listed two About and two Terms pages in the CMS
        // admin. Menus below now point at the canonical PageSeeder slugs.

        // ─── 13. Advertisements — one creative per configured zone ──────
        //
        // Every zone in config('ads.zones') gets a creative, so a buyer can preview each
        // placement without building one first. All of them promote buying MakeAI on
        // CodeCanyon, which is what a demo's ad slots should actually be selling.
        //
        // Banners are written to the PUBLIC STORAGE DISK and referenced by relative key
        // ("ads/foo.svg"). That matters: image_url is varchar(500), which a base64 data URI
        // of a real design blows past, and a "/assets/..." public path only resolves in the
        // JS mediaUrl() helper — the PHP media_url() would rewrite it to "/storage/assets/..."
        // and break. A stored key is the one form BOTH helpers resolve identically. The SVGs
        // are generated here rather than shipped, so any install can seed them.
        $envatoUrl = (string) (config('demo.envato_url') ?: 'https://codecanyon.net');

        $writeBanner = function (string $name, int $width, int $height) use ($envatoUrl): string {
            // Layout adapts to the slot: wide strips put the CTA beside the wordmark, the
            // square rectangle stacks it underneath.
            $isWide = $width >= 400 && $height <= 120;
            $isTall = $height >= 200;

            $radius = 10;
            $font = 'Segoe UI,Arial,sans-serif';

            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Buy MakeAI on CodeCanyon">'
                . '<defs>'
                . '<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">'
                . '<stop offset="0" stop-color="#0f172a"/><stop offset="0.55" stop-color="#1e3a8a"/><stop offset="1" stop-color="#6d28d9"/>'
                . '</linearGradient>'
                . '<linearGradient id="glow" x1="0" y1="0" x2="1" y2="0">'
                . '<stop offset="0" stop-color="#22d3ee" stop-opacity="0.55"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/>'
                . '</linearGradient>'
                // Everything is clipped to the rounded card. Without this the left accent
                // bar and the glow circle rendered square corners that poked outside the
                // rounded edge, so the banner did not sit flush in its slot.
                . '<clipPath id="card"><rect width="' . $width . '" height="' . $height . '" rx="' . $radius . '"/></clipPath>'
                . '</defs>'
                . '<g clip-path="url(#card)">'
                . '<rect width="' . $width . '" height="' . $height . '" fill="url(#bg)"/>'
                . '<circle cx="' . ($width - 30) . '" cy="' . ($height - 20) . '" r="' . (int) ($height * 0.8) . '" fill="url(#glow)" opacity="0.35"/>'
                . '<rect x="0" y="0" width="5" height="' . $height . '" fill="#82b541"/>';

            if ($isTall) {
                // 300x250 rectangle — stacked, with room for a feature list.
                $mid = (int) ($width / 2);

                $svg .= '<text x="' . $mid . '" y="58" fill="#ffffff" font-family="' . $font . '" font-size="34" font-weight="700" text-anchor="middle">MakeAI</text>'
                    . '<text x="' . $mid . '" y="82" fill="#bfdbfe" font-family="' . $font . '" font-size="11" letter-spacing="2" text-anchor="middle">AI SAAS PLATFORM</text>'
                    . '<text x="' . $mid . '" y="116" fill="#e2e8f0" font-family="' . $font . '" font-size="12.5" text-anchor="middle">400+ AI tools &amp; templates</text>'
                    . '<text x="' . $mid . '" y="136" fill="#e2e8f0" font-family="' . $font . '" font-size="12.5" text-anchor="middle">Intelligent AI assistant</text>'
                    . '<text x="' . $mid . '" y="156" fill="#e2e8f0" font-family="' . $font . '" font-size="12.5" text-anchor="middle">Affiliates, coupons &amp; billing</text>'
                    . '<text x="' . $mid . '" y="176" fill="#e2e8f0" font-family="' . $font . '" font-size="12.5" text-anchor="middle">Full admin panel included</text>'
                    . '<rect x="' . ($mid - 90) . '" y="196" width="180" height="38" rx="19" fill="#82b541"/>'
                    . '<text x="' . $mid . '" y="221" fill="#0f172a" font-family="' . $font . '" font-size="15" font-weight="700" text-anchor="middle">Buy on CodeCanyon</text>';
            } elseif ($isWide) {
                // 728x90 / 468x60 strips — wordmark left, CTA right.
                $isLarge = $height >= 80;
                $ctaWidth = $width >= 600 ? 200 : 150;
                $ctaX = $width - $ctaWidth - 22;
                $mid = (int) ($height / 2);

                if ($isLarge) {
                    // Three stacked lines: wordmark, pitch, then the feature strip.
                    $svg .= '<text x="26" y="34" fill="#ffffff" font-family="' . $font . '" font-size="27" font-weight="700">MakeAI</text>'
                        . '<text x="26" y="56" fill="#bfdbfe" font-family="' . $font . '" font-size="13">Launch your own AI SaaS today</text>'
                        . '<text x="26" y="76" fill="#c7d2fe" font-family="' . $font . '" font-size="11.5">400+ AI tools &#183; Intelligent AI assistant &#183; Affiliates &#183; Coupons &#183; Billing</text>';
                } else {
                    // 468x60 has room for the wordmark plus one condensed feature line.
                    $svg .= '<text x="20" y="26" fill="#ffffff" font-family="' . $font . '" font-size="20" font-weight="700">MakeAI</text>'
                        . '<text x="20" y="44" fill="#c7d2fe" font-family="' . $font . '" font-size="10.5">400+ AI tools &#183; Assistant &#183; Affiliates &#183; Coupons</text>';
                }

                $svg .= '<rect x="' . $ctaX . '" y="' . ($mid - 18) . '" width="' . $ctaWidth . '" height="36" rx="18" fill="#82b541"/>'
                    . '<text x="' . (int) ($ctaX + $ctaWidth / 2) . '" y="' . ($mid + 6) . '" fill="#0f172a" font-family="' . $font . '" font-size="' . ($width >= 600 ? 15 : 12) . '" font-weight="700" text-anchor="middle">Buy on CodeCanyon</text>';
            }

            $svg .= '</g></svg>';

            $key = 'ads/' . $name . '.svg';
            Storage::disk('public')->put($key, $svg);

            return $key;
        };

        // Gradient purchase block for the custom_html slots. Inline styles only — ad markup
        // is injected raw into pages that do not share the admin stylesheet.
        $purchaseHtml = function (string $headline, string $sub) use ($envatoUrl): string {
            return '<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 55%,#6d28d9 100%);border-radius:12px;padding:22px 20px;text-align:center;color:#fff;font-family:Segoe UI,Arial,sans-serif">'
                . '<div style="font-size:11px;letter-spacing:2px;color:#bfdbfe;text-transform:uppercase">Powered by MakeAI</div>'
                . '<div style="font-size:19px;font-weight:700;margin-top:6px">' . $headline . '</div>'
                . '<div style="font-size:13px;color:#e2e8f0;margin-top:6px;line-height:1.5">' . $sub . '</div>'
                . '<a href="' . $envatoUrl . '" target="_blank" rel="noopener nofollow sponsored" '
                . 'style="display:inline-block;margin-top:14px;background:#82b541;color:#0f172a;font-weight:700;font-size:13px;padding:10px 22px;border-radius:999px;text-decoration:none">'
                . 'Buy now on CodeCanyon</a></div>';
        };

        // [zone, title, type, show_to, state, banner name, width, height, headline, sub]
        $adRows = [
            ['header_banner', 'Header Leaderboard', 'image_link', 'all', 'expired', 'makeai-leaderboard', 728, 90, '', ''],
            ['footer_banner', 'Footer Leaderboard', 'image_link', 'all', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            ['sidebar_top', 'Sidebar Rectangle', 'image_link', 'all', 'live', 'makeai-rectangle', 300, 250, '', ''],
            ['sidebar_bottom', 'Sidebar Purchase Block', 'custom_html', 'all', 'live', '', 0, 0, 'Get MakeAI for your business', 'The complete AI SaaS platform — 400+ AI tools, an intelligent AI assistant, affiliates, coupons and billing, with a full admin panel.'],
            ['between_posts', 'Between Posts Leaderboard', 'image_link', 'all', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            ['blog_after_content', 'After Post Leaderboard', 'image_link', 'all', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            ['between_ai_tools', 'Tools Feed Leaderboard', 'image_link', 'all', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            ['tool_page_top', 'Tool Page Leaderboard', 'image_link', 'guests', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            ['tool_page_bottom', 'Before Tabs Leaderboard', 'image_link', 'all', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            ['chat_banner', 'Chat Purchase Block', 'custom_html', 'free_users', 'live', '', 0, 0, 'Launch your own AI chat', 'Streaming chat, credits and BYOK come ready to sell.'],
            ['dashboard_top', 'Dashboard Leaderboard', 'image_link', 'logged_in', 'live', 'makeai-leaderboard', 728, 90, '', ''],
            // A configured AdSense unit and a retired campaign, so both paths and the
            // scheduled / expired states are represented in the admin list.
            ['custom_zone_1', 'AdSense Display Unit', 'adsense', 'all', 'scheduled', '', 0, 0, '', ''],
            ['custom_zone_2', 'Retired Launch Campaign', 'image_link', 'paid_users', 'expired', 'makeai-rectangle', 300, 250, '', ''],
        ];

        $bannerKeys = [];

        foreach ($adRows as $sortOrder => [$zone, $title, $type, $showTo, $state, $banner, $w, $h, $headline, $sub]) {
            [$startAt, $endAt, $isActive] = match ($state) {
                'scheduled' => [now()->addDays(5), now()->addDays(35), true],
                'expired' => [now()->subDays(60), now()->subDays(30), false],
                // Seeded and in-window, but toggled off so it does not render yet.
                'paused' => [now()->subDays(mt_rand(5, 40)), now()->addDays(mt_rand(20, 90)), false],
                default => [now()->subDays(mt_rand(5, 40)), now()->addDays(mt_rand(20, 90)), true],
            };

            $imageKey = null;

            if ($type === 'image_link') {
                // Each size is generated once and shared by the zones that use it.
                $bannerKeys[$banner] ??= $writeBanner($banner, $w, $h);
                $imageKey = $bannerKeys[$banner];
            }

            $createdAt = $startAt->copy()->subDay();

            DB::table('ads')->updateOrInsert(
                ['zone' => $zone],
                [
                    'title' => $title,
                    'type' => $type,
                    'adsense_client' => $type === 'adsense' ? 'ca-pub-1234567890123456' : null,
                    'adsense_slot' => $type === 'adsense' ? '9876543210' : null,
                    'adsense_format' => $type === 'adsense' ? 'auto' : null,
                    'custom_html' => $type === 'custom_html' ? $purchaseHtml($headline, $sub) : null,
                    'image_url' => $imageKey,
                    'link_url' => $type === 'image_link' ? $envatoUrl : null,
                    'link_target' => $type === 'image_link' ? '_blank' : '_self',
                    'show_to' => $showTo,
                    'is_active' => $isActive,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    // A retired campaign keeps its lifetime totals; a scheduled one has none yet.
                    'impressions' => $state === 'scheduled' ? 0 : mt_rand(500, 9000),
                    'clicks' => $state === 'scheduled' ? 0 : mt_rand(15, 260),
                    'sort_order' => $sortOrder,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
        }

        // ─── 14. Menus (/admin/appearance/menus) ────────────────────────
        //
        // Four menus, because four regions of the site each read one by slug and each was
        // resolving to nothing:
        //
        //   main            the desktop header
        //   mobile          the hamburger drawer — AppHeader falls back to this exact slug
        //                   (`mobileHamburgerBlock.config.menu_slug || 'mobile'`), so with no
        //                   such menu the drawer opened empty on every phone
        //   footer          the footer link column
        //   knowledge-base  the help centre's own nav
        //
        // Item `type` is one of url|page|route and `requires_auth` one of none|guest|auth|pro
        // (MenuItemRequest). Route-type items name a route that must exist — `home`,
        // `pricing`, `ai.tools.index`, `blog.index` — while anything belonging to an addon is
        // url-type on purpose: the Knowledge Base is deactivatable, and a route() call for a
        // route an inactive addon never registered throws on render.
        $pageId = fn (string $slug) => Page::where('slug', $slug)->value('id');

        // The help centre's URL prefix is admin-configurable, so it is read rather than
        // assumed — hardcoding /help would break every KB link the moment it is renamed.
        $kb = '/'.trim(
            function_exists('addon_setting')
                ? (string) (addon_setting('ai-knowledge-base', 'public_slug', 'help') ?: 'help')
                : 'help',
            '/'
        );

        // The main menu's mega panel, built from the catalogue rather than a hardcoded list.
        //
        // AppHeader renders a mega item as a THREE-level tree: the flagged parent is the
        // trigger, its children become the columns (each column's own label is drawn as the
        // heading), and the grandchildren are the links. A column with no children collapses
        // to a single link instead of a headed list, so an empty category would silently
        // change the panel's shape.
        //
        // Four columns exactly, because MEGA_MAX_COLUMNS is 4 and `megaColumns()` slices
        // anything beyond it — a fifth category would be written to the database, shown in
        // the admin menu builder, and then never rendered.
        $megaColumns = [];

        foreach (\App\Models\Category::query()->aiTools()->orderBy('sort_order')->limit(4)->get() as $category) {
            // Ordered by the catalogue's own sort, NOT by usage_count: this section runs
            // before 15b writes those counts, so on a fresh database they are all 0 and on a
            // re-seed they are not — the panel would list different tools each reset.
            $tools = AiTool::where('category_id', $category->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(5)
                ->get(['slug', 'name', 'description']);

            if ($tools->isEmpty()) {
                continue;
            }

            $megaColumns[] = [
                'label' => $category->name,
                'type' => 'url',
                'url' => '/ai-tools/category/'.$category->slug,
                'children' => $tools->map(fn ($tool) => [
                    'label' => $tool->name,
                    'type' => 'url',
                    'url' => '/ai-tools/'.$tool->slug,
                    // Rendered under the link by menuItemDescription(); trimmed because the
                    // panel truncates to one line anyway.
                    'description' => \Illuminate\Support\Str::limit((string) $tool->description, 60),
                ])->all(),
            ];
        }

        $menus = [
            'main' => ['name' => 'Main Menu', 'items' => [
                ['label' => 'Home', 'type' => 'route', 'route_name' => 'home', 'icon' => 'ti ti-home'],
                // Mega only once there are columns to show — flagged with an empty submenu
                // it renders as an empty panel on hover.
                ['label' => 'AI Tools', 'type' => 'route', 'route_name' => 'ai.tools.index', 'icon' => 'ti ti-sparkles',
                    'mega_menu' => $megaColumns !== [], 'children' => $megaColumns],
                ['label' => 'Pricing', 'type' => 'route', 'route_name' => 'pricing', 'icon' => 'ti ti-tag'],
                ['label' => 'Blog', 'type' => 'route', 'route_name' => 'blog.index', 'icon' => 'ti ti-news'],
                ['label' => 'Help Center', 'type' => 'url', 'url' => $kb, 'icon' => 'ti ti-lifebuoy'],
                ['label' => 'About', 'type' => 'page', 'page' => 'about', 'icon' => 'ti ti-info-circle', 'badge_text' => 'New', 'badge_color' => 'green'],
            ]],

            'mobile' => ['name' => 'Mobile Menu', 'items' => [
                ['label' => 'Home', 'type' => 'route', 'route_name' => 'home', 'icon' => 'ti ti-home'],
                ['label' => 'AI Tools', 'type' => 'route', 'route_name' => 'ai.tools.index', 'icon' => 'ti ti-sparkles'],
                ['label' => 'Pricing', 'type' => 'route', 'route_name' => 'pricing', 'icon' => 'ti ti-tag'],
                ['label' => 'Blog', 'type' => 'route', 'route_name' => 'blog.index', 'icon' => 'ti ti-news'],
                ['label' => 'Help Center', 'type' => 'url', 'url' => $kb, 'icon' => 'ti ti-lifebuoy'],
                // The drawer is the only nav on a phone, so it carries the account links the
                // desktop header keeps in its avatar dropdown — split by auth state so a
                // signed-out visitor is not shown a dashboard they cannot open.
                ['label' => 'Dashboard', 'type' => 'url', 'url' => '/user/dashboard', 'icon' => 'ti ti-layout-dashboard', 'requires_auth' => 'auth'],
                ['label' => 'Sign in', 'type' => 'url', 'url' => '/login', 'icon' => 'ti ti-login', 'requires_auth' => 'guest'],
                ['label' => 'Create account', 'type' => 'url', 'url' => '/register', 'icon' => 'ti ti-user-plus', 'requires_auth' => 'guest'],
            ]],

            'footer' => ['name' => 'Footer Menu', 'items' => [
                ['label' => 'About', 'type' => 'page', 'page' => 'about'],
                ['label' => 'Pricing', 'type' => 'route', 'route_name' => 'pricing'],
                ['label' => 'Blog', 'type' => 'route', 'route_name' => 'blog.index'],
                ['label' => 'Privacy', 'type' => 'page', 'page' => 'privacy-policy'],
                ['label' => 'Terms', 'type' => 'page', 'page' => 'terms-of-service'],
                ['label' => 'Usage Policy', 'type' => 'page', 'page' => 'usage-policy'],
            ]],

            // These two slugs are not arbitrary: resolveFooterMenuSlug() in AppFooter maps
            // the footer picker's 'company' / 'legal' options onto exactly these strings, so
            // a menu named anything else can never be selected from that dropdown.
            // `footer.menu_column` already defaults to 'footer-company', which until now
            // pointed at a menu that did not exist — hence the empty footer link column.
            'footer-company' => ['name' => 'Footer — Company', 'items' => [
                ['label' => 'About', 'type' => 'page', 'page' => 'about'],
                ['label' => 'Pricing', 'type' => 'route', 'route_name' => 'pricing'],
                ['label' => 'AI Tools', 'type' => 'route', 'route_name' => 'ai.tools.index'],
                ['label' => 'Blog', 'type' => 'route', 'route_name' => 'blog.index'],
                ['label' => 'Help Center', 'type' => 'url', 'url' => $kb],
                ['label' => 'Contact', 'type' => 'page', 'page' => 'contact'],
            ]],

            'footer-legal' => ['name' => 'Footer — Legal', 'items' => [
                ['label' => 'Privacy Policy', 'type' => 'page', 'page' => 'privacy-policy'],
                ['label' => 'Terms of Service', 'type' => 'page', 'page' => 'terms-of-service'],
                ['label' => 'Usage Policy', 'type' => 'page', 'page' => 'usage-policy'],
                ['label' => 'Cookie Policy', 'type' => 'page', 'page' => 'cookie-policy'],
            ]],

            // Only the routes the public KB actually exposes: home, the article index, and
            // a single article. There is no /category/{slug} route — an earlier draft
            // pointed two items at one and both would have 404'd.
            'knowledge-base' => ['name' => 'Knowledge Base Menu', 'items' => [
                ['label' => 'Help Center', 'type' => 'url', 'url' => $kb, 'icon' => 'ti ti-lifebuoy'],
                ['label' => 'All Articles', 'type' => 'url', 'url' => $kb.'/articles', 'icon' => 'ti ti-article'],
                ['label' => 'Blog', 'type' => 'route', 'route_name' => 'blog.index', 'icon' => 'ti ti-news'],
                ['label' => 'Contact Support', 'type' => 'url', 'url' => '/user/dashboard/support', 'icon' => 'ti ti-message-circle', 'requires_auth' => 'auth'],
            ]],
        ];

        // Bind the menus to the regions that render them.
        //
        // The header exposes ONE source setting (`desktop.menu_source`) that both the
        // desktop nav and the off-canvas drawer read, but each falls back differently when
        // it is unset: AppHeader resolves the desktop nav with a 'main' fallback and the
        // hamburger with a 'mobile' one. Leaving it empty is therefore not "unconfigured",
        // it is the only way to get a DIFFERENT menu into each region — the shipped
        // 'primary' pins both to `main`, which is why the drawer was showing desktop links.
        // Merged into what is already stored, not passed alone: save*Settings() writes the
        // whole blob, so a bare ['desktop' => …] would blank any mobile_top/mobile_bottom
        // overrides already configured.
        $themeSettings = app(\App\Services\ThemeSettingsService::class);

        $storedHeader = $themeSettings->getStoredHeaderSettings();
        $storedHeader['desktop'] = array_replace(
            is_array($storedHeader['desktop'] ?? null) ? $storedHeader['desktop'] : [],
            ['menu_source' => '']
        );
        $themeSettings->saveHeaderSettings($storedHeader);

        // Footer bottom bar — the strip beside the copyright. `menu_column` (the link
        // column higher up) is left on its own default.
        $themeSettings->saveFooterSettings(array_replace(
            $themeSettings->getStoredFooterSettings(),
            ['bottom_menu' => 'footer']
        ));

        // Recursive so the mega panel's columns and their links are written by the same
        // path as a flat item. Keyed on (menu, parent, label) rather than (menu, label):
        // nested labels only have to be unique among their siblings, and a tool named after
        // a top-level entry would otherwise overwrite it.
        $writeItem = function (array $item, int $menuId, ?int $parentId, int $position) use (&$writeItem, $pageId): void {
            // A page-type item whose Page was never seeded would render as a dead link, so
            // it is skipped rather than written with a null page_id.
            $page = isset($item['page']) ? $pageId($item['page']) : null;

            if (isset($item['page']) && ! $page) {
                return;
            }

            $row = MenuItem::updateOrCreate(
                ['menu_id' => $menuId, 'parent_id' => $parentId, 'label' => $item['label']],
                [
                    'description' => $item['description'] ?? null,
                    'type' => $item['type'],
                    'url' => $item['url'] ?? null,
                    'page_id' => $page,
                    'route_name' => $item['route_name'] ?? null,
                    'target' => '_self',
                    'icon' => $item['icon'] ?? null,
                    'badge_text' => $item['badge_text'] ?? null,
                    'badge_color' => $item['badge_color'] ?? null,
                    'is_active' => true,
                    'requires_auth' => $item['requires_auth'] ?? 'none',
                    'mega_menu' => $item['mega_menu'] ?? false,
                    'sort_order' => $position + 1,
                ]
            );

            foreach ($item['children'] ?? [] as $childPosition => $child) {
                $writeItem($child, $menuId, $row->id, $childPosition);
            }
        };

        foreach ($menus as $menuSlug => $menu) {
            $menuRow = Menu::firstOrCreate(['slug' => $menuSlug], ['name' => $menu['name']]);

            foreach ($menu['items'] as $position => $item) {
                $writeItem($item, $menuRow->id, null, $position);
            }
        }

        // ─── 14b. Sidebar widgets (/admin/appearance/sidebar) ───────────
        $this->seedSidebarWidgets();

        // ─── 15. Sample Tool Reviews ────────────────────────────────────
        $reviewComments = [
            5 => [
                'Incredible tool! Saved me hours of copywriting work.',
                'The quality of outputs is top-notch, highly recommended.',
                'Blown away by how creative the suggestions are!',
            ],
            4 => [
                'Very good quality, just needs a bit of editing before publishing.',
                'Solid performance. The interface is clean and easy to navigate.',
                'Helped me break my writer\'s block. Will use it daily.',
            ],
            3 => [
                'Decent results, but sometimes repetitive.',
                'Average tool. Good for quick drafts but not advanced research.',
            ],
            2 => [
                'The generated copy was a bit robotic and generic.',
            ],
        ];

        for ($i = 0; $i < 15; $i++) {
            $user = $demoUsers[$i % count($demoUsers)];
            $toolSlug = $toolSlugs[$i % count($toolSlugs)];
            $rating = [5, 5, 4, 4, 4, 3, 3, 2][$i % 8];
            $commentList = $reviewComments[$rating];
            $comment = $commentList[array_rand($commentList)];

            $review = $this->backdate(\App\Models\ToolReview::updateOrCreate(
                ['user_id' => $user->id, 'tool_slug' => $toolSlug],
                [
                    'rating' => $rating,
                    'comment' => $comment,
                    'is_approved' => ($i % 5 !== 0), // 80% approved, 20% pending approval
                    // No 'is_featured' here: tool_reviews has no such column (plans,
                    // blog_posts and user_collections do — this one does not).
                    'helpful_count' => 0,
                ]
            ), now()->subDays(mt_rand(1, 45))->subHours(mt_rand(0, 23)));

            // Add some votes to approved reviews
            if ($review->is_approved) {
                $voteCount = mt_rand(0, 8);
                $helpfulVotes = 0;
                for ($v = 0; $v < $voteCount; $v++) {
                    $voter = $demoUsers[($i + $v + 1) % count($demoUsers)];
                    if ($voter->id === $user->id) {
                        continue;
                    }
                    $isHelpful = (mt_rand(1, 10) > 2); // 80% helpful, 20% unhelpful
                    $helpfulVotes += $isHelpful ? 1 : 0;
                    \App\Models\ToolReviewVote::updateOrCreate(
                        ['review_id' => $review->id, 'user_id' => $voter->id],
                        [
                            'is_helpful' => $isHelpful,
                        ]
                    );
                }

                // Keep the denormalised counter in step with the votes actually cast,
                // otherwise the review card reads "0 found this helpful" next to real votes.
                if ($helpfulVotes !== $review->helpful_count) {
                    $review->forceFill(['helpful_count' => $helpfulVotes])->save();
                }
            }
        }

        // ─── 15b. Tool engagement — usage, views, extra reviews, favorites ───
        // Out of the box every tool read 0 uses / 0 views, no rating and no favorites, so
        // the tools directory and each tool page looked dead. This gives the whole catalog
        // believable engagement.
        $allTools = AiTool::query()->orderBy('id')->get(['id', 'slug']);
        $demoUserIds = collect($demoUsers)->pluck('id')->all();

        // 1) Usage + view counts on EVERY tool. A ~15% "popular" slice runs far higher so
        //    the "most used" ordering has a real head-and-tail rather than a flat line.
        //    Views always exceed uses (people browse more than they run). Direct DB writes
        //    bypass the per-tool cache-invalidation the model's saved() event would fire 410x.
        $toolUsage = [];

        foreach ($allTools as $position => $tool) {
            $isPopular = $position % 7 === 0;
            $usage = $isPopular ? mt_rand(2500, 24000) : mt_rand(40, 1900);
            $views = $usage + mt_rand((int) ($usage * 0.4), (int) ($usage * 2.5)) + mt_rand(20, 400);

            DB::table('ai_tools')->where('id', $tool->id)->update([
                'usage_count' => $usage,
                'views_count' => $views,
            ]);

            // Remembered so review coverage below can be weighted toward busy tools.
            $toolUsage[$tool->id] = $usage;
        }

        // 2) Reviews across a broad sample of tools (the earlier block only touched the
        //    first handful). Each selected tool gets a few reviews from DISTINCT users, so
        //    the unique(user_id, tool_slug) constraint is never hit. Saving a ToolReview
        //    fires the model event that recomputes the tool's avg_rating / review_count from
        //    its APPROVED reviews, so those columns stay correct without touching them here.
        $reviewBodies = [
            5 => [
                'Easily the best tool in this category I have tried.',
                'Outstanding output quality, saves me hours every week.',
                'Exactly what I needed — fast, accurate and easy to use.',
                'I use this almost daily. Highly recommended.',
            ],
            4 => [
                'Very good results, occasionally needs a light edit.',
                'Solid and reliable. Does what it promises.',
                'Great value. The output is consistently useful.',
            ],
            3 => [
                'Decent for quick drafts, less so for nuanced work.',
                'Works fine but nothing that stands out.',
            ],
            2 => [
                'A bit generic — I expected more from the results.',
            ],
        ];

        // Skew positive: the pool below averages ~4.3 stars, which reads as a healthy catalog.
        $ratingPool = [5, 5, 5, 5, 4, 4, 4, 3, 3, 2];

        // Review coverage is weighted by usage: a busy tool is far more likely to be
        // reviewed, and to carry more reviews, than an obscure one — so the "most used" and
        // "top rated" views broadly agree instead of looking random. Tiers map a tool's
        // usage to [percent chance of any reviews, min reviews, max reviews].
        $reviewTierFor = function (int $usage): array {
            return match (true) {
                $usage >= 5000 => [100, 5, 9],  // heavily used → always reviewed, lots
                $usage >= 2000 => [90, 3, 7],
                $usage >= 800 => [70, 2, 5],
                $usage >= 250 => [45, 1, 3],
                default => [20, 1, 2],           // long-tail → rarely reviewed, few
            };
        };

        foreach ($allTools as $position => $tool) {
            [$chance, $minReviews, $maxReviews] = $reviewTierFor($toolUsage[$tool->id] ?? 0);

            if (mt_rand(1, 100) > $chance) {
                continue; // this tool draws no reviews
            }

            $reviewCount = mt_rand($minReviews, $maxReviews);
            // Distinct reviewers for this tool, offset by position so the sample rotates
            // through the whole user base rather than always hitting the same few.
            $reviewerIds = collect($demoUserIds)
                ->shuffle()
                ->take($reviewCount)
                ->values();

            foreach ($reviewerIds as $reviewerIndex => $reviewerId) {
                $rating = $ratingPool[array_rand($ratingPool)];
                $createdAt = now()->subDays(mt_rand(1, 180))->subHours(mt_rand(0, 23));

                $this->backdate(\App\Models\ToolReview::updateOrCreate(
                    ['user_id' => $reviewerId, 'tool_slug' => $tool->slug],
                    [
                        // Most reviews are visible; a few sit unapproved in the queue.
                        'is_approved' => mt_rand(1, 6) !== 1,
                        'rating' => $rating,
                        'comment' => $reviewBodies[$rating][array_rand($reviewBodies[$rating])],
                        'helpful_count' => mt_rand(0, 18),
                    ]
                ), $createdAt);
            }
        }

        // 3) Favorites across the catalog. favorites_count is read live from this relation
        //    (AiToolController: $tool->favorites()->count()), so seeding the rows is all that
        //    is needed. A ~10% "loved" slice draws many more favorites for a believable spread.
        $toolType = AiTool::class;

        foreach ($allTools as $position => $tool) {
            if ($position % 4 === 3) {
                continue; // not every tool is someone's favorite
            }

            $isLoved = $position % 11 === 0;
            $favCount = $isLoved ? mt_rand(12, 30) : mt_rand(1, 9);

            $fans = collect($demoUserIds)->shuffle()->take($favCount)->values();

            foreach ($fans as $fanId) {
                Favorite::updateOrCreate([
                    'user_id' => $fanId,
                    'favoriteable_type' => $toolType,
                    'favoriteable_id' => $tool->id,
                ]);
            }
        }

        // ─── 15c. Output caps, so a public demo is cheap to run ─────────
        //
        // Every generation on the demo is paid for with real provider credits by whoever
        // hosts it, and without an override each tool falls back to `default_max_tokens`
        // (2000). A visitor clicking through a handful of tools burns the long-form budget
        // for output nobody reads past the first paragraph.
        //
        // max_tokens_override is the lever that actually costs money: PromptBuilder sends it
        // as the completion ceiling AND feeds it to getLengthInstruction(), so the prompt
        // itself asks for something short rather than being cut off mid-sentence. 300 tokens
        // is roughly 220 words — two paragraphs, still a real-looking result — against the
        // 2000 it would otherwise request.
        //
        // avg_output_tokens does NOT limit anything; it is the pre-flight estimate behind the
        // credit reservation and the quoted cost (TokenGuard::estimateCreditCost,
        // PromptBuilder::estimateCost). Matched to the cap so the credits a visitor is
        // charged line up with the output they actually get — left at its old figure the
        // demo would quote and reserve for 2000 tokens it can no longer produce.
        //
        // One statement for the whole catalogue: this is ~410 rows, and the per-tool loop
        // above is already the expensive part of this seeder.
        DB::table('ai_tools')->update([
            'max_tokens_override' => 300,
            'avg_output_tokens' => 300,
        ]);

        // The catalogue is cached and both columns are IN that payload
        // (ToolCatalogCacheService), while AiToolController reads the cap from the cached
        // copy — so a direct DB write like the one above is invisible to the running site
        // until the cache turns over. demo:reset does not flush it either.
        \App\Services\AI\ToolCatalogCacheService::invalidateAll();

        // ─── 16. Sample Contact Messages ────────────────────────────────
        $contactMessages = [
            [
                'name' => 'Alice Johnson',
                'email' => 'alice.j@example.com',
                'subject' => 'Partnership Inquiry',
                'message' => 'Hello, I represent a startup incubator and we would love to discuss a bulk discount plan for our members. Please let me know who I can talk to regarding partnership options.',
                'is_read' => true,
                'replied_at' => now()->subDays(3),
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'mchen@example.org',
                'subject' => 'API Access Request',
                'message' => 'Hi, I am interested in integrating MakeAI\'s text generation tool with my CMS. Do you offer public API endpoints and documentation? Thanks!',
                'is_read' => false,
                'replied_at' => null,
            ],
            [
                'name' => 'Sophia Martinez',
                'email' => 'sophia.m@example.com',
                'subject' => 'Billing Question',
                'message' => 'I noticed a charge on my credit card that I don\'t recognize. Could you please help me look up my invoice history? My account email is sophia.m@example.com.',
                'is_read' => false,
                'replied_at' => null,
            ],
            [
                'name' => 'David Wilson',
                'email' => 'david.w@example.net',
                'subject' => 'Feature Request: Image Upscaling',
                'message' => 'Hello, your AI image editor is amazing! It would be even better if you added an option for 4x image upscaling. Is that on your roadmap for future updates?',
                'is_read' => true,
                'replied_at' => now()->subDays(5),
            ],
            [
                'name' => 'Elena Rostova',
                'email' => 'elena.rostova@example.com',
                'subject' => 'Incorrect translation in dashboard',
                'message' => 'I found a translation typo in the Russian dashboard UI (on the settings page). I would love to submit a correction patch if there is a localization file.',
                'is_read' => false,
                'replied_at' => null,
            ],
        ];

        foreach ($contactMessages as $index => $msg) {
            $this->backdate(\App\Models\ContactMessage::updateOrCreate(
                ['email' => $msg['email'], 'subject' => $msg['subject']],
                [
                    'name' => $msg['name'],
                    'message' => $msg['message'],
                    'ip_address' => '192.168.10.' . (20 + $index),
                    'is_read' => $msg['is_read'],
                    'replied_at' => $msg['replied_at'],
                ]
            ), now()->subDays(mt_rand(1, 10))->subHours(mt_rand(1, 23)));
        }

        // ─── 17. Testimonials ───────────────────────────────────────────
        // Each testimonial gets a generated initials avatar (see demoAvatar) — self-contained
        // SVGs on the public disk, no external portraits to license or 404.
        $testimonials = [
            [
                'name' => 'Sarah Whitfield',
                'role' => 'Head of Content',
                'company' => 'Northwind Media',
                'content' => 'We replaced three separate writing tools with this platform. Our team ships a week of blog content in a single afternoon now, and the quality is consistent enough that editing is a formality rather than a rewrite.',
                'rating' => 5,
                'is_featured' => true,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'Daniel Okafor',
                'role' => 'Founder',
                'company' => 'Loopcraft',
                'content' => 'As a solo founder I needed leverage, not another subscription. This paid for itself in the first month — the landing page copy it generated outperformed what I had written by a wide margin.',
                'rating' => 5,
                'is_featured' => true,
                'source' => 'trustpilot',
                'show_source' => true,
            ],
            [
                'name' => 'Mei Lin Chen',
                'role' => 'Marketing Manager',
                'company' => 'Brightpath SaaS',
                'content' => 'The template library is the real strength here. Having ad copy, email sequences and SEO metadata in one workflow means my team stops context-switching between five different tabs.',
                'rating' => 5,
                'is_featured' => true,
                'source' => 'google',
                'show_source' => true,
            ],
            [
                'name' => 'Thomas Reinhardt',
                'role' => 'Agency Owner',
                'company' => 'Reinhardt Digital',
                'content' => 'We run content for eleven clients and the brand voice settings keep each one sounding distinct. Onboarding a new client used to take days of tone calibration; it is now about twenty minutes.',
                'rating' => 5,
                'is_featured' => true,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'Priya Raghunathan',
                'role' => 'Product Marketer',
                'company' => 'Cadence Analytics',
                'content' => 'Genuinely useful for launch work. I drafted an entire release announcement, the accompanying email and six social variants in one sitting, then spent my time on positioning instead of formatting.',
                'rating' => 5,
                'is_featured' => false,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'James Callahan',
                'role' => 'SEO Consultant',
                'company' => 'Callahan & Co',
                'content' => 'The meta description and schema tooling alone justify the cost. I audit a lot of AI writing tools for clients and this is one of the few that treats SEO as a first-class feature rather than an afterthought.',
                'rating' => 4,
                'is_featured' => false,
                'source' => 'google',
                'show_source' => true,
            ],
            [
                'name' => 'Elena Moreau',
                'role' => 'E-commerce Lead',
                'company' => 'Maison Verte',
                'content' => 'We have close to two thousand SKUs and writing descriptions was a permanent backlog. Bulk generation cleared it in under a week and conversion held steady, which was the part I was worried about.',
                'rating' => 5,
                'is_featured' => false,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'Marcus Adeyemi',
                'role' => 'Technical Writer',
                'company' => 'Kernel Systems',
                'content' => 'Handles technical material better than I expected. It does not invent API behaviour when you give it proper context, which is the failure mode that made me abandon two other tools.',
                'rating' => 4,
                'is_featured' => false,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'Sofia Marchetti',
                'role' => 'Social Media Strategist',
                'company' => 'Atlas Collective',
                'content' => 'The scheduling and caption tools together mean a full month of social content gets planned on a Monday morning. My clients think I hired someone.',
                'rating' => 5,
                'is_featured' => false,
                'source' => 'trustpilot',
                'show_source' => true,
            ],
            [
                'name' => 'David Kowalski',
                'role' => 'Operations Director',
                'company' => 'Fieldstone Logistics',
                'content' => 'We are not a marketing company, so the templates carried us further than any blank-page tool would have. Internal docs, customer emails and policy drafts all got noticeably faster.',
                'rating' => 4,
                'is_featured' => false,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'Aisha Rahman',
                'role' => 'Startup Advisor',
                'company' => 'Seedline Ventures',
                'content' => 'I recommend this to nearly every portfolio company at pre-seed. It gives a two-person team the content output of a small department without the headcount conversation.',
                'rating' => 5,
                'is_featured' => false,
                'source' => 'manual',
                'show_source' => false,
            ],
            [
                'name' => 'Lucas Ferreira',
                'role' => 'Freelance Copywriter',
                'company' => null,
                'content' => 'I was sceptical that this would replace me. It has not — it removed the parts of the job I disliked. I take on more clients now and spend the time on strategy instead of first drafts.',
                'rating' => 5,
                'is_featured' => false,
                'source' => 'manual',
                'show_source' => false,
            ],
        ];

        foreach ($testimonials as $index => $testimonial) {
            $createdAt = now()->subDays(($index + 1) * 9)->setTime(mt_rand(9, 18), mt_rand(0, 59));

            $this->backdate(\App\Models\Testimonial::updateOrCreate(
                ['name' => $testimonial['name']],
                array_merge($testimonial, [
                    'avatar' => $this->demoAvatar($testimonial['name'], 'avatars/demo-testimonial-' . ($index + 1) . '.svg'),
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ])
            ), $createdAt);
        }

        // ─── 18. FAQs ───────────────────────────────────────────────────
        // Four categories, four questions each. Answers describe how THIS platform actually
        // behaves (credits, BYOK, 2FA channels, GDPR export) rather than generic filler, so
        // the public FAQ page reads like a real help centre.
        $faqGroups = [
            'Getting Started' => [
                [
                    'question' => 'What is MakeAI and who is it for?',
                    'answer' => 'MakeAI is an all-in-one generative AI platform for writing, images, chat and research. It is built for marketers, agencies, e-commerce teams and solo founders who want production-ready output without stitching together several separate tools.',
                ],
                [
                    'question' => 'Do I need any technical knowledge to get started?',
                    'answer' => 'No. Every tool is a guided form — you fill in a few fields describing what you need and the platform handles the prompting. If you do want lower-level control, the chat and playground tools let you write prompts directly.',
                ],
                [
                    'question' => 'How do I create my first piece of content?',
                    'answer' => 'Pick a template from the AI Tools directory, fill in the short brief, and generate. The result opens in the editor where you can refine, regenerate individual sections, and save it to your documents library or export it.',
                ],
                [
                    'question' => 'Can I try the platform before paying?',
                    'answer' => 'Yes. The free plan includes a monthly credit allowance so you can test the tools and models at no cost. No card is required to sign up, and you can upgrade at any point once you know it fits your workflow.',
                ],
            ],
            'Billing & Plans' => [
                [
                    'question' => 'Which payment methods do you accept?',
                    'answer' => 'We accept major credit and debit cards, PayPal, and a number of regional gateways depending on your country. Available options are shown at checkout once your billing country is detected.',
                ],
                [
                    'question' => 'Can I upgrade, downgrade or cancel at any time?',
                    'answer' => 'Yes. Plan changes take effect from your next billing date, and cancelling stops any future charges while leaving your access active until the end of the period you have already paid for. Everything is self-service from the Billing page.',
                ],
                [
                    'question' => 'Do you offer refunds?',
                    'answer' => 'If you have not consumed credits on a new subscription, we will refund it in full within 14 days of purchase. Once credits have been used the payment is non-refundable, since the underlying model usage has already been billed to us.',
                ],
                [
                    'question' => 'What happens if I run out of credits before my renewal?',
                    'answer' => 'Generation pauses until your allowance resets on the next billing date. You can either buy a one-off credit top-up, which never expires, or move to a higher plan and have the difference applied immediately.',
                ],
            ],
            'AI Tools & Credits' => [
                [
                    'question' => 'How are credits calculated?',
                    'answer' => 'Credits are based on the model you choose and the length of the request and response. Faster, smaller models cost noticeably less per generation than frontier models, and the estimated cost is shown before you run a tool.',
                ],
                [
                    'question' => 'Which AI models can I use?',
                    'answer' => 'The catalog spans the current OpenAI, Anthropic, Google, DeepSeek, Meta, Mistral and Cohere line-ups, alongside embedding, transcription and image models. Your administrator controls which of them are enabled on each plan.',
                ],
                [
                    'question' => 'Do unused credits roll over to the next month?',
                    'answer' => 'Plan credits reset at the start of each billing period and do not carry over. Credits bought as a separate top-up are different — those sit in your wallet and stay there until you spend them.',
                ],
                [
                    'question' => 'Can I use my own provider API keys?',
                    'answer' => 'Yes. Bring-your-own-key lets you connect your own OpenAI or Anthropic account, in which case generation is billed to that provider directly instead of consuming platform credits. Keys are encrypted at rest and can be removed at any time.',
                ],
            ],
            'Account & Security' => [
                [
                    'question' => 'How do I enable two-factor authentication?',
                    'answer' => 'Open Settings, then Security, and choose either an authenticator app or SMS to your verified phone number. You will be given one-time recovery codes during setup — store them somewhere safe, as they are the fallback if you lose the device.',
                ],
                [
                    'question' => 'Is my content used to train AI models?',
                    'answer' => 'No. Your prompts and generated content are never used to train models. Requests are passed to the provider solely to produce your output, and you can opt out of anonymised product analytics entirely in your privacy settings.',
                ],
                [
                    'question' => 'Can I export or permanently delete my data?',
                    'answer' => 'Yes. From Privacy settings you can request a full export of your account data as a downloadable archive, or schedule account deletion. Deletion removes your documents, chats and personal details in line with GDPR requirements.',
                ],
                [
                    'question' => 'What should I do if I think my account is compromised?',
                    'answer' => 'Change your password immediately and use the "log out of all sessions" control in Security to revoke every other active device. Review your recent login history on the same screen, and contact support if anything looks unfamiliar.',
                ],
            ],
        ];

        $faqCategoryOrder = 0;

        foreach ($faqGroups as $categoryName => $questions) {
            $faqCategoryOrder++;

            // FaqCategory has $timestamps = false; the table defaults created_at itself.
            $faqCategory = \App\Models\FaqCategory::updateOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $faqCategoryOrder]
            );

            foreach ($questions as $questionIndex => $faq) {
                \App\Models\Faq::updateOrCreate(
                    ['question' => $faq['question']],
                    [
                        'answer' => $faq['answer'],
                        'category_id' => $faqCategory->id,
                        'is_active' => true,
                        'sort_order' => $questionIndex + 1,
                    ]
                );
            }
        }

        // ─── 19. Newsletter Subscribers ─────────────────────────────────
        // Signup dates are spread across today, the last 90 days and the preceding ~10
        // months so the dashboard's newsletter-subscribers chart is continuous at every
        // range. Row shape mirrors the real lifecycle in NewsletterController: a subscribed
        // row has no confirm_token, a pending (double opt-in) row still holds one, and an
        // unsubscribed row carries unsubscribed_at.
        $subscriberDates = [];

        foreach (range(1, 3) as $ignored) {
            $subscriberDates[] = now()->startOfDay()
                ->addHours(mt_rand(8, min(20, max(8, (int) now()->hour))))
                ->addMinutes(mt_rand(0, 59));
        }

        foreach (range(1, 30) as $ignored) {
            $subscriberDates[] = now()->subDays(mt_rand(1, 89))->setTime(mt_rand(7, 22), mt_rand(0, 59));
        }

        for ($monthsAgo = 4; $monthsAgo <= 13; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            foreach (range(1, 3) as $ignored) {
                $subscriberDates[] = $monthStart->copy()
                    ->addDays(mt_rand(0, $monthStart->daysInMonth - 1))
                    ->setTime(mt_rand(7, 22), mt_rand(0, 59));
            }
        }

        // Existing customers on the list, plus standalone leads who never signed up for an
        // account — the realistic mix for a marketing list.
        $leadNames = [
            'Amelia Hart', 'Noah Bergstrom', 'Chloe Devereux', 'Ethan Mwangi', 'Isabelle Fontaine',
            'Ryan Kowalczyk', 'Nadia Haddad', 'Julian Vasquez', 'Freya Lindqvist', 'Omar Siddiqui',
            '清水 美咲', 'Beatriz Almeida', 'Lucas Meyer', 'Anika Sharma', 'Sean O\'Donnell',
            'Valentina Rossi', 'Kwame Boateng', 'Ingrid Solberg', 'Diego Herrera', 'Mia Lindgren',
        ];

        $subscriberCustomers = collect($demoUsers)->shuffle()->take(24)->values();

        foreach ($subscriberDates as $index => $subscribedAt) {
            // A third of the list are known customers; the rest are pure leads.
            $customer = $subscriberCustomers[$index] ?? null;

            if ($customer) {
                $email = $customer->email;
                $name = $customer->name;
            } else {
                $leadName = $leadNames[$index % count($leadNames)];
                // Index keeps the address unique even when the name pool wraps around.
                $email = 'lead' . ($index + 1) . '.' . strtolower(preg_replace('/[^a-z]/i', '', explode(' ', $leadName)[0])) . '@example.com';
                $name = $leadName;
            }

            $status = match (true) {
                $index % 9 === 5 => 'unsubscribed',
                $index % 11 === 7 => 'pending',
                default => 'subscribed',
            };

            $subscriber = \App\Models\NewsletterSubscriber::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'status' => $status,
                    'token' => \App\Models\NewsletterSubscriber::generateToken(),
                    // Only a pending double opt-in row still holds a confirmation token;
                    // confirm() nulls it once the address is verified.
                    'confirm_token' => $status === 'pending'
                        ? \App\Models\NewsletterSubscriber::generateToken()
                        : null,
                    'subscribed_at' => $subscribedAt,
                    'unsubscribed_at' => $status === 'unsubscribed'
                        ? $subscribedAt->copy()->addDays(mt_rand(5, 90))->min(now())
                        : null,
                ]
            );

            // created_at isn't fillable, and the subscribers chart buckets on it.
            $subscriber->forceFill([
                'created_at' => $subscribedAt,
                'updated_at' => $subscribedAt,
            ])->save();
        }

        // ─── 20. Announcements ──────────────────────────────────────────
        // Covers all four surfaces (topbar / popup / bottom_popup / notification), every
        // trigger the theme's AnnouncementManager understands (immediate, delay, scroll %,
        // exit intent) and a spread of audiences and frequencies. One row is scheduled for
        // the future and one has already expired, so the admin list demonstrates the
        // starts_at / ends_at window as well as the live state. Images stay null — the
        // banners are colour-and-text, so nothing can 404.
        $announcementAuthorId = Admin::where('email', $adminEmail)->value('id');

        $announcements = [
            [
                'type' => 'topbar',
                'title' => 'Summer launch: 30% off annual plans',
                'content' => 'Upgrade before the end of the month and save 30% on any annual subscription.',
                'bg_color' => '#1f75fe',
                'text_color' => '#ffffff',
                'cta_text' => 'View plans',
                'cta_url' => '/pricing',
                'target_audience' => 'all',
                'trigger_type' => null,
                'trigger_value' => null,
                'show_frequency' => 'session',
                'is_active' => true,
                'starts_at' => now()->subDays(6),
                'ends_at' => now()->addDays(24),
            ],
            [
                'type' => 'popup',
                'title' => 'Start creating for free',
                'content' => 'Create an account and get monthly credits to try every AI tool — no card required.',
                'bg_color' => '#111827',
                'text_color' => '#ffffff',
                'cta_text' => 'Create free account',
                'cta_url' => '/register',
                'target_audience' => 'guests',
                'trigger_type' => 'delay',
                'trigger_value' => '8',
                'show_frequency' => 'session',
                'is_active' => true,
                'starts_at' => now()->subDays(20),
                'ends_at' => null,
            ],
            [
                'type' => 'bottom_popup',
                'title' => 'Running low on credits?',
                'content' => 'Pro unlocks every model, unlimited chats and priority generation queues.',
                'bg_color' => '#8b5cf6',
                'text_color' => '#ffffff',
                'cta_text' => 'Compare plans',
                'cta_url' => '/pricing',
                'target_audience' => 'free',
                'trigger_type' => 'scroll',
                'trigger_value' => '55',
                'show_frequency' => 'once',
                'is_active' => true,
                'starts_at' => now()->subDays(12),
                'ends_at' => null,
            ],
            [
                'type' => 'notification',
                'title' => 'New: document collections',
                'content' => 'Group your saved documents into collections and share them with your team.',
                'bg_color' => '#059669',
                'text_color' => '#ffffff',
                'cta_text' => 'See what is new',
                'cta_url' => '/blog',
                'target_audience' => 'auth',
                'trigger_type' => 'immediate',
                'trigger_value' => null,
                'show_frequency' => 'once',
                'is_active' => true,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->addDays(11),
            ],
            [
                'type' => 'popup',
                'title' => 'Switch to annual and save two months',
                'content' => 'You are on a monthly plan — moving to annual billing works out cheaper from day one.',
                'bg_color' => '#0f172a',
                'text_color' => '#ffffff',
                'cta_text' => 'Switch to annual',
                'cta_url' => '/user/dashboard/billing',
                'target_audience' => 'pro',
                'trigger_type' => 'exit',
                'trigger_value' => null,
                'show_frequency' => 'once',
                'is_active' => true,
                // Scheduled: starts next week, so it is configured but not yet live.
                'starts_at' => now()->addDays(7),
                'ends_at' => now()->addDays(37),
            ],
            [
                'type' => 'topbar',
                'title' => 'Scheduled maintenance completed',
                'content' => 'Thanks for your patience — the platform is fully operational again.',
                'bg_color' => '#f59e0b',
                'text_color' => '#111827',
                'cta_text' => null,
                'cta_url' => null,
                'target_audience' => 'all',
                'trigger_type' => null,
                'trigger_value' => null,
                'show_frequency' => 'always',
                'is_active' => false,
                // Already finished: sits in the list as a past announcement.
                'starts_at' => now()->subDays(48),
                'ends_at' => now()->subDays(45),
            ],
        ];

        foreach ($announcements as $index => $announcement) {
            $createdAt = ($announcement['starts_at'] ?? now())->copy()->subDays(2)->setTime(mt_rand(9, 17), mt_rand(0, 59));

            $record = Announcement::updateOrCreate(
                ['title' => $announcement['title']],
                array_merge($announcement, [
                    'image' => null,
                    'created_by' => $announcementAuthorId,
                ])
            );

            $record->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        // ─── 21. Bulk SMS Campaigns ─────────────────────────────────────
        // Recipients are drawn from the SAME audience the admin screen targets
        // (SmsCampaignController::eligibleUsers): a verified phone plus a marketing opt-in.
        // Numbers are snapshotted in E.164 exactly as the sender records them, so the logs
        // show what was actually texted. Covers a completed send, one with delivery
        // failures, an in-progress send, and an unsent draft.
        $smsEligible = collect($demoUsers)
            ->filter(fn ($candidate) => filled($candidate->phone)
                && filled($candidate->phone_verified_at)
                && $candidate->sms_marketing_opt_in)
            ->values();

        $smsCampaigns = [
            [
                'message' => 'MakeAI: your monthly credits have just reset. Log in to start creating.',
                'action_label' => 'Open dashboard',
                // SMS bodies need an absolute URL, so these are built from APP_URL rather
                // than the relative paths the in-app notifications use.
                'action_url' => url('/user/dashboard'),
                'status' => 'sent',
                'days' => 21,
                'share' => 1.0,
                'failure_rate' => 0.04,
            ],
            [
                'message' => 'Flash sale: 30% off annual plans for the next 48 hours only.',
                'action_label' => 'View plans',
                'action_url' => url('/pricing'),
                'status' => 'sent',
                'days' => 9,
                'share' => 0.85,
                'failure_rate' => 0.12,
            ],
            [
                'message' => 'New in MakeAI: document collections let you group and share your work.',
                'action_label' => null,
                'action_url' => null,
                'status' => 'sending',
                'days' => 0,
                'share' => 0.6,
                'failure_rate' => 0.03,
            ],
            [
                'message' => 'Reminder: your subscription renews next week. Update billing details if needed.',
                'action_label' => 'Billing settings',
                'action_url' => url('/user/dashboard/billing'),
                'status' => 'draft',
                'days' => 1,
                'share' => 0.0,
                'failure_rate' => 0.0,
            ],
        ];

        foreach ($smsCampaigns as $campaignIndex => $row) {
            $startedAt = now()->subDays($row['days'])->setTime(mt_rand(9, 17), mt_rand(0, 59));
            $audience = $smsEligible->take((int) round($smsEligible->count() * $row['share']));

            $campaign = SmsCampaign::updateOrCreate(
                ['message' => $row['message']],
                [
                    'action_url' => $row['action_url'],
                    'action_label' => $row['action_label'],
                    'recipient_count' => $audience->count(),
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'status' => $row['status'],
                    'created_by_admin_id' => $announcementAuthorId,
                    // A draft has never run, so it has no start/finish timestamps.
                    'started_at' => $row['status'] === 'draft' ? null : $startedAt,
                    'finished_at' => $row['status'] === 'sent' ? $startedAt->copy()->addMinutes(mt_rand(3, 25)) : null,
                ]
            );

            $campaign->forceFill(['created_at' => $startedAt, 'updated_at' => $startedAt])->save();

            // Rebuild the recipient log from scratch so a re-seed cannot double it up
            // (the table is unique on campaign_id + user_id).
            SmsCampaignRecipient::where('campaign_id', $campaign->id)->delete();

            $sent = 0;
            $failed = 0;

            foreach ($audience as $position => $recipientUser) {
                // A campaign still sending has a tail of not-yet-delivered rows.
                $isPending = $row['status'] === 'sending' && $position >= (int) round($audience->count() * 0.7);
                $isFailed = ! $isPending && $row['failure_rate'] > 0 && $position % (int) max(2, round(1 / $row['failure_rate'])) === 1;

                $recipientStatus = match (true) {
                    $isPending => 'pending',
                    $isFailed => 'failed',
                    default => 'sent',
                };

                $sent += $recipientStatus === 'sent' ? 1 : 0;
                $failed += $recipientStatus === 'failed' ? 1 : 0;

                $sentAt = $recipientStatus === 'pending'
                    ? null
                    : $startedAt->copy()->addSeconds($position * mt_rand(2, 9));

                $this->backdate(SmsCampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'user_id' => $recipientUser->id,
                    // Snapshotted E.164, matching what the sender records.
                    'phone' => \App\Support\PhoneNumber::e164($recipientUser->phone, $recipientUser->phone_country)
                        ?? $recipientUser->phone,
                    'status' => $recipientStatus,
                    'error_message' => $recipientStatus === 'failed'
                        ? ['Unreachable handset', 'Invalid destination number', 'Carrier rejected message'][$position % 3]
                        : null,
                    'sent_at' => $sentAt,
                ]), $startedAt, $sentAt ?? $startedAt);
            }

            // Keep the campaign counters in step with the rows actually written.
            $campaign->forceFill(['sent_count' => $sent, 'failed_count' => $failed])->save();
        }

        // ─── 22. In-app notifications (admin bell + demo user) ──────────
        // Both surfaces read Laravel's polymorphic `notifications` table via the same
        // InAppNotification shape (title/message/category/level/icon/action_url/…); they
        // differ only by notifiable_type. Rows are inserted directly with the real payload
        // shape so they render natively, with a realistic read/unread mix (the app tracks
        // read state on read_at; the status enum mirrors it — keep them consistent).
        $notificationType = \App\Notifications\InAppNotification::class;
        $demoAdmin = Admin::where('email', $adminEmail)->first();

        $seedNotifications = function ($notifiable, string $notifiableType, array $rows) use ($notificationType) {
            // Every other block in this seeder is idempotent (updateOrCreate, or delete-then-
            // write). This one wrote raw rows with a fresh UUID each time and no dedup key, so
            // a standalone `db:seed --class=DemoSeeder` appended a SECOND full set — the bell
            // showed every notification twice. These accounts' notifications are entirely
            // seeder-owned, so clearing them first is the correct reset.
            DB::table('notifications')
                ->where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiable->id)
                ->delete();

            foreach ($rows as $index => $row) {
                $createdAt = now()->subDays($row['days'])->subHours(mt_rand(0, 20))->subMinutes(mt_rand(0, 59));
                // Older items are already read; the newest few stay unread so the bell shows a count.
                $isRead = $row['days'] > 2;
                $readAt = $isRead ? $createdAt->copy()->addHours(mt_rand(1, 12)) : null;

                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => $notificationType,
                    'notifiable_type' => $notifiableType,
                    'notifiable_id' => $notifiable->id,
                    'data' => json_encode([
                        'title' => $row['title'],
                        'message' => $row['message'],
                        'category' => $row['category'],
                        'level' => $row['level'],
                        'icon' => $row['icon'],
                        'action_url' => $row['action_url'] ?? null,
                        'action_label' => $row['action_label'] ?? null,
                        'meta' => ['demo' => true],
                    ]),
                    'status' => $isRead ? 'read' : 'unread',
                    'read_at' => $readAt,
                    'created_at' => $createdAt,
                    'updated_at' => $readAt ?? $createdAt,
                ]);
            }
        };

        // Admin bell — operational events an operator would actually see.
        if ($demoAdmin) {
            $seedNotifications($demoAdmin, Admin::class, [
                ['days' => 0, 'category' => 'payment', 'level' => 'success', 'icon' => 'ti ti-credit-card', 'title' => 'New subscription payment', 'message' => 'David Demo upgraded to the Professional yearly plan ($499.99).', 'action_url' => '/admin/premium/subscriptions', 'action_label' => 'View subscriptions'],
                ['days' => 0, 'category' => 'support', 'level' => 'warning', 'icon' => 'ti ti-lifebuoy', 'title' => 'New support ticket', 'message' => 'A high-priority ticket "AI response is slower than expected" was opened.', 'action_url' => '/admin/support/tickets', 'action_label' => 'Open tickets'],
                ['days' => 1, 'category' => 'comments', 'level' => 'info', 'icon' => 'ti ti-message-report', 'title' => 'Comments awaiting moderation', 'message' => 'There are comments flagged for review in the moderation queue.', 'action_url' => '/admin/content/comments', 'action_label' => 'Review comments'],
                ['days' => 2, 'category' => 'affiliate', 'level' => 'info', 'icon' => 'ti ti-users', 'title' => 'Affiliate payout requested', 'message' => 'A creator requested a $250.00 payout via PayPal.', 'action_url' => '/admin/marketing/affiliate', 'action_label' => 'Review payouts'],
                ['days' => 4, 'category' => 'system', 'level' => 'success', 'icon' => 'ti ti-download', 'title' => 'Export ready', 'message' => 'Your "Users" export finished and is available in the Export Center.', 'action_url' => '/admin/reports/export-center', 'action_label' => 'Download'],
                ['days' => 6, 'category' => 'security', 'level' => 'warning', 'icon' => 'ti ti-shield-lock', 'title' => 'New admin sign-in', 'message' => 'A sign-in to the admin panel was recorded from a new location.', 'action_url' => '/admin/activity/admin-logs', 'action_label' => 'View activity'],
                ['days' => 9, 'category' => 'system', 'level' => 'info', 'icon' => 'ti ti-sparkles', 'title' => 'AI model catalog updated', 'message' => 'New models were added to the catalog and are available to assign to plans.', 'action_url' => '/admin/ai/providers', 'action_label' => 'Manage models'],
            ]);
        }

        // Demo user (the showcase account a buyer signs into).
        $notifUser = User::where('email', $userEmail)->first();

        if ($notifUser) {
            $seedNotifications($notifUser, User::class, [
                ['days' => 0, 'category' => 'credits', 'level' => 'success', 'icon' => 'ti ti-coins', 'title' => 'Credits refreshed', 'message' => 'Your monthly credit allowance has been topped up. Start creating!', 'action_url' => '/user/dashboard', 'action_label' => 'Go to dashboard'],
                ['days' => 0, 'category' => 'ai_tool', 'level' => 'info', 'icon' => 'ti ti-file-text', 'title' => 'Your document is ready', 'message' => 'The "Q3 AI Launch Campaign" document finished generating.', 'action_url' => '/user/dashboard/documents', 'action_label' => 'Open document'],
                ['days' => 1, 'category' => 'affiliate', 'level' => 'success', 'icon' => 'ti ti-user-plus', 'title' => 'New referral converted', 'message' => 'One of your referrals upgraded to a paid plan — a commission was added to your balance.', 'action_url' => '/user/dashboard/affiliate', 'action_label' => 'View earnings'],
                ['days' => 2, 'category' => 'subscription', 'level' => 'info', 'icon' => 'ti ti-calendar-time', 'title' => 'Subscription renews soon', 'message' => 'Your Professional plan renews in 7 days. Update billing details if anything changed.', 'action_url' => '/user/dashboard/billing', 'action_label' => 'Manage billing'],
                // Points at DEMO-TKT-1004, the one showcase ticket whose newest admin reply is
                // still unread — the bell and the ticket thread tell the same story.
                ['days' => 2, 'category' => 'support', 'level' => 'info', 'icon' => 'ti ti-lifebuoy', 'title' => 'Support replied to your ticket', 'message' => 'Support answered "Change the PayPal address my affiliate payouts are sent to" and needs a detail from you.', 'action_url' => '/user/dashboard/support/tickets/DEMO-TKT-1004', 'action_label' => 'Open ticket'],
                ['days' => 5, 'category' => 'security', 'level' => 'warning', 'icon' => 'ti ti-shield-lock', 'title' => 'New sign-in to your account', 'message' => 'We noticed a sign-in from a new device. If this was not you, secure your account.', 'action_url' => '/user/dashboard/security', 'action_label' => 'Review security'],
                ['days' => 8, 'category' => 'system', 'level' => 'info', 'icon' => 'ti ti-gift', 'title' => 'Welcome to MakeAI', 'message' => 'Thanks for joining! Explore 400+ AI tools and your intelligent assistant.', 'action_url' => '/ai-tools', 'action_label' => 'Browse tools'],
            ]);
        }

        // ─── 23. AI Assistant addon (settings + feedback trail) ─────────
        $this->seedAssistantShowcase($demoUsers, $showcaseUser);

        // ─── 24. AI Chatbot addon (settings + analytics trail) ──────────
        $this->seedChatbotShowcase($demoUsers);

        // ─── 25. AI Knowledge Base addon (help centre + reader activity) ───
        $this->seedKnowledgeBaseShowcase($demoUsers);

        // ─── 26. AI Image Pro addon (admin config + the creator's studio) ───
        $this->seedImageProShowcase($showcaseUser, $demoUsers);

        // ─── 27. FakerAI addon (generator defaults + run history) ───────
        // Last on purpose: its ledger credits each run with rows the sections above
        // created, so those rows have to exist before it can point at them.
        $this->seedFakerAiShowcase();

        // ─── 28. Spend limits for a publicly reachable demo ─────────────
        $this->seedDemoSpendLimits($showcaseUser);

        // ─── 29. Admin dashboard notes (the "My Notes" card) ────────────
        $this->seedAdminNotes($demoAdmin);

        // ─── 30. Credit top-up bonus tiers + coupons ────────────────────
        $this->seedCreditBonusTiers();
        $this->seedCoupons($professionalPlan);

        // ─── 31. Export Center (presets, schedules, recent files) ───────
        // Last: its recent-export files are generated from the real datasets, so every
        // section above has to have written its rows before there is anything to export.
        $this->seedExportCenter($demoAdmin);
    }

    /**
     * /admin/reports/export-center — saved presets, recurring schedules, and a Recent
     * Exports list with real downloadable files in it.
     *
     * All three panels opened empty, which is the worst case for this screen in particular:
     * presets and schedules are the reasons to use it over a one-off download, and with no
     * recent files a buyer cannot tell whether an export produces anything at all.
     *
     * Everything is derived from the DatasetRegistry rather than hardcoded. Datasets are
     * license-gated (isAvailable() — revenue disappears with billing off), their column keys
     * are defined in one place each, and a preset naming a column or dataset that does not
     * exist is quietly dropped by the page. Reading the registry means the seed can never
     * describe a dataset this install does not have.
     */
    private function seedExportCenter(?Admin $admin): void
    {
        if (! $admin) {
            return;
        }

        $registry = app(\App\Exports\Registry\DatasetRegistry::class);
        $availableKeys = $registry->availableKeys();

        // Only seed rows for datasets this install actually exposes.
        $pick = fn (array $wanted) => array_values(array_intersect($wanted, $availableKeys));

        // First N column keys of a dataset, so a preset carries a real column SUBSET (the
        // point of a preset) instead of the everything-selected default.
        $firstColumns = function (string $key, int $count) use ($registry): array {
            return array_slice(
                array_map(fn (\App\Exports\Registry\Column $c) => $c->key, $registry->resolve($key)->columns()),
                0,
                $count
            );
        };

        // ── Saved presets ──
        $presets = [
            ['dataset' => 'users', 'name' => 'Active users — monthly review', 'format' => 'xlsx', 'columns' => 6],
            ['dataset' => 'revenue', 'name' => 'Revenue for the accountant', 'format' => 'csv', 'columns' => 7],
            ['dataset' => 'ai-usage', 'name' => 'AI spend by tool', 'format' => 'xlsx', 'columns' => 6],
            ['dataset' => 'support-tickets', 'name' => 'Open tickets snapshot', 'format' => 'pdf', 'columns' => 5],
            ['dataset' => 'affiliates', 'name' => 'Affiliate payouts due', 'format' => 'csv', 'columns' => 5],
        ];

        foreach ($presets as $preset) {
            if (! in_array($preset['dataset'], $availableKeys, true)) {
                continue;
            }

            ExportPreset::updateOrCreate(
                ['admin_id' => $admin->id, 'name' => $preset['name']],
                [
                    'dataset' => $preset['dataset'],
                    'format' => $preset['format'],
                    // Left empty rather than invented: filter keys differ per dataset
                    // (supportedFilters()), and a filter the dataset ignores is dead weight
                    // that still renders as an active chip in the builder.
                    'filters' => [],
                    'columns' => $firstColumns($preset['dataset'], $preset['columns']),
                ]
            );
        }

        // ── Recurring schedules ──
        $schedules = [
            ['dataset' => 'revenue', 'name' => 'Monthly revenue report', 'format' => 'xlsx', 'frequency' => 'monthly', 'active' => true, 'lastDays' => 6],
            ['dataset' => 'users', 'name' => 'Weekly signups', 'format' => 'csv', 'frequency' => 'weekly', 'active' => true, 'lastDays' => 2],
            ['dataset' => 'ai-usage', 'name' => 'Daily AI usage digest', 'format' => 'csv', 'frequency' => 'daily', 'active' => true, 'lastDays' => 0],
            // Paused, so the list shows the inactive state and its "next run" is blank.
            ['dataset' => 'support-tickets', 'name' => 'Support backlog (paused)', 'format' => 'pdf', 'frequency' => 'weekly', 'active' => false, 'lastDays' => 21],
        ];

        foreach ($schedules as $schedule) {
            if (! in_array($schedule['dataset'], $availableKeys, true)) {
                continue;
            }

            $lastRun = now()->subDays($schedule['lastDays'])->setTime(6, 0);

            ScheduledExport::updateOrCreate(
                ['admin_id' => $admin->id, 'name' => $schedule['name']],
                [
                    'dataset' => $schedule['dataset'],
                    'format' => $schedule['format'],
                    'filters' => [],
                    'columns' => [],
                    'frequency' => $schedule['frequency'],
                    'is_active' => $schedule['active'],
                    'last_run_at' => $lastRun,
                    // A paused schedule has nothing scheduled — the runner skips it, so a
                    // future next_run_at would be a time it is never going to fire.
                    'next_run_at' => $schedule['active']
                        ? match ($schedule['frequency']) {
                            'daily' => $lastRun->copy()->addDay(),
                            'weekly' => $lastRun->copy()->addWeek(),
                            default => $lastRun->copy()->addMonth(),
                        }
                        : null,
                ]
            );
        }

        // ── Recent Exports ──
        //
        // The list reads FILES off the local disk, not a table, and parses the dataset and
        // format back out of the filename — so these follow exportStoragePath()'s
        // "{dataset-key}-{Y-m-d-His}.{format}" exactly or they list as "unknown".
        //
        // Written with each dataset's own headings and real rows, so Download hands back a
        // genuine export rather than a placeholder a buyer would open and find empty.
        $disk = Storage::disk('local');
        $exportDir = 'exports/'.$admin->id;

        // Seeder-owned, and the filenames carry a timestamp — without clearing first, every
        // reset would leave another generation of files behind for the list to accumulate.
        $disk->deleteDirectory($exportDir);

        $recent = $pick(['users', 'revenue', 'ai-usage', 'subscriptions', 'support-tickets', 'credit-ledger']);

        foreach ($recent as $index => $key) {
            $generatedAt = now()->subHours(($index * 9) + 2);

            try {
                $dataset = $registry->resolve($key);

                $rows = [implode(',', array_map($this->csvCell(...), $dataset->headings()))];

                foreach ($dataset->query([])->limit(50)->cursor() as $model) {
                    $rows[] = implode(',', array_map($this->csvCell(...), $dataset->row($model)));
                }

                $disk->put(
                    $exportDir.'/'.$key.'-'.$generatedAt->format('Y-m-d-His').'.csv',
                    implode("\n", $rows)."\n"
                );
            } catch (\Throwable $e) {
                // One dataset that cannot be queried on this install (an addon's, a schema
                // it does not have) must not take the whole demo reset down with it.
                continue;
            }
        }
    }

    /** RFC-4180 escaping for the seeded CSV exports. */
    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }

    /**
     * Bonus Credit Tiers on /admin/premium/credit-settings.
     *
     * The setting ships as an empty array, so that panel opened on "no tiers yet" and the
     * user-facing top-up page had no volume incentive to show at any amount — the one thing
     * the screen exists to configure.
     *
     * Ascending, and each tier's percent must beat the one below it: calculateCredits()
     * takes the HIGHEST bonus_percent among every tier the amount clears, not the last
     * matching one, so a tier priced above another but paying less is silently unreachable.
     * Quick amounts are lined up with the thresholds so the buttons on the top-up page land
     * exactly ON a tier rather than a dollar short of one.
     */
    private function seedCreditBonusTiers(): void
    {
        settings_set('credit_topup_enabled', true, 'boolean', 'billing');
        settings_set('credit_price_per_unit', '0.01', 'string', 'billing');
        settings_set('credit_topup_minimum', '5', 'string', 'billing');

        // $5 / $10 buy nothing extra — the first bonus starts at $25, so there is a reason
        // to move up. At 0.01/credit these are 2,500 / 5,000 / 10,000 / 25,000 credits.
        settings_set('credit_topup_quick_amounts', json_encode([5, 10, 25, 50, 100, 250]), 'json', 'billing');

        settings_set('credit_topup_bonus_tiers', json_encode([
            ['min_amount' => 25, 'bonus_percent' => 5],
            ['min_amount' => 50, 'bonus_percent' => 10],
            ['min_amount' => 100, 'bonus_percent' => 20],
            ['min_amount' => 250, 'bonus_percent' => 35],
        ]), 'json', 'billing');
    }

    /**
     * Coupons for /admin/premium/coupons.
     *
     * Empty out of the box, which left both the admin table and the public header banner
     * with nothing — and the banner is the only place a visitor ever sees the feature.
     *
     * The set covers what the list styles differently: percent and fixed, capped and
     * uncapped, plan-restricted and global, audience-targeted, plus the states a table of
     * live coupons always contains — one expired, one not yet started, one deactivated and
     * one already near its usage limit. Only ONE carries show_in_header: getHeaderCoupon()
     * reads ->first() and the controller demotes every other on publish, so seeding two
     * would create a state the admin UI cannot produce.
     *
     * Gated on coupons_enabled() — Extended license plus the setting — because the whole
     * admin page 404s without it and the rows would be unreachable.
     */
    private function seedCoupons(?Plan $professionalPlan): void
    {
        if (! function_exists('coupons_enabled') || ! coupons_enabled()) {
            return;
        }

        $coupons = [
            [
                // The one on the public header banner.
                'code' => 'WELCOME20', 'type' => 'percent', 'value' => 20, 'max_discount' => 50,
                'max_uses' => 500, 'per_user_limit' => 1, 'plan_id' => null, 'user_limit' => 'all',
                'starts_at' => now()->subDays(20), 'expires_at' => now()->addDays(40),
                'is_active' => true, 'show_in_header' => true, 'used_count' => 137,
            ],
            [
                // Fixed amount, restricted to the Professional plan.
                'code' => 'PRO15OFF', 'type' => 'fixed', 'value' => 15, 'max_discount' => null,
                'max_uses' => 200, 'per_user_limit' => 1, 'plan' => 'professional', 'user_limit' => 'free',
                'starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(20),
                'is_active' => true, 'show_in_header' => false, 'used_count' => 48,
            ],
            [
                // Uncapped percentage, no usage ceiling — the "always on" case.
                'code' => 'YEARLY10', 'type' => 'percent', 'value' => 10, 'max_discount' => null,
                'max_uses' => null, 'per_user_limit' => null, 'plan_id' => null, 'user_limit' => 'all',
                'starts_at' => now()->subMonths(3), 'expires_at' => null,
                'is_active' => true, 'show_in_header' => false, 'used_count' => 312,
            ],
            [
                // Nearly exhausted, so the list shows a usage bar close to full.
                'code' => 'FLASH50', 'type' => 'percent', 'value' => 50, 'max_discount' => 100,
                'max_uses' => 100, 'per_user_limit' => 1, 'plan_id' => null, 'user_limit' => 'recent_30_days',
                'starts_at' => now()->subDays(4), 'expires_at' => now()->addDays(3),
                'is_active' => true, 'show_in_header' => false, 'used_count' => 94,
            ],
            [
                // Scheduled — starts next week, so the table has a "not started" row.
                'code' => 'BLACKFRIDAY', 'type' => 'percent', 'value' => 40, 'max_discount' => 200,
                'max_uses' => 1000, 'per_user_limit' => 1, 'plan_id' => null, 'user_limit' => 'all',
                'starts_at' => now()->addDays(7), 'expires_at' => now()->addDays(21),
                'is_active' => true, 'show_in_header' => false, 'used_count' => 0,
            ],
            [
                // Expired last month.
                'code' => 'SUMMER25', 'type' => 'percent', 'value' => 25, 'max_discount' => 60,
                'max_uses' => 300, 'per_user_limit' => 1, 'plan_id' => null, 'user_limit' => 'all',
                'starts_at' => now()->subMonths(3), 'expires_at' => now()->subMonth(),
                'is_active' => true, 'show_in_header' => false, 'used_count' => 268,
            ],
            [
                // Switched off by hand, which is a different state from expired.
                'code' => 'LEGACY5', 'type' => 'fixed', 'value' => 5, 'max_discount' => null,
                'max_uses' => 50, 'per_user_limit' => 1, 'plan_id' => null, 'user_limit' => 'inactive',
                'starts_at' => now()->subMonths(6), 'expires_at' => now()->addMonths(6),
                'is_active' => false, 'show_in_header' => false, 'used_count' => 11,
            ],
        ];

        foreach ($coupons as $row) {
            $planId = ($row['plan'] ?? null) === 'professional'
                ? $professionalPlan?->id
                : ($row['plan_id'] ?? null);

            Coupon::updateOrCreate(
                ['code' => $row['code']],
                [
                    'type' => $row['type'],
                    'value' => $row['value'],
                    'max_discount' => $row['max_discount'],
                    'max_uses' => $row['max_uses'],
                    'per_user_limit' => $row['per_user_limit'],
                    'plan_id' => $planId,
                    'user_limit' => $row['user_limit'],
                    'starts_at' => $row['starts_at'],
                    'expires_at' => $row['expires_at'],
                    'is_active' => $row['is_active'],
                    'show_in_header' => $row['show_in_header'],
                    // Redemption history, so the list renders a usage bar rather than 0/500
                    // on every row. Fillable here, but incremented by the gateway in real
                    // use — never write to it outside a seeder.
                    'used_count' => $row['used_count'],
                ]
            );
        }
    }

    /**
     * The five notes behind the admin dashboard's "My Notes" card.
     *
     * The card is one of the few panels on that dashboard with its own empty state, and an
     * operator's private scratchpad reading "No notes yet" is the one panel that says
     * nobody works here. Five is deliberate: the card itself renders only the two newest
     * (`allNotes.slice(0, 2)`), so the rest exist to give the "View all notes" modal
     * something to open onto.
     *
     * The set covers every state the card can render, because a demo that only shows the
     * plain case documents nothing:
     *
     *   - the two newest carry a future reminder, which is the only condition under which
     *     the amber "Reminder: …" badge appears on the card;
     *   - one reminder is already due (past date, not yet sent), which raises the "Note
     *     Reminders" alert above the dashboard with its snooze and dismiss actions;
     *   - one has an auto_delete_date, the self-expiring note;
     *   - one has reminder_sent already true — a reminder that has been and gone, so the
     *     badge and the alert both correctly leave it alone.
     *
     * Notes belong to the `admins` guard, not `users`, and admin_id is a hard FK — so this
     * no-ops rather than fataling when the demo admin is missing.
     */
    private function seedAdminNotes(?Admin $admin): void
    {
        if (! $admin) {
            return;
        }

        $notes = [
            [
                'subject' => 'Review Q3 pricing before the renewal wave',
                'description' => 'The Professional yearly cohort from last October renews in six weeks. Decide whether the credit allowance moves with the price, and give support a heads-up before anything ships.',
                'created' => now()->subHours(3),
                // Future: this is one of the two the card renders, and the reason it shows
                // the reminder badge rather than a bare title.
                'reminder_date' => now()->addDays(2)->setTime(9, 30),
                'auto_delete_date' => null,
                'reminder_sent' => false,
            ],
            [
                'subject' => 'Rotate the OpenAI key on the first of the month',
                'description' => 'Old key stays live for 24h so nothing in flight breaks. Update it in AI > Providers, then confirm the fallback model still resolves.',
                'created' => now()->subDay()->setTime(16, 20),
                'reminder_date' => now()->addDays(5)->setTime(8, 0),
                'auto_delete_date' => null,
                'reminder_sent' => false,
            ],
            [
                'subject' => 'Follow up on the affiliate payout batch',
                'description' => 'Two payouts are still pending PayPal confirmation. Chase them before the weekly run so the balances do not carry over again.',
                'created' => now()->subDays(3)->setTime(11, 5),
                // Already due, so the dashboard raises its reminder alert. Kept to exactly
                // one — a wall of overdue reminders reads as a neglected install.
                'reminder_date' => now()->subHours(4),
                'auto_delete_date' => null,
                'reminder_sent' => false,
            ],
            [
                'subject' => 'Draft the changelog for the next release',
                'description' => 'New image tools, the export scheduler and the faster assistant streaming. Publish it as an announcement once the build is tagged.',
                'created' => now()->subDays(6)->setTime(14, 45),
                'reminder_date' => null,
                // The self-expiring case: this one clears itself once the release ships.
                'auto_delete_date' => now()->addDays(21)->endOfDay(),
                'reminder_sent' => false,
            ],
            [
                'subject' => 'Check the weekly backup restore',
                'description' => 'Restored into staging and the row counts matched. Worth repeating monthly rather than trusting the job to keep succeeding quietly.',
                'created' => now()->subDays(12)->setTime(10, 15),
                // Sent and done — present so the "past reminder" state is represented too.
                'reminder_date' => now()->subDays(11)->setTime(9, 0),
                'auto_delete_date' => null,
                'reminder_sent' => true,
            ],
        ];

        foreach ($notes as $note) {
            $record = AdminNote::updateOrCreate(
                ['admin_id' => $admin->id, 'subject' => $note['subject']],
                [
                    'description' => $note['description'],
                    'reminder_date' => $note['reminder_date'],
                    'auto_delete_date' => $note['auto_delete_date'],
                    'reminder_sent' => $note['reminder_sent'],
                ]
            );

            // The card orders by created_at desc, so without this the five land on the same
            // timestamp and which two it shows comes down to insertion order.
            $this->backdate($record, $note['created']);
        }
    }

    /**
     * AI Assistant demo content: showroom settings plus the feedback trail behind them.
     *
     * Split from the widget itself on purpose — the addon's own seeders run on every
     * activation for every buyer, and fabricated user ratings have no business in a real
     * install. They belong here, where the whole point is a populated demo site.
     */
    private function seedAssistantShowcase(array $demoUsers, ?User $showcaseUser): void
    {
        if (! function_exists('is_addon_active') || ! is_addon_active('ai-assistant')) {
            return;
        }

        if (! class_exists(AssistantConversation::class) || ! Schema::hasTable('assistant_conversations')) {
            return;
        }

        $this->seedAssistantSettings();
        $this->seedAssistantAutomationRules();
        $this->seedAssistantFeedbackTrail($demoUsers, $showcaseUser);
    }

    /**
     * Canned replies that answer without spending a provider call.
     *
     * ORDER IS BEHAVIOUR, not presentation. findMatchingAutomationRule() walks the active
     * rules in insertion order and returns the FIRST match, and 'contains' matches any
     * message the trigger appears in — so a broad trigger seeded early permanently shadows
     * every specific rule after it. These run most-specific first for that reason; the
     * broadest ('pricing') is last.
     *
     * match_type is limited to exact/contains: the service also understands 'fuzzy', but the
     * admin form validates in:exact,contains, so a seeded fuzzy rule could be read on that
     * screen and never saved again.
     */
    private function seedAssistantAutomationRules(): void
    {
        if (! class_exists(AiAssistantRule::class) || ! Schema::hasTable('ai_assistant_rules')) {
            return;
        }

        $rules = [
            [
                'trigger' => 'cancel my subscription',
                'match_type' => 'contains',
                'response' => "You can cancel yourself in under a minute: Dashboard → Billing → Manage plan → Cancel subscription.\n\nYour plan stays active until the end of the period you have already paid for — nothing is cut off on the day you cancel, and any credits left in that period are still yours to spend.",
                'is_active' => true,
                'days' => 54,
            ],
            [
                'trigger' => 'talk to a human',
                'match_type' => 'contains',
                'response' => "Of course — I'll step aside.\n\nOpen Dashboard → Support and start a ticket, and a person will pick it up. Include your account email ({user_email}) and, if it is about a payment, the invoice number; that usually saves a round trip.",
                'is_active' => true,
                'days' => 47,
            ],
            [
                'trigger' => 'reset my password',
                'match_type' => 'contains',
                'response' => "Sign out, then use \"Forgot password?\" on the sign-in page. The reset link lands in your inbox within a minute or two and is valid for 60 minutes.\n\nIf it does not arrive, check spam first — and note that the link expires once used, so request a fresh one rather than re-clicking an old email.",
                'is_active' => true,
                'days' => 41,
            ],
            [
                'trigger' => 'refund policy',
                'match_type' => 'contains',
                'response' => "The full terms are on the Terms of Service page, including how credits already spent are treated.\n\nRefunds are decided by a person, not by me — open Dashboard → Support with your invoice number and the team will look at your specific case.",
                'is_active' => true,
                'days' => 33,
            ],
            [
                'trigger' => 'how do i upgrade',
                'match_type' => 'contains',
                'response' => "Pricing → pick a plan → checkout. The upgrade applies immediately, and your new credit allowance is available the moment payment clears.\n\nIf you upgrade mid-cycle, the credits you have not spent carry over rather than being reset.",
                'is_active' => true,
                'days' => 26,
            ],
            [
                'trigger' => 'pricing',
                'match_type' => 'contains',
                'response' => "Everything is on the Pricing page, {user_name} — the current plans, what each includes, and the monthly and annual rates side by side.\n\nIf you tell me roughly how much you expect to generate each month, I can point you at the plan that fits.",
                'is_active' => true,
                'days' => 19,
            ],
            [
                'trigger' => 'hi',
                'match_type' => 'exact',
                'response' => "Hi {user_name} — I'm Ava, the {site_name} guide. Ask me about plans, credits, or which tool fits what you are writing. Type / to see the shortcuts.",
                'is_active' => true,
                'days' => 12,
            ],
            [
                // Left switched off on purpose: the list needs an inactive row so the toggle
                // on the rules screen has something to demonstrate, and a stale seasonal
                // promo is the honest reason a rule sits disabled.
                'trigger' => 'black friday',
                'match_type' => 'contains',
                'response' => "Our Black Friday offer has ended, but annual billing still works out cheaper than monthly across a full year — the current rates are on the Pricing page.",
                'is_active' => false,
                'days' => 7,
            ],
        ];

        foreach ($rules as $rule) {
            $createdAt = now()->subDays($rule['days'])->setTime(10, 30);

            $model = AiAssistantRule::updateOrCreate(
                ['trigger' => $rule['trigger']],
                [
                    'response' => $rule['response'],
                    'match_type' => $rule['match_type'],
                    'is_active' => $rule['is_active'],
                ]
            );

            $this->backdate($model, $createdAt);
        }
    }

    /**
     * Configure the assistant the way a buyer should first see it.
     *
     * Activation seeds the manifest defaults but deliberately never overwrites a stored
     * value (AddonService::seedDefaultSettings respects the operator). A demo reset wants
     * the opposite — the widget must come back fully dressed however the last visitor left
     * it — so these overwrite. Provider and model stay untouched: pinning a model here
     * would override the site default with one the install may have no key for.
     */
    private function seedAssistantSettings(): void
    {
        $settings = [
            // Visible on both surfaces; a demo where the widget is off shows nothing.
            ['enabled', true, 'boolean'],
            ['admin_enabled', true, 'boolean'],
            // Greet rather than auto-open: the bubble stays out of the way of screenshots.
            ['auto_open', false, 'boolean'],
            ['greeting_on_first_visit', true, 'boolean'],
            ['position', 'bottom-right', 'string'],
            ['accent_color', '#1F75FE', 'string'],
            ['excluded_pages', null, 'string'],

            ['enable_home', true, 'boolean'],
            ['enable_help', true, 'boolean'],
            ['enable_message', true, 'boolean'],
            ['enable_slash_commands', true, 'boolean'],
            ['enable_knowledge_base', true, 'boolean'],
            ['enable_csat', true, 'boolean'],
            ['enable_emoji', true, 'boolean'],

            ['allow_file_upload', true, 'boolean'],
            ['allowed_file_types', 'pdf,docx,txt,csv,png,jpg', 'string'],
            ['max_upload_size_mb', 10, 'integer'],

            ['assistant_name', 'Ava', 'string'],
            ['designation', 'AI Product Guide', 'string'],
            ['greeting_message', null, 'string'],

            // Only channels that cannot resolve to somebody else's real account: a reserved
            // 555-01xx fiction number and this install's own URL.
            ['social_whatsapp', 'https://wa.me/15550100199', 'string'],
            ['social_website', url('/'), 'string'],

            ['show_legal_note', true, 'boolean'],
            ['privacy_url', '/privacy-policy', 'string'],
            ['terms_url', '/terms-of-service', 'string'],

            ['max_tokens', 1024, 'integer'],
            ['temperature', '0.7', 'string'],

            ['show_to', 'all', 'string'],
            ['guest_daily_message_limit', 5, 'integer'],
            ['daily_message_limit', 20, 'integer'],
            ['pro_daily_message_limit', 0, 'integer'],

            // Manifest type is 'textarea', which storableType() maps to 'string' — the same
            // thing the admin form writes, so these round-trip through the settings screen.
            ['system_prompt_frontend', $this->assistantFrontendPrompt(), 'string'],
            ['system_prompt_admin', $this->assistantAdminPrompt(), 'string'],
        ];

        foreach ($settings as [$key, $value, $type]) {
            AddonSetting::set('ai-assistant', $key, $value, $type);
        }
    }

    /**
     * The public-facing system prompt.
     *
     * Written as something an operator would plausibly keep rather than filler: it gives the
     * assistant a persona, and — more usefully as a demo — shows the four interpolated
     * variables in context. buildFrontendSystemPrompt() appends the site name, current page,
     * plan and credit balance itself, so this deliberately does not restate them.
     */
    private function assistantFrontendPrompt(): string
    {
        return <<<'PROMPT'
        You are Ava, the product guide for {site_name}. You are talking to {user_name}, who is currently on {current_page}.

        How to answer:
        - Lead with the answer, then the steps. Two or three short paragraphs at most.
        - Name the exact screen a user should open, e.g. "Dashboard → Usage", not "your settings".
        - Match the user's language.

        What you must not do:
        - Never invent prices, credit costs, limits or refund terms. If you are not certain of a number, point to the Pricing page or the relevant help article instead of guessing.
        - Never promise a feature, date or discount that is not already documented.
        - Never ask for a password, card number or verification code. Nobody at {site_name} will.

        When you cannot help — billing disputes, refunds, anything account-specific — say so plainly and hand off: "I can't see your account from here. Open Dashboard → Support and the team will pick this up." Do not keep guessing.
        PROMPT;
    }

    /**
     * The admin-panel prompt. buildAdminSystemPrompt() appends live site context (user
     * counts, subscriptions, AI calls today, open tickets, cron health) beneath it, so this
     * sets the operator-facing posture and leaves the numbers to the runtime.
     */
    private function assistantAdminPrompt(): string
    {
        return <<<'PROMPT'
        You are the operations assistant for the {site_name} admin panel. You are talking to a staff member, not a customer.

        How to answer:
        - Be direct and specific. Name the admin screen and the setting, e.g. "AI → Providers → monthly spend limit".
        - When the live site context below is relevant to the question, use it rather than speaking in generalities.
        - Flag the risk before the instruction when an action is destructive, billing-affecting or visible to every user.

        What you must not do:
        - Never state a revenue, usage or user figure that is not in the context below — say which report to open instead.
        - Never advise editing the database directly, disabling license checks, or bypassing payment verification.
        - Never draft an announcement, email or refund decision as though it were already approved. Offer it as a draft.
        PROMPT;
    }

    /**
     * The conversations, ratings and experience scores behind the admin Feedback screen.
     *
     * Feedback rows are written with their message_id resolved, not left to the runtime
     * hash lookup, so the "Message" column shows the answer that was actually rated instead
     * of "Message not available" — that column is the whole reason the screen exists.
     */
    private function seedAssistantFeedbackTrail(array $demoUsers, ?User $showcaseUser): void
    {
        $adminUser = User::where('email', config('demo.admin_email'))->first();
        $pick = fn (int $i) => $demoUsers[$i] ?? null;
        $kbSources = $this->assistantKbSources();

        // Ratings skew positive (12 up / 3 down = 80%) so the satisfaction badge lands in
        // its healthy band, with enough negatives that the screen still has something to
        // moderate. `null` user = guest, which the table renders as such.
        $threads = [
            [
                'session' => 'demo-assistant-credits-0001',
                'scope' => 'user',
                'user' => $showcaseUser,
                'context_page' => '/user/dashboard',
                'title' => 'Credit cost of a long-form article',
                'model' => 'gpt-5.6-terra',
                'days' => 0,
                'csat' => 5,
                'turns' => [
                    [
                        'q' => 'How many credits does a long-form blog article cost?',
                        'a' => 'On the Professional plan a 1,500-word article costs roughly 45–60 credits, depending on the model you pick and how much source material you paste in. Shorter formats — meta descriptions, social captions — land under 10.',
                        'rating' => 1,
                        'comment' => 'Exactly the number I needed, thanks.',
                    ],
                    [
                        'q' => 'Can I see what I have spent this month?',
                        'a' => 'Yes — open Dashboard → Usage. It breaks your consumption down by day, by tool and by model, and shows how much of the monthly allowance is left before the next reset.',
                        'rating' => 1,
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-pricing-0002',
                'scope' => 'user',
                'user' => $pick(3),
                'context_page' => '/pricing',
                'title' => 'Free vs Professional',
                'model' => 'claude-sonnet-4-6',
                'days' => 1,
                'csat' => 4,
                'turns' => [
                    [
                        'q' => "What's the difference between Free and Professional?",
                        'a' => 'Free gives you 100 credits a month, the starter model and 5 templates. Professional unlocks every template, all models, unlimited chats, priority support and API access.',
                        'rating' => 1,
                    ],
                    [
                        'q' => 'Does the annual plan really save 20%?',
                        'a' => 'Annual billing works out cheaper than paying month to month across a full year. The exact figures for each plan are on the Pricing page.',
                        'rating' => -1,
                        'comment' => 'Dodged the question — I wanted the actual yearly price.',
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-guest-0003',
                'scope' => 'user',
                'user' => null,
                'context_page' => '/',
                'title' => 'Trying it without an account',
                'model' => 'gpt-5.4-mini',
                'days' => 2,
                'csat' => 4,
                'turns' => [
                    [
                        'q' => 'Can I try this without signing up?',
                        'a' => 'You can ask me 5 questions a day as a guest. Generating documents needs a free account, which takes about twenty seconds and comes with 100 credits to spend.',
                        'rating' => 1,
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-tools-0004',
                'scope' => 'user',
                'user' => $pick(7),
                'context_page' => '/ai-tools',
                'title' => 'Bulk product descriptions',
                'model' => 'gpt-5.6-terra',
                'days' => 3,
                'csat' => 3,
                'turns' => [
                    [
                        'q' => 'Which tool writes product descriptions?',
                        'a' => 'The Product Description Generator. Give it the product name, three or four key features and the tone you sell in, and it returns a description plus a short variant for listing pages.',
                        'rating' => 1,
                    ],
                    [
                        'q' => 'Can it do 50 products at once?',
                        'a' => 'Not in one submission today. You can chain the tool in a workflow and feed it products one after another, which is the closest thing to a batch run right now.',
                        'rating' => -1,
                        'comment' => 'I need a proper CSV import. This did not solve my problem.',
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-billing-0005',
                'scope' => 'user',
                'user' => $pick(12),
                'context_page' => '/user/dashboard/billing',
                'title' => 'Downloading an invoice',
                'model' => 'gemini-3.5-flash',
                'days' => 4,
                'csat' => 5,
                'turns' => [
                    [
                        'q' => 'How do I download my invoice?',
                        'a' => 'Dashboard → Billing → Invoices lists every payment with a PDF download beside it. If you need a company name or VAT number on the document, add it under Billing details first and re-download.',
                        'rating' => 1,
                        'comment' => 'Clear and it worked first time.',
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-admin-0006',
                'scope' => 'admin',
                'user' => $adminUser,
                'context_page' => '/admin/dashboard',
                'title' => 'Provider spend this month',
                'model' => 'claude-sonnet-4-6',
                'days' => 1,
                'csat' => 5,
                'turns' => [
                    [
                        'q' => 'Which AI provider is costing the most this month?',
                        'a' => 'OpenAI is your largest line by some distance, driven mostly by long-form article generations. The full split is on Reports → AI Usage, filtered by provider.',
                        'rating' => 1,
                    ],
                    [
                        'q' => 'How do I cap it?',
                        'a' => 'Set a monthly spend limit per provider under AI → Providers. Once the cap is hit, requests fall back to the next enabled provider instead of failing, so users never see an error.',
                        'rating' => 1,
                        'comment' => 'Did not know about the fallback behaviour.',
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-docs-0007',
                'scope' => 'user',
                'user' => $pick(20),
                'context_page' => '/blog',
                'title' => 'API access guide',
                'model' => 'gpt-5.4-mini',
                'days' => 5,
                'csat' => 2,
                'turns' => [
                    [
                        'q' => 'Do you have a guide on API access?',
                        'a' => 'Yes — API access ships with the Professional plan. Generate a key under Dashboard → API, then authenticate with a Bearer header on every request.',
                        'rating' => -1,
                        'comment' => 'The article it linked is out of date, endpoints have moved.',
                        'sources' => $kbSources,
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-refund-0008',
                'scope' => 'user',
                'user' => null,
                'context_page' => '/pricing',
                'title' => 'Refund policy',
                'model' => 'gpt-5.4-mini',
                'days' => 6,
                'csat' => 4,
                'turns' => [
                    [
                        'q' => 'Is there a refund policy?',
                        'a' => 'There is a money-back window on new subscriptions. The exact terms, and what happens to credits you have already spent, are set out on the Terms of Service page.',
                        'rating' => 1,
                    ],
                    [
                        'q' => 'How would I actually request one?',
                        'a' => 'Open a ticket from Dashboard → Support with your invoice number. Refund requests are handled by a person, usually within one business day.',
                        'rating' => 1,
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-failure-0009',
                'scope' => 'user',
                'user' => $pick(31),
                'context_page' => '/user/dashboard/documents',
                'title' => 'Generation failed',
                'model' => 'gemini-3.5-flash',
                'days' => 8,
                'csat' => 5,
                'turns' => [
                    [
                        'q' => 'Why did my generation fail halfway through?',
                        'a' => 'Three things cause that: you ran out of credits mid-run, the request timed out on a very long output, or a safety filter stopped it. Your usage page will show which — a failed run that consumed no credits points at the timeout.',
                        'rating' => 1,
                        'comment' => 'Was out of credits. Would not have found that on my own.',
                    ],
                ],
            ],
            [
                'session' => 'demo-assistant-affiliate-0010',
                'scope' => 'user',
                'user' => $pick(44),
                'context_page' => '/user/dashboard/affiliate',
                'title' => 'Affiliate payout timing',
                'model' => 'gpt-5.6-terra',
                'days' => 11,
                'csat' => 4,
                'turns' => [
                    [
                        'q' => 'When do affiliate commissions actually get paid?',
                        'a' => 'A commission is held for 30 days after the referred payment clears, to cover refunds. Once it moves to Available you can request a payout, and payouts are sent in batches to the method on your affiliate profile.',
                        'rating' => 1,
                    ],
                ],
            ],
        ];

        foreach ($threads as $index => $thread) {
            /** @var User|null $threadUser */
            $threadUser = $thread['user'];

            $startedAt = now()->subDays($thread['days'])->setTime(9 + ($index % 9), ($index * 7) % 60);
            $messageCount = count($thread['turns']) * 2;
            $lastMessageAt = $startedAt->copy()->addMinutes(($messageCount - 1) * 2);

            $conversation = AssistantConversation::firstOrNew([
                'session_id' => $thread['session'],
                'scope' => $thread['scope'],
            ]);

            // ULID only on first write — regenerating it on every reset would break any
            // link that pointed at the thread.
            $conversation->ulid = $conversation->ulid ?: (string) Str::ulid();

            $inputTokens = 0;
            $outputTokens = 0;
            $credits = 0.0;

            foreach ($thread['turns'] as $turnIndex => $turn) {
                $inputTokens += 90 + ($turnIndex * 24);
                $outputTokens += (int) ceil(mb_strlen($turn['a']) / 4);
                $credits += 1.5 + ($turnIndex * 0.25);
            }

            $conversation->fill([
                'user_id' => $threadUser?->id,
                // Guests are traceable by hashed IP only — same shape the controller writes.
                'ip_hash' => $threadUser ? null : sha1('203.0.113.' . (40 + $index)),
                'title' => $thread['title'],
                'model' => $thread['model'],
                'context_page' => $thread['context_page'],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_credits' => round($credits, 4),
                'message_count' => $messageCount,
                'last_message_at' => $lastMessageAt,
            ])->save();

            $this->backdate($conversation, $startedAt, $lastMessageAt);

            // Rewrite the thread rather than append, so a re-seed does not stack duplicate
            // turns. Feedback rows survive (message_id nulls out) and are re-linked below.
            AssistantMessage::where('conversation_id', $conversation->id)->delete();

            foreach ($thread['turns'] as $turnIndex => $turn) {
                $askedAt = $startedAt->copy()->addMinutes($turnIndex * 4);

                $question = AssistantMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $turn['q'],
                    'input_tokens' => 90 + ($turnIndex * 24),
                ]);
                $question->forceFill(['created_at' => $askedAt])->save();

                // The hash is what the widget posts alongside a thumbs rating, so the
                // feedback row below must carry this exact value to stay joinable.
                $contentHash = sha1($turn['a']);

                $answer = AssistantMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $turn['a'],
                    'content_hash' => $contentHash,
                    'model' => $thread['model'],
                    'output_tokens' => (int) ceil(mb_strlen($turn['a']) / 4),
                    'credits_charged' => 1.5 + ($turnIndex * 0.25),
                    'sources' => $turn['sources'] ?? null,
                ]);
                $answeredAt = $askedAt->copy()->addMinutes(2);
                $answer->forceFill(['created_at' => $answeredAt])->save();

                if (! isset($turn['rating'])) {
                    continue;
                }

                $feedback = AiAssistantFeedback::updateOrCreate(
                    [
                        'session_id' => $thread['session'],
                        'message_hash' => $contentHash,
                    ],
                    [
                        'message_id' => $answer->id,
                        'user_id' => $threadUser?->id,
                        'rating' => $turn['rating'],
                        'comment' => $turn['comment'] ?? null,
                        'context_page' => $thread['context_page'],
                    ]
                );

                $this->backdate($feedback, $answeredAt->copy()->addSeconds(40));
            }

            if (! isset($thread['csat'])) {
                continue;
            }

            $csat = AssistantCsat::updateOrCreate(
                [
                    'session_id' => $thread['session'],
                    'scope' => $thread['scope'],
                ],
                [
                    'user_id' => $threadUser?->id,
                    'score' => $thread['csat'],
                    'context_page' => $thread['context_page'],
                ]
            );

            $this->backdate($csat, $lastMessageAt->copy()->addMinutes(1));
        }
    }

    /**
     * A real citation for the one grounded answer in the trail, in the shape KbSearchService
     * returns. Null when the Knowledge Base addon is absent — the answer then reads as an
     * ordinary ungrounded reply rather than citing an article that does not exist.
     */
    private function assistantKbSources(): ?array
    {
        if (! Schema::hasTable('kb_articles')) {
            return null;
        }

        $article = DB::table('kb_articles')
            ->where('status', 'published')
            ->orderBy('id')
            ->first(['ulid', 'title', 'slug']);

        if (! $article) {
            return null;
        }

        return [[
            'ulid' => $article->ulid,
            'title' => $article->title,
            'slug' => $article->slug,
        ]];
    }

    /**
     * AI Chatbot demo content: showroom settings plus the chat history its Analytics screen
     * is built on.
     *
     * Same split as the assistant above — the addon's own seeder (ChatbotModeSeeder) ships
     * the modes to every buyer, but conversations, token spend and thumbs ratings are
     * fabricated activity that only belongs on a demo site.
     */
    private function seedChatbotShowcase(array $demoUsers): void
    {
        if (! function_exists('is_addon_active') || ! is_addon_active('ai-chatbot')) {
            return;
        }

        if (! class_exists(Conversation::class) || ! Schema::hasTable('conversations')) {
            return;
        }

        $this->seedChatbotSettings();
        $this->seedChatbotAnalyticsTrail($demoUsers);
    }

    /**
     * Configure the chatbot the way a buyer should first see it.
     *
     * Overwrites rather than defers to stored values, for the same reason the assistant's
     * settings do: activation seeds manifest defaults once and then respects the operator,
     * but a demo reset has to put the showroom back however the last visitor left it.
     *
     * `chat_logo` is deliberately absent — there is no demo logo asset to point it at, and
     * writing null would wipe a logo an evaluator uploaded mid-session.
     */
    private function seedChatbotSettings(): void
    {
        // Only claim RAG when the Knowledge Base addon is genuinely installed AND active.
        // On an install without it the toggle would advertise a capability the chat cannot
        // perform. KnowledgeBase::installed() is the same check the settings screen uses.
        $knowledgeBaseUsable = class_exists(\Addons\AiChatbot\Support\KnowledgeBase::class)
            && \Addons\AiChatbot\Support\KnowledgeBase::installed();

        $settings = [
            ['enabled', true, 'boolean'],

            // Model picker open on the provider catalog. Custom models are an operator's own
            // endpoints, which a demo install has none of — the toggle stays off with an
            // empty list behind it so the admin screen still shows how it works.
            ['allow_model_select', true, 'boolean'],
            ['show_provider_models', true, 'boolean'],
            ['show_custom_models', false, 'boolean'],
            ['custom_models', [], 'json'],
            ['mode_default_models', [], 'json'],
            // The manifest default (gpt-4o-mini) is no longer in the ai_models catalog, so a
            // fresh activation points the chat at a model the site cannot resolve. Pin a slug
            // AiModelSeeder actually ships, and a cheap one.
            ['default_chat_model', 'gpt-5.4-mini', 'string'],
            // Empty means "no restriction" to ChatModeController, so every active mode is
            // offered — including any the buyer adds later. That is what a showroom wants.
            ['enabled_modes', [], 'json'],

            ['enable_knowledge_base', $knowledgeBaseUsable, 'boolean'],
            ['enable_file_upload', true, 'boolean'],
            ['enable_voice', true, 'boolean'],

            // Visitors may try the chat without signing up, behind caps tight enough that a
            // public demo cannot be farmed for free generations on the operator's API keys.
            ['allow_guest_messages', true, 'boolean'],
            ['guest_max_messages', 10, 'integer'],
            ['guest_max_tokens', 1000, 'integer'],
            ['guest_max_chat_history', 10, 'integer'],
            ['guest_max_file_size_mb', 5, 'integer'],
            ['guest_max_messages_5h', 15, 'integer'],
            ['guest_max_messages_weekly', 40, 'integer'],
            ['guest_max_messages_monthly', 100, 'integer'],

            // Free tier: metered per message, with rate caps. The three free_max_messages_*
            // keys are not in the manifest — the settings screen writes them per tier — but
            // ChatController reads them, and leaving them at 0 (unlimited) would let a free
            // account out-consume a paying one.
            ['free_credits_per_message', 1, 'integer'],
            ['free_max_tokens', 2048, 'integer'],
            ['free_max_chat_history', 50, 'integer'],
            ['free_max_file_size_mb', 10, 'integer'],
            ['free_max_messages_5h', 20, 'integer'],
            ['free_max_messages_weekly', 100, 'integer'],
            ['free_max_messages_monthly', 300, 'integer'],

            // Both meters on: a demo is the one place where showing the billing machinery
            // under each answer is the point rather than noise.
            ['show_token_usage', true, 'boolean'],
            ['show_credits_charged', true, 'boolean'],

            ['meta_title', 'AI Chat', 'string'],
            ['meta_description', 'Chat with the latest AI models in one place — switch between coding, writing, marketing and research modes, attach files, and pick up any conversation where you left it.', 'string'],
            ['meta_keywords', 'ai chat, ai chatbot, gpt chat, claude chat, ai assistant, chat with ai', 'string'],
        ];

        foreach ($settings as [$key, $value, $type]) {
            AddonSetting::set('ai-chatbot', $key, $value, $type);
        }

        $this->seedChatbotPlanLimits();
    }

    /**
     * A per-plan limit ladder, so the tier tabs on the chatbot settings screen show a
     * progression instead of four identical columns of zeros.
     *
     * These keys are plan-scoped (`plan_{slug}_{limit}`) and generated by the settings
     * screen rather than declared in addon.json, so activation never seeds them — a fresh
     * install reads every one as unset and every paid tier behaves identically. Written off
     * the live plans table, ordered by price, so it still lands correctly if a buyer renamed
     * or re-priced the tiers.
     *
     * `credits_per_message` is deliberately blank for paid plans: ChatController treats a
     * blank as "no flat rate" and falls through to token-based billing, which is what the
     * seeded conversation credits are calculated from. A literal 0 would instead mean paid
     * chat costs nothing at all.
     */
    private function seedChatbotPlanLimits(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        $paidPlans = DB::table('plans')
            ->where('is_free', false)
            ->orderBy('price_monthly')
            ->pluck('slug')
            ->all();

        if ($paidPlans === []) {
            return;
        }

        // 0 means unlimited to ChatController, which is why only the top tier uses it.
        $ladder = [
            ['credits_per_message' => '', 'max_tokens' => 4096,  'max_chat_history' => 200, 'max_file_size_mb' => 20,  'max_messages_5h' => 60,  'max_messages_weekly' => 400,  'max_messages_monthly' => 1500],
            ['credits_per_message' => '', 'max_tokens' => 8192,  'max_chat_history' => 0,   'max_file_size_mb' => 50,  'max_messages_5h' => 150, 'max_messages_weekly' => 1000, 'max_messages_monthly' => 4000],
            ['credits_per_message' => '', 'max_tokens' => 16384, 'max_chat_history' => 0,   'max_file_size_mb' => 100, 'max_messages_5h' => 0,   'max_messages_weekly' => 0,    'max_messages_monthly' => 0],
        ];

        foreach ($paidPlans as $index => $slug) {
            // More paid plans than rungs: everything above the ladder gets the top rung.
            $tier = $ladder[min($index, count($ladder) - 1)];

            foreach ($tier as $key => $value) {
                AddonSetting::set(
                    'ai-chatbot',
                    "plan_{$slug}_{$key}",
                    $value,
                    $key === 'credits_per_message' ? 'string' : 'integer'
                );
            }
        }
    }

    /**
     * Thirty days of chat history, shaped for what the admin Analytics screen reads.
     *
     * Every panel on that page has a source here: the daily trends chart wants a
     * conversation AND a message on each of the last 30 days (a gap reads as an outage);
     * the four stat cards compare the last 7 days against the 7 before them, so volume
     * ramps toward today instead of sitting flat; tokens and credits are summed off the
     * conversation rollups, not the message rows; "Models popularity" resolves each
     * message's provider from the ai_models catalog by slug; "Modes popularity" groups by
     * conversation mode; and the feedback panel needs both a like/dislike split and ten
     * recent rows with something written in them.
     */
    private function seedChatbotAnalyticsTrail(array $demoUsers): void
    {
        $users = array_values(array_filter($demoUsers));

        if ($users === []) {
            return;
        }

        $this->resetChatbotDemoTrail($users);

        // Weighted so "Models popularity" ranks rather than ties, and filtered against the
        // live catalog: the Analytics screen looks each slug up in ai_models to name its
        // provider, and anything missing renders as "Unknown".
        $modelWeights = [
            'gpt-5.6-terra' => 9,
            'claude-sonnet-4-6' => 7,
            'gpt-5.4-mini' => 6,
            'gemini-3.5-flash' => 5,
            'claude-haiku-4-5' => 4,
            'deepseek-v4-pro' => 3,
            'grok-4.5' => 2,
            'llama-3.3-70b-versatile' => 1,
        ];

        $catalogRates = Schema::hasTable('ai_models')
            ? DB::table('ai_models')->pluck('credits_per_1k', 'slug')->all()
            : [];

        if ($catalogRates !== []) {
            $modelWeights = array_intersect_key($modelWeights, $catalogRates);
        }

        if ($modelWeights === []) {
            return;
        }

        $modelPool = [];
        foreach ($modelWeights as $slug => $weight) {
            $modelPool = array_merge($modelPool, array_fill(0, $weight, $slug));
        }

        // Modes come from the addon's own seeder. If it has not run (or every mode was
        // deactivated) the conversations still seed with a null mode, which Analytics
        // collapses into the "General" bucket.
        $modeSlugs = Schema::hasTable('chatbot_modes')
            ? ChatbotMode::query()->where('is_active', true)->pluck('slug')->all()
            : [];

        $threads = $this->chatbotThreadBank();
        $hasSessionColumn = Schema::hasColumn('conversations', 'session_id');
        $canRecordFeedback = class_exists(ChatMessageFeedback::class) && Schema::hasTable('chat_message_feedback');

        $praise = [
            'Exactly what I needed — copied it straight into the project.',
            'Clear, and it actually explained the reasoning instead of just dumping an answer.',
            'Saved me an afternoon of trial and error.',
            'Good structure. The follow-up questions were the useful part.',
            'Better than what I got from the tool I was paying for before.',
        ];

        $complaints = [
            'Too generic — I asked about my stack specifically.',
            'The second half repeated the first half.',
            'Confidently wrong about the API signature.',
            'Answer was fine but far longer than it needed to be.',
        ];

        $threadIndex = 0;
        $userIndex = 0;
        $guestCounter = 0;

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            // Busier in the last week than the week before it, so the four stat cards read as
            // healthy week-over-week growth rather than a suspiciously flat 0%. Never zero:
            // an empty day punches a hole in the 30-day trends chart.
            $perDay = match (true) {
                $daysAgo <= 6 => mt_rand(4, 6),
                $daysAgo <= 13 => mt_rand(2, 4),
                default => mt_rand(1, 3),
            };

            for ($n = 0; $n < $perDay; $n++) {
                $thread = $threads[$threadIndex % count($threads)];
                $threadIndex++;

                $startedAt = now()->subDays($daysAgo)->setTime(mt_rand(7, 21), mt_rand(0, 59));

                // Today's slots would otherwise land in the evening of a day that has not
                // happened yet — a chat dated two hours from now.
                if ($startedAt->isFuture()) {
                    $startedAt = now()->subMinutes(mt_rand(5, 180));
                }

                // Roughly one chat in eight is an anonymous visitor, which is the traffic mix
                // guest access is switched on for above.
                $isGuest = $hasSessionColumn && mt_rand(1, 100) <= 12;
                $owner = $isGuest ? null : $users[$userIndex++ % count($users)];

                $model = $modelPool[array_rand($modelPool)];
                $creditsPer1k = (int) ($catalogRates[$model] ?? 10);
                $mode = in_array($thread['mode'], $modeSlugs, true) ? $thread['mode'] : null;

                // Guests hit the 10-message cap long before a signed-in user does, so their
                // threads are short — a full six-turn conversation from a guest would
                // contradict the limits this same seeder just wrote.
                $turns = $isGuest ? array_slice($thread['turns'], 0, 1) : $thread['turns'];

                $conversation = Conversation::create([
                    'user_id' => $owner?->id,
                    'session_id' => $isGuest
                        ? 'demo-chat-guest-' . str_pad((string) ++$guestCounter, 4, '0', STR_PAD_LEFT)
                        : null,
                    'mode_slug' => $mode,
                    'title' => $thread['title'],
                    'model' => $model,
                    'total_tokens' => 0,
                    'total_credits' => 0,
                    'message_count' => 0,
                    'last_message_at' => $startedAt,
                    // One recent chat per handful is pinned, so the sidebar's pinned section
                    // is not permanently empty.
                    'is_pinned' => $owner !== null && $daysAgo <= 3 && $n === 0,
                ]);

                $totalTokens = 0;
                $totalCredits = 0.0;
                $messageCount = 0;
                $lastMessageAt = $startedAt;
                $lastAnswer = null;

                foreach ($turns as $turnIndex => $turn) {
                    $askedAt = $startedAt->copy()->addMinutes($turnIndex * mt_rand(2, 5));

                    // Input grows with the thread because the whole history is re-sent on every
                    // turn — the reason a long chat costs more than the same question asked cold.
                    $inputTokens = (int) ceil(mb_strlen($turn['q']) / 4) + 80 + ($turnIndex * 220);
                    $outputTokens = (int) ceil(mb_strlen($turn['a']) / 4);
                    // Mirrors TokenGuard::calculateCredits() — total tokens against the model's
                    // own credits_per_1k — so the demo's credit spend is arithmetically the same
                    // as a real one on the same models.
                    $credits = round(($inputTokens + $outputTokens) * ($creditsPer1k / 1000), 2);

                    $question = ConversationMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'user',
                        'content' => $turn['q'],
                        'attachments' => null,
                    ]);
                    $question->forceFill(['created_at' => $askedAt])->save();

                    $answeredAt = $askedAt->copy()->addSeconds(mt_rand(20, 90));

                    // Usage lives on the assistant row only, the way ChatController writes it.
                    $lastAnswer = ConversationMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $turn['a'],
                        'model' => $model,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'credits_charged' => $credits,
                    ]);
                    $lastAnswer->forceFill(['created_at' => $answeredAt])->save();

                    $totalTokens += $inputTokens + $outputTokens;
                    $totalCredits += $credits;
                    $messageCount += 2;
                    $lastMessageAt = $answeredAt;
                }

                $conversation->fill([
                    'total_tokens' => $totalTokens,
                    'total_credits' => round($totalCredits, 4),
                    'message_count' => $messageCount,
                    'last_message_at' => $lastMessageAt,
                ])->save();

                $this->backdate($conversation, $startedAt, $lastMessageAt);

                // Guests cannot rate: chat_message_feedback.user_id is a non-nullable FK.
                if (! $canRecordFeedback || ! $owner || ! $lastAnswer || mt_rand(1, 100) > 42) {
                    continue;
                }

                // ~80% positive, which puts the like/dislike split in a believable band while
                // still leaving the admin something to read the complaints on.
                $positive = mt_rand(1, 100) <= 80;

                // The panel shows the ten most recent ratings, so the freshest week always
                // carries a written comment — an all-blank comment column looks broken.
                $comment = null;
                if (! $positive || $daysAgo <= 6) {
                    $comment = $positive
                        ? $praise[array_rand($praise)]
                        : $complaints[array_rand($complaints)];
                }

                $feedback = ChatMessageFeedback::create([
                    'user_id' => $owner->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $lastAnswer->id,
                    'rating' => $positive ? 1 : -1,
                    'comment' => $comment,
                ]);

                $this->backdate($feedback, $lastMessageAt->copy()->addMinutes(mt_rand(1, 20)));
            }
        }
    }

    /**
     * Clear the previous demo trail so a reset replaces it instead of stacking a second
     * month of chats on top of the first.
     *
     * Scoped to the 50 background demo users and this seeder's own guest sessions: the
     * showcase account's own workspace (seedShowcaseChatbot) is rebuilt separately, and
     * anything a real evaluator wrote under their own account is left alone. Children go first by hand — SQLite does not enforce
     * the cascade unless foreign keys are switched on for the connection.
     */
    private function resetChatbotDemoTrail(array $users): void
    {
        $query = Conversation::query()
            ->whereIn('user_id', array_map(fn (User $user) => $user->id, $users));

        if (Schema::hasColumn('conversations', 'session_id')) {
            $query->orWhere('session_id', 'like', 'demo-chat-guest-%');
        }

        $conversationIds = $query->pluck('id')->all();

        if ($conversationIds === []) {
            return;
        }

        if (Schema::hasTable('chat_message_feedback')) {
            DB::table('chat_message_feedback')->whereIn('conversation_id', $conversationIds)->delete();
        }

        if (Schema::hasTable('conversation_tag')) {
            DB::table('conversation_tag')->whereIn('conversation_id', $conversationIds)->delete();
        }

        ConversationMessage::whereIn('conversation_id', $conversationIds)->delete();
        Conversation::whereIn('id', $conversationIds)->delete();
    }

    /**
     * The conversations the trail is built from — one per mode the addon ships, so
     * "Modes popularity" is a spread rather than a single bar, and written as real
     * exchanges because an evaluator opening any chat from the admin sees the transcript.
     */
    private function chatbotThreadBank(): array
    {
        return [
            [
                'mode' => 'chat-code',
                'title' => 'Laravel queue jobs failing silently',
                'turns' => [
                    [
                        'q' => 'My queued jobs are marked failed but nothing lands in the failed_jobs table. Laravel 12, Redis driver.',
                        'a' => "Almost always one of three things:\n\n1. The worker was started before the failed-job table existed — `php artisan queue:failed-table` then migrate, and restart the worker.\n2. `queue.failed.driver` is set to `null` in config (common in a copied .env).\n3. The job throws in `__construct`, which happens on dispatch, not in the worker — that exception goes to your normal log, not the failed table.\n\nCheck `config('queue.failed')` first; it is the cheapest of the three to rule out.",
                    ],
                    [
                        'q' => 'It was the null driver. How do I get an alert when a job fails now?',
                        'a' => "Hook the `JobFailed` event in `AppServiceProvider::boot()`:\n\n```php\nQueue::failing(function (JobFailed \$event) {\n    Log::critical('Job failed', [\n        'connection' => \$event->connectionName,\n        'job' => \$event->job->resolveName(),\n        'exception' => \$event->exception->getMessage(),\n    ]);\n});\n```\n\nPoint that log channel at Slack or your error tracker rather than building a notification path of your own.",
                    ],
                    [
                        'q' => 'Should I set tries or backoff on the job class?',
                        'a' => "Set both, and set them per job rather than globally. `public \$tries = 3;` with `public array \$backoff = [10, 60, 300];` gives you three attempts spread over roughly six minutes, which covers a provider blipping without hammering it. For anything that charges money on each attempt, use `\$tries = 1` and requeue deliberately.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-code',
                'title' => 'Refactor a fat controller',
                'turns' => [
                    [
                        'q' => 'I have a 600-line controller handling checkout. Where do I start splitting it?',
                        'a' => "Split by reason to change, not by line count.\n\nStart with the three things a checkout controller usually conflates: request validation (move to a FormRequest), the payment call and its failure handling (a service), and the post-payment side effects — invoice, email, credits (listeners on one event). What is left in the controller should read as five or six lines you can take in at a glance.\n\nDo the FormRequest first: it is the lowest-risk move and it usually deletes 100 lines on its own.",
                    ],
                    [
                        'q' => 'How do I keep the refactor safe without a full test suite?',
                        'a' => "Write one characterisation test per branch you are about to move — it does not have to be pretty, only faithful to current behaviour. Then move code in commits small enough that reverting one is painless. Resist renaming anything in the same commit as a move; a diff that both moves and renames is unreviewable.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-code',
                'title' => 'Explain this regex',
                'turns' => [
                    [
                        'q' => 'What does /^(?!.*\\.\\.)[\\w.-]+@[\\w-]+\\.[a-z]{2,}$/i actually match?',
                        'a' => "Piece by piece:\n\n- `(?!.*\\.\\.)` — a negative lookahead rejecting anything containing two consecutive dots.\n- `[\\w.-]+` — the local part: letters, digits, underscore, dot, hyphen.\n- `@[\\w-]+` — a domain label without dots.\n- `\\.[a-z]{2,}` — a dot and a TLD of two or more letters.\n- `/i` — case-insensitive.\n\nSo it matches `first.last@example.com` but not `a..b@example.com` or `user@sub.example.com` — note the single-label domain, which is probably a bug for you.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-write',
                'title' => 'Tighten a launch announcement',
                'turns' => [
                    [
                        'q' => 'Rewrite this so it is shorter and less breathless: "We are incredibly excited to finally announce the launch of our brand new AI-powered platform which we have been working on for many months."',
                        'a' => "\"Our AI platform is live today.\"\n\nThat is the whole sentence. Excitement belongs in what the product does next, not in an adverb before it — readers discount \"incredibly excited\" automatically, and \"finally\" tells them you were late.\n\nIf you want one line of context: \"Our AI platform is live today, after eight months in private beta with 40 teams.\" The number does the work the adjectives were trying to do.",
                    ],
                    [
                        'q' => 'Now give me the opening paragraph that follows it.',
                        'a' => "\"It does one thing: turns the research you already have into publishable drafts, in your voice, in about a minute. No prompt engineering, no blank page. Paste your notes, pick a format, edit what comes back.\"\n\nThree sentences, one claim each — capability, cost, action. Everything else you want to say belongs further down, where the readers who are still with you will find it.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-write',
                'title' => 'Cold email that does not sound like a template',
                'turns' => [
                    [
                        'q' => 'Write a cold email to agency owners about our AI content tool. Every version I write sounds like spam.',
                        'a' => "It sounds like spam because it opens with you. Try:\n\n> Subject: your October case study\n>\n> Saw the retail piece you published last week — the before/after structure is the clearest I have read this year.\n>\n> We built something that produces that structure's first draft from a client interview transcript. Agencies using it cut drafting from a day to about forty minutes; the editing judgement stays yours.\n>\n> Worth fifteen minutes?\n\nOne specific observation, one concrete number, one small ask. The observation has to be real — that is the part that cannot be templated.",
                    ],
                    [
                        'q' => 'What is a reasonable follow-up if they do not reply?',
                        'a' => "One follow-up, five business days later, shorter than the first and adding something rather than asking again:\n\n> Sent this over last week. Since then we published the transcript-to-draft walkthrough — here it is, in case it is more useful than a call.\n\nThen stop. A third email converts almost nobody and costs you the address.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-write',
                'title' => 'Summarise a long report',
                'turns' => [
                    [
                        'q' => 'I have a 40-page quarterly report. What is the best way to get a usable summary out of it?',
                        'a' => "Do not ask for \"a summary\" — ask for the shape you need. Three that work:\n\n1. **Decision brief**: what changed, what it means, what someone must now decide. Half a page.\n2. **Number sheet**: every figure that moved more than 10%, with its prior value.\n3. **Exception list**: only what contradicts last quarter's expectations.\n\nGenerate all three from the same document and read the exception list first — that is where anything worth acting on hides.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-marketing',
                'title' => 'Positioning against a cheaper competitor',
                'turns' => [
                    [
                        'q' => 'A competitor launched at half our price and we are losing deals. How should we respond?',
                        'a' => "Do not match the price; you cannot win a race you started second in.\n\nFind the customers for whom the cheaper product is expensive — the ones whose cost of a bad output is high (regulated copy, client deliverables, anything with a legal review downstream). Reposition around that risk, not around features.\n\nConcretely: change the comparison page from a feature grid to a \"what happens when it is wrong\" section. Buyers who ignore that section were never going to pay your price anyway.",
                    ],
                    [
                        'q' => 'Should sales be allowed to discount to close the gap?',
                        'a' => "Give them one lever, not a range: a fixed 15% for annual prepay, nothing else. An open discount ceiling teaches the market your list price is fiction, and the reps discover the floor within a quarter.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-marketing',
                'title' => 'Landing page copy review',
                'turns' => [
                    [
                        'q' => 'Our landing page converts at 0.8%. The hero says "The Future of Content, Powered by AI". What is wrong?',
                        'a' => "The hero makes no claim a visitor can test. \"The future of content\" describes a category, not what happens if they sign up.\n\nReplace it with the outcome and the constraint: \"Publish a researched, on-brand article in under an hour — without hiring another writer.\" Then put the proof directly under it, not three sections down.\n\n0.8% on a page with a category-level headline is normal. Fixing the headline usually moves it more than any redesign will.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-social',
                'title' => 'LinkedIn post from a blog article',
                'turns' => [
                    [
                        'q' => 'Turn our post about AI writing workflows into a LinkedIn post that does not read like an ad.',
                        'a' => "> We measured where the hour actually goes when our team writes an article.\n>\n> Drafting: 12 minutes. Research: 20. Deciding what the piece is about: 28.\n>\n> We had automated the 12.\n>\n> Everything we have shipped since then targets the 28 — outline pressure-testing, angle comparison, source triage. It is less impressive in a demo and considerably more useful.\n\nOne surprising number, one admission, one implication. No CTA — comments do that work for you on LinkedIn.",
                    ],
                    [
                        'q' => 'Give me three hooks I could A/B against that opener.',
                        'a' => "1. \"Automating the writing was the easy part. It was also the wrong part.\"\n2. \"We timed every step of writing one article. The result changed our roadmap.\"\n3. \"Twelve minutes of drafting. Twenty-eight minutes of deciding. Guess which one we automated first.\"\n\nThe third tends to win because it makes the reader answer before they scroll.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-social',
                'title' => 'Content calendar for a product launch',
                'turns' => [
                    [
                        'q' => 'Plan two weeks of social posts leading into a launch. B2B SaaS, small audience.',
                        'a' => "Week one, build the problem: three posts on the pain, each with a number or a story, none mentioning the product. One customer quote. One \"here is how we work today\" behind-the-scenes.\n\nWeek two, close the loop: teaser with a real screenshot (day 8), the reasoning behind one design decision (day 10), launch (day 12), then two days of answering replies in public rather than posting anything new.\n\nWith a small audience the replies matter more than the posts. Budget time for them.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-analyze',
                'title' => 'Reading a churn spike',
                'turns' => [
                    [
                        'q' => 'Monthly churn jumped from 3.1% to 5.4%. Where do I look first?',
                        'a' => "Segment before you theorise. Split the churned accounts by:\n\n- **Tenure** — a spike concentrated in month-two accounts is onboarding; spread evenly across tenures is pricing or a competitor.\n- **Plan** — if it is one tier, look at what changed in that tier's limits.\n- **Acquisition channel** — a bad cohort from one campaign can move the whole number.\n\nOne of those three almost always isolates it. If none do, check for an involuntary-churn spike: expired cards and failed renewals show up as churn but are a billing problem.",
                    ],
                    [
                        'q' => 'It is concentrated in month two on the mid tier. What now?',
                        'a' => "That is an expectation gap, not a product gap: they bought something in month one and did not find it by month two.\n\nPull the last 20 churned mid-tier accounts and check which features they never touched. If a feature that justifies the tier's price went unused in 15 of 20, the onboarding never routed them to it — that is a sequencing fix, not a discount.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-analyze',
                'title' => 'Compare two pricing models',
                'turns' => [
                    [
                        'q' => 'Credits versus flat unlimited for an AI product — which should we pick?',
                        'a' => "They fail differently, and you should pick the failure you can survive.\n\n**Credits** align revenue with cost, so a heavy user cannot bankrupt you — but they add friction to every action and buyers hate metering they cannot predict.\n\n**Flat unlimited** sells better and forecasts worse; your margin is hostage to your top 5% of users, and one automation-happy customer can eat a tier's profit.\n\nMost products land on flat pricing with a fair-use ceiling nobody normal reaches. That gets the sales advantage while keeping the escape hatch.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-design',
                'title' => 'Dashboard empty states',
                'turns' => [
                    [
                        'q' => 'Our dashboard looks dead for new users. What should empty states actually do?',
                        'a' => "An empty state has one job: make the next action obvious and small.\n\nDrop the illustration-plus-\"Nothing here yet\" pattern. Instead show the panel as it will look with data, greyed at 40% opacity, with one button that produces exactly that. People understand a preview instantly and a description never.\n\nIf the action needs setup, show the count of steps left, not a wall of prose.",
                    ],
                    [
                        'q' => 'How many actions should the empty dashboard offer?',
                        'a' => "One primary, one escape hatch. \"Generate your first article\" plus a quiet \"Import existing content\" link. Three equal buttons is a menu, and a menu on an empty screen is a decision the user is not equipped to make yet.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-design',
                'title' => 'Accessible colour palette',
                'turns' => [
                    [
                        'q' => 'How do I check whether our brand blue works for body text?',
                        'a' => "Contrast ratio against the background it sits on: 4.5:1 for body text, 3:1 for text above ~24px or bold above ~19px, and 3:1 for the non-text parts of a control such as an input border.\n\nBrand blues usually fail on white for body copy and pass for headings. The fix is not abandoning the colour — it is keeping the brand blue for large text and interactive fills, and using a darkened variant for anything paragraph-sized.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-research',
                'title' => 'Competitor feature scan',
                'turns' => [
                    [
                        'q' => 'What should I actually record when scanning competitor features?',
                        'a' => "Record four columns and ignore everything else: the feature, the tier it is gated behind, when it shipped, and whether their marketing leads with it.\n\nThe gating and the date are the informative pair. A feature that shipped 18 months ago and is still enterprise-only is a moat; one that moved down to the entry tier last quarter is table stakes you now have to match.",
                    ],
                    [
                        'q' => 'How often is it worth redoing this?',
                        'a' => "Quarterly for the full scan, with changelog subscriptions in between. Monthly scans mostly capture noise, and anything faster than that becomes a report nobody reads.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-mentor',
                'title' => 'Deciding what to build next',
                'turns' => [
                    [
                        'q' => 'I have five features requested by customers and time for one. How do I choose?',
                        'a' => "Ask what each one is evidence of rather than how many people asked.\n\nFor each request, write down: which customer segment asked, what they were trying to do when they hit the wall, and what they are doing instead today. Requests with a workaround already in place are lower urgency than they sound — the pain is real but survivable.\n\nThen pick the one where the workaround is worst, not the one with the most votes. Vote counts measure how many people bothered to ask, which correlates with how loud a segment is, not how much it matters.",
                    ],
                    [
                        'q' => 'Two of them tie on that test. Tiebreaker?',
                        'a' => "Build the one that teaches you more. At a tie on customer value, the option that resolves a question about your product's direction is worth more than the one that confirms what you already believe — you will make the next five decisions with what it tells you.",
                    ],
                ],
            ],
            [
                'mode' => 'chat-image',
                'title' => 'Prompt for a product banner',
                'turns' => [
                    [
                        'q' => 'Write me an image prompt for a hero banner for an AI writing tool.',
                        'a' => "> Wide editorial hero image: a clean desk at dawn, a single open laptop with a soft-glowing screen, warm side light from a window on the left, muted navy and amber palette, shallow depth of field, generous empty space on the right for headline text, photographic, no text, no logos, no hands.\n\nThe two clauses doing the real work are the empty space on the right and \"no text\" — models will happily fill your headline area with garbled lettering otherwise.",
                    ],
                ],
            ],
            [
                'mode' => null,
                'title' => 'Which model should I use?',
                'turns' => [
                    [
                        'q' => 'There are a dozen models in the picker. How do I choose one?',
                        'a' => "Pick by task shape, not by benchmark:\n\n- **Long reasoning, code, anything you will ship** — the largest model you can afford; the credit difference is smaller than the time you lose re-checking a cheap answer.\n- **Bulk work — captions, tags, rewrites** — a fast, cheap model. Quality differences vanish at short output lengths.\n- **Anything needing current information** — a search-backed model, or the answer will be confidently out of date.\n\nWhen unsure, run the same prompt through a cheap and an expensive model once. The gap on your work is usually different from the gap on the leaderboard.",
                    ],
                ],
            ],
        ];
    }

    /**
     * AI Knowledge Base demo content: settings, a help centre with real articles behind it,
     * and the reader activity every screen in the addon reports on.
     *
     * The addon's own KbSeeder ships three categories and six starter articles to every
     * buyer, all published at the moment of install — which leaves the help centre looking
     * like it was written in one afternoon and the admin Analytics screen reporting zeros
     * across the board. This spreads that library over five months, fills out the topics
     * around it, and adds the searches, votes and views a help centre accumulates by being
     * used. None of that belongs in an addon seeder that runs on real installs.
     *
     * Embeddings are deliberately NOT fabricated. Semantic search matches a live query
     * vector against stored ones, so invented numbers would return either nothing or
     * nonsense while the admin screen claimed the library was fully indexed. Articles are
     * left `pending` and the real ingestion job is dispatched (see below), which is the same
     * path the admin's own Save button takes.
     */
    private function seedKnowledgeBaseShowcase(array $demoUsers): void
    {
        if (! function_exists('is_addon_active') || ! is_addon_active('ai-knowledge-base')) {
            return;
        }

        if (! class_exists(KbArticle::class) || ! Schema::hasTable('kb_articles')) {
            return;
        }

        $this->seedKbSettings();

        $categories = $this->seedKbCategories();
        $articles = $this->seedKbArticles($categories);

        if ($articles === []) {
            return;
        }

        $this->seedKbEngagement($articles, $demoUsers);
        $this->seedKbSearchTrail($articles, $demoUsers);
        $this->dispatchKbIngestion($articles);
    }

    /**
     * Present the help centre the way a buyer should first meet it.
     *
     * `ai_model` and `provider` are left untouched on purpose: pinning them here would
     * override the site default with a model this install may hold no key for, and
     * KbSearchService already falls back to the site default when they are unset.
     */
    private function seedKbSettings(): void
    {
        $settings = [
            ['enabled', true, 'boolean'],
            ['public_slug', 'help', 'string'],
            ['page_title', 'Help Center', 'string'],
            ['page_description', 'Answers about plans, credits, the AI tools and the API — searchable, and written by the people who built it.', 'string'],

            // Menus the demo site actually has (section 14 seeds all four). A slug that does
            // not resolve renders the help centre with no navigation at all. The header gets
            // the help centre's OWN menu rather than the site's — a reader inside the docs
            // wants the other articles, not the marketing nav they arrived through.
            ['header_menu', 'knowledge-base', 'string'],
            ['footer_menu', 'footer', 'string'],

            ['top_k', 5, 'integer'],
            ['max_answer_tokens', 512, 'integer'],
            ['system_prompt', $this->kbSystemPrompt(), 'string'],

            ['show_vote_buttons', true, 'boolean'],
            ['allow_guest_search', true, 'boolean'],

            // The embeddable widget is one of the addon's selling points and invisible until
            // switched on, so the demo ships with it live.
            ['widget_enabled', true, 'boolean'],
            ['widget_accent_color', '#10b981', 'string'],
        ];

        foreach ($settings as [$key, $value, $type]) {
            AddonSetting::set('ai-knowledge-base', $key, $value, $type);
        }
    }

    /**
     * The answer prompt. Written to be honest about the limits of retrieval — a help-centre
     * bot that invents a policy is worse than one that says it cannot find the page.
     */
    private function kbSystemPrompt(): string
    {
        return <<<'PROMPT'
        You answer questions about {site_name} using only the help-centre articles provided to you as context.

        Rules:
        - Answer from the context. If the context does not cover the question, say so plainly and point the reader at Support rather than guessing.
        - Never invent prices, limits, dates or policy. If a number is not in the context, do not state one.
        - Be brief: two or three short paragraphs, or a short list when the answer is a sequence of steps.
        - Cite the article you used by title, so the reader can go and read the whole thing.
        - Match the reader's language.
        PROMPT;
    }

    /**
     * The help centre's sections. The first three already exist (KbSeeder ships them), so
     * these are keyed by slug — the demo adds three more and leaves the originals in place
     * with their articles attached.
     *
     * @return array<string, KbCategory>
     */
    private function seedKbCategories(): array
    {
        $rows = [
            ['getting-started', 'Getting Started', 'First document, first chat, and how the credits behind them work.', 'ti ti-rocket', 1],
            ['ai-tools', 'Using the AI Tools', 'Picking models, chaining tools together, and getting better output.', 'ti ti-wand', 2],
            ['account-billing', 'Account & Billing', 'Plans, invoices, upgrades and everything money touches.', 'ti ti-credit-card', 3],
            ['api-integrations', 'API & Integrations', 'Keys, rate limits, embeds and calling the platform from your own code.', 'ti ti-plug', 4],
            ['affiliates', 'Affiliates & Payouts', 'How referrals are tracked, what they pay, and when.', 'ti ti-users-group', 5],
            ['troubleshooting', 'Troubleshooting', 'When something fails, times out, or will not let you in.', 'ti ti-tool', 6],
        ];

        $categories = [];

        foreach ($rows as [$slug, $name, $description, $icon, $order]) {
            $category = KbCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'sort_order' => $order,
                    'is_active' => true,
                    'meta_title' => $name,
                    'meta_desc' => $description,
                ]
            );

            $categories[$slug] = $category;
        }

        return $categories;
    }

    /**
     * Write the library and give it a history.
     *
     * Two things matter beyond the prose. Publication dates are spread across five months
     * with a few landing inside the last fortnight, because the Analytics screen compares
     * articles published this week against last week and every date being "now" makes that
     * badge meaningless. And two articles stay in draft: the articles list has a status
     * filter, and a demo where every row says Published never exercises it.
     *
     * @param  array<string, KbCategory>  $categories
     * @return array<int, array{model: KbArticle, votes_up: int, votes_down: int}>
     */
    private function seedKbArticles(array $categories): array
    {
        $adminId = Schema::hasTable('admins')
            ? DB::table('admins')->where('email', config('demo.admin_email'))->value('id')
            : null;

        $articles = [];

        foreach ($this->kbArticleBank() as $row) {
            $category = $categories[$row['category']] ?? null;

            $publishedAt = $row['status'] === 'published'
                ? now()->subDays($row['days'])->setTime(9 + ($row['days'] % 8), ($row['days'] * 7) % 60)
                : null;

            $article = KbArticle::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'kb_category_id' => $category?->id,
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'body' => $row['body'],
                    'status' => $row['status'],
                    'sort_order' => $row['sort'] ?? 0,
                    'meta_title' => $row['title'],
                    'meta_desc' => $row['excerpt'],
                    'created_by' => $adminId,
                    'published_at' => $publishedAt,
                ]
            );

            // An article written five months ago should not have been created today. The
            // row's own timestamps drive the admin list's "Updated" column.
            $this->backdate(
                $article,
                $publishedAt?->copy()->subDays(mt_rand(1, 4)) ?? now()->subDays($row['days']),
                $publishedAt ?? now()->subDays(max(0, $row['days'] - 2))
            );

            // The vote counts travel beside the model rather than on it: they describe what
            // the demo readers should make of the article, and are not columns.
            $articles[] = [
                'model' => $article,
                'votes_up' => $row['votes_up'],
                'votes_down' => $row['votes_down'],
            ];
        }

        return $articles;
    }

    /**
     * Reader reaction: votes, and the view counts that rank the "Popular articles" list.
     *
     * Votes are stored as rows and the article counters are then recomputed from them,
     * exactly as KbVoteController does. Writing the counters directly would leave the two
     * disagreeing the first time a real visitor voted, and the article page reads the rows
     * to decide whether you have already voted.
     *
     * @param  array<int, array{model: KbArticle, votes_up: int, votes_down: int}>  $articles
     */
    private function seedKbEngagement(array $articles, array $demoUsers): void
    {
        if (! Schema::hasTable('kb_article_votes')) {
            return;
        }

        $users = array_values(array_filter($demoUsers));
        $articleIds = array_map(fn (array $item) => $item['model']->id, $articles);

        // Rebuild rather than accumulate: a reset should not leave last month's votes
        // stacked under this month's.
        KbArticleVote::whereIn('kb_article_id', $articleIds)->delete();

        $voteIndex = 0;

        foreach ($articles as $item) {
            $article = $item['model'];

            if ($article->status !== 'published') {
                continue;
            }

            $up = $item['votes_up'];
            $down = $item['votes_down'];

            foreach ([1 => $up, -1 => $down] as $vote => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $voteIndex++;

                    // Roughly a third of readers are signed in; the rest are anonymous, which
                    // is what a public help centre actually looks like.
                    $user = ($users !== [] && $voteIndex % 3 === 0)
                        ? $users[$voteIndex % count($users)]
                        : null;

                    $castAt = ($article->published_at ?? now()->subDays(30))
                        ->copy()
                        ->addDays(mt_rand(0, max(1, (int) $article->published_at?->diffInDays(now()))))
                        ->setTime(mt_rand(7, 22), mt_rand(0, 59));

                    if ($castAt->isFuture()) {
                        $castAt = now()->subHours(mt_rand(1, 48));
                    }

                    $row = KbArticleVote::create([
                        'kb_article_id' => $article->id,
                        // Unique per (article, session) — the table enforces it.
                        'session_id' => 'demo-kb-' . $article->id . '-' . $voteIndex,
                        'user_id' => $user?->id,
                        'vote' => $vote,
                    ]);

                    $this->backdate($row, $castAt);
                }
            }

            $helpful = KbArticleVote::where('kb_article_id', $article->id)->where('vote', 1)->count();
            $notHelpful = KbArticleVote::where('kb_article_id', $article->id)->where('vote', -1)->count();

            // Views track votes loosely — a page nobody reads collects no votes — with enough
            // spread that "Popular articles" is a real ranking and not the vote list again.
            $article->forceFill([
                'helpful_count' => $helpful,
                'not_helpful_count' => $notHelpful,
                'views' => (($helpful + $notHelpful) * mt_rand(9, 26)) + mt_rand(15, 90),
            ])->save();
        }
    }

    /**
     * Thirty days of help-centre searches — the spine of the KB Analytics screen.
     *
     * The four stat cards want: rows dated today (and on the same weekday a week ago, which
     * is what "Searches Today" compares against), a heavier last-7-days than the 7 before
     * it, and an answered/unanswered split that moves between those windows. Below them,
     * "Top queries" needs the same question asked by different people, and the unanswered
     * list needs questions the library genuinely does not cover — which is the panel's whole
     * point: it is a content to-do list, not an error log.
     *
     * @param  array<int, KbArticle>  $articles
     */
    private function seedKbSearchTrail(array $articles, array $demoUsers): void
    {
        if (! Schema::hasTable('kb_searches')) {
            return;
        }

        // Pure activity data — rebuilt on every reset, like the chat trail.
        KbSearch::query()->delete();

        $users = array_values(array_filter($demoUsers));

        $articleIds = array_values(array_map(
            fn (array $item) => $item['model']->id,
            array_filter($articles, fn (array $item) => $item['model']->status === 'published')
        ));

        if ($articleIds === []) {
            return;
        }

        // Answered queries, weighted — the first few are what people actually come to a help
        // centre for, and a flat distribution would leave "Top queries" a 30-way tie.
        $answered = [
            ['how do credits work', 9],
            ['reset my password', 7],
            ['cancel subscription', 7],
            ['upgrade to pro', 6],
            ['api key', 6],
            ['generation failed', 5],
            ['which model should i use', 5],
            ['refund policy', 4],
            ['affiliate commission', 4],
            ['invoice download', 4],
            ['upload a file to chat', 3],
            ['embed a tool on my site', 3],
            ['chat modes', 3],
            ['rate limits', 3],
            ['two factor authentication', 2],
            ['tool chains', 2],
            ['payout minimum', 2],
            ['credits ran out mid generation', 2],
            ['change plan mid cycle', 2],
            ['answer stopped halfway', 1],
        ];

        // What the library does not answer. These are the rows the admin is meant to act on,
        // so they read like real gaps rather than typos — and they repeat, because an
        // unanswered question asked once is noise and asked nine times is a missing article.
        $unanswered = [
            ['team seats', 6],
            ['is there a soc 2 report', 4],
            ['export documents to notion', 4],
            ['does it support german', 3],
            ['bulk upload a csv of topics', 3],
            ['white label the help center', 2],
            ['on premise install', 2],
            ['student discount', 2],
        ];

        $pool = [];
        foreach ($answered as [$query, $weight]) {
            $pool = array_merge($pool, array_fill(0, $weight, ['query' => $query, 'answered' => true]));
        }
        foreach ($unanswered as [$query, $weight]) {
            $pool = array_merge($pool, array_fill(0, $weight, ['query' => $query, 'answered' => false]));
        }

        $rows = [];
        $sessionIndex = 0;

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            // Busier recently, so the week-over-week cards read as a help centre finding its
            // audience. Today is deliberately well-populated: "Searches Today" is the first
            // number on the screen.
            $perDay = match (true) {
                $daysAgo === 0 => mt_rand(11, 16),
                $daysAgo <= 6 => mt_rand(13, 20),
                $daysAgo <= 13 => mt_rand(8, 13),
                default => mt_rand(4, 9),
            };

            for ($n = 0; $n < $perDay; $n++) {
                $pick = $pool[array_rand($pool)];
                $sessionIndex++;

                $at = now()->subDays($daysAgo)->setTime(mt_rand(6, 22), mt_rand(0, 59));

                if ($at->isFuture()) {
                    $at = now()->subMinutes(mt_rand(5, 240));
                }

                // A handful of articles come back per hit, and the answer cites the top few.
                $hits = $pick['answered']
                    ? (array) array_rand(array_flip($articleIds), min(count($articleIds), mt_rand(2, 4)))
                    : [];

                $user = ($users !== [] && $sessionIndex % 4 === 0)
                    ? $users[$sessionIndex % count($users)]
                    : null;

                $rows[] = [
                    'session_id' => 'demo-kb-search-' . str_pad((string) $sessionIndex, 5, '0', STR_PAD_LEFT),
                    'user_id' => $user?->id,
                    'query' => $pick['query'],
                    'results_count' => count($hits),
                    'was_answered' => $pick['answered'],
                    'article_ids' => $hits === [] ? null : json_encode(array_values($hits)),
                    'created_at' => $at,
                ];
            }
        }

        // The model has no updated_at and sets created_at itself, so a chunked insert is both
        // faster and truer to the table than 400 individual saves.
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('kb_searches')->insert($chunk);
        }
    }

    /**
     * Queue the real ingestion for the articles just written.
     *
     * This is the same job the admin's Save button dispatches; running it is what actually
     * makes semantic search work, and it needs a worker on the `embeddings` queue plus a
     * provider key. Skipped when the queue would run inline (`sync`), because a seeder must
     * not make paid API calls — or fail — in the middle of building a demo database.
     *
     * @param  array<int, array{model: KbArticle, votes_up: int, votes_down: int}>  $articles
     */
    private function dispatchKbIngestion(array $articles): void
    {
        if (! class_exists(\Addons\AiKnowledgeBase\Jobs\IngestKbArticle::class)) {
            return;
        }

        if (config('queue.default') === 'sync') {
            return;
        }

        foreach ($articles as $item) {
            if ($item['model']->status !== 'published') {
                continue;
            }

            \Addons\AiKnowledgeBase\Jobs\IngestKbArticle::dispatch($item['model']->id)->onQueue('embeddings');
        }
    }

    /**
     * The library itself.
     *
     * The first six slugs are the ones KbSeeder ships — matched deliberately, so the demo
     * deepens the starter articles instead of publishing a near-duplicate beside each one.
     * `days` is how long ago the article was published; the votes are what the demo readers
     * made of it, and they are not uniformly positive.
     */
    private function kbArticleBank(): array
    {
        return [
            // ─── Getting Started ────────────────────────────────────────
            [
                'slug' => 'how-to-create-your-first-ai-document',
                'category' => 'getting-started',
                'title' => 'How to create your first AI document',
                'excerpt' => 'From an empty dashboard to a finished draft, in about two minutes.',
                'body' => '<h2>Before you start</h2><p>You need an account and at least a few credits. New accounts arrive with enough to produce roughly ten short pieces, so there is nothing to buy before your first attempt.</p><h2>Pick the tool, not the model</h2><p>Open <strong>AI Tools</strong> and choose by what you are making — Blog Article, Product Description, Email Subject Lines. Each tool carries a prompt that has already been tuned for that format, which matters far more than which model sits behind it. The model picker is there when you want it, and safe to ignore until you do.</p><h2>Fill in the brief</h2><p>Every field you complete narrows the output. Topic alone produces something generic; topic plus audience plus the one point you want made produces something you can edit rather than rewrite. If a field does not apply, leave it empty rather than inventing an answer.</p><h2>Generate, then edit</h2><p>Output appears in the editor within a few seconds and is saved to <strong>Documents</strong> automatically — you will not lose it by navigating away. Treat the first result as a draft to react to. Regenerating with one changed field is usually faster than rewriting a paragraph by hand.</p>',
                'status' => 'published',
                'days' => 148,
                'sort' => 1,
                'votes_up' => 64,
                'votes_down' => 5,
            ],
            [
                'slug' => 'understanding-credits-and-usage-limits',
                'category' => 'getting-started',
                'title' => 'Understanding credits and usage limits',
                'excerpt' => 'What a credit is, what consumes them, and how to see where yours went.',
                'body' => '<h2>What a credit is</h2><p>A credit is the platform\'s unit of AI spend. Every generation costs credits in proportion to the tokens it uses and the price of the model that produced them — a long article on a top-tier model costs many times what a meta description on a fast model does.</p><h2>What consumes them</h2><ul><li>Tool generations and chat replies, priced by tokens in plus tokens out.</li><li>File analysis, which pays for reading the file as well as answering about it.</li><li>Image and audio jobs, priced per item rather than per token.</li></ul><p>Browsing, editing a saved document and re-reading a chat cost nothing.</p><h2>Seeing where they went</h2><p>Open <strong>Dashboard → Usage</strong>. It breaks spend down by day, by tool and by model, and shows how much of the current allowance is left before it resets. If one tool dominates the chart, switching just that tool to a cheaper model usually costs less quality than switching everything.</p><h2>Running low</h2><p>You will see a warning at 80% of your allowance. Nothing is deleted when you reach the limit — generation pauses until the allowance resets or you move up a plan.</p>',
                'status' => 'published',
                'days' => 141,
                'sort' => 2,
                'votes_up' => 88,
                'votes_down' => 9,
            ],
            [
                'slug' => 'choosing-the-right-ai-model',
                'category' => 'getting-started',
                'title' => 'Choosing the right AI model for the job',
                'excerpt' => 'A short, opinionated guide to the model picker — by task, not by benchmark.',
                'body' => '<h2>Match the model to the task</h2><p>The leaderboard is not your workload. Three rules cover almost everything:</p><ul><li><strong>Work you will publish or ship</strong> — reasoning, code, anything with a reader — use the largest model your plan allows. The credit difference is smaller than the time you lose checking a cheap answer.</li><li><strong>Bulk, short output</strong> — captions, tags, subject lines, rewrites — use a fast model. Quality differences shrink to nothing at that length.</li><li><strong>Anything about current events</strong> — use a search-backed model, or accept an answer that is confidently out of date.</li></ul><h2>Test it on your own work</h2><p>Run the same brief through a cheap model and an expensive one, once. The gap on your material is frequently smaller — occasionally larger — than the gap on any public benchmark, and after that comparison you will stop wondering.</p><h2>Defaults</h2><p>Each tool has a sensible default and each chat mode prefers models suited to it. Changing the model on one generation never changes it permanently; the picker resets to the default next time.</p>',
                'status' => 'published',
                'days' => 96,
                'sort' => 3,
                'votes_up' => 51,
                'votes_down' => 4,
            ],
            [
                'slug' => 'organising-documents-and-collections',
                'category' => 'getting-started',
                'title' => 'Organising your work with documents and collections',
                'excerpt' => 'Where output is saved, how to find it later, and what collections are for.',
                'body' => '<h2>Everything is saved</h2><p>Generated output lands in <strong>Documents</strong> the moment it finishes, titled from the brief you gave. Nothing is discarded when you close the tab, and editing a document never overwrites the original generation record in your history.</p><h2>Collections</h2><p>A collection is a folder for the tools you keep coming back to, not for documents — it exists so that a workflow you run every Monday is two clicks away instead of a search. Most people end up with three or four: one per client, or one per content type.</p><h2>Finding things later</h2><p>Search in Documents covers titles and body text. History, separately, records every generation with the tool and model that produced it — useful when you remember making something but not what you called it.</p><h2>Favourites</h2><p>Starring a tool pins it to the top of the tools list and to your dashboard. Starring a document does the same in Documents. They are independent of collections; use both.</p>',
                'status' => 'published',
                'days' => 62,
                'sort' => 4,
                'votes_up' => 29,
                'votes_down' => 3,
            ],
            [
                'slug' => 'migrating-from-another-ai-writing-tool',
                'category' => 'getting-started',
                'title' => 'Migrating from another AI writing tool',
                'excerpt' => 'Bringing your prompts, brand voice and existing drafts across.',
                'body' => '<h2>Draft</h2><p>Outline only — needs the import screen finished before this can be published.</p><h2>What to cover</h2><ul><li>Exporting drafts from the three most common competitors.</li><li>Translating a saved prompt into a tool brief.</li><li>Where brand voice settings live here versus there.</li></ul>',
                'status' => 'draft',
                'days' => 9,
                'sort' => 5,
                'votes_up' => 0,
                'votes_down' => 0,
            ],

            // ─── Using the AI Tools ─────────────────────────────────────
            [
                'slug' => 'how-chat-modes-work',
                'category' => 'ai-tools',
                'title' => 'How the AI Chatbot modes work',
                'excerpt' => 'What changes when you switch mode, and when it is worth doing.',
                'body' => '<h2>A mode is a starting point</h2><p>Each mode — Code, Write, Marketing, Research and the rest — carries its own system prompt, its own preferred models and its own starter prompts. Switching mode changes how the assistant approaches the question; it does not restrict what you can ask.</p><h2>Switching mid-conversation</h2><p>You can change mode inside an existing chat. The history stays, and the new mode applies from your next message onward — useful when a writing conversation turns into a data question. The transcript records where the switch happened.</p><h2>Which mode for which job</h2><ul><li><strong>Code</strong> — writing, fixing and explaining code, with syntax-highlighted blocks.</li><li><strong>Write</strong> — long-form drafting where tone and structure matter.</li><li><strong>Research</strong> — questions needing current information and citations.</li><li><strong>Analyze</strong> — interpreting data, comparing options, summarising reports.</li></ul><h2>If none of them fit</h2><p>Use the default. A mode you have to fight is worse than no mode, and custom instructions on your profile apply in every mode regardless.</p>',
                'status' => 'published',
                'days' => 74,
                'sort' => 1,
                'votes_up' => 43,
                'votes_down' => 6,
            ],
            [
                'slug' => 'uploading-files-to-a-chat',
                'category' => 'ai-tools',
                'title' => 'Uploading files to a chat',
                'excerpt' => 'Supported formats, size limits, and what the assistant can actually see.',
                'body' => '<h2>What you can attach</h2><p>PDF, DOCX, TXT, MD, CSV and common image formats. Text is extracted from documents and sent alongside your message; images are sent to the model directly, so it can describe or read what is in them.</p><h2>Size and count</h2><p>Up to five files per message, each within your plan\'s size limit — shown next to the attach button, and lower for signed-out visitors. Very long documents are truncated rather than rejected, so an important passage buried on page 90 may not make it into the context.</p><h2>What it costs</h2><p>An attached file is charged as input tokens like any other text. A long PDF can cost more than the answer it produces, which is worth knowing before uploading a whole annual report to ask one question about it.</p><h2>Privacy</h2><p>Files are stored against your account and used only in the conversations you attach them to. Guest uploads are cleared automatically; your own are removed when you delete the conversation.</p>',
                'status' => 'published',
                'days' => 47,
                'sort' => 2,
                'votes_up' => 37,
                'votes_down' => 2,
            ],
            [
                'slug' => 'building-a-tool-chain',
                'category' => 'ai-tools',
                'title' => 'Building a multi-step tool chain',
                'excerpt' => 'Wiring several tools into one run, and where chains go wrong.',
                'body' => '<h2>What a chain does</h2><p>A chain runs tools in sequence and feeds each output into the next input — outline, then draft, then meta description — so a workflow you would otherwise run by hand three times becomes one click.</p><h2>Building one</h2><p>Open <strong>Tool Chains</strong>, add steps in order, and map each step\'s input to either your original brief or the previous step\'s output. Run it once with a real brief before saving; a chain that is wrong in step two wastes credits on every step after it.</p><h2>Where they go wrong</h2><ul><li><strong>Too many steps.</strong> Beyond four or five, small errors compound into output nobody wants to edit.</li><li><strong>Passing everything forward.</strong> Feeding a whole draft into a step that needs only the title inflates cost and confuses the model.</li><li><strong>No checkpoint.</strong> For anything expensive, keep a step whose output you read before the rest runs.</li></ul><h2>Cost</h2><p>A chain costs the sum of its steps. The run summary shows the per-step breakdown, which is the fastest way to find the step doing the damage.</p>',
                'status' => 'published',
                'days' => 33,
                'sort' => 3,
                'votes_up' => 24,
                'votes_down' => 5,
            ],

            // ─── Account & Billing ──────────────────────────────────────
            [
                'slug' => 'how-to-upgrade-your-subscription',
                'category' => 'account-billing',
                'title' => 'How to upgrade your subscription',
                'excerpt' => 'What changes immediately, what carries over, and how billing is prorated.',
                'body' => '<h2>Upgrading</h2><p>Open <strong>Pricing</strong>, choose a plan and check out. The new allowance is available as soon as payment clears — there is no waiting period and no re-onboarding.</p><h2>Mid-cycle</h2><p>Upgrade part-way through a month and you are charged the difference for the remainder of the period, not a fresh full price. Credits you have not spent carry over rather than being reset.</p><h2>Monthly or annual</h2><p>Annual billing costs less across a full year and is charged once. If you are unsure whether the higher tier suits you, start monthly — moving up later is one click, and nothing is lost in the move.</p><h2>Payment methods</h2><p>Card and PayPal, processed by the gateway the operator has configured. Card details never touch this platform\'s servers; the gateway holds them and we store only the reference needed to bill you again.</p>',
                'status' => 'published',
                'days' => 132,
                'sort' => 1,
                'votes_up' => 46,
                'votes_down' => 7,
            ],
            [
                'slug' => 'managing-your-billing-and-invoices',
                'category' => 'account-billing',
                'title' => 'Managing your billing and invoices',
                'excerpt' => 'Finding invoices, changing your card, and updating the details printed on them.',
                'body' => '<h2>Invoices</h2><p>Every payment produces an invoice, listed under <strong>Dashboard → Billing</strong> and downloadable as a PDF. They remain available after you cancel — you do not need an active subscription to retrieve last year\'s paperwork.</p><h2>Billing details</h2><p>Company name, address and tax number are set in the same place and appear on invoices issued from then on. Invoices already issued are not retroactively altered; if you need one reissued, ask Support.</p><h2>Changing your card</h2><p>Add the new card before the old one expires and it is used at the next renewal. A failed renewal is retried a few times over several days before anything is suspended, and you are emailed at each attempt.</p><h2>Cancelling</h2><p>Cancel from the Billing page. Access continues to the end of the period you have already paid for, and credits inside that period remain yours to spend.</p>',
                'status' => 'published',
                'days' => 119,
                'sort' => 2,
                'votes_up' => 39,
                'votes_down' => 11,
            ],
            [
                'slug' => 'what-happens-when-credits-run-out',
                'category' => 'account-billing',
                'title' => 'What happens when you run out of credits',
                'excerpt' => 'What stops, what does not, and the options for getting going again.',
                'body' => '<h2>What stops</h2><p>New generations. That is all. Your documents, chats, history and settings are untouched, and you keep full access to everything already produced.</p><h2>What you will see</h2><p>A warning at 80% of your allowance, and a clear message rather than a failure when you reach the limit. A generation is never charged half-way: if it cannot be paid for, it does not start.</p><h2>Getting going again</h2><ul><li>Wait for the allowance to reset at the start of your next billing period.</li><li>Move up a plan — the new allowance is available immediately, and mid-cycle upgrades are prorated.</li><li>Switch heavy work to a cheaper model. The Usage screen shows which tool is spending most.</li></ul><h2>Interrupted generations</h2><p>If a generation fails after starting, the credits reserved for it are returned automatically. You should never be billed for output you did not receive; if the balance looks wrong, Support can see the ledger entry.</p>',
                'status' => 'published',
                'days' => 12,
                'sort' => 3,
                'votes_up' => 18,
                'votes_down' => 1,
            ],

            // ─── API & Integrations ─────────────────────────────────────
            [
                'slug' => 'creating-and-rotating-api-keys',
                'category' => 'api-integrations',
                'title' => 'Creating and rotating API keys',
                'excerpt' => 'Issuing a key, scoping it, and replacing one without downtime.',
                'body' => '<h2>Creating a key</h2><p>Open <strong>Dashboard → API</strong> and create a key. The full value is shown once, at creation — it is stored hashed, so nobody, including Support, can recover it later. Save it to your secret store before closing the dialog.</p><h2>Using it</h2><p>Send it as a bearer token on every request. Calls made with a key are billed to the account that owns it and appear in your usage history alongside everything else, tagged by key so you can tell integrations apart.</p><h2>Rotating without downtime</h2><p>Create the replacement first, deploy it, confirm traffic has moved, then revoke the old one. Revocation takes effect immediately, so revoking before deploying takes your integration down for as long as the deploy takes.</p><h2>If a key leaks</h2><p>Revoke it now and create a new one. Then check the usage history for that key — unexpected volume from an unfamiliar time of day is the clearest sign it was used by someone else.</p>',
                'status' => 'published',
                'days' => 88,
                'sort' => 1,
                'votes_up' => 33,
                'votes_down' => 2,
            ],
            [
                'slug' => 'embedding-a-tool-on-your-own-site',
                'category' => 'api-integrations',
                'title' => 'Embedding a tool on your own site',
                'excerpt' => 'Putting a generator on your own page, and who pays for the generations.',
                'body' => '<h2>Creating an embed</h2><p>Pick a tool, create an embed, and copy the snippet. It renders the tool inside an iframe on your page with your own accent colour, and needs no build step.</p><h2>Who pays</h2><p>You do. Generations from an embed are billed to the account that created it, which is the point for a lead magnet and a hazard for an open page — set the per-visitor limits before you publish, not after.</p><h2>Restricting where it runs</h2><p>Lock each embed to the domains you intend to use it on. Without that, a copied snippet works anywhere and spends your credits from someone else\'s website.</p><h2>Tracking</h2><p>Each embed records its own usage, so you can see which page produces generations and what they cost you. It is the same data as the main usage screen, filtered to that snippet.</p>',
                'status' => 'published',
                'days' => 54,
                'sort' => 2,
                'votes_up' => 21,
                'votes_down' => 4,
            ],

            // ─── Affiliates & Payouts ───────────────────────────────────
            [
                'slug' => 'how-affiliate-commissions-are-calculated',
                'category' => 'affiliates',
                'title' => 'How affiliate commissions are calculated',
                'excerpt' => 'What counts as a referral, what it pays, and when it is confirmed.',
                'body' => '<h2>What counts</h2><p>A referral is attributed when someone arrives through your link and creates an account within the cookie window. Attribution is last-click, and self-referrals are excluded automatically.</p><h2>What it pays</h2><p>A percentage of what the referred account actually pays, applied to each payment they make while the commission remains active. The current rate is on your affiliate dashboard; changes apply to new referrals rather than existing ones.</p><h2>Pending versus confirmed</h2><p>A commission is pending until the referred payment clears the refund window. Only confirmed commissions count toward a payout — this is what stops a refunded first month being paid out as commission.</p><h2>Reading the dashboard</h2><p>Clicks, signups, conversions and earnings are separated deliberately. A healthy click-to-signup rate with a poor signup-to-paid rate usually means the audience is right and the page they land on is not.</p>',
                'status' => 'published',
                'days' => 71,
                'sort' => 1,
                'votes_up' => 27,
                'votes_down' => 3,
            ],
            [
                'slug' => 'requesting-an-affiliate-payout',
                'category' => 'affiliates',
                'title' => 'Requesting a payout',
                'excerpt' => 'Minimums, methods, timing, and the two things that hold payouts up.',
                'body' => '<h2>Before you can request</h2><p>Your confirmed balance must be at or above the minimum shown on the affiliate dashboard, and your payout details must be saved. Pending commissions do not count toward the minimum.</p><h2>Requesting</h2><p>Request from the affiliate dashboard. The amount is reserved immediately so it cannot be requested twice, and the request appears with its status while it is reviewed.</p><h2>Timing</h2><p>Requests are processed in batches. Expect a few working days from approval to money arriving, longer across bank holidays or where your payment provider adds its own delay.</p><h2>What holds a payout up</h2><ul><li>Payout details that do not match the name on the account.</li><li>A referred payment still inside its refund window — that commission stays pending.</li><li>Missing tax information, where the operator requires it.</li></ul><p>Each of these shows on the request itself, so you can see which one applies rather than guessing.</p>',
                'status' => 'published',
                'days' => 26,
                'sort' => 2,
                'votes_up' => 16,
                'votes_down' => 5,
            ],

            // ─── Troubleshooting ────────────────────────────────────────
            [
                'slug' => 'ai-generation-failed-what-to-do',
                'category' => 'troubleshooting',
                'title' => 'AI generation failed — what to do',
                'excerpt' => 'The four things that cause almost every failure, in the order worth checking.',
                'body' => '<h2>1. Credits</h2><p>Check the balance on your dashboard first — it is the most common cause and the quickest to rule out. A generation that cannot be paid for never starts, so nothing was charged.</p><h2>2. A timeout on a long request</h2><p>Very long outputs occasionally exceed the provider\'s limit. Reducing the requested length, or splitting one enormous request into two, resolves nearly all of these.</p><h2>3. The content filter</h2><p>Some inputs are refused by the model\'s own safety layer. Rephrasing the brief more specifically usually clears it; if the subject is genuinely sensitive, a different model may handle it differently.</p><h2>4. A provider outage</h2><p>Occasionally the fault is upstream. Trying the same brief on a different model tells you in one attempt whether the problem is the provider or the request.</p><h2>Still stuck</h2><p>Open a support ticket with the tool name and roughly when it happened — that is enough to find the exact log entry, including the provider\'s own error.</p>',
                'status' => 'published',
                'days' => 108,
                'sort' => 1,
                'votes_up' => 58,
                'votes_down' => 14,
            ],
            [
                'slug' => 'clearing-your-browser-cache-and-cookies',
                'category' => 'troubleshooting',
                'title' => 'Clearing your browser cache and cookies',
                'excerpt' => 'The fix for stale screens and login loops, browser by browser.',
                'body' => '<h2>When this helps</h2><p>A page that shows old data after an update, a login that bounces you back to the sign-in screen, or a layout that renders wrongly in one browser only. It will not help with generation failures — those are server-side.</p><h2>Chrome and Edge</h2><p>Ctrl+Shift+Delete (Cmd+Shift+Delete on macOS), select cached images and files plus cookies, choose All time, clear.</p><h2>Firefox</h2><p>Ctrl+Shift+Delete, set the range to Everything, tick Cache and Cookies, clear now.</p><h2>Safari</h2><p>Safari → Settings → Privacy → Manage Website Data → Remove All.</p><h2>Afterwards</h2><p>Restart the browser and sign in again. If the problem survives a cache clear and a different browser, it is not your cache — send Support a screenshot and the page address.</p>',
                'status' => 'published',
                'days' => 101,
                'sort' => 2,
                'votes_up' => 31,
                'votes_down' => 12,
            ],
            [
                'slug' => 'why-an-answer-stopped-mid-sentence',
                'category' => 'troubleshooting',
                'title' => 'Why an answer stopped mid-sentence',
                'excerpt' => 'Truncated output has three causes, and only one of them is a fault.',
                'body' => '<h2>It hit the token ceiling</h2><p>Every response has a maximum length, set per plan and per tool. Reaching it stops the answer wherever it happens to be. Asking the assistant to continue picks up from the cut, and for tools, a shorter brief or a smaller requested output avoids it entirely.</p><h2>You navigated away</h2><p>Answers stream as they are produced. Closing the tab mid-stream stops generation, and what had already arrived is saved. You are billed for the tokens actually produced, not for the answer you did not get.</p><h2>The provider dropped the connection</h2><p>Rare, and the only one that is genuinely a fault. Retrying is the right first move; if it happens repeatedly on the same model within a short period, that model is having a bad day and another will work.</p><h2>Telling them apart</h2><p>An answer that ends on a clean sentence boundary is usually a ceiling. One that stops mid-word is a dropped connection.</p>',
                'status' => 'published',
                'days' => 5,
                'sort' => 3,
                'votes_up' => 9,
                'votes_down' => 1,
            ],
            [
                'slug' => 'login-and-two-factor-problems',
                'category' => 'troubleshooting',
                'title' => 'Login and two-factor problems',
                'excerpt' => 'Codes that will not work, lost devices, and recovery codes.',
                'body' => '<h2>The code is rejected</h2><p>Authenticator codes depend on your device clock. A phone a minute out of step produces codes that are always wrong — enable automatic time on the device and try again. Each code is also single-use; if you mistype one, wait for the next.</p><h2>You lost the device</h2><p>Use a recovery code from the set issued when you switched two-factor on. Each works once. If SMS is enabled on your account, the login screen offers it as an alternative channel.</p><h2>No codes and no device</h2><p>Contact Support from the email address on the account. Verification is deliberately slow here — the same process that protects your account from someone impersonating you also delays you.</p><h2>Password resets</h2><p>The reset link is valid for sixty minutes and once only. If it has expired or already been used, request a fresh one rather than re-clicking the old email.</p>',
                'status' => 'published',
                'days' => 3,
                'sort' => 4,
                'votes_up' => 7,
                'votes_down' => 2,
            ],
            [
                'slug' => 'team-seats-and-shared-workspaces',
                'category' => 'ai-tools',
                'title' => 'Team seats and shared workspaces',
                'excerpt' => 'Placeholder for the seats feature — do not publish until pricing is signed off.',
                'body' => '<h2>Draft</h2><p>Holding page for the most-asked unanswered question in the help centre. Publish once seat pricing is confirmed.</p><h2>Must cover</h2><ul><li>How a seat differs from an extra account.</li><li>Whether credits pool across the team.</li><li>What an admin can see of a member\'s work.</li></ul>',
                'status' => 'draft',
                'days' => 4,
                'sort' => 9,
                'votes_up' => 0,
                'votes_down' => 0,
            ],
        ];
    }

    /**
     * FakerAI demo content: generator defaults plus a run history with a working undo behind
     * every row.
     *
     * The addon has no content of its own — it is a machine for producing other addons'
     * content — so what an evaluator needs to see is the *record* of it having been used:
     * the History page with real runs, real token spend, an admin's name against each, and a
     * failure among the successes.
     *
     * The ledger those runs carry is not decoration. Each batch is attributed the demo rows
     * of its own type that this seeder created — the testimonials, reviews, comments,
     * favourites and counter values it would have produced had the operator generated them
     * with FakerAI rather than with a seeder. So "Delete batch" genuinely deletes exactly
     * those rows and subtracts exactly those counter amounts, which is the feature the page
     * is selling. Nothing is invented and nothing is double-counted: no counter is bumped
     * here, only attributed, so the amount a rollback subtracts is always a slice of a value
     * that already exists.
     *
     * (Demo mode blocks the POST that triggers a rollback, so a visitor cannot empty the
     * demo site with it. On a real install it does precisely what it says.)
     */
    private function seedFakerAiShowcase(): void
    {
        if (! function_exists('is_addon_active') || ! is_addon_active('faker-ai')) {
            return;
        }

        if (! class_exists(FakerBatch::class) || ! Schema::hasTable('faker_ai_batches')) {
            return;
        }

        $this->seedFakerAiSettings();
        $this->seedFakerAiBatchHistory();
    }

    /**
     * The generator's defaults, as an operator running a demo site would set them.
     */
    private function seedFakerAiSettings(): void
    {
        $settings = [
            ['default_language', 'English', 'string'],
            // Steers the voice of AI-written testimonials, reviews and comments. The default
            // manifest wording is good; this adds the one instruction that most improves the
            // output — variety in length, which is what makes fake reviews read as fake.
            ['default_tone', 'authentic, varied, conversational — mix short one-line reactions with longer detailed ones', 'string'],
            // Wide enough that generated rows spread across a believable history rather than
            // stacking on one afternoon.
            ['backdate_days', 120, 'integer'],
            ['ai_chunk_size', 20, 'integer'],
        ];

        foreach ($settings as [$key, $value, $type]) {
            AddonSetting::set('faker-ai', $key, $value, $type);
        }
    }

    /**
     * Eleven runs across the last seven weeks: nine completed, one that failed on a provider
     * rate limit, and one small recent run so the top of the list is fresh.
     *
     * Token spend is recorded only for the generators that actually call a model. The
     * numeric ones — usage, views, shares, favourites, ratings — report zero tokens, because
     * they invent counters rather than prose, and a demo that showed them burning tokens
     * would misrepresent what the addon costs to run.
     */
    private function seedFakerAiBatchHistory(): void
    {
        $adminId = Schema::hasTable('admins')
            ? DB::table('admins')->where('email', config('demo.admin_email'))->value('id')
            : null;

        // Rebuild: the ledger points at rows this seeder recreates on every run, so keeping
        // an old batch would leave it pointing at ids that no longer exist.
        if (Schema::hasTable('faker_ai_records')) {
            DB::table('faker_ai_records')->delete();
        }
        DB::table('faker_ai_batches')->delete();

        foreach ($this->fakerAiBatchPlan() as $plan) {
            $ledger = ($plan['ledger'])();

            // A run that could find nothing to attribute is not written at all — an empty
            // batch claiming 40 insertions would be the one dishonest row on the page.
            if ($plan['status'] === 'completed' && $ledger['inserted'] === 0) {
                continue;
            }

            $createdAt = now()->subDays($plan['days'])->setTime($plan['hour'], ($plan['days'] * 11) % 60);

            $batch = FakerBatch::create([
                'type' => $plan['type'],
                'label' => $plan['label'],
                'target' => $plan['target'] === null ? null : json_encode($plan['target']),
                'target_label' => $plan['target_label'],
                'requested_count' => $plan['requested'],
                'inserted_count' => $plan['status'] === 'completed' ? $ledger['inserted'] : 0,
                'options' => array_merge([
                    'language' => 'English',
                    'tone' => addon_setting('faker-ai', 'default_tone'),
                    'prompt' => $plan['prompt'] ?? null,
                ], $plan['options'] ?? []),
                'tokens_input' => $plan['tokens'][0] ?? 0,
                'tokens_output' => $plan['tokens'][1] ?? 0,
                'status' => $plan['status'],
                'error' => $plan['error'] ?? null,
                'created_by' => $adminId,
            ]);

            $this->backdate($batch, $createdAt, $createdAt->copy()->addMinutes(mt_rand(1, 6)));

            if ($plan['status'] !== 'completed') {
                continue;
            }

            $this->writeFakerAiLedger($batch, $ledger, $createdAt);
        }
    }

    /**
     * Write a batch's reversal ledger.
     *
     * Insert records are written oldest-id-first because BatchRollback replays newest-first
     * so that a child row (a review) is removed before the author it belongs to — the
     * generators log authors first for the same reason.
     *
     * @param  array{inserted: int, deletes: array<int, array{0: string, 1: string}>, decrements: array<int, array{0: string, 1: string, 2: string, 3: int}>}  $ledger
     */
    private function writeFakerAiLedger(FakerBatch $batch, array $ledger, \Carbon\Carbon $createdAt): void
    {
        $rows = [];

        foreach ($ledger['deletes'] as [$type, $id]) {
            $rows[] = [
                'batch_id' => $batch->id,
                'action' => 'delete',
                'subject_type' => $type,
                'subject_id' => (string) $id,
                'column' => null,
                'amount' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach ($ledger['decrements'] as [$type, $id, $column, $amount]) {
            $rows[] = [
                'batch_id' => $batch->id,
                'action' => 'decrement',
                'subject_type' => $type,
                'subject_id' => (string) $id,
                'column' => $column,
                'amount' => $amount,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('faker_ai_records')->insert($chunk);
        }
    }

    /**
     * The runs, newest last. Each `ledger` closure resolves the real rows the run is
     * credited with at seed time, so a batch never references an id that is not there.
     */
    private function fakerAiBatchPlan(): array
    {
        return [
            [
                'type' => 'users',
                'label' => 'Users',
                'target' => null,
                'target_label' => null,
                'requested' => 12,
                'days' => 47,
                'hour' => 10,
                'status' => 'completed',
                'tokens' => [1_480, 3_920],
                'prompt' => 'Mixed seniority, mostly marketing and product roles, non-US names included.',
                'ledger' => fn () => $this->fakerAiInsertLedger(
                    User::class,
                    User::query()
                        ->where('email', 'like', '%@demo.com')
                        ->whereNotIn('email', array_filter([config('demo.user_email'), config('demo.admin_email')]))
                        ->orderByDesc('id')
                        ->limit(12)
                        ->pluck('id')
                        ->all()
                ),
            ],
            [
                'type' => 'testimonials',
                'label' => 'Testimonials',
                'target' => null,
                'target_label' => null,
                'requested' => 10,
                'days' => 44,
                'hour' => 11,
                'status' => 'completed',
                'tokens' => [980, 4_610],
                'options' => ['rating' => '4-5'],
                'ledger' => fn () => Schema::hasTable('testimonials')
                    ? $this->fakerAiInsertLedger(
                        \App\Models\Testimonial::class,
                        DB::table('testimonials')->orderBy('id')->limit(10)->pluck('id')->all()
                    )
                    : $this->fakerAiEmptyLedger(),
            ],
            [
                'type' => 'tool-reviews',
                'label' => 'Tool Reviews',
                'target' => ['*'],
                'target_label' => 'All tools',
                'requested' => 150,
                'days' => 38,
                'hour' => 9,
                'status' => 'completed',
                'tokens' => [6_240, 28_400],
                'options' => ['rating' => '3-5'],
                'ledger' => fn () => Schema::hasTable('tool_reviews')
                    ? $this->fakerAiInsertLedger(
                        \App\Models\ToolReview::class,
                        DB::table('tool_reviews')->orderByDesc('id')->limit(150)->pluck('id')->all()
                    )
                    : $this->fakerAiEmptyLedger(),
            ],
            [
                'type' => 'blog-comments',
                'label' => 'Blog Comments',
                'target' => ['*'],
                'target_label' => 'All published posts',
                'requested' => 60,
                'days' => 31,
                'hour' => 14,
                'status' => 'completed',
                'tokens' => [3_110, 12_880],
                'ledger' => fn () => Schema::hasTable('comments')
                    ? $this->fakerAiInsertLedger(
                        \App\Models\Comment::class,
                        DB::table('comments')->orderByDesc('id')->limit(60)->pluck('id')->all()
                    )
                    : $this->fakerAiEmptyLedger(),
            ],
            [
                'type' => 'tool-favorites',
                'label' => 'Tool Favorites',
                'target' => ['*'],
                'target_label' => 'Spread across all tools',
                'requested' => 240,
                'days' => 26,
                'hour' => 16,
                'status' => 'completed',
                // Numeric generator — no model call, so no tokens.
                'tokens' => [0, 0],
                'ledger' => fn () => Schema::hasTable('favorites')
                    ? $this->fakerAiInsertLedger(
                        \App\Models\Favorite::class,
                        DB::table('favorites')->orderByDesc('id')->limit(240)->pluck('id')->all()
                    )
                    : $this->fakerAiEmptyLedger(),
            ],
            [
                'type' => 'tool-usage',
                'label' => 'Tool Usage',
                'target' => ['*'],
                'target_label' => 'Spread across all tools',
                'requested' => 4_000,
                'days' => 21,
                'hour' => 12,
                'status' => 'completed',
                'tokens' => [0, 0],
                // usage_count is the applied total the History row reports; views_count rides
                // along on the same batch, exactly as ToolUsageGenerator records it.
                'ledger' => fn () => $this->fakerAiCounterLedger(
                    \App\Models\AiTool::class,
                    'ai_tools',
                    ['usage_count', 'views_count'],
                    4_000,
                    40
                ),
            ],
            [
                'type' => 'blog-views',
                'label' => 'Blog Views',
                'target' => ['*'],
                'target_label' => 'Spread across all published posts',
                'requested' => 9_000,
                'days' => 17,
                'hour' => 15,
                'status' => 'completed',
                'tokens' => [0, 0],
                'ledger' => fn () => $this->fakerAiCounterLedger(
                    \App\Models\BlogPost::class,
                    'blog_posts',
                    ['views_count'],
                    9_000,
                    20
                ),
            ],
            [
                'type' => 'blog-shares',
                'label' => 'Blog Share Counts',
                'target' => ['*'],
                'target_label' => 'Spread across all published posts',
                'requested' => 600,
                'days' => 13,
                'hour' => 10,
                'status' => 'completed',
                'tokens' => [0, 0],
                'ledger' => fn () => $this->fakerAiCounterLedger(
                    \App\Models\BlogPost::class,
                    'blog_posts',
                    ['share_count'],
                    600,
                    20
                ),
            ],
            [
                'type' => 'kb-ratings',
                'label' => 'Knowledge Base Helpful Ratings',
                'target' => ['*'],
                'target_label' => 'All published articles',
                'requested' => 300,
                'days' => 8,
                'hour' => 13,
                'status' => 'completed',
                'tokens' => [0, 0],
                'ledger' => fn () => Schema::hasTable('kb_articles')
                    ? $this->fakerAiCounterLedger(
                        \Addons\AiKnowledgeBase\Models\KbArticle::class,
                        'kb_articles',
                        ['helpful_count', 'not_helpful_count'],
                        300,
                        18
                    )
                    : $this->fakerAiEmptyLedger(),
            ],
            [
                // The failure. Rate limits are what actually goes wrong on a long batch, and a
                // History page where every row succeeded tells an evaluator nothing about what
                // happens when one does not.
                'type' => 'tool-reviews',
                'label' => 'Tool Reviews',
                'target' => ['*'],
                'target_label' => 'All tools',
                'requested' => 400,
                'days' => 6,
                'hour' => 17,
                'status' => 'failed',
                'tokens' => [2_040, 6_150],
                'options' => ['rating' => 'random'],
                'error' => 'Provider returned 429 (rate limit) on chunk 4 of 20 and did not recover after 3 retries. The run was abandoned and the rows from the completed chunks were rolled back, so nothing was left behind.',
                'ledger' => fn () => $this->fakerAiEmptyLedger(),
            ],
            [
                'type' => 'testimonials',
                'label' => 'Testimonials',
                'target' => null,
                'target_label' => null,
                'requested' => 2,
                'days' => 2,
                'hour' => 9,
                'status' => 'completed',
                'tokens' => [220, 940],
                'options' => ['rating' => '5'],
                'prompt' => 'Two short ones for the pricing page — agency owners, one sentence each.',
                'ledger' => fn () => Schema::hasTable('testimonials')
                    ? $this->fakerAiInsertLedger(
                        \App\Models\Testimonial::class,
                        DB::table('testimonials')->orderByDesc('id')->limit(2)->pluck('id')->all()
                    )
                    : $this->fakerAiEmptyLedger(),
            ],
        ];
    }

    /**
     * A ledger of row insertions: rolling the batch back deletes exactly these rows.
     *
     * @param  array<int, int|string>  $ids
     */
    private function fakerAiInsertLedger(string $class, array $ids): array
    {
        $deletes = array_map(fn ($id) => [$class, (string) $id], $ids);

        return [
            'inserted' => count($deletes),
            'deletes' => $deletes,
            'decrements' => [],
        ];
    }

    /**
     * A ledger of counter bumps: the run's requested total, spread across the busiest rows
     * the way the real generators distribute one.
     *
     * Two rules keep it honest. Each row is credited at most a share of what it already
     * holds, so rolling the batch back can never drive a figure below what the rest of the
     * demo data justifies — nothing is bumped here, only attributed. And a secondary column
     * (views alongside usage) is credited in the row's own existing ratio rather than a
     * made-up one, so undoing the batch leaves the pair as proportionate as it found them.
     *
     * @param  array<int, string>  $columns  the counters this run touched; the first is the
     *                                       one the History row reports as applied
     */
    private function fakerAiCounterLedger(
        string $class,
        string $table,
        array $columns,
        int $total,
        int $limit
    ): array {
        if (! Schema::hasTable($table)) {
            return $this->fakerAiEmptyLedger();
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return $this->fakerAiEmptyLedger();
            }
        }

        $primary = $columns[0];

        $rows = DB::table($table)
            ->where($primary, '>', 0)
            ->orderByDesc($primary)
            ->limit($limit)
            ->get(array_merge(['id'], $columns));

        if ($rows->isEmpty()) {
            return $this->fakerAiEmptyLedger();
        }

        $perRow = max(1, intdiv($total, $rows->count()));
        $decrements = [];
        $applied = 0;

        foreach ($rows as $row) {
            $current = (int) $row->{$primary};

            // Never claim more than 60% of a counter: the demo's own history has to survive
            // the undo with its shape intact.
            $amount = min($perRow, (int) floor($current * 0.6));

            if ($amount < 1) {
                continue;
            }

            $decrements[] = [$class, (string) $row->id, $primary, $amount];
            $applied += $amount;

            foreach (array_slice($columns, 1) as $column) {
                $secondary = (int) $row->{$column};

                if ($secondary < 1) {
                    continue;
                }

                $scaled = min(
                    (int) floor($secondary * 0.6),
                    max(1, (int) round($amount * ($secondary / max(1, $current))))
                );

                if ($scaled < 1) {
                    continue;
                }

                $decrements[] = [$class, (string) $row->id, $column, $scaled];
            }
        }

        return [
            'inserted' => $applied,
            'deletes' => [],
            'decrements' => $decrements,
        ];
    }

    private function fakerAiEmptyLedger(): array
    {
        return ['inserted' => 0, 'deletes' => [], 'decrements' => []];
    }

    /**
     * AI Image Pro demo content: the admin's configuration and analytics on one side, the
     * demo creator's own studio library on the other.
     *
     * Both halves come from the same two tables the live pipeline writes — `aip_jobs` and
     * `aip_assets` — because the admin Overview derives every figure from them rather than
     * from counters of its own. Seeding a job therefore lights up the operation breakdown,
     * the model breakdown, the credit total and the failure list at once, and the creator's
     * Library is simply the subset of those rows that belong to them.
     *
     * The pictures are real files, not paths to nothing: abstract SVG artwork written to the
     * public disk, the same self-contained approach the demo avatars and ad banners take. It
     * cannot 404, carries no licensing baggage in a redistributed product, and scales to any
     * thumbnail size without a second file.
     */
    private function seedImageProShowcase(?User $showcaseUser, array $demoUsers): void
    {
        if (! function_exists('is_addon_active') || ! is_addon_active('ai-image-pro')) {
            return;
        }

        if (! class_exists(AipAsset::class) || ! Schema::hasTable('aip_assets')) {
            return;
        }

        $artwork = $this->seedImageProArtwork();

        // The Studio's model picker reads `ai_models` where type = image AND is_active.
        // Every image row in this catalog can end up switched off (they arrive that way on
        // installs where the media providers were never set up), which leaves the suite with
        // an empty picker and no default — the addon looks broken rather than unconfigured.
        // Activating them is what an operator standing up an image demo would do; actually
        // generating still needs the provider keys, which a seeder has no business inventing.
        if (Schema::hasTable('ai_models')) {
            DB::table('ai_models')->where('type', 'image')->update(['is_active' => true]);
        }

        $this->seedImageProSettings($artwork);
        $this->seedImageProPresets($artwork);

        if ($showcaseUser) {
            $this->seedImageProLibrary($showcaseUser, $artwork);
        }

        $this->seedImageProPlatformActivity($demoUsers, $artwork);
    }

    // ─── Artwork ────────────────────────────────────────────────────────

    /**
     * Write the demo's images and return their storage paths keyed by name.
     *
     * Abstract rather than photographic on purpose. A grey "1024×1024" placeholder box makes
     * an image product look broken; an abstract composition reads as generated art, which is
     * what these rows claim to be, without pretending to be a photograph of something real.
     *
     * @return array<string, string>
     */
    private function seedImageProArtwork(): array
    {
        $pieces = [
            // key                  motif       palette
            'aurora-portrait' => ['bloom',   ['#312e81', '#7c3aed', '#f472b6']],
            'studio-ceramic' => ['studio',  ['#f8fafc', '#cbd5e1', '#94a3b8']],
            'summer-sale' => ['bloom',   ['#f97316', '#fb7185', '#fde047']],
            'coffee-mark' => ['mark',    ['#1c1917', '#78350f', '#d6d3d1']],
            'floating-city' => ['scene',   ['#0c4a6e', '#0ea5e9', '#fbbf24']],
            'nordic-interior' => ['studio',  ['#fefce8', '#e7e5e4', '#a8a29e']],
            'forest-dawn' => ['scene',   ['#064e3b', '#10b981', '#fef3c7']],
            'neon-alley' => ['scene',   ['#18181b', '#a855f7', '#22d3ee']],
            'ceramic-hero' => ['studio',  ['#ecfeff', '#a5f3fc', '#0e7490']],
            'brand-gradient' => ['bloom',   ['#1e1b4b', '#4338ca', '#22d3ee']],
            'desert-road' => ['scene',   ['#7c2d12', '#f59e0b', '#fed7aa']],
            'paper-texture' => ['studio',  ['#fafaf9', '#e7e5e4', '#d6d3d1']],
            'ink-botanical' => ['mark',    ['#0f172a', '#334155', '#e2e8f0']],
            'ocean-poster' => ['bloom',   ['#082f49', '#0284c7', '#67e8f9']],
        ];

        $paths = [];

        foreach ($pieces as $key => [$motif, $palette]) {
            $path = "ai-image-pro/demo/{$key}.svg";
            Storage::disk('public')->put($path, $this->imageProSvg($key, $motif, $palette));
            $paths[$key] = $path;
        }

        return $paths;
    }

    /**
     * One piece of artwork. Deterministic from the key, so re-seeding rewrites the same
     * image rather than reshuffling the whole gallery.
     *
     * @param  array<int, string>  $palette  background, mid, accent
     */
    private function imageProSvg(string $key, string $motif, array $palette): string
    {
        $seed = abs(crc32($key));
        $id = substr(md5($key), 0, 6);
        [$dark, $mid, $accent] = $palette;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">'
            . '<defs>'
            . '<linearGradient id="bg' . $id . '" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="' . $dark . '"/><stop offset="1" stop-color="' . $mid . '"/>'
            . '</linearGradient>'
            . '<radialGradient id="gl' . $id . '" cx="0.5" cy="0.5" r="0.5">'
            . '<stop offset="0" stop-color="' . $accent . '" stop-opacity="0.85"/>'
            . '<stop offset="1" stop-color="' . $accent . '" stop-opacity="0"/>'
            . '</radialGradient>'
            . '</defs>'
            . '<rect width="1024" height="1024" fill="url(#bg' . $id . ')"/>';

        if ($motif === 'scene') {
            // Horizon, sun, layered hills.
            $sunY = 320 + ($seed % 120);
            $svg .= '<circle cx="' . (300 + ($seed % 420)) . '" cy="' . $sunY . '" r="140" fill="url(#gl' . $id . ')"/>'
                . '<circle cx="' . (300 + ($seed % 420)) . '" cy="' . $sunY . '" r="62" fill="' . $accent . '" opacity="0.9"/>'
                . '<path d="M0 700 C 200 ' . (600 + ($seed % 80)) . ', 380 ' . (760 - ($seed % 60)) . ', 620 690 S 900 640, 1024 700 L1024 1024 L0 1024 Z" fill="' . $dark . '" opacity="0.55"/>'
                . '<path d="M0 820 C 260 ' . (750 + ($seed % 60)) . ', 520 880, 780 810 S 960 790, 1024 830 L1024 1024 L0 1024 Z" fill="' . $dark . '" opacity="0.8"/>';
        } elseif ($motif === 'studio') {
            // Soft product-photography backdrop with a centred subject and its shadow.
            $svg .= '<rect width="1024" height="560" fill="' . $accent . '" opacity="0.10"/>'
                . '<ellipse cx="512" cy="742" rx="300" ry="46" fill="' . $accent . '" opacity="0.28"/>'
                . '<rect x="392" y="386" width="240" height="330" rx="120" fill="' . $accent . '" opacity="0.55"/>'
                . '<rect x="452" y="330" width="120" height="90" rx="46" fill="' . $mid . '" opacity="0.9"/>'
                . '<circle cx="' . (200 + ($seed % 90)) . '" cy="' . (190 + ($seed % 70)) . '" r="150" fill="url(#gl' . $id . ')"/>';
        } elseif ($motif === 'mark') {
            // A logo-like geometric mark on a flat field.
            $svg .= '<circle cx="512" cy="512" r="300" fill="none" stroke="' . $accent . '" stroke-width="18" opacity="0.65"/>'
                . '<circle cx="512" cy="512" r="186" fill="' . $accent . '" opacity="0.22"/>'
                . '<path d="M392 592 L512 372 L632 592 Z" fill="' . $accent . '" opacity="0.85"/>'
                . '<rect x="452" y="600" width="120" height="18" rx="9" fill="' . $accent . '" opacity="0.7"/>';
        } else {
            // 'bloom' — overlapping translucent colour fields.
            for ($i = 0; $i < 5; $i++) {
                $r = 180 + (($seed >> ($i * 3)) % 220);
                $cx = 120 + (($seed >> ($i * 2)) % 800);
                $cy = 120 + (($seed >> ($i + 4)) % 800);
                $fill = $i % 2 === 0 ? $accent : $mid;
                $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $fill . '" opacity="0.30"/>';
            }
            $svg .= '<circle cx="512" cy="512" r="420" fill="url(#gl' . $id . ')"/>';
        }

        return $svg . '</svg>';
    }

    // ─── Admin configuration ────────────────────────────────────────────

    /**
     * Configure the suite the way a buyer should first meet it.
     *
     * Provider API keys are pointedly absent: they are `encrypted` settings holding real
     * credentials, and a seeder has none to give. The landing page's own copy already ships
     * complete defaults (LandingContentService), so only its empty image slots are filled in
     * here — a features section of text beside blank frames is the one part of that page
     * that looks unfinished out of the box.
     *
     * @param  array<string, string>  $artwork
     */
    private function seedImageProSettings(array $artwork): array
    {
        $url = fn (string $key) => Storage::disk('public')->url($artwork[$key]);

        // Pin a model the catalog actually ships. AiModelSeeder's image tier is small, so
        // prefer the flagship and fall back to whatever is active rather than guessing.
        $defaultModel = Schema::hasTable('ai_models')
            ? (DB::table('ai_models')->where('slug', 'gpt-image-2')->where('is_active', true)->value('slug')
                ?? DB::table('ai_models')->where('type', 'image')->where('is_active', true)->orderBy('id')->value('slug'))
            : null;

        // Per-plan daily caps, read off the live plans table so renamed tiers still line up.
        $planLimits = [];
        if (Schema::hasTable('plans')) {
            foreach (DB::table('plans')->orderBy('price_monthly')->get(['slug', 'is_free']) as $index => $plan) {
                // Free is capped tightly, the entry tier generously, and the upper tiers not
                // at all — credits are the limiter there.
                $planLimits[$plan->slug] = $plan->is_free ? 15 : ($index === 1 ? 120 : 0);
            }
        }

        $settings = [
            ['enabled', true, 'boolean'],
            // Guests may open the Studio and spend the free daily allowance before being
            // asked to sign up — that funnel is the addon's main selling point, and a demo
            // that hides it behind a login shows nothing.
            ['studio_access', 'guest', 'string'],
            ['library_access', 'login', 'string'],

            ['default_model', $defaultModel, 'string'],
            ['allow_user_model_choice', true, 'boolean'],
            ['max_batch_size', 4, 'integer'],
            ['enable_prompt_enhancer', true, 'boolean'],
            ['credits_prompt_enhancer', 1, 'integer'],
            ['enable_negative_prompt', true, 'boolean'],
            ['enable_seed', true, 'boolean'],

            ['max_input_size_mb', 12, 'integer'],
            ['max_input_dimension', 8000, 'integer'],
            ['max_output_dimension', 4096, 'integer'],
            ['guest_daily_limit', 5, 'integer'],
            ['user_daily_limit', 0, 'integer'],
            ['plan_daily_limits', $planLimits, 'json'],

            ['max_storage_mb_per_user', 0, 'integer'],
            // Guest work is swept nightly, free accounts keep a month, paid keeps forever —
            // which is also the difference the retention settings exist to sell.
            ['retention_days_guest', 1, 'integer'],
            ['retention_days_free', 30, 'integer'],
            ['retention_days_paid', 0, 'integer'],
            ['auto_save_to_library', true, 'boolean'],
            ['mirror_to_documents', false, 'boolean'],
            ['thumbnail_width', 512, 'integer'],

            // Switched on so the setting is visibly doing something; paid accounts are
            // exempt, which is the point of it.
            ['watermark_enabled', true, 'boolean'],
            ['watermark_text', (string) settings('app_name', 'MakeAI'), 'string'],
            ['watermark_position', 'bottom-right', 'string'],
            ['watermark_opacity', 55, 'integer'],

            // Only the image slots. Copy stays with the shipped defaults.
            ['landing_examples', [
                ['title' => 'Photorealistic portrait', 'description' => 'A lifelike portrait with natural light and shallow depth of field.', 'image' => $url('aurora-portrait'), 'prompt' => 'A photorealistic portrait of a golden retriever wearing sunglasses on a sunny beach, shallow depth of field, warm afternoon light'],
                ['title' => 'Product shot', 'description' => 'Clean studio product photography on a seamless background.', 'image' => $url('studio-ceramic'), 'prompt' => 'Studio product photograph of a minimalist ceramic coffee cup on a seamless light grey background, soft shadows, commercial lighting'],
                ['title' => 'Social media ad', 'description' => 'A vibrant, scroll-stopping creative for Instagram or Facebook.', 'image' => $url('summer-sale'), 'prompt' => 'A vibrant summer sale social media ad for a fashion brand, bold typography space, tropical colours, high contrast'],
                ['title' => 'Brand logo concept', 'description' => 'A modern, minimal mark for a specialty brand.', 'image' => $url('coffee-mark'), 'prompt' => 'A modern minimalist logo concept for a specialty coffee brand, geometric, single colour, clean vector look'],
                ['title' => 'Digital illustration', 'description' => 'Stylised artwork with rich colour and painterly texture.', 'image' => $url('floating-city'), 'prompt' => 'A whimsical digital illustration of a floating island city at sunset, painterly texture, rich colour palette'],
                ['title' => 'Interior visualisation', 'description' => 'An architectural interior with realistic materials and light.', 'image' => $url('nordic-interior'), 'prompt' => 'A bright Scandinavian living room interior, natural oak floors, large windows, soft morning light, architectural photography'],
            ], 'json'],
            ['landing_features', [
                ['title' => 'Text to image in seconds', 'body' => 'Describe what you want in plain language and get a finished image back. No prompt engineering degree required — write it the way you would say it.', 'image' => $url('brand-gradient'), 'cta_text' => 'Open the Studio', 'cta_link' => '/ai-image', 'cta_icon' => 'ti ti-arrow-right'],
                ['title' => 'Professional-grade results', 'body' => 'High-resolution output suitable for print, web and social. Every image is yours to use, and the upscaler takes it further when you need more detail.', 'image' => $url('ceramic-hero'), 'cta_text' => '', 'cta_link' => '', 'cta_icon' => ''],
                ['title' => 'A full editing suite, built in', 'body' => 'Cut out the background, erase an object, expand the canvas, restyle it, or just crop and compress it. You never have to leave the page or open another tool.', 'image' => $url('desert-road'), 'cta_text' => '', 'cta_link' => '', 'cta_icon' => ''],
                ['title' => 'Free tools, no account needed', 'body' => 'Resize, crop, convert and compress run right in the browser session at no cost. Try the generator free too — sign up only when you want to keep your work.', 'image' => $url('paper-texture'), 'cta_text' => '', 'cta_link' => '', 'cta_icon' => ''],
            ], 'json'],
            ['landing_steps', [
                ['title' => 'Describe your image', 'body' => 'Write a prompt in plain language. Add a style, an aspect ratio and a reference image if you have one.', 'image' => $url('ink-botanical')],
                ['title' => 'Generate', 'body' => 'Pick a model and hit generate. Get your image back in seconds — not quite right? Adjust the prompt and go again.', 'image' => $url('ocean-poster')],
                ['title' => 'Refine and download', 'body' => 'Upscale it, remove the background, crop or compress it, then download. Everything stays on the same page.', 'image' => $url('neon-alley')],
            ], 'json'],
        ];

        foreach ($settings as [$key, $value, $type]) {
            if ($value === null) {
                continue;
            }

            AddonSetting::set('ai-image-pro', $key, $value, $type);
        }

        return ['default_model' => $defaultModel];
    }

    /**
     * The Studio's style picker.
     *
     * The addon's own ImageProSeeder already ships seven styles with well-written prompt
     * suffixes — what it cannot ship is a thumbnail for each, so the picker renders as a
     * column of blank tiles. This gives the shipped styles their preview image (touching
     * nothing else about them) and adds four that fill genuine gaps in the set, rather than
     * publishing a near-duplicate beside each one.
     *
     * @param  array<string, string>  $artwork
     */
    private function seedImageProPresets(array $artwork): void
    {
        if (! class_exists(AipPreset::class) || ! Schema::hasTable('aip_presets')) {
            return;
        }

        // Thumbnails only — the shipped prompt text is the addon's to own.
        $thumbs = [
            'photorealistic' => 'aurora-portrait',
            'product-shot' => 'studio-ceramic',
            'anime' => 'floating-city',
            'watercolor' => 'paper-texture',
            'cyberpunk-neon' => 'neon-alley',
            'oil-painting' => 'desert-road',
            'minimal' => 'ink-botanical',
        ];

        foreach ($thumbs as $slug => $art) {
            AipPreset::where('slug', $slug)
                ->whereNull('thumb_path')
                ->update(['thumb_path' => $artwork[$art] ?? null]);
        }

        // Styles the shipped set does not cover.
        $additions = [
            ['cinematic', 'Cinematic', ', cinematic still, anamorphic lens, dramatic key light, film grain, muted teal and amber grade', 'flat lighting, snapshot, low contrast', 'neon-alley', 20],
            ['illustration', 'Illustration', ', digital illustration, painterly brushwork, rich colour palette, expressive shapes', 'photograph, realistic skin texture', 'floating-city', 21],
            ['3d-render', '3D Render', ', octane render, soft global illumination, subsurface scattering, physically based materials, 8k', 'flat 2d, line art, sketch', 'ceramic-hero', 22],
            ['line-art', 'Line Art', ', clean vector line art, single weight strokes, generous negative space, monochrome', 'gradient, photographic texture, noise', 'ink-botanical', 23],
        ];

        foreach ($additions as [$slug, $name, $suffix, $negative, $art, $sort]) {
            $preset = AipPreset::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'prompt_suffix' => $suffix,
                    'negative_prompt' => $negative,
                    'thumb_path' => $artwork[$art] ?? null,
                    'sort' => $sort,
                    'is_active' => true,
                ]
            );

            $this->backdate($preset, now()->subDays(60 - $sort));
        }
    }

    // ─── The demo creator's studio ──────────────────────────────────────

    /**
     * The showcase account's own Library: folders, a gallery with real prompts behind it,
     * and the job rows that produced each image.
     *
     * Lineage is the part worth seeding carefully. An image that was generated, then
     * upscaled, then had its background removed is three assets and three jobs joined by
     * `parent_id`, and the Library shows that chain — a flat pile of unrelated images would
     * hide the half of the product that operates on existing work.
     *
     * @param  array<string, string>  $artwork
     */
    private function seedImageProLibrary(User $user, array $artwork): void
    {
        $this->resetImageProLibrary($user);

        $folders = [];
        $folderRows = [
            ['Client — Northwind', '#0ea5e9', 1, 34],
            ['Product shots', '#f59e0b', 2, 21],
            ['Blog headers', '#6366f1', 3, 12],
        ];

        foreach ($folderRows as [$name, $color, $sort, $daysAgo]) {
            $folder = AipFolder::create([
                'user_id' => $user->id,
                'name' => $name,
                'color' => $color,
                'sort' => $sort,
            ]);

            $this->backdate($folder, now()->subDays($daysAgo)->setTime(10, 20));
            $folders[$name] = $folder;
        }

        /** @var array<string, AipAsset> $created */
        $created = [];

        foreach ($this->imageProLibraryPlan() as $row) {
            $at = now()->subDays($row['days'])->setTime($row['hour'], ($row['days'] * 13) % 60);

            if ($at->isFuture()) {
                $at = now()->subMinutes(mt_rand(15, 180));
            }

            $parent = isset($row['parent']) ? ($created[$row['parent']] ?? null) : null;

            // Uploads never have a job behind them — the user brought the file. Everything
            // else is the output of one, and the job is what the credits hang off.
            $job = null;

            if ($row['source'] !== 'uploaded') {
                $job = AipJob::create([
                    'user_id' => $user->id,
                    'operation' => $row['operation'],
                    'tier' => $row['tier'],
                    'status' => AipJob::STATUS_COMPLETED,
                    'engine' => $row['engine'],
                    'model' => $row['model'] ?? null,
                    'input_asset_id' => $parent?->id,
                    'params' => $row['params'] ?? null,
                    'batch_size' => $row['batch'] ?? 1,
                    'credits_charged' => $row['credits'],
                    'billing_mode' => $row['billing'],
                    'started_at' => $at,
                    'completed_at' => $at->copy()->addSeconds($row['seconds'] ?? mt_rand(4, 40)),
                ]);

                $this->backdate($job, $at, $job->completed_at);
            }

            $path = $artwork[$row['art']];

            $asset = AipAsset::create([
                'user_id' => $user->id,
                'folder_id' => $folders[$row['folder'] ?? '']->id ?? null,
                'job_id' => $job?->id,
                'parent_id' => $parent?->id,
                'source' => $row['source'],
                'operation' => $row['source'] === 'uploaded' ? null : $row['operation'],
                'disk' => 'public',
                'path' => $path,
                // The artwork is vector, so one file serves as its own thumbnail — there is
                // no smaller version to make.
                'thumb_path' => $path,
                'mime' => 'image/svg+xml',
                'width' => $row['width'] ?? 1024,
                'height' => $row['height'] ?? 1024,
                'bytes' => Storage::disk('public')->size($path),
                'prompt' => $row['prompt'] ?? null,
                'negative_prompt' => $row['negative'] ?? null,
                'model' => $row['model'] ?? null,
                'provider' => $row['engine'] === 'gd' ? null : $row['engine'],
                'seed' => $row['seed'] ?? null,
                'params' => $row['params'] ?? null,
                'is_favorite' => $row['favorite'] ?? false,
            ]);

            $this->backdate($asset, $at, $at);
            $created[$row['key']] = $asset;
        }

        // One failure, kept out of the asset list because it never produced an image: a
        // provider timeout that was refunded. Without it the credit ledger tells only the
        // half of the story where everything works.
        $failedAt = now()->subDays(4)->setTime(16, 35);

        $failed = AipJob::create([
            'user_id' => $user->id,
            'operation' => 'style_transfer',
            'tier' => 'provider',
            'status' => AipJob::STATUS_FAILED,
            'engine' => 'replicate',
            'input_asset_id' => $created['hero-upscaled']->id ?? null,
            'params' => ['style' => 'watercolour', 'strength' => 0.65],
            'credits_charged' => 20,
            'billing_mode' => 'flat',
            'refunded' => true,
            'provider_job_id' => 'r8-' . Str::lower(Str::random(12)),
            'error_message' => 'Provider timed out after 120s without returning an image. The 20 credits reserved for this run were refunded automatically.',
            'started_at' => $failedAt,
            'completed_at' => $failedAt->copy()->addSeconds(121),
        ]);

        $this->backdate($failed, $failedAt, $failedAt->copy()->addSeconds(121));
    }

    /**
     * Clear the showcase account's studio, files included, so a reset rebuilds it rather
     * than stacking a second gallery on top of the first.
     */
    private function resetImageProLibrary(User $user): void
    {
        // Force-delete past the soft delete: a soft-deleted asset still counts toward the
        // storage meter and would accumulate silently across resets.
        AipAsset::withTrashed()->where('user_id', $user->id)->forceDelete();
        AipJob::where('user_id', $user->id)->delete();

        if (Schema::hasTable('aip_folders')) {
            AipFolder::where('user_id', $user->id)->delete();
        }
    }

    /**
     * The creator's gallery, oldest first so a derived image is always written after the
     * one it came from.
     */
    private function imageProLibraryPlan(): array
    {
        return [
            [
                'key' => 'ceramic-hero',
                'art' => 'ceramic-hero',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'openai',
                'model' => 'gpt-image-2',
                'billing' => 'media',
                'credits' => 24,
                'days' => 33,
                'hour' => 10,
                'folder' => 'Product shots',
                'prompt' => 'Studio product photograph of a matte ceramic pour-over coffee set on a seamless pale backdrop, soft boxed light from the left, gentle reflection, commercial catalogue style',
                'negative' => 'clutter, text, watermark, harsh shadow',
                'seed' => '918442071',
                'params' => ['aspect_ratio' => '1:1', 'preset' => 'product-shot', 'quality' => 'high'],
                'batch' => 4,
                'favorite' => true,
            ],
            [
                'key' => 'hero-upscaled',
                'art' => 'ceramic-hero',
                'source' => 'derived',
                'operation' => 'upscale',
                'tier' => 'provider',
                'engine' => 'replicate',
                'billing' => 'flat',
                'credits' => 20,
                'days' => 33,
                'hour' => 11,
                'folder' => 'Product shots',
                'parent' => 'ceramic-hero',
                'width' => 4096,
                'height' => 4096,
                'params' => ['scale' => 4, 'face_enhance' => false],
                'seconds' => 38,
                'favorite' => true,
            ],
            [
                'key' => 'hero-cutout',
                'art' => 'studio-ceramic',
                'source' => 'derived',
                'operation' => 'bg_remove',
                'tier' => 'provider',
                'engine' => 'remove_bg',
                'billing' => 'flat',
                'credits' => 5,
                'days' => 32,
                'hour' => 9,
                'folder' => 'Product shots',
                'parent' => 'hero-upscaled',
                'width' => 4096,
                'height' => 4096,
                'params' => ['format' => 'png', 'keep_shadow' => false],
            ],
            [
                'key' => 'northwind-brand',
                'art' => 'brand-gradient',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'replicate',
                'model' => 'flux-1.1-pro',
                'billing' => 'media',
                'credits' => 18,
                'days' => 27,
                'hour' => 14,
                'folder' => 'Client — Northwind',
                'prompt' => 'Abstract brand background for a logistics SaaS, deep indigo to cyan gradient, subtle geometric grid, generous empty space on the right for a headline',
                'negative' => 'text, logo, letters, faces',
                'seed' => '440217755',
                'params' => ['aspect_ratio' => '16:9', 'preset' => 'cyberpunk-neon'],
                'width' => 1344,
                'height' => 768,
                'favorite' => true,
            ],
            [
                'key' => 'northwind-expanded',
                'art' => 'ocean-poster',
                'source' => 'derived',
                'operation' => 'outpaint',
                'tier' => 'provider',
                'engine' => 'stability',
                'billing' => 'flat',
                'credits' => 15,
                'days' => 27,
                'hour' => 15,
                'folder' => 'Client — Northwind',
                'parent' => 'northwind-brand',
                'width' => 1792,
                'height' => 768,
                'params' => ['direction' => 'left,right', 'pixels' => 224],
            ],
            [
                'key' => 'northwind-mark',
                'art' => 'coffee-mark',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'ideogram',
                'model' => 'ideogram-v2',
                'billing' => 'media',
                'credits' => 14,
                'days' => 24,
                'hour' => 11,
                'folder' => 'Client — Northwind',
                'prompt' => 'A minimal geometric monogram mark for a logistics company, single weight strokes, balanced negative space, flat monochrome, vector look',
                'seed' => '77310244',
                'params' => ['aspect_ratio' => '1:1', 'preset' => 'line-art'],
                'batch' => 4,
            ],
            [
                'key' => 'blog-forest',
                'art' => 'forest-dawn',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'google',
                'model' => 'gemini-3.1-flash-image-preview',
                'billing' => 'media',
                'credits' => 9,
                'days' => 19,
                'hour' => 8,
                'folder' => 'Blog headers',
                'prompt' => 'Wide blog header of mist over a pine forest at dawn, cool green palette, generous sky for a headline overlay, editorial photography',
                'seed' => '605128390',
                'params' => ['aspect_ratio' => '16:9'],
                'width' => 1344,
                'height' => 768,
            ],
            [
                'key' => 'blog-forest-resized',
                'art' => 'forest-dawn',
                'source' => 'derived',
                'operation' => 'resize',
                'tier' => 'local',
                'engine' => 'gd',
                'billing' => 'free',
                'credits' => 0,
                'days' => 19,
                'hour' => 9,
                'folder' => 'Blog headers',
                'parent' => 'blog-forest',
                'width' => 1200,
                'height' => 686,
                'params' => ['width' => 1200, 'keep_ratio' => true],
                'seconds' => 2,
            ],
            [
                'key' => 'desert-scene',
                'art' => 'desert-road',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'stability',
                'model' => 'stable-diffusion-3.5',
                'billing' => 'media',
                'credits' => 11,
                'days' => 15,
                'hour' => 16,
                'prompt' => 'An empty desert highway at golden hour, long shadows, heat haze on the horizon, cinematic anamorphic framing',
                'negative' => 'people, cars, signage',
                'seed' => '129945002',
                'params' => ['aspect_ratio' => '21:9', 'preset' => 'cinematic'],
                'width' => 1536,
                'height' => 640,
            ],
            [
                'key' => 'desert-cleaned',
                'art' => 'desert-road',
                'source' => 'derived',
                'operation' => 'object_remove',
                'tier' => 'provider',
                'engine' => 'clipdrop',
                'billing' => 'flat',
                'credits' => 15,
                'days' => 15,
                'hour' => 17,
                'parent' => 'desert-scene',
                'width' => 1536,
                'height' => 640,
                'params' => ['brush_size' => 42],
            ],
            [
                'key' => 'client-upload-shelf',
                'art' => 'paper-texture',
                'source' => 'uploaded',
                'operation' => 'upload',
                'tier' => 'local',
                'engine' => 'gd',
                'billing' => 'free',
                'credits' => 0,
                'days' => 11,
                'hour' => 10,
                'folder' => 'Client — Northwind',
                'width' => 2048,
                'height' => 1365,
            ],
            [
                'key' => 'client-upload-cutout',
                'art' => 'studio-ceramic',
                'source' => 'derived',
                'operation' => 'bg_remove',
                'tier' => 'provider',
                'engine' => 'remove_bg',
                'billing' => 'flat',
                'credits' => 5,
                'days' => 11,
                'hour' => 11,
                'folder' => 'Client — Northwind',
                'parent' => 'client-upload-shelf',
                'width' => 2048,
                'height' => 1365,
                'params' => ['format' => 'png'],
            ],
            [
                'key' => 'social-summer',
                'art' => 'summer-sale',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'openai',
                'model' => 'gpt-image-2',
                'billing' => 'media',
                'credits' => 24,
                'days' => 8,
                'hour' => 13,
                'prompt' => 'A bright summer sale creative for a fashion brand, tropical colour blocking, bold empty band across the middle for typography, high contrast',
                'seed' => '318870066',
                'params' => ['aspect_ratio' => '4:5', 'preset' => 'illustration'],
                'width' => 1024,
                'height' => 1280,
                'batch' => 2,
                'favorite' => true,
            ],
            [
                'key' => 'social-variation',
                'art' => 'ocean-poster',
                'source' => 'derived',
                'operation' => 'variations',
                'tier' => 'generate',
                'engine' => 'openai',
                'model' => 'gpt-image-2',
                'billing' => 'media',
                'credits' => 24,
                'days' => 8,
                'hour' => 14,
                'parent' => 'social-summer',
                'prompt' => 'A bright summer sale creative for a fashion brand, tropical colour blocking, bold empty band across the middle for typography, high contrast',
                'seed' => '318870067',
                'params' => ['aspect_ratio' => '4:5', 'variation_strength' => 0.4],
                'width' => 1024,
                'height' => 1280,
                'batch' => 2,
            ],
            [
                'key' => 'illustration-city',
                'art' => 'floating-city',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'replicate',
                'model' => 'flux-1.1-pro',
                'billing' => 'media',
                'credits' => 18,
                'days' => 5,
                'hour' => 15,
                'folder' => 'Blog headers',
                'prompt' => 'A whimsical illustration of a floating island city at sunset, painterly texture, warm rich palette, small figures for scale',
                'seed' => '812446190',
                'params' => ['aspect_ratio' => '3:2', 'preset' => 'illustration'],
                'width' => 1216,
                'height' => 832,
                'favorite' => true,
            ],
            [
                'key' => 'illustration-restyled',
                'art' => 'ink-botanical',
                'source' => 'derived',
                'operation' => 'style_transfer',
                'tier' => 'provider',
                'engine' => 'fal',
                'billing' => 'flat',
                'credits' => 20,
                'days' => 5,
                'hour' => 16,
                'folder' => 'Blog headers',
                'parent' => 'illustration-city',
                'width' => 1216,
                'height' => 832,
                'params' => ['style' => 'ink-wash', 'strength' => 0.7],
            ],
            [
                'key' => 'interior-shot',
                'art' => 'nordic-interior',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'google',
                'model' => 'gemini-3.1-flash-image-preview',
                'billing' => 'media',
                'credits' => 9,
                'days' => 2,
                'hour' => 11,
                'prompt' => 'A bright Scandinavian living room, natural oak floors, tall windows, soft morning light, architectural photography, no people',
                'negative' => 'people, clutter, text',
                'seed' => '229104773',
                'params' => ['aspect_ratio' => '3:2'],
                'width' => 1216,
                'height' => 832,
            ],
            [
                'key' => 'interior-compressed',
                'art' => 'nordic-interior',
                'source' => 'derived',
                'operation' => 'compress',
                'tier' => 'local',
                'engine' => 'gd',
                'billing' => 'free',
                'credits' => 0,
                'days' => 2,
                'hour' => 12,
                'parent' => 'interior-shot',
                'width' => 1216,
                'height' => 832,
                'params' => ['quality' => 72],
                'seconds' => 1,
            ],
            [
                'key' => 'neon-poster',
                'art' => 'neon-alley',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'stability',
                'model' => 'stable-diffusion-3.5',
                'billing' => 'media',
                'credits' => 11,
                'days' => 0,
                'hour' => 9,
                'prompt' => 'A rain-slick city alley at night lit by neon signage, deep purple and cyan, reflections on the ground, cinematic',
                'seed' => '556130918',
                'params' => ['aspect_ratio' => '2:3', 'preset' => 'cyberpunk-neon'],
                'width' => 832,
                'height' => 1216,
            ],
            [
                'key' => 'aurora-portrait',
                'art' => 'aurora-portrait',
                'source' => 'generated',
                'operation' => 'generate',
                'tier' => 'generate',
                'engine' => 'openai',
                'model' => 'gpt-image-2',
                'billing' => 'media',
                'credits' => 24,
                'days' => 0,
                'hour' => 10,
                'prompt' => 'A portrait lit by aurora-coloured rim light against a dark studio background, shallow depth of field, editorial fashion photography',
                'negative' => 'extra fingers, distorted face, watermark',
                'seed' => '744029615',
                'params' => ['aspect_ratio' => '1:1', 'preset' => 'photorealistic'],
                'batch' => 2,
                'favorite' => true,
            ],
        ];
    }

    // ─── Platform-wide activity (the admin Overview) ────────────────────

    /**
     * Thirty days of image jobs from the wider user base, which is what the admin Overview
     * reports on.
     *
     * Its four cards compare a window against the equally-long window before it, so volume
     * ramps toward today; the operation and model breakdowns need a weighted spread rather
     * than a uniform one to rank anything; and the failure panel needs failures with
     * messages worth reading. Completed jobs get an asset so the "images" card is not
     * mysteriously lower than the job count.
     *
     * @param  array<string, string>  $artwork
     */
    private function seedImageProPlatformActivity(array $demoUsers, array $artwork): void
    {
        $users = array_values(array_filter($demoUsers));

        if ($users === []) {
            return;
        }

        $userIds = array_map(fn (User $user) => $user->id, $users);

        AipAsset::withTrashed()->whereIn('user_id', $userIds)->forceDelete();
        AipJob::whereIn('user_id', $userIds)->delete();

        // operation, tier, engine, model, billing, credits, weight
        $recipes = [
            ['generate', 'generate', 'openai', 'gpt-image-2', 'media', 24, 10],
            ['generate', 'generate', 'replicate', 'flux-1.1-pro', 'media', 18, 8],
            ['generate', 'generate', 'stability', 'stable-diffusion-3.5', 'media', 11, 6],
            ['generate', 'generate', 'google', 'gemini-3.1-flash-image-preview', 'media', 9, 5],
            ['generate', 'generate', 'ideogram', 'ideogram-v2', 'media', 14, 3],
            ['variations', 'generate', 'openai', 'gpt-image-2', 'media', 24, 3],
            ['prompt_edit', 'generate', 'openai', 'gpt-image-2', 'media', 24, 3],
            ['bg_remove', 'provider', 'remove_bg', null, 'flat', 5, 7],
            ['upscale', 'provider', 'replicate', null, 'flat', 20, 6],
            ['bg_replace', 'provider', 'clipdrop', null, 'flat', 15, 3],
            ['inpaint', 'provider', 'stability', null, 'flat', 15, 2],
            ['object_remove', 'provider', 'clipdrop', null, 'flat', 15, 2],
            ['outpaint', 'provider', 'stability', null, 'flat', 15, 2],
            ['style_transfer', 'provider', 'fal', null, 'flat', 20, 2],
            ['resize', 'local', 'gd', null, 'free', 0, 5],
            ['crop', 'local', 'gd', null, 'free', 0, 4],
            ['compress', 'local', 'gd', null, 'free', 0, 4],
            ['convert', 'local', 'gd', null, 'free', 0, 2],
            ['watermark', 'local', 'gd', null, 'free', 0, 2],
        ];

        $pool = [];
        foreach ($recipes as $recipe) {
            $pool = array_merge($pool, array_fill(0, array_pop($recipe), $recipe));
        }

        // Only the paid tiers fail in a way worth reporting: local GD work either succeeds or
        // is rejected before a job row exists.
        $failures = [
            'Provider returned 402 — the Stability account has no credit remaining.',
            'Provider timed out after 120s without returning an image.',
            'Upstream rejected the prompt: flagged by the provider content filter.',
            'Replicate prediction finished with status "failed" and no output.',
            'Downloading the provider result failed: connection reset after 3 attempts.',
        ];

        $artKeys = array_keys($artwork);
        $index = 0;

        // Ninety days, not thirty. The Overview compares a window against the equally-long
        // one before it, so a history that stops at the edge of the 30-day window makes its
        // own first month look like +5000% growth from nothing. The tail is lighter than the
        // head, which reads as an install being adopted rather than one switched on today.
        for ($daysAgo = 89; $daysAgo >= 0; $daysAgo--) {
            $perDay = match (true) {
                $daysAgo <= 6 => mt_rand(7, 12),
                $daysAgo <= 13 => mt_rand(4, 8),
                $daysAgo <= 29 => mt_rand(2, 5),
                $daysAgo <= 59 => mt_rand(2, 4),
                default => mt_rand(1, 2),
            };

            for ($n = 0; $n < $perDay; $n++) {
                [$operation, $tier, $engine, $model, $billing, $credits] = $pool[array_rand($pool)];
                $index++;

                $at = now()->subDays($daysAgo)->setTime(mt_rand(7, 22), mt_rand(0, 59));

                if ($at->isFuture()) {
                    $at = now()->subMinutes(mt_rand(5, 240));
                }

                // Around one paid job in fifteen fails; the credits come straight back.
                $failed = $tier !== 'local' && mt_rand(1, 15) === 1;
                $user = $users[$index % count($users)];

                $job = AipJob::create([
                    'user_id' => $user->id,
                    'operation' => $operation,
                    'tier' => $tier,
                    'status' => $failed ? AipJob::STATUS_FAILED : AipJob::STATUS_COMPLETED,
                    'engine' => $engine,
                    'model' => $model,
                    'batch_size' => $tier === 'generate' ? mt_rand(1, 4) : 1,
                    'credits_charged' => $credits,
                    'billing_mode' => $billing,
                    'refunded' => $failed,
                    'error_message' => $failed ? $failures[array_rand($failures)] : null,
                    'started_at' => $at,
                    'completed_at' => $at->copy()->addSeconds(mt_rand(2, 90)),
                ]);

                $this->backdate($job, $at, $job->completed_at);

                if ($failed) {
                    continue;
                }

                $art = $artwork[$artKeys[$index % count($artKeys)]];

                $asset = AipAsset::create([
                    'user_id' => $user->id,
                    'job_id' => $job->id,
                    'source' => $tier === 'generate' && $operation === 'generate' ? 'generated' : 'derived',
                    'operation' => $operation,
                    'disk' => 'public',
                    'path' => $art,
                    'thumb_path' => $art,
                    'mime' => 'image/svg+xml',
                    'width' => 1024,
                    'height' => 1024,
                    'bytes' => Storage::disk('public')->size($art),
                    'model' => $model,
                    'provider' => $engine === 'gd' ? null : $engine,
                    'is_favorite' => mt_rand(1, 9) === 1,
                ]);

                $this->backdate($asset, $at, $at);
            }
        }
    }

    /**
     * Spend limits for a demo that anyone on the internet can reach.
     *
     * Every AI call on this site is billed to the operator's own provider keys, and the
     * showcase account is SHARED — the credentials are printed on the sign-in page. Without
     * these, one visitor with a loop can spend real money all afternoon.
     *
     * Three independent layers, because each one alone has a hole:
     *
     *   1. A global daily USD budget. The only control a visitor cannot route around by
     *      rotating IPs or signing in, and the last line of defence — TokenGuard refuses
     *      every AI call once the day's spend reaches it.
     *   2. Per-identity credit ceilings: one for anonymous visitors (per IP) and a much
     *      smaller daily allowance on the shared account than a real Professional user gets.
     *   3. A per-minute generation cap (applied per IP in demo mode by ThrottleAiRequests),
     *      so one visitor cannot drain the shared daily allowance in a single burst.
     *
     * Seeded rather than set by hand so a demo reset cannot quietly remove them. Deliberately
     * conservative: a demo has to show the product working, not let anyone finish a day's
     * work on it. Raise them if the demo feels stingy — the numbers are settings, not code.
     */
    /**
     * Per-country prices for the paid plans, so /admin/premium/plans and the public pricing
     * page have regional pricing to show.
     *
     * Country Prices sat empty on every plan, which made a headline feature look unbuilt:
     * the admin tab was a blank table, and PlanPriceResolver fell through to `source =>
     * 'default'` for every visitor, so nothing ever exercised the country branch.
     *
     * Free plans are deliberately skipped — the whole table is prices, and a second row
     * saying a $0 plan costs ₹0 in India documents nothing. That is also what the user
     * asked for.
     *
     * Prices are DERIVED from each plan's own USD figure rather than hardcoded, because the
     * base plans come from PlanSeeder and this seeder's own firstOrCreate only wins on a
     * fresh database; hardcoding would silently describe a plan that costs something else.
     * The multipliers are rough FX × a regional discount — India and Brazil are priced well
     * under a straight conversion, the UK and Germany near parity — which is what real
     * regional pricing looks like and what makes the tab worth looking at.
     */
    /**
     * The three sidebar widget areas on /admin/appearance/sidebar.
     *
     * Unconfigured, normalizeConfig() hands back empty `main` and `blog` areas and three
     * placeholder blocks on `page` — so tool pages and the blog rendered a bare column and
     * the builder itself opened with almost nothing on it. Nine of the eleven widget types
     * are used below; the two left out are ad_zone duplicates and custom_html beyond the one
     * example.
     *
     * Each area targets a different context (see SidebarBuilderRequest::AREAS), so the
     * widgets differ per area rather than being the same list three times: the blog column
     * gets posts, tags and blog categories; the tool column gets tool categories, popular
     * and recently-added tools.
     *
     * Written straight to the setting, so it must already be in the shape
     * SidebarBuilderRequest::sanitizedConfig() produces — id / type / config per block,
     * `type` one of the eleven the request whitelists, and only the config keys it
     * validates (title, placeholder, show_count, count, description, zone_id, content).
     * Anything else is dropped the first time an admin saves the page, which would quietly
     * change the demo.
     */
    private function seedSidebarWidgets(): void
    {
        // ad_zone points at zones this seeder actually creates (section 13's `ads` rows) —
        // AppSidebar falls back to 'sidebar_top' for a missing zone_id, but a zone that does
        // not exist renders nothing at all.
        $config = ['areas' => [
            // AI tool pages.
            'main' => [
                ['id' => 'main-search', 'type' => 'search_box', 'config' => ['title' => 'Search tools', 'placeholder' => 'Search 400+ AI tools...']],
                ['id' => 'main-categories', 'type' => 'categories_list', 'config' => ['title' => 'Tool Categories', 'show_count' => true]],
                ['id' => 'main-popular', 'type' => 'popular_tools', 'config' => ['title' => 'Most Popular', 'count' => 5]],
                ['id' => 'main-recent', 'type' => 'recently_added', 'config' => ['title' => 'Recently Added', 'count' => 4]],
                ['id' => 'main-ad', 'type' => 'ad_zone', 'config' => ['title' => '', 'zone_id' => 'sidebar_top']],
                ['id' => 'main-newsletter', 'type' => 'newsletter', 'config' => ['title' => 'AI tips, monthly', 'description' => 'New tools, prompt techniques and product news. No spam, unsubscribe any time.']],
            ],

            // Blog listing and single posts.
            'blog' => [
                ['id' => 'blog-search', 'type' => 'search_box', 'config' => ['title' => 'Search the blog', 'placeholder' => 'Search articles...']],
                ['id' => 'blog-categories', 'type' => 'blog_categories', 'config' => ['title' => 'Categories', 'show_count' => true]],
                ['id' => 'blog-recent', 'type' => 'recent_posts', 'config' => ['title' => 'Recent Posts', 'count' => 5]],
                ['id' => 'blog-tags', 'type' => 'tag_cloud', 'config' => ['title' => 'Popular Tags']],
                ['id' => 'blog-social', 'type' => 'social_follow', 'config' => ['title' => 'Follow along']],
                ['id' => 'blog-ad', 'type' => 'ad_zone', 'config' => ['title' => '', 'zone_id' => 'sidebar_bottom']],
            ],

            // Custom CMS pages.
            'page' => [
                ['id' => 'page-search', 'type' => 'search_box', 'config' => ['title' => 'Search', 'placeholder' => 'Search articles...']],
                ['id' => 'page-categories', 'type' => 'categories_list', 'config' => ['title' => 'Browse Tools', 'show_count' => true]],
                ['id' => 'page-recent', 'type' => 'recent_posts', 'config' => ['title' => 'From the Blog', 'count' => 3]],
                // Only tags TiptapHtmlSanitizer::BASIC_TAGS keeps, so re-saving the builder
                // leaves this block byte-identical instead of stripping half of it.
                ['id' => 'page-help', 'type' => 'custom_html', 'config' => [
                    'title' => 'Need a hand?',
                    'content' => '<p>Answers to the common questions live in the <a href="/help">Help Center</a>.</p>'
                        . '<p>Still stuck? <a href="/contact">Contact us</a> — we reply within one business day.</p>',
                ]],
                ['id' => 'page-social', 'type' => 'social_follow', 'config' => ['title' => 'Follow us']],
            ],
        ]];

        Setting::setValue('sidebar_config', $config, 'json', 'appearance');
    }

    private function seedPlanCountryPrices(): void
    {
        // factor: USD price → local price. vat: shown per-country (EU sale needs it).
        // trial: overrides the plan's own trial in that market; null leaves it off.
        $regions = [
            ['code' => 'IN', 'currency' => 'INR', 'factor' => 29.0, 'vat' => 18.00, 'trial' => 14],
            ['code' => 'BR', 'currency' => 'BRL', 'factor' => 2.75, 'vat' => 0.00, 'trial' => 7],
            ['code' => 'ZA', 'currency' => 'ZAR', 'factor' => 9.25, 'vat' => 15.00, 'trial' => null],
            ['code' => 'GB', 'currency' => 'GBP', 'factor' => 0.79, 'vat' => 20.00, 'trial' => null],
            ['code' => 'DE', 'currency' => 'EUR', 'factor' => 0.92, 'vat' => 19.00, 'trial' => 7],
        ];

        // Charm pricing, and never a bare 0: a plan with no lifetime price must keep NULL in
        // that column, or the pricing page starts offering a lifetime tier for nothing.
        $localize = function (?float $usd, float $factor): ?float {
            if ($usd === null || $usd <= 0) {
                return null;
            }

            $value = $usd * $factor;

            return max(0.99, round($value) - 0.01);
        };

        $paidPlans = Plan::where('is_free', false)
            ->where(fn ($query) => $query->where('price_monthly', '>', 0)->orWhere('price_yearly', '>', 0))
            ->get();

        foreach ($paidPlans as $plan) {
            foreach ($regions as $region) {
                $monthly = $localize((float) $plan->price_monthly, $region['factor']);
                $yearly = $localize((float) $plan->price_yearly, $region['factor']);
                $lifetime = $localize($plan->price_lifetime ? (float) $plan->price_lifetime : null, $region['factor']);

                // A per-country trial can only be offered on a cycle that has a price.
                $trialDays = $region['trial'];

                PlanCountryPrice::updateOrCreate(
                    ['plan_id' => $plan->id, 'country_code' => $region['code']],
                    [
                        'currency_code' => $region['currency'],
                        // The struck-through "was" figure the pricing page renders next to
                        // the live one. ~35% above, so the discount reads as a real offer.
                        'original_price_monthly' => $monthly ? round($monthly * 1.35) - 0.01 : null,
                        'original_price_yearly' => $yearly ? round($yearly * 1.35) - 0.01 : null,
                        'original_price_lifetime' => $lifetime ? round($lifetime * 1.35) - 0.01 : null,
                        'price_monthly' => $monthly,
                        'price_yearly' => $yearly,
                        'price_lifetime' => $lifetime,
                        'vat_percentage' => $region['vat'],
                        'trial_monthly_enabled' => $trialDays !== null && $monthly !== null,
                        'trial_yearly_enabled' => $trialDays !== null && $yearly !== null,
                        'trial_lifetime_enabled' => false,
                        'trial_monthly_days' => $monthly !== null ? $trialDays : null,
                        'trial_yearly_days' => $yearly !== null ? $trialDays : null,
                        'trial_lifetime_days' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedDemoSpendLimits(?User $showcaseUser): void
    {
        // 1. The hard ceiling. Roughly a few hundred short generations a day across the
        //    whole site; when it trips, the demo pauses AI until midnight.
        Setting::setValue('global_daily_ai_budget_usd', '3.00', 'string', 'ai');

        // 2a. Anonymous visitors, counted per IP by TokenGuard::assertIpCanSpend().
        //     Enough to watch a tool actually produce something before signing up.
        Setting::setValue('guest_daily_credit_limit', '25', 'integer', 'ai');

        // 2c. Signed-in visitors, ALSO per IP. Without this the account's daily allowance
        //     below is a single pool the whole internet shares, and the first visitor of the
        //     day can take all of it. Set above the guest figure — signing in should be
        //     worth something — but well under the account total, so the pool serves a few
        //     dozen people rather than one. Only consulted when demo.enabled.
        Setting::setValue('demo_ip_daily_credit_limit', '40', 'integer', 'ai');

        // 3. Per-IP generation rate, read by ThrottleAiRequests (demo installs only).
        Setting::setValue('demo_generation_rate_limit_per_min', '5', 'integer', 'ai');

        // 2b. The shared account. Its plan says Professional — which is the point of the
        //     demo — but the daily allowance is a site-wide pool split between everyone
        //     signed in as it, not one person's quota, so it is sized accordingly. The
        //     wallet balance is left alone: it is what the dashboard charts report, and the
        //     limits are what actually stop the spend.
        //
        //     HEADROOM, not absolutes. seedShowcaseCreditTimeline() has already set
        //     credits_used_today / credits_used_month from the seeded transaction history
        //     (a couple of thousand credits this month, so the Usage dashboard has something
        //     to show). A flat "monthly_limit = 2000" therefore lands BELOW usage the demo
        //     ships with and locks every visitor out before they click anything. Adding the
        //     allowance on top keeps that history intact and still bounds new spend.
        if ($showcaseUser) {
            $showcaseUser->refresh();

            $showcaseUser->forceFill([
                'daily_limit' => round((float) $showcaseUser->credits_used_today) + 150,
                'monthly_limit' => round((float) $showcaseUser->credits_used_month) + 2000,
            ])->save();
        }

        // 4. Document AI (/admin/ai/rag). The limits above meter generations; RAG spends on
        //    two paths of its own that they never see — embedding a document on upload, and
        //    the context pulled into every question afterwards. At stock settings one
        //    visitor dropping a 25 MB, 300-page PDF embeds the lot, then each question ships
        //    six chunks to the model. Every value below is inside the admin page's own
        //    validation, so opening /admin/ai/rag and saving does not bounce.

        // 4a. Ingest ceiling — the one-off embedding bill, and the biggest single lever.
        //     3 MB / 25 pages still accepts a real report or whitepaper; it refuses the
        //     500-page manual nobody is going to read in a demo anyway.
        Setting::setValue('rag_max_file_mb', '3', 'integer', 'rag');
        Setting::setValue('rag_max_pages', '25', 'integer', 'rag');
        Setting::setValue('rag_max_url_fetch_mb', '2', 'integer', 'rag');

        // 4b. Retrieval width — the RECURRING cost, paid again on every question. Halving
        //     top-K halves the context tokens per answer; at 3 chunks the answers are still
        //     grounded in the document, which is the thing being demonstrated.
        Setting::setValue('rag_top_k', '3', 'integer', 'rag');

        // 4c. Whisper transcription for YouTube sources: off. It is the most expensive path
        //     in the product — a long video is minutes of audio billed per minute — and it
        //     is only a FALLBACK for when the transcript API misses. Off by default already;
        //     pinned here so a reset undoes anyone who flipped it on mid-demo.
        Setting::setValue('rag_youtube_whisper_fallback', '0', 'boolean', 'rag');

        // 4d. Price the two RAG paths so the credit ceilings in 1-3 actually bind them.
        //     Shipped at 0, ingest is free and unbounded: the daily budget notices only once
        //     the provider bill has already been run up. Charging brings uploads and
        //     questions inside the same per-IP allowance as everything else — at 2 credits
        //     per MB against the 40-credit IP limit, one visitor can embed ~20 MB a day.
        Setting::setValue('rag_ingest_credits_per_mb', '2', 'string', 'rag');
        Setting::setValue('rag_ingest_credits_url', '1', 'string', 'rag');
        Setting::setValue('rag_chunks_per_credit', '10', 'integer', 'rag');

        // 4e. Ephemeral uploads are disk, not tokens, but a public demo accumulates them
        //     from strangers indefinitely. Two days is long enough to finish a session.
        Setting::setValue('rag_ephemeral_retention_days', '2', 'integer', 'rag');
    }

    /**
     * Stamp a freshly written row with the timestamps it was meant to have.
     *
     * Every model in this app declares an explicit $fillable, and none of them list
     * created_at/updated_at — so a 'created_at' handed to create()/updateOrCreate() is
     * silently discarded by mass-assignment and the row lands on today. That flattened
     * every seeded timeline in this file onto "now": the revenue chart, AI usage, signups,
     * blog and comment dates, login history, reviews. Force-filling after the insert is
     * the only thing that sticks.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function backdate($model, $createdAt, $updatedAt = null)
    {
        $model->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt ?? $createdAt,
        ])->save();

        return $model;
    }

    /**
     * How many records to seed for the day $daysAgo back.
     *
     * Every dashboard KPI compares a window against the window before it — today vs
     * yesterday, 7d vs the prior 7d, 30d vs the prior 30d. A flat spread makes all of
     * them land on roughly 0%, and the moment today is only partly seeded they go
     * negative, which is why the demo read as a business in decline on both the admin
     * and the creator dashboard.
     *
     * The bands below ramp toward the present, and each step is wide enough that the
     * jitter inside it can never invert the comparison between two adjacent windows.
     * Today is deliberately the busiest day on every chart.
     *
     * $scale thins or thickens the whole curve for series that should be quieter than
     * generations (revenue, internal platform usage).
     */
    private function dailyVolume(int $daysAgo, float $scale = 1.0): int
    {
        // Tuned so growth is visible at every range a card offers but still believable:
        // roughly +25% on today, +20% on 7d, +40% on 30d and +65% on 90d. A steeper ramp
        // reads as fabricated — an early draft hit +1467% on 30d signups — and a flatter
        // one puts the short ranges back on 0%.
        [$low, $high] = match (true) {
            $daysAgo === 0 => [9, 10],
            $daysAgo <= 6 => [7, 8],
            $daysAgo <= 13 => [6, 7],
            $daysAgo <= 29 => [5, 6],
            $daysAgo <= 59 => [4, 5],
            $daysAgo <= 119 => [3, 4],
            default => [2, 3],
        };

        return max(1, (int) round(mt_rand($low, $high) * $scale));
    }

    /**
     * $count moments spread across the calendar day $daysAgo back, between $from and
     * $to o'clock.
     *
     * Deliberately independent of the current clock. The loops this replaces ran
     * `$hour <= now()->hour`, which had two consequences: today was truncated at
     * whatever hour demo:reset happened to fire (the showcase account's loop started
     * at 08:00, so a reset before then left today with a single row), and because each
     * iteration consumed mt_rand() draws, the hour also shifted every later draw in the
     * seeder — so no two resets produced the same demo despite the fixed RANDOM_SEED.
     *
     * Seeding hours later than "now" is correct here: every dashboard window ends at
     * endOfDay(), so those rows are counted, and it is what makes today a whole day.
     */
    private function spreadOverDay(int $daysAgo, int $count, int $from = 7, int $to = 22): array
    {
        $day = now()->subDays($daysAgo)->startOfDay();
        $span = max(1, $to - $from);
        $moments = [];

        for ($i = 0; $i < $count; $i++) {
            $hour = $from + (int) floor($span * $i / max(1, $count));
            $moments[] = $day->copy()->setTime(min(23, $hour), mt_rand(0, 59));
        }

        return $moments;
    }

    /**
     * Moments for a day from an explicit hour => count map.
     *
     * spreadOverDay() divides its count evenly across the working hours, so every hourly
     * bucket it produces holds exactly one record. That is invisible on the day-bucketed
     * ranges, but the "Today" range on the dashboard is bucketed by hour — and a row of
     * identical one-high bars scattered over 24 slots is not a chart. A profile lets a
     * day carry a shape: busy mid-morning, a lunch dip, an afternoon peak, a quiet
     * evening tail.
     *
     * @param  array<int, int>  $profile  hour (0-23) => number of records in that hour
     * @return list<\Carbon\Carbon>
     */
    private function spreadOverDayByProfile(int $daysAgo, array $profile): array
    {
        $day = now()->subDays($daysAgo)->startOfDay();
        $moments = [];

        foreach ($profile as $hour => $count) {
            for ($i = 0; $i < $count; $i++) {
                $moments[] = $day->copy()->setTime(min(23, max(0, $hour)), mt_rand(0, 59));
            }
        }

        // Chronological, so sequential ids still read in order within the day.
        usort($moments, fn ($a, $b) => $a <=> $b);

        return $moments;
    }

    /**
     * A day offset in [0, $max] biased toward the present — the smaller of two draws,
     * so about three quarters of the values land in the newer half of the range. Keeps
     * login sessions (and so the Active Users card) rising toward today instead of
     * sitting flat across the whole 90-day window.
     */
    private function recentBiasedDaysAgo(int $max): int
    {
        return min(mt_rand(0, $max), mt_rand(0, $max));
    }

    /**
     * A book of subscribers large enough for the subscription charts to have a line.
     *
     * Each account gets one billing row and the payment that opened it, so the subscriber
     * count, the churn series and the revenue line describe the same business rather than
     * three unrelated numbers.
     *
     * Volume follows the same recency ramp as every other series, and cancellations are
     * dated forward from each signup — a cancellation that has not happened yet simply has
     * not happened. That is what keeps churn coming from the older, smaller cohorts, so it
     * never outruns new signups on the chart.
     */
    private function seedSubscriberCohort(Plan $plan, array $gateways, array $cycles, array $amounts, string $password): void
    {
        $firstNames = ['Adela', 'Boris', 'Camila', 'Dmitri', 'Esme', 'Felix', 'Gisela', 'Hakim',
            'Ilona', 'Janek', 'Kamila', 'Lorenz', 'Marisol', 'Nadim', 'Orla', 'Pavel',
            'Rania', 'Stefan', 'Tamsin', 'Ugo', 'Valeria', 'Wojtek', 'Xenia', 'Yusuf', 'Zofia'];
        $lastNames = ['Adeyemi', 'Brennan', 'Costa', 'Dvorak', 'Eriksen', 'Ferrara', 'Gundersen',
            'Halloran', 'Ibrahim', 'Jankovic', 'Karlsson', 'Lindholm', 'Moreau', 'Nakamura',
            'Oyelaran', 'Pashkov', 'Quiroga', 'Rasmussen', 'Sandoval', 'Tikhonov'];

        $hashedPassword = Hash::make($password);
        $seq = 0;

        // Oldest first, so sequential ids read chronologically in the admin lists.
        for ($day = 179; $day >= 0; $day--) {
            // Today is the only day the dashboard draws hour by hour, so it gets an
            // explicit shape instead of the even spread every other day uses. See
            // TODAY_SIGNUP_HOURS for why.
            $moments = $day === 0
                ? $this->spreadOverDayByProfile(0, self::TODAY_SIGNUP_HOURS)
                : $this->spreadOverDay($day, $this->dailyVolume($day, 0.45), 8, 21);

            foreach ($moments as $joinedAt) {
                $seq++;

                $cycle = $cycles[$seq % count($cycles)];
                $gateway = $gateways[$seq % count($gateways)];
                $amount = $amounts[$cycle];

                // Roughly one in four eventually cancels, 20-75 days after signing up.
                // One in six left visible gaps in the churn line at 7d; this keeps it
                // continuous while staying far below the new-signup line.
                // The wide offset window matters as much as the rate: a narrow one bunches
                // cancellations onto the same few dates, which draws a spiky churn line
                // with holes in it rather than a low continuous one.
                $cancelledAt = $seq % 4 === 0
                    ? $joinedAt->copy()->addDays(mt_rand(14, 95))
                    : null;

                if ($cancelledAt && $cancelledAt->isFuture()) {
                    $cancelledAt = null;
                }

                $status = match (true) {
                    $cancelledAt !== null => 'cancelled',
                    $seq % 11 === 0 && $day <= 12 => 'trialing',
                    default => 'active',
                };

                // A live subscription showing a period that ended weeks ago is exactly the
                // detail that makes a demo look fake, so active plans roll forward to the
                // next boundary that has not passed.
                $periodEnd = $cancelledAt ? $cancelledAt->copy() : $joinedAt->copy();

                while (! $cancelledAt && $periodEnd->isPast()) {
                    $cycle === 'yearly' ? $periodEnd->addYear() : $periodEnd->addMonth();
                }

                $trialEndsAt = $status === 'trialing' ? now()->addDays(mt_rand(3, 12)) : null;

                $user = User::updateOrCreate(
                    ['email' => 'member'.$seq.'@demo.com'],
                    [
                        'name' => $firstNames[$seq % count($firstNames)].' '.$lastNames[$seq % count($lastNames)],
                        'password' => $hashedPassword,
                        'credits' => mt_rand(120, 4200),
                        'plan_id' => $plan->id,
                        'subscription_status' => $status,
                        'subscription_ends_at' => $cancelledAt ?? $periodEnd,
                        'trial_ends_at' => $trialEndsAt,
                        'is_active' => true,
                        'email_verified_at' => $joinedAt,
                        'last_login_at' => now()->subHours(min(mt_rand(1, 168), mt_rand(1, 168))),
                    ]
                );

                $this->backdate($user, $joinedAt);

                $subscription = \App\Models\GatewaySubscription::updateOrCreate(
                    ['gateway_subscription_id' => 'demo-member-sub-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
                    [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'billing_cycle' => $cycle,
                        'status' => $status,
                        'gateway' => $gateway,
                        'amount' => $amount,
                        'currency' => 'USD',
                        'trial_ends_at' => $trialEndsAt,
                        'current_period_start' => $joinedAt,
                        'current_period_end' => $periodEnd,
                        'cancelled_at' => $cancelledAt,
                    ]
                );

                $this->backdate($subscription, $joinedAt, $cancelledAt ?? $joinedAt);

                // The payment that opened the subscription, so the revenue line and the
                // subscriber count cannot contradict each other.
                $payment = Payment::updateOrCreate(
                    ['gateway_payment_id' => 'demo-member-pay-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
                    [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'gateway' => $gateway,
                        'amount' => $amount,
                        'currency' => 'USD',
                        'status' => 'completed',
                        'type' => 'subscription',
                        'metadata' => ['demo' => true, 'cohort' => 'subscriber'],
                    ]
                );

                $this->backdate($payment, $joinedAt);
            }
        }

        $this->redateTodayCancellations();
    }

    /**
     * Give today's churn series the same hourly shape the signup series has.
     *
     * Cancellations are dated forward from each signup, so whether any of them land on
     * today — and at which hour — is left to the 14-95 day offset. Over a day-bucketed
     * range that averages out; over today's 24 hourly buckets it does not, and the
     * downward half of the Subscription Health chart came out empty or a single stray bar.
     *
     * This moves cancellations that already exist rather than creating new ones: the
     * churn total over 7d/30d/90d is unchanged, no subscription changes status, and the
     * new-subscription series is untouched (a cancelled row is already excluded from it).
     * Only the timestamp moves, from some past date onto an hour of today.
     *
     * Runs last so it sees the whole cohort, and selects on id rather than on the dates it
     * is about to rewrite. That is what makes it re-runnable: this seeder is written to be
     * applied twice (the dashboard coverage test does exactly that), and an earlier version
     * that filtered on `cancelled_at < today` skipped the rows it had already moved and
     * dragged a fresh five onto today on every pass.
     */
    private function redateTodayCancellations(): void
    {
        $wanted = array_sum(self::TODAY_CHURN_HOURS);

        // The 21-day floor guarantees the new date still lands after the subscription
        // started — every candidate signed up months before today.
        $candidates = \App\Models\GatewaySubscription::query()
            ->where('gateway_subscription_id', 'like', 'demo-member-sub-%')
            ->whereNotNull('cancelled_at')
            ->where('created_at', '<=', now()->subDays(21))
            ->orderBy('id')
            ->get()
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        // Strided rather than "the first N", so the churn these dates are borrowed from is
        // spread across the whole six months instead of being drained out of the oldest
        // week — where, on the lifetime chart, five missing cancellations are visible.
        $stride = max(1, intdiv($candidates->count(), $wanted));
        $subscriptions = $candidates
            ->filter(fn ($subscription, $index) => $index % $stride === 0)
            ->take($wanted)
            ->values();

        $moments = $this->spreadOverDayByProfile(0, self::TODAY_CHURN_HOURS);
        $users = User::whereIn('id', $subscriptions->pluck('user_id'))->get()->keyBy('id');

        foreach ($subscriptions as $index => $subscription) {
            $cancelledAt = $moments[$index] ?? null;

            if (! $cancelledAt) {
                break;
            }

            // current_period_end moves with it: the churn query buckets on whichever of
            // the two it finds, so leaving the old period end behind would report the
            // same cancellation twice, on two different days.
            $subscription->forceFill([
                'cancelled_at' => $cancelledAt,
                'current_period_end' => $cancelledAt,
                'updated_at' => $cancelledAt,
            ])->save();

            $users->get($subscription->user_id)?->forceFill([
                'subscription_ends_at' => $cancelledAt,
            ])->save();
        }
    }

    /**
     * Generate a self-contained initials avatar (gradient circle + initials) onto the public
     * disk and return its relative key. No external service — like the ad banners, this can
     * never 404 and carries no licensing baggage for a redistributed product. The colour is
     * deterministic from the name, so the same person always gets the same avatar.
     */
    private function demoAvatar(string $name, string $key): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = '?';
        }

        $palettes = [
            ['#6366f1', '#8b5cf6'], ['#0ea5e9', '#22d3ee'], ['#10b981', '#34d399'],
            ['#f59e0b', '#f97316'], ['#ec4899', '#f472b6'], ['#ef4444', '#fb7185'],
            ['#14b8a6', '#2dd4bf'], ['#8b5cf6', '#d946ef'], ['#3b82f6', '#6366f1'],
            ['#f43f5e', '#fb7185'], ['#0891b2', '#06b6d4'], ['#7c3aed', '#a78bfa'],
        ];
        $pair = $palettes[abs(crc32($name)) % count($palettes)];
        $gradientId = 'g' . substr(md5($key), 0, 6);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">'
            . '<defs><linearGradient id="' . $gradientId . '" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="' . $pair[0] . '"/><stop offset="1" stop-color="' . $pair[1] . '"/>'
            . '</linearGradient></defs>'
            . '<rect width="100" height="100" rx="50" fill="url(#' . $gradientId . ')"/>'
            . '<text x="50" y="50" dy="0.35em" text-anchor="middle" font-family="Segoe UI,Arial,sans-serif" '
            . 'font-size="40" font-weight="700" fill="#ffffff">' . $initials . '</text></svg>';

        Storage::disk('public')->put($key, $svg);

        return $key;
    }

    private function seedShowcaseUserExperience(User $user, Plan $plan, array $toolSlugs): void
    {
        $user->forceFill([
            'name' => 'Demo Creator',
            'referral_code' => 'DEMOAFF1',
            'affiliate_custom_slug' => 'demo-creator-affiliate',
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonths(11),
            'daily_limit' => 1200,
            'monthly_limit' => 15000,
            'referral_earnings' => 1200.75,
            'referral_count' => 6,
            'use_case' => 'marketing',
            'onboarding_completed_at' => now()->subDays(20),
            'last_login_at' => now()->subMinutes(18),
            'last_login_ip' => '203.76.120.45',
            'theme_preference' => 'system',
            'email_marketing' => true,
            'country' => 'US',
            'profession' => 'Digital Marketer & Creator',
            // Self-contained generated avatar (was an external Unsplash URL) — no external
            // request or licensing concern for a redistributed demo.
            'avatar' => $this->demoAvatar('Demo Creator', 'avatars/demo-creator.svg'),
            'brand_voice' => 'Direct, conversational, persuasive, with a touch of wit. Focuses on benefits and action-oriented verbs.',
            'chat_custom_instructions' => 'Provide concise, practical feedback. When writing code or copy, use clean formatting and bullet points. Avoid preamble.',
            'preferences' => ['marketing_emails' => true, 'security_alerts' => true, 'weekly_newsletter' => true],
            'cookie_consent' => ['analytics' => true, 'marketing' => true],
            'dismissed_tooltips' => ['dashboard_welcome', 'editor_guide', 'template_search'],
            'notification_preferences' => [
                'in_app' => ['billing' => true, 'content' => true, 'security' => true, 'system' => true],
                'email' => ['billing' => true, 'content' => false, 'security' => true, 'system' => false]
            ],
            'timezone' => 'America/New_York',
        ])->save();

        // The showcase account is the one a buyer signs into, so it needs a REAL billing row
        // to match its active status — without it the admin Subscriptions screen listed it as
        // a synthetic row (cycle "Not set", amount "Not available") and its own billing page
        // had no subscription to manage.
        $showcaseStartedAt = now()->subMonths(1)->startOfDay()->addHours(10);

        $showcaseSubscription = \App\Models\GatewaySubscription::updateOrCreate(
            ['gateway_subscription_id' => 'demo-sub-showcase'],
            [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => 'yearly',
                'status' => 'active',
                'gateway' => 'stripe',
                'amount' => 499.99,
                'currency' => 'USD',
                'trial_ends_at' => null,
                'current_period_start' => $showcaseStartedAt,
                'current_period_end' => $user->subscription_ends_at ?? now()->addMonths(11),
                'cancelled_at' => null,
            ]
        );

        $showcaseSubscription->forceFill([
            'created_at' => $showcaseStartedAt,
            'updated_at' => $showcaseStartedAt,
        ])->save();

        // Seed the user's BYOK provider keys. The relation is byok() — there is no
        // apiKeys(); App\Models\AiKey is the PLATFORM's own key pool, a different thing.
        // UserByok encrypts api_key on write, so these are stored the same way a real one is.
        $user->byok()->delete();
        $user->byok()->create([
            'provider' => 'openai',
            'api_key' => 'sk-proj-DEMOOPENAIKEY1234567890abcdefghijklmnopqrstuv',
            'is_active' => true,
        ]);
        $user->byok()->create([
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-api03-DEMOANTHROPICKEY1234567890abcdefghijklmnopqrstuv',
            'is_active' => true,
        ]);

        $documents = $this->seedShowcaseDocuments($user, $toolSlugs);
        $this->seedShowcaseChatbot($user);
        // Usage first: the credit ledger is derived from these generations, so the dashboard's
        // credit chart and the Usage page's generation chart show the same numbers.
        $this->seedShowcaseUsageLogs($user, $toolSlugs);
        $this->seedShowcaseCreditTimeline($user);
        $this->seedShowcaseLoginHistory($user);
        $this->seedShowcasePayments($user, $plan);
        $this->seedShowcaseChains($user, $toolSlugs);
        $this->seedShowcaseToolEmbeds($user, $toolSlugs);
        $this->seedShowcaseCollections($user, $toolSlugs);
        $this->seedShowcaseFavorites($user, $documents, $toolSlugs);
        $this->seedShowcaseAffiliateExperience($user);
        $this->seedShowcaseSupportTickets($user);
    }

    /**
     * Support inbox for the account a buyer signs into. The 12 generic tickets seeded further
     * down belong to background demo users and carry no replies, so /support was empty for the
     * showcase account and a ticket thread could never be opened. These six cover every status
     * (open / in_progress / waiting_user / resolved / closed), both read states, and the two
     * rating states — resolved-but-unrated so the rating form renders, and closed-and-rated.
     */
    private function seedShowcaseSupportTickets(User $user): void
    {
        $departments = SupportDepartment::whereIn('slug', ['general', 'technical', 'billing', 'feature-request'])
            ->get()
            ->keyBy('slug');

        $agent = Admin::where('email', config('demo.admin_email'))->first() ?? Admin::oldest('id')->first();

        // department_id is NOT NULL and every thread below has admin replies, so both the
        // departments (SupportSeeder) and an admin to answer as have to exist.
        if ($departments->isEmpty() || $agent === null) {
            return;
        }

        // Re-seeding resets the showcase inbox rather than updating in place: any ticket a
        // demo visitor opened on this account is dropped (replies and attachments cascade),
        // so the thread numbering below can never collide with a live ticket.
        SupportTicket::withTrashed()->where('user_id', $user->id)->forceDelete();

        $threads = [
            [
                'number' => 'DEMO-TKT-1001',
                'department' => 'billing',
                'subject' => 'Invoice for the annual plan shows our old company name',
                'status' => 'closed',
                'priority' => 'medium',
                'source' => 'email',
                'age_hours' => 26 * 24,
                'resolved_after' => 22,
                'closed_after' => 3 * 24,
                'rating' => 5,
                'rating_comment' => 'Reissued the same day and explained exactly what changed. Excellent.',
                'unread' => false,
                'replies' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>Hi team,</p><p>We rebranded last month and the receipt for our Professional yearly renewal ($499.99) still shows the previous company name. Our accountant cannot file it as is.</p><p>Could you reissue it with the updated details? Happy to send them over.</p>'],
                    ['author' => 'admin', 'at' => 3, 'content' => '<p>Hello,</p><p>Of course — we can reissue that invoice. Please reply with the legal company name, billing address, and VAT/tax number exactly as they should appear, and we will regenerate the document.</p><p>Best regards,<br>Support Team</p>'],
                    ['author' => 'user', 'at' => 5, 'content' => '<p>Here they are:</p><ul><li><strong>Company:</strong> Demo Creator Studio LLC</li><li><strong>Address:</strong> 1200 Market Street, Suite 400, Wilmington, DE 19801</li><li><strong>VAT / Tax ID:</strong> US-DE-88-4192730</li></ul><p>Thank you!</p>'],
                    ['author' => 'admin', 'at' => 20, 'content' => '<p>Hello,</p><p>The invoice has been reissued with the new details and emailed to the address on your account. You can also download it any time from <strong>Billing &rarr; Invoices</strong>; the old document is now marked as superseded.</p><p>Your future receipts will use these details automatically.</p><p>Best regards,<br>Support Team</p>'],
                    ['author' => 'user', 'at' => 26, 'content' => '<p>Received it, and the details are correct. Thanks for turning this around so quickly.</p>'],
                ],
            ],
            [
                'number' => 'DEMO-TKT-1002',
                'department' => 'technical',
                'subject' => 'Bulk export of my documents times out at around 400 files',
                'status' => 'resolved',
                'priority' => 'high',
                'source' => 'web',
                'age_hours' => 9 * 24,
                'resolved_after' => 31,
                'closed_after' => null,
                // Left unrated on purpose: this is the thread that shows the satisfaction form.
                'rating' => null,
                'rating_comment' => null,
                'unread' => false,
                'replies' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>Every time I export my full document library the download fails after a couple of minutes. Smaller selections (around 50 documents) work fine, so it looks size-related.</p><p>Browser is Chrome on macOS, and I have roughly 430 documents in the account.</p>'],
                    ['author' => 'admin', 'at' => 4, 'content' => '<p>Hello,</p><p>Thanks for the detail — that helps. Large exports are built in the background, so the browser request should not be the one doing the work. Could you confirm whether you started the export from <strong>Documents &rarr; Export selected</strong> or from the Export Center?</p><p>Best regards,<br>Technical Support</p>'],
                    ['author' => 'user', 'at' => 7, 'content' => '<p>From <strong>Documents &rarr; Export selected</strong>, with "Select all" ticked. I have not used the Export Center for this.</p>'],
                    ['author' => 'admin', 'at' => 30, 'content' => '<p>Hello,</p><p>Confirmed and fixed. Exports above 250 documents are now queued and delivered as a download link once they finish, instead of being generated inline.</p><p>We ran your full library through the new path and it completed in about 40 seconds — the archive is waiting for you in the Export Center. Marking this as resolved, but reply here if it reappears.</p><p>Best regards,<br>Technical Support</p>'],
                ],
            ],
            [
                'number' => 'DEMO-TKT-1003',
                'department' => 'technical',
                'subject' => 'Brand voice settings are ignored in long-form blog output',
                'status' => 'in_progress',
                'priority' => 'high',
                'source' => 'web',
                'age_hours' => 5 * 24,
                'resolved_after' => null,
                'closed_after' => null,
                'rating' => null,
                'rating_comment' => null,
                'unread' => false,
                'replies' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>My saved brand voice (direct, conversational, benefit-led) is applied correctly in short-form tools like ad copy and social captions, but the Blog Post Writer ignores it once the output goes past roughly 800 words — the tone drifts back to generic marketing filler.</p><p>Is the instruction being truncated somewhere in the prompt?</p>'],
                    ['author' => 'admin', 'at' => 2, 'content' => '<p>Hello,</p><p>Good catch, and thank you for the precise description. Could you share the document title (or ID) of a generation where this happened, along with the model you selected? That lets us replay the exact request.</p><p>Best regards,<br>Technical Support</p>'],
                    ['author' => 'user', 'at' => 6, 'content' => '<p>Sure — the document is <strong>"Q3 AI Launch Campaign"</strong>, generated with Claude Sonnet at around 1,400 words. The first two sections sound right, then it flattens out.</p>'],
                    ['author' => 'admin', 'at' => 26, 'content' => '<p>Hello,</p><p>We reproduced it. On long generations the brand voice is only sent with the opening section, so later sections are written without it — that is a bug on our side and it is now with the engineering team.</p><p>As a workaround, adding the voice as a short line in the tool\'s "Additional instructions" field keeps it in every section. We will update this ticket as soon as the fix ships.</p><p>Best regards,<br>Technical Support</p>'],
                ],
            ],
            [
                'number' => 'DEMO-TKT-1004',
                'department' => 'general',
                'subject' => 'Change the PayPal address my affiliate payouts are sent to',
                'status' => 'waiting_user',
                'priority' => 'medium',
                'source' => 'web',
                'age_hours' => 2 * 24,
                'resolved_after' => null,
                'closed_after' => null,
                'rating' => null,
                'rating_comment' => null,
                // Newest admin reply lands after the user last read the thread, so it renders
                // as unread in the ticket view.
                'unread' => true,
                'replies' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>I have a $250.00 payout pending and I need it to go to a different PayPal account than the one on file. What is the safest way to change it before the payout is processed?</p>'],
                    ['author' => 'admin', 'at' => 5, 'content' => '<p>Hello,</p><p>We can update the payout destination while the request is still pending. For security we need two things from you:</p><ol><li>Confirm the new PayPal email address here, from this account.</li><li>Complete the verification step we sent to your registered email.</li></ol><p>Once both are done we will switch the destination and the pending payout will go to the new address.</p><p>Best regards,<br>Support Team</p>'],
                ],
            ],
            [
                'number' => 'DEMO-TKT-1005',
                'department' => 'feature-request',
                'subject' => 'Add a scheduled queue for generated social captions',
                'status' => 'open',
                'priority' => 'low',
                'source' => 'web',
                'age_hours' => 20,
                'resolved_after' => null,
                'closed_after' => null,
                'rating' => null,
                'rating_comment' => null,
                'unread' => false,
                'replies' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>A small feature idea: after generating a batch of social captions I currently copy them out one by one into a separate scheduling tool.</p><p>It would save a lot of time if captions could be queued with a publish date directly from the collection view — even a simple CSV export with a date column would help.</p>'],
                ],
            ],
            [
                'number' => 'DEMO-TKT-1006',
                'department' => 'billing',
                'subject' => 'Charged twice for the annual renewal',
                'status' => 'open',
                'priority' => 'urgent',
                'source' => 'web',
                'age_hours' => 6,
                'resolved_after' => null,
                'closed_after' => null,
                'rating' => null,
                'rating_comment' => null,
                'unread' => false,
                'replies' => [
                    ['author' => 'user', 'at' => 0, 'content' => '<p>My card statement shows two charges of $499.99 on the same day for the Professional yearly plan, but my billing page only lists one subscription.</p><p>Could you check whether the second one is a duplicate charge or just a pending authorization? Reference ends in 4417.</p>'],
                ],
            ],
        ];

        foreach ($threads as $thread) {
            $department = $departments[$thread['department']] ?? $departments->first();
            $createdAt = now()->subMinutes((int) round($thread['age_hours'] * 60));
            $offset = fn ($hours) => $createdAt->copy()->addMinutes((int) round($hours * 60));

            $adminReplies = array_values(array_filter($thread['replies'], fn ($reply) => $reply['author'] === 'admin'));
            $lastReply = $thread['replies'][count($thread['replies']) - 1];
            $lastReplyAt = $offset($lastReply['at']);
            $firstResponseAt = $adminReplies === [] ? null : $offset($adminReplies[0]['at']);

            $ticket = SupportTicket::create([
                'ticket_number' => $thread['number'],
                'user_id' => $user->id,
                'department_id' => $department->id,
                // Unanswered tickets stay in the unassigned queue, which is what the admin
                // "Unassigned" filter needs something to show.
                'assigned_to' => $adminReplies === [] ? null : $agent->id,
                'subject' => $thread['subject'],
                'status' => $thread['status'],
                'priority' => $thread['priority'],
                'source' => $thread['source'],
                'first_response_at' => $firstResponseAt,
                'resolved_at' => $thread['resolved_after'] === null ? null : $offset($thread['resolved_after']),
                'closed_at' => $thread['closed_after'] === null ? null : $offset($thread['closed_after']),
                'last_reply_at' => $lastReplyAt,
                'last_reply_by' => $lastReply['author'],
                'satisfaction_rating' => $thread['rating'],
                'satisfaction_comment' => $thread['rating_comment'],
                'user_last_read_at' => $thread['unread'] ? $createdAt->copy()->addMinutes(10) : $lastReplyAt->copy()->addMinutes(20),
                'admin_last_read_at' => $lastReplyAt->copy()->addMinutes(5),
            ]);

            $ticket->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $lastReplyAt,
            ])->save();

            foreach ($thread['replies'] as $reply) {
                $writtenAt = $offset($reply['at']);

                // created_at is not fillable on SupportTicketReply, so the thread timeline has
                // to be forced on after the row exists — otherwise every reply lands at "now"
                // and the conversation reads out of order against the ticket's own dates.
                $ticket->replies()->create([
                    'author_type' => $reply['author'],
                    'author_id' => $reply['author'] === 'admin' ? $agent->id : $user->id,
                    'content' => $reply['content'],
                    'attachments' => null,
                    'is_internal_note' => false,
                    'is_ai_draft' => false,
                ])->forceFill([
                    'created_at' => $writtenAt,
                    'updated_at' => $writtenAt,
                ])->save();
            }
        }
    }

    private function seedShowcaseAffiliateExperience(User $user): void
    {
        AffiliateReferral::where('referrer_id', $user->id)->delete();
        AffiliateCommission::where('referrer_id', $user->id)->delete();
        AffiliatePayout::where('user_id', $user->id)->delete();

        $referrals = [
            ['user' => User::where('email', 'iris9@demo.com')->first(), 'days' => 18, 'converted' => true, 'commission' => 185.50, 'status' => 'approved'],
            ['user' => User::where('email', 'jack10@demo.com')->first(), 'days' => 16, 'converted' => true, 'commission' => 240.00, 'status' => 'approved'],
            ['user' => User::where('email', 'karen11@demo.com')->first(), 'days' => 14, 'converted' => true, 'commission' => 165.25, 'status' => 'paid'],
            ['user' => User::where('email', 'leo12@demo.com')->first(), 'days' => 11, 'converted' => true, 'commission' => 310.00, 'status' => 'approved'],
            ['user' => User::where('email', 'maria13@demo.com')->first(), 'days' => 9, 'converted' => true, 'commission' => 225.00, 'status' => 'pending'],
            ['user' => User::where('email', 'nick14@demo.com')->first(), 'days' => 6, 'converted' => true, 'commission' => 300.00, 'status' => 'approved'],
            ['user' => User::where('email', 'olivia15@demo.com')->first(), 'days' => 4, 'converted' => false, 'commission' => null, 'status' => null],
            ['user' => User::where('email', 'paul16@demo.com')->first(), 'days' => 2, 'converted' => false, 'commission' => null, 'status' => null],
        ];

        $commissionIndex = 1;

        foreach ($referrals as $index => $entry) {
            $referredUser = $entry['user'];
            if (! $referredUser) {
                continue;
            }

            $landedAt = now()->subDays($entry['days'])->setTime(10 + ($index % 6), 12 + (($index * 9) % 35));
            $convertedAt = $entry['converted'] ? $landedAt->copy()->addHours(6 + $index) : null;

            // created_at is not fillable on any of these models, so it has to be forced on
            // after the row exists. Passing it through updateOrCreate was silently dropped
            // and every referral, payment and commission below was landing on today —
            // which is why the affiliate chart spiked at "now" instead of spreading out.
            $referral = AffiliateReferral::updateOrCreate(
                ['referrer_id' => $user->id, 'referral_code' => $user->referral_code, 'ip_address' => '198.51.100.' . (20 + $index)],
                [
                    'referred_id' => $referredUser->id,
                    'landed_at' => $landedAt,
                    'converted_at' => $convertedAt,
                ]
            );

            $referral->forceFill(['created_at' => $landedAt, 'updated_at' => $landedAt])->save();

            $referredUser->forceFill([
                'referred_by' => $user->id,
                'created_at' => $landedAt->copy()->subHours(3),
            ])->save();

            if (! $entry['converted']) {
                continue;
            }

            $payment = Payment::updateOrCreate(
                ['gateway_payment_id' => 'demo-aff-pay-' . str_pad((string) $commissionIndex, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $referredUser->id,
                    'plan_id' => $referredUser->plan_id,
                    'gateway' => 'stripe',
                    'amount' => $entry['commission'] * 5,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'type' => 'subscription',
                    'metadata' => ['demo' => true, 'affiliate' => true, 'referral_code' => $user->referral_code],
                ]
            );

            $paidAt = $convertedAt->copy()->addMinutes($index * 7);
            $payment->forceFill(['created_at' => $paidAt, 'updated_at' => $paidAt])->save();

            $commission = AffiliateCommission::updateOrCreate(
                ['order_id' => $payment->id],
                [
                    'referrer_id' => $user->id,
                    'referred_id' => $referredUser->id,
                    'amount' => $entry['commission'],
                    'status' => $entry['status'],
                    'approved_at' => in_array($entry['status'], ['approved', 'paid'], true) ? $convertedAt->copy()->addHours(2) : null,
                    // No 'notes' here: affiliate_commissions has no such column.
                    'paid_at' => $entry['status'] === 'paid' ? $convertedAt->copy()->addDays(3) : null,
                ]
            );

            $commission->forceFill([
                'created_at' => $convertedAt,
                'updated_at' => $convertedAt,
            ])->save();

            $commissionIndex++;
        }

        AffiliatePayout::updateOrCreate(
            ['user_id' => $user->id, 'amount' => 250.00, 'method' => 'paypal', 'status' => 'pending'],
            [
                'payout_details' => [
                    'account' => 'demo@payments.example',
                    'note' => 'Demo showcase payout request',
                ],
            ]
        )->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        AffiliatePayout::updateOrCreate(
            ['user_id' => $user->id, 'amount' => 180.00, 'method' => 'credits', 'status' => 'paid'],
            [
                'payout_details' => [
                    'account' => 'In-app credits',
                    'note' => 'Demo showcase payout completed',
                ],
                'processed_at' => now()->subDays(8),
            ]
        )->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(8),
        ])->save();

        $this->seedShowcaseAffiliateTimeline($user);

        // The cached totals on the user row are set by hand in seedShowcaseUserExperience();
        // recompute them from the ledger the same way the app does, so the profile figures
        // agree with the commissions that now exist.
        app(AffiliateService::class)->syncReferralTotals($user->id);
    }

    /**
     * A continuous click / registration / conversion timeline for the affiliate chart.
     *
     * The narrative referrals above all sit between 2 and 18 days back, so the chart's 1D
     * view was completely empty and 7D had holes — the same problem the admin dashboard
     * solves for its own cards by seeding a low-volume timeline that reaches into the
     * current hour. Every bucket the chart can ask for now has something in it: hours
     * across today (1D), each of the last seven days (7D), every day of the last month
     * (1M) and every month of the last year (1Y).
     *
     * Clicks are anonymous referral rows (no referred_id); registrations and conversions
     * need a real account and order behind them, so those get their own referred users.
     */
    private function seedShowcaseAffiliateTimeline(User $user): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $password = Hash::make(config('demo.user_password'));

        // ── Clicks: dense enough that no bucket of any period is blank ──────────
        $clickTimes = collect();

        // Today, every couple of hours, plus one inside the current hour so the 1D
        // chart's "is_current" bucket is never the empty one.
        for ($hour = 0; $hour <= now()->hour; $hour += 2) {
            $clickTimes->push(now()->startOfDay()->addHours($hour)->addMinutes(mt_rand(0, 59)));
        }
        // Anchored to the start of the current hour, not now()->subMinutes(...) — subtracting
        // minutes falls into the previous hour early in the hour, which is exactly the bucket
        // the 1D chart highlights as current.
        $clickTimes->push(now()->startOfHour()->addMinutes(mt_rand(0, (int) now()->minute)));

        // The rest of the month, day by day.
        for ($day = 1; $day <= 29; $day++) {
            foreach (range(1, mt_rand(1, 3)) as $ignored) {
                $clickTimes->push(now()->subDays($day)->setTime(mt_rand(7, 22), mt_rand(0, 59)));
            }
        }

        // Months 2-11 back — the 30-day loop already covers this month and last.
        for ($monthsAgo = 2; $monthsAgo <= 11; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            foreach (range(1, mt_rand(4, 8)) as $ignored) {
                $clickTimes->push($monthStart->copy()->addDays(mt_rand(0, $monthStart->daysInMonth - 1))->setTime(mt_rand(7, 22), mt_rand(0, 59)));
            }
        }

        // Plain create(): every referral for this user was deleted by the caller, so there
        // is nothing to collide with and no synthetic unique key to invent.
        foreach ($clickTimes as $landedAt) {
            AffiliateReferral::create([
                'referrer_id' => $user->id,
                'referred_id' => null,
                'referral_code' => $user->referral_code,
                'ip_address' => '198.51.100.' . mt_rand(30, 250),
                'landed_at' => $landedAt,
                'converted_at' => null,
            ])->forceFill(['created_at' => $landedAt, 'updated_at' => $landedAt])->save();
        }

        // ── Registrations + conversions ─────────────────────────────────────────
        // One today, one on each of the last six days, then thinning out across the rest
        // of the month and one a month for the rest of the year.
        $signupTimes = collect([now()->subHours(mt_rand(3, 6))])
            ->merge(collect(range(1, 6))->map(fn (int $day) => now()->subDays($day)->setTime(mt_rand(8, 21), mt_rand(0, 59))))
            // Runs to day 59, not day 29. The series used to jump straight from 29 to
            // month 2, which left days 30-59 empty — and that is exactly the window the
            // 30d signups card compares against, so the card read several hundred percent
            // growth off an almost-empty baseline.
            ->merge(collect([8, 11, 14, 17, 20, 23, 26, 29, 32, 35, 38, 41, 44, 47, 50, 53, 56, 59])
                ->map(fn (int $day) => now()->subDays($day)->setTime(mt_rand(8, 21), mt_rand(0, 59))));

        for ($monthsAgo = 2; $monthsAgo <= 11; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            $signupTimes->push($monthStart->copy()->addDays(mt_rand(0, $monthStart->daysInMonth - 1))->setTime(mt_rand(8, 21), mt_rand(0, 59)));
        }

        $referredNames = ['Amara Cole', 'Brett Okafor', 'Cleo Ramirez', 'Dario Fenn', 'Elin Novak',
            'Farid Haddad', 'Greta Lindqvist', 'Hugo Almeida', 'Ines Duarte', 'Jonas Weber',
            'Kaya Sato', 'Liam Doherty', 'Mira Kovac', 'Noor Rahimi', 'Otto Lindgren',
            'Petra Balint', 'Quinn Adeyemi', 'Rafael Moreno', 'Sana Iqbal', 'Tomas Sokol',
            'Ulla Virtanen', 'Vito Marchetti', 'Wren Callahan', 'Yara Nassar', 'Zoran Petrov'];

        foreach ($signupTimes->values() as $index => $signupAt) {
            $name = $referredNames[$index % count($referredNames)];
            $landedAt = $signupAt->copy()->subMinutes(mt_rand(20, 90));
            $convertedAt = $signupAt->copy();

            $referredUser = User::updateOrCreate(
                ['email' => 'referred' . ($index + 1) . '@demo.com'],
                [
                    'name' => $name,
                    'password' => $password,
                    'credits' => mt_rand(40, 900),
                    'plan_id' => $plan?->id,
                    'subscription_status' => $plan ? 'active' : 'none',
                    'subscription_ends_at' => $plan ? now()->addMonths(mt_rand(1, 11)) : null,
                    'referred_by' => $user->id,
                    'avatar' => $this->demoAvatar($name, 'avatars/demo-referred-' . ($index + 1) . '.svg'),
                    'is_active' => true,
                    'email_verified_at' => $convertedAt,
                ]
            );

            $referredUser->forceFill([
                'created_at' => $convertedAt,
                'updated_at' => $convertedAt,
            ])->save();

            AffiliateReferral::create([
                'referrer_id' => $user->id,
                'referred_id' => $referredUser->id,
                'referral_code' => $user->referral_code,
                'ip_address' => '198.51.100.' . mt_rand(30, 250),
                'landed_at' => $landedAt,
                'converted_at' => $convertedAt,
            ])->forceFill(['created_at' => $landedAt, 'updated_at' => $landedAt])->save();

            $orderAmount = round(mt_rand(39, 299) + 0.99, 2);
            $payment = Payment::updateOrCreate(
                ['gateway_payment_id' => 'demo-aff-tl-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $referredUser->id,
                    'plan_id' => $plan?->id,
                    'gateway' => 'stripe',
                    'amount' => $orderAmount,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'type' => 'subscription',
                    'metadata' => ['demo' => true, 'affiliate' => true, 'timeline' => true, 'referral_code' => $user->referral_code],
                ]
            );

            $payment->forceFill(['created_at' => $convertedAt, 'updated_at' => $convertedAt])->save();

            // Recent commissions are still inside the hold window, so they read as pending;
            // older ones are approved, and the oldest have already been paid out.
            //
            // Every 5th and 9th referral instead lands on one of the two failure states, so
            // the Commissions status filter has rows behind ALL FIVE of its tabs — `rejected`
            // and `cancelled` were never seeded at all, so those tabs were permanently empty
            // on a demo and looked broken rather than merely quiet.
            $status = match (true) {
                // A refunded order cancels the commission it earned.
                $index % 9 === 4 => 'cancelled',
                // Fraud/self-referral checks reject one occasionally.
                $index % 5 === 3 => 'rejected',
                $convertedAt->gt(now()->subDays(14)) => 'pending',
                $convertedAt->gt(now()->subDays(90)) => 'approved',
                default => 'paid',
            };

            AffiliateCommission::create([
                'referrer_id' => $user->id,
                'referred_id' => $referredUser->id,
                'order_id' => $payment->id,
                'amount' => round($orderAmount * 0.2, 2),
                'status' => $status,
                // Only approved/paid were ever approved; rejected and cancelled never were.
                'approved_at' => in_array($status, ['approved', 'paid'], true) ? $convertedAt->copy()->addHours(2) : null,
                'paid_at' => $status === 'paid' ? $convertedAt->copy()->addDays(mt_rand(15, 25)) : null,
            ])->forceFill(['created_at' => $convertedAt, 'updated_at' => $convertedAt])->save();
        }
    }

    /**
     * The credit ledger, derived from the generations seeded by seedShowcaseUsageLogs().
     *
     * The user dashboard draws its usage chart from credit_transactions while the Usage page
     * draws its from ai_usage_logs. Those were two unrelated sets of hand-written numbers, so
     * the same day reported different credit totals on the two screens and the dashboard's
     * 90d view was almost entirely empty. One debit per active day, equal to that day's
     * generations, makes the two charts the same curve — and gives the dashboard 90 days of
     * continuous data for free.
     *
     * Top-ups are the only invented rows; they bracket the usage so the balance stays
     * positive and the transaction list has something other than debits in it.
     */
    private function seedShowcaseCreditTimeline(User $user): void
    {
        CreditTransaction::where('user_id', $user->id)
            ->where('description', 'like', 'Demo showcase:%')
            ->delete();

        // One debit per day the account generated anything, summing that day's credits.
        $dailySpend = AiUsageLog::where('user_id', $user->id)
            ->get(['created_at', 'credits_used'])
            ->groupBy(fn ($log) => $log->created_at->toDateString())
            ->map(fn ($logs) => (float) $logs->sum('credits_used'));

        $entries = $dailySpend
            ->map(fn (float $credits, string $date) => [
                'at' => \Illuminate\Support\Carbon::parse($date)->setTime(21, 45),
                'amount' => -$credits,
                'type' => 'usage',
                'description' => 'Demo showcase: Generations on ' . \Illuminate\Support\Carbon::parse($date)->translatedFormat('M j'),
            ])
            ->values();

        // Top-ups: one a year ago to open the account, then refills as the balance drew down,
        // plus the affiliate payout taken as credits (mirrors the 180.00 credits payout in
        // seedShowcaseAffiliateExperience).
        $topUps = collect([
            ['at' => now()->subMonths(11)->startOfMonth()->addDays(2)->setTime(10, 15), 'amount' => 12000, 'type' => 'purchase', 'description' => 'Demo showcase: Annual Studio access'],
            ['at' => now()->subMonths(11)->startOfMonth()->addDays(3)->setTime(11, 0), 'amount' => 4000, 'type' => 'bonus', 'description' => 'Demo showcase: Launch bonus credits'],
            ['at' => now()->subMonths(5)->startOfMonth()->addDays(9)->setTime(14, 30), 'amount' => 6000, 'type' => 'purchase', 'description' => 'Demo showcase: Mid-year credit top-up'],
            ['at' => now()->subDays(8)->setTime(16, 20), 'amount' => 750, 'type' => 'referral', 'description' => 'Demo showcase: Partner referral payout'],
            // The only row typed 'topup' rather than 'purchase', and deliberately so: that
            // string is what addCredits() writes for a paid top-up and what the Usage page's
            // top-up card sums to size itself. With none of them the card had no denominator
            // and the whole purchased-credits half of that screen stayed dark. Backed by
            // demo-showcase-pay-008 in seedShowcasePayments().
            ['at' => now()->subDays(4)->setTime(13, 5), 'amount' => 2000, 'type' => 'topup', 'description' => 'Demo showcase: Credit top-up (2,000 credits)'],
        ]);

        $ledger = $entries->concat($topUps)->sortBy(fn (array $row) => $row['at']->getTimestamp())->values();
        $runningBalance = 0.0;

        foreach ($ledger as $row) {
            $runningBalance += $row['amount'];

            $this->backdate(CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $row['amount'],
                'balance_after' => $runningBalance,
                'type' => $row['type'],
                'description' => $row['description'],
                'meta' => ['demo_showcase' => true],
            ]), $row['at']);
        }

        $usedToday = (float) CreditTransaction::where('user_id', $user->id)
            ->where('type', 'usage')
            ->whereDate('created_at', now()->toDateString())
            ->sum(DB::raw('ABS(amount)'));

        $usedMonth = (float) CreditTransaction::where('user_id', $user->id)
            ->where('type', 'usage')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum(DB::raw('ABS(amount)'));

        // Purchased credits are tracked apart from the wallet: they are the user's own money
        // and must survive every plan renewal, which is what grantPlanAllowance() reserves
        // room for. Clamped to the balance because deductCredits() maintains exactly that
        // invariant — a tracked top-up larger than the wallet it lives in would reserve room
        // for credits that were already spent, handing out free ones each renewal.
        $topupBalance = min(
            (float) $ledger->where('type', 'topup')->sum('amount'),
            max(0.0, $runningBalance)
        );

        $user->forceFill([
            'credits' => $runningBalance,
            'topup_credits' => $topupBalance,
            'credits_used_today' => $usedToday,
            'credits_used_month' => $usedMonth,
        ])->save();
    }

    /**
     * Sign-in history for the showcase account. The dashboard's "Recent sign-ins" panel reads
     * it, and the block that seeds login_history further up only walks $demoUsers — which the
     * showcase account is not part of — so the panel was empty on the one account a buyer
     * signs into. The newest row matches the last_login_at / last_login_ip already on the user.
     */
    private function seedShowcaseLoginHistory(User $user): void
    {
        LoginHistory::where('user_id', $user->id)->delete();

        $places = [
            ['country' => 'United States', 'city' => 'New York', 'ip' => '203.76.120.45'],
            ['country' => 'United States', 'city' => 'Brooklyn', 'ip' => '203.76.120.88'],
            ['country' => 'United States', 'city' => 'Boston', 'ip' => '198.51.100.64'],
            ['country' => 'United Kingdom', 'city' => 'London', 'ip' => '198.51.100.31'],
            ['country' => 'Canada', 'city' => 'Toronto', 'ip' => '192.0.2.77'],
        ];

        // Newest first: an active account signs in most days, from a home city with the
        // occasional trip.
        $sessions = collect([$user->last_login_at ?? now()->subMinutes(18)])
            ->merge(collect([1, 2, 3, 5, 8, 12, 17, 23, 31, 44, 60, 82])
                ->map(fn (int $daysAgo) => now()->subDays($daysAgo)->setTime(mt_rand(8, 21), mt_rand(0, 59))));

        foreach ($sessions as $index => $signedInAt) {
            // The most recent sign-in must agree with the profile's last_login_ip.
            $place = $index === 0 ? $places[0] : $places[array_rand($places)];

            $this->backdate(LoginHistory::create([
                'user_id' => $user->id,
                'ip' => $index === 0 ? ($user->last_login_ip ?? $place['ip']) : $place['ip'],
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
                'country' => $place['country'],
                'city' => $place['city'],
                // One failed attempt in the history, so the security panel is not uniformly green.
                'success' => $index !== 6,
            ]), $signedInAt);
        }
    }

    /**
     * @return array<int, Document>
     */
    private function seedShowcaseDocuments(User $user, array $toolSlugs): array
    {
        $documents = [];
        $documentRows = [
            [
                'title' => 'Q3 AI Launch Campaign',
                'tool_slug' => $toolSlugs[0] ?? 'blog-article-generator',
                'content' => '<h1>Q3 AI Launch Campaign</h1><p>Position MakeAI as the fastest way for founders to turn ideas into launch-ready assets.</p><p>Focus on polished outputs, premium visuals, and speed-to-value for buyers.</p>',
                'days' => 3,
            ],
            [
                'title' => 'Premium Dashboard Value Pitch',
                'tool_slug' => $toolSlugs[1] ?? 'seo-meta-description-generator',
                'content' => '<h1>Premium Dashboard Value Pitch</h1><p>Lead with clarity, momentum, and polished analytics.</p><p>Showcase believable activity so buyers can imagine running a real SaaS from day one.</p>',
                'days' => 2,
            ],
            [
                'title' => 'Founder Outreach Sequence',
                'tool_slug' => $toolSlugs[2] ?? 'email-subject-line-generator',
                'content' => '<h1>Founder Outreach Sequence</h1><p>Use outcome-first messaging with short, confident calls to action.</p><p>Keep tone consultative and premium.</p>',
                'days' => 1,
            ],
            [
                'title' => 'Weekly Content Operations',
                'tool_slug' => $toolSlugs[3] ?? 'product-description-writer',
                'content' => '<h1>Weekly Content Operations</h1><p>Bundle blog, email, and social outputs into one repeatable workflow.</p><p>Measure production speed, approval rate, and content reuse.</p>',
                'days' => 0,
            ],
        ];

        foreach ($documentRows as $row) {
            $createdAt = now()->subDays($row['days'])->setTime(11 + $row['days'], 20);

            $document = Document::updateOrCreate(
                ['user_id' => $user->id, 'title' => $row['title']],
                [
                    'content' => $row['content'],
                    'tool_slug' => $row['tool_slug'],
                    'word_count' => Document::calculateWordCount($row['content']),
                ]
            );

            $document->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * The showcase account's own AI Chatbot workspace.
     *
     * The three flat conversations this replaces left every feature of the chat sidebar
     * empty — no projects, no tags, nothing pinned, nothing shared, no branch, no
     * attachment — so a buyer signing in as the demo user saw a stripped-down product.
     * This seeds the workspace of somebody who has genuinely been using it: chats in every
     * bucket the sidebar groups by (pinned / today / yesterday / last 7 days / older),
     * filed into projects, tagged, one shared by link, one branched from another, one
     * carrying a real uploaded file, and thumbs ratings on the answers.
     *
     * Everything here belongs to this one account, and the whole set is rebuilt on each
     * run, so a demo reset returns the workspace to exactly this state.
     */
    private function seedShowcaseChatbot(User $user): void
    {
        if (! function_exists('is_addon_active') || ! is_addon_active('ai-chatbot')) {
            return;
        }

        if (! class_exists(Conversation::class) || ! Schema::hasTable('conversations')) {
            return;
        }

        $this->resetShowcaseChatbot($user);

        // How the account answers "how should I write for you?" — the field is on the user,
        // and an empty one makes the custom-instructions panel look unfinished.
        $user->forceFill([
            'chat_custom_instructions' => "I run a two-person content studio for B2B SaaS clients.\n\nWrite in plain British English, no exclamation marks, no \"unlock\"/\"leverage\"/\"game-changing\". Lead with the specific claim and put the caveat after it, not before. When you give me options, rank them and say which you would pick.\n\nIf a request is missing something you actually need — the audience, the format, the length — ask once and then get on with it.",
        ])->save();

        $projects = $this->seedShowcaseChatProjects($user);
        $tags = $this->seedShowcaseChatTags($user);

        // Pricing comes from the live catalog so the per-message credit lines under each
        // answer match what these models would really have cost.
        $creditRates = Schema::hasTable('ai_models')
            ? DB::table('ai_models')->pluck('credits_per_1k', 'slug')->all()
            : [];

        $threads = $this->showcaseChatThreads($user);

        // Oldest first, so conversation ids ascend with time the way a real account's do —
        // and so a branch can reference the conversation it came from.
        usort($threads, fn (array $a, array $b) => $b['days'] <=> $a['days']);

        /** @var array<string, Conversation> $created */
        $created = [];
        /** @var array<string, array<int, ConversationMessage>> $createdMessages */
        $createdMessages = [];

        foreach ($threads as $thread) {
            $startedAt = now()->subDays($thread['days'])->setTime($thread['hour'], $thread['minute'] ?? 0);

            // A "today" slot scheduled for later this evening would be dated in the future.
            if ($startedAt->isFuture()) {
                $startedAt = now()->subMinutes(mt_rand(10, 120));
            }

            $model = $thread['model'];
            $creditsPer1k = (int) ($creditRates[$model] ?? 10);

            $conversation = Conversation::create([
                'user_id' => $user->id,
                'mode_slug' => $thread['mode'],
                'project_id' => $projects[$thread['project'] ?? '']->id ?? null,
                'title' => $thread['title'],
                'model' => $model,
                'total_tokens' => 0,
                'total_credits' => 0,
                'message_count' => 0,
                'last_message_at' => $startedAt,
                'is_pinned' => $thread['pinned'] ?? false,
                // A shared chat needs a token for /share/{token} to resolve — the sidebar's
                // "Shared" state and the public view both hang off it.
                'share_token' => ($thread['shared'] ?? false) ? (string) Str::ulid() : null,
            ]);

            $turns = $thread['turns'];

            // A branch replays its parent's opening exchanges before diverging, exactly as
            // ChatController::branch() copies them, so the two threads read as one decision
            // point explored twice.
            if (isset($thread['branch_of'])) {
                $parent = $created[$thread['branch_of']] ?? null;
                $parentMessages = $createdMessages[$thread['branch_of']] ?? [];
                $keep = ($thread['branch_after'] ?? 1) * 2;

                if ($parent && count($parentMessages) >= $keep) {
                    $conversation->fill([
                        'parent_conversation_id' => $parent->id,
                        'branch_point_message_id' => $parentMessages[$keep - 1]->id,
                    ])->save();

                    $replayed = array_slice($thread['replay'] ?? [], 0, $thread['branch_after'] ?? 1);
                    $turns = array_merge($replayed, $turns);
                }
            }

            $totalTokens = 0;
            $totalCredits = 0.0;
            $messageCount = 0;
            $lastMessageAt = $startedAt;
            $messages = [];

            foreach ($turns as $turnIndex => $turn) {
                $askedAt = $startedAt->copy()->addMinutes($turnIndex * mt_rand(3, 7));

                // Input grows with the thread: the whole history is re-sent every turn.
                $inputTokens = (int) ceil(mb_strlen($turn['q']) / 4) + 90 + ($turnIndex * 260);
                $outputTokens = (int) ceil(mb_strlen($turn['a']) / 4);
                // Same arithmetic as TokenGuard::calculateCredits().
                $credits = round(($inputTokens + $outputTokens) * ($creditsPer1k / 1000), 2);

                $question = ConversationMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $turn['q'],
                    'attachments' => isset($turn['attachment'])
                        ? [$this->seedShowcaseChatAttachment($user, $turn['attachment'])]
                        : null,
                ]);
                $question->forceFill(['created_at' => $askedAt])->save();
                $messages[] = $question;

                $answeredAt = $askedAt->copy()->addSeconds(mt_rand(25, 95));

                // Usage sits on the assistant row only, the way ChatController writes it —
                // which is also what the per-message token/credit line reads.
                $answer = ConversationMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $turn['a'],
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'credits_charged' => $credits,
                ]);
                $answer->forceFill(['created_at' => $answeredAt])->save();
                $messages[] = $answer;

                $totalTokens += $inputTokens + $outputTokens;
                $totalCredits += $credits;
                $messageCount += 2;
                $lastMessageAt = $answeredAt;

                if (isset($turn['rating']) && class_exists(ChatMessageFeedback::class) && Schema::hasTable('chat_message_feedback')) {
                    $feedback = ChatMessageFeedback::create([
                        'user_id' => $user->id,
                        'conversation_id' => $conversation->id,
                        'message_id' => $answer->id,
                        'rating' => $turn['rating'],
                        'comment' => $turn['comment'] ?? null,
                    ]);

                    $this->backdate($feedback, $answeredAt->copy()->addMinutes(mt_rand(1, 9)));
                }
            }

            $conversation->fill([
                'total_tokens' => $totalTokens,
                'total_credits' => round($totalCredits, 4),
                'message_count' => $messageCount,
                'last_message_at' => $lastMessageAt,
            ])->save();

            $this->backdate($conversation, $startedAt, $lastMessageAt);

            $tagIds = array_values(array_filter(array_map(
                fn (string $name) => $tags[$name]->id ?? null,
                $thread['tags'] ?? []
            )));

            if ($tagIds !== []) {
                $conversation->tags()->sync($tagIds);
            }

            $created[$thread['key']] = $conversation;
            $createdMessages[$thread['key']] = $messages;
        }
    }

    /**
     * Wipe the showcase account's chat workspace so a re-seed replaces it rather than
     * doubling it. Children first — SQLite only cascades when foreign keys are enabled on
     * the connection — and the uploaded files with them, since their ULID names are
     * regenerated on every run and would otherwise pile up in storage.
     */
    private function resetShowcaseChatbot(User $user): void
    {
        $conversationIds = Conversation::where('user_id', $user->id)->pluck('id')->all();

        if ($conversationIds !== []) {
            if (Schema::hasTable('chat_message_feedback')) {
                DB::table('chat_message_feedback')->whereIn('conversation_id', $conversationIds)->delete();
            }

            if (Schema::hasTable('conversation_tag')) {
                DB::table('conversation_tag')->whereIn('conversation_id', $conversationIds)->delete();
            }

            ConversationMessage::whereIn('conversation_id', $conversationIds)->delete();
            // Branches reference their parent, so clear the self-reference before deleting.
            Conversation::whereIn('id', $conversationIds)->update([
                'parent_conversation_id' => null,
                'branch_point_message_id' => null,
            ]);
            Conversation::whereIn('id', $conversationIds)->delete();
        }

        if (Schema::hasTable('conversation_tags')) {
            ConversationTag::where('user_id', $user->id)->delete();
        }

        if (Schema::hasTable('chat_projects')) {
            ChatProject::where('user_id', $user->id)->delete();
        }

        Storage::disk('local')->deleteDirectory("chat-attachments/{$user->id}");
    }

    /**
     * The folders in the sidebar's Projects section. Colours are the ones the project
     * picker offers, so nothing here is unreachable through the UI.
     *
     * @return array<string, ChatProject>
     */
    private function seedShowcaseChatProjects(User $user): array
    {
        if (! Schema::hasTable('chat_projects')) {
            return [];
        }

        $rows = [
            ['Q3 Content Engine', 'Pillar pages, comparison posts and the monthly newsletter.', '#6366F1', 34],
            ['Client — Northwind', 'Onboarding docs, call recaps and their quarterly report.', '#0EA5E9', 22],
            ['Product Launch', 'Everything for the March release: emails, social, landing copy.', '#F59E0B', 8],
        ];

        $projects = [];

        foreach ($rows as [$name, $description, $color, $daysAgo]) {
            $project = ChatProject::create([
                'user_id' => $user->id,
                'name' => $name,
                'description' => $description,
                'color_hex' => $color,
            ]);

            $this->backdate($project, now()->subDays($daysAgo)->setTime(9, 15));
            $projects[$name] = $project;
        }

        return $projects;
    }

    /**
     * @return array<string, ConversationTag>
     */
    private function seedShowcaseChatTags(User $user): array
    {
        if (! Schema::hasTable('conversation_tags')) {
            return [];
        }

        $rows = [
            ['Research', '#8B5CF6', 30],
            ['Draft', '#22C55E', 27],
            ['Client work', '#0EA5E9', 21],
            ['Needs review', '#F97316', 11],
        ];

        $tags = [];

        foreach ($rows as [$name, $color, $daysAgo]) {
            $tag = ConversationTag::create([
                'user_id' => $user->id,
                'name' => $name,
                'color' => $color,
            ]);

            $this->backdate($tag, now()->subDays($daysAgo)->setTime(10, 5));
            $tags[$name] = $tag;
        }

        return $tags;
    }

    /**
     * Write a real file for the one conversation that has an attachment, and return the
     * metadata row in the exact shape ChatAttachmentController::store() responds with.
     *
     * It has to be a real file on the local disk: preview() resolves the attachment by id
     * and serves it from `storage_path`, and ChatController re-reads the same path when the
     * thread is continued. A fabricated path would 404 the moment anyone clicked the chip.
     */
    private function seedShowcaseChatAttachment(User $user, array $attachment): array
    {
        $ulid = (string) Str::ulid();
        $path = "chat-attachments/{$user->id}/{$ulid}.{$attachment['extension']}";

        Storage::disk('local')->put($path, $attachment['content']);

        return [
            'id' => $ulid,
            'name' => $attachment['name'],
            'type' => $attachment['mime'],
            'size' => strlen($attachment['content']),
            'extension' => $attachment['extension'],
            'storage_path' => $path,
            // What the extractor would have pulled out of the file, which is what actually
            // gets sent to the model — for a CSV that is the file itself.
            'text_content' => $attachment['content'],
        ];
    }

    /**
     * The showcase account's conversations.
     *
     * Dated to land one in each group the sidebar builds (pinned / today / yesterday /
     * last 7 days / older) and written in the voice of the account's own custom
     * instructions above — a two-person B2B SaaS content studio — so the transcripts, the
     * documents and the tool history all read as the same person's work.
     */
    private function showcaseChatThreads(User $user): array
    {
        $attachmentCsv = "week,sent,open_rate,click_rate,replies\n"
            . "2026-05-04,1840,41.2%,6.8%,12\n"
            . "2026-05-11,1902,38.7%,5.1%,9\n"
            . "2026-05-18,1955,44.9%,9.3%,21\n"
            . "2026-05-25,1988,29.4%,3.2%,4\n"
            . "2026-06-01,2031,43.1%,8.7%,18\n";

        return [
            [
                'key' => 'brand-voice',
                'title' => 'Brand voice audit',
                'mode' => 'chat-write',
                'model' => 'claude-sonnet-4-6',
                'days' => 40,
                'hour' => 10,
                'tags' => ['Research'],
                'turns' => [
                    [
                        'q' => 'I have 30 published posts and no written voice guide. How do I work out what our voice actually is, rather than what I would like it to be?',
                        'a' => "Read the five posts that performed best and the five that fell flat, and note only the mechanical differences: sentence length, whether you open with a claim or a scene, how often you use \"we\", whether examples are named or generic.\n\nWhat you will almost certainly find is that your voice is already consistent and just undocumented. Write the guide from the evidence — \"we open with the claim\", \"we name the client or use no example at all\" — not from adjectives. A guide that says \"friendly but authoritative\" cannot be followed by anyone, including you.",
                        'rating' => 1,
                        'comment' => 'The "mechanical differences only" framing is what made this usable.',
                    ],
                    [
                        'q' => 'Turn that into six rules I can put in front of a freelancer.',
                        'a' => "1. Open with the claim. No scene-setting paragraph.\n2. One idea per paragraph, three sentences maximum.\n3. Name the company in every example, or use no example.\n4. Never \"unlock\", \"leverage\", \"game-changing\", \"in today's fast-paced\".\n5. Numbers beat adjectives: \"cut drafting to 40 minutes\", not \"dramatically faster\".\n6. End on the next action, not a summary of what was just read.\n\nSix is about the limit of what a freelancer will actually hold in their head. Anything else belongs in edit notes.",
                    ],
                ],
            ],
            [
                'key' => 'pricing-page',
                'title' => 'Pricing page rewrite',
                'mode' => 'chat-marketing',
                'model' => 'gpt-5.6-terra',
                'days' => 28,
                'hour' => 14,
                'project' => 'Q3 Content Engine',
                'tags' => ['Draft'],
                'turns' => [
                    [
                        'q' => 'Our pricing page has three tiers and a feature grid with 22 rows. People are bouncing. What do I cut?',
                        'a' => "Cut the grid to the six rows that differ between tiers. The other sixteen are in every plan, so all they do is make the reader work to find the ones that matter.\n\nThen put one sentence above each tier naming who it is for — \"for a solo founder publishing weekly\", \"for a studio with clients\". Most people cannot map a feature list onto themselves, but they recognise a description of their own situation immediately.",
                        'rating' => 1,
                    ],
                    [
                        'q' => 'Should the annual discount be shown as a percentage or as two months free?',
                        'a' => "Two months free. Same arithmetic, but it is a concrete thing rather than a rate the reader has to apply to a number they have not committed to yet.\n\nShow the monthly-equivalent price underneath in smaller text so anyone comparing against a competitor's monthly figure can do it without a calculator.",
                    ],
                ],
            ],
            [
                'key' => 'northwind-brief',
                'title' => 'Northwind onboarding brief',
                'mode' => 'chat-write',
                'model' => 'claude-sonnet-4-6',
                'days' => 21,
                'hour' => 11,
                'project' => 'Client — Northwind',
                'tags' => ['Client work'],
                'pinned' => true,
                'turns' => [
                    [
                        'q' => 'New client, mid-market logistics SaaS. Draft the structure of an onboarding brief I can send before our kickoff call.',
                        'a' => "Keep it to one page and make every section a question they answer, not a box you fill:\n\n1. **Who is the buyer, and who else has to agree?** — the second half is where B2B content usually goes wrong.\n2. **What are they doing today instead of buying you?** — the real competitor is almost never the named one.\n3. **Which three claims can you prove?** — with the proof attached.\n4. **What must we never say?** — legal, positioning, past mistakes.\n5. **Who signs off, and by when?** — one name.\n\nSend it 48 hours before the call so the answers arrive cold rather than being improvised live.",
                        'rating' => 1,
                    ],
                    [
                        'q' => 'They came back with nothing for question 3. How hard do I push?',
                        'a' => "Push once, in the call, by asking it differently: \"what is the last thing a customer told you that surprised you?\" People who cannot list provable claims can almost always retell a customer conversation.\n\nIf that also comes up empty, treat it as the finding rather than the blocker — it means the first piece of work is a customer interview, not a blog post, and you should quote it that way.",
                    ],
                ],
            ],
            [
                'key' => 'seo-cluster',
                'title' => 'SEO cluster for workflow templates',
                'mode' => 'chat-research',
                'model' => 'sonar-pro',
                'days' => 14,
                'hour' => 15,
                'project' => 'Q3 Content Engine',
                'tags' => ['Research'],
                'turns' => [
                    [
                        'q' => 'Plan a content cluster around AI workflow templates. We have no domain authority to speak of.',
                        'a' => "With no authority, skip the head term entirely for now.\n\nOne pillar page on \"AI workflow templates\" for later, then five comparison and five \"how do I actually\" pages targeting phrasings with obvious intent and thin competition — \"AI workflow for client onboarding\", \"content approval workflow template\". Those rank on relevance rather than links.\n\nInternally link every spoke to the pillar and to two siblings. The cluster is what eventually carries the head term; publishing the pillar first just gets it ignored.",
                        'rating' => 1,
                    ],
                    [
                        'q' => 'How long before I should expect to see anything?',
                        'a' => "Ten to sixteen weeks for the long-tail spokes to settle, longer for the pillar. Anything that moves inside three weeks is usually a temporary freshness boost, not a position — do not rewrite your plan on the strength of it.\n\nThe leading indicator worth watching earlier is impressions on the spoke queries. Impressions rising with poor position means the topic is right and the page is not.",
                    ],
                ],
            ],
            [
                'key' => 'newsletter-cadence',
                'title' => 'Newsletter cadence experiment',
                'mode' => 'chat-analyze',
                'model' => 'gemini-3.5-flash',
                'days' => 9,
                'hour' => 16,
                'turns' => [
                    [
                        'q' => 'We moved the newsletter from weekly to fortnightly. Opens went up 4 points, total clicks went down. Which number should I care about?',
                        'a' => "Total clicks, but not for the obvious reason.\n\nOpen rate rose because you sent to a list that had longer to forget it was tired of you — that is a denominator effect, not renewed interest. Total clicks fell because you halved the number of chances to be clicked.\n\nThe number that settles it is clicks per subscriber per month, and unsubscribes per send. If fortnightly holds clicks per subscriber steady while dropping unsubscribes, it is a genuine win. If clicks per subscriber fell too, you just published less.",
                        'rating' => 1,
                        'comment' => 'Reframing it as clicks per subscriber per month settled an argument we had been having for a fortnight.',
                    ],
                ],
            ],
            [
                'key' => 'launch-emails',
                'title' => 'Launch email sequence',
                'mode' => 'chat-write',
                'model' => 'gpt-5.6-terra',
                'days' => 6,
                'hour' => 9,
                'project' => 'Product Launch',
                'tags' => ['Draft', 'Needs review'],
                'turns' => [
                    [
                        'q' => "Here are last quarter's email results. Where should the launch sequence differ from what we have been sending?",
                        'a' => "Two things stand out in that file.\n\nThe 18 May send doubled every other week's replies at a similar volume, and the 25 May send collapsed on all three metrics — worth checking what those two had in common before writing anything new.\n\nFor the launch itself: your click rate lives between 3% and 9% on a stable list, so a four-email sequence is realistic and a seven-email one will burn goodwill you need for the follow-up. Announcement, one objection, one proof, one last call.",
                        'attachment' => [
                            'name' => 'email-performance-q2.csv',
                            'extension' => 'csv',
                            'mime' => 'text/csv',
                            'content' => $attachmentCsv,
                        ],
                        'rating' => 1,
                    ],
                    [
                        'q' => 'Write the objection email. The objection is "we already have a process for this".',
                        'a' => "> Subject: you already have a process\n>\n> You do. It works, it is in someone's head, and it survives contact with a busy week — mostly.\n>\n> The teams that switched were not replacing a broken process. They were replacing the part of a working one that only exists because one person remembers it: which draft is current, who still has to approve, what the client said in March.\n>\n> If that person is you, this is worth fifteen minutes. If it is someone else, forward it to them.\n\nGranting the objection in the first line is the whole trick. Arguing with it makes the reader defend a process they were mildly unhappy with.",
                    ],
                    [
                        'q' => 'Give me a subject line alternative that is less clever.',
                        'a' => "\"What happens when the person who remembers is on holiday.\"\n\nPlainer, same argument, and it survives being read in a notification preview — which is where most of these are actually judged.",
                    ],
                ],
            ],
            [
                'key' => 'competitor-scan',
                'title' => 'Competitor scan — Q3',
                'mode' => 'chat-research',
                'model' => 'gpt-5.6-terra',
                'days' => 5,
                'hour' => 13,
                'tags' => ['Research'],
                'turns' => [
                    [
                        'q' => 'Two competitors moved features from their top tier down to entry level this quarter. What does that usually mean?',
                        'a' => "Usually one of two things, and they look identical from outside.\n\nEither the feature stopped differentiating — someone cheaper shipped it, so holding it back only cost them entry-level conversions. Or their top tier needed room for something new and this was pushed down to make space.\n\nCheck whether the top tier gained anything in the same release. If it did, it is repositioning and you have a quarter or two. If it did not, the feature is now table stakes and you are already late.",
                        'rating' => -1,
                        'comment' => 'Useful frame but it did not tell me which of the two these actually were — I had to check myself.',
                    ],
                ],
            ],
            [
                'key' => 'northwind-recap',
                'title' => 'Northwind call recap → tasks',
                'mode' => 'chat-analyze',
                'model' => 'claude-sonnet-4-6',
                'days' => 3,
                'hour' => 17,
                'project' => 'Client — Northwind',
                'tags' => ['Client work'],
                'shared' => true,
                'turns' => [
                    [
                        'q' => 'Turn these call notes into owned tasks with dates: they want the quarterly report restructured, the case study is blocked on legal, and they asked about adding a second language.',
                        'a' => "**Us, this week** — restructure the quarterly report. Send the new outline for approval before writing, so the rewrite is not done twice.\n\n**Them, this week** — one named person to chase legal on the case study. A task assigned to \"legal\" is a task assigned to nobody; ask for the name in writing today.\n\n**Nobody, deliberately** — the second language. It is a scoping question, not a task, and turning it into one now commits you to an estimate you cannot make. Put it on the next agenda instead.\n\nThe third item is the one that matters. Vague asks become deadlines by accident when everything on a list looks the same.",
                        'rating' => 1,
                    ],
                    [
                        'q' => 'They will push on the language question. Give me a one-line holding answer.',
                        'a' => "\"Happy to scope it — the answer depends on whether you need the existing 40 pages translated or only new work, and those are very different projects. Can we take fifteen minutes on it next week?\"\n\nIt is a real question rather than a stall, which is why it holds.",
                    ],
                ],
            ],
            [
                'key' => 'social-repurpose',
                'title' => 'Repurpose the launch post',
                'mode' => 'chat-social',
                'model' => 'gpt-5.4-mini',
                'days' => 1,
                'hour' => 11,
                'project' => 'Product Launch',
                'turns' => [
                    [
                        'q' => 'Cut the launch blog post into a LinkedIn post and three shorter follow-ups.',
                        'a' => "Main post: the number that made you build it, then what you did about it, then one line on what is still unsolved. Admitting the unsolved part is what stops it reading as an advert.\n\nFollow-ups, spread over two weeks:\n1. The objection you heard most in beta, answered honestly.\n2. One screenshot with the reasoning behind a single design decision.\n3. A customer's own sentence, quoted, with no commentary from you.\n\nDo not post them on consecutive days — the third lands on people who have not seen the first.",
                    ],
                ],
            ],
            [
                'key' => 'ad-hooks',
                'title' => 'Ad hooks for the retargeting set',
                'mode' => 'chat-marketing',
                'model' => 'claude-haiku-4-5',
                'days' => 1,
                'hour' => 15,
                'tags' => ['Draft'],
                'turns' => [
                    [
                        'q' => 'Five retargeting hooks for people who read the pricing page and left.',
                        'a' => "1. \"You looked at the pricing. Here is what the middle tier actually includes.\"\n2. \"Cheaper than the freelancer you were about to email.\"\n3. \"Two months free if you pay yearly. That is the whole offer.\"\n4. \"Still comparing? Here is the honest version of us versus them.\"\n5. \"Forty minutes per article, or your month back.\"\n\nNumber 4 will outperform the rest and make you uncomfortable, which is usually the same thing. Number 2 only works if it is true — check your actual rates before running it.",
                    ],
                ],
            ],
            [
                'key' => 'hero-rewrite',
                'title' => 'Homepage hero rewrite',
                'mode' => 'chat-marketing',
                'model' => 'gpt-5.6-terra',
                'days' => 0,
                'hour' => 9,
                'minute' => 20,
                'project' => 'Q3 Content Engine',
                'tags' => ['Needs review'],
                'pinned' => true,
                'turns' => [
                    [
                        'q' => 'Our hero reads "The Future of Content, Powered by AI". Give me something a visitor can test in three seconds.',
                        'a' => "\"Publish a researched, on-brand article in under an hour — without hiring another writer.\"\n\nThe current line describes a category. This one makes a claim the reader can check against their own week, which is the only thing a hero has time to do.\n\nPut the proof directly beneath it rather than three sections down: one customer sentence, one number, nothing else.",
                        'rating' => 1,
                    ],
                    [
                        'q' => 'The founder thinks it undersells the AI part. Push back or fold?',
                        'a' => "Push back, but not on taste — on evidence.\n\nEveryone in the category leads with AI, so leading with it makes you indistinguishable from the eleven other tabs the visitor has open. The outcome is what differs. Offer to run both as an A/B test on the hero alone and let the number decide; that usually ends the argument faster than the argument does.\n\nIf they still want AI in the hero, put it in the subhead where it costs nothing.",
                    ],
                ],
            ],
            [
                'key' => 'hero-rewrite-branch',
                'title' => 'Homepage hero rewrite (branch)',
                'mode' => 'chat-marketing',
                'model' => 'claude-sonnet-4-6',
                'days' => 0,
                'hour' => 10,
                'minute' => 5,
                'project' => 'Q3 Content Engine',
                'branch_of' => 'hero-rewrite',
                'branch_after' => 1,
                // Replayed from the parent up to the branch point, exactly as the branch
                // endpoint copies them, before this thread takes a different turn.
                'replay' => [
                    [
                        'q' => 'Our hero reads "The Future of Content, Powered by AI". Give me something a visitor can test in three seconds.',
                        'a' => "\"Publish a researched, on-brand article in under an hour — without hiring another writer.\"\n\nThe current line describes a category. This one makes a claim the reader can check against their own week, which is the only thing a hero has time to do.\n\nPut the proof directly beneath it rather than three sections down: one customer sentence, one number, nothing else.",
                    ],
                ],
                'turns' => [
                    [
                        'q' => 'Different direction from here: keep the category framing but make it specific. What would that look like?',
                        'a' => "\"The content workflow for studios that publish every week.\"\n\nStill a category line, but a category of one — it names the cadence and the buyer instead of the technology. A founder who wants the positioning-led hero can usually live with this, because it sounds like a claim about the market rather than a claim about a feature.\n\nIt will convert slightly worse than the outcome line and considerably better than what is up there now.",
                    ],
                ],
            ],
            [
                'key' => 'onboarding-email',
                'title' => 'Onboarding email #2',
                'mode' => 'chat-write',
                'model' => 'claude-sonnet-4-6',
                'days' => 0,
                'hour' => 12,
                'minute' => 40,
                'turns' => [
                    [
                        'q' => 'Second onboarding email. They signed up, they have not generated anything yet. What is the job of this email?',
                        'a' => "One job: get them to produce a single output, however small.\n\nSo do not summarise the product. Pick the shortest useful thing they can finish in two minutes — a meta description, a subject line — and make the whole email about that one action, with the link going straight into it rather than to the dashboard.\n\nThe account that generates once in week one is a different retention curve from the account that reads three emails and never does. Everything else you might say fits better in email four.",
                        'rating' => 1,
                    ],
                ],
            ],
        ];
    }

    /**
     * A continuous usage timeline for the showcase account's Usage dashboard.
     *
     * The six fixed rows this replaces sat at 10/8/6/4/3/1 days back, so the page's 1D view
     * was empty, 7D had holes and 1Y showed a single month — the same gap the affiliate chart
     * had. Every bucket the chart can ask for now has generations behind it: hours across
     * today (1D, including the current hour), each of the last seven days (7D), every day of
     * the last month (1M) and every month of the last year (1Y).
     *
     * The tool mix is weighted rather than uniform so "Top tools" ranks something real, and
     * the hour-of-day spread is deliberately front-loaded so "Peak hour" and "Most active
     * day" have an answer instead of a dash.
     */
    private function seedShowcaseUsageLogs(User $user, array $toolSlugs): void
    {
        // Re-seeding resets the account's history, the same way the support inbox does, so a
        // demo visitor's own generations never accumulate across resets.
        AiUsageLog::where('user_id', $user->id)->delete();
        GenerationHistory::where('user_id', $user->id)->delete();

        $engines = [
            ['provider' => 'openai', 'model' => 'gpt-5.6-terra'],
            ['provider' => 'openai', 'model' => 'gpt-5.4-mini'],
            ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-6'],
            ['provider' => 'google', 'model' => 'gemini-3.5-flash'],
            ['provider' => 'deepseek', 'model' => 'deepseek-v4-pro'],
            ['provider' => 'meta', 'model' => 'llama-3.3-70b-versatile'],
        ];

        $fallbackSlugs = ['blog-article-generator', 'seo-meta-description-generator',
            'email-subject-line-generator', 'product-description-writer', 'ad-copy-generator',
            'social-media-post-generator'];
        $slugs = array_values(array_slice($toolSlugs !== [] ? $toolSlugs : $fallbackSlugs, 0, 8));

        // Weighted draw: the first slugs are this creator's daily drivers, the tail is
        // occasional. A uniform pick would make every tool tie in the Top tools panel.
        $weightedSlugs = [];
        foreach ($slugs as $position => $slug) {
            foreach (range(0, max(0, 8 - ($position * 2))) as $ignored) {
                $weightedSlugs[] = $slug;
            }
        }

        $moments = collect();

        // Today across the whole working day, plus one inside the current hour so the 1D
        // chart's current bucket is never the empty one.
        //
        // This loop used to start at 08:00 and stop at now()->hour, which meant a
        // demo:reset before 08:00 produced exactly ONE row for today against 2-5 for
        // yesterday — the creator dashboard then opened on a drop every single morning.
        foreach ($this->spreadOverDay(0, $this->dailyVolume(0, 0.8), 8, 21) as $moment) {
            $moments->push($moment);
        }
        $moments->push(now()->startOfHour()->addMinutes(mt_rand(0, (int) now()->minute)));

        // Every day back to 90, which is the widest daily range anything asks for (the user
        // dashboard's own credit chart offers 7d / this month / 90d). Volume ramps with
        // recency so the account reads as one that has been getting busier at every range,
        // not just against the 30-day boundary the old two-step taper happened to fall on.
        for ($day = 1; $day <= 179; $day++) {
            foreach ($this->spreadOverDay($day, $this->dailyVolume($day, 0.8), 8, 21) as $moment) {
                $moments->push($moment);
            }
        }

        // Months 6-11 back, filling in behind the daily loop.
        //
        // This started at 7, on the assumption that 180 days covers everything nearer. It
        // does not: 179 days is a little under six months, so where the daily loop stops
        // depends on what day of the month it is. Run it late in a month — 30 July reaches
        // back only to 1 February — and month 6 back is left with nothing at all in it,
        // which is a hole in the MIDDLE of the 1Y chart rather than at either end. Run it
        // on the 10th and the daily loop reaches into that month and the gap closes on its
        // own, which is what kept this hidden.
        //
        // Six is always safe: subMonths(6) is 181-184 days back, so that month always
        // starts before the daily loop's reach. Where the two overlap the month simply
        // gets a few more generations, which costs nothing.
        for ($monthsAgo = 6; $monthsAgo <= 11; $monthsAgo++) {
            $monthStart = now()->subMonths($monthsAgo)->startOfMonth();
            foreach (range(1, mt_rand(6, 12)) as $ignored) {
                $moments->push($monthStart->copy()->addDays(mt_rand(0, $monthStart->daysInMonth - 1))->setTime(mt_rand(8, 21), mt_rand(0, 59)));
            }
        }

        $moments = $moments->sort()->values();

        $historyPreviews = [
            'Five subject lines built around the launch offer, ordered by expected open rate.',
            'A 1,200-word draft covering the problem, the workflow, and a closing CTA.',
            'Three ad variants: one benefit-led, one urgency-led, one social-proof-led.',
            'Product copy rewritten in the saved brand voice, with a shorter feature list.',
            'A LinkedIn post plus two comment replies to keep the thread going.',
            'Meta description under 155 characters with the primary keyword kept in front.',
            'An outreach sequence of four emails, each with a single ask.',
            'Landing page hero, subhead, and three benefit blocks in the brand voice.',
        ];

        foreach ($moments as $index => $createdAt) {
            $engine = $engines[$index % count($engines)];
            $toolSlug = $weightedSlugs[array_rand($weightedSlugs)];
            $inputTokens = mt_rand(320, 2600);
            $outputTokens = mt_rand(180, 1500);
            // Kept small enough that a year of generations stays plausible against the
            // credit ledger seeded in seedShowcaseCreditTimeline().
            $credits = mt_rand(8, 45);

            $this->backdate(AiUsageLog::create([
                'user_id' => $user->id,
                'provider' => $engine['provider'],
                'model' => $engine['model'],
                'type' => 'text_generation',
                'tool_slug' => $toolSlug,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round(($inputTokens * 0.00001) + ($outputTokens * 0.00002), 6),
                'credits_used' => $credits,
                'response_time_ms' => mt_rand(900, 6500),
                // One in ~25 fails, so the account has a realistic error or two rather than a
                // spotless record.
                'status' => mt_rand(1, 25) === 1 ? 'failed' : 'completed',
                'metadata' => ['demo_showcase' => true],
            ]), $createdAt);

            // "Recent activity" on the Usage page and the whole History page read
            // generation_history, which nothing seeded — both were empty. Only the last
            // fortnight needs rows; the lists show at most a handful.
            if ($createdAt->lt(now()->subDays(14))) {
                continue;
            }

            GenerationHistory::create([
                'user_id' => $user->id,
                'tool_slug' => $toolSlug,
                'document_id' => null,
                'prompt_system' => 'You are a marketing copywriter. Follow the saved brand voice.',
                'prompt_user' => 'Draft copy for the current campaign using the details provided.',
                'field_values' => ['tone' => 'confident', 'audience' => 'SaaS founders'],
                'model' => $engine['model'],
                'provider' => $engine['provider'],
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'output_preview' => $historyPreviews[$index % count($historyPreviews)],
                'tokens_input' => $inputTokens,
                'tokens_output' => $outputTokens,
                'is_favorited' => $index % 7 === 0,
                'label' => null,
                'created_at' => $createdAt,
            ]);
        }

        // credits_used_today / credits_used_month are set by seedShowcaseCreditTimeline(),
        // which runs next and mirrors these same generations into the ledger — one source,
        // so the dashboard tiles, the Usage tiles and both charts cannot disagree.

        // The Usage page caches its whole stats payload for 5 minutes. A real generation
        // busts that key (GenerationHistoryService::record, TokenGuard), but a seeder writes
        // the rows straight to the database, so after demo:reset the page kept serving the
        // PREVIOUS dataset — stale generation counts and a recent-activity list from before
        // the reset — until the entry aged out.
        Cache::forget("usage_stats_{$user->id}");
    }

    /**
     * Payment history for the showcase account's Billing page.
     *
     * The account had an active $499.99 yearly subscription and a credit ledger full of
     * purchases, but ZERO payment rows — so "Payment History" was empty on the one account a
     * buyer signs into, and the invoices did not exist to back either story.
     *
     * The dates reconcile with what is already seeded rather than inventing a parallel
     * history: the two annual charges bracket the GatewaySubscription's period (bought 11
     * months ago, renewed 1 month ago for the term ending in 11), and the top-up matches the
     * "Mid-year credit top-up" row in seedShowcaseCreditTimeline().
     */
    private function seedShowcasePayments(User $user, Plan $plan): void
    {
        $rows = [
            [
                'id' => 'demo-showcase-pay-001',
                'months' => 11,
                'amount' => 499.99,
                'type' => 'subscription',
                'status' => 'completed',
                'gateway' => 'stripe',
                'plan' => true,
            ],
            [
                'id' => 'demo-showcase-pay-002',
                'months' => 8,
                'amount' => 29.00,
                'type' => 'one_time',
                'status' => 'completed',
                'gateway' => 'stripe',
                'plan' => false,
            ],
            [
                // Mirrors the 6,000-credit "Mid-year credit top-up" in the credit ledger.
                'id' => 'demo-showcase-pay-003',
                'months' => 5,
                'amount' => 49.00,
                'type' => 'credit_topup',
                'status' => 'completed',
                'gateway' => 'stripe',
                'plan' => false,
            ],
            [
                // A card that expired before the renewal went through — the retry below is
                // the one that succeeded, so the Billing page shows a real failure/recovery.
                'id' => 'demo-showcase-pay-004',
                'months' => 3,
                'amount' => 19.99,
                'type' => 'subscription',
                'status' => 'failed',
                'gateway' => 'stripe',
                'plan' => true,
            ],
            [
                'id' => 'demo-showcase-pay-005',
                'months' => 2,
                'amount' => 15.00,
                'type' => 'credit_topup',
                'status' => 'refunded',
                'gateway' => 'paypal',
                'plan' => false,
            ],
            [
                // The renewal that matches the active GatewaySubscription row.
                'id' => 'demo-showcase-pay-006',
                'months' => 1,
                'amount' => 499.99,
                'type' => 'subscription',
                'status' => 'completed',
                'gateway' => 'stripe',
                'plan' => true,
            ],
            [
                // Still settling, so the page shows the pending state too.
                'id' => 'demo-showcase-pay-007',
                'months' => 0,
                'amount' => 25.00,
                'type' => 'credit_topup',
                'status' => 'pending',
                'gateway' => 'bank_transfer',
                'plan' => false,
            ],
            [
                // The purchase behind the 2,000-credit 'topup' row in the credit ledger, and
                // the reason users.topup_credits is non-zero. Dated in days rather than
                // months so it stays inside the windows the recent-activity panels read.
                'id' => 'demo-showcase-pay-008',
                'days' => 4,
                'amount' => 19.00,
                'type' => 'credit_topup',
                'status' => 'completed',
                'gateway' => 'stripe',
                'plan' => false,
            ],
        ];

        foreach ($rows as $row) {
            // Most of this history is spaced in months; the recent top-up needs day
            // precision, so a row may carry 'days' instead. First matching arm wins, so
            // 'months' is never read for a row that does not define it.
            $paidAt = match (true) {
                isset($row['days']) => now()->subDays($row['days'])->setTime(13, 5),
                $row['months'] === 0 => now()->subHours(6),
                default => now()->subMonths($row['months'])->startOfDay()->addHours(10),
            };

            $this->backdate(Payment::updateOrCreate(
                ['gateway_payment_id' => $row['id']],
                [
                    'user_id' => $user->id,
                    'plan_id' => $row['plan'] ? $plan->id : null,
                    'gateway' => $row['gateway'],
                    'amount' => $row['amount'],
                    'currency' => 'USD',
                    'status' => $row['status'],
                    'type' => $row['type'],
                    'metadata' => ['demo_showcase' => true],
                ]
            ), $paidAt);
        }
    }

    /**
     * Tool chains and their run history for the showcase account.
     *
     * /chains opened on an empty state, so the feature looked unbuilt. Steps use the shape
     * ChainController::normalizeSteps() writes — step number, tool_slug, static_inputs and
     * field_map with the {{input}} / {{previous_output}} templating — so a seeded chain opens
     * correctly in the builder and could actually be run.
     */
    private function seedShowcaseChains(User $user, array $toolSlugs): void
    {
        ToolChainRun::where('user_id', $user->id)->delete();
        ToolChain::where('user_id', $user->id)->delete();

        $slug = fn (int $index, string $fallback) => $toolSlugs[$index] ?? $fallback;

        $chains = [
            [
                'name' => 'Blog post → meta description',
                'runs' => 34,
                'last_run_hours' => 5,
                'steps' => [
                    ['tool_slug' => $slug(0, 'blog-article-generator'), 'static_inputs' => ['tone' => 'confident'], 'field_map' => ['topic' => '{{input}}']],
                    ['tool_slug' => $slug(1, 'seo-meta-description-generator'), 'static_inputs' => [], 'field_map' => ['content' => '{{previous_output}}']],
                ],
            ],
            [
                'name' => 'Product launch kit',
                'runs' => 12,
                'last_run_hours' => 30,
                'steps' => [
                    ['tool_slug' => $slug(3, 'product-description-writer'), 'static_inputs' => ['audience' => 'SaaS founders'], 'field_map' => ['product' => '{{input}}']],
                    ['tool_slug' => $slug(4, 'ad-copy-generator'), 'static_inputs' => ['platform' => 'LinkedIn'], 'field_map' => ['product' => '{{step_1_output}}']],
                    ['tool_slug' => $slug(5, 'social-media-post-generator'), 'static_inputs' => [], 'field_map' => ['topic' => '{{previous_output}}']],
                ],
            ],
            [
                'name' => 'Weekly newsletter assembly',
                'runs' => 3,
                'last_run_hours' => 6 * 24,
                'steps' => [
                    ['tool_slug' => $slug(2, 'email-subject-line-generator'), 'static_inputs' => [], 'field_map' => ['topic' => '{{input}}']],
                    ['tool_slug' => $slug(0, 'blog-article-generator'), 'static_inputs' => ['tone' => 'conversational'], 'field_map' => ['topic' => '{{input}}']],
                ],
            ],
        ];

        $created = [];

        foreach ($chains as $row) {
            $lastRunAt = now()->subHours($row['last_run_hours']);
            $steps = [];

            foreach ($row['steps'] as $index => $step) {
                $steps[] = [...$step, 'step' => $index + 1];
            }

            $chain = ToolChain::create([
                'user_id' => $user->id,
                'name' => $row['name'],
                'steps' => $steps,
                'last_run_at' => $lastRunAt,
                'run_count' => $row['runs'],
            ]);

            // The list is ordered by updated_at, so the most recently run chain sits on top.
            $this->backdate($chain, $lastRunAt->copy()->subDays($row['runs'] > 20 ? 40 : 18), $lastRunAt);

            $created[$chain->name] = $chain;
        }

        // Recent runs — one of each state, so the status pills, the error row and the
        // expandable step output all have something to show.
        $runs = [
            [
                'chain' => 'Blog post → meta description',
                'status' => 'completed',
                'hours' => 5,
                'input' => 'How AI writing assistants change agency retainers',
                'tokens' => 2140,
                'credits' => 38,
                'error' => null,
                'outputs' => [
                    ['step' => 1, 'tokens' => 1480, 'credits' => 26, 'output' => "How AI Writing Assistants Are Changing Agency Retainers\n\nRetainers used to be priced on hours. When a first draft takes twenty minutes instead of a morning, that maths stops working — and the agencies winning right now have already repriced around outcomes rather than time..."],
                    ['step' => 2, 'tokens' => 660, 'credits' => 12, 'output' => 'Agency retainers are being repriced around outcomes, not hours. See how AI drafting changes scoping, margins and what clients actually pay for.'],
                ],
            ],
            [
                'chain' => 'Product launch kit',
                'status' => 'completed',
                'hours' => 30,
                'input' => 'Scheduled publishing for generated social captions',
                'tokens' => 3980,
                'credits' => 71,
                'error' => null,
                'outputs' => [
                    ['step' => 1, 'tokens' => 1620, 'credits' => 29, 'output' => "Queue every caption you generate and let them publish themselves. Scheduled publishing turns a folder of drafts into a filled week — pick the slot, approve the copy, and move on to the next client."],
                    ['step' => 2, 'tokens' => 1180, 'credits' => 21, 'output' => "Stop pasting captions into three tools.\nGenerate, queue, publish — one workflow, one tab.\nBuilt for the people who run five brands before lunch."],
                    ['step' => 3, 'tokens' => 1180, 'credits' => 21, 'output' => "New: schedule the captions you just generated, without leaving the editor.\n\nPick a slot, approve the copy, done. Your week fills itself.\n\n#marketing #ai #socialmedia"],
                ],
            ],
            [
                'chain' => 'Blog post → meta description',
                'status' => 'failed',
                'hours' => 52,
                'input' => 'Q4 reporting checklist for marketing teams',
                'tokens' => 1490,
                'credits' => 26,
                'error' => 'Step 2 failed: the provider returned a rate-limit error. Credits for the completed step were not refunded.',
                'outputs' => [
                    ['step' => 1, 'tokens' => 1490, 'credits' => 26, 'output' => "The Q4 Reporting Checklist for Marketing Teams\n\nClose the quarter without the usual scramble: agree the numbers that matter in week one, automate the pulls, and keep a running narrative so the deck writes itself..."],
                ],
            ],
            [
                'chain' => 'Weekly newsletter assembly',
                'status' => 'completed',
                'hours' => 6 * 24,
                'input' => 'Issue 42 — what shipped this month',
                'tokens' => 1860,
                'credits' => 33,
                'error' => null,
                'outputs' => [
                    ['step' => 1, 'tokens' => 420, 'credits' => 7, 'output' => "1. Everything we shipped this month (in 4 minutes)\n2. Issue 42: the features you asked for\n3. Your month in one email"],
                    ['step' => 2, 'tokens' => 1440, 'credits' => 26, 'output' => "This month went to the unglamorous work: exports that finish, a queue that keeps your brand voice from step one to step nine, and a chain builder that no longer forgets its field mapping..."],
                ],
            ],
        ];

        foreach ($runs as $row) {
            $chain = $created[$row['chain']] ?? null;

            if (! $chain) {
                continue;
            }

            $startedAt = now()->subHours($row['hours']);
            $completedAt = $startedAt->copy()->addSeconds(mt_rand(25, 90));

            // Fill each step record with the tool it actually ran, straight off the chain.
            $stepOutputs = [];

            foreach ($row['outputs'] as $index => $output) {
                $stepOutputs[] = [
                    ...$output,
                    'tool_slug' => $chain->steps[$index]['tool_slug'] ?? $slug(0, 'blog-article-generator'),
                ];
            }

            $this->backdate(ToolChainRun::create([
                'chain_id' => $chain->id,
                'user_id' => $user->id,
                'status' => $row['status'],
                'input' => $row['input'],
                'step_outputs' => $stepOutputs,
                'total_tokens' => $row['tokens'],
                'total_credits' => $row['credits'],
                'error' => $row['error'],
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ]), $startedAt, $completedAt);
        }
    }

    /**
     * Embeddable tool widgets for the showcase account — /tool-embeds was another empty
     * screen. Covers the states the page renders differently: a live embed locked to two
     * origins with branding off, a password-protected one, and a paused one.
     */
    private function seedShowcaseToolEmbeds(User $user, array $toolSlugs): void
    {
        ToolEmbed::where('user_id', $user->id)->delete();

        $embeds = [
            [
                'tool_slug' => $toolSlugs[0] ?? 'blog-article-generator',
                'label' => 'Blog widget — marketing site',
                'allowed_origins' => ['https://demo-creator-studio.example', 'https://www.demo-creator-studio.example'],
                'password' => null,
                'theme' => 'auto',
                'primary_color' => '#1f75fe',
                'show_branding' => false,
                'usage_count' => 1284,
                'last_used_hours' => 2,
                'is_active' => true,
                'age_days' => 96,
            ],
            [
                'tool_slug' => $toolSlugs[1] ?? 'seo-meta-description-generator',
                'label' => 'Client portal — SEO helper',
                // Password-gated: the embed asks for it before it will run.
                'password' => 'demo-embed-password',
                'allowed_origins' => ['https://portal.demo-creator-studio.example'],
                'theme' => 'light',
                'primary_color' => '#8b5cf6',
                'show_branding' => true,
                'usage_count' => 317,
                'last_used_hours' => 26,
                'is_active' => true,
                'age_days' => 54,
            ],
            [
                'tool_slug' => $toolSlugs[4] ?? 'ad-copy-generator',
                'label' => 'Campaign landing page (paused)',
                'allowed_origins' => null,
                'password' => null,
                'theme' => 'dark',
                'primary_color' => null,
                'show_branding' => true,
                'usage_count' => 63,
                'last_used_hours' => 21 * 24,
                'is_active' => false,
                'age_days' => 25,
            ],
        ];

        foreach ($embeds as $index => $row) {
            $createdAt = now()->subDays($row['age_days']);
            $lastUsedAt = now()->subHours($row['last_used_hours']);

            $embed = ToolEmbed::create([
                'user_id' => $user->id,
                'tool_slug' => $row['tool_slug'],
                // Fixed tokens keep the embed snippet stable across reseeds, so a demo page
                // pointed at one of these keeps working.
                'token' => 'demoembed' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . Str::padRight('', 53, 'x'),
                'label' => $row['label'],
                'allowed_origins' => $row['allowed_origins'],
                'password_hash' => $row['password'] ? bcrypt($row['password']) : null,
                'theme' => $row['theme'],
                'primary_color' => $row['primary_color'],
                'show_branding' => $row['show_branding'],
                'usage_count' => $row['usage_count'],
                'last_used_at' => $lastUsedAt,
                'is_active' => $row['is_active'],
            ]);

            // The list orders by updated_at, so the busiest embed stays on top.
            $this->backdate($embed, $createdAt, $lastUsedAt);
        }
    }

    private function seedShowcaseCollections(User $user, array $toolSlugs): void
    {
        $collectionRows = [
            [
                'name' => 'Launch Campaigns',
                'description' => 'Go-to tools for landing pages, ads, and launch copy.',
                'icon' => 'ti ti-rocket',
                'color' => '#1f75fe',
                'is_featured' => true,
                'tools' => array_slice($toolSlugs, 0, 3),
            ],
            [
                'name' => 'Daily Client Ops',
                'description' => 'Templates for repeatable client work and content delivery.',
                'icon' => 'ti ti-briefcase',
                'color' => '#8b5cf6',
                'is_featured' => false,
                'tools' => array_slice($toolSlugs, 3, 3),
            ],
        ];

        foreach ($collectionRows as $sortOrder => $row) {
            $collection = UserCollection::updateOrCreate(
                ['user_id' => $user->id, 'name' => $row['name']],
                [
                    'description' => $row['description'],
                    'icon' => $row['icon'],
                    'color' => $row['color'],
                    'is_featured' => $row['is_featured'],
                    'sort_order' => $sortOrder,
                ]
            );

            UserCollectionTool::where('collection_id', $collection->id)->delete();

            foreach (array_values(array_filter($row['tools'])) as $index => $toolSlug) {
                UserCollectionTool::create([
                    'collection_id' => $collection->id,
                    'tool_slug' => $toolSlug,
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * @param  array<int, Document>  $documents
     */
    private function seedShowcaseFavorites(User $user, array $documents, array $toolSlugs): void
    {
        $favoriteToolIds = AiTool::query()
            ->whereIn('slug', array_slice($toolSlugs, 0, 3))
            ->pluck('id')
            ->all();

        foreach ($favoriteToolIds as $toolId) {
            Favorite::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'favoriteable_type' => AiTool::class,
                    'favoriteable_id' => $toolId,
                ],
                []
            );
        }

        foreach (array_slice($documents, 0, 2) as $document) {
            Favorite::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'favoriteable_type' => Document::class,
                    'favoriteable_id' => $document->id,
                ],
                []
            );
        }
    }

    private function loremParagraph(): string
    {
        $sentences = [
            'Artificial intelligence is transforming the way we create, communicate, and solve problems.',
            'The rapid advancement of machine learning models has opened new possibilities for automation and creativity.',
            'Businesses are increasingly adopting AI-powered tools to streamline their workflows and boost productivity.',
            'Content creation has never been easier with the latest natural language processing technologies.',
            'Developers can now integrate sophisticated AI capabilities into their applications with minimal effort.',
            'The future of technology lies in the seamless collaboration between humans and intelligent systems.',
            'Data-driven insights are helping companies make better decisions faster than ever before.',
            'Modern AI models can understand context, nuance, and even creative intent with remarkable accuracy.',
        ];

        return implode(' ', [
            $sentences[array_rand($sentences)],
            $sentences[array_rand($sentences)],
            $sentences[array_rand($sentences)],
        ]);
    }

    private function loremSentence(): string
    {
        $sentences = [
            'Discover how AI can revolutionize your daily workflow.',
            'Learn the best practices for implementing machine learning solutions.',
            'Explore cutting-edge techniques in natural language processing.',
            'Unlock the full potential of generative AI for your business.',
            'Stay ahead with the latest trends in artificial intelligence.',
            'Transform your content strategy with intelligent automation.',
        ];

        return $sentences[array_rand($sentences)];
    }
}
