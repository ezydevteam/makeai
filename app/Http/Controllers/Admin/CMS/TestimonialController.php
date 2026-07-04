<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestimonialGenerateRequest;
use App\Http\Requests\Admin\TestimonialRequest;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TestimonialController extends Controller
{
    public function index(): Response
    {
        $this->authorizeTestimonials();

        $testimonials = Testimonial::orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/CMS/Testimonials/Index', [
            'testimonials' => $testimonials,
        ]);
    }

    public function store(TestimonialRequest $request)
    {
        $this->authorizeTestimonials();
        $validated = $this->normalizePayload($request);

        Testimonial::create($validated);

        return back()->with('success', translate('Testimonial created successfully.'));
    }

    public function update(TestimonialRequest $request, Testimonial $testimonial)
    {
        $this->authorizeTestimonials();
        $validated = $this->normalizePayload($request, $testimonial);

        $testimonial->update($validated);

        return back()->with('success', translate('Testimonial updated successfully.'));
    }

    public function destroy(Testimonial $testimonial)
    {
        $this->authorizeTestimonials();
        $this->deleteStoredAvatar($testimonial->avatar);
        $testimonial->delete();

        return back()->with('success', translate('Testimonial deleted.'));
    }

    public function bulkSort(Request $request)
    {
        $this->authorizeTestimonials();

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:testimonials,id'],
        ]);

        foreach ($validated['order'] as $position => $id) {
            Testimonial::where('id', $id)->update(['sort_order' => $position]);
        }

        return back()->with('success', translate('Sort order saved.'));
    }

    public function toggleFeatured(Testimonial $testimonial)
    {
        $this->authorizeTestimonials();
        $testimonial->update(['is_featured' => ! $testimonial->is_featured]);

        return back()->with('success', $testimonial->is_featured ? translate('Marked as featured.') : translate('Removed from featured.'));
    }

    public function toggleActive(Testimonial $testimonial)
    {
        $this->authorizeTestimonials();
        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return back();
    }



    public function generate(TestimonialGenerateRequest $request, AiService $aiService): JsonResponse
    {
        $user = User::firstOrCreate(
            ['email' => User::internalAiEmail()],
            [
                'name' => User::internalAiName(),
                'password' => bcrypt(Str::random(32)),
                'is_active' => true,
                'is_banned' => false,
            ]
        );

        $validated = $request->validated();
        $extraPrompt = trim((string) ($validated['prompt'] ?? ''));
        $prompt = "Generate {$validated['count']} realistic SaaS customer testimonials for a {$validated['company_type']} product. Tone: {$validated['tone']}. Return valid JSON array only. Each item must have name, role, company, content, rating from 4 to 5.";

        if ($extraPrompt !== '') {
            $prompt .= "\n\nAdditional admin instructions:\n{$extraPrompt}";
        }

        $result = $aiService->complete(
            $user,
            $prompt,
            'You generate safe demo testimonials for a SaaS admin panel. Return JSON only.',
            options: ['max_tokens' => 1200, 'temperature' => 0.55],
            toolSlug: 'admin_testimonial_generate'
        );

        $items = collect($this->decodeAiJsonArray((string) ($result->content ?? '')))
            ->filter(fn ($item) => is_array($item))
            ->take((int) $validated['count'])
            ->map(function (array $item, int $index) {
                return [
                    'name' => Str::limit(trim((string) ($item['name'] ?? translate('Customer'))), 255, ''),
                    'role' => Str::limit(trim((string) ($item['role'] ?? '')), 255, '') ?: null,
                    'company' => Str::limit(trim((string) ($item['company'] ?? '')), 255, '') ?: null,
                    'content' => trim(strip_tags((string) ($item['content'] ?? ''))),
                    'rating' => min(5, max(1, (int) ($item['rating'] ?? 5))),
                    'is_active' => true,
                    'is_featured' => false,
                    'sort_order' => Testimonial::max('sort_order') + $index + 1,
                    'source' => 'ai',
                ];
            })
            ->filter(fn ($item) => $item['content'] !== '')
            ->values();

        $items->each(fn ($item) => Testimonial::create($item));

        return response()->json([
            'success' => true,
            'message' => translate('Generated :count testimonial(s).', ['count' => $items->count()]),
        ]);
    }

    private function normalizePayload(TestimonialRequest $request, ?Testimonial $testimonial = null): array
    {
        $validated = $request->validated();
        unset($validated['avatar_file']);

        if ($request->hasFile('avatar_file')) {
            if ($testimonial) {
                $this->deleteStoredAvatar($testimonial->avatar);
            }

            $validated['avatar'] = $request->file('avatar_file')->store('testimonials', 'public');
        }

        return $validated;
    }

    private function deleteStoredAvatar(?string $avatar): void
    {
        if (! $avatar || str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($avatar);
    }

    private function authorizeTestimonials(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.testimonials')) {
            abort(403);
        }
    }

    private function decodeAiJsonArray(string $content): array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\[.*\])/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
