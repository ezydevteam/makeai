<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Appearance/Menus', [
            'menus' => Menu::with('items')->get(),
            'pages' => Page::select('id', 'title', 'slug')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'slug' => 'required|string|unique:menus,slug']);
        Menu::create($request->all());

        return back()->with('success', 'Menu created.');
    }

    public function addItem(Request $request, Menu $menu)
    {
        $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:url,page,route',
            'url' => 'required_if:type,url',
            'page_id' => 'required_if:type,page',
            'route_name' => 'required_if:type,route',
        ]);

        $menu->items()->create($request->all());

        return back()->with('success', 'Menu item added.');
    }

    public function updateItem(Request $request, MenuItem $item)
    {
        $item->update($request->all());

        return back()->with('success', 'Item updated.');
    }

    public function deleteItem(MenuItem $item)
    {
        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return back()->with('success', 'Menu deleted.');
    }
}
