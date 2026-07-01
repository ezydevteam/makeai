<?php

namespace App\Http\Controllers;

use App\Jobs\RunToolChainJob;
use App\Models\AiTool;
use App\Models\ToolChain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChainController extends Controller
{
    public function index(): Response
    {
        $chains = ToolChain::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('User/Chains/Index', ['chains' => $chains]);
    }

    public function create(): Response
    {
        $tools = AiTool::active()->select('slug', 'name')->get();

        return Inertia::render('User/Chains/Builder', ['tools' => $tools]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'steps' => 'required|array|min:2|max:5',
            'steps.*.tool_slug' => 'required|string|max:100',
            'steps.*.static_inputs' => 'array',
            'steps.*.field_map' => 'array',
        ]);

        $steps = [];
        foreach ($validated['steps'] as $index => $step) {
            $steps[] = [
                'step' => $index + 1,
                'tool_slug' => $step['tool_slug'],
                'static_inputs' => $step['static_inputs'] ?? [],
                'field_map' => $step['field_map'] ?? [],
            ];
        }

        ToolChain::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'steps' => $steps,
        ]);

        return redirect()->route('user.dashboard.chains.index');
    }

    public function show(ToolChain $chain): Response
    {
        if ($chain->user_id !== Auth::id()) abort(403);

        $chain->load('runs');
        $tools = AiTool::active()->select('slug', 'name')->get();

        return Inertia::render('User/Chains/Builder', [
            'chain' => $chain,
            'tools' => $tools,
            'editing' => true,
        ]);
    }

    public function update(Request $request, ToolChain $chain): RedirectResponse
    {
        if ($chain->user_id !== Auth::id()) abort(403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'steps' => 'sometimes|array|min:2|max:5',
        ]);

        if (isset($validated['steps'])) {
            $validated['steps'] = array_map(function ($step, $i) {
                return array_merge($step, ['step' => $i + 1]);
            }, $validated['steps'], array_keys($validated['steps']));
        }

        $chain->update($validated);

        return back();
    }

    public function destroy(ToolChain $chain): RedirectResponse
    {
        if ($chain->user_id !== Auth::id()) abort(403);
        $chain->delete();

        return back();
    }

    public function run(ToolChain $chain): RedirectResponse
    {
        if ($chain->user_id !== Auth::id()) abort(403);

        if (empty($chain->steps) || count($chain->steps) < 2) {
            return back()->with('error', 'A chain needs at least 2 steps to run.');
        }

        foreach ($chain->steps as $index => $step) {
            $slug = $step['tool_slug'] ?? '';
            if (empty($slug)) {
                return back()->with('error', "Step {$index} has no tool selected.");
            }
            if (! AiTool::where('slug', $slug)->exists()) {
                return back()->with('error', "Tool '{$slug}' in step {$index} was not found. It may have been deleted.");
            }
        }

        RunToolChainJob::dispatch($chain, Auth::user());

        return back()->with('success', 'Chain started — output will appear in history.');
    }
}
