<?php

namespace Addons\AiRepurposer\Http\Controllers;

use Addons\AiRepurposer\Http\Requests\BulkRepurposeRequest;
use Addons\AiRepurposer\Http\Requests\RepurposeRequest;
use Addons\AiRepurposer\Jobs\ProcessBulkRepurposeJob;
use Addons\AiRepurposer\Jobs\ProcessRepurposeJob;
use Addons\AiRepurposer\Models\RpJob;
use Addons\AiRepurposer\Models\RpOutput;
use Addons\AiRepurposer\Services\RepurposeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RepurposerController extends Controller
{
    public function index()
    {
        $jobs = RpJob::forUser(auth()->id())
            ->with('outputs')
            ->latest()
            ->paginate(15);

        return Inertia::render('Addons/ai-repurposer/User/Index', [
            'jobs' => $jobs,
        ]);
    }

    public function store(RepurposeRequest $request)
    {
        $credits = (int) addon_setting('ai-repurposer', 'credits_per_repurpose', 15);

        if (! credit_quota_mode() && auth()->user()->credits < $credits) {
            return back()->with('error', __('Insufficient credits.'));
        }

        if (! deduct_credits(auth()->id(), $credits, 'Content repurpose')) {
            return back()->with('error', __('Failed to deduct credits.'));
        }

        $sourcePath = null;
        if ($request->hasFile('file')) {
            $sourcePath = $request->file('file')->store('repurposer/' . auth()->id(), 'local');
        }

        $job = RpJob::create([
            'user_id'           => auth()->id(),
            'source_type'       => $request->source_type,
            'source_url'        => $request->source_url,
            'source_path'       => $sourcePath,
            'source_title'      => $request->title,
            'transcript'        => $request->source_type === 'text_paste' ? $request->text : null,
            'transcript_source' => $request->source_type === 'text_paste' ? 'pasted' : null,
            'status'            => 'queued',
            'formats_requested' => $request->formats,
            'credits_deducted'  => $credits,
        ]);

        ProcessRepurposeJob::dispatch($job->id)->onQueue('ai');

        return redirect()->route('addon.rp.user.show', ['job' => $job->ulid]);
    }

    public function storeBulk(BulkRepurposeRequest $request)
    {
        $bulkCredits = (int) addon_setting('ai-repurposer', 'credits_per_bulk_item', 12);
        $totalCredits = count($request->urls) * $bulkCredits;

        if (! credit_quota_mode() && auth()->user()->credits < $totalCredits) {
            return back()->with('error', __('Insufficient credits for bulk processing.'));
        }

        if (! deduct_credits(auth()->id(), $totalCredits, 'Bulk content repurpose (' . count($request->urls) . ' items)')) {
            return back()->with('error', __('Failed to deduct credits.'));
        }

        $batchId = Str::uuid()->toString();

        foreach ($request->urls as $url) {
            RpJob::create([
                'user_id'           => auth()->id(),
                'source_type'       => 'youtube_url',
                'source_url'        => $url,
                'source_title'      => $request->title_prefix,
                'status'            => 'queued',
                'formats_requested' => $request->formats,
                'credits_deducted'  => $bulkCredits,
                'is_bulk'           => true,
                'bulk_batch_id'     => $batchId,
            ]);
        }

        ProcessBulkRepurposeJob::dispatch($batchId)->onQueue('ai');

        return redirect()->route('addon.rp.user.index')
            ->with('success', __('Bulk repurpose started for :count URLs.', ['count' => count($request->urls)]));
    }

    public function show(RpJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            abort(403);
        }

        $job->load('outputs');

        return Inertia::render('Addons/ai-repurposer/User/Result', [
            'job' => $job,
        ]);
    }

    public function status(RpJob $job): JsonResponse
    {
        if ($job->user_id !== auth()->id()) {
            abort(403);
        }

        $job->refresh();

        return response()->json([
            'status'             => $job->status,
            'formats_completed'  => $job->formats_completed ?? [],
            'formats_requested'  => $job->formats_requested ?? [],
            'progress_percent'   => $job->progress_percent,
            'error_message'      => $job->error_message,
        ]);
    }

    public function regenerate(Request $request, RpJob $job, string $format)
    {
        if ($job->user_id !== auth()->id()) {
            abort(403);
        }

        if (! in_array($format, array_keys(RepurposeService::FORMATS))) {
            return response()->json(['error' => __('Invalid format.')], 422);
        }

        app(RepurposeService::class)->regenerateFormat($job, $format);

        $output = RpOutput::where('rp_job_id', $job->id)->where('format', $format)->first();

        return response()->json([
            'success' => true,
            'content' => $output?->content,
        ]);
    }

    public function saveToBlog(RpOutput $output): JsonResponse
    {
        if ($output->user_id !== auth()->id()) {
            abort(403);
        }

        if ($output->format !== 'blog_post') {
            return response()->json(['error' => __('Only blog posts can be saved.')], 422);
        }

        $job = $output->job;

        $postId = DB::table('blog_posts')->insertGetId([
            'user_id'    => auth()->id(),
            'title'      => $job->source_title ?? 'Repurposed Post',
            'content'    => $output->content,
            'status'     => 'draft',
            'slug'       => Str::slug($job->source_title ?? 'repurposed-post') . '-' . Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $output->update(['is_saved' => true, 'saved_post_id' => $postId]);

        return response()->json(['post_id' => $postId, 'saved' => true]);
    }

    public function destroy(RpJob $job): RedirectResponse
    {
        if ($job->user_id !== auth()->id()) {
            abort(403);
        }

        if ($job->source_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($job->source_path);
        }

        $job->delete();

        return redirect()->route('addon.rp.user.index')
            ->with('success', __('Repurpose job deleted.'));
    }
}
