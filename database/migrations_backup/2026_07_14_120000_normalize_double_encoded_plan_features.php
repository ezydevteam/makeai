<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PlanSeeder and DemoSeeder called json_encode() on `features`, which the Plan model already
 * cast to an array — so Eloquent encoded it a second time and the column ended up holding a
 * JSON string wrapping a JSON array. Reading it back gave a string, which the checkout page
 * then spread character by character ("A", "l", "l", ...).
 *
 * The model now decodes defensively, so this is about the data itself: leave every row
 * single-encoded so the column means what it says.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plans')->select('id', 'features')->cursor() as $plan) {
            $list = $this->toFeatureList($plan->features);

            // Compare decoded, not raw: MySQL's json column reformats on storage ("a", "b"
            // rather than "a","b"), so a string comparison would rewrite every row every run.
            $current = is_string($plan->features) ? json_decode($plan->features, true) : $plan->features;

            if (! is_array($current) || $current !== $list) {
                DB::table('plans')->where('id', $plan->id)->update(['features' => json_encode($list)]);
            }
        }
    }

    public function down(): void
    {
        // Correctly encoded data is the desired state; re-breaking it serves no purpose.
    }

    /**
     * Decode until we reach an array, so both double-encoded and correctly encoded rows land
     * on the same shape. Anything unparseable becomes an empty list rather than throwing —
     * a bad row must not block the migration.
     */
    private function toFeatureList(mixed $value): array
    {
        $decoded = $value;

        for ($i = 0; $i < 2 && is_string($decoded); $i++) {
            $decoded = json_decode($decoded, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($feature) => is_string($feature) ? trim($feature) : null, $decoded),
            fn (?string $feature) => $feature !== null && $feature !== '',
        ));
    }
};
