<?php

namespace App\Http\Controllers\Admin;

use App\Models\Menu;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;

class HeaderBuilderController extends Controller
{
    /**
     * Default header configuration.
     */
    private function getDefaults(): array
    {
        return [
            'top' => [
                'enabled' => false,
                'sticky' => false,
                'transparent_homepage' => false,
                'height' => 40,
                'hide_on_scroll' => false,
                'container_width' => 'default',
                'sticky_behavior' => 'none',
                'upscroll_offset' => 80,
                'downscroll_offset' => 80,
                'transition_enabled' => true,
                'shadow' => false,
                'progressbar' => false,
                'blocks' => [
                    ['id' => 'top_nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'top', 'alignment' => 'center', 'text_color' => '', 'hover_color' => '', 'hover_style' => 'underline', 'submenu_bg_color' => '', 'submenu_text_color' => '']],
                    ['id' => 'top_lang', 'type' => 'language_switcher', 'enabled' => true, 'config' => []],
                    ['id' => 'top_html', 'type' => 'custom_html', 'enabled' => false, 'config' => ['content' => '']],
                ],
            ],
            'main' => [
                'enabled' => true,
                'sticky' => true,
                'transparent_homepage' => false,
                'height' => 72,
                'hide_on_scroll' => false,
                'container_width' => 'default',
                'sticky_behavior' => 'always',
                'upscroll_offset' => 80,
                'downscroll_offset' => 80,
                'transition_enabled' => true,
                'shadow' => false,
                'progressbar' => false,
                'blocks' => [
                    ['id' => 'logo', 'type' => 'logo', 'enabled' => true, 'config' => [
                        'image' => null,
                        'alt' => settings('app_name', translate('Application')),
                        'link' => '/',
                        'show_text' => true,
                        'text' => settings('app_name', translate('Application')),
                    ]],
                    ['id' => 'nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'main', 'alignment' => 'center', 'text_color' => '', 'hover_color' => '', 'hover_style' => 'underline', 'submenu_bg_color' => '', 'submenu_text_color' => '']],
                    ['id' => 'search', 'type' => 'search', 'enabled' => false, 'config' => ['compact' => false, 'search_style' => 'box', 'enable_live_search' => true, 'show_suggestions' => true, 'icon_class' => 'ti ti-search', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                    ['id' => 'lang', 'type' => 'language_switcher', 'enabled' => false, 'config' => []],
                    ['id' => 'dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => []],
                    ['id' => 'cta', 'type' => 'cta_button', 'enabled' => true, 'config' => [
                        'text' => translate('Get Started'),
                        'link' => '/register',
                        'style' => 'filled',
                        'color' => 'primary',
                        'icon_class' => '',
                        'icon_only' => false,
                        'icon_color' => '',
                        'bg_style' => 'filled',
                        'bg_color' => '',
                        'text_color' => '',
                    ]],
                    ['id' => 'user', 'type' => 'user_menu', 'enabled' => true, 'config' => [
                        'show_credits' => true,
                        'show_avatar' => true,
                    ]],
                    ['id' => 'credits', 'type' => 'credit_balance', 'enabled' => false, 'config' => ['label' => translate('Credits'), 'icon_class' => 'ti ti-bolt', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                    ['id' => 'notify', 'type' => 'notification_bell', 'enabled' => true, 'config' => []],
                    ['id' => 'social', 'type' => 'social_icons', 'enabled' => false, 'config' => ['icons' => []]],
                    ['id' => 'html', 'type' => 'custom_html', 'enabled' => false, 'config' => ['content' => '']],
                ],
            ],
            'mobile' => [
                'enabled' => true,
                'sticky' => true,
                'transparent_homepage' => false,
                'height' => 64,
                'hide_on_scroll' => false,
                'container_width' => 'default',
                'sticky_behavior' => 'always',
                'upscroll_offset' => 80,
                'downscroll_offset' => 80,
                'transition_enabled' => true,
                'shadow' => false,
                'progressbar' => false,
                'blocks' => [
                    ['id' => 'mobile_hamburger', 'type' => 'hamburger', 'enabled' => true, 'config' => [
                        'menu_slug' => 'mobile',
                        'label' => translate('Menu'),
                        'icon_class' => 'ti ti-menu-2',
                        'show_label' => true,
                        'drawer_title' => '',
                        'icon_color' => '',
                        'bg_style' => 'light',
                        'bg_color' => '',
                    ]],
                    ['id' => 'mobile_logo', 'type' => 'logo', 'enabled' => true, 'config' => [
                        'image' => null,
                        'alt' => settings('app_name', translate('Application')),
                        'link' => '/',
                        'show_text' => true,
                        'text' => settings('app_name', translate('Application')),
                    ]],
                    ['id' => 'mobile_notify', 'type' => 'notification_bell', 'enabled' => true, 'config' => []],
                    ['id' => 'mobile_dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => ['label' => translate('Theme'), 'icon_class' => '', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                ],
            ],
            'mobile_bottom' => [
                'enabled' => false,
                'sticky' => true,
                'transparent_homepage' => false,
                'height' => 64,
                'hide_on_scroll' => false,
                'container_width' => 'default',
                'sticky_behavior' => 'always',
                'upscroll_offset' => 80,
                'downscroll_offset' => 80,
                'transition_enabled' => true,
                'shadow' => true,
                'progressbar' => false,
                'blocks' => [
                    ['id' => 'mobile_bottom_home', 'type' => 'home_link', 'enabled' => true, 'config' => ['link' => '/', 'label' => translate('Home'), 'icon_class' => 'ti ti-home', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                    ['id' => 'mobile_bottom_search', 'type' => 'search_icon', 'enabled' => true, 'config' => ['label' => translate('Search'), 'icon_class' => 'ti ti-search', 'show_label' => true, 'enable_live_search' => true, 'show_suggestions' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                    ['id' => 'mobile_bottom_user', 'type' => 'user_menu_icon', 'enabled' => true, 'config' => ['label' => translate('Account'), 'guest_label' => translate('Sign In'), 'icon_class' => 'ti ti-user', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                ],
            ],
        ];
    }

    /**
     * Ensure every block has a stable unique key for Vue rendering and drag/drop.
     */
    private function normalizeBlockIds(array $config): array
    {
        $usedIds = [];

        foreach (['top', 'main', 'mobile', 'mobile_bottom'] as $section) {
            $config[$section]['container_width'] ??= 'default';
            $config[$section]['sticky_behavior'] ??= ($config[$section]['sticky'] ?? false)
                ? (($config[$section]['hide_on_scroll'] ?? false) ? 'upscroll' : 'always')
                : 'none';
            $config[$section]['upscroll_offset'] = (int) ($config[$section]['upscroll_offset'] ?? 80);
            $config[$section]['downscroll_offset'] = (int) ($config[$section]['downscroll_offset'] ?? 80);
            $config[$section]['transition_enabled'] ??= true;
            $config[$section]['shadow'] ??= false;
            $config[$section]['progressbar'] ??= false;
            $config[$section]['sticky'] = $config[$section]['sticky_behavior'] !== 'none';
            $config[$section]['hide_on_scroll'] = $config[$section]['sticky_behavior'] === 'upscroll';

            if (! isset($config[$section]['blocks']) || ! is_array($config[$section]['blocks'])) {
                continue;
            }

            foreach ($config[$section]['blocks'] as $index => $block) {
                $id = trim((string) ($block['id'] ?? ''));

                if ($id === '' || isset($usedIds[$id])) {
                    $type = (string) Str::of((string) ($block['type'] ?? 'block'))
                        ->lower()
                        ->replaceMatches('/[^a-z0-9_]+/', '_')
                        ->trim('_');

                    $type = $type ?: 'block';

                    do {
                        $id = $type.'_'.Str::ulid();
                    } while (isset($usedIds[$id]));

                    $config[$section]['blocks'][$index]['id'] = $id;
                }

                $usedIds[$id] = true;
            }
        }

        return $config;
    }

    /**
     * Preserve old sticky/hide toggles when saved config has not been upgraded yet.
     */
    private function migrateLegacyStickyConfig(array $config): array
    {
        foreach (['top', 'main', 'mobile', 'mobile_bottom'] as $section) {
            if (! isset($config[$section]) || isset($config[$section]['sticky_behavior'])) {
                continue;
            }

            $config[$section]['sticky_behavior'] = ($config[$section]['sticky'] ?? false)
                ? (($config[$section]['hide_on_scroll'] ?? false) ? 'upscroll' : 'always')
                : 'none';
        }

        return $config;
    }

    /**
     * Show the header builder page.
     */
    public function index()
    {
        $config = Setting::getValue('header_config');

        $defaults = $this->getDefaults();

        $savedConfig = $config ? (is_array($config) ? $config : json_decode($config, true) ?? []) : [];
        $savedConfig = $this->migrateLegacyStickyConfig($savedConfig);
        $config = $this->normalizeBlockIds($savedConfig ? array_replace_recursive($defaults, $savedConfig) : $defaults);

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
            'top' => ['required', 'array'],
            'top.enabled' => ['required', 'boolean'],
            'top.sticky' => ['required', 'boolean'],
            'top.transparent_homepage' => ['required', 'boolean'],
            'top.height' => ['required', 'integer', 'min:32', 'max:80'],
            'top.hide_on_scroll' => ['required', 'boolean'],
            'top.container_width' => ['required', 'in:default,full,boxed'],
            'top.sticky_behavior' => ['required', 'in:none,always,upscroll,downscroll'],
            'top.upscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'top.downscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'top.transition_enabled' => ['required', 'boolean'],
            'top.shadow' => ['required', 'boolean'],
            'top.progressbar' => ['required', 'boolean'],
            'top.blocks' => ['required', 'array'],
            'main' => ['required', 'array'],
            'main.enabled' => ['required', 'boolean'],
            'main.sticky' => ['required', 'boolean'],
            'main.transparent_homepage' => ['required', 'boolean'],
            'main.height' => ['required', 'integer', 'min:48', 'max:120'],
            'main.hide_on_scroll' => ['required', 'boolean'],
            'main.container_width' => ['required', 'in:default,full,boxed'],
            'main.sticky_behavior' => ['required', 'in:none,always,upscroll,downscroll'],
            'main.upscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'main.downscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'main.transition_enabled' => ['required', 'boolean'],
            'main.shadow' => ['required', 'boolean'],
            'main.progressbar' => ['required', 'boolean'],
            'main.blocks' => ['required', 'array'],
            'mobile' => ['required', 'array'],
            'mobile.enabled' => ['required', 'boolean'],
            'mobile.sticky' => ['required', 'boolean'],
            'mobile.transparent_homepage' => ['required', 'boolean'],
            'mobile.height' => ['required', 'integer', 'min:48', 'max:96'],
            'mobile.hide_on_scroll' => ['required', 'boolean'],
            'mobile.container_width' => ['required', 'in:default,full,boxed'],
            'mobile.sticky_behavior' => ['required', 'in:none,always,upscroll,downscroll'],
            'mobile.upscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'mobile.downscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'mobile.transition_enabled' => ['required', 'boolean'],
            'mobile.shadow' => ['required', 'boolean'],
            'mobile.progressbar' => ['required', 'boolean'],
            'mobile.blocks' => ['required', 'array'],
            'mobile_bottom' => ['required', 'array'],
            'mobile_bottom.enabled' => ['required', 'boolean'],
            'mobile_bottom.sticky' => ['required', 'boolean'],
            'mobile_bottom.transparent_homepage' => ['required', 'boolean'],
            'mobile_bottom.height' => ['required', 'integer', 'min:48', 'max:96'],
            'mobile_bottom.hide_on_scroll' => ['required', 'boolean'],
            'mobile_bottom.container_width' => ['required', 'in:default,full,boxed'],
            'mobile_bottom.sticky_behavior' => ['required', 'in:none,always,upscroll,downscroll'],
            'mobile_bottom.upscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'mobile_bottom.downscroll_offset' => ['required', 'integer', 'min:0', 'max:800'],
            'mobile_bottom.transition_enabled' => ['required', 'boolean'],
            'mobile_bottom.shadow' => ['required', 'boolean'],
            'mobile_bottom.progressbar' => ['required', 'boolean'],
            'mobile_bottom.blocks' => ['required', 'array'],
        ]);

        Setting::setValue('header_config', $this->normalizeBlockIds($request->only(['top', 'main', 'mobile', 'mobile_bottom'])), 'json', 'appearance');

        return back()->with('success', translate('Header configuration saved successfully.'));
    }
}
