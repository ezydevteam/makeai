<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;

class SitemapController extends Controller
{
    public function __construct(private SitemapService $sitemap) {}

    public function index()
    {
        return response($this->sitemap->generate(), 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
