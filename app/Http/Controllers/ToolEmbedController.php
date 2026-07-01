<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\ToolEmbed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ToolEmbedController extends Controller
{
    public function index(): Response
    {
        $embeds = ToolEmbed::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        $tools = AiTool::active()->select('slug', 'name')->get()
            ->map(fn ($t) => ['slug' => $t->slug, 'name' => $t->name]);

        return Inertia::render('User/ToolEmbeds', [
            'embeds' => $embeds,
            'tools' => $tools,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tool_slug' => 'required|string|max:100',
            'label' => 'nullable|string|max:100',
            'allowed_origins' => 'nullable|array',
            'allowed_origins.*' => 'string',
            'password' => 'nullable|string|max:255',
            'theme' => 'nullable|in:light,dark,auto',
            'primary_color' => 'nullable|string|max:7',
            'show_branding' => 'boolean',
        ]);

        $embed = new ToolEmbed([
            'user_id' => Auth::id(),
            'tool_slug' => $validated['tool_slug'],
            'label' => $validated['label'] ?? null,
            'allowed_origins' => $validated['allowed_origins'] ?? null,
            'password_hash' => ! blank($validated['password']) ? bcrypt($validated['password']) : null,
            'theme' => $validated['theme'] ?? 'auto',
            'primary_color' => $validated['primary_color'] ?? null,
            'show_branding' => $validated['show_branding'] ?? true,
        ]);

        $embed->save();

        return back();
    }

    public function update(Request $request, ToolEmbed $embed): RedirectResponse
    {
        if ($embed->user_id !== Auth::id()) abort(403);

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'allowed_origins' => 'nullable|array',
            'password' => 'nullable|string|max:255',
            'theme' => 'nullable|in:light,dark,auto',
            'primary_color' => 'nullable|string|max:7',
            'show_branding' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (array_key_exists('password', $validated)) {
            $validated['password_hash'] = ! blank($validated['password']) ? bcrypt($validated['password']) : null;
            unset($validated['password']);
        }

        $embed->update($validated);

        return back();
    }

    public function destroy(ToolEmbed $embed): RedirectResponse
    {
        if ($embed->user_id !== Auth::id()) abort(403);
        $embed->delete();

        return back();
    }

    public function regenerateToken(ToolEmbed $embed): RedirectResponse
    {
        if ($embed->user_id !== Auth::id()) abort(403);

        $embed->update(['token' => \Illuminate\Support\Str::random(64)]);

        return back();
    }
}
