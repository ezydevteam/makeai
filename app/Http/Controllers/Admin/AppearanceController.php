<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppearanceSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppearanceController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Appearance/Settings', [
            'admin_settings' => AppearanceSetting::getForScope('admin'),
            'theme_settings' => AppearanceSetting::getForScope('theme_default'),
        ]);
    }

    public function update(Request $request)
    {
        $scope = $request->input('scope', 'theme_default');
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            AppearanceSetting::updateOrCreate(
                ['scope' => $scope, 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->with('success', 'Appearance settings updated.');
    }
}
