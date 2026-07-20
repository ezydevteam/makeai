<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Http\Requests\GenerateImageRequest;
use Addons\AiImagePro\Http\Requests\RunOperationRequest;
use Addons\AiImagePro\Http\Requests\UploadImageRequest;
use Addons\AiImagePro\Http\Resources\AssetResource;
use Addons\AiImagePro\Jobs\RunImageJob;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Models\AipPreset;
use Addons\AiImagePro\Services\AssetService;
use Addons\AiImagePro\Services\CreditService;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\LandingContentService;
use Addons\AiImagePro\Services\LocalImageService;
use Addons\AiImagePro\Services\ModelCatalog;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\GlobalBudgetExceededException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AI\TokenGuard;
use App\Services\ContentModerationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Studio: the single-page workbench where an image is created (generate),
 * transformed with a queued provider op (upscale, bg-remove, …) or polled for a
 * result. Local GD operations do not live here — they are synchronous and answered
 * by ToolController — but every async operation, whatever its tier, is created and
 * dispatched from this controller so the billing split lives in exactly one place.
 *
 * Nothing about an operation is decided here: the registry says which tier an op is,
 * ImageAccessService says whether the caller may run it, and the model catalogue and
 * TokenGuard price the media path. This controller only wires them together.
 */
class StudioController extends Controller
{
    public function __construct(
        private readonly ImageAccessService $access,
        private readonly OperationRegistry $registry,
        private readonly ModelCatalog $catalog,
        private readonly AssetService $assets,
        private readonly CreditService $credits,
        private readonly LocalImageService $localImages,
        private readonly LandingContentService $landing,
    ) {
    }

    /**
     * The Studio page. The route carries no `auth` middleware by design — the admin
     * decides whether guests get in via the `studio_access` level — so the gate is
     * enforced here rather than by the router.
     *
     * A guest arriving from the landing page carries their prompt in `?prompt=`; the
     * page prefills it and fires the generation itself, so it still passes through
     * every gate the endpoint enforces (access level, guest daily limit, moderation).
     * Nothing is charged or trusted here.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $this->access->canAccessStudio($user)) {
            if (! $user) {
                return redirect()->guest(route('login'));
            }

            abort(403, translate('You do not have access to the Studio.'));
        }

        $autoPrompt = $request->string('prompt')->trim()->limit(2000)->value();

        // The landing panel also lets a visitor pick a model and an aspect ratio before
        // they ever reach the Studio. Carry those across, or those chips would be dead
        // controls. Both are validated against what is actually on offer — a hand-typed
        // query string cannot select a model the admin disabled.
        $autoModel = $request->string('model')->trim()->value();
        $autoModel = collect($this->catalog->forFrontend())->contains('id', $autoModel) ? $autoModel : null;

        $autoAspect = $request->string('aspect')->trim()->value();
        $autoAspect = collect($this->aspectRatios())->contains('key', $autoAspect) ? $autoAspect : null;

        return Inertia::render('Addons/ai-image-pro/User/Studio', [
            'heading' => $this->landing->studioHeading(),
            'subheading' => $this->landing->studioSubheading(),
            'examples' => $this->landing->examples(),
            'autoPrompt' => $autoPrompt !== '' ? $autoPrompt : null,
            'autoModel' => $autoModel,
            'autoAspect' => $autoAspect,
            'operations' => $this->registry->forFrontend($user),
            'models' => $this->catalog->forFrontend(),
            'defaultModel' => ($default = $this->catalog->default()) ? $this->catalog->alias($default) : null,
            'allowModelChoice' => $this->catalog->allowsUserChoice(),
            'aspectRatios' => $this->aspectRatios(),
            'presets' => $this->presetsForFrontend(),
            'maxBatchSize' => max(1, (int) addon_setting(OperationRegistry::SLUG, 'max_batch_size', 4)),
            'limits' => [
                'maxInputSizeMb' => max(1, (int) addon_setting(OperationRegistry::SLUG, 'max_input_size_mb', 10)),
                'maxInputDimension' => (int) addon_setting(OperationRegistry::SLUG, 'max_input_dimension', 8000),
                'maxOutputDimension' => (int) addon_setting(OperationRegistry::SLUG, 'max_output_dimension', 4096),
            ],
            'features' => [
                'promptEnhancer' => (bool) addon_setting(OperationRegistry::SLUG, 'enable_prompt_enhancer', true),
                'negativePrompt' => (bool) addon_setting(OperationRegistry::SLUG, 'enable_negative_prompt', true),
                'seed' => (bool) addon_setting(OperationRegistry::SLUG, 'enable_seed', true),
            ],
            // ->resolve() flattens to a plain array. Without it Inertia resolves the
            // Responsable collection through toResponse(), which applies the JsonResource
            // `data` wrapper — the page would receive {data: [...]} and blow up on iterate.
            'recentAssets' => AssetResource::collection($this->recentAssets($user, $request->ip()))->resolve(),
            'isGuest' => $user === null,
        ]);
    }

    /**
     * Store a source image. Not an operation — no job, no credits — so it gates on
     * Studio access (in UploadImageRequest) rather than on the registry, but it must
     * still guard the bitmap before it is decoded and respect the storage quota.
     */
    public function upload(UploadImageRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);

        $this->access->assertWithinStorageQuota($user);

        /** @var UploadedFile $file */
        $file = $request->file('image');

        try {
            // Header-only guard before AssetService decodes the file.
            $this->localImages->guard((string) $file->getRealPath());

            $asset = $this->assets->storeUpload($file, [
                'user_id' => $user?->id,
                'guest_ip' => $user ? null : $request->ip(),
                'source' => 'uploaded',
                'expires_at' => $this->access->expiresAtFor($user),
            ]);
        } catch (ImageOperationException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $asset->loadMissing('parent:id,ulid');

        return response()->json([
            'success' => true,
            'asset' => new AssetResource($asset),
        ]);
    }

    /**
     * Text-to-image. `generate` is the media tier: it is priced per unit per model
     * through TokenGuard, so the affordability check happens here (immediate
     * 402/429/503) and the actual charge happens in the job after the images land.
     */
    public function generate(GenerateImageRequest $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $ip = $request->ip();

        $prompt = (string) $request->input('prompt', '');

        if (ContentModerationService::fromSettings()->textViolates($prompt, OperationRegistry::SLUG)) {
            return $this->error(translate('This request was blocked by content safety filters.'), 422);
        }

        try {
            $model = $this->catalog->resolveAlias($request->input('model'));
        } catch (ImageOperationException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $referenceAsset = $this->resolveOwnedAsset($request->input('reference_asset_ulid'), $user, $ip);

        $count = $this->clampCount($request->input('count'));

        $this->access->assertWithinStorageQuota($user);

        // Media pre-flight — throws an HttpResponseException (402/429/503) that short
        // circuits before any job row or credit movement.
        $this->assertMediaAffordable($user, $model->slug, $count);

        $job = AipJob::create([
            'user_id' => $user?->id,
            'guest_ip' => $user ? null : $ip,
            'operation' => GenerateImageRequest::OPERATION,
            'tier' => OperationRegistry::TIER_GENERATE,
            'status' => AipJob::STATUS_QUEUED,
            'engine' => $model->provider,
            'model' => $model->slug,
            'input_asset_id' => $referenceAsset?->id,
            'batch_size' => $count,
            'billing_mode' => OperationRegistry::BILLING_MEDIA,
            'params' => [
                'prompt' => $prompt,
                'negative_prompt' => $request->input('negative_prompt'),
                'aspect' => $request->input('aspect'),
                'seed' => $request->input('seed'),
                'preset' => $request->input('preset'),
                'count' => $count,
            ],
        ]);

        RunImageJob::dispatch($job->id)->onQueue('media');

        return $this->jobResponse($job);
    }

    /**
     * Every async operation that acts on an existing image: the generate-tier
     * derivations (variations, prompt_edit) and the provider-tier edit APIs
     * (upscale, bg_remove, inpaint, …). RunOperationRequest has already gated the
     * op and validated its inputs against the registry; here we assemble the job
     * and charge it the way its tier dictates.
     */
    public function runOperation(RunOperationRequest $request): JsonResponse
    {
        $operation = $request->operation();
        $user = $this->currentUser($request);
        $ip = $request->ip();

        $tier = $this->registry->tier($operation);

        $inputAsset = null;
        if ($this->registry->requiresInput($operation, 'image')) {
            $inputAsset = $this->resolveOwnedAsset($request->input('asset_ulid'), $user, $ip);

            if (! $inputAsset) {
                return $this->error(translate('Select an image first.'), 422);
            }
        }

        $params = (array) $request->input('params', []);

        $prompt = (string) ($params['prompt'] ?? '');
        if ($prompt !== '' && ContentModerationService::fromSettings()->textViolates($prompt, OperationRegistry::SLUG)) {
            return $this->error(translate('This request was blocked by content safety filters.'), 422);
        }

        $this->access->assertWithinStorageQuota($user);

        $count = $this->clampCount($request->input('count'));

        // Mask and reference are stored on the private disk — they are transient
        // job inputs, not library assets, and must never be publicly reachable.
        $maskPath = $request->hasFile('mask')
            ? $this->storePrivate($request->file('mask'), $user, 'masks')
            : null;

        $referencePath = null;
        if ($this->registry->requiresInput($operation, 'reference')) {
            if ($request->hasFile('reference')) {
                $referencePath = $this->storePrivate($request->file('reference'), $user, 'references');
            } elseif ($refUlid = $request->input('reference_asset_ulid')) {
                $reference = $this->resolveOwnedAsset($refUlid, $user, $ip);

                if (! $reference) {
                    return $this->error(translate('The reference image is not available.'), 422);
                }

                $params['reference_asset_ulid'] = $reference->ulid;
            }
        }

        if ($tier === OperationRegistry::TIER_GENERATE) {
            try {
                $model = $this->catalog->resolveAlias($request->input('model'));
            } catch (ImageOperationException $e) {
                return $this->error($e->getMessage(), 422);
            }

            $this->assertMediaAffordable($user, $model->slug, $count);

            $engine = $model->provider;
            $modelSlug = $model->slug;
            $billing = OperationRegistry::BILLING_MEDIA;
            $flatCredits = 0;
        } else {
            $flatCredits = $this->registry->credits($operation);

            // 402 when a metered-mode user can't afford it — before any job row.
            $this->credits->assertCanAffordFlat($user, $flatCredits);

            $engine = $this->registry->engine($operation);
            $modelSlug = null;
            $billing = OperationRegistry::BILLING_FLAT;
        }

        $job = AipJob::create([
            'user_id' => $user?->id,
            'guest_ip' => $user ? null : $ip,
            'operation' => $operation,
            'tier' => $tier,
            'status' => AipJob::STATUS_QUEUED,
            'engine' => $engine,
            'model' => $modelSlug,
            'input_asset_id' => $inputAsset?->id,
            'mask_path' => $maskPath,
            'reference_path' => $referencePath,
            'batch_size' => $count,
            'billing_mode' => $billing,
            'params' => $params,
        ]);

        // Flat tier charges up front so the refund path (in the job) knows exactly
        // what to return; the media tier is charged in the job after success.
        if ($tier === OperationRegistry::TIER_PROVIDER) {
            $this->credits->chargeFlat($user, $job, $flatCredits);
        }

        RunImageJob::dispatch($job->id)->onQueue('media');

        return $this->jobResponse($job);
    }

    /**
     * Poll a job. Bound by ulid; ownership is enforced here because the route sits in
     * the guest-capable group and a guest owns a job only by matching IP.
     */
    public function status(Request $request, AipJob $job): JsonResponse
    {
        $user = $this->currentUser($request);

        if (! $this->jobOwnedBy($job, $user, $request->ip())) {
            abort(403);
        }

        $job->load(['assets' => fn ($query) => $query->with('parent:id,ulid')]);

        return response()->json([
            'status' => $job->status,
            'error' => $job->error_message,
            'assets' => AssetResource::collection($job->assets),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function currentUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function clampCount(mixed $count): int
    {
        $max = max(1, (int) addon_setting(OperationRegistry::SLUG, 'max_batch_size', 4));

        return max(1, min($max, (int) ($count ?? 1)));
    }

    /**
     * Resolve a ulid to an asset the caller owns, or null. A ulid that resolves to
     * someone else's asset is a 403, never a silent null — that would leak nothing
     * but also mask an authorisation bug.
     */
    private function resolveOwnedAsset(mixed $ulid, ?User $user, ?string $ip): ?AipAsset
    {
        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        $asset = AipAsset::where('ulid', $ulid)->first();

        if (! $asset) {
            return null;
        }

        if (! $asset->ownedBy($user, $ip)) {
            abort(403);
        }

        return $asset;
    }

    private function storePrivate(UploadedFile $file, ?User $user, string $bucket): string
    {
        $owner = $user ? (string) $user->id : 'guest';

        return (string) $file->store(OperationRegistry::SLUG . '/' . $owner . '/' . $bucket, 'local');
    }

    /**
     * Media affordability pre-flight. TokenGuard throws domain exceptions; translate
     * them to the HTTP contract's status codes so the Studio can react immediately.
     *
     * @throws HttpResponseException
     */
    private function assertMediaAffordable(?User $user, string $modelSlug, int $count): void
    {
        try {
            TokenGuard::beforeMedia($user, 'image', $modelSlug, $count);
        } catch (InsufficientCreditsException $e) {
            throw $this->httpFailure($e->getMessage(), 402);
        } catch (CreditLimitException $e) {
            throw $this->httpFailure($e->getMessage(), 429);
        } catch (GlobalBudgetExceededException $e) {
            throw $this->httpFailure($e->getMessage(), 503);
        }
    }

    private function jobOwnedBy(AipJob $job, ?User $user, ?string $ip): bool
    {
        if ($user) {
            return $job->user_id === $user->id;
        }

        return $job->user_id === null && $ip !== null && $job->guest_ip === $ip;
    }

    private function jobResponse(AipJob $job): JsonResponse
    {
        return response()->json([
            'success' => true,
            'job' => [
                'ulid' => $job->ulid,
                'status' => $job->status,
            ],
        ]);
    }

    /**
     * The selectable aspect ratios: the admin's `aspect_ratios` JSON when set,
     * otherwise the built-in defaults. Never hardcoded in the frontend.
     *
     * @return array<int, array{key: string, label: string, width: int, height: int}>
     */
    private function aspectRatios(): array
    {
        $stored = addon_setting(OperationRegistry::SLUG, 'aspect_ratios');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (is_array($stored) && $stored !== []) {
            $ratios = [];

            foreach ($stored as $ratio) {
                if (! is_array($ratio) || ! isset($ratio['key'])) {
                    continue;
                }

                $ratios[] = [
                    'key' => (string) $ratio['key'],
                    'label' => (string) ($ratio['label'] ?? $ratio['key']),
                    'width' => (int) ($ratio['width'] ?? 1),
                    'height' => (int) ($ratio['height'] ?? 1),
                ];
            }

            if ($ratios !== []) {
                return $ratios;
            }
        }

        return $this->defaultAspectRatios();
    }

    /**
     * @return array<int, array{key: string, label: string, width: int, height: int}>
     */
    private function defaultAspectRatios(): array
    {
        return [
            ['key' => '1:1', 'label' => translate('Square'), 'width' => 1, 'height' => 1],
            ['key' => '16:9', 'label' => translate('Widescreen'), 'width' => 16, 'height' => 9],
            ['key' => '9:16', 'label' => translate('Portrait'), 'width' => 9, 'height' => 16],
            ['key' => '4:3', 'label' => translate('Standard'), 'width' => 4, 'height' => 3],
            ['key' => '3:2', 'label' => translate('Photo'), 'width' => 3, 'height' => 2],
            ['key' => '3:4', 'label' => translate('Portrait 3:4'), 'width' => 3, 'height' => 4],
            ['key' => '2:3', 'label' => translate('Portrait 2:3'), 'width' => 2, 'height' => 3],
        ];
    }

    /**
     * @return array<int, array{slug: string, name: string, prompt_suffix: ?string, thumb_url: ?string}>
     */
    private function presetsForFrontend(): array
    {
        return AipPreset::active()->get()->map(fn (AipPreset $preset) => [
            'slug' => $preset->slug,
            'name' => $preset->name,
            'prompt_suffix' => $preset->prompt_suffix,
            'thumb_url' => $preset->thumb_url,
        ])->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AipAsset>
     */
    private function recentAssets(?User $user, ?string $ip)
    {
        $query = AipAsset::with('parent:id,ulid')->latest();

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('user_id')->where('guest_ip', $ip);
        }

        return $query->limit(24)->get();
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function httpFailure(string $message, int $status): HttpResponseException
    {
        return new HttpResponseException(
            response()->json(['success' => false, 'message' => $message], $status)
        );
    }
}
