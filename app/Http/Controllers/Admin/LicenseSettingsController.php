<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateLicenseRequest;
use App\Services\LicenseService;
use Inertia\Inertia;

class LicenseSettingsController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenseService,
    ) {}

    public function edit()
    {
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['settings.license', 'settings.manage']),
            403
        );

        return Inertia::render('Admin/License', [
            'status' => $this->licenseService->getStatus(),
            'item_id' => config('license.item_id'),
        ]);
    }

    public function activate(ActivateLicenseRequest $request)
    {
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['settings.license', 'settings.manage']),
            403
        );

        $result = $this->licenseService->verify($request->validated('purchase_code'));

        if (! $result->valid) {
            return back()->with('error', $result->error);
        }

        return redirect()
            ->route('admin.license.settings')
            ->with('success', translate('License activated successfully! :type for :buyer.', [
                'type' => $result->type === LicenseService::TYPE_EXTENDED ? 'Extended License' : 'Regular License',
                'buyer' => $result->buyer,
            ]));
    }

    public function deactivate()
    {
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['settings.license', 'settings.manage']),
            403
        );

        $this->licenseService->deactivate();

        return redirect()
            ->route('admin.license.settings')
            ->with('success', translate('License deactivated. The app is now in unverified mode.'));
    }

    public function reverify()
    {
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['settings.license', 'settings.manage']),
            403
        );

        $success = $this->licenseService->reverify();

        return back()->with(
            $success ? 'success' : 'error',
            $success
                ? translate('License re-verified successfully.')
                : translate('Re-verification failed. Grace period started.')
        );
    }
}
