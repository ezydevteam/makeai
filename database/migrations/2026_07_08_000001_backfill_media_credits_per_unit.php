<?php

use App\Models\AiModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Media models (audio/image/transcription) are billed PER UNIT via
 * TokenGuard::mediaCreditCost, which reads meta.credits_per_unit — but existing
 * rows (e.g. the seeded ElevenLabs voices) had that unset, so every clip fell back
 * to the flat config default regardless of model. Backfill meta.credits_per_unit
 * from the curated per-model credits value (stored in credits_per_1k) so per-model
 * audio/image pricing takes effect. Skips rows an admin already set manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_models')) {
            return;
        }

        AiModel::query()
            ->whereIn('type', ['audio', 'image', 'transcription'])
            ->where('credits_per_1k', '>', 0)
            ->get()
            ->each(function (AiModel $model) {
                $meta = $model->meta ?? [];

                if (isset($meta['credits_per_unit'])) {
                    return; // respect an existing manual override
                }

                $meta['credits_per_unit'] = (float) $model->credits_per_1k;
                $model->meta = $meta;
                $model->save();
            });
    }

    public function down(): void
    {
        // Irreversible: the pre-backfill per-unit charge was the flat config default.
    }
};
