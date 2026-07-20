<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Generators\Contracts\FakeGenerator;
use Addons\FakerAi\Models\FakerBatch;
use Addons\FakerAi\Services\BatchRollback;
use Addons\FakerAi\Services\FakeContentGenerator;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Shared plumbing for generators: AI access, style resolution, date/IP jitter, fake-author
 * creation, and the generic ledger-driven rollback. Concrete generators override
 * type()/label()/group()/generate() and, when relevant, requiresTarget()/targets()/rollback().
 */
abstract class AbstractGenerator implements FakeGenerator
{
    public function __construct(protected FakeContentGenerator $content) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function requiresTarget(): bool
    {
        return false;
    }

    public function targets(): array
    {
        return [];
    }

    public function usesAi(): bool
    {
        return true;
    }

    public function optionFields(): array
    {
        return [];
    }

    /**
     * Default rollback: replay the batch's ledger. Generators with side effects (recomputing a
     * tool's review average, busting a cache) override this, call parent, then fix up.
     */
    public function rollback(FakerBatch $batch): void
    {
        $this->rollbackRecords($batch);
    }

    // ─── targets ─────────────────────────────────────────────

    /**
     * Narrow a query to the batch's chosen targets, or leave it untouched when the batch
     * targets everything ('*' / nothing selected).
     *
     * @param  string  $column  the column holding the target key (e.g. 'slug', 'ulid')
     */
    protected function applyTargets($query, FakerBatch $batch, string $column)
    {
        if (! $batch->targetsAll()) {
            $query->whereIn($column, $batch->targetList());
        }

        return $query;
    }

    // ─── style / jitter ──────────────────────────────────────

    /**
     * Merge per-run options over the addon's defaults into the style array the AI service reads.
     */
    protected function style(FakerBatch $batch, array $extra = []): array
    {
        $options = $batch->options ?? [];

        return array_merge([
            'language' => trim((string) ($options['language'] ?? addon_setting('faker-ai', 'default_language', 'English'))) ?: 'English',
            'tone' => trim((string) ($options['tone'] ?? addon_setting('faker-ai', 'default_tone', 'authentic, varied, conversational'))) ?: 'authentic',
            'prompt' => trim((string) ($options['prompt'] ?? '')),
            'context' => trim((string) settings('site_name', config('app.name', ''))),
        ], $extra);
    }

    /**
     * A random timestamp within the addon's backdate window, so seeded rows don't all share
     * one created_at. Anchored to now going backwards.
     */
    protected function backdate(?int $days = null): Carbon
    {
        $days = $days ?? max(1, (int) addon_setting('faker-ai', 'backdate_days', 90));

        return now()
            ->subDays(random_int(0, $days))
            ->subMinutes(random_int(0, 1439));
    }

    /**
     * A plausible-looking public IPv4 for view/analytics rows. Avoids private/reserved ranges.
     */
    protected function randomIp(): string
    {
        do {
            $first = random_int(1, 223);
        } while (in_array($first, [10, 127, 169, 172, 192], true));

        return sprintf('%d.%d.%d.%d', $first, random_int(0, 255), random_int(0, 255), random_int(1, 254));
    }

    // ─── star ratings ────────────────────────────────────────

    /**
     * Selectable star bands, as option key => [min, max]. Shared by every generator that
     * writes a rating, so testimonials and tool reviews always offer the same choices.
     */
    private const RATING_BANDS = [
        '5' => [5, 5],
        '4' => [4, 4],
        '4-5' => [4, 5],
        '3-5' => [3, 5],
        '2-5' => [2, 5],
        'random' => [1, 5],
    ];

    /** The "Rating" select, for a generator's optionFields(). */
    protected function ratingOptionField(): array
    {
        return [
            'key' => 'rating',
            'label' => 'Rating',
            'type' => 'select',
            'options' => [
                ['value' => '5', 'label' => '5 stars only'],
                ['value' => '4', 'label' => '4 stars only'],
                ['value' => '4-5', 'label' => '4–5 stars'],
                ['value' => '3-5', 'label' => '3–5 stars'],
                ['value' => '2-5', 'label' => '2–5 stars'],
                ['value' => 'random', 'label' => 'Random (1–5)'],
            ],
            // Mostly-positive, which is what both generators produced before the field existed.
            'default' => '4-5',
        ];
    }

    /**
     * The [min, max] star band the batch asked for.
     *
     * @return array{0:int,1:int}
     */
    protected function ratingBand(FakerBatch $batch): array
    {
        $key = (string) ($batch->options['rating'] ?? '4-5');

        return self::RATING_BANDS[$key] ?? self::RATING_BANDS['4-5'];
    }

    /**
     * What to tell the model about the rating, so the TEXT it writes matches the number.
     * Without this a forced 2-star band would sit under glowing praise.
     *
     * @param  array{0:int,1:int}  $band
     */
    protected function ratingFieldHint(array $band): string
    {
        [$min, $max] = $band;

        if ($min === $max) {
            return "exactly {$min} — the wording must read like a genuine {$min}-out-of-5 opinion";
        }

        return "an integer from {$min} to {$max}; vary it across the set, and make the sentiment "
            ."of the text match the number you give it (a {$min} must read as {$min}-star, not praise)";
    }

    /**
     * Settle the final star value: honour the model's number when it is sane, but force it
     * inside the admin's chosen band — a "5 stars only" run must never emit a 4.
     *
     * @param  array{0:int,1:int}  $band
     */
    protected function pickRating(mixed $value, array $band): int
    {
        [$min, $max] = $band;
        $n = (int) round((float) $value);

        // Nonsense (missing, 0, 9, "great") — pick within the band rather than snapping to an
        // edge, so the distribution stays varied.
        if ($n < 1 || $n > 5) {
            return random_int($min, $max);
        }

        return max($min, min($max, $n));
    }

    // ─── fake authors ────────────────────────────────────────

    /**
     * Create a fake user to author a review, recorded on the batch so rollback removes it.
     *
     * Fakes are identifiable two ways: a reserved @faker.local email domain (queryable with a
     * portable LIKE) and a preferences flag. They're active and verified so they display like
     * real members, but they carry a random password nobody holds and are never emailed.
     */
    protected function createFakeUser(FakerBatch $batch, string $name, Carbon $date, array $extra = []): User
    {
        $name = trim($name) !== '' ? trim($name) : 'Member '.Str::upper(Str::random(4));
        $email = Str::slug($name, '.').'.'.Str::lower(Str::random(6)).'@faker.local';

        $user = new User;
        $user->fill(array_merge([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(32),   // 'hashed' cast hashes it on set
            'is_active' => true,
            'email_verified_at' => $date,
            'preferences' => ['faker_ai' => true],
        ], $extra));
        $user->created_at = $date;
        $user->updated_at = $date;
        $user->save();

        $batch->recordInsert($user);

        return $user;
    }

    // ─── rollback engine ─────────────────────────────────────

    /**
     * Reverse every mutation the batch logged. Delegated to BatchRollback so the exact same
     * replay is used whether a generator or the controller (via a missing generator) drives it.
     */
    protected function rollbackRecords(FakerBatch $batch): void
    {
        BatchRollback::run($batch);
    }
}
