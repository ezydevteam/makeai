<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class FooterBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'layout' => ['required', 'integer', 'min:1', 'max:4'],
            'background' => ['nullable', 'array'],
            'background.color' => ['nullable', 'string', 'max:50'],
            'background.image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'background.overlay_opacity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'custom_css' => ['nullable', 'string', 'max:2000'],
            'container_width' => ['nullable', 'string', 'in:default,full,boxed'],
            'column_flex' => ['nullable', 'string', 'in:default,column-1,column-2,column-3,column-4'],
            'text_color' => ['nullable', 'string', 'max:50'],
            'heading_style' => ['nullable', 'string', 'in:default,accent,minimal'],
            'heading_color' => ['nullable', 'string', 'max:50'],
            'heading_font_weight' => ['nullable', 'string', 'max:10'],
            'heading_text_transform' => ['nullable', 'string', 'max:20'],
            'heading_font_size' => ['nullable', 'string', 'max:20'],
            'columns' => ['required', 'array', 'min:1', 'max:4'],
            'columns.*.id' => ['required', 'string', 'max:80'],
            'columns.*.title' => ['nullable', 'string', 'max:120'],
            'columns.*.subtitle' => ['nullable', 'string', 'max:180'],
            'columns.*.blocks' => ['present', 'array'],
            'columns.*.blocks.*.id' => ['required', 'string', 'max:120'],
            'columns.*.blocks.*.type' => ['required', 'string', 'in:about_text,menu_list,contact_info,social_icons,newsletter,custom_html,recent_blog_posts,ai_tool_categories,legal_links,language_switcher,dark_mode,trust_badges,store_badges,divider,copyright_text,payment_icons,back_to_top'],
            'columns.*.blocks.*.enabled' => ['required', 'boolean'],
            'columns.*.blocks.*.config' => ['required', 'array'],
            'bottom_blocks' => ['present', 'array'],
            'bottom_blocks.*.id' => ['required', 'string', 'max:120'],
            'bottom_blocks.*.type' => ['required', 'string', 'in:about_text,menu_list,contact_info,social_icons,newsletter,custom_html,recent_blog_posts,ai_tool_categories,legal_links,language_switcher,dark_mode,trust_badges,store_badges,divider,copyright_text,payment_icons,back_to_top'],
            'bottom_blocks.*.enabled' => ['required', 'boolean'],
            'bottom_blocks.*.config' => ['required', 'array'],
            'bottom_columns' => ['required', 'array', 'size:2'],
            'bottom_columns.*.id' => ['required', 'string', 'in:left,right'],
            'bottom_columns.*.title' => ['required', 'string', 'max:80'],
            'bottom_columns.*.blocks' => ['present', 'array'],
            'bottom_columns.*.blocks.*.id' => ['required', 'string', 'max:120'],
            'bottom_columns.*.blocks.*.type' => ['required', 'string', 'in:about_text,menu_list,contact_info,social_icons,newsletter,custom_html,recent_blog_posts,ai_tool_categories,legal_links,language_switcher,dark_mode,trust_badges,store_badges,divider,copyright_text,payment_icons,back_to_top'],
            'bottom_columns.*.blocks.*.enabled' => ['required', 'boolean'],
            'bottom_columns.*.blocks.*.config' => ['required', 'array'],
            'bottom_bar' => ['required', 'array'],
            'bottom_bar.copyright_text' => ['nullable', 'string', 'max:255'],
            'bottom_bar.menu_slug' => ['nullable', 'string', 'max:120'],
            'bottom_bar.show_payment_icons' => ['required', 'boolean'],
            'bottom_bar.payment_icons' => ['present', 'array'],
            'bottom_bar.payment_icons.*' => ['string', 'in:visa,mastercard,paypal,stripe,amex,discover,apple_pay,google_pay'],
            'bottom_bar.show_back_to_top' => ['required', 'boolean'],
            'bottom_bar.border_top' => ['required', 'boolean'],
            'bottom_bar.padding' => ['required', 'integer', 'min:8', 'max:80'],
            'bottom_bar.bg_color' => ['nullable', 'string', 'max:50'],
            'bottom_bar.text_color' => ['nullable', 'string', 'max:50'],
            'bottom_bar.column_flex' => ['nullable', 'string', 'in:default,left,right'],
        ];
    }

    public function footerConfig(): array
    {
        $data = $this->validated();

        $bottomOnlyTypes = ['copyright_text', 'payment_icons', 'back_to_top', 'social_icons', 'legal_links', 'custom_html', 'divider'];

        $data['columns'] = collect($data['columns'])
            ->take((int) $data['layout'])
            ->map(fn (array $column): array => [
                'id' => $this->cleanId($column['id'], 'column'),
                'title' => (string) ($column['title'] ?? ''),
                'subtitle' => (string) ($column['subtitle'] ?? ''),
                'blocks' => $this->sanitizeBlocks($column['blocks'] ?? []),
            ])
            ->values()
            ->all();

        $data['bottom_columns'] = collect($data['bottom_columns'])
            ->values()
            ->map(fn (array $column, int $index): array => [
                'id' => $index === 0 ? 'left' : 'right',
                'title' => $index === 0 ? translate('Left Column') : translate('Right Column'),
                'blocks' => collect($this->sanitizeBlocks($column['blocks'] ?? []))
                    ->filter(fn (array $block) => in_array($block['type'], $bottomOnlyTypes, true))
                    ->values()
                    ->all(),
            ])
            ->all();
        $data['bottom_blocks'] = array_merge($data['bottom_columns'][0]['blocks'], $data['bottom_columns'][1]['blocks']);

        $data['background'] = $data['background'] ?? ['color' => '', 'image_url' => '', 'overlay_opacity' => 0];
        $data['custom_css'] = $data['custom_css'] ?? '';
        $data['container_width'] = $data['container_width'] ?? 'default';
        $data['column_flex'] = $data['column_flex'] ?? 'default';
        $data['text_color'] = $data['text_color'] ?? '';
        $data['heading_style'] = $data['heading_style'] ?? 'default';
        $data['heading_color'] = $data['heading_color'] ?? '';
        $data['heading_font_weight'] = $data['heading_font_weight'] ?? '700';
        $data['heading_text_transform'] = $data['heading_text_transform'] ?? 'uppercase';
        $data['heading_font_size'] = $data['heading_font_size'] ?? '0.75rem';

        return $data;
    }

    private function sanitizeBlocks(array $blocks): array
    {
        return collect($blocks)
            ->map(function (array $block): array {
                $config = $block['config'] ?? [];

                if (($block['type'] ?? '') === 'custom_html') {
                    $config['content'] = $this->sanitizeHtml((string) ($config['content'] ?? ''));
                }

                return [
                    'id' => $this->cleanId($block['id'] ?? '', 'block'),
                    'type' => (string) $block['type'],
                    'enabled' => (bool) $block['enabled'],
                    'config' => $config,
                ];
            })
            ->values()
            ->all();
    }

    private function cleanId(mixed $value, string $prefix): string
    {
        $id = Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '_')
            ->trim('_')
            ->toString();

        return $id !== '' ? $id : $prefix.'_'.Str::ulid()->lower();
    }

    private function sanitizeHtml(string $html): string
    {
        return \App\Services\TiptapHtmlSanitizer::sanitize($html, \App\Services\TiptapHtmlSanitizer::BASIC_TAGS);
    }
}
