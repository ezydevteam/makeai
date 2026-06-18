<?php

/**
 * External (non-LLM) API integrations for MakeAI.
 *
 * Organized into the tabbed groups shown in Admin → Settings → Integrations:
 *   Tab 1: AI Models (LLM providers — managed separately in config/ai.php)
 *   Tab 2: Image & Media
 *   Tab 3: Voice & Audio
 *   Tab 4: Video
 *   Tab 5: Productivity
 *   Tab 6: Utilities & Payments
 *
 * `ai_fallback` controls whether the System Default AI option appears
 * in the provider switcher. Set to false for integrations that don't
 * leverage an LLM (CAPTCHA, SMS, Payments, Search, etc.).
 *
 * No secrets are ever stored in this file.
 * All credentials are stored encrypted in the `settings` table.
 */

return [
    'integrations' => [

        // ─────────────────────────────────────────────────────────────────
        // TAB 2: IMAGE & MEDIA
        // ─────────────────────────────────────────────────────────────────

        'image_generation' => [
            'name' => 'AI Image Generation',
            'service' => 'ImageGenerationService',
            'tab' => 'image_media',
            'doc_url' => 'https://platform.openai.com/docs/guides/images',
            'ai_fallback' => false,
            'providers' => [
                'openai_dalle' => [
                    'name' => 'DALL·E 3',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'replicate_sd' => [
                    'name' => 'Stable Diffusion (Replicate)',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'fal_flux' => [
                    'name' => 'Flux Pro (fal.ai)',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'ideogram' => [
                    'name' => 'Ideogram',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'midjourney' => [
                    'name' => 'Midjourney (Proxy)',
                    'secrets' => ['api_key'],
                    'options' => ['proxy_url'],
                ],
                'stability_ai' => [
                    'name' => 'Stability AI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'stock_image' => [
            'name' => 'Stock Image Search',
            'service' => 'StockImageService',
            'tab' => 'image_media',
            'doc_url' => 'https://pixabay.com/api/docs/',
            'ai_fallback' => false,
            'providers' => [
                'pixabay' => [
                    'name' => 'Pixabay',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'pexels' => [
                    'name' => 'Pexels',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'unsplash' => [
                    'name' => 'Unsplash',
                    'secrets' => ['access_key'],
                    'options' => [],
                ],
            ],
        ],

        'background_remover' => [
            'name' => 'Background Remover',
            'service' => 'BackgroundRemoveService',
            'tab' => 'image_media',
            'doc_url' => 'https://www.remove.bg/api',
            'ai_fallback' => false,
            'providers' => [
                'removebg' => [
                    'name' => 'Remove.bg',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'clipdrop' => [
                    'name' => 'Clipdrop',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'image_upscaler' => [
            'name' => 'Image Upscaler',
            'service' => 'ImageUpscaleService',
            'tab' => 'image_media',
            'doc_url' => 'https://replicate.com/docs',
            'ai_fallback' => false,
            'providers' => [
                'replicate' => [
                    'name' => 'Replicate',
                    'secrets' => ['api_key'],
                    'options' => ['model'],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // TAB 3: VOICE & AUDIO
        // ─────────────────────────────────────────────────────────────────

        'text_to_speech' => [
            'name' => 'Text to Speech',
            'service' => 'TextToSpeechService',
            'tab' => 'voice_audio',
            'doc_url' => 'https://elevenlabs.io/docs/api-reference',
            'ai_fallback' => false,
            'providers' => [
                'elevenlabs' => [
                    'name' => 'ElevenLabs',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'openai_tts' => [
                    'name' => 'OpenAI TTS',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'azure_speech' => [
                    'name' => 'Azure Cognitive Speech',
                    'secrets' => ['api_key'],
                    'options' => ['region'],
                ],
                'google_tts' => [
                    'name' => 'Google Cloud TTS',
                    'secrets' => ['service_account_json'],
                    'options' => [],
                ],
                'amazon_polly' => [
                    'name' => 'Amazon Polly',
                    'secrets' => ['access_key', 'secret_key'],
                    'options' => ['region'],
                ],
                'murf' => [
                    'name' => 'Murf AI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'playht' => [
                    'name' => 'PlayHT',
                    'secrets' => ['user_id', 'api_key'],
                    'options' => [],
                ],
            ],
        ],

        'speech_to_text' => [
            'name' => 'Speech to Text',
            'service' => 'SpeechToTextService',
            'tab' => 'voice_audio',
            'doc_url' => 'https://platform.openai.com/docs/guides/speech-to-text',
            'ai_fallback' => false,
            'providers' => [
                'openai_whisper' => [
                    'name' => 'Whisper (OpenAI)',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'assemblyai' => [
                    'name' => 'AssemblyAI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'deepgram' => [
                    'name' => 'Deepgram',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'ai_music' => [
            'name' => 'AI Music Generation',
            'service' => 'AiMusicService',
            'tab' => 'voice_audio',
            'doc_url' => 'https://suno.ai',
            'ai_fallback' => false,
            'providers' => [
                'suno' => [
                    'name' => 'Suno AI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'udio' => [
                    'name' => 'Udio',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'voice_separator' => [
            'name' => 'Voice / Instrument Separator',
            'service' => 'VoiceSeparatorService',
            'tab' => 'voice_audio',
            'doc_url' => 'https://www.lalal.ai/api/',
            'ai_fallback' => false,
            'providers' => [
                'lalal' => [
                    'name' => 'Lalal.ai',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // TAB 4: VIDEO
        // ─────────────────────────────────────────────────────────────────

        'video_generation' => [
            'name' => 'AI Video Generation',
            'service' => 'VideoGenerationService',
            'tab' => 'video',
            'doc_url' => 'https://www.klingai.com/',
            'ai_fallback' => false,
            'providers' => [
                'kling' => [
                    'name' => 'Kling AI',
                    'secrets' => ['api_key', 'secret'],
                    'options' => [],
                ],
                'google_veo' => [
                    'name' => 'Google Veo (Vertex AI)',
                    'secrets' => ['credentials_json'],
                    'options' => ['project_id', 'region'],
                ],
                'minimax' => [
                    'name' => 'Minimax Video',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'runway' => [
                    'name' => 'Runway ML',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'openai_sora' => [
                    'name' => 'Sora (OpenAI)',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'pika' => [
                    'name' => 'Pika Labs',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'avatar_video' => [
            'name' => 'AI Avatar Video',
            'service' => 'AvatarVideoService',
            'tab' => 'video',
            'doc_url' => 'https://docs.d-id.com/',
            'ai_fallback' => false,
            'providers' => [
                'did' => [
                    'name' => 'D-ID',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'heygen' => [
                    'name' => 'HeyGen',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'synthesia' => [
                    'name' => 'Synthesia',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // TAB 5: PRODUCTIVITY
        // ─────────────────────────────────────────────────────────────────

        'notion' => [
            'name' => 'Notion Integration',
            'service' => 'NotionService',
            'tab' => 'productivity',
            'doc_url' => 'https://developers.notion.com/',
            'ai_fallback' => false,
            'providers' => [
                'notion' => [
                    'name' => 'Notion',
                    'secrets' => ['integration_token'],
                    'options' => [],
                ],
            ],
        ],

        'google_drive' => [
            'name' => 'Google Drive',
            'service' => 'GoogleDriveService',
            'tab' => 'productivity',
            'doc_url' => 'https://developers.google.com/drive/api',
            'ai_fallback' => false,
            'providers' => [
                'google' => [
                    'name' => 'Google Drive / Docs',
                    'secrets' => ['client_id', 'client_secret'],
                    'options' => ['redirect_uri'],
                ],
            ],
        ],

        'wordpress' => [
            'name' => 'WordPress Publishing',
            'service' => 'WordPressService',
            'tab' => 'productivity',
            'doc_url' => 'https://developer.wordpress.org/rest-api/',
            'ai_fallback' => false,
            'providers' => [
                'wordpress' => [
                    'name' => 'WordPress',
                    'secrets' => ['app_password'],
                    'options' => ['site_url'],
                ],
            ],
        ],

        'zapier' => [
            'name' => 'Zapier Webhooks',
            'service' => 'ZapierService',
            'tab' => 'productivity',
            'doc_url' => 'https://zapier.com/help/create/code-webhooks/trigger-zaps-with-webhooks',
            'ai_fallback' => false,
            'providers' => [
                'zapier' => [
                    'name' => 'Zapier',
                    'secrets' => ['webhook_url'],
                    'options' => [],
                ],
            ],
        ],

        'slack' => [
            'name' => 'Slack Notifications',
            'service' => 'SlackService',
            'tab' => 'productivity',
            'doc_url' => 'https://api.slack.com/',
            'ai_fallback' => false,
            'providers' => [
                'slack' => [
                    'name' => 'Slack',
                    'secrets' => ['bot_token', 'signing_secret'],
                    'options' => [],
                ],
            ],
        ],

        'gamma' => [
            'name' => 'AI Presentations (Gamma)',
            'service' => 'GammaService',
            'tab' => 'productivity',
            'doc_url' => 'https://gamma.app/docs/api',
            'ai_fallback' => false,
            'providers' => [
                'gamma' => [
                    'name' => 'Gamma',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],

        'canva' => [
            'name' => 'Canva Integration',
            'service' => 'CanvaService',
            'tab' => 'productivity',
            'doc_url' => 'https://www.canva.dev/docs/api/',
            'ai_fallback' => false,
            'providers' => [
                'canva' => [
                    'name' => 'Canva',
                    'secrets' => ['client_id', 'client_secret'],
                    'options' => [],
                ],
            ],
        ],

        'hubspot' => [
            'name' => 'HubSpot CRM',
            'service' => 'HubSpotService',
            'tab' => 'productivity',
            'doc_url' => 'https://developers.hubspot.com/docs/api',
            'ai_fallback' => false,
            'providers' => [
                'hubspot' => [
                    'name' => 'HubSpot',
                    'secrets' => ['private_app_token'],
                    'options' => [],
                ],
            ],
        ],

        'airtable' => [
            'name' => 'Airtable Database',
            'service' => 'AirtableService',
            'tab' => 'productivity',
            'doc_url' => 'https://airtable.com/developers/web/api',
            'ai_fallback' => false,
            'providers' => [
                'airtable' => [
                    'name' => 'Airtable',
                    'secrets' => ['personal_access_token'],
                    'options' => [],
                ],
            ],
        ],

        // ─────────────────────────────────────────────────────────────────
        // TAB 6: UTILITIES & PAYMENTS
        // ─────────────────────────────────────────────────────────────────

        'plagiarism' => [
            'name' => 'Plagiarism Checker',
            'service' => 'PlagiarismService',
            'tab' => 'utilities',
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
            'service' => 'AiDetectorService',
            'tab' => 'utilities',
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
            'service' => 'GrammarService',
            'tab' => 'utilities',
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

        'web_search' => [
            'name' => 'Web Search',
            'service' => 'WebSearchService',
            'tab' => 'utilities',
            'doc_url' => 'https://serpapi.com/',
            'ai_fallback' => false,
            'providers' => [
                'serpapi' => [
                    'name' => 'SerpAPI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'google_cse' => [
                    'name' => 'Google Search',
                    'secrets' => ['api_key'],
                    'options' => ['cse_id'],
                ],
                'bing' => [
                    'name' => 'Bing Search',
                    'secrets' => ['api_key'],
                    'options' => ['endpoint'],
                ],
            ],
        ],

        'translation' => [
            'name' => 'Translation',
            'service' => 'TranslationService',
            'tab' => 'utilities',
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

        'currency_rates' => [
            'name' => 'Currency Exchange Rates',
            'service' => 'CurrencyRateService',
            'tab' => 'utilities',
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

        'ip_geolocation' => [
            'name' => 'IP Geolocation',
            'service' => 'IpGeolocationService',
            'tab' => 'utilities',
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

        'sms_provider' => [
            'name' => 'SMS (OTP / Notifications)',
            'service' => 'SmsProviderService',
            'tab' => 'utilities',
            'doc_url' => 'https://www.twilio.com/docs',
            'ai_fallback' => false,
            'providers' => [
                'twilio' => [
                    'name' => 'Twilio',
                    'secrets' => ['account_sid', 'auth_token'],
                    'options' => ['from_number'],
                ],
            ],
        ],

        'push_notifications' => [
            'name' => 'Push Notifications',
            'service' => 'PushNotificationService',
            'tab' => 'utilities',
            'doc_url' => 'https://firebase.google.com/docs/cloud-messaging',
            'ai_fallback' => false,
            'providers' => [
                'firebase' => [
                    'name' => 'Firebase FCM',
                    'secrets' => ['server_key'],
                    'options' => [],
                ],
            ],
        ],

        'captcha' => [
            'name' => 'CAPTCHA Protection',
            'service' => 'CaptchaService',
            'tab' => 'utilities',
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
            'service' => 'SpamFilterService',
            'tab' => 'utilities',
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

        'url_scraper' => [
            'name' => 'URL Scraper',
            'service' => 'WebScraperService',
            'tab' => 'utilities',
            'doc_url' => 'https://github.com/spatie/browsershot',
            'ai_fallback' => false,
            'providers' => [
                'browsershot' => [
                    'name' => 'Browsershot',
                    'secrets' => [],
                    'options' => ['chrome_path'],
                ],
                'goutte' => [
                    'name' => 'Goutte',
                    'secrets' => [],
                    'options' => [],
                ],
            ],
        ],

        'youtube_transcript' => [
            'name' => 'YouTube Transcript',
            'service' => 'YoutubeService',
            'tab' => 'utilities',
            'doc_url' => 'https://www.youtube.com/oembed',
            'ai_fallback' => false,
            'providers' => [
                'youtube_transcript' => [
                    'name' => 'youtube-transcript package',
                    'secrets' => [],
                    'options' => [],
                ],
            ],
        ],

        'crypto_payments' => [
            'name' => 'Crypto Payments',
            'service' => 'CryptoPaymentService',
            'tab' => 'utilities',
            'doc_url' => 'https://coingate.com/docs/api',
            'ai_fallback' => false,
            'providers' => [
                'coingate' => [
                    'name' => 'CoinGate',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],
    ],
];
