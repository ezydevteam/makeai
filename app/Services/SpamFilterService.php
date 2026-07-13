<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SpamFilterService
{
    private bool $enabled;
    private ?string $apiKey;
    private ?string $siteUrl;

    public function __construct(bool $enabled = false, ?string $apiKey = null, ?string $siteUrl = null)
    {
        $this->enabled = $enabled;
        $this->apiKey = $apiKey;
        $this->siteUrl = $siteUrl;
    }

    public static function fromSettings(): self
    {
        return new self(
            enabled: (bool) settings('external_spam_filter_enabled', false),
            apiKey: settings('external_spam_filter_akismet_api_key'),
            siteUrl: settings('external_spam_filter_akismet_site_url'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Active means the admin turned the extension on AND supplied credentials.
     * When inactive, {@see check()} fails open so it never blocks legitimate content.
     */
    public function isActive(): bool
    {
        return $this->enabled && $this->isConfigured();
    }

    /**
     * Ask Akismet whether a submission is spam.
     *
     * Fails OPEN: returns false when the filter is inactive or the API errors,
     * so an outage or a missing key never silently swallows real submissions.
     *
     * @param  array{content?: string, author?: ?string, email?: ?string, ip?: ?string, user_agent?: ?string, referrer?: ?string, permalink?: ?string, type?: string}  $comment
     */
    public function check(array $comment): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $payload = array_filter([
            'blog' => $this->siteUrl ?: config('app.url'),
            'user_ip' => $comment['ip'] ?? request()->ip(),
            'user_agent' => $comment['user_agent'] ?? request()->userAgent(),
            'referrer' => $comment['referrer'] ?? request()->header('referer'),
            'permalink' => $comment['permalink'] ?? null,
            'comment_type' => $comment['type'] ?? 'comment',
            'comment_author' => $comment['author'] ?? null,
            'comment_author_email' => $comment['email'] ?? null,
            'comment_content' => $comment['content'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::timeout(max(5, (int) settings('external_spam_filter_timeout', 15)))
                ->asForm()
                ->post("https://{$this->apiKey}.rest.akismet.com/1.1/comment-check", $payload);

            // Akismet returns the literal string "true" for spam, "false" for ham.
            return trim($response->body()) === 'true';
        } catch (Throwable) {
            return false;
        }
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('No API key configured.')];
        }

        try {
            $response = Http::timeout(15)
                ->asForm()
                ->post('https://rest.akismet.com/1.1/verify-key', [
                    'key' => $this->apiKey,
                    'blog' => $this->siteUrl ?: config('app.url'),
                ]);

            return trim($response->body()) === 'valid'
                ? ['success' => true, 'message' => translate('Akismet API key is valid.')]
                : ['success' => false, 'error' => translate('Akismet rejected the API key.')];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
