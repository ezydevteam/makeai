<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\ExportUserDataJob;
use App\Jobs\PermanentlyDeleteUserJob;
use App\Models\DataExportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PrivacyController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $exportHistory = DataExportRequest::forUser($user->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'status' => $r->status,
                'requested_at' => $r->created_at->toIso8601String(),
                'expires_at' => $r->expires_at?->toIso8601String(),
                'is_expired' => $r->isExpired(),
            ]);

        $sessions = $user->loginHistory()
            ->where('success', true)
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'ip' => $s->ip,
                'country' => $s->country,
                'city' => $s->city,
                'user_agent' => $s->user_agent,
                'last_seen' => $s->created_at->toIso8601String(),
            ]);

        $pendingExport = DataExportRequest::forUser($user->id)
            ->pending()
            ->count();

        $recentExport = DataExportRequest::forUser($user->id)
            ->recent()
            ->exists();

        $scheduledDeletion = $user->scheduled_deletion_at?->toIso8601String();

        return Inertia::render('User/Privacy', [
            'exportHistory' => $exportHistory,
            'sessions' => $sessions,
            'pendingExport' => $pendingExport > 0,
            'recentExport' => $recentExport,
            'scheduledDeletion' => $scheduledDeletion,
            'user' => [
                'email_marketing' => $user->email_marketing,
                'allow_data_improve' => $user->allow_data_improve,
                'cookie_consent' => $user->cookie_consent,
            ],
        ]);
    }

    public function requestExport(): RedirectResponse
    {
        $user = Auth::user();

        $recentExport = DataExportRequest::forUser($user->id)->recent()->exists();
        if ($recentExport) {
            return back()->with('error', __('You can only request one data export every 24 hours.'));
        }

        $exportRequest = DataExportRequest::create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        ExportUserDataJob::dispatch($user, $exportRequest);

        return back()->with('success', __('Your data export has been requested. You will be notified when it is ready.'));
    }

    public function downloadExport(DataExportRequest $exportRequest): mixed
    {
        if ($exportRequest->user_id !== Auth::id()) {
            abort(403);
        }

        if ($exportRequest->status !== 'ready' || $exportRequest->isExpired()) {
            return back()->with('error', __('Export file is no longer available.'));
        }

        $path = storage_path('app/' . $exportRequest->file_path);

        if (! file_exists($path)) {
            return back()->with('error', __('Export file not found.'));
        }

        $exportRequest->update(['status' => 'downloaded']);

        return response()->download($path, 'my-data-' . now()->format('Ymd') . '.zip');
    }

    public function scheduleDeletion(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! $user->verifyOtp($request->input('otp'))) {
            return back()->with('error', __('Invalid verification code.'));
        }

        $user->update([
            'scheduled_deletion_at' => now()->addDays(30),
            'is_active' => false,
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('info', __('Your account has been scheduled for deletion. You have 30 days to cancel by logging in.'));
    }

    public function cancelDeletion(): RedirectResponse
    {
        $user = Auth::user();

        $user->update([
            'scheduled_deletion_at' => null,
            'is_active' => true,
        ]);

        return back()->with('success', __('Account deletion has been cancelled.'));
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'email_marketing' => ['boolean'],
            'allow_data_improve' => ['boolean'],
            'cookie_consent' => ['nullable', 'array'],
        ]);

        $user->update($validated);

        return back()->with('success', __('Privacy preferences updated.'));
    }

    public function revokeSession(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $sessionId = $request->input('session_id');

        // Delete from actual sessions table to terminate the session
        \DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        // Also remove from login history (audit log)
        $user->loginHistory()
            ->where('id', $sessionId)
            ->delete();

        return back()->with('success', __('Session revoked.'));
    }

    public function signOutAllDevices(): RedirectResponse
    {
        $user = Auth::user();

        $user->tokens()->delete();

        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->with('info', __('You have been signed out of all devices.'));
    }
}
