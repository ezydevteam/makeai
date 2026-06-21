<?php

namespace App\Http\Controllers\Admin\Activity;

use App\Http\Controllers\Controller;
use App\Models\AffiliateReferral;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserLogController extends Controller
{
    public function index()
    {
        $now = now();
        $start = $now->copy()->subDays(29)->startOfDay();
        $activities = $this->getRecentActivityCollection($start, $now);
        $paginated = $this->paginateActivityCollection($activities, 20);

        return Inertia::render('Admin/Activity/UserLog', [
            'activity' => $paginated,
            'rangeLabel' => translate('Last 30 days'),
        ]);
    }

    private function getRecentActivityCollection($start = null, $end = null): Collection
    {
        $activities = collect();
        $toolNames = AiTool::pluck('name', 'slug');
        $modelNames = AiModel::pluck('name', 'slug');

        $userQuery = User::query()->latest();
        if ($start && $end) {
            $userQuery->whereBetween('created_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $userQuery->take(8);
        }
        $userQuery->get()->each(function ($user) use (&$activities) {
            $activities->push([
                'type' => 'user_registered',
                'icon' => 'user',
                'title' => $user->name,
                'user_email' => $user->email,
                'user_ulid' => $user->ulid,
                'detail' => $user->email,
                'time' => $user->created_at,
            ]);
        });

        $paymentQuery = Payment::with('user')->where('status', 'completed')->latest();
        if ($start && $end) {
            $paymentQuery->whereBetween('created_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $paymentQuery->take(8);
        }
        $paymentQuery->get()->each(function ($payment) use (&$activities) {
            $activities->push([
                'type' => $payment->type === 'subscription' ? 'subscription' : 'payment',
                'icon' => 'dollar',
                'title' => $payment->user?->name ?? translate('Unknown'),
                'user_email' => $payment->user?->email,
                'user_ulid' => $payment->user?->ulid,
                'detail' => number_format($payment->amount, 2).' '.$payment->currency,
                'time' => $payment->created_at,
            ]);
        });

        $usageQuery = AiUsageLog::with('user')->latest();
        if ($start && $end) {
            $usageQuery->whereBetween('created_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $usageQuery->take(8);
        }
        $usageQuery->get()->each(function ($log) use (&$activities, $toolNames, $modelNames) {
            $toolLabel = $log->tool_slug ? ($toolNames[$log->tool_slug] ?? $log->tool_slug) : null;
            $modelLabel = $log->model ? ($modelNames[$log->model] ?? $log->model) : null;
            $providerLabel = $this->formatProviderLabel($log->provider);
            $fallbackModelLabel = $this->formatModelLabel($log->model, $log->provider);

            $activities->push([
                'type' => 'ai_request',
                'icon' => 'spark',
                'title' => $log->user?->name ?? translate('Unknown'),
                'user_email' => $log->user?->email,
                'user_ulid' => $log->user?->ulid,
                'detail' => ($toolLabel ?? $providerLabel.' / '.($modelLabel ?? $fallbackModelLabel)).' — '.number_format($log->credits_used, 2).' credits',
                'time' => $log->created_at,
            ]);
        });

        $referralQuery = AffiliateReferral::with(['referrer', 'referred'])->whereNotNull('converted_at')->latest('converted_at');
        if ($start && $end) {
            $referralQuery->whereBetween('converted_at', [$start, $end]);
        }
        if (! ($start && $end)) {
            $referralQuery->take(8);
        }
        $referralQuery->get()->each(function ($referral) use (&$activities) {
            $activities->push([
                'type' => 'referral',
                'icon' => 'user',
                'title' => $referral->referrer?->name ?? translate('Unknown'),
                'user_email' => $referral->referrer?->email,
                'user_ulid' => $referral->referrer?->ulid,
                'detail' => $referral->referred?->email ?? translate('Unknown'),
                'ip_address' => $referral->ip_address,
                'time' => $referral->converted_at,
            ]);
        });

        return $activities->sortByDesc('time')->values();
    }

    private function paginateActivityCollection(Collection $activities, int $perPage = 20): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $activities->values();
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($results, $items->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function formatProviderLabel(?string $provider): string
    {
        if (! $provider) {
            return translate('Unknown');
        }

        return match (strtolower($provider)) {
            'openrouter' => 'OpenRouter',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'google' => 'Google',
            default => Str::headline($provider),
        };
    }

    private function formatModelLabel(?string $model, ?string $provider = null): string
    {
        if (! $model) {
            return translate('Unknown');
        }

        $cleaned = Str::afterLast($model, '/');
        $cleaned = preg_replace('/-\d{8}$/', '', $cleaned) ?? $cleaned;

        return Str::of($cleaned)
            ->replace(['_', '.'], ' ')
            ->replace('-', ' ')
            ->headline()
            ->toString();
    }
}
