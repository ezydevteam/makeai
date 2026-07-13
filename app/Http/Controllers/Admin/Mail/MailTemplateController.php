<?php

namespace App\Http\Controllers\Admin\Mail;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MailTemplateAiAssistRequest;
use App\Http\Requests\Admin\MailTemplateRequest;
use App\Http\Requests\Admin\MailTemplateStoreRequest;
use App\Models\MailTemplate;
use App\Models\User;
use App\Services\AI\AiService;
use Database\Seeders\MailTemplateSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MailTemplateController extends Controller
{
    public function index()
    {
        $seededTemplateCount = MailTemplate::whereIn('slug', MailTemplateSeeder::SYSTEM_TEMPLATE_SLUGS)->count();

        if ($seededTemplateCount < count(MailTemplateSeeder::SYSTEM_TEMPLATE_SLUGS)) {
            app(MailTemplateSeeder::class)->run();
        }

        $affiliateAvailable = app(\App\Services\AffiliateService::class)->isEnabled();

        $templates = MailTemplate::query()
            ->with('editor:id,name')
            ->when(! isProAvailable(), fn ($query) => $query->where('requires_pro', false))
            // Affiliate is a monetization feature gated by the Extended License +
            // its own toggle — hide its templates when it isn't available.
            ->when(! $affiliateAvailable, fn ($query) => $query
                ->where('category', '!=', 'affiliate')
                ->where('slug', '!=', 'referral_earned'))
            ->latest()
            ->get();

        return Inertia::render('Admin/Mail/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function edit(MailTemplate $template)
    {
        abort_if($template->requires_pro && ! isProAvailable(), 404);
        abort_if($this->isAffiliateTemplate($template) && ! app(\App\Services\AffiliateService::class)->isEnabled(), 404);

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
        abort_if($this->isAffiliateTemplate($template) && ! app(\App\Services\AffiliateService::class)->isEnabled(), 404);

        $template->update(array_merge($request->validated(), [
            'last_edited_by' => auth('admin')->id(),
        ]));

        return redirect()
            ->route('admin.mail.templates.index')
            ->with('success', translate('Mail template updated successfully.'));
    }

    private function isAffiliateTemplate(MailTemplate $template): bool
    {
        return $template->category === 'affiliate' || $template->slug === 'referral_earned';
    }

    public function aiAssist(MailTemplateAiAssistRequest $request, AiService $aiService): JsonResponse
    {
        $validated = $request->validated();
        $action = $validated['action'];
        $subject = trim($validated['subject'] ?? '');
        $content = Str::limit(strip_tags($validated['content'] ?? ''), 12000, '');
        $selectedText = trim(strip_tags($validated['selected_text'] ?? ''));

        if (in_array($action, ['improve_content'], true) && $selectedText === '' && $content === '') {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_CONTENT',
                'message' => translate('Add content before using this AI assist action.'),
            ], 422);
        }

        $user = User::internalAi();

        $result = $aiService->complete(
            $user,
            $this->aiAssistPrompt($action, $subject, $content, $selectedText),
            'You are an expert email copywriter for a SaaS platform. Return only the requested content, with no preamble.',
            options: ['max_tokens' => 800, 'temperature' => 0.5],
            toolSlug: 'admin_mail_template_assist'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'content' => \App\Services\TiptapHtmlSanitizer::sanitize(trim($result->content ?? ''), \App\Services\TiptapHtmlSanitizer::BASIC_TAGS),
            ],
            'message' => translate('AI assist completed.'),
        ]);
    }

    private function aiAssistPrompt(string $action, string $subject, string $content, string $selectedText): string
    {
        return match ($action) {
            'generate_content' => "Write a professional, engaging SaaS email body in clean HTML. Use the subject as context. Return HTML only.\n\nSubject: {$subject}",
            'improve_content' => "Improve the selected email text for clarity, flow, and professionalism. Keep the meaning intact. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'generate_subject' => "Generate one concise, engaging email subject line under 50 characters. Return plain text only.\n\nContext:\n{$content}",
        };
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
