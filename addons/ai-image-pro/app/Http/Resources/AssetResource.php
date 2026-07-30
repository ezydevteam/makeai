<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Resources;

use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Services\ModelCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The one and only shape an asset is ever serialised in — Studio, Library,
 * job polling, upload and the synchronous tool endpoint all return this.
 *
 * `parent_ulid` is only resolved when the `parent` relation was eager-loaded;
 * every controller in this addon loads it (`with('parent:id,ulid')` /
 * `loadMissing('parent:id,ulid')`) so the gallery never triggers an N+1.
 *
 * @mixin AipAsset
 */
class AssetResource extends JsonResource
{
    /**
     * No `data` envelope. The frontend contract is a bare asset object everywhere —
     * an array of them in the Studio, the `data` of a plain paginator in the Library.
     * Left at the default, JsonResource wraps each asset in {"data": {...}}, which
     * broke iteration in the Studio and nested every row of the Library paginator.
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'url' => $this->url,
            'thumb_url' => $this->thumb_url,
            'width' => $this->width,
            'height' => $this->height,
            'mime' => $this->mime,
            'bytes' => $this->bytes,
            'prompt' => $this->prompt,
            'negative_prompt' => $this->negative_prompt,
            // The row stores the real slug; the public alias is what ships. Null once the
            // model is gone from the catalogue entirely — the UI shows the provider alone
            // rather than fall back to a slug, which is the thing we are not publishing.
            'model' => app(ModelCatalog::class)->aliasFor($this->model),
            'provider' => $this->provider,
            'seed' => $this->seed,
            'operation' => $this->operation,
            'source' => $this->source,
            'is_favorite' => (bool) $this->is_favorite,
            'folder_id' => $this->folder_id,
            'parent_ulid' => $this->relationLoaded('parent') ? $this->parent?->ulid : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
