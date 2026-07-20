<?php

namespace App\Http\Controllers;

class RobotsController extends Controller
{
    /**
     * Serve robots.txt dynamically so the Sitemap directive can use the
     * real, configured application URL (config('app.url')).
     *
     * Public content (homepage, AI tools, blog, pricing, custom pages) is
     * crawlable. Private/auth/account/admin/API areas are disallowed.
     */
    public function index()
    {
        $sitemapUrl = rtrim(config('app.url'), '/') . '/sitemap.xml';

        $disallow = [
            // Admin & infrastructure
            '/admin',
            '/horizon',
            '/api',
            // Authentication
            '/login',
            '/register',
            '/logout',
            '/forgot-password',
            '/reset-password',
            '/two-factor',
            '/verify-email',
            '/password',
            // Account & private user area
            '/user',
            '/dashboard',
            '/settings',
            '/profile',
            '/checkout',
            '/billing',
            '/billing-portal',
            '/support',
            '/favorites',
            '/collections',
            '/history',
            '/affiliate',
            '/onboarding',
            '/notifications',
            '/chains',
            '/tool-embeds',
            '/playground',
            // Auth-gated tools & addons
            '/tools/rag',
            '/voiceover-studio',
            '/content-repurposer',
            // Embeds & share widgets (meant to be iframed, not crawled)
            '/embed',
        ];

        $lines = ['User-agent: *'];
        foreach ($disallow as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $sitemapUrl;

        return response(implode("\n", $lines) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
