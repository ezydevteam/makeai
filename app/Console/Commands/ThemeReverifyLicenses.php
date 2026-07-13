<?php

namespace App\Console\Commands;

use App\Models\ThemeLicense;
use App\Services\ThemeLicenseService;
use Illuminate\Console\Command;

class ThemeReverifyLicenses extends Command
{
    protected $signature = 'theme:reverify-licenses';

    protected $description = 'Re-verify all premium theme licenses against the License Server';

    public function handle(ThemeLicenseService $service): int
    {
        $slugs = ThemeLicense::pluck('theme_slug');

        foreach ($slugs as $slug) {
            $service->reverify($slug);
        }

        $this->info("Re-verified {$slugs->count()} theme license(s).");

        return self::SUCCESS;
    }
}
