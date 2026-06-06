<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\FooterBuilderRequest;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Setting;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FooterBuilderController extends Controller
{
    /**
     * Default footer configuration.
     */
    private function getDefaults(): array
    {
        $appName = settings('app_name', translate('Application'));

        return [
            'layout' => 4, // 1, 2, 3, or 4 columns
            'columns' => [
                [
                    'id' => 'footer_column_1',
                    'width' => 25,
                    'title' => '',
                    'subtitle' => '',
                    'heading_style' => 'default',
                    'blocks' => [
                        ['id' => 'default_about', 'type' => 'about_text', 'enabled' => true, 'config' => ['logo' => null, 'alt' => $appName, 'description' => translate('The ultimate AI platform for creators, developers, and businesses. Generate anything you can imagine.')]],
                    ],
                ],
                [
                    'id' => 'footer_column_2',
                    'width' => 25,
                    'title' => '',
                    'subtitle' => '',
                    'heading_style' => 'default',
                    'blocks' => [
                        ['id' => 'default_menu_1', 'type' => 'menu_list', 'enabled' => true, 'config' => ['title' => translate('Platform'), 'menu_slug' => 'footer-1']],
                    ],
                ],
                [
                    'id' => 'footer_column_3',
                    'width' => 25,
                    'title' => '',
                    'subtitle' => '',
                    'heading_style' => 'default',
                    'blocks' => [
                        ['id' => 'default_menu_2', 'type' => 'menu_list', 'enabled' => true, 'config' => ['title' => translate('Support'), 'menu_slug' => 'footer-2']],
                    ],
                ],
                [
                    'id' => 'footer_column_4',
                    'width' => 25,
                    'title' => '',
                    'subtitle' => '',
                    'heading_style' => 'default',
                    'blocks' => [
                        ['id' => 'default_contact', 'type' => 'contact_info', 'enabled' => true, 'config' => ['title' => translate('Contact Us'), 'address' => '', 'phone' => '', 'email' => '']],
                    ],
                ],
            ],
            'bottom_blocks' => [
                ['id' => 'bottom_copyright', 'type' => 'copyright_text', 'enabled' => true, 'config' => ['text' => translate('© {year} :app. All rights reserved.', ['app' => $appName])]],
                ['id' => 'bottom_payment_icons', 'type' => 'payment_icons', 'enabled' => true, 'config' => ['icons' => ['visa', 'mastercard', 'paypal', 'stripe']]],
                ['id' => 'bottom_back_to_top', 'type' => 'back_to_top', 'enabled' => true, 'config' => ['label' => translate('Back to top')]],
            ],
            'bottom_columns' => [
                [
                    'id' => 'left',
                    'title' => translate('Left Column'),
                    'blocks' => [
                        ['id' => 'bottom_copyright', 'type' => 'copyright_text', 'enabled' => true, 'config' => ['text' => translate('© {year} :app. All rights reserved.', ['app' => $appName])]],
                    ],
                ],
                [
                    'id' => 'right',
                    'title' => translate('Right Column'),
                    'blocks' => [
                        ['id' => 'bottom_payment_icons', 'type' => 'payment_icons', 'enabled' => true, 'config' => ['icons' => ['visa', 'mastercard', 'paypal', 'stripe']]],
                        ['id' => 'bottom_back_to_top', 'type' => 'back_to_top', 'enabled' => true, 'config' => ['label' => translate('Back to top')]],
                    ],
                ],
            ],
            'bottom_bar' => [
                'copyright_text' => translate('© {year} :app. All rights reserved.', ['app' => $appName]),
                'menu_slug' => null,
                'show_payment_icons' => true,
                'payment_icons' => ['visa', 'mastercard', 'paypal', 'stripe'],
                'show_back_to_top' => true,
                'layout_desktop' => 2,
                'layout_tablet' => 2,
                'layout_mobile' => 2,
                'alignment_desktop' => 'between',
                'alignment_tablet' => 'center',
                'alignment_mobile' => 'center',
                'padding_desktop' => 32,
                'padding_tablet' => 24,
                'padding_mobile' => 20,
                'border_top' => true,
            ],
        ];
    }

    /**
     * Normalize legacy array-only columns into structured drag/drop columns.
     */
    private function normalizeConfig(array $config): array
    {
        $defaults = $this->getDefaults();
        $hasSavedBottomColumns = isset($config['bottom_columns']) && is_array($config['bottom_columns']) && count($config['bottom_columns']) === 2;
        $config = array_replace_recursive($defaults, $config);
        $config['layout'] = max(1, min(4, (int) ($config['layout'] ?? 4)));
        $config['columns'] = array_values(array_slice($config['columns'] ?? [], 0, $config['layout']));

        foreach ($config['columns'] as $index => $column) {
            $legacyBlocks = array_is_list($column) ? $column : ($column['blocks'] ?? []);

            $config['columns'][$index] = [
                'id' => $this->stableId($column['id'] ?? 'footer_column_'.($index + 1), 'footer_column'),
                'width' => (int) ($column['width'] ?? $this->defaultColumnWidth($config['layout'])),
                'title' => (string) ($column['title'] ?? ''),
                'subtitle' => (string) ($column['subtitle'] ?? ''),
                'heading_style' => in_array(($column['heading_style'] ?? 'default'), ['default', 'accent', 'minimal'], true)
                    ? $column['heading_style']
                    : 'default',
                'blocks' => $this->normalizeBlocks(is_array($legacyBlocks) ? $legacyBlocks : []),
            ];
        }

        while (count($config['columns']) < $config['layout']) {
            $config['columns'][] = [
                'id' => 'footer_column_'.(count($config['columns']) + 1),
                'width' => $this->defaultColumnWidth($config['layout']),
                'title' => '',
                'subtitle' => '',
                'heading_style' => 'default',
                'blocks' => [],
            ];
        }

        $config['bottom_bar']['layout_desktop'] = 2;
        $config['bottom_bar']['layout_tablet'] = 2;
        $config['bottom_bar']['layout_mobile'] = 2;
        $config['bottom_bar']['alignment_desktop'] ??= 'between';
        $config['bottom_bar']['alignment_tablet'] ??= 'center';
        $config['bottom_bar']['alignment_mobile'] ??= 'center';
        $config['bottom_bar']['padding_desktop'] ??= 32;
        $config['bottom_bar']['padding_tablet'] ??= 24;
        $config['bottom_bar']['padding_mobile'] ??= 20;
        $config['bottom_bar']['border_top'] ??= true;
        $config['bottom_bar']['payment_icons'] ??= [];
        $bottomBlocks = $config['bottom_blocks'] ?? [];

        if ($bottomBlocks === []) {
            $bottomBlocks = [
                ['id' => 'bottom_copyright', 'type' => 'copyright_text', 'enabled' => true, 'config' => ['text' => $config['bottom_bar']['copyright_text'] ?? '']],
                ['id' => 'bottom_payment_icons', 'type' => 'payment_icons', 'enabled' => (bool) ($config['bottom_bar']['show_payment_icons'] ?? true), 'config' => ['icons' => $config['bottom_bar']['payment_icons'] ?? []]],
                ['id' => 'bottom_back_to_top', 'type' => 'back_to_top', 'enabled' => (bool) ($config['bottom_bar']['show_back_to_top'] ?? true), 'config' => ['label' => translate('Back to top')]],
            ];
        }

        $bottomColumns = $hasSavedBottomColumns ? ($config['bottom_columns'] ?? []) : [];
        if (! is_array($bottomColumns) || count($bottomColumns) !== 2) {
            $splitBottomBlocks = $this->splitBottomBlocks($bottomBlocks);
            $bottomColumns = [
                ['id' => 'left', 'title' => translate('Left Column'), 'blocks' => $splitBottomBlocks['left']],
                ['id' => 'right', 'title' => translate('Right Column'), 'blocks' => $splitBottomBlocks['right']],
            ];
        }

        $config['bottom_columns'] = [
            [
                'id' => 'left',
                'title' => translate('Left Column'),
                'blocks' => $this->normalizeBlocks(is_array($bottomColumns[0]['blocks'] ?? null) ? $bottomColumns[0]['blocks'] : []),
            ],
            [
                'id' => 'right',
                'title' => translate('Right Column'),
                'blocks' => $this->normalizeBlocks(is_array($bottomColumns[1]['blocks'] ?? null) ? $bottomColumns[1]['blocks'] : []),
            ],
        ];
        $config['bottom_blocks'] = array_merge($config['bottom_columns'][0]['blocks'], $config['bottom_columns'][1]['blocks']);

        return $config;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{left: array<int, array<string, mixed>>, right: array<int, array<string, mixed>>}
     */
    private function splitBottomBlocks(array $blocks): array
    {
        $left = [];
        $right = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'copyright_text') {
                $left[] = $block;
            } else {
                $right[] = $block;
            }
        }

        return ['left' => $left, 'right' => $right];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBlocks(array $blocks): array
    {
        $usedIds = [];

        return collect($blocks)
            ->map(function (array $block) use (&$usedIds): array {
                $type = (string) ($block['type'] ?? 'custom_html');
                $id = $this->stableId($block['id'] ?? '', $type);

                while (isset($usedIds[$id])) {
                    $id = $this->stableId('', $type);
                }

                $usedIds[$id] = true;

                return [
                    'id' => $id,
                    'type' => $type,
                    'enabled' => (bool) ($block['enabled'] ?? true),
                    'config' => is_array($block['config'] ?? null) ? $block['config'] : [],
                ];
            })
            ->values()
            ->all();
    }

    private function stableId(mixed $value, string $prefix): string
    {
        $id = trim((string) $value);

        if ($id !== '') {
            return $id;
        }

        return Str::of($prefix)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_').'_'.Str::ulid();
    }

    private function defaultColumnWidth(int $layout): int
    {
        return match ($layout) {
            1 => 100,
            2 => 50,
            3 => 33,
            default => 25,
        };
    }

    /**
     * Show the footer builder page.
     */
    public function index()
    {
        $config = Setting::getValue('footer_config');

        if ($config) {
            $config = is_array($config) ? $config : json_decode($config, true) ?? [];
            $config = $this->normalizeConfig($config);
        } else {
            $config = $this->normalizeConfig($this->getDefaults());
        }

        $menus = Menu::orderBy('name')->get(['id', 'name', 'slug']);
        $aiCategories = Category::active()->aiTools()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'tools_count']);

        return Inertia::render('Admin/Appearance/FooterBuilder', [
            'config' => $config,
            'menus' => $menus,
            'aiCategories' => $aiCategories,
        ]);
    }

    /**
     * Update the footer configuration.
     */
    public function update(FooterBuilderRequest $request)
    {
        Setting::setValue('footer_config', $this->normalizeConfig($request->footerConfig()), 'json', 'appearance');

        return back()->with('success', translate('Footer configuration updated successfully.'));
    }
}
