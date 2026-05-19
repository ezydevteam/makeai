<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailTemplateRequest;
use App\Http\Requests\Admin\MailTemplateStoreRequest;
use App\Models\MailTemplate;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MailTemplateController extends Controller
{
    public function index()
    {
        $seededTemplateCount = MailTemplate::whereIn('slug', MailTemplateSeeder::SYSTEM_TEMPLATE_SLUGS)->count();

        if ($seededTemplateCount < count(MailTemplateSeeder::SYSTEM_TEMPLATE_SLUGS)) {
            app(MailTemplateSeeder::class)->run();
        }

        $templates = MailTemplate::query()
            ->when(! isProAvailable(), fn ($query) => $query->where('requires_pro', false))
            ->latest()
            ->get();

        return Inertia::render('Admin/Mail/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function edit(MailTemplate $template)
    {
        abort_if($template->requires_pro && ! isProAvailable(), 404);

        return Inertia::render('Admin/Mail/Templates/Editor', [
            'template' => $template,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Mail/Templates/Editor', [
            'template' => [
                'id' => null,
                'slug' => '',
                'name' => '',
                'subject' => '',
                'content' => '<h1>Hello {user_name}</h1><p>Write your custom email content here.</p>',
                'is_active' => true,
                'is_system' => false,
                'category' => 'custom',
            ],
        ]);
    }

    public function store(MailTemplateStoreRequest $request): RedirectResponse
    {
        MailTemplate::create(array_merge($request->validated(), [
            'category' => 'custom',
            'is_system' => false,
            'requires_pro' => false,
            'last_edited_by' => auth('admin')->id(),
        ]));

        return redirect()
            ->route('admin.mail.templates.index')
            ->with('success', translate('Custom mail template created successfully.'));
    }

    public function update(MailTemplateRequest $request, MailTemplate $template)
    {
        abort_if($template->requires_pro && ! isProAvailable(), 404);

        $template->update(array_merge($request->validated(), [
            'last_edited_by' => auth('admin')->id(),
        ]));

        return redirect()
            ->route('admin.mail.templates.index')
            ->with('success', translate('Mail template updated successfully.'));
    }

    public function destroy(MailTemplate $template): RedirectResponse
    {
        if ($template->is_system) {
            return back()->with('error', translate('System templates cannot be deleted.'));
        }

        $template->delete();

        return back()->with('success', translate('Custom mail template deleted successfully.'));
    }
}
