<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename app_* branding keys to site_* in the settings table.
     */
    public function up(): void
    {
        $map = [
            'app_name'           => 'site_name',
            'app_url'            => 'site_url',
            'app_tagline'        => 'site_tagline',
            'app_description'    => 'site_description',
            'app_logo_light'     => 'site_logo_light',
            'app_logo_dark'      => 'site_logo_dark',
            'app_favicon_ico'    => 'site_favicon_ico',
            'app_favicon_png'    => 'site_favicon_png',
            'app_og_image'       => 'site_og_image',
            'app_copyright_text' => 'site_copyright_text',
            'app_support_email'  => 'site_support_email',
            'app_support_url'    => 'site_support_url',
            'app_terms_url'      => 'site_terms_url',
            'app_privacy_url'    => 'site_privacy_url',
        ];

        foreach ($map as $old => $new) {
            DB::table('settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }
    }

    public function down(): void
    {
        $map = [
            'site_name'           => 'app_name',
            'site_url'            => 'app_url',
            'site_tagline'        => 'app_tagline',
            'site_description'    => 'app_description',
            'site_logo_light'     => 'app_logo_light',
            'site_logo_dark'      => 'app_logo_dark',
            'site_favicon_ico'    => 'app_favicon_ico',
            'site_favicon_png'    => 'app_favicon_png',
            'site_og_image'       => 'app_og_image',
            'site_copyright_text' => 'app_copyright_text',
            'site_support_email'  => 'app_support_email',
            'site_support_url'    => 'app_support_url',
            'site_terms_url'      => 'app_terms_url',
            'site_privacy_url'    => 'app_privacy_url',
        ];

        foreach ($map as $old => $new) {
            DB::table('settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }
    }
};
