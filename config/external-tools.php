<?php

/**
 * External (non-LLM) API integrations for MakeAI.
 *
 * Two admin surfaces read this catalog (grouping is decided in
 * AiManagementController::AI_TOOL_INTEGRATIONS, not here):
 *   - Integrations (under AI Management): per-user, credit-billable AI content
 *     tools — Plagiarism, AI Content Detector, Grammar, Translation.
 *   - Extensions (under Settings): platform/system services — CAPTCHA, spam
 *     filtering, IP geolocation, currency rates, content moderation, web
 *     analytics, SMS gateway, and email validation.
 *
 * Cloud media storage is configured separately on Settings → Storage, not here.
 * Other connectors (publishing, CRM, push, …) are not shipped in core; an addon
 * that needs one registers its own integration, credentials, and billing rule.
 *
 * Each entry may declare an optional `settings` array of integration-level admin
 * fields (select/boolean) rendered on the page and stored as external_{slug}_{key}.
 *
 * No secrets are ever stored in this file.
 * All credentials are stored encrypted in the `settings` table.
 */

return [
    'integrations' => [

        // ─────────────────────────────────────────────────────────────────
        // AI CONTENT TOOLS  (Integrations — under AI Management)
        // Kept in core; tools that consume these are on the roadmap.
        // ─────────────────────────────────────────────────────────────────

        'plagiarism' => [
            'name' => 'Plagiarism Checker',
            'description' => 'Scan generated text against the web to detect duplicate or copied content.',
            'service' => 'PlagiarismService',
            'doc_url' => 'https://www.copyscape.com/api-guide.php',
            'ai_fallback' => false,
            'providers' => [
                'copyscape' => [
                    'name' => 'Copyscape',
                    'secrets' => ['api_key'],
                    'options' => ['username'],
                ],
                'originality' => [
                    'name' => 'Originality.ai',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'ai_detector' => [
            'name' => 'AI Content Detector',
            'description' => 'Estimate how likely a piece of text was written by AI.',
            'service' => 'AiDetectorService',
            'doc_url' => 'https://gptzero.me/docs',
            'ai_fallback' => false,
            'providers' => [
                'gptzero' => [
                    'name' => 'GPTZero',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'sapling' => [
                    'name' => 'Sapling',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'grammar' => [
            'name' => 'Grammar Checker',
            'description' => 'Check spelling, grammar, and style, and suggest corrections.',
            'service' => 'GrammarService',
            'doc_url' => 'https://languagetool.org/http-api/',
            'ai_fallback' => false,
            'providers' => [
                'languagetool' => [
                    'name' => 'LanguageTool',
                    'secrets' => ['api_key'],
                    'options' => ['base_url'],
                ],
            ],
        ],

        'translation' => [
            'name' => 'Translation',
            'description' => 'Translate content between languages via a dedicated translation API.',
            'service' => 'Integrations\\TranslationIntegration',
            'doc_url' => 'https://www.deepl.com/docs-api',
            'ai_fallback' => false,
            'providers' => [
                'deepl' => [
                    'name' => 'DeepL',
                    'secrets' => ['auth_key'],
                    'options' => [],
                ],
                'google_translate' => [
                    'name' => 'Google Translate',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // SYSTEM SERVICES  (Extensions — under Settings)
        // ─────────────────────────────────────────────────────────────────

        'captcha' => [
            'name' => 'CAPTCHA Protection',
            'description' => 'Protect login, registration, and forms from bots with reCAPTCHA or hCaptcha.',
            'service' => 'CaptchaService',
            'doc_url' => 'https://www.google.com/recaptcha/about/',
            'ai_fallback' => false,
            'providers' => [
                'recaptcha' => [
                    'name' => 'Google reCAPTCHA',
                    'secrets' => ['site_key', 'secret_key'],
                    'options' => [],
                ],
                'hcaptcha' => [
                    'name' => 'hCaptcha',
                    'secrets' => ['site_key', 'secret_key'],
                    'options' => [],
                ],
            ],
        ],

        'spam_filter' => [
            'name' => 'Spam Filter',
            'description' => 'Screen comments and contact messages for spam with Akismet.',
            'service' => 'SpamFilterService',
            'doc_url' => 'https://akismet.com/development/api/',
            'ai_fallback' => false,
            'providers' => [
                'akismet' => [
                    'name' => 'Akismet',
                    'secrets' => ['api_key'],
                    'options' => ['site_url'],
                ],
            ],
        ],

        'ip_geolocation' => [
            'name' => 'IP Geolocation',
            'description' => 'Resolve visitor IP addresses to country and location for analytics and fraud checks.',
            'service' => 'IpGeolocationService',
            'doc_url' => 'https://ipinfo.io/developers',
            'ai_fallback' => false,
            'providers' => [
                'ipinfo' => [
                    'name' => 'IPInfo',
                    'secrets' => ['token'],
                    'options' => [],
                ],
            ],
        ],

        'currency_rates' => [
            'name' => 'Currency Exchange Rates',
            'description' => 'Fetch live exchange rates to display prices in multiple currencies.',
            'service' => 'CurrencyRateService',
            'doc_url' => 'https://exchangerate.host/',
            'ai_fallback' => false,
            'providers' => [
                'exchangerate' => [
                    'name' => 'ExchangeRate API',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'fixer' => [
                    'name' => 'Fixer.io',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'content_moderation' => [
            'name' => 'Content Moderation',
            'description' => 'Screen AI prompts and generated media for unsafe or NSFW content.',
            'service' => 'ContentModerationService',
            'doc_url' => 'https://platform.openai.com/docs/guides/moderation',
            'ai_fallback' => false,
            // Integration-level admin settings (beyond credentials). Rendered on the
            // Extensions page and stored as external_{slug}_{key}.
            'settings' => [
                [
                    'key' => 'mode',
                    'type' => 'select',
                    'label' => 'Enforcement Mode',
                    'help' => 'Off disables checks. Flag allows the request but logs a warning. Block rejects unsafe prompts before any credits are charged.',
                    'default' => 'flag',
                    'options' => [
                        ['value' => 'off', 'label' => 'Off'],
                        ['value' => 'flag', 'label' => 'Flag & log (allow)'],
                        ['value' => 'block', 'label' => 'Block unsafe requests'],
                    ],
                ],
            ],
            'providers' => [
                'openai_moderation' => [
                    'name' => 'OpenAI Moderation',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'sightengine' => [
                    'name' => 'Sightengine',
                    'secrets' => ['api_user', 'api_secret'],
                    'options' => [],
                ],
            ],
        ],

        'web_analytics' => [
            'name' => 'Web Analytics',
            'description' => 'Add a privacy-aware analytics tag (GA4, GTM, Plausible, or Umami) to every page.',
            'service' => 'WebAnalyticsService',
            'doc_url' => 'https://support.google.com/analytics/answer/9304153',
            'ai_fallback' => false,
            'settings' => [
                [
                    'key' => 'respect_consent',
                    'type' => 'boolean',
                    'label' => 'Wait for cookie consent',
                    'help' => 'When cookie consent is enabled, only load the analytics tag after the visitor accepts analytics cookies. Recommended for GDPR compliance.',
                    'default' => true,
                ],
            ],
            'providers' => [
                'ga4' => [
                    'name' => 'Google Analytics 4',
                    'secrets' => [],
                    'options' => ['measurement_id'],
                ],
                'gtm' => [
                    'name' => 'Google Tag Manager',
                    'secrets' => [],
                    'options' => ['container_id'],
                ],
                'plausible' => [
                    'name' => 'Plausible',
                    'secrets' => [],
                    'options' => ['domain', 'script_url'],
                ],
                'umami' => [
                    'name' => 'Umami',
                    'secrets' => [],
                    'options' => ['website_id', 'script_url'],
                ],
            ],
        ],

        'sms_gateway' => [
            'name' => 'SMS Gateway',
            'description' => 'Send transactional SMS and OTP messages via Twilio, Vonage, or MessageBird.',
            'service' => 'SmsService',
            'doc_url' => 'https://www.twilio.com/docs/sms',
            'ai_fallback' => false,
            'providers' => [
                'twilio' => [
                    'name' => 'Twilio',
                    'secrets' => ['account_sid', 'auth_token'],
                    'options' => ['from'],
                ],
                'vonage' => [
                    'name' => 'Vonage',
                    'secrets' => ['api_key', 'api_secret'],
                    'options' => ['from'],
                ],
                'messagebird' => [
                    'name' => 'MessageBird',
                    'secrets' => ['access_key'],
                    'options' => ['originator'],
                ],
            ],
        ],

        'email_validation' => [
            'name' => 'Email Validation',
            'description' => 'Block disposable and undeliverable email addresses at signup.',
            'service' => 'EmailValidationService',
            'settings' => [
                [
                    'key' => 'mode',
                    'type' => 'select',
                    'label' => 'What to block',
                    'help' => 'Choose how strict signup email checks are. Disposable-only is more permissive; blocking all invalid addresses is stricter.',
                    'default' => 'all',
                    'options' => [
                        ['value' => 'all', 'label' => 'Disposable and undeliverable addresses'],
                        ['value' => 'disposable_only', 'label' => 'Disposable addresses only'],
                    ],
                ],
            ],
            'doc_url' => 'https://www.zerobounce.net/docs/email-validation-api-quickstart',
            'ai_fallback' => false,
            'providers' => [
                'zerobounce' => [
                    'name' => 'ZeroBounce',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'neverbounce' => [
                    'name' => 'NeverBounce',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

    ],
];
