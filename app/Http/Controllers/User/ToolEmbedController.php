<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\ToolEmbed;
use App\Services\AI\ToolAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ToolEmbedController extends Controller
{
    public function index(): Response
    {
        $embeds = ToolEmbed::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        // Only tools the embed endpoint will actually serve — it 403s anything that
        // isn't embeddable, so offering the rest just lets users build dead embeds.
        $tools = $this->embeddableTools()
            ->map(fn (AiTool $t) => ['slug' => $t->slug, 'name' => $t->name])
            ->values();

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
            'allowed_origins' => 'nullable|array|max:20',
            // Nullable, not just string: ConvertEmptyStringsToNull turns a blank entry
            // into null, and normalizeOrigins() drops it anyway. A nested array still fails.
            'allowed_origins.*' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'theme' => 'nullable|in:light,dark,auto',
            'primary_color' => 'nullable|string|max:7',
            'show_branding' => 'boolean',
        ]);

        $this->assertToolIsEmbeddable($validated['tool_slug']);

        $embed = new ToolEmbed([
            'user_id' => Auth::id(),
            'tool_slug' => $validated['tool_slug'],
            'label' => $validated['label'] ?? null,
            'allowed_origins' => $this->normalizeOrigins($validated['allowed_origins'] ?? null),
            // ?? null because `password` is optional: when the key is simply absent
            // (any client that isn't the create form, which always sends an empty
            // string) the bare $validated['password'] raised an ErrorException → 500.
            'password_hash' => ! blank($validated['password'] ?? null) ? bcrypt($validated['password']) : null,
            'theme' => $validated['theme'] ?? 'auto',
            'primary_color' => $validated['primary_color'] ?? null,
            'show_branding' => $validated['show_branding'] ?? true,
        ]);

        $embed->save();

        return back();
    }

    public function update(Request $request, ToolEmbed $embed): RedirectResponse
    {
        if ($embed->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'allowed_origins' => 'nullable|array|max:20',
            // Without this rule a nested array reaches allowed_origins and every
            // later load of the embed dies in array_map('trim', …) with a TypeError.
            // Nullable, not just string: ConvertEmptyStringsToNull turns a blank entry
            // into null, and normalizeOrigins() drops it anyway. A nested array still fails.
            'allowed_origins.*' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'theme' => 'nullable|in:light,dark,auto',
            'primary_color' => 'nullable|string|max:7',
            'show_branding' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (array_key_exists('allowed_origins', $validated)) {
            $validated['allowed_origins'] = $this->normalizeOrigins($validated['allowed_origins']);
        }

        if (array_key_exists('password', $validated)) {
            $validated['password_hash'] = ! blank($validated['password']) ? bcrypt($validated['password']) : null;
            unset($validated['password']);
        }

        $embed->update($validated);

        return back();
    }

    public function destroy(ToolEmbed $embed): RedirectResponse
    {
        if ($embed->user_id !== Auth::id()) {
            abort(403);
        }

        $embed->delete();

        return back();
    }

    public function regenerateToken(ToolEmbed $embed): RedirectResponse
    {
        if ($embed->user_id !== Auth::id()) {
            abort(403);
        }

        $embed->update(['token' => \Illuminate\Support\Str::random(64)]);

        return back();
    }

    /**
     * Active tools the current user may actually publish: embeddable, and within
     * the user's own access level (a public embed runs billed to its owner, and
     * EmbedController gates on the owner's access at run time).
     */
    private function embeddableTools()
    {
        $access = app(ToolAccessService::class);
        $user = Auth::user();

        return AiTool::active()
            ->where('is_embeddable', true)
            ->get()
            ->filter(fn (AiTool $tool) => $access->checkAccess($tool, $user)->allowed);
    }

    private function assertToolIsEmbeddable(string $slug): void
    {
        $allowed = $this->embeddableTools()->contains(fn (AiTool $tool) => $tool->slug === $slug);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'tool_slug' => translate('That tool cannot be embedded.'),
            ]);
        }
    }

    /**
     * Origins are compared verbatim against the request's Origin header and used to
     * build the frame-ancestors CSP, so strip blanks and de-dupe rather than storing
     * junk that silently never matches.
     *
     * @param  array<int, string>|null  $origins
     * @return array<int, string>|null
     */
    private function normalizeOrigins(?array $origins): ?array
    {
        if ($origins === null) {
            return null;
        }

        $clean = collect($origins)
            ->filter(fn ($origin) => is_string($origin))
            ->map(fn (string $origin) => trim($origin))
            ->filter(fn (string $origin) => $origin !== '')
            ->unique()
            ->values()
            ->all();

        return $clean === [] ? null : $clean;
    }
}
