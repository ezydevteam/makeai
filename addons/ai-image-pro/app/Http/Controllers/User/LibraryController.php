<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Http\Resources\AssetResource;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipFolder;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\ModelCatalog;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Library: a paginated, filterable gallery of everything the user has created
 * or uploaded, plus their folders and a live storage meter. The route requires a
 * real account, so everything scopes off `user_id`.
 */
class LibraryController extends Controller
{
    /**
     * Every value the addon ever writes to `aip_assets.source`. The Library's filter
     * offers exactly these, and anything else is ignored — see sourceFilter().
     *
     *   generated — a generation with no input image
     *   derived   — produced from an existing image by an operation
     *   uploaded  — the user's own file
     */
    private const SOURCES = ['generated', 'derived', 'uploaded'];

    public function __construct(
        private readonly ImageAccessService $access,
        private readonly ModelCatalog $catalog,
        private readonly OperationRegistry $registry,
    ) {
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->access->canAccessLibrary($user)) {
            abort(403, translate('You do not have access to the Library.'));
        }

        $filters = [
            'q' => $this->stringFilter($request, 'q'),
            'folder_id' => $request->filled('folder_id') ? (int) $request->input('folder_id') : null,
            'source' => $this->sourceFilter($request),
            'favorite' => $request->boolean('favorite') ? true : null,
            'model' => $this->stringFilter($request, 'model'),
        ];

        $assets = AipAsset::query()
            ->where('user_id', $user->id)
            ->with('parent:id,ulid')
            ->when($filters['q'], fn (Builder $q, string $term) => $q->where(function (Builder $inner) use ($term) {
                $inner->where('prompt', 'like', '%' . $term . '%')
                    ->orWhere('operation', 'like', '%' . $term . '%')
                    ->orWhere('model', 'like', '%' . $term . '%');
            }))
            ->when($filters['folder_id'], fn (Builder $q, int $id) => $q->where('folder_id', $id))
            ->when($filters['source'], fn (Builder $q, string $source) => $q->where('source', $source))
            ->when($filters['favorite'], fn (Builder $q) => $q->where('is_favorite', true))
            ->when($filters['model'], fn (Builder $q, string $model) => $q->where('model', $model))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $folders = AipFolder::where('user_id', $user->id)
            ->withCount('assets')
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $capMb = (int) addon_setting(OperationRegistry::SLUG, 'max_storage_mb_per_user', 0);

        return Inertia::render('Addons/ai-image-pro/User/Library', [
            // through() maps the items while preserving Laravel's plain paginator shape
            // (`links` as an ARRAY, `total`/`from`/`to`/`current_page` at the top level),
            // which is what the page and the shared Pagination component read. Passing the
            // paginator to AssetResource::collection() instead would reshape it into
            // {data, links:{first,last,…}, meta:{…}} and silently break pagination.
            'assets' => $assets->through(fn (AipAsset $asset) => new AssetResource($asset)),
            'folders' => $folders,
            'filters' => $filters,
            'storage' => [
                'usedMb' => round($this->access->storageUsedBytes($user) / (1024 * 1024), 2),
                'capMb' => $capMb,
            ],
            'models' => $this->modelsUsedBy($user),
            // An asset stores the raw provider slug it was generated with. The catalogue
            // turns that into the display name and provider the admin configured; without
            // it the preview would show `gemini-3.1-flash-image-preview` at the user.
            'modelCatalog' => $this->catalog->forFrontend(),
            // An asset stores the registry key of the operation that made it (`bg_remove`).
            // The registry turns that into the name the admin sees, so the preview header
            // never shows a bare key.
            'operations' => $this->registry->forFrontend($user),
            // When the admin does not let users pick a model, which model ran is not the
            // user's decision — the preview hides it rather than naming it.
            'allowModelChoice' => $this->catalog->allowsUserChoice(),
        ]);
    }

    /**
     * The distinct model slugs the user has generated with — feeds the Library's
     * model filter dropdown.
     *
     * @return array<int, string>
     */
    private function modelsUsedBy(User $user): array
    {
        return AipAsset::where('user_id', $user->id)
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->all();
    }

    /**
     * `source` is matched against the column verbatim, so an unrecognised value can only
     * ever return nothing. Ignoring it beats filtering on it: an unknown value means a stale
     * bookmark or a hand-typed query string, and "every image" is a far more honest answer
     * than a permanently empty gallery with no explanation.
     */
    private function sourceFilter(Request $request): ?string
    {
        $value = $this->stringFilter($request, 'source');

        return in_array($value, self::SOURCES, true) ? $value : null;
    }

    private function stringFilter(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
