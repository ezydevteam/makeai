<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['web']]);

Broadcast::channel('App.Models.Admin.{id}', function ($admin, $id) {
    return (int) $admin->id === (int) $id;
}, ['guards' => ['admin']]);

Broadcast::channel('rag.session.{sessionUlid}', function ($user, $sessionUlid) {
    return \App\Models\RagSession::where('id', $sessionUlid)
        ->where('user_id', $user->id)
        ->exists();
}, ['guards' => ['web']]);
