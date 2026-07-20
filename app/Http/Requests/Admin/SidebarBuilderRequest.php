<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SidebarBuilderRequest extends FormRequest
{
    /**
     * The widget areas the sidebar builder manages. Each area targets a
     * different page context on the public site:
     *   - main: AI tool related pages
     *   - blog: blog listing & posts
     *   - page: general / custom CMS pages
     */
    public const AREAS = ['main', 'blog', 'page'];

    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'areas' => ['required', 'array'],
            'areas.main' => ['present', 'array'],
            'areas.blog' => ['present', 'array'],
            'areas.page' => ['present', 'array'],
            'areas.*.*.id' => ['required', 'string', 'max:120'],
            'areas.*.*.type' => ['required', 'string', 'in:search_box,categories_list,blog_categories,recent_posts,popular_tools,recently_added,tag_cloud,newsletter,ad_zone,social_follow,custom_html'],
            'areas.*.*.config' => ['required', 'array'],
            'areas.*.*.config.title' => ['nullable', 'string', 'max:200'],
            'areas.*.*.config.placeholder' => ['nullable', 'string', 'max:200'],
            'areas.*.*.config.show_count' => ['nullable', 'boolean'],
            'areas.*.*.config.count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'areas.*.*.config.description' => ['nullable', 'string', 'max:300'],
            'areas.*.*.config.zone_id' => ['nullable', 'string', 'max:120'],
            'areas.*.*.config.content' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function sanitizedConfig(): array
    {
        $data = $this->validated();

        $areas = [];
        foreach (self::AREAS as $area) {
            $areas[$area] = collect($data['areas'][$area] ?? [])
                ->map(fn (array $block): array => [
                    'id' => (string) ($block['id'] ?? ''),
                    'type' => (string) $block['type'],
                    'config' => $this->sanitizeBlockConfig($block['type'], $block['config'] ?? []),
                ])
                ->values()
                ->all();
        }

        return ['areas' => $areas];
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
