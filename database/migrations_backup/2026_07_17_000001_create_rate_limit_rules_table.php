<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the settings refactor (see settings-refactor-plan.md).
 *
 * Moves the rate-limit tier matrix out of the key/value `settings` table into a
 * dedicated, typed `rate_limit_rules` table, then backfills it from any values an
 * admin had already customized and drops those flat rows.
 *
 * NOT migrated (they are scalars, not part of the category×tier matrix, and stay in
 * `settings`): rl_ai_abuse_*, rl_login_ban_*.
 */
return new class extends Migration
{
    /** Tiers whose suffix we strip off a setting key to recover the category. */
    private array $tiers = ['guest', 'free_user', 'pro_user'];

    public function up(): void
    {
        Schema::create('rate_limit_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('tier'); // guest, free_user, pro_user
            $table->unsignedInteger('max_attempts');
            $table->unsignedInteger('window_seconds');
            $table->timestamps();

            $table->unique(['category', 'tier']);
        });

        $this->backfillFromSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_rules');
    }

    /**
     * Copy customized `rl_{category}_{tier}_{max|window}` settings into the new table,
     * then delete those settings rows. Un-customized categories are left absent —
     * RateLimiterService falls back to its coded defaults exactly as before.
     */
    private function backfillFromSettings(): void
    {
        // Prefilter to rl_* keys, then let parseKey() decide precisely which are
        // tier-matrix rows. parseKey rejects the abuse/login scalars (they do not end
        // in _max/_window with a known tier), so they stay in settings untouched.
        // Underscore is a LIKE wildcard here, which is fine and portable (MySQL + sqlite).
        $rows = DB::table('settings')
            ->where('key', 'like', 'rl_%')
            ->get(['key', 'value']);

        $matrix = [];   // [category][tier] => ['max_attempts'=>?, 'window_seconds'=>?]
        $doneKeys = [];

        foreach ($rows as $row) {
            $parsed = $this->parseKey($row->key);
            if ($parsed === null) {
                continue; // not a real tier-matrix key, leave it in settings
            }
            [$category, $tier, $field] = $parsed;
            $matrix[$category][$tier][$field] = (int) $row->value;
            $doneKeys[] = $row->key;
        }

        $now = DB::table('settings')->max('created_at') ?? now();

        foreach ($matrix as $category => $tiers) {
            foreach ($tiers as $tier => $vals) {
                // Only insert a complete pair; a half-written setting falls back to defaults.
                if (! isset($vals['max_attempts'], $vals['window_seconds'])) {
                    continue;
                }
                DB::table('rate_limit_rules')->updateOrInsert(
                    ['category' => $category, 'tier' => $tier],
                    [
                        'max_attempts' => $vals['max_attempts'],
                        'window_seconds' => $vals['window_seconds'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        if ($doneKeys !== []) {
            DB::table('settings')->whereIn('key', $doneKeys)->delete();
            foreach ($doneKeys as $key) {
                Cache::forget('settings:'.$key);
            }
            Cache::forget('settings:all');
        }
    }

    /**
     * Split "rl_{category}_{tier}_{max|window}" into [category, tier, field].
     * Category may itself contain underscores (text_gen, social_auth), so we peel
     * the field suffix and the tier suffix off the end rather than splitting.
     *
     * @return array{0:string,1:string,2:string}|null
     */
    private function parseKey(string $key): ?array
    {
        if (! str_starts_with($key, 'rl_')) {
            return null;
        }
        $body = substr($key, 3); // strip "rl_"

        if (str_ends_with($body, '_max')) {
            $field = 'max_attempts';
            $body = substr($body, 0, -4);
        } elseif (str_ends_with($body, '_window')) {
            $field = 'window_seconds';
            $body = substr($body, 0, -7);
        } else {
            return null;
        }

        foreach ($this->tiers as $tier) {
            if (str_ends_with($body, '_'.$tier)) {
                $category = substr($body, 0, -(strlen($tier) + 1));

                return $category === '' ? null : [$category, $tier, $field];
            }
        }

        return null;
    }
};
