<?php

declare(strict_types=1);

namespace App\Services;

use Closure;

/**
 * Addons that can take over the site homepage.
 *
 * An addon registers itself from its AddonServiceProvider::boot() — which only runs
 * while the addon is active — so both the admin picker and the public `/` route learn
 * what is on offer without core code naming any addon.
 *
 * To offer a homepage from a new addon, call this from its ServiceProvider::boot():
 *
 *     app(HomepageProviderRegistry::class)->register('my-addon', [
 *         'label'        => translate('My Addon'),
 *         'description'  => translate('What the visitor sees at the site root.'),
 *         // Closures, not values: boot() runs before the route table is complete,
 *         // and settings read here would be cached for the process lifetime.
 *         'settings_url' => fn () => route('admin.addons.settings', ['slug' => 'my-addon']),
 *         'available'    => fn () => (bool) addon_setting('my-addon', 'enabled', true),
 *         'render'       => fn () => Inertia::render('Addons/my-addon/Landing', [...]),
 *     ]);
 *
 * The alias MUST be the addon slug — `available()` cross-checks it against the addon's
 * active flag, so a provider left registered by a deactivated addon can never be served.
 */
class HomepageProviderRegistry
{
    /**
     * @var array<string, array{label: string, description: string, settings_url: string|Closure|null, available: Closure, render: Closure}>
     */
    private array $providers = [];

    /**
     * @param  array{label?: string, description?: string, settings_url?: string|Closure|null, available?: Closure, render: Closure}  $provider
     */
    public function register(string $alias, array $provider): void
    {
        $this->providers[$alias] = [
            'label' => $provider['label'] ?? $alias,
            'description' => $provider['description'] ?? '',
            'settings_url' => $provider['settings_url'] ?? null,
            'available' => $provider['available'] ?? static fn (): bool => true,
            'render' => $provider['render'],
        ];
    }

    /**
     * The provider behind an alias, or null when its addon is inactive, its own
     * `available` gate is closed, or nothing registered under that alias.
     *
     * @return array{label: string, description: string, settings_url: string|Closure|null, available: Closure, render: Closure}|null
     */
    public function resolve(string $alias): ?array
    {
        $provider = $this->providers[$alias] ?? null;

        if (! $provider || ! is_addon_active($alias) || ! ($provider['available'])()) {
            return null;
        }

        return $provider;
    }

    /**
     * Every selectable provider, shaped for the admin picker. Empty when no addon
     * offers a homepage — which is what hides the picker entirely.
     *
     * @return array<int, array{value: string, label: string, description: string, settings_url: string|null}>
     */
    public function options(): array
    {
        $options = [];

        foreach (array_keys($this->providers) as $alias) {
            if (! $provider = $this->resolve($alias)) {
                continue;
            }

            $settingsUrl = $provider['settings_url'];

            $options[] = [
                'value' => $alias,
                'label' => $provider['label'],
                'description' => $provider['description'],
                'settings_url' => $settingsUrl instanceof Closure ? $settingsUrl() : $settingsUrl,
            ];
        }

        return $options;
    }

    /**
     * Render the homepage for an alias. Null when the provider is unavailable, which
     * lets the caller fall back to the active theme's own homepage.
     */
    public function render(string $alias): mixed
    {
        if (! $provider = $this->resolve($alias)) {
            return null;
        }

        return ($provider['render'])();
    }
}
