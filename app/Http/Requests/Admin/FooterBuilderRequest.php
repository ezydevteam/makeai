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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'layout' => ['required', 'integer', 'min:1', 'max:4'],
            'columns' => ['required', 'array', 'min:1', 'max:4'],
            'columns.*.id' => ['required', 'string', 'max:80'],
            'columns.*.width' => ['required', 'integer', 'in:25,33,50,66,75,100'],
            'columns.*.title' => ['nullable', 'string', 'max:120'],
            'columns.*.subtitle' => ['nullable', 'string', 'max:180'],
            'columns.*.heading_style' => ['required', 'string', 'in:default,accent,minimal'],
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
            'bottom_bar.layout_desktop' => ['required', 'integer', 'in:2'],
            'bottom_bar.layout_tablet' => ['required', 'integer', 'in:2'],
            'bottom_bar.layout_mobile' => ['required', 'integer', 'in:2'],
            'bottom_bar.alignment_desktop' => ['required', 'string', 'in:left,center,right,between'],
            'bottom_bar.alignment_tablet' => ['required', 'string', 'in:left,center,right,between'],
            'bottom_bar.alignment_mobile' => ['required', 'string', 'in:left,center,right,between'],
            'bottom_bar.padding_desktop' => ['required', 'integer', 'min:8', 'max:80'],
            'bottom_bar.padding_tablet' => ['required', 'integer', 'min:8', 'max:80'],
            'bottom_bar.padding_mobile' => ['required', 'integer', 'min:8', 'max:80'],
            'bottom_bar.border_top' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function footerConfig(): array
    {
        $data = $this->validated();

        $data['columns'] = collect($data['columns'])
            ->take((int) $data['layout'])
            ->map(fn (array $column): array => [
                'id' => $this->cleanId($column['id'], 'column'),
                'width' => (int) $column['width'],
                'title' => (string) ($column['title'] ?? ''),
                'subtitle' => (string) ($column['subtitle'] ?? ''),
                'heading_style' => (string) $column['heading_style'],
                'blocks' => $this->sanitizeBlocks($column['blocks'] ?? []),
            ])
            ->values()
            ->all();

        $data['bottom_columns'] = collect($data['bottom_columns'])
            ->values()
            ->map(fn (array $column, int $index): array => [
                'id' => $index === 0 ? 'left' : 'right',
                'title' => $index === 0 ? translate('Left Column') : translate('Right Column'),
                'blocks' => $this->sanitizeBlocks($column['blocks'] ?? []),
            ])
            ->all();
        $data['bottom_blocks'] = array_merge($data['bottom_columns'][0]['blocks'], $data['bottom_columns'][1]['blocks']);

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
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
        $html = preg_replace('/<\s*(script|style|iframe|object|embed)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? '';

        return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><span><div><small><code>');
    }
}
