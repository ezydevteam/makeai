<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CouponRedemption — records that a user redeemed a coupon on a specific
 * payment. Backs per-user coupon limits.
 */
class CouponRedemption extends Model
{
    protected $fillable = [
        'coupon_id', 'user_id', 'payment_id', 'user_unique_guard',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
