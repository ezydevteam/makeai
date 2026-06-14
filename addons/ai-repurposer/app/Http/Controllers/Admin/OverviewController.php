<?php

namespace Addons\AiRepurposer\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OverviewController extends Controller
{
    public function index()
    {
        return Inertia::render('Addons/ai-repurposer/Admin/Overview');
    }
}
