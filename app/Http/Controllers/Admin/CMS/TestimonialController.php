<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    private function normalizePayload(TestimonialRequest $request, ?Testimonial $testimonial = null): array
    {
        $validated = $request->validated();
        unset($validated['avatar_file']);

        if ($request->hasFile('avatar_file')) {
            // Store first, then delete the previous avatar only on success (external
            // URLs are left untouched by media_path inside the helper).
            $validated['avatar'] = store_public_upload(
                $request->file('avatar_file'),
                'testimonials',
                $testimonial?->avatar,
            );
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
}
