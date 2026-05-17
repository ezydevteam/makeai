<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MailLayoutController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Mail/Layout', [
            'layout' => Setting::getValue('mail_layout', '<html><body>{content}</body></html>'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(['layout' => 'required|string']);

        Setting::setValue('mail_layout', $request->layout, 'string', 'mail');

        return back()->with('success', 'Email layout updated successfully.');
    }
}
