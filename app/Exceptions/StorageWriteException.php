<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a write to the media (public) disk fails.
 *
 * The public disk runs with `throw => false` so a transient cloud hiccup degrades
 * instead of 500-ing the whole site (see CloudStorageService). That means store()/put()
 * return `false` on failure instead of throwing — and callers must not treat that as
 * success. {@see store_public_upload()} raises this exception on a false return so the
 * failure is surfaced to the admin (flashed error on web, 500 JSON on API) rather than
 * silently persisting a broken path.
 */
class StorageWriteException extends RuntimeException
{
    public static function forUpload(): self
    {
        return new self(translate('The file could not be saved to storage. Please verify your storage settings (Settings → Storage) and try again.'));
    }
}
