<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MailLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/Mail/Logs', [
            'filters' => $filters,
            'logs' => MailLog::query()
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->string('search')->toString();

                    $query->where(function ($query) use ($search) {
                        $query->where('recipient_email', 'like', "%{$search}%")
                            ->orWhere('subject', 'like', "%{$search}%")
                            ->orWhere('template_slug', 'like', "%{$search}%");
                    });
                })
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function resend(MailLog $log, MailService $mailService): RedirectResponse
    {
        if ($log->template_slug === null) {
            return back()->with('error', translate('Only templated emails can be resent from logs.'));
        }

        $mailService->send($log->template_slug, $log->recipient_email);

        return back()->with('success', translate('Email resend has been queued.'));
    }
}
