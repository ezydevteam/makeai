<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    protected function authorize($ability, $arguments = [])
    {
        $user = auth('admin')->user() ?? auth()->user();

        return app(Gate::class)->forUser($user)->authorize($ability, $arguments);
    }
}
