<?php

return [
    'integrations' => [
        'plagiarism' => [
            'name' => 'Plagiarism Checker',
            'service' => 'PlagiarismService',
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
            'providers' => [
                'languagetool' => [
                    'name' => 'LanguageTool',
                    'secrets' => ['api_key'],
                    'options' => ['base_url'],
                ],
            ],
        ],
        'background_remover' => [
            'name' => 'Background Remover',
            'service' => 'BackgroundRemoveService',
            'providers' => [
                'removebg' => [
                    'name' => 'Remove.bg',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],
        'image_upscaler' => [
            'name' => 'Image Upscaler',
            'service' => 'ImageUpscaleService',
            'providers' => [
                'replicate' => [
                    'name' => 'Replicate',
                    'secrets' => ['api_key'],
                    'options' => ['model'],
                ],
            ],
        ],
        'stock_image' => [
            'name' => 'Stock Image Search',
            'service' => 'StockImageService',
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
        'web_search' => [
            'name' => 'Web Search',
            'service' => 'WebSearchService',
            'providers' => [
                'serpapi' => [
                    'name' => 'SerpAPI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'bing' => [
                    'name' => 'Bing Search',
                    'secrets' => ['api_key'],
                    'options' => ['endpoint'],
                ],
                'perplexity' => [
                    'name' => 'Perplexity',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],
        'youtube_transcript' => [
            'name' => 'YouTube Transcript',
            'service' => 'YoutubeService',
            'providers' => [
                'youtube_transcript' => [
                    'name' => 'youtube-transcript package',
                    'secrets' => [],
                    'options' => [],
                ],
            ],
        ],
        'url_scraper' => [
            'name' => 'URL Scraper',
            'service' => 'WebScraperService',
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
        'speech_to_text' => [
            'name' => 'Speech to Text',
            'service' => 'SpeechToTextService',
            'providers' => [
                'whisper' => [
                    'name' => 'Whisper',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
                'assemblyai' => [
                    'name' => 'AssemblyAI',
                    'secrets' => ['api_key'],
                    'options' => [],
                ],
            ],
        ],
    ],
];
