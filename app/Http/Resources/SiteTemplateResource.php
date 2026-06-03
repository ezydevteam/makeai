<?php

namespace App\Http\Resources;

use App\Models\SiteTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin SiteTemplate */
class SiteTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'layout_component' => $this->layout_component,

            'color_primary' => $this->color_primary,
            'color_secondary' => $this->color_secondary,
            'color_bg' => $this->color_bg,
            'color_surface' => $this->color_surface,
            'color_text' => $this->color_text,
            'font_heading' => $this->font_heading,
            'font_body' => $this->font_body,

            'hero_headline' => $this->hero_headline ?? settings('app_name', config('app.name')),
            'hero_subheadline' => $this->hero_subheadline ?? settings('site_tagline', ''),
            'hero_cta_text' => $this->hero_cta_text ?? __('Get Started'),
            'hero_cta_url' => $this->hero_cta_url ?? route('register'),
            'hero_bg_image' => $this->hero_bg_image ? Storage::url($this->hero_bg_image) : null,

            'custom_css' => $this->custom_css,
            'custom_html_head' => $this->custom_html_head,
            'custom_html_body' => $this->custom_html_body,

            'meta_title' => $this->meta_title ?? ($this->name.' — '.settings('app_name', config('app.name'))),
            'meta_description' => $this->meta_description ?? $this->tagline,
            'og_image' => $this->og_image ? Storage::url($this->og_image) : settings('app_og_image'),
        ];
    }
}
