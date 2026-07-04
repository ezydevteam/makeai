<?php

namespace App\Services\Security;

use RuntimeException;

/**
 * SsrfGuard — validates user-supplied URLs before the server fetches them.
 *
 * Blocks requests to loopback, link-local (cloud metadata), private and
 * reserved IP ranges, for both IP-literal hosts and DNS-resolved hosts.
 * Use for every server-side fetch of a user-provided URL (RAG URL ingestion,
 * web scraping, link previews).
 */
class SsrfGuard
{
    /**
     * @throws RuntimeException when the URL must not be fetched server-side
     */
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new RuntimeException(translate('The URL is not valid.'));
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(translate('Only http and https URLs are allowed.'));
        }

        $host = strtolower(trim($parts['host'], '[]'));

        // IP literal host
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! self::isPublicIp($host)) {
                throw new RuntimeException(translate('This URL points to a restricted network address.'));
            }

            return;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new RuntimeException(translate('This URL points to a restricted network address.'));
        }

        // Resolve DNS (A + AAAA) and validate every address
        $addresses = [];

        $ipv4 = @gethostbynamel($host);
        if (is_array($ipv4)) {
            $addresses = array_merge($addresses, $ipv4);
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if (empty($addresses)) {
            throw new RuntimeException(translate('The URL host could not be resolved.'));
        }

        foreach ($addresses as $address) {
            if (! self::isPublicIp($address)) {
                throw new RuntimeException(translate('This URL points to a restricted network address.'));
            }
        }
    }

    /**
     * True when the IP is a publicly routable address (not private/reserved/loopback/link-local).
     */
    public static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Guzzle redirect options that re-validate every redirect target.
     */
    public static function redirectOptions(int $maxRedirects = 3): array
    {
        return [
            'max' => $maxRedirects,
            'strict' => true,
            'referer' => false,
            'on_redirect' => function ($request, $response, $uri): void {
                self::assertPublicUrl((string) $uri);
            },
        ];
    }
}
