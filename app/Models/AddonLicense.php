<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonLicense extends Model
{
    protected $table = 'addon_licenses';

    protected $fillable = [
        'addon_slug', 'purchase_code',
        'license_type', 'buyer', 'purchased_at', 'supported_until',
        'domain', 'verified_at', 'status', 'grace_started_at',
        'signed_payload', 'signature',
    ];

    protected $casts = [
        'license_type' => 'integer',
        'purchased_at' => 'datetime',
        'supported_until' => 'datetime',
        'verified_at' => 'datetime',
        'grace_started_at' => 'datetime',
    ];

    protected $hidden = ['purchase_code', 'signed_payload', 'signature'];
}
