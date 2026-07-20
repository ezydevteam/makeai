<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise every stored media reference to a RELATIVE disk key.
 *
 * Historically some writers persisted a URL instead of a key — Storage::url() output like
 * "/storage/blog/x.jpg" (ads.image_url, languages.flag, blog_posts.featured_image), while
 * every other column stored a bare key ("blog/x.jpg"). Those baked-in "/storage/..." values
 * 404 the moment media moves to an S3-compatible bucket, because the file then lives in the
 * bucket rather than on the app server.
 *
 * The writers now persist keys and the readers resolve them at render time via
 * media_url() / mediaUrl(). This migration brings existing rows in line.
 *
 * Safety:
 *  - Only strips a leading "/storage/" (or "storage/") prefix and a leading slash.
 *  - EXTERNAL absolute URLs (http://, https://, //) are left untouched — a social-login
 *    avatar or an ad pointing at a remote image is not ours to rewrite.
 *  - data: URIs and non-path values (emoji or two-letter language flags) are untouched.
 *  - Idempotent: a value that is already a relative key is left as-is, so re-running is a
 *    no-op. Both media_url() and mediaUrl() also tolerate the legacy format, so this is a
 *    consistency cleanup rather than a correctness prerequisite.
 */
return new class extends Migration
{
    /** @var array<string, string[]> table => media columns */
    private const TARGETS = [
        'users' => ['avatar'],
        'admins' => ['avatar'],
        'pages' => ['og_image', 'featured_image'],
        'blog_posts' => ['featured_image'],
        'ads' => ['image_url'],
        'languages' => ['flag'],
        'testimonials' => ['avatar'],
        'ai_tools' => ['og_image'],
    ];

    /** Settings keys whose value is a stored media path. */
    private const SETTING_KEYS = [
        'site_logo_light',
        'site_logo_dark',
        'site_favicon_ico',
        'site_favicon_png',
        'site_og_image',
        'maintenance_background_image',
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->normalizeColumn($table, $column);
            }
        }

        $this->normalizeSettings();
    }

    public function down(): void
    {
        // Irreversible by design: the relative key is the canonical format and the readers
        // resolve it per-driver. Re-adding a hardcoded "/storage/" prefix would recreate the
        // bug this migration fixes.
    }

    private function normalizeColumn(string $table, string $column): void
    {
        DB::table($table)
            ->select('id', $column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $original = (string) $row->{$column};
                    $normalized = $this->toRelativeKey($original);

                    if ($normalized !== null && $normalized !== $original) {
                        DB::table($table)->where('id', $row->id)->update([$column => $normalized]);
                    }
                }
            });
    }

    private function normalizeSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $rows = DB::table('settings')
            ->whereIn('key', self::SETTING_KEYS)
            ->get(['id', 'key', 'value']);

        foreach ($rows as $row) {
            $original = (string) ($row->value ?? '');
            $normalized = $this->toRelativeKey($original);

            if ($normalized !== null && $normalized !== $original) {
                DB::table('settings')->where('id', $row->id)->update(['value' => $normalized]);
            }
        }
    }

    /**
     * Reduce a stored value to a relative disk key, or return null when it must not be
     * touched (blank, external URL, data URI, or a non-path value such as an emoji flag).
     */
    private function toRelativeKey(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Never rewrite something that isn't ours to serve.
        if (preg_match('#^(https?:)?//#i', $value) || str_starts_with($value, 'data:')) {
            return null;
        }

        $key = ltrim($value, '/');

        if (str_starts_with($key, 'storage/')) {
            $key = substr($key, strlen('storage/'));
        }

        // Windows separators can leak in from settings written on a Windows host.
        $key = str_replace('\\', '/', $key);

        // A value with no path separator and no file extension is not a media path
        // (e.g. an emoji or "en" language flag) — leave it alone.
        if (! str_contains($key, '/') && ! preg_match('/\.[a-z0-9]{2,5}$/i', $key)) {
            return null;
        }

        return $key;
    }
};
