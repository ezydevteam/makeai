<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access Levels
    |--------------------------------------------------------------------------
    |
    | Define all available access levels for tools and categories.
    | Each level has a key, label, description, and requirements.
    |
    */
    'levels' => [
        'inherit' => [
            'label' => 'Inherit (Default)',
            'description' => 'Use parent or global default setting',
            'requires_auth' => false,
            'requires_subscription' => false,
            'plan_slugs' => null,
            'sort_order' => 0,
        ],

        'guest' => [
            'label' => 'Guest (Free)',
            'description' => 'Anyone can access without login',
            'requires_auth' => false,
            'requires_subscription' => false,
            'plan_slugs' => null,
            'sort_order' => 10,
        ],

        'login' => [
            'label' => 'Login Required',
            'description' => 'User must be authenticated',
            'requires_auth' => true,
            'requires_subscription' => false,
            'plan_slugs' => null,
            'sort_order' => 20,
        ],

        'premium' => [
            'label' => 'Premium (Any Plan)',
            'description' => 'Any active subscription required',
            'requires_auth' => true,
            'requires_subscription' => true,
            'plan_slugs' => null,
            'sort_order' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Access Level
    |--------------------------------------------------------------------------
    |
    | The default access level for new tools/categories when not specified.
    |
    */
    'default' => 'login',

    /*
    |--------------------------------------------------------------------------
    | Dynamic Plan Levels
    |--------------------------------------------------------------------------
    |
    | Whether to generate access levels for each plan automatically.
    | When enabled, each active plan will have its own access level.
    |
    */
    'dynamic_plan_levels' => true,

    /*
    |--------------------------------------------------------------------------
    | Plan Level Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for dynamically generated plan access levels.
    | Example: 'plan:starter', 'plan:enterprise'
    |
    */
    'plan_prefix' => 'plan:',
];
