<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Inertia\Inertia;

class MailLogController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Mail/Logs', [
            'logs' => MailLog::latest()->paginate(20),
        ]);
    }
}
