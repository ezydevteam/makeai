<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TestimonialController extends Controller
{
    public function index(): Response
    {
        $testimonials = Testimonial::orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/CMS/Testimonials/Index', [
            'testimonials' => $testimonials,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'source' => ['required', Rule::in(['manual', 'google', 'trustpilot', 'import'])],
        ]);

        Testimonial::create($validated);

        return back()->with('success', 'Testimonial created successfully.');
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'source' => ['required', Rule::in(['manual', 'google', 'trustpilot', 'import'])],
        ]);

        $testimonial->update($validated);

        return back()->with('success', 'Testimonial updated successfully.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return back()->with('success', 'Testimonial deleted.');
    }

    public function bulkSort(Request $request)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:testimonials,id'],
        ]);

        foreach ($validated['order'] as $position => $id) {
            Testimonial::where('id', $id)->update(['sort_order' => $position]);
        }

        return back()->with('success', 'Sort order saved.');
    }

    public function toggleFeatured(Testimonial $testimonial)
    {
        $testimonial->update(['is_featured' => ! $testimonial->is_featured]);

        return back()->with('success', $testimonial->is_featured ? 'Marked as featured.' : 'Removed from featured.');
    }

    public function toggleActive(Testimonial $testimonial)
    {
        $testimonial->update(['is_active' => ! $testimonial->is_active]);

        return back();
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('csv');
        $rows = array_map('str_getcsv', file($file->getPathname()));
        $header = array_map('strtolower', array_map('trim', array_shift($rows)));
        $imported = 0;

        foreach ($rows as $row) {
            if (count($row) < count($header)) {
                continue;
            }
            $data = array_combine($header, $row);
            Testimonial::create([
                'name' => $data['name'] ?? 'Unknown',
                'role' => $data['role'] ?? null,
                'company' => $data['company'] ?? null,
                'content' => $data['content'] ?? $data['review'] ?? '',
                'rating' => (int) ($data['rating'] ?? 5),
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
                'source' => 'import',
            ]);
            $imported++;
        }

        return back()->with('success', "Imported {$imported} testimonial(s) from CSV.");
    }
}
