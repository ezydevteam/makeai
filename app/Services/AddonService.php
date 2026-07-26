<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\AddonSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AddonService
{
    private string $addonsPath;

    /**
     * Namespaces already handed to the Composer autoloader this request, so repeated calls
     * (boot, then an activation in the same request) do not re-register the same prefix.
     *
     * @var array<string,true>
     */
    private static array $autoloadRegistered = [];

    public function __construct()
    {
        $this->addonsPath = config('addons.path', base_path('addons'));
    }

    // ─── autoloading ────────────────────────────────────────

    /**
     * Teach Composer's autoloader where every installed addon's classes live.
     *
     * WHY THIS EXISTS. Addons are sold and shipped separately from the core package, which
     * means a buyer can install an addon that did not exist when their copy of MakeAI was
     * built. Their composer.json cannot possibly name it, and `composer dump-autoload` is
     * not available to them — most run on shared hosting with no shell at all. Before this,
     * an addon's namespace had to be hardcoded into the core composer.json ahead of time, so
     * shipping a genuinely new addon to an existing install was impossible: the service
     * provider loaded (AddonService require_once's it directly), then the first route that
     * resolved one of its controllers died with a class-not-found.
     *
     * Registering at runtime removes that ceiling entirely — an addon works the moment its
     * files are on disk, with no composer step and no core release.
     *
     * Composer's ClassLoader is used rather than a hand-written spl_autoload_register (the
     * approach taken for themes in AppServiceProvider) for one specific reason: addons put
     * seeders and factories OUTSIDE the app/ PSR-4 root, so the three prefixes below overlap.
     * Composer resolves the longest matching prefix first, which is exactly the precedence
     * those layouts depend on; reimplementing that by hand is avoidable risk.
     *
     * Deliberately covers every addon present on disk, not just active ones: activation
     * decides whether an addon's provider RUNS, but installation, licence checks and the
     * admin listing all touch addon classes before that point. An unused mapping costs
     * nothing — the autoloader only hits the disk when a matching class is actually resolved.
     *
     * Note AddonServiceProvider.php itself is NOT covered here. It sits at the addon root
     * rather than under app/, so it falls outside the PSR-4 root by design and continues to
     * be require_once'd by registerActiveAddons().
     */
    public function registerAddonAutoloading(): void
    {
        if (! File::isDirectory($this->addonsPath)) {
            return;
        }

        $loader = $this->composerLoader();

        if (! $loader) {
            return;
        }

        foreach (File::directories($this->addonsPath) as $dir) {
            $slug = basename($dir);

            // addon.json is what makes a directory an addon; without it there is nothing to
            // map and the folder is something else that happens to live here.
            if (! File::exists($dir . '/addon.json')) {
                continue;
            }

            $namespace = 'Addons\\' . $this->studlySlug($slug) . '\\';

            if (isset(self::$autoloadRegistered[$namespace])) {
                continue;
            }

            self::$autoloadRegistered[$namespace] = true;

            foreach ([
                $namespace => $dir . '/app/',
                $namespace . 'Database\\Seeders\\' => $dir . '/database/seeders/',
                $namespace . 'Database\\Factories\\' => $dir . '/database/factories/',
            ] as $prefix => $path) {
                if (File::isDirectory($path)) {
                    $loader->addPsr4($prefix, $path);
                }
            }
        }
    }

    /**
     * The live Composer ClassLoader.
     *
     * Requiring vendor/autoload.php again is cheap and side-effect free — Composer's
     * generated init class memoises the loader and hands back the same instance that is
     * already registered, so this adds prefixes to the running autoloader rather than
     * creating a second one.
     */
    private function composerLoader(): ?\Composer\Autoload\ClassLoader
    {
        static $loader = null;

        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            return $loader;
        }

        $autoload = base_path('vendor/autoload.php');

        if (! is_file($autoload)) {
            return null;
        }

        $resolved = require $autoload;

        return $loader = $resolved instanceof \Composer\Autoload\ClassLoader ? $resolved : null;
    }

    /**
     * Slug to namespace segment: `ai-image-pro` → `AiImagePro`. Must stay in step with
     * resolveProviderClass(), which builds the provider FQCN the same way.
     */
    private function studlySlug(string $slug): string
    {
        return str_replace('-', '', ucwords($slug, '-'));
    }

    // ─── filesystem sync ────────────────────────────────────

    /**
     * Scan the addons/ directory and upsert addon.json (primary) or settings.json (fallback)
     * into the addons table. Called once on boot.
     */
    public function syncFromFilesystem(): void
    {
        if (! File::isDirectory($this->addonsPath)) {
            return;
        }

        $scannedSlugs = [];

        foreach (File::directories($this->addonsPath) as $dir) {
            $slug = basename($dir);
            $scannedSlugs[] = $slug;

            $manifest = $this->readManifest($slug);
            if (! $manifest) {
                continue;
            }

            $addon = Addon::where('slug', $slug)->first();

            Addon::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $manifest['name'] ?? $slug,
                    'version' => $manifest['version'] ?? '0.0.0',
                    'manifest' => $manifest,
                    'installed_at' => $addon?->installed_at ?? ($addon ? null : now()),
                ]
            );
        }

        // Mark addons no longer on disk as inactive
        if ($scannedSlugs) {
            Addon::whereNotIn('slug', $scannedSlugs)->update(['is_active' => false]);
        }
    }

    /**
     * Read an addon's manifest. addon.json takes priority, settings.json as fallback.
     */
    public function readManifest(string $slug): ?array
    {
        $addonJson = $this->addonsPath . '/' . $slug . '/addon.json';
        $settingsJson = $this->addonsPath . '/' . $slug . '/settings.json';

        $file = File::exists($addonJson) ? $addonJson : (File::exists($settingsJson) ? $settingsJson : null);
        if (! $file) {
            return null;
        }

        $manifest = json_decode(File::get($file), true);
        if (! $manifest || ! isset($manifest['slug'])) {
            $manifest['slug'] = $slug;
        }

        return $manifest;
    }

    // ─── available / active ──────────────────────────────────

    /**
     * Get all available addons (from DB, synced from filesystem).
     */
    public function getAvailableAddons(): array
    {
        return Addon::all()->map(function (Addon $addon) {
            $manifest = $addon->manifest ?? [];
            $manifest['slug'] = $addon->slug;
            $manifest['name'] = $addon->name;
            $manifest['version'] = $addon->version;
            $manifest['is_active'] = $addon->is_active;
            $manifest['license_ok'] = $this->checkLicenseRequirement($manifest);
            $manifest['envato_item_id'] = $manifest['envato_item_id'] ?? null;
            $manifest['has_logo'] = $this->hasLogo($addon->slug);
            return $manifest;
        })->toArray();
    }

    /**
     * Get a specific addon's config from DB.
     */
    public function getAddonConfig(string $slug): ?array
    {
        $addon = Addon::where('slug', $slug)->first();
        if (! $addon) {
            return null;
        }

        $manifest = $addon->manifest ?? [];
        $manifest['slug'] = $addon->slug;
        $manifest['name'] = $addon->name;
        $manifest['version'] = $addon->version;
        $manifest['is_active'] = $addon->is_active;
        $manifest['license_ok'] = $this->checkLicenseRequirement($manifest);
        $manifest['envato_item_id'] = $manifest['envato_item_id'] ?? null;
        $manifest['has_logo'] = $this->hasLogo($addon->slug);
        $manifest['installed_at'] = $addon->installed_at?->toDateString();
        $manifest['activated_at'] = $addon->activated_at?->toDateString();

        return $manifest;
    }

    private function hasLogo(string $slug): bool
    {
        return File::exists($this->addonsPath . '/' . $slug . '/logo.png');
    }

    /**
     * Check if an addon is active.
     */
    public function isActive(string $slug): bool
    {
        return Addon::where('slug', $slug)->where('is_active', true)->exists();
    }

    // ─── permissions ────────────────────────────────────────

    /**
     * Create the admin permissions an addon declares in its addon.json "permissions" block.
     *
     * Addon routes and admin menus gate themselves with `admin.permission:addon.*` slugs, but
     * nothing used to create those rows — so for every addon except ai-image-pro (which
     * carried its own bespoke seeder) the slug did not exist, `hasPermission()` returned false
     * for any non-super-admin, and the addon's admin pages 403'd with no way to grant access
     * (the permission wasn't on the Roles & Permissions screen to tick). This is that missing
     * step, run on activation.
     *
     * Idempotent: existing rows are reused, so re-activating never duplicates or resets them.
     */
    public function syncPermissions(string $slug): void
    {
        if (! Schema::hasTable('admin_permissions')) {
            return;
        }

        $addon = Addon::where('slug', $slug)->first();

        if (! $addon) {
            return;
        }

        $declared = $addon->manifest['permissions'] ?? [];

        if (! is_array($declared) || $declared === []) {
            return;
        }

        $ids = [];

        foreach ($declared as $permission) {
            if (empty($permission['slug'])) {
                continue;
            }

            $ids[] = \App\Models\AdminPermission::firstOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'] ?? $permission['slug'],
                    'group' => $permission['group'] ?? ($addon->name ?: $slug),
                ],
            )->id;
        }

        // The Super Admin bypasses permission checks in code, but keep the pivot complete so
        // the role reads correctly on screen. Never detach anything an operator granted.
        if ($ids !== [] && Schema::hasTable('admin_roles')) {
            \App\Models\AdminRole::where('slug', config('auth.providers.admins.super_admin_slug', 'super-admin'))
                ->first()
                ?->permissions()
                ->syncWithoutDetaching($ids);
        }
    }

    /**
     * Permission slug => owning addon slug, for every addon on record (active or not).
     *
     * @return array<string, string>
     */
    public function permissionOwners(): array
    {
        $owners = [];

        foreach (Addon::all() as $addon) {
            foreach ($addon->manifest['permissions'] ?? [] as $permission) {
                if (! empty($permission['slug'])) {
                    $owners[$permission['slug']] = $addon->slug;
                }
            }
        }

        return $owners;
    }

    /**
     * Permission slugs owned by an addon that is NOT currently active.
     *
     * Rows are deliberately kept when an addon is deactivated (so a role's grants survive a
     * deactivate/reactivate cycle), which means the Roles & Permissions screen must hide them
     * instead — otherwise an inactive addon's permissions stay on the page forever.
     *
     * @return string[]
     */
    public function inactivePermissionSlugs(): array
    {
        $activeSlugs = $this->getActiveAddons();

        return array_keys(array_filter(
            $this->permissionOwners(),
            static fn (string $addonSlug): bool => ! in_array($addonSlug, $activeSlugs, true),
        ));
    }

    /**
     * Get active addon slugs.
     */
    public function getActiveAddons(): array
    {
        // Migrate legacy data on first call if needed
        $this->migrateLegacyActiveAddons();

        return Addon::where('is_active', true)->pluck('slug')->toArray();
    }

    /**
     * One-time migration: if addons table exists but has no active entries,
     * read from legacy settings('active_addons') and mark those as active.
     */
    private function migrateLegacyActiveAddons(): void
    {
        static $migrated = false;
        if ($migrated) return;
        $migrated = true;

        if (Addon::where('is_active', true)->exists()) return;

        $legacy = settings('active_addons', '[]');
        if (is_string($legacy)) $legacy = json_decode($legacy, true) ?? [];
        if (empty($legacy)) return;

        foreach ($legacy as $slug) {
            Addon::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'version' => '0.0.0',
                    'is_active' => true,
                    'installed_at' => now(),
                    'activated_at' => now(),
                ]
            );
        }
    }

    // ─── activation / deactivation ───────────────────────────

    public function activate(string $slug): bool
    {
        // A freshly uploaded addon is activated in the same request it was extracted in, so
        // its namespace was not on disk when boot ran. Map it now or the migrations and
        // seeders below cannot resolve their own classes.
        $this->registerAddonAutoloading();

        $manifest = $this->getAddonConfig($slug);
        if (! $manifest) {
            return false;
        }

        if (! $this->checkLicenseRequirement($manifest)) {
            return false;
        }

        // GATE: addons with envato_item_id must have a verified addon license
        if (! $this->checkAddonLicense($slug, $manifest)) {
            return false;
        }

        $addon = Addon::where('slug', $slug)->first();
        if (! $addon) return false;

        $addon->update([
            'is_active' => true,
            'activated_at' => $addon->activated_at ?? now(),
        ]);

        Cache::forget('active_addons_list');

        // Bundled addons ship in addons/ (not installed via zip), so their migrations
        // and seeders never run unless we do it here on activation. migrateAddon() only
        // applies pending migrations and addon seeders are written idempotently, so this
        // is safe to run on every (re)activation.
        $this->installAddonSchema($slug);

        // Create the permissions the addon's routes and menus gate themselves with, so an
        // operator can actually grant them on Roles & Permissions. Idempotent.
        $this->syncPermissions($slug);

        return true;
    }

    public function deactivate(string $slug): bool
    {
        Addon::where('slug', $slug)->update([
            'is_active' => false,
        ]);

        Cache::forget('active_addons_list');

        return true;
    }

    public function delete(string $slug): bool
    {
        $addon = Addon::where('slug', $slug)->first();

        if (! $addon || $addon->is_active) {
            return false;
        }

        $this->dropAddonTables($slug);

        AddonSetting::forget($slug);

        $addon->delete();

        File::deleteDirectory($this->addonsPath . '/' . $slug);

        Cache::forget('active_addons_list');

        return true;
    }

    // ─── settings ────────────────────────────────────────────

    public function getAddonSettings(string $slug): array
    {
        $config = $this->getAddonConfig($slug);
        if (! $config || empty($config['settings'])) {
            return [];
        }

        $settings = $config['settings'];

        if ($slug === 'ai-chatbot') {
            // Remove the hardcoded Pro Tier settings
            $settings = array_filter($settings, function ($s) {
                return ($s['group'] ?? '') !== 'pro_tier';
            });

            // Add new 5-hour, weekly, monthly limit settings to the Free Tier
            $settings[] = [
                'key' => 'free_max_messages_5h',
                'type' => 'integer',
                'label' => 'Max Messages per 5 Hours',
                'default' => 0,
                'group' => 'free_tier',
                'description' => '0 for unlimited.'
            ];
            $settings[] = [
                'key' => 'free_max_messages_weekly',
                'type' => 'integer',
                'label' => 'Max Messages per Week',
                'default' => 0,
                'group' => 'free_tier',
                'description' => '0 for unlimited.'
            ];
            $settings[] = [
                'key' => 'free_max_messages_monthly',
                'type' => 'integer',
                'label' => 'Max Messages per Month',
                'default' => 0,
                'group' => 'free_tier',
                'description' => '0 for unlimited.'
            ];

            // Dynamically load active premium plans and create settings for each
            if (class_exists(\App\Models\Plan::class)) {
                $plans = \App\Models\Plan::active()->where('is_free', false)->get();
                foreach ($plans as $plan) {
                    $groupName = "plan_{$plan->slug}_tier";

                    $settings[] = [
                        'key' => "plan_{$plan->slug}_credits_per_message",
                        'type' => 'string',
                        'label' => "Credits per Message",
                        'default' => '0',
                        'group' => $groupName
                    ];
                    $settings[] = [
                        'key' => "plan_{$plan->slug}_max_tokens",
                        'type' => 'integer',
                        'label' => "Max Tokens per Request",
                        'default' => 4096,
                        'group' => $groupName
                    ];
                    $settings[] = [
                        'key' => "plan_{$plan->slug}_max_file_size_mb",
                        'type' => 'integer',
                        'label' => "Max File Upload Size (MB)",
                        'default' => 50,
                        'group' => $groupName
                    ];
                    $settings[] = [
                        'key' => "plan_{$plan->slug}_max_chat_history",
                        'type' => 'integer',
                        'label' => "Max Chat History Messages",
                        'default' => 0,
                        'group' => $groupName,
                        'description' => '0 for unlimited.'
                    ];
                    $settings[] = [
                        'key' => "plan_{$plan->slug}_max_messages_5h",
                        'type' => 'integer',
                        'label' => "Max Messages per 5 Hours",
                        'default' => 0,
                        'group' => $groupName,
                        'description' => '0 for unlimited.'
                    ];
                    $settings[] = [
                        'key' => "plan_{$plan->slug}_max_messages_weekly",
                        'type' => 'integer',
                        'label' => "Max Messages per Week",
                        'default' => 0,
                        'group' => $groupName,
                        'description' => '0 for unlimited.'
                    ];
                    $settings[] = [
                        'key' => "plan_{$plan->slug}_max_messages_monthly",
                        'type' => 'integer',
                        'label' => "Max Messages per Month",
                        'default' => 0,
                        'group' => $groupName,
                        'description' => '0 for unlimited.'
                    ];
                }
            }
        }

        return array_values(array_map(function ($setting) use ($slug) {
            $setting['value'] = AddonSetting::get($slug, $setting['key'], $setting['default'] ?? null);

            return $setting;
        }, $settings));
    }

    public function saveAddonSettings(string $slug, array $values): void
    {
        $config = $this->getAddonConfig($slug);
        $settings = $config['settings'] ?? [];

        if ($slug === 'ai-chatbot') {
            $settings = array_filter($settings, function ($s) {
                return ($s['group'] ?? '') !== 'pro_tier';
            });

            $settings[] = ['key' => 'free_max_messages_5h', 'type' => 'integer'];
            $settings[] = ['key' => 'free_max_messages_weekly', 'type' => 'integer'];
            $settings[] = ['key' => 'free_max_messages_monthly', 'type' => 'integer'];

            if (class_exists(\App\Models\Plan::class)) {
                $plans = \App\Models\Plan::active()->where('is_free', false)->get();
                foreach ($plans as $plan) {
                    $settings[] = ['key' => "plan_{$plan->slug}_credits_per_message", 'type' => 'string'];
                    $settings[] = ['key' => "plan_{$plan->slug}_max_tokens", 'type' => 'integer'];
                    $settings[] = ['key' => "plan_{$plan->slug}_max_file_size_mb", 'type' => 'integer'];
                    $settings[] = ['key' => "plan_{$plan->slug}_max_chat_history", 'type' => 'integer'];
                    $settings[] = ['key' => "plan_{$plan->slug}_max_messages_5h", 'type' => 'integer'];
                    $settings[] = ['key' => "plan_{$plan->slug}_max_messages_weekly", 'type' => 'integer'];
                    $settings[] = ['key' => "plan_{$plan->slug}_max_messages_monthly", 'type' => 'integer'];
                }
            }
        }

        $settingsMap = collect($settings)->keyBy('key');

        foreach ($values as $key => $value) {
            AddonSetting::set(
                $slug,
                $key,
                $value,
                $this->storableType($settingsMap[$key]['type'] ?? 'string'),
            );
        }
    }

    /**
     * Map a manifest setting type onto the settings.type enum
     * (string|boolean|integer|json|encrypted). Manifest types outside that set —
     * 'file', 'select', 'color', 'icon', 'textarea' — must be persisted as a
     * compatible enum value or MySQL rejects the row. Each of those only ever holds
     * a scalar string and casts as a plain string on read, so 'string' is the correct
     * storage type; the file-upload and widget handling key off the addon.json
     * manifest type, not this column.
     */
    private function storableType(string $manifestType): string
    {
        $storable = ['string', 'boolean', 'integer', 'json', 'encrypted'];

        return in_array($manifestType, $storable, true) ? $manifestType : 'string';
    }

    // ─── menu items ──────────────────────────────────────────

    /**
     * Collect admin_menu entries from all active addons for Inertia sharing.
     */
    public function getActiveAddonMenuItems(): array
    {
        $items = [];

        foreach (Addon::where('is_active', true)->get() as $addon) {
            $menuEntries = $addon->manifest['admin_menu'] ?? [];
            if (! is_array($menuEntries)) continue;

            foreach ($menuEntries as $entry) {
                $items[] = [
                    'slug' => $addon->slug,
                    'addon_name' => $addon->manifest['name'] ?? $addon->name,
                    'label' => $entry['label'] ?? $addon->name,
                    'route' => $entry['route'] ?? '',
                    'route_params' => $entry['route_params'] ?? [],
                    'route_pattern' => $entry['route_pattern'] ?? ($entry['route'] ?? ''),
                    'icon' => $entry['icon'] ?? 'ti ti-puzzle',
                    'permission' => $entry['permission'] ?? 'addons.view',
                ];
            }
        }

        return $items;
    }

    // ─── registration ────────────────────────────────────────

    /**
     * Register all active addons' ServiceProviders.
     * Called from AppServiceProvider::boot().
     */
    public function registerActiveAddons(): void
    {
        // Must precede the provider loop: a provider's register()/boot() resolves its own
        // services, and those only load once their namespace is mapped.
        $this->registerAddonAutoloading();

        foreach ($this->getActiveAddons() as $slug) {
            $providerPath = $this->addonsPath . '/' . $slug . '/AddonServiceProvider.php';
            if (File::exists($providerPath)) {
                try {
                    require_once $providerPath;
                    $namespace = $this->resolveProviderClass($slug);
                    if (class_exists($namespace)) {
                        app()->register($namespace);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to load addon '{$slug}': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Resolve the ServiceProvider class name from addon slug.
     */
    private function resolveProviderClass(string $slug): string
    {
        $namespace = str_replace('-', '', ucwords($slug, '-'));

        return "Addons\\{$namespace}\\AddonServiceProvider";
    }

    // ─── migration ───────────────────────────────────────────

    /**
     * Run migrations for a specific addon.
     */
    public function migrateAddon(string $slug): void
    {
        $migrationsPath = $this->addonsPath . '/' . $slug . '/database/migrations';
        if (! File::isDirectory($migrationsPath)) {
            return;
        }

        try {
            \Artisan::call('migrate', [
                '--path' => 'addons/' . $slug . '/database/migrations',
                '--force' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to migrate addon '{$slug}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bring an addon's schema + reference data up to date: run its migrations, then its
     * seeders. Used by both the zip-install path and activation of bundled addons so a
     * fresh Envato install ends up with the addon's tables created AND seeded. Best-effort:
     * failures are logged, not thrown, so a seeding hiccup doesn't abort activation.
     */
    public function installAddonSchema(string $slug): void
    {
        try {
            $this->migrateAddon($slug);
        } catch (\Throwable $e) {
            // migrateAddon already logged; keep going so the operator can retry.
        }

        $this->seedAddon($slug);
        $this->seedDefaultSettings($slug);
    }

    /**
     * Persist each manifest-declared setting's default value if it has never been saved.
     * Addon settings are only written when the operator first hits Save, so before that
     * `addon_setting()` calls fall through to whatever hardcoded fallback the consuming
     * code passed — which can disagree with the advertised default (e.g. guest max tokens
     * declared 1000 but read with a 4096 fallback). Seeding the declared defaults on
     * activation makes runtime behavior match the manifest from the first request, and
     * makes the admin settings form show real values immediately. Never clobbers a value
     * the operator has already set.
     */
    public function seedDefaultSettings(string $slug): void
    {
        foreach ($this->getAddonSettings($slug) as $setting) {
            if (! isset($setting['key'])) {
                continue;
            }

            // Ask the table, not a read-with-default: a read can't distinguish a stored
            // value from the fallback the caller passed, so it would report unsaved keys
            // as persisted.
            if (AddonSetting::isPersisted($slug, $setting['key'])) {
                continue; // respect the operator's value
            }

            AddonSetting::set(
                $slug,
                $setting['key'],
                $setting['default'] ?? null,
                $this->storableType($setting['type'] ?? 'string'),
            );
        }
    }

    /**
     * Run every seeder shipped in an addon's database/seeders directory. Addon seeders
     * must be idempotent (e.g. updateOrCreate + a Schema::hasTable guard).
     *
     * The require_once before class_exists is now a fallback rather than the mechanism:
     * registerAddonAutoloading() maps Addons\<Name>\Database\Seeders\ for every installed
     * addon, so these normally resolve on their own. It is kept for seeders that do not
     * follow the conventional namespace — the file name is authoritative here, the
     * namespace only a guess.
     */
    private function seedAddon(string $slug): void
    {
        $seedersPath = $this->addonsPath . '/' . $slug . '/database/seeders';
        if (! File::isDirectory($seedersPath)) {
            return;
        }

        $namespace = str_replace('-', '', ucwords($slug, '-'));

        // Prefer a single addon-level DatabaseSeeder if present (it orchestrates the rest);
        // otherwise run each discovered seeder file.
        $files = collect(File::files($seedersPath))
            ->filter(fn ($f) => $f->getExtension() === 'php');

        $dbSeeder = $files->first(fn ($f) => $f->getFilenameWithoutExtension() === 'DatabaseSeeder');
        $targets = $dbSeeder ? collect([$dbSeeder]) : $files;

        foreach ($targets as $file) {
            $class = "Addons\\{$namespace}\\Database\\Seeders\\" . $file->getFilenameWithoutExtension();

            try {
                if (! class_exists($class)) {
                    require_once $file->getPathname();
                }
                if (! class_exists($class)) {
                    continue;
                }

                \Artisan::call('db:seed', [
                    '--class' => $class,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Failed to seed addon '{$slug}' with {$class}: " . $e->getMessage());
            }
        }
    }

    // ─── helpers ─────────────────────────────────────────────

    private function checkLicenseRequirement(array $config): bool
    {
        if (! license_verified()) {
            return false;
        }

        $required = $config['requires_license'] ?? 1;

        return get_license_type() >= $required;
    }

    /**
     * GATE: addons sold as their own Envato item must have a verified addon license.
     *
     * Keyed on `envato_item_id`, NOT `requires_license`. The two mean different things
     * and conflating them made every bundled addon impossible to activate:
     *
     *   requires_license → the minimum main-app license TYPE (1 = Regular, 2 = Extended).
     *                      Enforced in checkLicenseRequirement() as get_license_type() >= n.
     *   envato_item_id   → this addon is a separate marketplace purchase, so it needs its
     *                      own purchase code registered in `addon_licenses`.
     *
     * A bundled addon carries no item id, so it ships with the app and needs no second
     * code — only the main license, which checkLicenseRequirement() has already verified.
     */
    private function checkAddonLicense(string $slug, array $manifest): bool
    {
        if (empty($manifest['envato_item_id'])) {
            return true; // bundled with the app — no separate purchase code
        }

        return app(\App\Services\AddonLicenseService::class)->isLicensed($slug);
    }

    private function dropAddonTables(string $slug): void
    {
        $migrationsPath = $this->addonsPath . '/' . $slug . '/database/migrations';

        if (! File::isDirectory($migrationsPath)) {
            return;
        }

        $tables = [];

        foreach (File::files($migrationsPath) as $file) {
            $contents = File::get($file->getPathname());

            if (preg_match_all("/Schema::create\\(['\"]([^'\"]+)['\"]/", $contents, $matches)) {
                foreach ($matches[1] as $tableName) {
                    $tables[] = $tableName;
                }
            }
        }

        $tables = array_values(array_unique($tables));

        foreach (array_reverse($tables) as $table) {
            Schema::dropIfExists($table);
        }
    }
}
