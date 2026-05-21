<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SidebarBuilderController extends Controller
{
    /**
     * Display the Sidebar Builder page.
     */
    public function index()
    {
        // Load current configuration from settings
        $sidebarConfig = Setting::where('key', 'sidebar_config')->value('value');

        $config = $sidebarConfig ? json_decode($sidebarConfig, true) : null;

        $defaultConfig = [
            'blocks' => [
                [
                    'id' => 'b1',
                    'type' => 'search_box',
                    'config' => [
                        'title' => 'Search',
                        'placeholder' => 'Search articles...',
                    ],
                ],
                [
                    'id' => 'b2',
                    'type' => 'categories_list',
                    'config' => [
                        'title' => 'Categories',
                        'show_count' => true,
                    ],
                ],
                [
                    'id' => 'b3',
                    'type' => 'recent_posts',
                    'config' => [
                        'title' => 'Recent Posts',
                        'count' => 3,
                    ],
                ],
            ],
            'position' => 'right', // 'left' or 'right'
            'sticky' => true,
        ];

        return Inertia::render('Admin/Appearance/SidebarBuilder', [
            'config' => $config ?: $defaultConfig,
        ]);
    }

    /**
     * Update the Sidebar Builder configuration.
     */
    public function update(Request $request)
    {
        $request->validate([
            'blocks' => 'required|array',
            'position' => 'required|string|in:left,right',
            'sticky' => 'required|boolean',
        ]);

        Setting::updateOrCreate(
            ['key' => 'sidebar_config'],
            ['value' => json_encode($request->only(['blocks', 'position', 'sticky']))]
        );

        return back()->with('success', translate('Sidebar configuration updated successfully.'));
    }
}
