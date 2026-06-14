<?php

namespace App\Http\Controllers\Admin;

use App\Models\Menu;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class HeaderBuilderController extends Controller
{
    private function getDefaults(): array
    {
        $defaults = [
            'layout' => 'classic',
            'top' => $this->defaultSection(40, false, [
                ['id' => 'top_nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'top', 'alignment' => 'center', 'text_color' => '', 'hover_color' => '', 'hover_style' => 'underline', 'submenu_bg_color' => '', 'submenu_text_color' => '', 'block_align' => 'left']],
                ['id' => 'top_lang', 'type' => 'language_switcher', 'enabled' => true, 'config' => ['block_align' => 'left']],
                ['id' => 'top_html', 'type' => 'custom_html', 'enabled' => false, 'config' => ['content' => '', 'block_align' => 'left']],
            ]),
            'main' => $this->defaultSection(72, true, [
                ['id' => 'logo', 'type' => 'logo', 'enabled' => true, 'config' => ['block_align' => 'left']],
                ['id' => 'nav', 'type' => 'navigation', 'enabled' => true, 'config' => ['menu_slug' => 'main', 'alignment' => 'center', 'text_color' => '', 'hover_color' => '', 'hover_style' => 'underline', 'submenu_bg_color' => '', 'submenu_text_color' => '', 'block_align' => 'center']],
                ['id' => 'search', 'type' => 'search', 'enabled' => false, 'config' => ['compact' => false, 'search_style' => 'box', 'enable_live_search' => true, 'show_suggestions' => true, 'icon_class' => 'ti ti-search', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '', 'block_align' => 'center']],
                ['id' => 'lang', 'type' => 'language_switcher', 'enabled' => false, 'config' => ['block_align' => 'right']],
                ['id' => 'dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => ['block_align' => 'right']],
                ['id' => 'cta', 'type' => 'cta_button', 'enabled' => true, 'config' => ['text' => translate('Get Started'), 'link' => '/register', 'style' => 'filled', 'color' => 'primary', 'icon_class' => '', 'icon_only' => false, 'icon_color' => '', 'bg_style' => 'filled', 'bg_color' => '', 'text_color' => '', 'block_align' => 'right']],
                ['id' => 'user', 'type' => 'user_menu', 'enabled' => true, 'config' => ['guest_mode' => 'login_only', 'guest_login_icon_class' => 'ti ti-login-2', 'guest_login_style' => 'primary', 'guest_register_icon_class' => 'ti ti-user-plus', 'guest_register_style' => 'gradient', 'auth_display' => 'avatar_name', 'show_arrow_icon' => true, 'show_credits' => true, 'block_align' => 'right']],
                ['id' => 'credits', 'type' => 'credit_balance', 'enabled' => false, 'config' => ['label' => translate('Credits'), 'icon_class' => 'ti ti-bolt', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '', 'block_align' => 'right']],
                ['id' => 'notify', 'type' => 'notification_bell', 'enabled' => true, 'config' => ['icon_class' => 'ti ti-bell', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '', 'block_align' => 'right']],
                ['id' => 'social', 'type' => 'social_icons', 'enabled' => false, 'config' => ['icons' => [], 'block_align' => 'right']],
                ['id' => 'html', 'type' => 'custom_html', 'enabled' => false, 'config' => ['content' => '', 'block_align' => 'right']],
            ]),
            'mobile' => $this->defaultSection(64, true, [
                ['id' => 'mobile_hamburger', 'type' => 'hamburger', 'enabled' => true, 'config' => ['menu_slug' => 'mobile', 'label' => translate('Menu'), 'icon_class' => 'ti ti-menu-2', 'show_label' => true, 'drawer_title' => '', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '', 'block_align' => 'left']],
                ['id' => 'mobile_logo', 'type' => 'logo', 'enabled' => true, 'config' => ['block_align' => 'left']],
                ['id' => 'mobile_notify', 'type' => 'notification_bell', 'enabled' => true, 'config' => ['icon_class' => 'ti ti-bell', 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '', 'block_align' => 'right']],
                ['id' => 'mobile_dark', 'type' => 'dark_mode', 'enabled' => true, 'config' => ['label' => translate('Theme'), 'icon_class' => '', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '', 'block_align' => 'right']],
            ]),
            'mobile_bottom' => $this->defaultSection(64, true, [
                ['id' => 'mobile_bottom_home', 'type' => 'home_link', 'enabled' => true, 'config' => ['link' => '/', 'label' => translate('Home'), 'icon_class' => 'ti ti-home', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                ['id' => 'mobile_bottom_search', 'type' => 'search_icon', 'enabled' => true, 'config' => ['label' => translate('Search'), 'icon_class' => 'ti ti-search', 'show_label' => true, 'enable_live_search' => true, 'show_suggestions' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
                ['id' => 'mobile_bottom_user', 'type' => 'user_menu_icon', 'enabled' => true, 'config' => ['label' => translate('Account'), 'guest_label' => translate('Sign In'), 'icon_class' => 'ti ti-user', 'show_label' => true, 'icon_color' => '', 'bg_style' => 'light', 'bg_color' => '']],
            ], shadow: true),
        ];

        $defaults['main']['center_alignment'] = 'center';

        return $defaults;
    }

    private function defaultSection(int $height, bool $enabled, array $blocks = [], bool $shadow = false): array
    {
        return [
            'enabled' => $enabled,
            'sticky' => $enabled,
            'transparent_homepage' => false,
            'height' => $height,
            'hide_on_scroll' => false,
            'container_width' => 'default',
            'sticky_behavior' => $enabled ? 'always' : 'none',
            'sticky_height' => $height,
            'upscroll_offset' => 80,
            'downscroll_offset' => 80,
            'transition_enabled' => true,
            'shadow' => $shadow,
            'progressbar' => false,
            'text_color' => '',
            'background' => ['color' => '', 'image_url' => '', 'overlay_opacity' => 0],
            'custom_css' => '',
            'blocks' => $blocks,
        ];
    }

    private function normalizeBlockIds(array $config): array
    {
        $usedIds = [];

        foreach (['top', 'main', 'mobile', 'mobile_bottom'] as $section) {
            if (!isset($config[$section])) continue;

            $config[$section]['container_width'] ??= 'default';
            $config[$section]['sticky_behavior'] ??= ($config[$section]['sticky'] ?? false) ? (($config[$section]['hide_on_scroll'] ?? false) ? 'upscroll' : 'always') : 'none';
            $config[$section]['upscroll_offset'] = (int) ($config[$section]['upscroll_offset'] ?? 80);
            $config[$section]['downscroll_offset'] = (int) ($config[$section]['downscroll_offset'] ?? 80);
            $config[$section]['transition_enabled'] ??= true;
            $config[$section]['shadow'] ??= false;
            $config[$section]['progressbar'] ??= false;
            $config[$section]['background'] ??= ['color' => '', 'image_url' => '', 'overlay_opacity' => 0];
            $config[$section]['custom_css'] ??= '';
            $config[$section]['column_flex'] = $config[$section]['column_flex'] ?? 'default';
            $config[$section]['sticky'] = $config[$section]['sticky_behavior'] !== 'none';
            $config[$section]['hide_on_scroll'] = $config[$section]['sticky_behavior'] === 'upscroll';

            if (!isset($config[$section]['blocks']) || !is_array($config[$section]['blocks'])) continue;

            foreach ($config[$section]['blocks'] as $index => $block) {
                $id = trim((string) ($block['id'] ?? ''));
                if ($id === '' || isset($usedIds[$id])) {
                    $type = (string) Str::of((string) ($block['type'] ?? 'block'))->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_') ?: 'block';
                    do { $id = $type . '_' . Str::ulid(); } while (isset($usedIds[$id]));
                    $config[$section]['blocks'][$index]['id'] = $id;
                }
                $usedIds[$id] = true;

                // Normalize block_align if missing
                if (!isset($config[$section]['blocks'][$index]['config']['block_align'])) {
                    $blockType = $block['type'] ?? '';
                    $defaultAlign = 'left';
                    if ($section === 'main') {
                        $defaultAlign = in_array($blockType, ['logo']) ? 'left' : (in_array($blockType, ['navigation', 'search']) ? 'center' : 'right');
                    } elseif ($section === 'top') {
                        $defaultAlign = in_array($blockType, ['cta_button', 'social_icons']) ? 'right' : 'left';
                    } elseif ($section === 'mobile') {
                        $defaultAlign = in_array($blockType, ['hamburger', 'logo']) ? 'left' : 'right';
                    }
                    $config[$section]['blocks'][$index]['config']['block_align'] = $defaultAlign;
                }
            }

            // Normalize center_alignment for main section
            if ($section === 'main') {
                $config['main']['center_alignment'] = $config['main']['center_alignment'] ?? 'center';
            }
        }

        $config['layout'] ??= 'classic';

        return $config;
    }

    private function migrateLegacyStickyConfig(array $config): array
    {
        foreach (['top', 'main', 'mobile', 'mobile_bottom'] as $section) {
            if (!isset($config[$section]) || isset($config[$section]['sticky_behavior'])) continue;
            $config[$section]['sticky_behavior'] = ($config[$section]['sticky'] ?? false) ? (($config[$section]['hide_on_scroll'] ?? false) ? 'upscroll' : 'always') : 'none';
        }
        return $config;
    }

    public function index()
    {
        $config = Setting::getValue('header_config');
        $defaults = $this->getDefaults();
        $savedConfig = $config ? (is_array($config) ? $config : json_decode($config, true) ?? []) : [];
        $savedConfig = $this->migrateLegacyStickyConfig($savedConfig);
        $config = $this->normalizeBlockIds($savedConfig ? array_replace_recursive($defaults, $savedConfig) : $defaults);

        return Inertia::render('Admin/Appearance/HeaderBuilder', [
            'config' => $config,
            'menus' => Menu::orderBy('name')->get(['id', 'name', 'slug']),
            'defaults' => $defaults,
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];

        foreach (['top', 'main', 'mobile', 'mobile_bottom'] as $section) {
            $rules[$section] = ['required', 'array'];
            $rules["{$section}.enabled"] = ['required', 'boolean'];
            $rules["{$section}.sticky"] = ['nullable', 'boolean'];
            $rules["{$section}.transparent_homepage"] = ['nullable', 'boolean'];
            $rules["{$section}.height"] = ['required', 'integer', 'min:32', 'max:120'];
            $rules["{$section}.hide_on_scroll"] = ['nullable', 'boolean'];
            $rules["{$section}.container_width"] = ['nullable', 'in:default,full,boxed'];
            $rules["{$section}.sticky_behavior"] = ['nullable', 'in:none,always,upscroll,downscroll'];
            $rules["{$section}.transition_enabled"] = ['nullable', 'boolean'];
            $rules["{$section}.shadow"] = ['nullable', 'boolean'];
            $rules["{$section}.progressbar"] = ['nullable', 'boolean'];
            $rules["{$section}.custom_css"] = ['nullable', 'string', 'max:2000'];
            $rules["{$section}.blocks"] = ['required', 'array'];
        }

        $request->validate($rules);

        $config = $request->only(['top', 'main', 'mobile', 'mobile_bottom', 'layout']);
        $config = $this->normalizeBlockIds($config);

        Setting::setValue('header_config', $config, 'json', 'appearance');

        return back()->with('success', translate('Header configuration saved successfully.'));
    }

    public function resetSection(string $section): JsonResponse
    {
        $allowed = ['top', 'main', 'mobile', 'mobile_bottom'];
        if (!in_array($section, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid section.'], 422);
        }

        $config = Setting::getValue('header_config');
        $savedConfig = $config ? (is_array($config) ? $config : json_decode($config, true) ?? []) : [];

        $defaults = $this->getDefaults();
        $defaultSection = $defaults[$section];
        $defaultSection['blocks'] = array_map(fn ($b) => array_merge($b, ['id' => $b['id'] . '_' . Str::ulid()]), $defaultSection['blocks']);

        $savedConfig[$section] = $defaultSection;
        Setting::setValue('header_config', $this->normalizeBlockIds($savedConfig), 'json', 'appearance');

        return response()->json(['success' => true, 'section' => $savedConfig[$section]]);
    }

    public function export(): JsonResponse
    {
        $config = Setting::getValue('header_config');
        $savedConfig = $config ? (is_array($config) ? $config : json_decode($config, true) ?? []) : [];
        return response()->json($savedConfig, 200, [], JSON_PRETTY_PRINT);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],
            'directory' => ['nullable', 'string', 'max:100'],
        ]);

        $dir = $request->input('directory', 'logos');
        $path = $request->file('file')->store($dir, 'public');
        $url = Storage::disk('public')->url($path);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = $request->getSchemeAndHttpHost().$url;
        } elseif ($request->isSecure()) {
            $url = preg_replace('/^http:\/\//i', 'https://', $url) ?? $url;
        }

        return response()->json(['url' => $url, 'path' => $path]);
    }
}
