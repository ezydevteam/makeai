<?php

namespace App\Http\Controllers\Admin\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactSettingsRequest;
use Illuminate\Http\RedirectResponse;

class ContactSettingsController extends Controller
{
    public function edit(): RedirectResponse
    {
        $this->authorizeContact();

        return redirect()->route('admin.contact.messages.index', ['settings' => 1]);
    }

    public function update(ContactSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $type = is_bool($value) ? 'boolean' : 'string';
            settings_set($key, $value, $type, 'contact');
        }

        return back()->with('success', translate('Contact settings saved.'));
    }

    private function authorizeContact(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.pages')) {
            abort(403, translate('Unauthorized.'));
        }
    }
}
