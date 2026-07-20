<?php

namespace App\Http\Controllers\Admin\Appearance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SidebarBuilderRequest;
use App\Models\Setting;
use Inertia\Inertia;

class SidebarController extends Controller
{
    public function getAvailablePages(): array
    {
        return $this->availablePages();
    }

    public function index()
    {
        // Read through getValue() (not a raw query) — sidebar_config lives inside the
        // `appearance` json blob, so a direct where('key', ...) row lookup would miss it.
        // Stored as type=json, so getValue() returns a decoded array.
        $config = Setting::getValue('sidebar_config');

        return Inertia::render('Admin/Appearance/SidebarBuilder', [
            'config' => $this->normalizeConfig(is_array($config) ? $config : null),
            'availablePages' => $this->availablePages(),
        ]);
    }

    public function update(SidebarBuilderRequest $request)
    {
        $config = $request->sanitizedConfig();

        // Persist as JSON so castValue() decodes it back to an array, and route the
        // write through setValue() which invalidates the forever-cache. A raw
        // updateOrCreate() would store type='string' (returning the JSON string to
        // the frontend) and leave the stale cached value in place.
        Setting::setValue('sidebar_config', $config, 'json', 'appearance');

        return back()->with('success', translate('Sidebar configuration updated successfully.'));
    }

    /**
     * Coerce any stored/absent config into the current three-area shape.
     * Legacy configs stored a flat `blocks` array that rendered on CMS pages,
     * so those blocks migrate into the `page` area to preserve behavior.
     */
    private function normalizeConfig(?array $config): array
    {
        $empty = ['main' => [], 'blog' => [], 'page' => []];

        if ($config === null) {
            return ['areas' => array_merge($empty, ['page' => $this->defaultPageBlocks()])];
        }

        if (isset($config['areas']) && is_array($config['areas'])) {
            return ['areas' => array_merge($empty, array_intersect_key($config['areas'], $empty))];
        }

        if (isset($config['blocks']) && is_array($config['blocks'])) {
            return ['areas' => array_merge($empty, ['page' => $config['blocks']])];
        }

        return ['areas' => $empty];
    }

    private function defaultPageBlocks(): array
    {
        return [
            ['id' => 'b1', 'type' => 'search_box', 'config' => ['title' => translate('Search'), 'placeholder' => translate('Search articles...')]],
            ['id' => 'b2', 'type' => 'categories_list', 'config' => ['title' => translate('Categories'), 'show_count' => true]],
            ['id' => 'b3', 'type' => 'recent_posts', 'config' => ['title' => translate('Recent Posts'), 'count' => 3]],
        ];
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
