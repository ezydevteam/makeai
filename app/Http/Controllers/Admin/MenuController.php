<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Http\Requests\Admin\MenuItemsReorderRequest;
use App\Http\Requests\Admin\MenuRequest;
use App\Models\BlogCategory;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function getRouteOptions(): array
    {
        return $this->routeOptions();
    }

    public function index()
    {
        return Inertia::render('Admin/Appearance/Menus', [
            'menus' => Menu::with(['items.page'])->orderBy('name')->get(),
            'pages' => Page::query()
                ->published()
                ->orderBy('title')
                ->get(['id', 'title', 'slug']),
            'blogCategories' => BlogCategory::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'aiCategories' => Category::aiTools()->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'routeOptions' => $this->routeOptions(),
        ]);
    }

    public function store(MenuRequest $request)
    {
        Menu::create($request->validated());

        return back()->with('success', translate('Menu created.'));
    }

    public function update(MenuRequest $request, Menu $menu)
    {
        $menu->update($request->validated());

        return back()->with('success', translate('Menu updated.'));
    }

    public function addItem(MenuItemRequest $request, Menu $menu)
    {
        $menu->items()->create($this->normalizeItemData($request, menu: $menu));

        return back()->with('success', translate('Menu item added.'));
    }

    public function updateItem(MenuItemRequest $request, MenuItem $item)
    {
        $item->update($this->normalizeItemData($request, $item, $item->menu));

        return back()->with('success', translate('Item updated.'));
    }

    public function deleteItem(MenuItem $item)
    {
        $item->delete();

        return back()->with('success', translate('Item removed.'));
    }

    public function reorderItems(MenuItemsReorderRequest $request, Menu $menu)
    {
        $items = collect($request->validated('items'));
        $menuItemIds = $menu->items()->pluck('id')->all();

        DB::transaction(function () use ($items, $menuItemIds): void {
            foreach ($items as $item) {
                $id = (int) $item['id'];
                $parentId = filled($item['parent_id'] ?? null) ? (int) $item['parent_id'] : null;

                if (! in_array($id, $menuItemIds, true)) {
                    continue;
                }

                if ($parentId && (! in_array($parentId, $menuItemIds, true) || $parentId === $id)) {
                    $parentId = null;
                }

                MenuItem::query()
                    ->whereKey($id)
                    ->update([
                        'parent_id' => $parentId,
                        'sort_order' => (int) $item['sort_order'],
                    ]);
            }
        });

        return back()->with('success', translate('Menu order updated.'));
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return back()->with('success', translate('Menu deleted.'));
    }

    public function import(Request $request, Menu $menu)
    {
        $request->validate(['json' => ['required', 'string', 'max:100000']]);

        $data = json_decode($request->input('json'), true);

        if (! is_array($data) || empty($data['items'])) {
            return back()->with('error', translate('Invalid menu JSON. An "items" array is required.'));
        }

        DB::transaction(function () use ($menu, $data) {
            if (! empty($data['name'])) {
                $menu->update(['name' => $data['name']]);
            }
            if (! empty($data['slug'])) {
                $menu->update(['slug' => $data['slug']]);
            }

            $menu->items()->delete();

            $idMap = [];

            // First pass: create all items
            foreach ($data['items'] as $index => $itemData) {
                $item = $menu->items()->create([
                    'label' => $itemData['label'] ?? 'Untitled',
                    'type' => $itemData['type'] ?? 'url',
                    'url' => ($itemData['type'] ?? 'url') === 'url' ? ($itemData['url'] ?? '') : null,
                    'page_id' => ($itemData['type'] ?? 'url') === 'page' ? ($itemData['page_id'] ?? null) : null,
                    'route_name' => ($itemData['type'] ?? 'url') === 'route' ? ($itemData['route_name'] ?? null) : null,
                    'parent_id' => null,
                    'target' => $itemData['target'] ?? '_self',
                    'icon' => $itemData['icon'] ?? null,
                    'badge_text' => $itemData['badge_text'] ?? null,
                    'badge_color' => $itemData['badge_color'] ?? null,
                    'is_active' => (bool) ($itemData['is_active'] ?? true),
                    'requires_auth' => $itemData['requires_auth'] ?? 'none',
                    'mega_menu' => (bool) ($itemData['mega_menu'] ?? false),
                    'mega_menu_content' => $itemData['mega_menu_content'] ?? null,
                    'sort_order' => (int) ($itemData['sort_order'] ?? $index),
                ]);
                $idMap[$itemData['id'] ?? $index] = $item->id;
            }

            // Second pass: resolve parent_ids
            foreach ($data['items'] as $index => $itemData) {
                $sourceId = $itemData['id'] ?? $index;
                $newId = $idMap[$sourceId] ?? null;
                $parentSourceId = $itemData['parent_id'] ?? null;

                if ($newId && $parentSourceId && isset($idMap[$parentSourceId]) && $idMap[$parentSourceId] !== $newId) {
                    MenuItem::whereKey($newId)->update(['parent_id' => $idMap[$parentSourceId]]);
                }
            }
        });

        return back()->with('success', translate('Menu items imported.'));
    }

    private function normalizeItemData(MenuItemRequest $request, ?MenuItem $item = null, ?Menu $menu = null): array
    {
        $data = $request->validated();
        $data['parent_id'] = filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null;
        $data['page_id'] = $data['type'] === 'page' ? (int) $data['page_id'] : null;
        $data['url'] = $data['type'] === 'url' ? $data['url'] : null;
        $data['route_name'] = $data['type'] === 'route' ? $data['route_name'] : null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['mega_menu'] = (bool) ($data['mega_menu'] ?? false);
        $data['badge_text'] = filled($data['badge_text'] ?? null) ? $data['badge_text'] : null;
        $data['badge_color'] = filled($data['badge_color'] ?? null) ? $data['badge_color'] : null;
        $data['mega_menu_content'] = $data['mega_menu'] && filled($data['mega_menu_content'] ?? null)
            ? $data['mega_menu_content']
            : null;

        if ($item && $data['parent_id'] === $item->id) {
            $data['parent_id'] = null;
        }

        if ($menu && $data['parent_id'] && ! $menu->items()->whereKey($data['parent_id'])->exists()) {
            $data['parent_id'] = null;
        }

        return $data;
    }

    private function routeOptions(): array
    {
        return [
            ['name' => 'home', 'label' => translate('Home')],
            ['name' => 'pricing', 'label' => translate('Pricing')],
            ['name' => 'ai.tools.index', 'label' => translate('AI Tools')],
            ['name' => 'blog.index', 'label' => translate('Blog')],
            ['name' => 'login', 'label' => translate('Login')],
            ['name' => 'register', 'label' => translate('Register')],
        ];
    }
}
