<?php

namespace App\Services;

use App\Models\GenerationHistory;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class GenerationHistoryService
{
    public function record(User $user, array $data): GenerationHistory
    {
        $history = GenerationHistory::create([
            'user_id' => $user->id,
            'tool_slug' => $data['tool_slug'],
            'document_id' => $data['document_id'] ?? null,
            'prompt_system' => $data['prompt_system'] ?? '',
            'prompt_user' => $data['prompt_user'] ?? '',
            'field_values' => $data['field_values'] ?? [],
            'model' => $data['model'] ?? '',
            'provider' => $data['provider'] ?? '',
            'temperature' => $data['temperature'] ?? 0.7,
            'max_tokens' => $data['max_tokens'] ?? 0,
            'output_preview' => $data['output_preview'] ?? '',
            'tokens_input' => $data['tokens_input'] ?? 0,
            'tokens_output' => $data['tokens_output'] ?? 0,
        ]);

        $this->pruneHistory($user);

        Cache::forget("usage_stats_{$user->id}");

        return $history;
    }

    public function getHistory(User $user, ?string $toolSlug = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = GenerationHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($toolSlug) {
            $query->where('tool_slug', $toolSlug);
        }

        return $query->paginate($perPage);
    }

    public function restore(GenerationHistory $history): array
    {
        return [
            'field_values' => $history->field_values ?? [],
            'model' => $history->model,
            'provider' => $history->provider,
            'temperature' => $history->temperature,
            'max_tokens' => $history->max_tokens,
        ];
    }

    public function diff(GenerationHistory $a, GenerationHistory $b): array
    {
        $wordsA = preg_split('/\s+/', $a->prompt_user);
        $wordsB = preg_split('/\s+/', $b->prompt_user);

        $result = [];
        $maxLen = max(count($wordsA), count($wordsB));

        for ($i = 0; $i < $maxLen; $i++) {
            $wordA = $wordsA[$i] ?? null;
            $wordB = $wordsB[$i] ?? null;

            if ($wordA === $wordB) {
                $result[] = ['word' => $wordA ?? '', 'status' => 'unchanged'];
            } elseif ($wordA === null) {
                $result[] = ['word' => $wordB, 'status' => 'added'];
            } elseif ($wordB === null) {
                $result[] = ['word' => $wordA, 'status' => 'removed'];
            } else {
                $result[] = ['word' => $wordA, 'status' => 'removed'];
                $result[] = ['word' => $wordB, 'status' => 'added'];
            }
        }

        return $result;
    }

    public function toggleFavorite(GenerationHistory $history): void
    {
        $history->update(['is_favorited' => ! $history->is_favorited]);
    }

    private function pruneHistory(User $user): void
    {
        $count = GenerationHistory::where('user_id', $user->id)->count();

        if ($count > 200) {
            $toDelete = GenerationHistory::where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->limit($count - 200)
                ->pluck('id');

            GenerationHistory::whereIn('id', $toDelete)->delete();
        }
    }
}
