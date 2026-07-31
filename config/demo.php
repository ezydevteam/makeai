<?php

/*
|--------------------------------------------------------------------------
| Demo Mode Configuration
|--------------------------------------------------------------------------
|
| These settings are controlled by the MakeAI seller authority via .env
| variables. Site admins cannot toggle demo mode — it is a system-level
| control for Envato marketplace preview and buyer confidence.
|
| Edit your .env file directly:
|   DEMO_ENABLED=true
|   DEMO_BANNER_COLOR=amber
|   DEMO_ENVATO_URL=https://codecanyon.net/item/...
|   DEMO_ADMIN_EMAIL=admin@demo.com
|   DEMO_ADMIN_PASSWORD=<choose one>
|   DEMO_USER_EMAIL=demo@demo.com
|   DEMO_USER_PASSWORD=<choose one>
|
| The passwords have NO default on purpose. They are published on the sign-in
| page whenever demo mode is on, so a shipped default would be a known admin
| password on every demo site that forgot to set one. Unset means: credentials
| are not displayed, and DemoSeeder refuses to create the accounts.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode Toggle
    |--------------------------------------------------------------------------
    |
    | When enabled, destructive write operations (POST/PUT/PATCH/DELETE) are
    | blocked for all routes except AI generation, auth, and preferences.
    |
    */

    'enabled' => (bool) env('DEMO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Demo Banner
    |--------------------------------------------------------------------------
    |
    | A sticky, dismissible-per-session banner is shown on all layouts when
    | demo mode is active. Configure color and purchase link here.
    |
    */

    'banner_color' => env('DEMO_BANNER_COLOR', 'amber'),

    'envato_url' => env('DEMO_ENVATO_URL', 'https://codecanyon.net'),

    /*
    |--------------------------------------------------------------------------
    | Demo Credentials
    |--------------------------------------------------------------------------
    |
    | Displayed on the login and registration pages when demo mode is active.
    | DemoSeeder creates exactly these accounts, so the two stay in step.
    |
    | Emails keep a default (they are not secret). Passwords deliberately do
    | not — see the note at the top of this file.
    |
    */

    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@demo.com'),

    'admin_password' => env('DEMO_ADMIN_PASSWORD'),

    'user_email' => env('DEMO_USER_EMAIL', 'demo@demo.com'),

    'user_password' => env('DEMO_USER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Demo Switcher — Nav Menu
    |--------------------------------------------------------------------------
    |
    | The demo bar's nav menu (visible only in demo mode) lets a buyer preview
    | different TARGET-PAGE styles WITHOUT changing the theme preset — that is
    | the right-side selector modal's job (presets + addon homepages, both
    | discovered automatically).
    |
    | Each item maps a stable `key` (used in the ?demo_home / ?demo_tool query
    | param) to a page-level layout the theme already ships:
    |   - home items → a hero `hero_variant` (Home.vue renders it)
    |   - tool items → a ToolPage `layout` (default | modern | minimalist | creative)
    |
    | Selection is carried in the URL (GET only) so it never trips demo mode's
    | write-block and stays deep-linkable.
    |
    */

    'nav' => [
        'home' => [
            'label' => 'Home',
            'items' => [
                ['key' => 'home-1', 'label' => 'Home 1 — Gradient',   'hero_variant' => 'centered-gradient'],
                ['key' => 'home-2', 'label' => 'Home 2 — Tools Grid', 'hero_variant' => 'tools-grid'],
                ['key' => 'home-3', 'label' => 'Home 3 — Featured',   'hero_variant' => 'featured'],
            ],
        ],
        'tools' => [
            'label' => 'Tool Page',
            'items' => [
                ['key' => 'tool-1', 'label' => 'Page 1 — Default',     'layout' => 'default'],
                ['key' => 'tool-2', 'label' => 'Page 2 — Modern',      'layout' => 'modern'],
                ['key' => 'tool-3', 'label' => 'Page 3 — Minimalist',  'layout' => 'minimalist'],
                ['key' => 'tool-4', 'label' => 'Page 4 — Creative',    'layout' => 'creative'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Provisioning — credentials that must survive demo:reset
    |--------------------------------------------------------------------------
    |
    | demo:reset runs migrate:fresh every six hours, which drops ai_keys,
    | payment_gateways and settings along with every other table. .env is the only
    | thing that survives, so it is the source of truth: DemoProvisionSeeder writes
    | these back into the database as the last step of every reset. Demo mode also
    | blocks admin writes, so re-entering them by hand is not an option.
    |
    | Every entry is optional and a blank value is skipped, so an install that sets
    | none of these — i.e. every install except the seller's demo — provisions
    | nothing. The seeder is only ever called from demo:reset, never from db:seed.
    |
    | WARNING: these are encrypted at rest with APP_KEY. If APP_KEY changes, every
    | credential reseeded before the change becomes undecryptable. Pin it on the
    | demo host and never regenerate it there.
    |
    */

    'provisioning' => [

        // Written to App\Models\AiKey — the platform key pool ProviderRegistry reads.
        // Keys are provider slugs from config/ai.php.
        //
        // The google slot exists because the fallback model below is a NATIVE Google model
        // (gemini-3.1-flash-lite is not offered through OpenRouter in the seeded catalog),
        // and a fallback whose provider has no key is not a fallback. Leave it blank and
        // the default still works — the fallback just stays on its seeded value.
        'ai_keys' => [
            'openrouter' => env('DEMO_AI_KEY_OPENROUTER'),
            'google' => env('DEMO_AI_KEY_GOOGLE'),
        ],

        // FoundationSeeder points the default at openai/gpt-5.4-mini and the fallback at the
        // same provider, neither of which has a key on a demo — every generation would fail.
        // These are ai_models.slug values; each is applied only when its own provider got a
        // key above, so a wrong slug degrades to "left as seeded" instead of breaking.
        'ai_default_model' => env('DEMO_AI_DEFAULT_MODEL', 'deepseek/deepseek-v4-flash'),
        'ai_fallback_model' => env('DEMO_AI_FALLBACK_MODEL', 'gemini-3.1-flash-lite'),

        // Written to payment_gateways.credentials (encrypted per value). Field names must
        // match config/payment-gateways.php. A gateway is enabled only when every one of
        // its fields is present, and is always forced into test mode.
        'gateways' => [
            'stripe' => [
                'publishable_key' => env('DEMO_STRIPE_PUBLISHABLE_KEY'),
                'secret_key' => env('DEMO_STRIPE_SECRET_KEY'),
                'webhook_secret' => env('DEMO_STRIPE_WEBHOOK_SECRET'),
            ],
            'paypal' => [
                'client_id' => env('DEMO_PAYPAL_CLIENT_ID'),
                'client_secret' => env('DEMO_PAYPAL_CLIENT_SECRET'),
                'webhook_id' => env('DEMO_PAYPAL_WEBHOOK_ID'),
            ],
            'paddle' => [
                'api_key' => env('DEMO_PADDLE_API_KEY'),
                'client_token' => env('DEMO_PADDLE_CLIENT_TOKEN'),
                'webhook_secret' => env('DEMO_PADDLE_WEBHOOK_SECRET'),
            ],
            'razorpay' => [
                'key_id' => env('DEMO_RAZORPAY_KEY_ID'),
                'key_secret' => env('DEMO_RAZORPAY_KEY_SECRET'),
                'webhook_secret' => env('DEMO_RAZORPAY_WEBHOOK_SECRET'),
            ],
            'sslcommerz' => [
                'store_id' => env('DEMO_SSLCOMMERZ_STORE_ID'),
                'store_password' => env('DEMO_SSLCOMMERZ_STORE_PASSWORD'),
            ],
            'coingate' => [
                'auth_token' => env('DEMO_COINGATE_AUTH_TOKEN'),
                'webhook_token' => env('DEMO_COINGATE_WEBHOOK_TOKEN'),
            ],
            'paystack' => [
                'public_key' => env('DEMO_PAYSTACK_PUBLIC_KEY'),
                'secret_key' => env('DEMO_PAYSTACK_SECRET_KEY'),
            ],
            '2checkout' => [
                'merchant_code' => env('DEMO_2CHECKOUT_MERCHANT_CODE'),
                'secret_key' => env('DEMO_2CHECKOUT_SECRET_KEY'),
            ],
            // Not a credential — the payment instructions shown to the buyer. It is the one
            // gateway that needs no third-party account, so setting this alone gives the
            // demo a working checkout path end to end.
            'bank_transfer' => [
                'instructions' => env('DEMO_BANK_TRANSFER_INSTRUCTIONS'),
            ],
        ],

        // Written to settings as social_login_{provider}_{client_id,client_secret,enabled}.
        // Both halves are required before a provider is enabled — the same rule
        // OauthSettingsController enforces in the admin.
        'oauth' => [
            'google' => [
                'client_id' => env('DEMO_OAUTH_GOOGLE_CLIENT_ID'),
                'client_secret' => env('DEMO_OAUTH_GOOGLE_CLIENT_SECRET'),
            ],
            'twitter' => [
                'client_id' => env('DEMO_OAUTH_TWITTER_CLIENT_ID'),
                'client_secret' => env('DEMO_OAUTH_TWITTER_CLIENT_SECRET'),
            ],
        ],

        // Everything on Settings → Extensions, keyed integration → provider → field, exactly
        // as config/external-tools.php declares them. Secrets are stored encrypted and
        // options as plain strings, matching what AiManagementController writes.
        //
        // No provider needs choosing: for each integration the first provider whose declared
        // secrets are ALL present wins, and that one is selected and switched on. So filling
        // in the account you actually have is the whole configuration step. Providers that
        // declare no secrets (the analytics ones) qualify on any option being set.
        //
        // Env names are DEMO_EXT_{PROVIDER}_{FIELD} — provider slugs are unique across
        // integrations, so this stays short and unambiguous.
        //
        // `captcha` is deliberately absent: it guards sign-in, so it keeps its own block
        // below with a separate on/off switch rather than being auto-enabled here.
        'extensions' => [
            'plagiarism' => [
                'copyscape' => [
                    'api_key' => env('DEMO_EXT_COPYSCAPE_API_KEY'),
                    'username' => env('DEMO_EXT_COPYSCAPE_USERNAME'),
                ],
                'originality' => ['api_key' => env('DEMO_EXT_ORIGINALITY_API_KEY')],
            ],
            'ai_detector' => [
                'gptzero' => ['api_key' => env('DEMO_EXT_GPTZERO_API_KEY')],
                'sapling' => ['api_key' => env('DEMO_EXT_SAPLING_API_KEY')],
            ],
            'grammar' => [
                'languagetool' => [
                    'api_key' => env('DEMO_EXT_LANGUAGETOOL_API_KEY'),
                    'base_url' => env('DEMO_EXT_LANGUAGETOOL_BASE_URL'),
                ],
            ],
            'translation' => [
                'deepl' => ['auth_key' => env('DEMO_EXT_DEEPL_AUTH_KEY')],
                'google_translate' => ['api_key' => env('DEMO_EXT_GOOGLE_TRANSLATE_API_KEY')],
            ],
            'spam_filter' => [
                'akismet' => [
                    'api_key' => env('DEMO_EXT_AKISMET_API_KEY'),
                    'site_url' => env('DEMO_EXT_AKISMET_SITE_URL'),
                ],
            ],
            'ip_geolocation' => [
                'ipinfo' => ['token' => env('DEMO_EXT_IPINFO_TOKEN')],
            ],
            'currency_rates' => [
                'exchangerate' => ['api_key' => env('DEMO_EXT_EXCHANGERATE_API_KEY')],
                'fixer' => ['api_key' => env('DEMO_EXT_FIXER_API_KEY')],
            ],
            'content_moderation' => [
                'openai_moderation' => ['api_key' => env('DEMO_EXT_OPENAI_MODERATION_API_KEY')],
                'sightengine' => [
                    'api_user' => env('DEMO_EXT_SIGHTENGINE_API_USER'),
                    'api_secret' => env('DEMO_EXT_SIGHTENGINE_API_SECRET'),
                ],
            ],
            'web_analytics' => [
                'ga4' => ['measurement_id' => env('DEMO_EXT_GA4_MEASUREMENT_ID')],
                'gtm' => ['container_id' => env('DEMO_EXT_GTM_CONTAINER_ID')],
                'plausible' => [
                    'domain' => env('DEMO_EXT_PLAUSIBLE_DOMAIN'),
                    'script_url' => env('DEMO_EXT_PLAUSIBLE_SCRIPT_URL'),
                ],
                'umami' => [
                    'website_id' => env('DEMO_EXT_UMAMI_WEBSITE_ID'),
                    'script_url' => env('DEMO_EXT_UMAMI_SCRIPT_URL'),
                ],
            ],
            'sms_gateway' => [
                'twilio' => [
                    'account_sid' => env('DEMO_EXT_TWILIO_ACCOUNT_SID'),
                    'auth_token' => env('DEMO_EXT_TWILIO_AUTH_TOKEN'),
                    'from' => env('DEMO_EXT_TWILIO_FROM'),
                ],
                'vonage' => [
                    'api_key' => env('DEMO_EXT_VONAGE_API_KEY'),
                    'api_secret' => env('DEMO_EXT_VONAGE_API_SECRET'),
                    'from' => env('DEMO_EXT_VONAGE_FROM'),
                ],
                'messagebird' => [
                    'access_key' => env('DEMO_EXT_MESSAGEBIRD_ACCESS_KEY'),
                    'originator' => env('DEMO_EXT_MESSAGEBIRD_ORIGINATOR'),
                ],
            ],
            'email_validation' => [
                'zerobounce' => ['api_key' => env('DEMO_EXT_ZEROBOUNCE_API_KEY')],
                'neverbounce' => ['api_key' => env('DEMO_EXT_NEVERBOUNCE_API_KEY')],
            ],
        ],

        // Written to settings as external_captcha_* and read by CaptchaService.
        //
        // The keys are provisioned whenever both are present, but the guard itself only
        // switches on when 'enabled' says so — captcha sits in front of sign-in, and a
        // site key that does not list the demo domain locks every visitor out of a demo
        // whose whole purpose is being signed into. Off by default: provision the keys,
        // confirm the challenge renders, then flip DEMO_RECAPTCHA_ENABLED.
        'captcha' => [
            'enabled' => filter_var(env('DEMO_RECAPTCHA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'site_key' => env('DEMO_RECAPTCHA_SITE_KEY'),
            'secret_key' => env('DEMO_RECAPTCHA_SECRET_KEY'),
        ],

        // The demo host's own logo and favicon, written to the site_* branding settings.
        //
        // These cannot be uploaded through the admin panel: DemoMode blocks every write
        // that is not on its allowlist, and Appearance › Branding is not. Setting them by
        // hand does not survive either — demo:reset runs migrate:fresh (wiping settings)
        // and demo:sweep-uploads (wiping the storage trees) before this seeder runs.
        //
        // Each value is a FILENAME inside public/demo-assets/, not a path: that directory
        // is part of the release rather than the writable storage tree, so it is the one
        // place a source image survives a reset. DemoProvisionSeeder copies each file onto
        // the public disk and points the setting at the copy — storing the source path
        // directly would break, since media_url() resolves stored keys against the public
        // disk and would rewrite it to /storage/demo-assets/…
        //
        // A missing or blank entry is skipped and the setting is left as seeded (empty),
        // which falls back to the site-name wordmark in the header.
        // favicon_ico is blank on purpose: app.blade.php renders the PNG as the primary
        // <link rel="icon"> and the .ico only as `rel="alternate icon"`, which no browser
        // in use reaches. Point DEMO_FAVICON_ICO at a file in public/demo-assets/ if you
        // ever want one; leaving it blank skips the slot silently rather than warning
        // about a file that does not need to exist.
        'branding' => [
            'logo_light' => env('DEMO_LOGO_LIGHT', 'logo-light.png'),
            'logo_dark' => env('DEMO_LOGO_DARK', 'logo-dark.png'),
            'favicon_ico' => env('DEMO_FAVICON_ICO', ''),
            'favicon_png' => env('DEMO_FAVICON_PNG', 'favicon.png'),
        ],
    ],

];
