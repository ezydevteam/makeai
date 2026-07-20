<?php

/**
 * Human-readable presentation for admin audit-log entries.
 *
 * The presenter (App\Support\AuditLogPresenter) first looks up a row's route
 * name in `actions` below for a curated label. If there's no entry, it falls
 * back to humanising the route name itself (admin.coupons.update → "Updated
 * Coupon"), which stays correct as new features are added — so this map only
 * needs entries where the auto-label would be ambiguous or you want polish.
 *
 * Buyers extend the log vocabulary by editing THIS file — no Vue, no rebuild
 * of the label logic.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Verb keywords → past-tense label
    |--------------------------------------------------------------------------
    | Matched against the LAST segment of a route name for the auto-label.
    */
    'verbs' => [
        'update' => 'Updated',
        'store' => 'Created',
        'create' => 'Created',
        'save' => 'Saved',
        'delete' => 'Deleted',
        'destroy' => 'Deleted',
        'force-delete' => 'Permanently Deleted',
        'restore' => 'Restored',
        'approve' => 'Approved',
        'bulk-approve' => 'Bulk Approved',
        'reject' => 'Rejected',
        'process' => 'Processed',
        'toggle' => 'Toggled',
        'toggle-status' => 'Toggled',
        'activate' => 'Activated',
        'deactivate' => 'Deactivated',
        'ban' => 'Banned',
        'grant' => 'Granted',
        'send' => 'Sent',
        'test' => 'Tested',
        'retry' => 'Retried',
        'sort' => 'Reordered',
        'reorder' => 'Reordered',
        'import' => 'Imported',
        'export' => 'Exported',
        'impersonate' => 'Impersonated',
        'dismiss' => 'Dismissed',
        'snooze' => 'Snoozed',
        'generate' => 'Generated',
        'clear' => 'Cleared',
        'run' => 'Ran',
    ],

    /*
    |--------------------------------------------------------------------------
    | Category → chip icon + colour (frontend)
    |--------------------------------------------------------------------------
    | Derived from the first meaningful route segment. Drives the coloured
    | pill in the log table so entries scan by area at a glance.
    */
    'categories' => [
        'settings' => ['label' => 'Settings', 'icon' => 'ti ti-settings', 'color' => 'slate'],
        'security' => ['label' => 'Security', 'icon' => 'ti ti-shield-lock', 'color' => 'rose'],
        'users' => ['label' => 'Users', 'icon' => 'ti ti-users', 'color' => 'sky'],
        'roles' => ['label' => 'Roles', 'icon' => 'ti ti-user-shield', 'color' => 'indigo'],
        'billing' => ['label' => 'Billing', 'icon' => 'ti ti-credit-card', 'color' => 'emerald'],
        'affiliate' => ['label' => 'Affiliate', 'icon' => 'ti ti-affiliate', 'color' => 'amber'],
        'content' => ['label' => 'Content', 'icon' => 'ti ti-article', 'color' => 'violet'],
        'appearance' => ['label' => 'Appearance', 'icon' => 'ti ti-palette', 'color' => 'pink'],
        'marketing' => ['label' => 'Marketing', 'icon' => 'ti ti-speakerphone', 'color' => 'orange'],
        'ai' => ['label' => 'AI', 'icon' => 'ti ti-sparkles', 'color' => 'fuchsia'],
        'system' => ['label' => 'System', 'icon' => 'ti ti-server-cog', 'color' => 'gray'],
        'mail' => ['label' => 'Mail', 'icon' => 'ti ti-mail', 'color' => 'cyan'],
        'general' => ['label' => 'General', 'icon' => 'ti ti-point', 'color' => 'gray'],
    ],

    /*
    |--------------------------------------------------------------------------
    | First route segment → category key
    |--------------------------------------------------------------------------
    */
    'segment_category' => [
        'settings' => 'settings', 'features' => 'settings', 'oauth' => 'settings',
        'gdpr' => 'settings', 'contact' => 'settings', 'social-counters' => 'settings',
        'sidebar' => 'settings', 'notifications' => 'settings',
        'security' => 'security', '2fa' => 'security', 'license' => 'security',
        'users' => 'users', 'account' => 'users',
        'roles' => 'roles', 'admins' => 'roles', 'permissions' => 'roles',
        'transactions' => 'billing', 'plans' => 'billing', 'coupons' => 'billing',
        'payment-gateways' => 'billing', 'subscriptions' => 'billing',
        'credit-settings' => 'billing', 'reports' => 'billing',
        'affiliate' => 'affiliate',
        'blog' => 'content', 'pages' => 'content', 'menus' => 'content',
        'faqs' => 'content', 'faq-categories' => 'content', 'testimonials' => 'content',
        'comments' => 'content', 'translations' => 'content', 'languages' => 'content',
        'appearance' => 'appearance', 'themes' => 'appearance',
        'ads' => 'marketing', 'announcements' => 'marketing', 'newsletter' => 'marketing',
        'ai' => 'ai',
        'system' => 'system', 'addons' => 'system', 'currencies' => 'system',
        'mail' => 'mail',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route name → curated label (overrides the auto-label)
    |--------------------------------------------------------------------------
    | Only where the auto-label would be unclear or deserves polish.
    */
    'actions' => [
        'admin.settings.update' => ['label' => 'Updated General Settings'],
        'admin.features.settings.update' => ['label' => 'Updated Feature Toggles'],
        'admin.oauth.settings.update' => ['label' => 'Updated OAuth Settings'],
        'admin.gdpr.settings.update' => ['label' => 'Updated GDPR Settings'],
        'admin.notifications.settings.update' => ['label' => 'Updated Notification Settings'],
        'admin.contact.settings.update' => ['label' => 'Updated Contact Settings'],
        'admin.sidebar.update' => ['label' => 'Updated Sidebar Layout'],

        'admin.license.activate' => ['label' => 'Activated License'],
        'admin.license.deactivate' => ['label' => 'Deactivated License'],
        'admin.license.reverify' => ['label' => 'Re-verified License'],

        'admin.system.cache.clear' => ['label' => 'Cleared System Cache'],
        'admin.system.maintenance.toggle' => ['label' => 'Toggled Maintenance Mode'],
        'admin.system.apply-update' => ['label' => 'Applied System Update'],
        'admin.system.rollback-update' => ['label' => 'Rolled Back System Update'],
        'admin.system.cron.run' => ['label' => 'Ran a Cron Job'],

        'admin.users.impersonate' => ['label' => 'Impersonated a User'],
        'admin.users.toggle-status' => ['label' => 'Changed a User\'s Status'],
        'admin.users.2fa.disable' => ['label' => 'Disabled a User\'s 2FA'],

        'admin.transactions.approve' => ['label' => 'Approved a Transaction'],
        'admin.transactions.reject' => ['label' => 'Rejected a Transaction'],
        'admin.subscriptions.grant' => ['label' => 'Granted a Subscription'],
        'admin.subscriptions.deactivate' => ['label' => 'Deactivated a Subscription'],
        'admin.payment-gateways.sort' => ['label' => 'Reordered Payment Gateways'],
        'admin.credit-settings.update' => ['label' => 'Updated Credit Settings'],
        'admin.plans.update' => ['label' => 'Updated a Plan'],
        'admin.plans.settings' => ['label' => 'Updated Plan Settings'],

        'admin.affiliate.commissions.approve' => ['label' => 'Approved an Affiliate Commission'],
        'admin.affiliate.commissions.reject' => ['label' => 'Rejected an Affiliate Commission'],
        'admin.affiliate.commissions.bulk-approve' => ['label' => 'Bulk-approved Affiliate Commissions'],
        'admin.affiliate.payouts.process' => ['label' => 'Processed an Affiliate Payout'],
        'admin.affiliate.affiliates.ban' => ['label' => 'Banned an Affiliate'],
        'admin.affiliate.settings.edit' => ['label' => 'Updated Affiliate Settings'],
    ],
];
