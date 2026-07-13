<?php

namespace App\Http\Controllers\Admin\Appearance;

use App\Http\Controllers\Controller;
use App\Services\AddonLicenseService;
use App\Services\AddonService;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * Admin Addon Management Controller.
 */
class AddonController extends Controller
{
    public function __construct(
        private AddonService $addonService,
        private AddonLicenseService $addonLicenseService,
    ) {}

    /**
     * List all available addons.
     */
    public function addons()
    {
        $addons = $this->addonService->getAvailableAddons();

        // Enrich with license + update info for the Vue page
        $addons = array_map(function ($addon) {
            $slug = $addon['slug'];
            $addon['license'] = $this->addonLicenseService->getLicenseInfo($slug);
            $addon['envato_item_id'] = $addon['envato_item_id'] ?? null;
            $addon['logo_url'] = ! empty($addon['has_logo'])
                ? route('admin.addons.logo', ['slug' => $slug])
                : null;
            // Cached result of the scheduled license-server update check.
            $addon['update_available'] = (bool) addon_setting($slug, 'update_available', false);
            $addon['latest_version'] = addon_setting($slug, 'latest_version');
            $addon['update_changelog'] = addon_setting($slug, 'update_changelog');
            $addon['update_checked_at'] = addon_setting($slug, 'update_checked_at');
            return $addon;
        }, $addons);

        return Inertia::render('Admin/Appearance/Addons', [
            'addons' => $addons,
        ]);
    }

    /**
     * Check the license server for addon updates on demand (same check the daily
     * scheduler runs). Results are cached in addon settings and shown as badges.
     */
    public function checkAddonUpdates()
    {
        // Light, Inertia-friendly rate limit (6/min): a redirect, never JSON — the
        // custom `throttle` alias returns JSON which breaks Inertia requests.
        $key = 'addon-update-check:' . (auth('admin')->id() ?? request()->ip());
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 6)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($key);

            return back()->with('error', translate('Please wait :seconds seconds before checking for updates again.', ['seconds' => $seconds]));
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $results = collect($this->addonLicenseService->checkAllAddonUpdates());
        $available = $results->filter(fn ($r) => ! empty($r['update_available']))->count();
        $errored = $results->filter(fn ($r) => ! empty($r['error']))->count();

        if ($results->isEmpty()) {
            return back()->with('success', translate('No licensed addons to check for updates.'));
        }

        if ($available > 0) {
            return back()->with('success', translate(':count addon update(s) available.', ['count' => $available]));
        }

        if ($errored > 0) {
            return back()->with('error', translate('Could not reach the license server to check :count addon(s). Please try again later.', ['count' => $errored]));
        }

        return back()->with('success', translate('All addons are up to date.'));
    }

    /**
     * Serve addon logo from the addon directory when available.
     */
    public function addonLogo(string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            abort(404);
        }

        $logoPath = config('addons.path', base_path('addons')) . '/' . $slug . '/logo.png';

        if (! File::exists($logoPath)) {
            abort(404);
        }

        return response()->file($logoPath, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Activate an addon.
     */
    public function activateAddon(Request $request, string $slug)
    {
        if ($this->addonService->activate($slug)) {
            $this->logAddonAudit($request, 'addon_activated', $slug);

            return back()->with('success', translate('Addon :addon activated successfully.', ['addon' => $slug]));
        }

        return back()->with('error', translate('Failed to activate addon. Check license requirements.'));
    }

    /**
     * Deactivate an addon.
     */
    public function deactivateAddon(Request $request, string $slug)
    {
        $this->addonService->deactivate($slug);

        $this->logAddonAudit($request, 'addon_deactivated', $slug);

        return back()->with('success', translate('Addon :addon deactivated.', ['addon' => $slug]));
    }

    public function deleteAddon(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);

        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        if (! empty($config['is_active'])) {
            return back()->with('error', translate('Deactivate the addon before deleting it.'));
        }

        if (! $this->addonService->delete($slug)) {
            return back()->with('error', translate('Failed to delete addon.'));
        }

        return redirect()->route('admin.addons')->with('success', translate('Addon :addon deleted successfully.', ['addon' => $slug]));
    }

    /**
     * Get addon settings page.
     */
    public function addonSettings(string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        // Some addons ship a fully custom settings page served by their OWN controller
        // with bespoke props (e.g. public-knowledge-base passes `providers`). Rendering
        // that page through this generic renderer omits those props and crashes the Vue
        // page. Detect such a dedicated settings screen from the admin menu. Addons that
        // reuse this generic page keep `route === admin.addons.settings`, so they don't match.
        $customSettingsEntry = collect($config['admin_menu'] ?? [])->first(function ($entry) {
            $route = $entry['route'] ?? '';

            return $route !== ''
                && $route !== 'admin.addons.settings'
                && (($entry['label'] ?? '') === 'Settings' || str_ends_with($route, '.settings'));
        });

        // When that route is registered (addon active), the addon's controller owns the
        // page and supplies its own props — send the admin there.
        if ($customSettingsEntry && \Illuminate\Support\Facades\Route::has($customSettingsEntry['route'])) {
            return redirect()->route($customSettingsEntry['route'], $customSettingsEntry['route_params'] ?? []);
        }

        $settings = $this->addonService->getAddonSettings($slug);

        // Fetch available chat models for the AI Assistant model select.
        // Only show models from providers with at least one active, non-disabled API key.
        $configuredProviders = \App\Models\AiKey::available()->pluck('provider');

        $friendlyModelNames = config('ai.model_names', []);
        $friendlyProviderNames = config('ai.provider_names', []);

        $aiModels = \App\Models\AiModel::active()
            ->whereIn('type', ['chat'])
            ->whereIn('provider', $configuredProviders)
            ->orderBy('provider')
            ->orderBy('name')
            ->get(['slug', 'name', 'provider'])
            ->map(fn ($m) => [
                'value' => $m->slug,
                'label' => $friendlyModelNames[$m->slug] ?? $m->name,
                // Display name, e.g. "Google Gemini" — what the existing consumers render.
                'provider' => $friendlyProviderNames[$m->provider] ?? ucfirst($m->provider),
                // The raw slug ("google"). Added because `provider` above is a DISPLAY name,
                // so any screen that filters models by the provider an addon has configured
                // (a slug) matched nothing and showed an empty model list. Additive, so the
                // existing `provider` consumers are unaffected.
                'provider_slug' => $m->provider,
            ]);

        $rules = [];
        if ($slug === 'ai-assistant' && \Illuminate\Support\Facades\Schema::hasTable('ai_assistant_rules')) {
            $rules = \Addons\AiAssistant\Models\AiAssistantRule::orderBy('id', 'desc')->get();
        }

        $modes = [];
        if ($slug === 'ai-chatbot' && class_exists(\Addons\AiChatbot\Models\ChatbotMode::class) && \Illuminate\Support\Facades\Schema::hasTable('chatbot_modes')) {
            $modes = \Addons\AiChatbot\Models\ChatbotMode::active()->orderBy('sort_order')->get(['slug', 'name']);
        }

        // Normally render the addon's own Settings.vue when present. But if the addon
        // declares a dedicated custom settings screen (handled above) whose route is NOT
        // registered — i.e. the addon is inactive, so its controller/props aren't loaded —
        // that Vue page would render without its required props. Fall back to the generic
        // renderer, which works from the declared `settings` array alone.
        $addonSpecificPage = resource_path('js/Pages/Addons/' . $slug . '/Admin/Settings.vue');
        $addonOnDiskPage = base_path('addons/' . $slug . '/resources/js/Pages/Admin/Settings.vue');
        $page = ((file_exists($addonSpecificPage) || file_exists($addonOnDiskPage)) && ! $customSettingsEntry)
            ? 'Addons/' . $slug . '/Admin/Settings'
            : 'Admin/Appearance/AddonSettings';

        return Inertia::render($page, [
            'addon' => $config,
            'settings' => $settings,
            'aiModels' => $aiModels,
            'rules' => $rules,
            'modes' => $modes,
        ]);
    }

    public function saveAddonSettings(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        $data = $request->except('_token');

        // Loop through the fields and intercept file uploads
        foreach ($request->files->keys() as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                if ($file->isValid()) {
                    // Store first; delete the old file only after the new write succeeds.
                    $data[$key] = store_public_upload($file, 'assistant', addon_setting($slug, $key));
                }
            }
        }

        // Also check if any file field was explicitly set to null or cleared or needs clean relative path
        if (!empty($config['settings'])) {
            foreach ($config['settings'] as $setting) {
                if (($setting['type'] ?? '') === 'file' && ! $request->hasFile($setting['key'])) {
                    $rawVal = $request->input($setting['key']);
                    // Treat null OR empty string as "cleared" — a multipart submit sends
                    // an emptied file field as '' (never null), so without this an old
                    // upload would be orphaned on disk when the admin removes it.
                    if ($rawVal === null || $rawVal === '') {
                        $oldPath = addon_setting($slug, $setting['key']);
                        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                        $data[$setting['key']] = null;
                    } else {
                        $val = $request->input($setting['key']);
                        if (is_string($val)) {
                            if (str_starts_with($val, '/storage/')) {
                                $val = substr($val, 9);
                            } else {
                                $diskUrl = Storage::disk('public')->url('');
                                if (str_starts_with($val, $diskUrl)) {
                                    $val = str_replace($diskUrl, '', $val);
                                }
                            }
                            $data[$setting['key']] = $val;
                        }
                    }
                }
            }
        }

        $this->addonService->saveAddonSettings($slug, $data);

        return back()->with('success', translate('Addon settings saved.'));
    }

    /**
     * Install addon from uploaded zip.
     */
    public function installAddon(Request $request)
    {
        // CRITICAL SECURITY: Explicit authorization check for addon installation
        abort_unless(auth('admin')->user()?->hasAnyPermission(['addons.manage', 'settings.manage']), 403);

        $request->validate([
            'addon_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $file = $request->file('addon_zip');
        $zip = new ZipArchive;
        $openResult = $zip->open($file->getRealPath());

        if ($openResult !== true) {
            return back()->with('error', translate('Failed to open the uploaded zip file.'));
        }

        $addonsPath = config('addons.path', base_path('addons'));

        // Find the root slug directory inside the zip
        $slug = null;
        $rootDirs = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $parts = explode('/', trim($name, '/'));
            if (count($parts) > 0 && $parts[0] !== '' && $parts[0] !== '__MACOSX') {
                $rootDirs[$parts[0]] = true;
            }
        }

        $slug = count($rootDirs) === 1 ? array_key_first($rootDirs) : null;

        if (! $slug) {
            $zip->close();
            return back()->with('error', translate('Invalid addon zip structure. Expected a single root directory.'));
        }

        // CRITICAL SECURITY FIX: Validate all paths to prevent Zip Slip (Path Traversal)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // 1. Reject absolute paths
            if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                $zip->close();
                return back()->with('error', translate('Invalid addon zip: contains absolute paths.'));
            }

            // 2. Reject directory traversal attempts
            if (str_contains($name, '../') || str_contains($name, '..\\')) {
                $zip->close();
                return back()->with('error', translate('Invalid addon zip: contains directory traversal attempts.'));
            }

            // 3. Ensure the file belongs to the expected root directory
            if (! str_starts_with($name, $slug . '/') && $name !== $slug . '/') {
                $zip->close();
                return back()->with('error', translate('Invalid addon zip: files must be inside the root directory.'));
            }
        }

        // Check if the slug has addon.json or settings.json
        $hasManifest = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, $slug . '/') && basename($name) === 'addon.json') {
                $hasManifest = true;
                break;
            }
            if (str_starts_with($name, $slug . '/') && basename($name) === 'settings.json') {
                $hasManifest = true;
                break;
            }
        }

        if (! $hasManifest) {
            $zip->close();
            return back()->with('error', translate('Invalid addon zip — no addon.json or settings.json found inside.'));
        }

        $destPath = $addonsPath . '/' . $slug;

        // CRITICAL SECURITY FIX: Extract to a secure temporary directory first
        $tempDir = sys_get_temp_dir() . '/addon_upload_' . uniqid();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $sourceDir = $tempDir . '/' . $slug;

        if (! is_dir($sourceDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($tempDir);
            return back()->with('error', translate('Invalid addon zip structure after extraction.'));
        }

        // Ensure destination parent exists
        if (! is_dir($addonsPath)) {
            mkdir($addonsPath, 0755, true);
        }

        // Copy the validated directory to the final destination safely
        \Illuminate\Support\Facades\File::copyDirectory($sourceDir, $destPath);

        // Clean up the temporary directory
        \Illuminate\Support\Facades\File::deleteDirectory($tempDir);

        // Run sync to register the new addon
        $this->addonService->syncFromFilesystem();

        // Run addon migrations
        $this->addonService->migrateAddon($slug);

        // The uploaded package is now the installed version — clear any pending
        // "update available" flag so the badge disappears immediately.
        $newVersion = $this->addonService->getAddonConfig($slug)['version'] ?? null;
        addon_setting_set($slug, 'update_available', false, 'boolean');
        if ($newVersion) {
            addon_setting_set($slug, 'latest_version', $newVersion);
        }

        return back()->with('success', translate('Addon installed successfully. You can now activate it below.'));
    }

    /**
     * Bulk activate addons.
     */
    public function bulkActivate(Request $request)
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'min:1'],
            'slugs.*' => ['string'],
        ]);

        $activated = 0;
        foreach ($validated['slugs'] as $slug) {
            if ($this->addonService->activate($slug)) {
                $activated++;
                $this->logAddonAudit($request, 'addon_activated', $slug);
            }
        }

        return back()->with('success', translate(':count addon(s) activated.', ['count' => $activated]));
    }

    /**
     * Bulk deactivate addons.
     */
    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'min:1'],
            'slugs.*' => ['string'],
        ]);

        $deactivated = 0;
        foreach ($validated['slugs'] as $slug) {
            $this->addonService->deactivate($slug);
            $deactivated++;
            $this->logAddonAudit($request, 'addon_deactivated', $slug);
        }

        return back()->with('success', translate(':count addon(s) deactivated.', ['count' => $deactivated]));
    }

    /**
     * Verify addon license — Envato purchase code validation.
     * Called BEFORE activateAddon for first-time addons.
     */
    public function verifyAddonLicense(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return response()->json(['error' => translate('Addon not found.')], 404);
        }

        $requiresLicense = $config['requires_license'] ?? false;
        if (! $requiresLicense) {
            return response()->json(['error' => translate('This addon does not require a separate license.')], 422);
        }

        $request->validate([
            'purchase_code' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->addonLicenseService->verify(
            $slug,
            $request->input('purchase_code')
        );

        if (! $result->valid) {
            return response()->json([
                'error' => $result->error,
                'error_code' => $result->errorCode,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'buyer' => $result->buyer,
            'type' => $result->type,
            'message' => translate('License verified for :buyer', ['buyer' => $result->buyer]),
        ]);
    }

    private function logAddonAudit(Request $request, string $action, string $slug): void
    {
        try {
            DB::table('admin_audit_logs')->insert([
                'admin_id' => auth('admin')->id(),
                'action' => $action,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
                'payload' => json_encode([
                    'addon_slug' => $slug,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
