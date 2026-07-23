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
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MailTemplateController extends Controller
{
    public function index(Request $request)
    {
        // Self-heal missing system templates in DEVELOPMENT only. The shipped
        // package excludes database/seeders (its data comes from database/data/
        // data.sql), so MailTemplateSeeder does not exist on a buyer's install and
        // referencing it here 500'd the whole page. Guarded on class_exists: in a
        // real install the templates are already imported, so there is nothing to
        // heal; in a dev checkout the seeder runs as before.
        if (class_exists(MailTemplateSeeder::class)) {
            $seededTemplateCount = MailTemplate::whereIn('slug', MailTemplateSeeder::SYSTEM_TEMPLATE_SLUGS)->count();

            if ($seededTemplateCount < count(MailTemplateSeeder::SYSTEM_TEMPLATE_SLUGS)) {
                app(MailTemplateSeeder::class)->run();
            }
        }

        $proAvailable = isProAvailable();
        $affiliateAvailable = app(\App\Services\AffiliateService::class)->isEnabled();
        $ticketsEnabled = (bool) settings('tickets_enabled', true);

        // The visibility gates apply to every read below (list, category filter),
        // so build them once rather than restating them per query.
        $visible = fn () => MailTemplate::query()
            ->when(! $proAvailable, fn ($query) => $query->where('requires_pro', false))
            // Affiliate is a monetization feature gated by the Extended License +
            // its own toggle — hide its templates when it isn't available.
            ->when(! $affiliateAvailable, fn ($query) => $query
                ->where('category', '!=', 'affiliate')
                ->where('slug', '!=', 'referral_earned'))
            // Same idea for the support desk: no ticketing, no ticket templates.
            ->when(! $ticketsEnabled, fn ($query) => $query->where('category', '!=', 'support'));

        $search = trim((string) $request->string('search'));
        $category = trim((string) $request->string('category'));
        $type = trim((string) $request->string('type'));

        // Without pro, $visible() has already dropped every requires_pro template,
        // so honouring type=pro would render an empty table with nothing to explain
        // it. A stale bookmark or hand-edited URL falls back to no type filter.
        if ($type === 'pro' && ! $proAvailable) {
            $type = '';
        }

        $templates = $visible()
            ->with('editor:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($group) => $group
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($type !== '', fn ($query) => match ($type) {
                'system' => $query->where('is_system', true),
                'custom' => $query->where('is_system', false),
                'active' => $query->where('is_active', true),
                'disabled' => $query->where('is_active', false),
                'pro' => $query->where('requires_pro', true),
                default => $query,
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Mail/Templates/Index', [
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'type' => $type,
            ],
            // Sourced from the full visible set, not the current page — otherwise the
            // dropdown would only offer categories that happen to appear on page 1.
            'categories' => $visible()->distinct()->orderBy('category')->pluck('category'),
            'proAvailable' => $proAvailable,
        ]);
    }

    public function edit(MailTemplate $template)
    {
        abort_if($template->requires_pro && ! isProAvailable(), 404);
        abort_if($this->isAffiliateTemplate($template) && ! app(\App\Services\AffiliateService::class)->isEnabled(), 404);
        abort_if($template->category === 'support' && ! settings('tickets_enabled', true), 404);

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
        abort_if($template->category === 'support' && ! settings('tickets_enabled', true), 404);

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

        // continue_writing works off the surrounding body, not a highlight, so
        // it is the one selection-menu action that does not require one.
        if (in_array($action, $this->selectionAiActions(), true) && $action !== 'continue_writing' && $selectedText === '') {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_SELECTION',
                'message' => translate('Select text before using this AI action.'),
            ], 422);
        }

        if ($action !== 'generate_content' && $content === '' && $selectedText === '') {
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

        $formatted = $this->formatAiAssistResult($action, trim($result->content ?? ''));

        // An empty completion used to fall through as a success, so the editor
        // "applied" nothing and still showed the success toast.
        if (trim($formatted['content']) === '') {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_RESULT',
                'message' => translate('The AI returned nothing. Try again.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'message' => translate('AI assist completed.'),
        ]);
    }

    private function aiAssistPrompt(string $action, string $subject, string $content, string $selectedText): string
    {
        return match ($action) {
            'generate_content' => "Write a professional, engaging SaaS email body in clean HTML. Use the subject as context. Return HTML only.\n\nSubject: {$subject}",
            // Document-level: rewrites the whole body, so it reads $content.
            'improve_content' => "Improve this email body for clarity, flow, and professionalism. Keep the meaning and every {placeholder} token intact. Return the full rewritten body as clean HTML only.\n\nSubject: {$subject}\n\nEmail body:\n{$content}",
            'generate_subject' => "Generate one concise, engaging email subject line under 50 characters. Return plain text only.\n\nContext:\n{$content}",
            'improve_selection' => "Improve the selected email text for clarity, flow, and professionalism. Keep the meaning and every {placeholder} token intact. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'shorten_selection' => "Shorten the selected email text while preserving the main meaning and every {placeholder} token. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'expand_selection' => "Expand the selected email text with useful detail and smoother flow. Keep every {placeholder} token intact. Return clean HTML only.\n\nSubject: {$subject}\n\nSelected text:\n{$selectedText}",
            'rephrase_selection' => "Rephrase the selected email text while preserving the original meaning and every {placeholder} token. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'translate_selection' => "Translate the selected email text into clear English unless it is already English; if it is already English, translate it into Bengali. Leave every {placeholder} token untranslated. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'change_tone' => "Rewrite the selected email text in a professional, friendly tone. Preserve the meaning and every {placeholder} token. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'summarize_selection' => "Summarize the selected email text into a concise version. Preserve the key points and every {placeholder} token. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'fix_grammar' => "Fix grammar, spelling, punctuation, and awkward phrasing in the selected email text. Preserve the meaning and every {placeholder} token. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'continue_writing' => "Continue writing this email from the cursor with 1 or 2 short, useful paragraphs. Return clean HTML only.\n\nSubject: {$subject}\n\nExisting body:\n{$content}",
        };
    }

    private function formatAiAssistResult(string $action, string $content): array
    {
        // The subject is a plain <input>: sanitizing it as HTML left the model's
        // wrapping <p> tags in the field as literal markup.
        if ($action === 'generate_subject') {
            return ['content' => Str::limit(trim(strip_tags($content), "\"' \n\r\t\v\0"), 255, '')];
        }

        return ['content' => \App\Services\TiptapHtmlSanitizer::sanitize($content, \App\Services\TiptapHtmlSanitizer::BASIC_TAGS)];
    }

    private function selectionAiActions(): array
    {
        return [
            'improve_selection',
            'shorten_selection',
            'expand_selection',
            'rephrase_selection',
            'translate_selection',
            'change_tone',
            'summarize_selection',
            'fix_grammar',
            'continue_writing',
        ];
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
