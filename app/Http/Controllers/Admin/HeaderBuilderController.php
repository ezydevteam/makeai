<?php

namespace App\Http\Controllers\Admin;

use App\Models\Menu;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class HeaderBuilderController extends Controller
{
    /**
     * Default header configuration.
     */
    private function getDefaults(): array
    {
        return [
            'layout' => 'classic', // classic, centered, minimal
            'sticky' => true,
            'transparent_homepage' => false,
            'height' => 72,
            'hide_on_scroll' => false,
            'blocks' => [
                ['id' => 'logo', 'type' => 'logo', 'enabled' => true, 'config' => [
                    'image' => null,
                    'alt' => 'MakeAI',
                    'link' => '/',
                    'show_text' => true,
                    'text' => 'MakeAI',
                ]],
                ['id' => 'nav', 'type' => 'navigation', 'enabled' => true, 'config' => [
                    'menu_slug' => 'main',
                ]],
                ['id' => 'search', 'type' => 'search', 'enabled' => false, 'config' => []],
                ['id' => 'lang', 'type' => 'language_switcher', 'enabled' => false, 'config' => []],
                ['id' => 'dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => []],
                ['id' => 'cta', 'type' => 'cta_button', 'enabled' => true, 'config' => [
                    'text' => 'Get Started',
                    'link' => '/register',
                    'style' => 'filled', // filled, outline
                    'color' => 'primary',
                ]],
                ['id' => 'user', 'type' => 'user_menu', 'enabled' => true, 'config' => [
                    'show_credits' => true,
                    'show_avatar' => true,
                ]],
                ['id' => 'credits', 'type' => 'credit_balance', 'enabled' => false, 'config' => []],
                ['id' => 'notify', 'type' => 'notification_bell', 'enabled' => false, 'config' => []],
                ['id' => 'social', 'type' => 'social_icons', 'enabled' => false, 'config' => [
                    'icons' => [],
                ]],
                ['id' => 'html', 'type' => 'custom_html', 'enabled' => false, 'config' => [
                    'content' => '',
                ]],
            ],
            'mobile' => [
                'menu_slug' => 'mobile',
                'show_logo' => true,
                'show_hamburger' => true,
            ],
        ];
    }

    /**
     * Show the header builder page.
     */
    public function index()
    {
        $config = Setting::getValue('header_config');

        $defaults = $this->getDefaults();

        if ($config) {
            $savedConfig = is_array($config) ? $config : json_decode($config, true) ?? [];

            // Merge top-level keys
            $config = array_merge($defaults, $savedConfig);

            // Ensure all default blocks exist (in case new ones were added to the codebase after the user saved)
            if (isset($savedConfig['blocks']) && is_array($savedConfig['blocks'])) {
                $savedBlockIds = array_column($savedConfig['blocks'], 'id');
                $mergedBlocks = $savedConfig['blocks'];

                foreach ($defaults['blocks'] as $defaultBlock) {
                    if (! in_array($defaultBlock['id'], $savedBlockIds)) {
                        $mergedBlocks[] = $defaultBlock;
                    }
                }
                $config['blocks'] = $mergedBlocks;
            }
        } else {
            $config = $defaults;
        }

        $menus = Menu::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Appearance/HeaderBuilder', [
            'config' => $config,
            'menus' => $menus,
        ]);
    }

    /**
     * Save the header configuration.
     */
    public function update(Request $request)
    {
        $request->validate([
            'layout' => 'required|in:classic,centered,minimal',
            'sticky' => 'boolean',
            'transparent_homepage' => 'boolean',
            'height' => 'integer|min:48|max:120',
            'hide_on_scroll' => 'boolean',
            'blocks' => 'required|array',
            'mobile' => 'required|array',
        ]);

        Setting::setValue('header_config', $request->all(), 'json', 'appearance');

        return back()->with('success', 'Header configuration saved successfully.');
    }
}
