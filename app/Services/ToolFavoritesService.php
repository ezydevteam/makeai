<?php

namespace App\Services;

use App\Models\AiTool;
use App\Models\Favorite;
use App\Models\User;
use App\Models\UserCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ToolFavoritesService
{
    public function toggleFavorite(User $user, string $toolSlug): bool
    {
        $tool = AiTool::where('slug', $toolSlug)->firstOrFail();

        $existing = Favorite::where('user_id', $user->id)
            ->where('favoriteable_type', 'App\Models\AiTool')
            ->where('favoriteable_id', $tool->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->clearCache($user);

            return false;
        }

        Favorite::create([
            'user_id' => $user->id,
            'favoriteable_type' => 'App\Models\AiTool',
            'favoriteable_id' => $tool->id,
        ]);

        $this->clearCache($user);

        return true;
    }

    public function getFavorites(User $user): Collection
    {
        return Cache::remember("user_tool_favorites_{$user->id}", 3600, function () use ($user) {
            return Favorite::where('user_id', $user->id)
                ->where('favoriteable_type', 'App\Models\AiTool')
                ->with('favoriteable')
                ->get()
                ->pluck('favoriteable.slug')
                ->filter()
                ->values();
        });
    }

    public function isFavorited(User $user, string $toolSlug): bool
    {
        return $this->getFavorites($user)->contains($toolSlug);
    }

    public function getUserCollections(User $user): Collection
    {
        return UserCollection::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('is_featured', true);
        })
            ->withCount('tools')
            ->orderBy('sort_order')
            ->get();
    }

    private function clearCache(User $user): void
    {
        Cache::forget("user_tool_favorites_{$user->id}");
    }
}
