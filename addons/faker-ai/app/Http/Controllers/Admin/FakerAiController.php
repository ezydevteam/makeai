<?php

namespace Addons\FakerAi\Http\Controllers\Admin;

use Addons\FakerAi\Generators\Contracts\FakeGenerator;
use Addons\FakerAi\Jobs\GenerateFakeDataJob;
use Addons\FakerAi\Models\FakerBatch;
use Addons\FakerAi\Services\BatchRollback;
use Addons\FakerAi\Services\GeneratorRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FakerAiController extends Controller
{
    public function __construct(private GeneratorRegistry $registry) {}

    public function index(): Response
    {
        return Inertia::render('Addons/faker-ai/Admin/Index', [
            'generators' => $this->registry->toArray(),
            'limits' => $this->limits(),
            'defaults' => [
                'language' => addon_setting('faker-ai', 'default_language', 'English'),
                'tone' => addon_setting('faker-ai', 'default_tone', 'authentic, varied, conversational'),
                'backdate_days' => (int) addon_setting('faker-ai', 'backdate_days', 90),
            ],
            'recent' => $this->recentBatches(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $type = (string) $request->input('type');
        $generator = $this->registry->get($type);

        if (! $generator || ! $generator->isAvailable()) {
            return back()->with('error', 'That data type cannot be generated on this install.');
        }

        $max = $this->maxFor($type);

        $validated = $request->validate([
            'type' => 'required|string',
            'count' => "required|integer|min:1|max:{$max}",
            // A batch may target many items at once; '*' is the "everything" sentinel.
            'target' => $generator->requiresTarget() ? 'required|array|min:1' : 'nullable|array',
            'target.*' => 'string|max:191',
            'language' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:255',
            'prompt' => 'nullable|string|max:2000',
        ]);

        $targets = $generator->requiresTarget()
            ? $this->normalizeTargets($generator, $validated['target'])
            : [];

        if ($generator->requiresTarget() && $targets === []) {
            return back()->with('error', 'None of the selected targets exist any more.');
        }

        $target = $targets === [] ? null : json_encode($targets);

        $batch = FakerBatch::create([
            'type' => $type,
            'label' => $generator->label(),
            'target' => $target,
            'target_label' => $this->targetLabel($generator, $targets),
            'requested_count' => (int) $validated['count'],
            'options' => array_merge([
                'language' => $validated['language'] ?? null,
                'tone' => $validated['tone'] ?? null,
                'prompt' => $validated['prompt'] ?? null,
            ], $this->resolveOptionFields($generator, $request)),
            'status' => 'pending',
            'created_by' => $request->user('admin')?->id,
        ]);

        GenerateFakeDataJob::dispatch($batch->id);

        return back()->with(
            'success',
            "Queued {$validated['count']} × {$generator->label()}. Track progress under History."
        );
    }

    public function history(): Response
    {
        $batches = FakerBatch::query()
            ->with('creator:id,name')
            ->latest()
            ->paginate(20)
            ->through(fn (FakerBatch $b) => [
                'id' => $b->id,
                'type' => $b->type,
                'label' => $b->label,
                'target_label' => $b->target_label,
                'requested_count' => $b->requested_count,
                'inserted_count' => $b->inserted_count,
                'status' => $b->status,
                'error' => $b->error,
                'tokens' => $b->tokens_input + $b->tokens_output,
                'created_by' => $b->creator?->name,
                'created_at' => $b->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Addons/faker-ai/Admin/History', [
            'batches' => $batches,
        ]);
    }

    public function destroyBatch(FakerBatch $batch): RedirectResponse
    {
        // Prefer the generator's own rollback (it may bust caches / recompute stats); fall back
        // to the raw ledger replay if the generator is gone (its addon was deactivated).
        $generator = $this->registry->get($batch->type);
        if ($generator) {
            $generator->rollback($batch);
        } else {
            BatchRollback::run($batch);
        }

        // Records cascade-delete with the batch.
        $batch->delete();

        return back()->with('success', 'Batch deleted and its generated data rolled back.');
    }

    // ─── helpers ─────────────────────────────────────────────

    /**
     * Read the generator's declared option fields off the request, validating each select value
     * against its allowed options and falling back to the field default. Keeps arbitrary input
     * out of the stored batch options.
     *
     * @return array<string,mixed>
     */
    private function resolveOptionFields(FakeGenerator $generator, Request $request): array
    {
        $resolved = [];

        foreach ($generator->optionFields() as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }

            $value = $request->input("options.{$key}");
            $allowed = array_column($field['options'] ?? [], 'value');

            $resolved[$key] = ($allowed && in_array($value, $allowed, true))
                ? $value
                : ($field['default'] ?? null);
        }

        return $resolved;
    }

    /**
     * Keep only targets the generator still offers, and collapse a selection containing the
     * '*' sentinel down to just '*' — "everything" plus a subset is still everything.
     *
     * @param  array<int,string>  $selected
     * @return array<int,string>
     */
    private function normalizeTargets(FakeGenerator $generator, array $selected): array
    {
        if (in_array('*', $selected, true)) {
            return ['*'];
        }

        $allowed = array_column($generator->targets(), 'value');

        return array_values(array_unique(array_filter(
            $selected,
            fn ($value) => in_array($value, $allowed, true),
        )));
    }

    /**
     * A short human summary for the History table: "All tools", one label, or the first two
     * plus a "+N more" tail so a 40-item selection doesn't blow out the column.
     *
     * @param  array<int,string>  $targets
     */
    private function targetLabel(FakeGenerator $generator, array $targets): ?string
    {
        if ($targets === []) {
            return null;
        }

        $options = collect($generator->targets())->keyBy('value');

        if ($targets === ['*']) {
            return $options->get('*')['label'] ?? 'All';
        }

        $labels = collect($targets)->map(fn ($value) => $options->get($value)['label'] ?? $value);

        if ($labels->count() <= 2) {
            return $labels->implode(', ');
        }

        return $labels->take(2)->implode(', ').' +'.($labels->count() - 2).' more';
    }

    /** @return array<string,int> type => max count allowed in one run. */
    private function limits(): array
    {
        $out = [];
        foreach ($this->registry->available() as $generator) {
            $out[$generator->type()] = $this->maxFor($generator->type());
        }

        return $out;
    }

    /**
     * Per-type ceiling on one run. AI-authored types stay modest (each item is an AI-drafted
     * row); pure counters allow big numbers; types that insert a row per item (blog views,
     * tool favorites) sit in between to bound the rollback ledger — favorites also mint a
     * fake user per concurrent favorite on a single tool.
     */
    private function maxFor(string $type): int
    {
        return match ($type) {
            'blog-views', 'tool-favorites' => 2000,
            'tool-usage', 'blog-shares', 'kb-ratings' => 100000,
            default => 200,
        };
    }

    private function recentBatches()
    {
        return FakerBatch::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'label', 'target_label', 'requested_count', 'inserted_count', 'status', 'created_at'])
            ->map(fn (FakerBatch $b) => [
                'id' => $b->id,
                'label' => $b->label,
                'target_label' => $b->target_label,
                'requested_count' => $b->requested_count,
                'inserted_count' => $b->inserted_count,
                'status' => $b->status,
                'created_at' => $b->created_at?->diffForHumans(),
            ]);
    }
}
