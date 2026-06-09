<?php

namespace App\Http\Controllers;

use App\Models\UserCollection;
use App\Models\AiTool;
use App\Services\ToolFavoritesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $collections = app(ToolFavoritesService::class)->getUserCollections($user);

        return Inertia::render('Collections/Index', [
            'collections' => $collections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        UserCollection::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
        ]);

        return redirect()->route('user.dashboard.collections.index');
    }

    public function show(UserCollection $collection): Response
    {
        $slugs = $collection->tools()->orderBy('sort_order')->pluck('tool_slug');
        $tools = AiTool::whereIn('slug', $slugs)->get()->keyBy('slug');

        // Preserve sort order
        $ordered = $slugs->map(fn ($slug) => $tools->get($slug))->filter();

        // All active tools for the add-to-collection picker
        $availableTools = AiTool::active()->select('slug', 'name')->get()
            ->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name]);

        return Inertia::render('Collections/Show', [
            'collection' => $collection,
            'tools' => $ordered->values(),
            'availableTools' => $availableTools,
        ]);
    }

    public function update(Request $request, UserCollection $collection): RedirectResponse
    {
        if ($collection->user_id !== Auth::id() && ! $collection->is_featured) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        $collection->update($validated);

        return back();
    }

    public function destroy(UserCollection $collection): RedirectResponse
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $collection->delete();

        return redirect()->route('user.dashboard.collections.index');
    }

    public function addTool(Request $request, UserCollection $collection): RedirectResponse
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'tool_slug' => 'required|string|max:100',
        ]);

        $exists = $collection->tools()->where('tool_slug', $validated['tool_slug'])->exists();

        if (! $exists) {
            $collection->tools()->create([
                'tool_slug' => $validated['tool_slug'],
                'sort_order' => $collection->tools()->count(),
            ]);
        }

        return redirect()->route('user.dashboard.collections.show', $collection->ulid);
    }

    public function removeTool(UserCollection $collection, string $slug): RedirectResponse
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $collection->tools()->where('tool_slug', $slug)->delete();

        return redirect()->route('user.dashboard.collections.show', $collection->ulid);
    }

    public function reorder(Request $request, UserCollection $collection): RedirectResponse
    {
        if ($collection->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'slugs' => 'required|array',
            'slugs.*' => 'string',
        ]);

        foreach ($validated['slugs'] as $index => $slug) {
            $collection->tools()->where('tool_slug', $slug)->update(['sort_order' => $index]);
        }

        return back();
    }
}
