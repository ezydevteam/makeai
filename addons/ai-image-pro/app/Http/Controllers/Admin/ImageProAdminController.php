<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\Admin;

use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin analytics overview. Everything here is derived from `aip_jobs` and
 * `aip_assets` — the same rows the user pipeline writes — so the numbers are a
 * direct reflection of real activity, never a separate counter that can drift.
 */
class ImageProAdminController extends Controller
{
    /**
     * Selectable reporting windows, in days. `all` is null — no lower bound at all,
     * which is why every windowed query goes through window() rather than assuming a
     * date exists, and why `all` gets no percentage change (there is no "before").
     */
    private const PERIODS = [
        '1d' => 1,
        '7d' => 7,
        '30d' => 30,
        'all' => null,
    ];

    private const DEFAULT_PERIOD = '30d';

    public function overview(Request $request): Response
    {
        $period = $this->period($request);

        [$from, $previousFrom] = $this->bounds($period);

        // Current window, and the equally-long window immediately before it. The second
        // one exists only to compute the percentage change.
        $jobs = fn (?Carbon $since, ?Carbon $until = null): Builder => $this->window(AipJob::query(), $since, $until);
        $assets = fn (?Carbon $since, ?Carbon $until = null): Builder => $this->window(AipAsset::query(), $since, $until);

        $total = $jobs($from)->count();
        $failed = $jobs($from)->where('status', AipJob::STATUS_FAILED)->count();
        $rate = $total > 0 ? round($failed / $total * 100, 1) : 0.0;

        $comparable = $previousFrom !== null;

        $previousTotal = $comparable ? $jobs($previousFrom, $from)->count() : 0;
        $previousFailed = $comparable
            ? $jobs($previousFrom, $from)->where('status', AipJob::STATUS_FAILED)->count()
            : 0;
        $previousRate = $previousTotal > 0 ? round($previousFailed / $previousTotal * 100, 1) : 0.0;

        return Inertia::render('Addons/ai-image-pro/Admin/Overview', [
            'period' => $period,
            'stats' => [
                'jobs' => [
                    'value' => $total,
                    'comparison' => $comparable ? $this->comparison($total, $previousTotal) : null,
                ],
                'images' => [
                    'value' => $assets($from)->count(),
                    'comparison' => $comparable
                        ? $this->comparison($assets($from)->count(), $assets($previousFrom, $from)->count())
                        : null,
                ],
                'credits' => [
                    'value' => round((float) $jobs($from)->sum('credits_charged'), 2),
                    'comparison' => $comparable
                        ? $this->comparison(
                            (float) $jobs($from)->sum('credits_charged'),
                            (float) $jobs($previousFrom, $from)->sum('credits_charged'),
                        )
                        : null,
                ],
                'failure_rate' => [
                    'value' => $rate,
                    // Deliberately 'neutral': in StatsCard, 'up' paints green. A RISING
                    // failure rate is bad news, so the standard treatment would colour a
                    // regression as an improvement. Grey with a signed label states the
                    // change without lying about whether it is good.
                    'comparison' => $comparable ? $this->comparison($rate, $previousRate, neutral: true) : null,
                ],
            ],
            'byOperation' => $jobs($from)
                ->select('operation', DB::raw('COUNT(*) as count'), DB::raw('SUM(credits_charged) as credits'))
                ->groupBy('operation')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'operation' => (string) $row->operation,
                    'count' => (int) $row->count,
                    'credits' => round((float) $row->credits, 2),
                ])
                ->all(),
            'byModel' => $jobs($from)
                ->whereNotNull('model')
                ->select('model', DB::raw('COUNT(*) as count'))
                ->groupBy('model')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'model' => (string) $row->model,
                    'count' => (int) $row->count,
                ])
                ->all(),
            'recentFailures' => $jobs($from)
                ->where('status', AipJob::STATUS_FAILED)
                ->latest('completed_at')
                ->limit(15)
                ->get(['ulid', 'operation', 'tier', 'engine', 'model', 'error_message', 'completed_at', 'created_at'])
                ->map(fn (AipJob $job) => [
                    'ulid' => $job->ulid,
                    'operation' => $job->operation,
                    'tier' => $job->tier,
                    'engine' => $job->engine,
                    'model' => $job->model,
                    'error' => $job->error_message,
                    'failed_at' => $job->completed_at?->toISOString(),
                    'created_at' => $job->created_at?->toISOString(),
                ])
                ->all(),
        ]);
    }

    /**
     * An unrecognised ?period= falls back to the default rather than 500ing or silently
     * reporting an empty window — a hand-typed or stale query string is not an error.
     */
    private function period(Request $request): string
    {
        $value = (string) $request->query('period', self::DEFAULT_PERIOD);

        return array_key_exists($value, self::PERIODS) ? $value : self::DEFAULT_PERIOD;
    }

    /**
     * [start of the current window, start of the equally-long window before it].
     *
     * Both are null for `all`: with no lower bound there is no "previous" span to compare
     * against, so those cards show a figure and no change.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function bounds(string $period): array
    {
        $days = self::PERIODS[$period];

        if ($days === null) {
            return [null, null];
        }

        // 1d means "today", not "the last 24 hours" — an admin reading a daily figure
        // expects it to match since-midnight, so its comparison is yesterday.
        $from = $days === 1 ? now()->startOfDay() : now()->subDays($days);

        return [$from, $from->copy()->subDays($days)];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function window(Builder $query, ?Carbon $since, ?Carbon $until = null): Builder
    {
        return $query
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until, fn (Builder $q) => $q->where('created_at', '<', $until));
    }

    /**
     * Percentage change, in the shape StatsCard expects — the same contract the core
     * admin dashboard uses (see AiUsageLogController::calculateComparison).
     *
     * @return array{label: string, type: string}
     */
    private function comparison(float|int $current, float|int $previous, bool $neutral = false): array
    {
        $sentiment = fn (string $type): string => $neutral ? 'neutral' : $type;

        if ((float) $previous === 0.0) {
            return (float) $current === 0.0
                ? ['label' => '0%', 'type' => 'neutral']
                : ['label' => '+100%', 'type' => $sentiment('up')];
        }

        $delta = (($current - $previous) / $previous) * 100;
        $rounded = (int) round(abs($delta));

        if ($rounded === 0) {
            return ['label' => '0%', 'type' => 'neutral'];
        }

        return [
            'label' => ($delta > 0 ? '+' : '-') . $rounded . '%',
            'type' => $sentiment($delta > 0 ? 'up' : 'down'),
        ];
    }
}
