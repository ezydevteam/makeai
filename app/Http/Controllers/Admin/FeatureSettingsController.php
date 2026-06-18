<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeatureSettingsRequest;
use Inertia\Inertia;
use Inertia\Response;

class FeatureSettingsController extends Controller
{
    public function edit(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Admin/Settings/Features', [
            'features' => [
                'scroll_to_top_enabled' => (bool) settings('scroll_to_top_enabled', true),
                'ai_chat_enabled' => (bool) settings('ai_chat_enabled', true),
                'ai_variations_enabled' => (bool) settings('ai_variations_enabled', true),
                'social_sharing_enabled' => (bool) settings('social_sharing_enabled', true),
                'document_editor_enabled' => (bool) settings('document_editor_enabled', true),
                'favorites_enabled' => (bool) settings('favorites_enabled', true),
                'reviews_enabled' => (bool) settings('reviews_enabled', true),
                'recently_used_tools_enabled' => (bool) settings('recently_used_tools_enabled', true),
                'estimated_generation_time_enabled' => (bool) settings('estimated_generation_time_enabled', true),
            ],
        ]);
    }

    public function update(FeatureSettingsRequest $request)
    {
        $this->authorizeSettings();

        $features = [
            'scroll_to_top_enabled',
            'ai_chat_enabled',
            'ai_variations_enabled',
            'social_sharing_enabled',
            'document_editor_enabled',
            'favorites_enabled',
            'reviews_enabled',
            'recently_used_tools_enabled',
            'estimated_generation_time_enabled',
            'subscriptions_enabled',
        ];

        foreach ($features as $feature) {
            settings_set($feature, (bool) $request->validated($feature), 'boolean', 'features');
        }

        return back()->with('success', translate('Feature settings updated.'));
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
