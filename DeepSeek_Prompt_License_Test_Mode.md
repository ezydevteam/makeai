# DeepSeek Prompt — License Test Mode (Pre-Approval Testing on Live Server)

## Context

MakeAI item is not yet approved/published on CodeCanyon, so no real Envato purchase
code exists yet to test the License Activation step of the installer (PART 03 §1.2,
§1.3 step 4) on a live server. This prompt adds a **gated test mode** to
`LicenseService` that:

1. Lets the developer (Shehab) fully test the license-gated flows (`isProAvailable()`,
   subscription UI, admin license screens) RIGHT NOW using fake purchase codes —
   without calling the Envato API.
2. Leaves the real Envato API verification path fully implemented and ready — once
   the item is approved and a real purchase code exists, switching to production
   is just flipping one `.env` flag (`LICENSE_TEST_MODE=false`), no code changes.
3. Is hard-blocked from ever activating in a real buyer's production environment
   by accident — multiple independent safety checks (see §4).

Per master prompt §1.2 "No offline bypass possible — API call is mandatory on first
install" — this remains true for `LICENSE_TEST_MODE=false` (the default and the
state shipped in the distribution zip per PART 68). Test mode is an explicit
developer-only escape hatch, never present in the shipped `.env.example`.

---

## 1. `.env` additions

Add to **developer's local/staging `.env` only** (NOT `.env.example`, NOT the
distribution zip's `.env.example` per PART 68 §68.6):

```env
LICENSE_TEST_MODE=true
```

Add to `.env.example` (shipped to buyers) as a commented-out, documented entry so
the variable exists but defaults safely off:

```env
# Developer-only: enables fake purchase codes for pre-release testing.
# MUST remain false (or absent) in any real installation. Leave commented out.
# LICENSE_TEST_MODE=false
```

Add to `config/app.php`:

```php
'license_test_mode' => env('LICENSE_TEST_MODE', false),
```

---

## 2. `app/Services/LicenseService.php`

Implement (or update if it already exists) per master prompt §1.2, with the test
mode branch added at the top of `verify()`:

```php
<?php

namespace App\Services;

use App\DataObjects\LicenseResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Fake purchase codes recognized only when LICENSE_TEST_MODE=true.
     * These never reach the Envato API.
     */
    private const TEST_CODES = [
        'TEST-LICENSE-0000-REGULAR'  => 1, // regular license
        'TEST-LICENSE-0000-EXTENDED' => 2, // extended license
    ];

    public function verify(string $purchaseCode): LicenseResult
    {
        if ($this->testModeActive() && array_key_exists($purchaseCode, self::TEST_CODES)) {
            return $this->buildTestResult($purchaseCode);
        }

        return $this->verifyWithEnvato($purchaseCode);
    }

    /**
     * Real Envato verification — unchanged, always present, always the
     * production path. This is what runs when LICENSE_TEST_MODE=false
     * (the default, and the state shipped to buyers).
     */
    private function verifyWithEnvato(string $purchaseCode): LicenseResult
    {
        $response = Http::withToken(config('services.envato.personal_token'))
            ->get('https://api.envato.com/v3/market/author/sale', [
                'code' => $purchaseCode,
            ]);

        if ($response->failed()) {
            Log::warning('Envato license verification failed', [
                'status' => $response->status(),
            ]);

            return new LicenseResult(
                valid: false,
                type: 0,
                buyer: null,
                expiresAt: null,
            );
        }

        $data = $response->json();

        // item_id check: must match this product's Envato item ID
        $expectedItemId = config('services.envato.item_id');
        if ((string) ($data['item']['id'] ?? null) !== (string) $expectedItemId) {
            return new LicenseResult(valid: false, type: 0, buyer: null, expiresAt: null);
        }

        $licenseType = match ($data['license'] ?? null) {
            'Regular License'  => 1,
            'Extended License' => 2,
            default            => 0,
        };

        $result = new LicenseResult(
            valid: $licenseType > 0,
            type: $licenseType,
            buyer: $data['buyer'] ?? null,
            expiresAt: null, // Envato licenses don't expire; re-verify weekly instead
        );

        $this->storeLicenseResult($purchaseCode, $result);

        return $result;
    }

    /**
     * Test-mode result builder. Mirrors the shape of a real Envato response
     * so downstream code (storeLicenseResult, getLicenseType, isExtended,
     * isProAvailable) behaves identically regardless of source.
     */
    private function buildTestResult(string $purchaseCode): LicenseResult
    {
        $type = self::TEST_CODES[$purchaseCode];

        $result = new LicenseResult(
            valid: true,
            type: $type,
            buyer: 'test-buyer',
            expiresAt: null,
        );

        Log::info('License verified via TEST MODE', [
            'purchase_code' => $purchaseCode,
            'type'          => $type,
        ]);

        $this->storeLicenseResult($purchaseCode, $result, isTest: true);

        return $result;
    }

    /**
     * Persists verification result to settings table (encrypted) — same
     * storage path for both real and test results, so admin UI, getLicenseType(),
     * isExtended(), isValid() all work identically in test mode.
     */
    private function storeLicenseResult(string $purchaseCode, LicenseResult $result, bool $isTest = false): void
    {
        settings_set('license_purchase_code', encrypt($purchaseCode), 'encrypted');
        settings_set('license_type', $result->type, 'integer');
        settings_set('license_buyer', $result->buyer ?? '', 'string');
        settings_set('license_valid', $result->valid, 'boolean');
        settings_set('license_verified_at', now()->toIso8601String(), 'string');
        settings_set('license_is_test_mode', $isTest, 'boolean');
    }

    public function getLicenseType(): int
    {
        return (int) settings('license_type', 0);
    }

    public function isExtended(): bool
    {
        return $this->getLicenseType() === 2;
    }

    public function isValid(): bool
    {
        return (bool) settings('license_valid', false);
    }

    public function reactivate(string $code): bool
    {
        $result = $this->verify($code);
        return $result->valid;
    }

    private function testModeActive(): bool
    {
        return config('app.license_test_mode', false) === true;
    }
}
```

If `App\DataObjects\LicenseResult` doesn't exist yet, create it:

```php
<?php

namespace App\DataObjects;

class LicenseResult
{
    public function __construct(
        public bool $valid,
        public int $type,        // 0 = invalid, 1 = regular, 2 = extended
        public ?string $buyer,
        public ?string $expiresAt,
    ) {}
}
```

---

## 3. Installer UI hint (Step 4 — License Activation)

In the Vue component for the License Activation step
(`resources/js/Pages/Install/Steps/License.vue` or equivalent per PART 68 §68.6
wizard), conditionally show the test codes **only when test mode is active**.
The backend must expose this flag via the install page's props — add to the
install controller:

```php
return Inertia::render('Install/Steps/License', [
    'licenseTestMode' => config('app.license_test_mode', false),
]);
```

In the Vue component:

```vue
<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const licenseTestMode = computed(() => usePage().props.licenseTestMode as boolean)
</script>

<template>
  <div class="space-y-4">
    <!-- existing purchase code input -->
    <Input v-model="form.purchase_code" label="Purchase code" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />

    <Alert v-if="licenseTestMode" variant="warning" class="text-sm">
      <strong>Developer test mode active.</strong> Use one of these fake codes to
      test license-gated features without contacting Envato:
      <ul class="mt-2 list-disc pl-5 space-y-1">
        <li><code>TEST-LICENSE-0000-REGULAR</code> — Regular license</li>
        <li><code>TEST-LICENSE-0000-EXTENDED</code> — Extended license (enables Pro/subscriptions)</li>
      </ul>
      This banner and these codes are disabled when <code>LICENSE_TEST_MODE</code>
      is false or absent — never visible to real buyers.
    </Alert>
  </div>
</template>
```

---

## 4. Safety checks — multiple independent layers

These ensure test mode can never silently end up active for a real buyer:

1. **Not in `.env.example`** — the distribution zip's `.env.example` (PART 68
   §68.1, §68.6) never sets `LICENSE_TEST_MODE`. Installer's `writeEnvFile()`
   copies from `.env.example`, so a fresh buyer install has the variable absent
   → `env('LICENSE_TEST_MODE', false)` → `false`.

2. **Fake codes are useless without the flag** — even if someone typed
   `TEST-LICENSE-0000-EXTENDED` into a real install's license step, `verify()`
   would skip the test branch entirely (`testModeActive()` returns `false`) and
   send it to `verifyWithEnvato()`, which will simply fail Envato verification
   (invalid code format/not found) — same as any other invalid code.

3. **Admin warning banner when active** — add to
   `resources/js/Pages/Admin/Settings/License.vue` (or wherever the admin license
   status is shown):

```vue
<Alert v-if="page.props.licenseTestMode" variant="danger">
  <strong>Warning:</strong> LICENSE_TEST_MODE is enabled on this installation.
  This must NEVER be true in production. Remove <code>LICENSE_TEST_MODE</code>
  from your <code>.env</code> file immediately if this is a live customer site.
</Alert>
```

   Expose `licenseTestMode` via `HandleInertiaRequests` middleware's shared
   `share()` method:

```php
'licenseTestMode' => config('app.license_test_mode', false),
```

4. **Startup log warning** — add to a service provider's `boot()` method
   (e.g. `AppServiceProvider`):

```php
if (config('app.license_test_mode') && app()->environment('production')) {
    Log::critical('LICENSE_TEST_MODE is enabled in a production environment! This must be disabled immediately.');
}
```

---

## 5. Testing checklist for Shehab (manual, on live server)

- [ ] Set `LICENSE_TEST_MODE=true` in `.env` on the test server only
- [ ] Run `php artisan config:clear` (cached config won't pick up `.env` changes)
- [ ] Visit `/install` (or admin → settings → license if already installed) and
      enter `TEST-LICENSE-0000-EXTENDED`
- [ ] Confirm `isProAvailable()` returns `true` (e.g. subscription menu items
      become visible)
- [ ] Confirm admin license screen shows the test-mode warning banner
- [ ] Try `TEST-LICENSE-0000-REGULAR` — confirm `isProAvailable()` returns
      `false` (regular license, even if `subscriptions_enabled` setting is on)
- [ ] Try a random invalid code (e.g. `ABC-123`) with test mode on — confirm it
      falls through to `verifyWithEnvato()` and fails normally (not silently
      accepted)

## 6. Switching to production once item is approved

Once the CodeCanyon item is approved and a real purchase code exists:

1. Set `LICENSE_TEST_MODE=false` (or remove the line) in `.env`
2. Run `php artisan config:clear`
3. Set `services.envato.personal_token` and `services.envato.item_id` in
   `config/services.php` / `.env` (Envato personal token + this product's item ID)
4. Re-test the License Activation step with the real purchase code

No code changes needed — `verifyWithEnvato()` has been the active path all along
in any environment where `LICENSE_TEST_MODE` is false/absent (which is every
buyer's environment, always).

---

## Summary of files touched/created

- `.env` (developer only — add `LICENSE_TEST_MODE=true`)
- `.env.example` (add commented documented entry, default off)
- `config/app.php` (add `license_test_mode` config key)
- `app/Services/LicenseService.php` (new or updated — test branch + real Envato path)
- `app/DataObjects/LicenseResult.php` (new, if missing)
- Install wizard License step Vue component (test-mode hint banner)
- Admin License settings Vue component (test-mode warning banner)
- `app/Http/Middleware/HandleInertiaRequests.php` (share `licenseTestMode`)
- A service provider's `boot()` (production safety log warning)
