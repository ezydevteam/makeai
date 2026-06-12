<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SidebarBuilderRequest;
use App\Models\Setting;
use Inertia\Inertia;

class SidebarBuilderController extends Controller
{
    public function index()
    {
        $sidebarConfig = Setting::where('key', 'sidebar_config')->value('value');
        $config = $sidebarConfig ? json_decode($sidebarConfig, true) : null;

        $defaultConfig = [
            'blocks' => [
                ['id' => 'b1', 'type' => 'search_box', 'config' => ['title' => translate('Search'), 'placeholder' => translate('Search articles...')]],
                ['id' => 'b2', 'type' => 'categories_list', 'config' => ['title' => translate('Categories'), 'show_count' => true]],
                ['id' => 'b3', 'type' => 'recent_posts', 'config' => ['title' => translate('Recent Posts'), 'count' => 3]],
            ],
            'position' => 'right',
            'sticky' => true,
            'show_on_pages' => [],
        ];

        return Inertia::render('Admin/Appearance/SidebarBuilder', [
            'config' => $config ?: $defaultConfig,
            'availablePages' => $this->availablePages(),
        ]);
    }

    public function update(SidebarBuilderRequest $request)
    {
        $config = $request->sanitizedConfig();

        Setting::updateOrCreate(
            ['key' => 'sidebar_config'],
            ['value' => json_encode($config)]
        );

        return back()->with('success', translate('Sidebar configuration updated successfully.'));
    }

    private function availablePages(): array
    {
        return [
            ['key' => 'home', 'label' => translate('Homepage')],
            ['key' => 'blog.index', 'label' => translate('Blog Listing')],
            ['key' => 'blog.show', 'label' => translate('Single Post')],
            ['key' => 'blog.category', 'label' => translate('Blog Category')],
            ['key' => 'blog.tag', 'label' => translate('Blog Tag')],
            ['key' => 'ai.tools.index', 'label' => translate('AI Tools Directory')],
            ['key' => 'ai.tools.show', 'label' => translate('Single Tool')],
            ['key' => 'page.*', 'label' => translate('Custom Pages')],
            ['key' => 'contact', 'label' => translate('Contact')],
        ];
    }
}
