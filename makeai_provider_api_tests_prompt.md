# DeepSeek Implementation Prompt
# MakeAI — AI Provider Integration Test Suite (All Providers)

---

## CONTEXT & PHILOSOPHY

এই test suite-এর দুটো layer আছে:

**Layer 1 — Unit/Mock Tests (CI-safe, always run)**
Real API call করে না। `AiService` mock করে contract verify করে।
প্রতিটা provider-এর request/response shape, error handling, failover logic test করে।
`./vendor/bin/pest` দিয়ে যেকোনো machine-এ চলে।

**Layer 2 — Integration Tests (Real API, opt-in)**
Real API key দিয়ে real call করে। `@group integration` tag দেওয়া।
`.env.integration` থেকে keys পড়ে। শুধু locally বা dedicated CI job-এ run হয়।
Command: `./vendor/bin/pest --group=integration`

**Architecture invariants:**
- সব AI calls `laravel/ai` SDK-এর `AiService` দিয়ে যায়, কখনো raw HTTP না
- `ProviderRegistry` → round-robin load balancing + failover on rate limit
- API keys `settings` table-এ encrypted (`type='encrypted'`), `settings('openai_key')` দিয়ে পড়া হয়
- Provider enable/disable: `settings('openai_enabled')`, `settings('anthropic_enabled')` etc.
- `AiService::complete(CompletionRequest $request)` → `CompletionResponse`
- `AiService::stream(CompletionRequest $request)` → `Generator`
- `AiService::embedText(string $text, string $provider)` → `float[]`

---

## FILE STRUCTURE TO CREATE

```
tests/
  Integration/
    Providers/
      Text/
        OpenAiProviderTest.php
        AnthropicProviderTest.php
        GeminiProviderTest.php
        DeepSeekProviderTest.php
        XaiProviderTest.php
        MistralProviderTest.php
        OpenRouterProviderTest.php
        PerplexityProviderTest.php
        GroqProviderTest.php
        CohereProviderTest.php
      Image/
        DalleProviderTest.php
        FluxProviderTest.php
        StabilityProviderTest.php
        IdeogramProviderTest.php
        ReplicateProviderTest.php
      Voice/
        ElevenLabsProviderTest.php
        OpenAiTtsProviderTest.php
        WhisperProviderTest.php
        ElevenLabsSttProviderTest.php
      Embedding/
        OpenAiEmbeddingTest.php
        CohereEmbeddingTest.php
      Connectivity/
        ProviderHealthCheckTest.php
        ProviderFailoverTest.php
  Feature/
    AI/
      ProviderRegistryTest.php        ← mock, always runs in CI
      AiServiceContractTest.php       ← mock, always runs in CI
      ProviderFallbackTest.php        ← mock, always runs in CI
      StreamingContractTest.php       ← mock, always runs in CI
  Unit/
    ProviderRegistryUnitTest.php      ← pure unit, no DB
```

---

## FILE 1: `tests/Pest.php` — group setup

Add integration group configuration:

```php
<?php

uses(Tests\TestCase::class)->in('Feature', 'Unit');

// Integration tests use real API — separate TestCase with no DB refresh
uses(Tests\IntegrationTestCase::class)->in('Integration');

// Skip integration tests unless explicitly requested
if (!in_array('integration', explode(',', env('PEST_GROUPS', '')))) {
    // Mark all @group integration tests as skipped in normal runs
}
```

---

## FILE 2: `tests/IntegrationTestCase.php`

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class IntegrationTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Load .env.integration keys into settings
        $this->loadIntegrationKeys();

        // Skip entire test class if no keys configured
        if (!$this->hasAnyProviderKey()) {
            $this->markTestSkipped('No provider API keys configured. Copy .env.integration.example and add real keys.');
        }
    }

    /**
     * Load real API keys from .env.integration into the settings table.
     * This lets the real AiService/ProviderRegistry read them normally.
     */
    protected function loadIntegrationKeys(): void
    {
        $keys = [
            'openai_key'       => env('TEST_OPENAI_KEY'),
            'anthropic_key'    => env('TEST_ANTHROPIC_KEY'),
            'gemini_key'       => env('TEST_GEMINI_KEY'),
            'deepseek_key'     => env('TEST_DEEPSEEK_KEY'),
            'xai_key'          => env('TEST_XAI_KEY'),
            'mistral_key'      => env('TEST_MISTRAL_KEY'),
            'openrouter_key'   => env('TEST_OPENROUTER_KEY'),
            'perplexity_key'   => env('TEST_PERPLEXITY_KEY'),
            'groq_key'         => env('TEST_GROQ_KEY'),
            'cohere_key'       => env('TEST_COHERE_KEY'),
            'elevenlabs_key'   => env('TEST_ELEVENLABS_KEY'),
            'fal_key'          => env('TEST_FAL_KEY'),
            'stability_key'    => env('TEST_STABILITY_KEY'),
            'replicate_key'    => env('TEST_REPLICATE_KEY'),
        ];

        foreach ($keys as $settingKey => $value) {
            if ($value) {
                settings_set($settingKey, $value, 'encrypted');
                settings_set(str_replace('_key', '_enabled', $settingKey), true, 'boolean');
            }
        }
    }

    protected function hasAnyProviderKey(): bool
    {
        $envKeys = [
            'TEST_OPENAI_KEY', 'TEST_ANTHROPIC_KEY', 'TEST_GEMINI_KEY',
            'TEST_DEEPSEEK_KEY', 'TEST_GROQ_KEY', 'TEST_MISTRAL_KEY',
        ];
        return collect($envKeys)->some(fn($k) => !empty(env($k)));
    }

    /**
     * Skip this test if a specific provider key is not configured.
     */
    protected function skipUnless(string $envKey, string $provider): void
    {
        if (empty(env($envKey))) {
            $this->markTestSkipped("Skipping: {$provider} key not set ({$envKey} in .env.integration)");
        }
    }

    /**
     * Assert a CompletionResponse is valid and non-empty.
     */
    protected function assertValidCompletion(\App\DTOs\AI\CompletionResponse $response): void
    {
        expect($response->content)->toBeString()->not->toBeEmpty();
        expect($response->inputTokens)->toBeInt()->toBeGreaterThan(0);
        expect($response->outputTokens)->toBeInt()->toBeGreaterThan(0);
        expect($response->model)->toBeString()->not->toBeEmpty();
        expect($response->provider)->toBeString()->not->toBeEmpty();
    }

    /**
     * Build a minimal CompletionRequest for testing.
     */
    protected function simpleRequest(
        string $provider,
        string $model,
        string $prompt = 'Reply with exactly the word: PONG',
        int $maxTokens = 10,
    ): \App\DTOs\AI\CompletionRequest {
        return new \App\DTOs\AI\CompletionRequest(
            provider:    $provider,
            model:       $model,
            messages:    [['role' => 'user', 'content' => $prompt]],
            systemPrompt: 'You are a test assistant. Follow instructions exactly.',
            maxTokens:   $maxTokens,
            temperature: 0.0,
        );
    }
}
```

---

## FILE 3: `.env.integration.example`

```env
# Copy this file to .env.integration and add your real API keys.
# These keys are ONLY used for local integration tests — never in CI unless you set them as secrets.
# All values here go into the settings table encrypted during tests.

# Text providers
TEST_OPENAI_KEY=sk-...
TEST_ANTHROPIC_KEY=sk-ant-...
TEST_GEMINI_KEY=AIza...
TEST_DEEPSEEK_KEY=sk-...
TEST_XAI_KEY=xai-...
TEST_MISTRAL_KEY=...
TEST_OPENROUTER_KEY=sk-or-...
TEST_PERPLEXITY_KEY=pplx-...
TEST_GROQ_KEY=gsk_...
TEST_COHERE_KEY=...

# Image providers
TEST_FAL_KEY=...           # Flux Pro (fal.ai)
TEST_STABILITY_KEY=...     # Stability AI
TEST_REPLICATE_KEY=r8_...  # Replicate (Stable Diffusion)
TEST_IDEOGRAM_KEY=...

# Voice providers
TEST_ELEVENLABS_KEY=...

# Leave blank to skip that provider's tests
```

---

## FILE 4: `tests/Integration/Providers/Text/OpenAiProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group openai
 */

use App\Services\AI\AiService;
use App\DTOs\AI\CompletionRequest;

beforeEach(function () {
    $this->skipUnless('TEST_OPENAI_KEY', 'OpenAI');
    $this->ai = app(AiService::class);
});

it('completes a simple prompt with gpt-4o-mini', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('openai', 'gpt-4o-mini')
    );

    $this->assertValidCompletion($response);
    expect($response->provider)->toBe('openai');
    expect($response->model)->toContain('gpt-4o-mini');
});

it('completes a simple prompt with gpt-4o', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('openai', 'gpt-4o')
    );
    $this->assertValidCompletion($response);
});

it('streams tokens from gpt-4o-mini', function () {
    $request = $this->simpleRequest('openai', 'gpt-4o-mini', 'Count to 5.', 50);
    $chunks  = [];

    foreach ($this->ai->stream($request) as $chunk) {
        $chunks[] = $chunk;
        if (count($chunks) > 100) break; // safety limit
    }

    expect($chunks)->not->toBeEmpty();
    $full = implode('', $chunks);
    expect($full)->toContain('1'); // counted at least to 1
});

it('returns token counts in completion response', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('openai', 'gpt-4o-mini')
    );

    expect($response->inputTokens)->toBeGreaterThan(5);
    expect($response->outputTokens)->toBeGreaterThan(0);
    expect($response->costUsd)->toBeFloat()->toBeGreaterThan(0);
});

it('respects max_tokens limit', function () {
    $request  = $this->simpleRequest('openai', 'gpt-4o-mini', 'Write a very long story about dragons.', 5);
    $response = $this->ai->complete($request);

    // With max_tokens=5 the output must be very short
    expect($response->outputTokens)->toBeLessThanOrEqual(10); // small margin
});

it('throws on invalid API key', function () {
    settings_set('openai_key', 'sk-invalid-key-for-testing', 'encrypted');

    expect(fn() => $this->ai->complete(
        $this->simpleRequest('openai', 'gpt-4o-mini')
    ))->toThrow(\App\Exceptions\AI\ProviderAuthException::class);
});

it('handles system prompt correctly', function () {
    $request = new CompletionRequest(
        provider:    'openai',
        model:       'gpt-4o-mini',
        messages:    [['role' => 'user', 'content' => 'What is your name?']],
        systemPrompt: 'Your name is TestBot. Always introduce yourself as TestBot.',
        maxTokens:   30,
        temperature: 0.0,
    );
    $response = $this->ai->complete($request);

    expect(strtolower($response->content))->toContain('testbot');
});

it('generates embeddings with text-embedding-3-small', function () {
    $vector = $this->ai->embedText('Hello world test sentence', 'openai');

    expect($vector)->toBeArray()->not->toBeEmpty();
    expect(count($vector))->toBeGreaterThan(100); // embedding dimension
    expect($vector[0])->toBeFloat();
});
```

---

## FILE 5: `tests/Integration/Providers/Text/AnthropicProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group anthropic
 */

beforeEach(function () {
    $this->skipUnless('TEST_ANTHROPIC_KEY', 'Anthropic');
    $this->ai = app(\App\Services\AI\AiService::class);
});

it('completes with claude-haiku-4-5', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('anthropic', 'claude-haiku-4-5')
    );
    $this->assertValidCompletion($response);
    expect($response->provider)->toBe('anthropic');
});

it('completes with claude-sonnet-4-5', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('anthropic', 'claude-sonnet-4-5')
    );
    $this->assertValidCompletion($response);
});

it('streams tokens from claude-haiku', function () {
    $chunks = [];
    foreach ($this->ai->stream($this->simpleRequest('anthropic', 'claude-haiku-4-5', 'Say hello.', 20)) as $chunk) {
        $chunks[] = $chunk;
    }
    expect($chunks)->not->toBeEmpty();
});

it('handles multi-turn conversation', function () {
    $request = new \App\DTOs\AI\CompletionRequest(
        provider: 'anthropic',
        model:    'claude-haiku-4-5',
        messages: [
            ['role' => 'user',      'content' => 'Remember the number 42.'],
            ['role' => 'assistant', 'content' => 'I will remember the number 42.'],
            ['role' => 'user',      'content' => 'What number should I remember?'],
        ],
        maxTokens:   20,
        temperature: 0.0,
    );
    $response = $this->ai->complete($request);

    expect($response->content)->toContain('42');
});

it('throws ProviderAuthException on invalid key', function () {
    settings_set('anthropic_key', 'sk-ant-invalid', 'encrypted');
    expect(fn() => $this->ai->complete(
        $this->simpleRequest('anthropic', 'claude-haiku-4-5')
    ))->toThrow(\App\Exceptions\AI\ProviderAuthException::class);
});
```

---

## FILE 6: `tests/Integration/Providers/Text/GeminiProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group gemini
 */

beforeEach(function () {
    $this->skipUnless('TEST_GEMINI_KEY', 'Google Gemini');
    $this->ai = app(\App\Services\AI\AiService::class);
});

it('completes with gemini-2.0-flash', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('google', 'gemini-2.0-flash')
    );
    $this->assertValidCompletion($response);
    expect($response->provider)->toBe('google');
});

it('completes with gemini-2.5-flash', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('google', 'gemini-2.5-flash')
    );
    $this->assertValidCompletion($response);
});

it('streams from gemini-2.0-flash', function () {
    $chunks = [];
    foreach ($this->ai->stream($this->simpleRequest('google', 'gemini-2.0-flash', 'Say hello.', 20)) as $chunk) {
        $chunks[] = $chunk;
    }
    expect($chunks)->not->toBeEmpty();
});

it('generates embeddings with text-embedding-004', function () {
    $vector = $this->ai->embedText('Test embedding sentence', 'google');
    expect($vector)->toBeArray()->not->toBeEmpty();
    expect($vector[0])->toBeFloat();
});
```

---

## FILE 7: `tests/Integration/Providers/Text/DeepSeekProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group deepseek
 */

beforeEach(function () {
    $this->skipUnless('TEST_DEEPSEEK_KEY', 'DeepSeek');
    $this->ai = app(\App\Services\AI\AiService::class);
});

it('completes with deepseek-chat (V3)', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('deepseek', 'deepseek-chat')
    );
    $this->assertValidCompletion($response);
    expect($response->provider)->toBe('deepseek');
});

it('completes with deepseek-reasoner (R1)', function () {
    // R1 returns reasoning_content + content — test that we extract content correctly
    $response = $this->ai->complete(
        $this->simpleRequest('deepseek', 'deepseek-reasoner', 'What is 2+2?', 50)
    );
    $this->assertValidCompletion($response);
    expect($response->content)->toContain('4');
});

it('streams from deepseek-chat', function () {
    $chunks = [];
    foreach ($this->ai->stream($this->simpleRequest('deepseek', 'deepseek-chat', 'Hi.', 10)) as $chunk) {
        $chunks[] = $chunk;
    }
    expect($chunks)->not->toBeEmpty();
});
```

---

## FILE 8: `tests/Integration/Providers/Text/GroqProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group groq
 */

beforeEach(function () {
    $this->skipUnless('TEST_GROQ_KEY', 'Groq');
    $this->ai = app(\App\Services\AI\AiService::class);
});

it('completes with llama-3.3-70b-versatile', function () {
    $response = $this->ai->complete(
        $this->simpleRequest('groq', 'llama-3.3-70b-versatile')
    );
    $this->assertValidCompletion($response);
    expect($response->provider)->toBe('groq');
});

it('completes ultra-fast (Groq speed test)', function () {
    $start    = microtime(true);
    $response = $this->ai->complete(
        $this->simpleRequest('groq', 'llama-3.1-8b-instant', 'Say: FAST', 5)
    );
    $elapsed = microtime(true) - $start;

    $this->assertValidCompletion($response);
    // Groq should respond in under 3 seconds even for first token
    expect($elapsed)->toBeLessThan(5.0);
});

it('streams from groq', function () {
    $chunks = [];
    foreach ($this->ai->stream($this->simpleRequest('groq', 'llama-3.1-8b-instant', 'Hi.', 10)) as $chunk) {
        $chunks[] = $chunk;
    }
    expect($chunks)->not->toBeEmpty();
});
```

---

## FILE 9: Additional text provider tests (create same pattern for each)

Create these files following the exact same pattern as above:

### `tests/Integration/Providers/Text/XaiProviderTest.php`
- `skipUnless('TEST_XAI_KEY', 'xAI')`
- Models to test: `grok-3-mini`, `grok-3`
- provider string: `'xai'`

### `tests/Integration/Providers/Text/MistralProviderTest.php`
- `skipUnless('TEST_MISTRAL_KEY', 'Mistral')`
- Models: `mistral-small-latest`, `mistral-large-latest`
- provider string: `'mistral'`

### `tests/Integration/Providers/Text/OpenRouterProviderTest.php`
- `skipUnless('TEST_OPENROUTER_KEY', 'OpenRouter')`
- Models: `openai/gpt-4o-mini` (via OpenRouter), `anthropic/claude-haiku-4-5`
- provider string: `'openrouter'`
- Extra test: verify that model string passes through as-is to OpenRouter

### `tests/Integration/Providers/Text/PerplexityProviderTest.php`
- `skipUnless('TEST_PERPLEXITY_KEY', 'Perplexity')`
- Models: `sonar`, `sonar-pro`
- provider string: `'perplexity'`
- Extra test: verify response includes citations (Perplexity-specific feature)

### `tests/Integration/Providers/Text/CohereProviderTest.php`
- `skipUnless('TEST_COHERE_KEY', 'Cohere')`
- Models: `command-r-plus`, `command-r`
- provider string: `'cohere'`
- Extra test: embedding with `embed-english-v3.0`

---

## FILE 10: `tests/Integration/Providers/Image/DalleProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group dalle
 * @group image
 */

beforeEach(function () {
    $this->skipUnless('TEST_OPENAI_KEY', 'DALL-E (OpenAI)');
    $this->imageService = app(\App\Services\AI\ImageService::class);
});

it('generates an image with dall-e-3', function () {
    $result = $this->imageService->generate(new \App\DTOs\AI\ImageRequest(
        provider: 'openai',
        model:    'dall-e-3',
        prompt:   'A simple white square on black background',
        size:     '1024x1024',
        quality:  'standard',
        n:        1,
    ));

    expect($result->imageUrl)->toBeString()->toStartWith('http');
    expect($result->revisedPrompt)->toBeString(); // DALL-E always returns revised prompt
    expect($result->provider)->toBe('openai');
});

it('generates with dall-e-2', function () {
    $result = $this->imageService->generate(new \App\DTOs\AI\ImageRequest(
        provider: 'openai',
        model:    'dall-e-2',
        prompt:   'A simple white square',
        size:     '512x512',
        n:        1,
    ));
    expect($result->imageUrl)->toBeString()->toStartWith('http');
});

it('throws on invalid size for dall-e-3', function () {
    expect(fn() => $this->imageService->generate(new \App\DTOs\AI\ImageRequest(
        provider: 'openai',
        model:    'dall-e-3',
        prompt:   'test',
        size:     '512x512', // invalid for dall-e-3
        n:        1,
    )))->toThrow(\App\Exceptions\AI\ProviderValidationException::class);
});
```

---

## FILE 11: `tests/Integration/Providers/Image/FluxProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group flux
 * @group image
 */

beforeEach(function () {
    $this->skipUnless('TEST_FAL_KEY', 'Flux (fal.ai)');
    $this->imageService = app(\App\Services\AI\ImageService::class);
});

it('generates an image with flux-pro', function () {
    $result = $this->imageService->generate(new \App\DTOs\AI\ImageRequest(
        provider:        'fal',
        model:           'fal-ai/flux-pro',
        prompt:          'A simple geometric pattern, minimalist',
        width:           1024,
        height:          1024,
        numInferenceSteps: 25,
    ));

    expect($result->imageUrl)->toBeString()->toStartWith('http');
    expect($result->provider)->toBe('fal');
});

it('generates with flux/schnell (faster)', function () {
    $start  = microtime(true);
    $result = $this->imageService->generate(new \App\DTOs\AI\ImageRequest(
        provider: 'fal',
        model:    'fal-ai/flux/schnell',
        prompt:   'Simple blue circle',
        width:    512,
        height:   512,
        numInferenceSteps: 4, // schnell is fast
    ));
    $elapsed = microtime(true) - $start;

    expect($result->imageUrl)->toBeString();
    expect($elapsed)->toBeLessThan(30.0); // schnell should be fast
});
```

---

## FILE 12: `tests/Integration/Providers/Voice/ElevenLabsProviderTest.php`

```php
<?php

/**
 * @group integration
 * @group elevenlabs
 * @group voice
 */

beforeEach(function () {
    $this->skipUnless('TEST_ELEVENLABS_KEY', 'ElevenLabs');
    $this->voiceService = app(\App\Services\AI\VoiceService::class);
});

it('generates speech audio from text', function () {
    $result = $this->voiceService->textToSpeech(new \App\DTOs\AI\TtsRequest(
        provider: 'elevenlabs',
        text:     'Hello, this is a test.',
        voiceId:  'rachel', // built-in ElevenLabs voice
        model:    'eleven_turbo_v2',
    ));

    expect($result->audioData)->toBeString()->not->toBeEmpty();
    expect($result->mimeType)->toBe('audio/mpeg');
    expect(strlen($result->audioData))->toBeGreaterThan(1000); // non-trivial audio
});

it('generates speech with openai TTS as fallback', function () {
    $this->skipUnless('TEST_OPENAI_KEY', 'OpenAI TTS');

    $result = $this->voiceService->textToSpeech(new \App\DTOs\AI\TtsRequest(
        provider: 'openai',
        text:     'Hello, this is a test.',
        voice:    'alloy',
        model:    'tts-1',
    ));

    expect($result->audioData)->toBeString()->not->toBeEmpty();
    expect($result->mimeType)->toBe('audio/mpeg');
});

it('transcribes audio with whisper', function () {
    $this->skipUnless('TEST_OPENAI_KEY', 'Whisper');

    // Use a real short audio file from test fixtures
    $audioPath = base_path('tests/fixtures/test_audio.mp3');
    if (!file_exists($audioPath)) {
        $this->markTestSkipped('Test audio fixture missing: tests/fixtures/test_audio.mp3');
    }

    $result = $this->voiceService->speechToText(new \App\DTOs\AI\SttRequest(
        provider:  'openai',
        model:     'whisper-1',
        audioPath: $audioPath,
        language:  'en',
    ));

    expect($result->transcript)->toBeString()->not->toBeEmpty();
    expect($result->language)->toBe('en');
});
```

---

## FILE 13: `tests/Integration/Connectivity/ProviderHealthCheckTest.php`

This is the "ping all providers" test — quick connectivity check, not full completion:

```php
<?php

/**
 * @group integration
 * @group health
 */

use App\Services\AI\ProviderRegistry;

it('checks health of all configured text providers', function () {
    $registry = app(ProviderRegistry::class);
    $results  = [];

    $providers = [
        'openai'      => ['env' => 'TEST_OPENAI_KEY',     'model' => 'gpt-4o-mini'],
        'anthropic'   => ['env' => 'TEST_ANTHROPIC_KEY',  'model' => 'claude-haiku-4-5'],
        'google'      => ['env' => 'TEST_GEMINI_KEY',      'model' => 'gemini-2.0-flash'],
        'deepseek'    => ['env' => 'TEST_DEEPSEEK_KEY',   'model' => 'deepseek-chat'],
        'xai'         => ['env' => 'TEST_XAI_KEY',        'model' => 'grok-3-mini'],
        'mistral'     => ['env' => 'TEST_MISTRAL_KEY',    'model' => 'mistral-small-latest'],
        'groq'        => ['env' => 'TEST_GROQ_KEY',       'model' => 'llama-3.1-8b-instant'],
        'openrouter'  => ['env' => 'TEST_OPENROUTER_KEY', 'model' => 'openai/gpt-4o-mini'],
        'perplexity'  => ['env' => 'TEST_PERPLEXITY_KEY', 'model' => 'sonar'],
        'cohere'      => ['env' => 'TEST_COHERE_KEY',     'model' => 'command-r'],
    ];

    $ai = app(\App\Services\AI\AiService::class);

    foreach ($providers as $provider => $config) {
        if (empty(env($config['env']))) {
            $results[$provider] = 'skipped (no key)';
            continue;
        }

        try {
            $response = $ai->complete($this->simpleRequest(
                $provider,
                $config['model'],
                'Reply: OK',
                5
            ));
            $results[$provider] = $response->content ? '✅ OK' : '❌ empty response';
        } catch (\App\Exceptions\AI\ProviderAuthException $e) {
            $results[$provider] = '❌ auth error: ' . $e->getMessage();
        } catch (\App\Exceptions\AI\ProviderRateLimitException $e) {
            $results[$provider] = '⚠️ rate limited';
        } catch (\Throwable $e) {
            $results[$provider] = '❌ error: ' . class_basename($e) . ': ' . $e->getMessage();
        }
    }

    // Print results table
    echo "\n\n=== Provider Health Check Results ===\n";
    foreach ($results as $provider => $status) {
        echo sprintf("  %-15s %s\n", $provider, $status);
    }
    echo "=====================================\n\n";

    // At least one provider must be healthy
    $healthy = collect($results)->filter(fn($s) => str_contains($s, '✅'))->count();
    expect($healthy)->toBeGreaterThan(0, 'At least one provider must be healthy');
})->group('health');
```

---

## FILE 14: `tests/Feature/AI/ProviderRegistryTest.php` (CI-safe mock tests)

```php
<?php

use App\Services\AI\ProviderRegistry;

it('returns enabled providers only', function () {
    settings_set('openai_enabled', true);
    settings_set('anthropic_enabled', false);
    settings_set('gemini_enabled', true);

    $registry = app(ProviderRegistry::class);
    $enabled  = $registry->getEnabledProviders();

    expect($enabled)->toContain('openai');
    expect($enabled)->toContain('google');
    expect($enabled)->not->toContain('anthropic');
});

it('resolves correct provider for a given model string', function () {
    $registry = app(ProviderRegistry::class);

    expect($registry->resolveProviderForModel('gpt-4o'))->toBe('openai');
    expect($registry->resolveProviderForModel('claude-haiku-4-5'))->toBe('anthropic');
    expect($registry->resolveProviderForModel('gemini-2.0-flash'))->toBe('google');
    expect($registry->resolveProviderForModel('deepseek-chat'))->toBe('deepseek');
    expect($registry->resolveProviderForModel('grok-3'))->toBe('xai');
    expect($registry->resolveProviderForModel('llama-3.1-8b-instant'))->toBe('groq');
});

it('round-robins multiple keys for the same provider', function () {
    // Simulate 3 OpenAI keys
    settings_set('openai_keys', json_encode([
        'sk-key-1', 'sk-key-2', 'sk-key-3',
    ]), 'json');

    $registry = app(ProviderRegistry::class);

    $key1 = $registry->getNextKeyForProvider('openai');
    $key2 = $registry->getNextKeyForProvider('openai');
    $key3 = $registry->getNextKeyForProvider('openai');
    $key4 = $registry->getNextKeyForProvider('openai'); // wraps back to first

    expect($key1)->not->toBe($key2);
    expect($key2)->not->toBe($key3);
    expect($key4)->toBe($key1); // round-robin wrapped
});

it('triggers failover when primary provider returns rate limit error', function () {
    settings_set('openai_enabled', true);
    settings_set('anthropic_enabled', true);

    // Primary (openai) throws rate limit
    $this->mock(\App\Services\AI\Providers\OpenAiProvider::class, function ($mock) {
        $mock->shouldReceive('complete')
            ->once()
            ->andThrow(new \App\Exceptions\AI\ProviderRateLimitException('openai', 60));
    });

    // Anthropic (fallback) succeeds
    $this->mock(\App\Services\AI\Providers\AnthropicProvider::class, function ($mock) {
        $mock->shouldReceive('complete')
            ->once()
            ->andReturn(new \App\DTOs\AI\CompletionResponse(
                content:      'Fallback response',
                inputTokens:  10,
                outputTokens: 5,
                model:        'claude-haiku-4-5',
                provider:     'anthropic',
            ));
    });

    $ai       = app(\App\Services\AI\AiService::class);
    $response = $ai->complete(new \App\DTOs\AI\CompletionRequest(
        provider:  'auto', // auto = ProviderRegistry picks
        model:     null,
        messages:  [['role' => 'user', 'content' => 'hello']],
        maxTokens: 10,
    ));

    expect($response->provider)->toBe('anthropic'); // used fallback
    expect($response->content)->toBe('Fallback response');
});

it('throws ProviderUnavailableException when all providers fail', function () {
    settings_set('openai_enabled', true);
    settings_set('anthropic_enabled', false);
    settings_set('gemini_enabled', false);

    $this->mock(\App\Services\AI\Providers\OpenAiProvider::class, function ($mock) {
        $mock->shouldReceive('complete')
            ->andThrow(new \App\Exceptions\AI\ProviderRateLimitException('openai', 60));
    });

    expect(fn() => app(\App\Services\AI\AiService::class)->complete(
        new \App\DTOs\AI\CompletionRequest(
            provider: 'auto', model: null,
            messages: [['role' => 'user', 'content' => 'hello']],
            maxTokens: 10,
        )
    ))->toThrow(\App\Exceptions\AI\ProviderUnavailableException::class);
});
```

---

## FILE 15: `tests/Feature/AI/StreamingContractTest.php` (CI-safe)

```php
<?php

use App\Services\AI\AiService;
use App\DTOs\AI\CompletionRequest;

it('stream() returns a Generator', function () {
    $this->mock(AiService::class, function ($mock) {
        $mock->shouldReceive('stream')
            ->once()
            ->andReturnUsing(function () {
                yield 'Hello';
                yield ' world';
                yield '!';
            });
    });

    $ai     = app(AiService::class);
    $stream = $ai->stream(new CompletionRequest(
        provider: 'openai', model: 'gpt-4o-mini',
        messages: [['role' => 'user', 'content' => 'hi']],
        maxTokens: 5,
    ));

    expect($stream)->toBeInstanceOf(Generator::class);

    $collected = [];
    foreach ($stream as $chunk) {
        $collected[] = $chunk;
    }

    expect($collected)->toBe(['Hello', ' world', '!']);
    expect(implode('', $collected))->toBe('Hello world!');
});

it('SSE endpoint returns correct headers', function () {
    $user = \App\Models\User::factory()->withCredits(1000)->create();

    $this->mock(AiService::class, function ($mock) {
        $mock->shouldReceive('stream')
            ->andReturnUsing(function () {
                yield 'test chunk';
            });
    });

    $response = $this->actingAs($user)
        ->post('/api/v1/generate/stream', [
            'tool'   => 'blog-writer',
            'inputs' => ['topic' => 'test'],
        ]);

    $response->assertHeader('Content-Type', 'text/event-stream');
    $response->assertHeader('X-Accel-Buffering', 'no');
    $response->assertHeader('Cache-Control', 'no-cache');
});

it('stream closes gracefully on client disconnect', function () {
    // Verify finally block runs even when connection drops
    $finallyRan = false;

    $this->mock(AiService::class, function ($mock) use (&$finallyRan) {
        $mock->shouldReceive('stream')
            ->andReturnUsing(function () use (&$finallyRan) {
                try {
                    yield 'chunk 1';
                    yield 'chunk 2';
                } finally {
                    $finallyRan = true; // MUST run even on disconnect
                }
            });
    });

    $user = \App\Models\User::factory()->withCredits(1000)->create();
    $this->actingAs($user)->post('/api/v1/generate/stream', [
        'tool'   => 'blog-writer',
        'inputs' => ['topic' => 'test'],
    ]);

    expect($finallyRan)->toBeTrue();
});
```

---

## FILE 16: Custom Exceptions to create (if they don't exist)

```
app/Exceptions/AI/ProviderAuthException.php
    — thrown when API key is invalid (401/403 from provider)
    — extends \RuntimeException
    — properties: string $provider

app/Exceptions/AI/ProviderRateLimitException.php
    — thrown when provider returns 429
    — properties: string $provider, int $retryAfterSeconds

app/Exceptions/AI/ProviderUnavailableException.php
    — thrown when all configured providers fail or none are configured
    — properties: array $triedProviders

app/Exceptions/AI/ProviderValidationException.php
    — thrown when request params are invalid for the provider (e.g. wrong image size)
    — properties: string $provider, string $field, string $reason
```

Each exception:
```php
<?php
namespace App\Exceptions\AI;

class ProviderAuthException extends \RuntimeException
{
    public function __construct(
        public readonly string $provider,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: "Authentication failed for provider: {$provider}",
            $code,
            $previous
        );
    }
}
```

---

## FILE 17: `tests/fixtures/` directory

Create `tests/fixtures/test_audio.mp3` — a real but tiny MP3 file for Whisper testing.
You can generate it with:

```bash
# One-time: create a test audio fixture using PHP
php artisan tinker
# Or just download a public domain short audio file
# The file needs to be a real MP3 — Whisper rejects empty files
```

Add to `.gitignore`:
```
# Keep fixture in repo (it's tiny and needed for STT tests)
!tests/fixtures/test_audio.mp3
```

---

## HOW TO RUN

```bash
# ─── CI-safe (mock tests only) ───────────────────────────────────
./vendor/bin/pest tests/Feature/AI/ tests/Unit/

# ─── All integration tests (needs .env.integration) ──────────────
cp .env.integration.example .env.integration
# Add real keys to .env.integration
PEST_GROUPS=integration ./vendor/bin/pest tests/Integration/ --group=integration

# ─── Single provider ─────────────────────────────────────────────
PEST_GROUPS=integration ./vendor/bin/pest --group=openai
PEST_GROUPS=integration ./vendor/bin/pest --group=anthropic
PEST_GROUPS=integration ./vendor/bin/pest --group=deepseek
PEST_GROUPS=integration ./vendor/bin/pest --group=groq

# ─── Health check only (quick ping all providers) ────────────────
PEST_GROUPS=integration ./vendor/bin/pest --group=health -v

# ─── Image providers ─────────────────────────────────────────────
PEST_GROUPS=integration ./vendor/bin/pest --group=image

# ─── Voice providers ─────────────────────────────────────────────
PEST_GROUPS=integration ./vendor/bin/pest --group=voice

# ─── With verbose output ─────────────────────────────────────────
PEST_GROUPS=integration ./vendor/bin/pest tests/Integration/ --group=integration -v

# ─── Parallel (careful: multiple real API calls simultaneously) ───
PEST_GROUPS=integration ./vendor/bin/pest tests/Integration/ --group=integration --parallel
```

---

## GitHub Actions: Optional Integration CI Job

```yaml
# .github/workflows/integration-tests.yml
name: Provider Integration Tests
on:
  schedule:
    - cron: '0 6 * * *'  # daily at 6am — checks all providers are still up
  workflow_dispatch:       # manual trigger

jobs:
  integration:
    runs-on: ubuntu-latest
    env:
      TEST_OPENAI_KEY:     ${{ secrets.TEST_OPENAI_KEY }}
      TEST_ANTHROPIC_KEY:  ${{ secrets.TEST_ANTHROPIC_KEY }}
      TEST_GEMINI_KEY:     ${{ secrets.TEST_GEMINI_KEY }}
      TEST_DEEPSEEK_KEY:   ${{ secrets.TEST_DEEPSEEK_KEY }}
      TEST_GROQ_KEY:       ${{ secrets.TEST_GROQ_KEY }}
      PEST_GROUPS:         integration
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-dev
      - run: cp .env.testing .env && php artisan key:generate
      - run: php artisan migrate --env=testing
      - run: ./vendor/bin/pest tests/Integration/ --group=integration -v
```

---

## CHECKLIST

- [ ] `IntegrationTestCase` loads keys from `.env.integration` into settings table
- [ ] `skipUnless()` helper skips gracefully when a provider key is missing
- [ ] Every provider test file has `@group integration` + provider-specific group
- [ ] `ProviderHealthCheckTest` prints a readable results table
- [ ] All 4 custom exceptions created with correct properties
- [ ] CI-safe tests in `tests/Feature/AI/` run with zero API calls
- [ ] Streaming contract tests verify `X-Accel-Buffering: no` header
- [ ] `finally` block test confirms cleanup runs on disconnect
- [ ] Failover test verifies primary→fallback chain with mocks
- [ ] Round-robin key rotation test covers wrap-around
- [ ] `.env.integration.example` has all provider key names listed
- [ ] `tests/fixtures/test_audio.mp3` exists for Whisper test
- [ ] `./vendor/bin/pest tests/Feature/AI/` passes in CI (no real calls)
- [ ] `PEST_GROUPS=integration ./vendor/bin/pest --group=health` prints provider table

---

## NOTES FOR DEEPSEEK

1. **`laravel/ai` SDK** — use `AiService::complete(CompletionRequest $request)` for all calls.
   The `CompletionRequest` DTO fields: `provider`, `model`, `messages`, `systemPrompt`,
   `maxTokens`, `temperature`, `topP`. Never use raw Guzzle/HTTP client in tests.

2. **Provider strings** — must match exactly what `ProviderRegistry` uses:
   `openai`, `anthropic`, `google`, `deepseek`, `xai`, `mistral`, `groq`,
   `openrouter`, `perplexity`, `cohere`, `fal`, `stability`, `replicate`, `elevenlabs`

3. **Image provider** — `ImageService::generate(ImageRequest $request)` returns
   `ImageResponse { imageUrl, revisedPrompt, provider, model, costUsd }`

4. **Voice provider** — `VoiceService::textToSpeech(TtsRequest)` returns
   `TtsResponse { audioData (binary string), mimeType, provider, durationSeconds }`

5. **`settings_set()` in IntegrationTestCase** — this writes to the real settings table
   (using SQLite in-memory for tests). The `ProviderRegistry` reads from `settings()` so
   this is the correct way to inject test keys without touching env files.

6. **No hardcoded "MakeAI"** anywhere in test strings.

7. **`ai_tools` table** — not `ai_templates`. If any test references templates, change to tools.

