<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\DepartmentRequest;
use App\Models\AdminRole;
use App\Models\SupportDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSupport();

        return Inertia::render('Admin/Support/Departments', [
            'departments' => SupportDepartment::with('assignedRole:id,name')
                ->withCount('tickets')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = trim($request->string('search')->toString());
                    $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
                })
                ->orderBy('sort_order')
                ->paginate(25)
                ->withQueryString(),
            'roles' => AdminRole::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(DepartmentRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        SupportDepartment::create($data);

        return back()->with('success', translate('Support department created.'));
    }

    public function update(DepartmentRequest $request, SupportDepartment $department)
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $department->update($data);

        return back()->with('success', translate('Support department updated.'));
    }

    public function destroy(SupportDepartment $department)
    {
        $this->authorizeSupport();
        abort_if($department->tickets()->exists(), 422, translate('Departments with tickets cannot be deleted.'));
        $department->delete();

        return back()->with('success', translate('Support department deleted.'));
    }

    private function authorizeSupport(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('support.tickets'), 403);
    }
}
