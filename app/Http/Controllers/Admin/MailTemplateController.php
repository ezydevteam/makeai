<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MailTemplateController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Mail/Templates/Index', [
            'templates' => MailTemplate::latest()->get(),
        ]);
    }

    public function edit(MailTemplate $template)
    {
        return Inertia::render('Admin/Mail/Templates/Editor', [
            'template' => $template,
        ]);
    }

    public function update(Request $request, MailTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:500',
            'content' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        $template->update(array_merge($validated, [
            'last_edited_by' => auth()->id(),
        ]));

        return redirect()->route('admin.mail.templates.index')->with('success', 'Mail template updated successfully.');
    }
}
