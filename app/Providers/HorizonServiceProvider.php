<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Configure the Horizon authorization services.
     *
     * We override the parent so the viewHorizon gate is ALWAYS enforced. Horizon's
     * default wires `Gate::check(...) || app()->environment('local')`, which leaves
     * the dashboard wide open in local — and dangerously open on any site
     * accidentally run with APP_ENV=local in production. The dashboard exposes raw
     * job payloads (user data, OTPs, reset tokens) and allows job retry/delete, so
     * there is no environment in which it should be public.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function ($request) {
            return Gate::check('viewHorizon', [$request->user()]);
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // Horizon requires Redis — deny (and keep the UI link hidden) otherwise.
            if (! app(\App\Services\BroadcastingService::class)->isRedisAvailable()) {
                return false;
            }

            // Horizon runs under the 'web' middleware, so the passed $user is the
            // web-guard user (never an Admin). Resolve the panel admin from the
            // admin guard instead, and restrict to active Super Admins — Horizon
            // exposes raw job payloads (which can contain user data).
            $admin = auth('admin')->user();

            return $admin instanceof \App\Models\Admin
                && $admin->is_active
                && $admin->isSuperAdmin();
        });
    }
}
