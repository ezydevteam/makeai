<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * AddonService — scans addons/ directory, registers enabled addons, auto-loads ServiceProviders.
 * Ref: AI_SaaS_Master_Prompt Part 2.2.
 */
class AddonService
{
    private string $addonsPath;

    public function __construct()
    {
        $this->addonsPath = config('addons.path', base_path('addons'));
    }

    /**
     * Get all available addons by scanning the addons directory.
     */
    public function getAvailableAddons(): array
    {
        $addons = [];

        if (! File::isDirectory($this->addonsPath)) {
            return $addons;
        }

        foreach (File::directories($this->addonsPath) as $dir) {
            $settingsFile = $dir.'/settings.json';
            if (File::exists($settingsFile)) {
                $config = json_decode(File::get($settingsFile), true);
                if ($config && isset($config['slug'])) {
                    $config['path'] = $dir;
                    $config['is_active'] = $this->isActive($config['slug']);
                    $config['license_ok'] = $this->checkLicenseRequirement($config);
                    $addons[] = $config;
                }
            }
        }

        return $addons;
    }

    /**
     * Get a specific addon's configuration.
     */
    public function getAddonConfig(string $slug): ?array
    {
        $settingsFile = $this->addonsPath.'/'.$slug.'/settings.json';

        if (! File::exists($settingsFile)) {
            return null;
        }

        $config = json_decode(File::get($settingsFile), true);
        $config['path'] = $this->addonsPath.'/'.$slug;
        $config['is_active'] = $this->isActive($slug);
        $config['license_ok'] = $this->checkLicenseRequirement($config);

        return $config;
    }

    /**
     * Check if an addon is active.
     */
    public function isActive(string $slug): bool
    {
        $active = $this->getActiveAddons();

        return in_array($slug, $active);
    }

    /**
     * Get list of active addon slugs.
     */
    public function getActiveAddons(): array
    {
        $addons = settings('active_addons', '[]');
        if (is_string($addons)) {
            $addons = json_decode($addons, true) ?? [];
        }

        return is_array($addons) ? $addons : [];
    }

    /**
     * Activate an addon.
     */
    public function activate(string $slug): bool
    {
        $config = $this->getAddonConfig($slug);
        if (! $config) {
            return false;
        }

        if (! $this->checkLicenseRequirement($config)) {
            return false;
        }

        $active = $this->getActiveAddons();
        if (! in_array($slug, $active)) {
            $active[] = $slug;
            settings_set('active_addons', json_encode($active), 'json', 'addons');
        }

        Cache::forget('active_addons_list');

        return true;
    }

    /**
     * Deactivate an addon.
     */
    public function deactivate(string $slug): bool
    {
        $active = $this->getActiveAddons();
        $active = array_values(array_filter($active, fn ($s) => $s !== $slug));
        settings_set('active_addons', json_encode($active), 'json', 'addons');
        Cache::forget('active_addons_list');

        return true;
    }

    /**
     * Get all settings for an addon with current values.
     */
    public function getAddonSettings(string $slug): array
    {
        $config = $this->getAddonConfig($slug);
        if (! $config || empty($config['settings'])) {
            return [];
        }

        return array_map(function ($setting) use ($slug) {
            $setting['value'] = settings("addon_{$slug}_{$setting['key']}", $setting['default'] ?? null);

            return $setting;
        }, $config['settings']);
    }

    /**
     * Save addon settings (bulk).
     */
    public function saveAddonSettings(string $slug, array $values): void
    {
        foreach ($values as $key => $value) {
            settings_set("addon_{$slug}_{$key}", $value, 'string', 'addon');
        }
    }

    /**
     * Register all active addons' ServiceProviders.
     * Called from AppServiceProvider::boot().
     */
    public function registerActiveAddons(): void
    {
        foreach ($this->getActiveAddons() as $slug) {
            $providerPath = $this->addonsPath.'/'.$slug.'/AddonServiceProvider.php';
            if (File::exists($providerPath)) {
                try {
                    require_once $providerPath;
                    $namespace = $this->resolveProviderClass($slug);
                    if (class_exists($namespace)) {
                        app()->register($namespace);
                    }
                } catch (\Exception $e) {
                    // Silently skip broken addons
                    Log::warning("Failed to load addon '{$slug}': ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Resolve the ServiceProvider class name from addon slug.
     */
    private function resolveProviderClass(string $slug): string
    {
        // Convention: addons/social-media/AddonServiceProvider.php → Addons\SocialMedia\AddonServiceProvider
        $namespace = str_replace('-', '', ucwords($slug, '-'));

        return "Addons\\{$namespace}\\AddonServiceProvider";
    }

    /**
     * Check if an addon's license requirement is met.
     */
    private function checkLicenseRequirement(array $config): bool
    {
        if (! license_verified()) {
            return false;
        }

        $required = $config['requires_license'] ?? 1;

        return get_license_type() >= $required;
    }
}
