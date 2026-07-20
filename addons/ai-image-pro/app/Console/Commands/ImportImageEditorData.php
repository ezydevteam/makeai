<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Console\Commands;

use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time, re-runnable migration from the legacy AI Image Editor addon into
 * AI Image Pro. It copies three things and deletes nothing from the old addon:
 *
 *   1. Completed edit outputs  → aip_assets (source='derived'), preserving the
 *      per-session edit chain as parent_id lineage (version_number order).
 *   2. The four provider API keys → AI Image Pro settings, only where the target
 *      key is still empty.
 *   3. The old per-operation credit costs → the new `operations` JSON setting,
 *      for the operations that carry over, only where no override exists yet.
 *
 * Every step is idempotent: re-running imports only what a previous run missed.
 */
class ImportImageEditorData extends Command
{
    protected $signature = 'aip:import-image-editor';

    protected $description = 'Import completed edits, API keys and per-operation credit costs from the legacy AI Image Editor addon into AI Image Pro.';

    /** Old encrypted API-key settings → identically-named AI Image Pro settings. */
    private const API_KEYS = [
        'stability_api_key',
        'replicate_api_key',
        'remove_bg_api_key',
        'clipdrop_api_key',
    ];

    /** AI Image Pro operation key → old `credits_*` setting it inherits its cost from. */
    private const OPERATION_CREDITS = [
        'inpaint' => 'credits_inpaint',
        'outpaint' => 'credits_outpaint',
        'bg_remove' => 'credits_bg_remove',
        'upscale' => 'credits_upscale',
        'style_transfer' => 'credits_style_transfer',
        'object_remove' => 'credits_object_remove',
        'color_correction' => 'credits_color_correction',
        'text_overlay' => 'credits_text_overlay',
    ];

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('ie_edits')
            || ! DB::getSchemaBuilder()->hasTable('ie_sessions')) {
            $this->warn('AI Image Editor tables (ie_edits/ie_sessions) not found — nothing to import.');

            return self::SUCCESS;
        }

        $keys = $this->importApiKeys();
        $ops = $this->importOperationCredits();
        [$assets, $skipped] = $this->importAssets();

        $this->newLine();
        $this->info('AI Image Editor import complete.');
        $this->table(['Imported', 'Count'], [
            ['API keys copied', $keys],
            ['Operation credit costs copied', $ops],
            ['Edit outputs imported as assets', $assets],
            ['Edit outputs already imported (skipped)', $skipped],
        ]);

        return self::SUCCESS;
    }

    /**
     * Copy the provider keys into AI Image Pro, but only where its own key is
     * still empty — never overwrite a key the operator already set here.
     */
    private function importApiKeys(): int
    {
        $copied = 0;

        foreach (self::API_KEYS as $key) {
            if (! empty(addon_setting(OperationRegistry::SLUG, $key))) {
                continue; // AI Image Pro already has this key
            }

            $value = addon_setting('ai-image-editor', $key);

            if (empty($value)) {
                continue; // nothing to copy
            }

            addon_setting_set(OperationRegistry::SLUG, $key, $value, 'encrypted');
            $copied++;
        }

        return $copied;
    }

    /**
     * Fold the old flat per-operation credit costs into the `operations` JSON
     * override, touching only the `credits` field and only for operations that
     * still exist and have no override yet.
     */
    private function importOperationCredits(): int
    {
        $registryKeys = app(OperationRegistry::class)->keys();

        $operations = addon_setting(OperationRegistry::SLUG, 'operations');
        if (is_string($operations)) {
            $operations = json_decode($operations, true);
        }
        $operations = is_array($operations) ? $operations : [];

        $copied = 0;

        foreach (self::OPERATION_CREDITS as $op => $legacyKey) {
            if (! in_array($op, $registryKeys, true)) {
                continue; // operation doesn't exist in AI Image Pro
            }

            if (array_key_exists('credits', $operations[$op] ?? [])) {
                continue; // respect an override already present — keep idempotent
            }

            $value = addon_setting('ai-image-editor', $legacyKey);
            if ($value === null) {
                continue; // old addon never had this cost
            }

            $operations[$op] = array_merge($operations[$op] ?? [], ['credits' => max(0, (int) $value)]);
            $copied++;
        }

        if ($copied > 0) {
            addon_setting_set(OperationRegistry::SLUG, 'operations', $operations, 'json');
        }

        return $copied;
    }

    /**
     * Copy every completed edit output into aip_assets, rebuilding each session's
     * edit chain as parent_id lineage (each version points at the previous one;
     * the first version has no parent because it derives from the session source,
     * which is not itself an edit output).
     *
     * @return array{0: int, 1: int} [created, skipped]
     */
    private function importAssets(): array
    {
        $created = 0;
        $skipped = 0;

        $sessionIds = DB::table('ie_edits')
            ->where('status', 'completed')
            ->whereNotNull('output_path')
            ->distinct()
            ->orderBy('ie_session_id')
            ->pluck('ie_session_id');

        $disk = Storage::disk('public');

        foreach ($sessionIds as $sessionId) {
            $edits = DB::table('ie_edits')
                ->where('ie_session_id', $sessionId)
                ->where('status', 'completed')
                ->whereNotNull('output_path')
                ->orderBy('version_number')
                ->orderBy('id')
                ->get();

            $parentId = null;

            foreach ($edits as $edit) {
                // Idempotent: an output already imported keeps its place in the
                // chain so later versions still attach to it.
                $existing = AipAsset::withTrashed()->where('path', $edit->output_path)->first();
                if ($existing) {
                    $parentId = $existing->id;
                    $skipped++;
                    continue;
                }

                $params = $this->decodeParams($edit->params);

                $asset = new AipAsset;
                $asset->fill([
                    'user_id' => $edit->user_id,
                    'parent_id' => $parentId,
                    'source' => 'derived',
                    'operation' => $edit->operation,
                    'disk' => 'public',
                    'path' => $edit->output_path,
                    'mime' => $this->mimeFor($edit->output_path),
                    'provider' => $edit->provider,
                    'prompt' => $params['prompt'] ?? null,
                    'negative_prompt' => $params['negative_prompt'] ?? null,
                    'params' => $params ?: null,
                ]);

                $this->applyFileMeta($asset, $disk, $edit->output_path);

                $timestamp = $this->timestampFor($edit);
                if ($timestamp) {
                    $asset->created_at = $timestamp;
                    $asset->updated_at = $timestamp;
                }

                $asset->save();

                $parentId = $asset->id;
                $created++;
            }
        }

        return [$created, $skipped];
    }

    /**
     * Record real width/height/bytes from the stored file when it's reachable on
     * the local public disk — the storage quota and gallery depend on them. Best
     * effort: a missing or remote file just leaves them null/0.
     */
    private function applyFileMeta(AipAsset $asset, $disk, string $path): void
    {
        try {
            if (! $disk->exists($path)) {
                return;
            }

            $asset->bytes = (int) $disk->size($path);

            $absolute = $disk->path($path);
            if (is_file($absolute)) {
                $dimensions = @getimagesize($absolute);
                if ($dimensions !== false) {
                    $asset->width = (int) $dimensions[0];
                    $asset->height = (int) $dimensions[1];
                    if (! empty($dimensions['mime'])) {
                        $asset->mime = $dimensions['mime'];
                    }
                }
            }
        } catch (\Throwable) {
            // Disk driver without a local path (e.g. cloud) — skip metadata.
        }
    }

    /** @return array<string, mixed> */
    private function decodeParams(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            default => 'image/png',
        };
    }

    private function timestampFor(object $edit): ?Carbon
    {
        $value = $edit->completed_at ?? $edit->created_at ?? null;

        return $value ? Carbon::parse($value) : null;
    }
}
