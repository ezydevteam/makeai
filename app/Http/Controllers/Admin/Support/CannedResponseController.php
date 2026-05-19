<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\CannedResponseRequest;
use App\Models\SupportCannedResponse;
use App\Models\SupportDepartment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CannedResponseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeRespond();

        return Inertia::render('Admin/Support/CannedResponses', [
            'responses' => SupportCannedResponse::with('department:id,name', 'creator:id,name')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = trim($request->string('search')->toString());
                    $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('content', 'like', "%{$search}%"));
                })
                ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->integer('department')))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'departments' => SupportDepartment::orderBy('sort_order')->get(['id', 'name']),
            'filters' => $request->only(['search', 'department']),
        ]);
    }

    public function store(CannedResponseRequest $request)
    {
        SupportCannedResponse::create([
            ...$request->validated(),
            'created_by' => auth('admin')->id(),
        ]);

        return back()->with('success', translate('Canned response created.'));
    }

    public function update(CannedResponseRequest $request, SupportCannedResponse $response)
    {
        $response->update($request->validated());

        return back()->with('success', translate('Canned response updated.'));
    }

    public function destroy(SupportCannedResponse $response)
    {
        $this->authorizeRespond();
        $response->delete();

        return back()->with('success', translate('Canned response deleted.'));
    }

    private function authorizeRespond(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('support.respond'), 403);
    }
}
