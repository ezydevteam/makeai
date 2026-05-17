<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class MailController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Mail/Index', [
            'settings' => Setting::getGroup('mail'),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mail_driver' => 'required|string|in:smtp,mailgun,ses,postmark,sendgrid,log,array',
            'mail_host' => 'nullable|string',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|string|in:tls,ssl,null',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string',
            'mailgun_domain' => 'nullable|string',
            'mailgun_secret' => 'nullable|string',
            'mailgun_endpoint' => 'nullable|string',
            'ses_key' => 'nullable|string',
            'ses_secret' => 'nullable|string',
            'ses_region' => 'nullable|string',
        ]);

        Setting::updateGroup('mail', $validated);

        return back()->with('success', 'Mail settings updated successfully.');
    }

    public function test(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            Mail::to($request->email)->send(new TestMail);

            return back()->with('success', 'Test email sent successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send test email: '.$e->getMessage());
        }
    }
}
