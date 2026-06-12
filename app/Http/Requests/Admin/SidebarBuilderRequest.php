<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SidebarBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'string', 'max:120'],
            'blocks.*.type' => ['required', 'string', 'in:search_box,categories_list,recent_posts,popular_tools,recently_added,tag_cloud,newsletter,ad_zone,social_follow,custom_html'],
            'blocks.*.config' => ['required', 'array'],
            'blocks.*.config.title' => ['nullable', 'string', 'max:200'],
            'blocks.*.config.placeholder' => ['nullable', 'string', 'max:200'],
            'blocks.*.config.show_count' => ['nullable', 'boolean'],
            'blocks.*.config.count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'blocks.*.config.description' => ['nullable', 'string', 'max:300'],
            'blocks.*.config.zone_id' => ['nullable', 'string', 'max:120'],
            'blocks.*.config.content' => ['nullable', 'string', 'max:10000'],
            'position' => ['required', 'string', 'in:left,right'],
            'sticky' => ['required', 'boolean'],
            'show_on_pages' => ['present', 'array'],
            'show_on_pages.*' => ['string', 'max:100'],
        ];
    }

    public function sanitizedConfig(): array
    {
        $data = $this->validated();

        $data['blocks'] = collect($data['blocks'])
            ->map(fn (array $block): array => [
                'id' => (string) ($block['id'] ?? ''),
                'type' => (string) $block['type'],
                'config' => $this->sanitizeBlockConfig($block['type'], $block['config'] ?? []),
            ])
            ->values()
            ->all();

        $data['show_on_pages'] = $data['show_on_pages'] ?? [];

        return $data;
    }

    private function sanitizeBlockConfig(string $type, array $config): array
    {
        $config['title'] = (string) ($config['title'] ?? '');

        if ($type === 'custom_html' && isset($config['content'])) {
            $config['content'] = \App\Services\TiptapHtmlSanitizer::sanitize(
                (string) $config['content'],
                \App\Services\TiptapHtmlSanitizer::BASIC_TAGS
            );
        }

        return $config;
    }
}
