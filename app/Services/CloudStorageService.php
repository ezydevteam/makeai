<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Settings-driven media storage.
 *
 * The admin picks a storage driver on Settings → Storage. `local` (default) keeps
 * files on the server; any S3-compatible driver (AWS S3, Cloudflare R2, DigitalOcean
 * Spaces, Wasabi, Backblaze B2) rebinds Laravel's `public` disk to the configured
 * bucket at boot — so every existing Storage::disk('public') call transparently
 * reads and writes media from the cloud with no call-site changes.
 *
 * Credentials live encrypted in the settings table; nothing is read from .env.
 */
class CloudStorageService
{
    /** Supported drivers. `local` is the built-in filesystem; the rest are S3-compatible. */
    public const DRIVERS = ['local', 's3', 'r2', 'spaces', 'wasabi', 'b2'];

    /**
     * Cloud drivers that support per-object S3 ACLs. AWS S3, DigitalOcean Spaces and
     * Wasabi honour `x-amz-acl: public-read`; Cloudflare R2 and Backblaze B2 do NOT —
     * they reject (B2) or ignore (R2) the ACL header, so sending `visibility => public`
     * makes every PutObject fail (breaking the connection test) or silently no-op.
     * Public access on R2/B2 is granted at the bucket level plus a public/custom domain,
     * which the admin supplies as the `url` field.
     */
    public const ACL_CAPABLE_DRIVERS = ['s3', 'spaces', 'wasabi'];

    private string $driver;
    private ?string $accessKey;
    private ?string $secretKey;
    private ?string $region;
    private ?string $bucket;
    private ?string $endpoint;
    private ?string $url;

    public function __construct(
        string $driver = 'local',
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?string $region = null,
        ?string $bucket = null,
        ?string $endpoint = null,
        ?string $url = null
    ) {
        $this->driver = in_array($driver, self::DRIVERS, true) ? $driver : 'local';
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->bucket = $bucket;
        $this->endpoint = $endpoint;
        $this->url = $url;
    }

    public static function fromSettings(): self
    {
        $driver = (string) settings('storage_driver', 'local');

        return self::forDriver($driver);
    }

    /**
     * Build an instance for a specific driver from its stored credentials.
     * Used by the settings page to test a driver before it is activated.
     */
    public static function forDriver(string $driver): self
    {
        $driver = in_array($driver, self::DRIVERS, true) ? $driver : 'local';

        if ($driver === 'local') {
            return new self('local');
        }

        return new self(
            driver: $driver,
            accessKey: settings("storage_{$driver}_access_key"),
            secretKey: settings("storage_{$driver}_secret_key"),
            region: settings("storage_{$driver}_region"),
            bucket: settings("storage_{$driver}_bucket"),
            endpoint: settings("storage_{$driver}_endpoint"),
            url: settings("storage_{$driver}_url"),
        );
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isCloud(): bool
    {
        return $this->driver !== 'local';
    }

    public function isConfigured(): bool
    {
        if (! $this->isCloud()) {
            return true;
        }

        return filled($this->accessKey) && filled($this->secretKey) && filled($this->bucket);
    }

    /**
     * Cloud is "active" only when a cloud driver is selected AND fully configured.
     * Guards {@see apply()} so a half-configured driver never breaks file serving.
     */
    public function isActive(): bool
    {
        return $this->isCloud() && $this->isConfigured() && self::s3DriverAvailable();
    }

    /**
     * Whether the Flysystem S3 adapter is installed. Guards {@see apply()} so a
     * missing dependency can never rebind the `public` disk to an unbuildable
     * driver and break media serving site-wide.
     */
    public static function s3DriverAvailable(): bool
    {
        return class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class);
    }

    /**
     * Flysystem S3 disk config for the configured provider. Custom endpoints
     * (R2/Spaces/Wasabi/B2) use path-style addressing; pure AWS uses virtual-hosted.
     */
    public function diskConfig(): array
    {
        $config = [
            'driver' => 's3',
            'key' => $this->accessKey,
            'secret' => $this->secretKey,
            'region' => $this->region ?: 'us-east-1',
            'bucket' => $this->bucket,
            'url' => $this->url ?: null,
            'endpoint' => $this->endpoint ?: null,
            'use_path_style_endpoint' => filled($this->endpoint),
            'throw' => false,
            'report' => false,
        ];

        // Only send an ACL on providers that support one. On R2/B2 an ACL-bearing
        // PutObject is rejected/ignored, so we omit `visibility` entirely and rely on
        // bucket-level public access. `retain_visibility => false` stops Flysystem from
        // attempting to read/copy ACLs during operations like copy() on those providers.
        if (in_array($this->driver, self::ACL_CAPABLE_DRIVERS, true)) {
            $config['visibility'] = 'public';
        } else {
            $config['retain_visibility'] = false;
        }

        return $config;
    }

    /**
     * Rebind the `public` disk to the cloud bucket when a configured cloud driver
     * is active. No-op for `local` or a half-configured driver. Called at boot.
     */
    public function apply(): void
    {
        if (! $this->isActive()) {
            return;
        }

        config(['filesystems.disks.public' => $this->diskConfig()]);
    }

    /**
     * Verify credentials with a real write→read→delete round-trip against the bucket.
     * Returns the same shape as the other extension services' testConnection().
     */
    public function testConnection(): array
    {
        if (! $this->isCloud()) {
            return ['success' => true, 'message' => translate('Local storage is always available.')];
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('Enter the access key, secret key, and bucket first.')];
        }

        if (! self::s3DriverAvailable()) {
            return ['success' => false, 'error' => translate('The S3 storage driver is not installed. Run "composer require league/flysystem-aws-s3-v3" on the server, then try again.')];
        }

        try {
            // Force the probe disk to throw so the real provider error (AccessDenied,
            // NoSuchBucket, bad region…) surfaces to the admin. Runtime serving keeps
            // throw=false so a transient hiccup degrades instead of 500ing the site.
            $disk = Storage::build(['throw' => true] + $this->diskConfig());
            $probe = 'healthcheck/'.Str::random(24).'.txt';
            $payload = 'makeai-storage-probe';

            $disk->put($probe, $payload);
            $readBack = $disk->get($probe);
            $disk->delete($probe);

            if ($readBack !== $payload) {
                return ['success' => false, 'error' => translate('Wrote to the bucket but read back unexpected data. Check bucket permissions.')];
            }

            return ['success' => true, 'message' => translate('Connection succeeded — write, read, and delete all worked.')];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $this->rootCauseMessage($e)];
        }
    }

    /**
     * Flysystem wraps provider errors in a generic "Unable to write…" message; the
     * actionable reason (AccessDenied, invalid key, wrong region) lives in the
     * previous-exception chain. Return the deepest message so admins see the cause.
     */
    private function rootCauseMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        while ($previous = $e->getPrevious()) {
            $e = $previous;
            if (filled($e->getMessage())) {
                $message = $e->getMessage();
            }
        }

        return $message;
    }
}
