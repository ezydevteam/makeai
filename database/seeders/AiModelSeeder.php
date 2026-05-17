<?php

namespace Database\Seeders;

use App\Models\AiModel;
use Illuminate\Database\Seeder;

class AiModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function up(): void
    {
        $providers = config('ai.providers', []);

        foreach ($providers as $providerSlug => $providerInfo) {
            $models = $providerInfo['models'] ?? [];
            foreach ($models as $modelSlug) {
                AiModel::updateOrCreate(
                    ['slug' => $modelSlug],
                    [
                        'name' => $this->formatName($modelSlug),
                        'provider' => $providerSlug,
                        'is_active' => true,
                        'type' => 'chat',
                        'cost_input_1k' => $this->guessCost($modelSlug, 'input'),
                        'cost_output_1k' => $this->guessCost($modelSlug, 'output'),
                        'credits_per_1k' => $this->guessCredits($modelSlug),
                        'max_tokens' => 4096,
                    ]
                );
            }
        }
    }

    private function formatName(string $slug): string
    {
        return str_replace(['-', 'gpt', 'claude'], [' ', 'GPT', 'Claude'], ucwords($slug, '-'));
    }

    private function guessCost(string $slug, string $type): float
    {
        // Dummy logic to populate some values
        if (str_contains($slug, '4o-mini') || str_contains($slug, 'haiku')) {
            return $type === 'input' ? 0.00015 : 0.0006;
        }
        if (str_contains($slug, '4o') || str_contains($slug, 'sonnet')) {
            return $type === 'input' ? 0.0025 : 0.010;
        }
        if (str_contains($slug, 'o1') || str_contains($slug, 'opus')) {
            return $type === 'input' ? 0.015 : 0.06;
        }

        return 0.001;
    }

    private function guessCredits(string $slug): int
    {
        if (str_contains($slug, 'mini') || str_contains($slug, 'haiku')) {
            return 1;
        }
        if (str_contains($slug, '4o') || str_contains($slug, 'sonnet')) {
            return 10;
        }
        if (str_contains($slug, 'o1') || str_contains($slug, 'opus')) {
            return 50;
        }

        return 5;
    }

    public function run(): void
    {
        $this->up();
    }
}
