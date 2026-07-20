<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Http\Requests\FavoriteToggleRequest;
use App\Models\AiTool;
use App\Models\BlogPost;
use App\Models\Document;
use App\Models\Favorite;
use App\Models\Page;
use App\Models\User;
use App\Services\ToolFavoritesService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $favorites = Favorite::query()
            ->where('user_id', $user->id)
            ->with('favoriteable')
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('User/Favorites', [
            'groups' => $this->groupFavorites($favorites->getCollection()),
            'collections' => app(ToolFavoritesService::class)->getUserCollections($user)->values(),
            'pagination' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'total' => $favorites->total(),
                'links' => $favorites->linkCollection(),
            ],
        ]);
    }

    public function toggle(FavoriteToggleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $favoriteableClass = $request->favoriteableClass();
        $favoriteable = $this->favoriteableQuery($favoriteableClass, $user)
            ->findOrFail($validated['favoriteable_id']);

        $favorite = Favorite::where('user_id', $user->id)
            ->where('favoriteable_type', $favoriteableClass)
            ->where('favoriteable_id', $favoriteable->getKey())
            ->first();

        if ($favorite) {
            $favorite->delete();

            return back()->with('info', translate('Removed from favorites.'));
        }

        Favorite::create([
            'user_id' => $user->id,
            'favoriteable_type' => $favoriteableClass,
            'favoriteable_id' => $favoriteable->getKey(),
        ]);

        return back()->with('success', translate('Added to favorites.'));
    }

    private function groupFavorites($favorites): array
    {
        return $favorites
            ->map(fn (Favorite $favorite) => $this->favoritePayload($favorite))
            ->filter()
            ->groupBy('type')
            ->map(fn ($items, string $type) => [
                'type' => $type,
                'label' => $this->typeLabel($type),
                'items' => $items->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function favoriteableQuery(string $favoriteableClass, User $user)
    {
        $query = $favoriteableClass::query();

        return match ($favoriteableClass) {
            BlogPost::class => $query->published(),
            AiTool::class => $query->active(),
            Document::class => $query->where('user_id', $user->id),
            Page::class => $query->published(),
            default => $query,
        };
    }

    private function favoritePayload(Favorite $favorite): ?array
    {
        $favoriteable = $favorite->favoriteable;

        if (! $favoriteable instanceof Model) {
            return null;
        }

        return match ($favorite->favoriteable_type) {
            BlogPost::class => [
                'id' => $favorite->id,
                'type' => 'blog_posts',
                'model_id' => $favoriteable->id,
                'title' => $favoriteable->title,
                'description' => $favoriteable->excerpt,
                'url' => route('blog.show', $favoriteable->slug),
                'image' => $favoriteable->featured_image,
                'icon' => 'ti-article',
                'color' => '#10b981',
                'favorited_at' => $favorite->created_at?->toISOString(),
            ],
            AiTool::class => [
                'id' => $favorite->id,
                'type' => 'ai_templates',
                'model_id' => $favoriteable->id,
                'slug' => $favoriteable->slug,
                'title' => $favoriteable->name,
                'description' => $favoriteable->description,
                'url' => route('ai.tools.show', $favoriteable->slug),
                'image' => null,
                'icon' => $favoriteable->icon ?: 'ti-wand',
                'color' => $favoriteable->color ?: '#8b5cf6',
                'favorited_at' => $favorite->created_at?->toISOString(),
            ],
            Document::class => [
                'id' => $favorite->id,
                'type' => 'documents',
                'model_id' => $favoriteable->id,
                'title' => $favoriteable->title,
                'description' => $favoriteable->tool_slug,
                'url' => route('documents.edit', $favoriteable->id),
                'image' => null,
                'icon' => 'ti-file-text',
                'color' => '#3b82f6',
                'favorited_at' => $favorite->created_at?->toISOString(),
            ],
            Page::class => [
                'id' => $favorite->id,
                'type' => 'pages',
                'model_id' => $favoriteable->id,
                'title' => $favoriteable->title,
                'description' => $favoriteable->excerpt,
                'url' => route('page.show', $favoriteable->slug),
                'image' => $favoriteable->featured_image,
                'icon' => 'ti-file',
                'color' => '#3b82f6',
                'favorited_at' => $favorite->created_at?->toISOString(),
            ],
            default => null,
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'blog_posts' => translate('Blog Posts'),
            'ai_templates' => translate('AI Tools'),
            'documents' => translate('Documents'),
            'pages' => translate('Pages'),
            default => translate('Favorites'),
        };
    }
}
