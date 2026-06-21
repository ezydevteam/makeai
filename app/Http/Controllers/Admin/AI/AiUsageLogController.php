<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiUsageLogController extends Controller
{
    /**
     * Display a listing of AI usage logs.
     */
    public function index(Request $request)
    {
        $query = AiUsageLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('tool_slug')) {
            $query->where('tool_slug', $request->tool_slug);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model', 'like', "%{$search}%")
                    ->orWhere('tool_slug', 'like', "%{$search}%")
                    ->orWhere('provider', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Get distinct providers for filter dropdown
        $providers = AiUsageLog::distinct()->pluck('provider')->filter()->values()->toArray();

        return Inertia::render('Admin/AI/Logs', [
            'logs' => $query->paginate(30)->withQueryString(),
            'filters' => $request->only(['provider', 'tool_slug', 'status', 'search', 'date_from', 'date_to']),
            'providers' => $providers,
        ]);
    }
}
