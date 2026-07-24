<?php

/*
|--------------------------------------------------------------------------
| Demo Mode Configuration
|--------------------------------------------------------------------------
|
| These settings are controlled by the MakeAI seller authority via .env
| variables. Site admins cannot toggle demo mode — it is a system-level
| control for Envato marketplace preview and buyer confidence.
|
| Edit your .env file directly:
|   DEMO_ENABLED=true
|   DEMO_BANNER_COLOR=amber
|   DEMO_ENVATO_URL=https://codecanyon.net/item/...
|   DEMO_ADMIN_EMAIL=admin@demo.com
|   DEMO_ADMIN_PASSWORD=<choose one>
|   DEMO_USER_EMAIL=demo@demo.com
|   DEMO_USER_PASSWORD=<choose one>
|
| The passwords have NO default on purpose. They are published on the sign-in
| page whenever demo mode is on, so a shipped default would be a known admin
| password on every demo site that forgot to set one. Unset means: credentials
| are not displayed, and DemoSeeder refuses to create the accounts.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode Toggle
    |--------------------------------------------------------------------------
    |
    | When enabled, destructive write operations (POST/PUT/PATCH/DELETE) are
    | blocked for all routes except AI generation, auth, and preferences.
    |
    */

    'enabled' => (bool) env('DEMO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Demo Banner
    |--------------------------------------------------------------------------
    |
    | A sticky, dismissible-per-session banner is shown on all layouts when
    | demo mode is active. Configure color and purchase link here.
    |
    */

    'banner_color' => env('DEMO_BANNER_COLOR', 'amber'),

    'envato_url' => env('DEMO_ENVATO_URL', 'https://codecanyon.net'),

    /*
    |--------------------------------------------------------------------------
    | Demo Credentials
    |--------------------------------------------------------------------------
    |
    | Displayed on the login and registration pages when demo mode is active.
    | DemoSeeder creates exactly these accounts, so the two stay in step.
    |
    | Emails keep a default (they are not secret). Passwords deliberately do
    | not — see the note at the top of this file.
    |
    */

    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@demo.com'),

    'admin_password' => env('DEMO_ADMIN_PASSWORD'),

    'user_email' => env('DEMO_USER_EMAIL', 'demo@demo.com'),

    'user_password' => env('DEMO_USER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Demo Switcher — Nav Menu
    |--------------------------------------------------------------------------
    |
    | The demo bar's nav menu (visible only in demo mode) lets a buyer preview
    | different TARGET-PAGE styles WITHOUT changing the theme preset — that is
    | the right-side selector modal's job (presets + addon homepages, both
    | discovered automatically).
    |
    | Each item maps a stable `key` (used in the ?demo_home / ?demo_tool query
    | param) to a page-level layout the theme already ships:
    |   - home items → a hero `hero_variant` (Home.vue renders it)
    |   - tool items → a ToolPage `layout` (default | modern | minimalist | creative)
    |
    | Selection is carried in the URL (GET only) so it never trips demo mode's
    | write-block and stays deep-linkable.
    |
    */

    'nav' => [
        'home' => [
            'label' => 'Home',
            'items' => [
                ['key' => 'home-1', 'label' => 'Home 1 — Gradient',   'hero_variant' => 'centered-gradient'],
                ['key' => 'home-2', 'label' => 'Home 2 — Tools Grid', 'hero_variant' => 'tools-grid'],
                ['key' => 'home-3', 'label' => 'Home 3 — Featured',   'hero_variant' => 'featured'],
            ],
        ],
        'tools' => [
            'label' => 'Tool Page',
            'items' => [
                ['key' => 'tool-1', 'label' => 'Page 1 — Default',     'layout' => 'default'],
                ['key' => 'tool-2', 'label' => 'Page 2 — Modern',      'layout' => 'modern'],
                ['key' => 'tool-3', 'label' => 'Page 3 — Minimalist',  'layout' => 'minimalist'],
                ['key' => 'tool-4', 'label' => 'Page 4 — Creative',    'layout' => 'creative'],
            ],
        ],
    ],

];
