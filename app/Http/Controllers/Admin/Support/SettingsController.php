<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Support\SupportSettingsRequest;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function edit(): RedirectResponse
    {
        $this->authorizeSupport();

        return redirect()->route('admin.support.tickets.index', ['settings' => 1]);
    }

    public function update(SupportSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            settings_set($key, $value, $type, 'support');
        }

        return back()->with('success', translate('Support settings updated.'));
    }

    private function authorizeSupport(): void
    {
    }
}
