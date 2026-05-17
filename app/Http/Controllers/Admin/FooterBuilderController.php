<?php

namespace App\Http\Controllers\Admin;

use App\Models\Menu;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class FooterBuilderController extends Controller
{
    /**
     * Default footer configuration.
     */
    private function getDefaults(): array
    {
        return [
            'layout' => 4, // 1, 2, 3, or 4 columns
            'columns' => [
                // Column 1
                [
                    ['id' => 'default_about', 'type' => 'about_text', 'config' => ['logo' => null, 'description' => 'The ultimate AI platform for creators, developers, and businesses. Generate anything you can imagine.']],
                ],
                // Column 2
                [
                    ['id' => 'default_menu_1', 'type' => 'menu_list', 'config' => ['title' => 'Platform', 'menu_slug' => 'footer-1']],
                ],
                // Column 3
                [
                    ['id' => 'default_menu_2', 'type' => 'menu_list', 'config' => ['title' => 'Support', 'menu_slug' => 'footer-2']],
                ],
                // Column 4
                [
                    ['id' => 'default_contact', 'type' => 'contact_info', 'config' => ['title' => 'Contact Us', 'address' => '', 'phone' => '', 'email' => 'support@makeai.com']],
                ],
            ],
            'bottom_bar' => [
                'copyright_text' => '© {year} MakeAI. All rights reserved.',
                'menu_slug' => null,
                'show_payment_icons' => true,
                'payment_icons' => ['visa', 'mastercard', 'paypal', 'stripe'],
                'show_back_to_top' => true,
            ],
        ];
    }

    /**
     * Show the footer builder page.
     */
    public function index()
    {
        $config = Setting::getValue('footer_config');

        if ($config) {
            $config = is_array($config) ? $config : json_decode($config, true) ?? [];
            $config = array_merge($this->getDefaults(), $config);
        } else {
            $config = $this->getDefaults();
        }

        $menus = Menu::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Appearance/FooterBuilder', [
            'config' => $config,
            'menus' => $menus,
        ]);
    }

    /**
     * Update the footer configuration.
     */
    public function update(Request $request)
    {
        $request->validate([
            'layout' => 'required|integer|min:1|max:4',
            'columns' => 'required|array',
            'bottom_bar' => 'required|array',
        ]);

        Setting::updateOrCreate(
            ['key' => 'footer_config'],
            ['value' => json_encode($request->all())]
        );

        return back()->with('success', 'Footer configuration updated successfully.');
    }
}
