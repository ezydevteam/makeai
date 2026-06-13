<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddonLicense extends Model
{
    protected $table = 'addon_licenses';

    protected $fillable = [
        'addon_slug', 'purchase_code', 'envato_item_id',
        'license_type', 'buyer', 'purchased_at', 'supported_until',
        'domain', 'verified_at', 'status', 'grace_started_at',
    ];

    protected $casts = [
        'envato_item_id' => 'integer',
        'license_type' => 'integer',
        'purchased_at' => 'datetime',
        'supported_until' => 'datetime',
        'verified_at' => 'datetime',
        'grace_started_at' => 'datetime',
    ];

    protected $hidden = ['purchase_code'];
}
