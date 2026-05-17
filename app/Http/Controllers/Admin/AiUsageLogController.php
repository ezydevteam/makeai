<?php

namespace App\Http\Controllers\Admin;

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

        return Inertia::render('Admin/AI/UsageLogs/Index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'filters' => $request->only(['provider', 'tool_slug']),
        ]);
    }
}
