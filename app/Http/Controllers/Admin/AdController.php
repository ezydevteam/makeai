<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::latest()->get()->map(function ($ad) {
            return array_merge($ad->toArray(), [
                'ctr' => $ad->ctr,
            ]);
        });

        return Inertia::render('Admin/Ads/Index', [
            'ads' => $ads,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Ads/Editor', [
            'ad' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:image,script',
            'placement' => 'required|in:sidebar,top,bottom,feed,blog_side',
            'content' => 'required_if:type,script|nullable|string',
            'image' => 'required_if:type,image|nullable|image|max:2048',
            'link_url' => 'required_if:type,image|nullable|url',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('ads', 'public');
        }

        Ad::create($validated);

        return redirect()->route('admin.ads.index')->with('success', 'Advertisement created successfully.');
    }

    public function edit(Ad $ad)
    {
        return Inertia::render('Admin/Ads/Editor', [
            'ad' => $ad,
        ]);
    }

    public function update(Request $request, Ad $ad)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:image,script',
            'placement' => 'required|in:sidebar,top,bottom,feed,blog_side',
            'content' => 'required_if:type,script|nullable|string',
            'image' => 'nullable|image|max:2048',
            'link_url' => 'required_if:type,image|nullable|url',
            'is_active' => 'required|boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($request->hasFile('image')) {
            if ($ad->image_path) {
                Storage::disk('public')->delete($ad->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('ads', 'public');
        }

        $ad->update($validated);

        return redirect()->route('admin.ads.index')->with('success', 'Advertisement updated successfully.');
    }

    public function destroy(Ad $ad)
    {
        if ($ad->image_path) {
            Storage::disk('public')->delete($ad->image_path);
        }
        $ad->delete();

        return redirect()->route('admin.ads.index')->with('success', 'Advertisement deleted successfully.');
    }

    public function toggle(Ad $ad)
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        return back();
    }
}
