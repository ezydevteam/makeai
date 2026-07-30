<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use App\Models\User;
use App\Services\AccessLevelService;

/**
 * The single source of truth for every operation the addon exposes.
 *
 * Nothing about an operation is hardcoded anywhere else: the Studio UI, the
 * controllers, the credit charge, the access gate and the job dispatcher all
 * read their behaviour from here. An operation's shipped defaults are merged
 * with the admin's overrides (stored as one JSON setting, `operations`), so an
 * operator can disable an op, change its provider, re-price it or restrict it
 * to a plan without a code change or a migration.
 *
 * Adding an operation = one entry in defaults() + one handler in the engine
 * that owns its tier.
 */
class OperationRegistry
{
    public const SLUG = 'ai-image-pro';

    // Tiers decide *which engine runs the op* and whether it queues.
    public const TIER_GENERATE = 'generate';   // core media pipeline (AiManager / addon gen clients)
    public const TIER_PROVIDER = 'provider';   // third-party edit APIs, queued
    public const TIER_LOCAL    = 'local';      // GD, synchronous, no queue

    // Billing modes decide *how the op is charged*.
    public const BILLING_MEDIA = 'media';      // TokenGuard::beforeMedia/afterMedia, per unit, per model
    public const BILLING_FLAT  = 'flat';       // flat admin-set credits, deduct_credits() + refundCredits()
    public const BILLING_FREE  = 'free';       // no charge

    // UI grouping in the tool rail.
    public const GROUP_CREATE  = 'create';
    public const GROUP_ENHANCE = 'enhance';
    public const GROUP_ADJUST  = 'adjust';

    public function __construct(private AccessLevelService $accessLevels)
    {
    }

    /**
     * Shipped defaults for every operation.
     *
     * Keys per operation:
     *   label, description, icon, group → tool rail presentation (both translated at
     *                                 render time, never stored translated)
     *   tier                        → engine + queue behaviour
     *   billing                     → media | flat | free
     *   inputs                      → what the request must carry: image, mask, prompt, reference, text
     *   providers                   → selectable engines (admin picks one); [] = fixed engine
     *   provider                    → default engine
     *   credits                     → default flat cost (ignored unless billing = flat)
     *   access                      → default access level key (AccessLevelService)
     *   enabled                     → shipped on/off
     *   async                       → queued (true) or answered in-request (false)
     *   requires_key                → api-key setting that must be present for `providers` entry
     *
     * @return array<string, array<string, mixed>>
     */
    public function defaults(): array
    {
        return [
            // ── Create ───────────────────────────────────────────────
            'generate' => [
                'label' => 'Generate',
                'description' => 'Turn a text prompt into a brand-new image.',
                'icon' => 'ti ti-sparkles',
                'group' => self::GROUP_CREATE,
                'tier' => self::TIER_GENERATE,
                'billing' => self::BILLING_MEDIA,
                'inputs' => ['prompt'],
                'providers' => [],
                'provider' => 'model',
                'credits' => 0,
                // The hook. A guest generates a few images on the free daily allowance
                // (guest_daily_limit) and hits the signup wall having already seen it work.
                // The paid provider edits below stay behind `login` so anonymous traffic
                // cannot burn third-party API credit.
                'access' => 'guest',
                'enabled' => true,
                'async' => true,
            ],
            'variations' => [
                'label' => 'Variations',
                'description' => 'Create fresh takes on an image you already have.',
                'icon' => 'ti ti-copy',
                'group' => self::GROUP_CREATE,
                'tier' => self::TIER_GENERATE,
                'billing' => self::BILLING_MEDIA,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'model',
                'credits' => 0,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'prompt_edit' => [
                'label' => 'Edit with Prompt',
                'description' => 'Describe a change and let the model repaint the image.',
                'icon' => 'ti ti-wand',
                'group' => self::GROUP_CREATE,
                'tier' => self::TIER_GENERATE,
                'billing' => self::BILLING_MEDIA,
                'inputs' => ['image', 'prompt'],
                'providers' => [],
                'provider' => 'model',
                'credits' => 0,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],

            // ── Enhance (third-party edit APIs) ──────────────────────
            'bg_remove' => [
                'label' => 'Remove Background',
                'description' => 'Cut the subject out and drop the background to transparent.',
                'icon' => 'ti ti-eraser',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image'],
                'providers' => ['remove_bg', 'clipdrop', 'replicate'],
                'provider' => 'remove_bg',
                'credits' => 5,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'bg_replace' => [
                'label' => 'Replace Background',
                'description' => 'Swap the background for a new scene you describe.',
                'icon' => 'ti ti-photo-scan',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image', 'prompt'],
                'providers' => ['stability', 'clipdrop'],
                'provider' => 'stability',
                'credits' => 15,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'upscale' => [
                'label' => 'Upscale',
                'description' => 'Enlarge an image and recover detail, without the mush.',
                'icon' => 'ti ti-arrows-maximize',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image'],
                'providers' => ['replicate', 'stability', 'clipdrop'],
                'provider' => 'replicate',
                'credits' => 20,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'inpaint' => [
                'label' => 'Erase & Replace',
                'description' => 'Brush over an area and describe what belongs there instead.',
                'icon' => 'ti ti-brush',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image', 'mask', 'prompt'],
                'providers' => ['stability', 'replicate'],
                'provider' => 'stability',
                'credits' => 15,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'object_remove' => [
                'label' => 'Remove Object',
                'description' => 'Paint out an unwanted object and fill the gap seamlessly.',
                'icon' => 'ti ti-clear-formatting',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image', 'mask'],
                'providers' => ['stability', 'clipdrop'],
                'provider' => 'stability',
                'credits' => 15,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'outpaint' => [
                'label' => 'Expand',
                'description' => 'Extend the canvas and let the model invent what lies beyond.',
                'icon' => 'ti ti-arrow-autofit-width',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image'],
                'providers' => ['stability'],
                'provider' => 'stability',
                'credits' => 15,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],
            'style_transfer' => [
                'label' => 'Style Transfer',
                'description' => 'Repaint an image in the style of a reference you supply.',
                'icon' => 'ti ti-palette',
                'group' => self::GROUP_ENHANCE,
                'tier' => self::TIER_PROVIDER,
                'billing' => self::BILLING_FLAT,
                'inputs' => ['image', 'reference'],
                'providers' => ['stability'],
                'provider' => 'stability',
                'credits' => 20,
                'access' => 'login',
                'enabled' => true,
                'async' => true,
            ],

            // ── Adjust (local GD, free, synchronous) ─────────────────
            'resize' => [
                'label' => 'Resize',
                'description' => 'Set exact pixel dimensions, with or without keeping the ratio.',
                'icon' => 'ti ti-resize',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'crop' => [
                'label' => 'Crop',
                'description' => 'Trim the frame down to the part that matters.',
                'icon' => 'ti ti-crop',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'rotate' => [
                'label' => 'Rotate & Flip',
                'description' => 'Turn the image, or flip it horizontally or vertically.',
                'icon' => 'ti ti-rotate',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'compress' => [
                'label' => 'Compress',
                'description' => 'Shrink the file size while keeping it looking sharp.',
                'icon' => 'ti ti-file-zip',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'convert' => [
                'label' => 'Convert Format',
                'description' => 'Save the image out as JPG, PNG, WebP or AVIF.',
                'icon' => 'ti ti-transform',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'watermark' => [
                'label' => 'Watermark',
                'description' => 'Stamp your own text across the image.',
                'icon' => 'ti ti-copyright',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image', 'text'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'text_overlay' => [
                'label' => 'Add Text',
                'description' => 'Place custom text anywhere on the image.',
                'icon' => 'ti ti-text-size',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image', 'text'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
            'color_correction' => [
                'label' => 'Adjust Colors',
                'description' => 'Tune brightness, contrast, saturation and hue.',
                'icon' => 'ti ti-adjustments',
                'group' => self::GROUP_ADJUST,
                'tier' => self::TIER_LOCAL,
                'billing' => self::BILLING_FREE,
                'inputs' => ['image'],
                'providers' => [],
                'provider' => 'gd',
                'credits' => 0,
                // Local tools cost nothing to run and are the top-of-funnel draw, so they
                // ship open to guests. Admin-overridable like every other field.
                'access' => 'guest',
                'enabled' => true,
                'async' => false,
            ],
        ];
    }

    /**
     * The API key setting each third-party engine needs. An engine with no entry
     * (gd, model) needs no key of its own.
     *
     * @return array<string, string>
     */
    public function engineKeys(): array
    {
        return [
            'stability' => 'stability_api_key',
            'replicate' => 'replicate_api_key',
            'remove_bg' => 'remove_bg_api_key',
            'clipdrop' => 'clipdrop_api_key',
            'fal' => 'fal_api_key',
            'ideogram' => 'ideogram_api_key',
        ];
    }

    /**
     * Every operation, with the admin's overrides applied.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $overrides = $this->overrides();

        $merged = [];
        foreach ($this->defaults() as $key => $definition) {
            $override = $overrides[$key] ?? [];

            // Only the operator-owned fields may be overridden — an admin can't
            // redefine an op's tier, inputs or billing mode from the settings
            // table and hand a free op to a paid engine.
            $definition['enabled'] = array_key_exists('enabled', $override)
                ? (bool) $override['enabled']
                : $definition['enabled'];

            $definition['access'] = $this->sanitizeAccess(
                $override['access'] ?? $definition['access'],
                $definition['access'],
            );

            $definition['credits'] = array_key_exists('credits', $override)
                ? max(0, (int) $override['credits'])
                : $definition['credits'];

            if (! empty($definition['providers'])
                && isset($override['provider'])
                && in_array($override['provider'], $definition['providers'], true)) {
                $definition['provider'] = $override['provider'];
            }

            $definition['key'] = $key;
            $definition['available'] = $this->engineIsConfigured($definition['provider']);

            $merged[$key] = $definition;
        }

        return $merged;
    }

    public function get(string $operation): ?array
    {
        return $this->all()[$operation] ?? null;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function require(string $operation): array
    {
        $definition = $this->get($operation);

        if (! $definition) {
            throw new \InvalidArgumentException("Unknown image operation: {$operation}");
        }

        return $definition;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->defaults());
    }

    /** @return array<int, string> */
    public function keysForTier(string $tier): array
    {
        return array_keys(array_filter($this->all(), fn ($op) => $op['tier'] === $tier));
    }

    public function isEnabled(string $operation): bool
    {
        return (bool) ($this->get($operation)['enabled'] ?? false);
    }

    /** Operation is switched on AND its engine has the key it needs. */
    public function isUsable(string $operation): bool
    {
        $definition = $this->get($operation);

        return $definition !== null && $definition['enabled'] && $definition['available'];
    }

    public function tier(string $operation): string
    {
        return (string) ($this->get($operation)['tier'] ?? self::TIER_LOCAL);
    }

    public function billing(string $operation): string
    {
        return (string) ($this->get($operation)['billing'] ?? self::BILLING_FREE);
    }

    public function engine(string $operation): string
    {
        return (string) ($this->get($operation)['provider'] ?? 'gd');
    }

    /**
     * The flat credit cost of an operation. Media-billed ops always return 0 — they
     * are priced per image per model through TokenGuard, not by a flat number here.
     * Flat AND free ops both honour an admin-set credit cost, so an operator can put
     * a price on a "free" local tool (0 or blank keeps it free) without it changing
     * tier or engine.
     */
    public function credits(string $operation): int
    {
        $definition = $this->get($operation);

        if (! $definition || $definition['billing'] === self::BILLING_MEDIA) {
            return 0;
        }

        return max(0, (int) $definition['credits']);
    }

    /**
     * Whether running this operation deducts credits from the user. True for a flat
     * op, or a free-tier op the admin has priced above zero.
     */
    public function isChargeable(string $operation): bool
    {
        return $this->credits($operation) > 0;
    }

    public function accessLevel(string $operation): string
    {
        return (string) ($this->get($operation)['access'] ?? 'login');
    }

    public function isAsync(string $operation): bool
    {
        return (bool) ($this->get($operation)['async'] ?? false);
    }

    /** @return array<int, string> */
    public function inputs(string $operation): array
    {
        return (array) ($this->get($operation)['inputs'] ?? []);
    }

    public function requiresInput(string $operation, string $input): bool
    {
        return in_array($input, $this->inputs($operation), true);
    }

    /**
     * An engine is configured when its API key is present. `gd` and `model`
     * carry no key of their own — GD is local, and `model` resolves its
     * credentials through the core AI key manager per model.
     */
    public function engineIsConfigured(string $engine): bool
    {
        if (in_array($engine, ['gd', 'model'], true)) {
            return true;
        }

        $key = $this->engineKeys()[$engine] ?? null;

        return $key !== null && ! empty(addon_setting(self::SLUG, $key));
    }

    /**
     * The operation list as the Studio consumes it: presentation, cost, and
     * whether *this* user may run it. `locked` means visible but not permitted —
     * the UI shows it with an upgrade/login prompt rather than hiding it, which
     * is what converts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forFrontend(?User $user): array
    {
        $result = [];

        foreach ($this->all() as $key => $definition) {
            if (! $definition['enabled']) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'label' => translate($definition['label']),
                'description' => translate((string) ($definition['description'] ?? '')),
                'icon' => $definition['icon'],
                'group' => $definition['group'],
                'tier' => $definition['tier'],
                'billing' => $definition['billing'],
                'inputs' => $definition['inputs'],
                'credits' => $this->credits($key),
                'async' => $definition['async'],
                'available' => $definition['available'],
                'locked' => ! $this->accessLevels->checkAccess($definition['access'], $user),
                'access' => $definition['access'],
            ];
        }

        return $result;
    }

    /**
     * The admin's saved overrides. Stored as one JSON blob so operations can be
     * re-priced or re-gated without a settings migration per operation.
     *
     * @return array<string, array<string, mixed>>
     */
    private function overrides(): array
    {
        $stored = addon_setting(self::SLUG, 'operations');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return is_array($stored) ? $stored : [];
    }

    /**
     * An access level that no longer exists (a deleted plan, or `premium` on a
     * Regular license where pro is unavailable) must not silently open the op to
     * everyone — fall back to the shipped default.
     */
    private function sanitizeAccess(mixed $candidate, string $fallback): string
    {
        if (! is_string($candidate) || $candidate === '') {
            return $fallback;
        }

        return $this->accessLevels->levelExists($candidate) ? $candidate : $fallback;
    }
}
