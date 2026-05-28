<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralCommission extends Model
{
    protected $fillable = [
        'referral_tenant_id',
        'referred_tenant_id',
        'payment_id',
        'amount',
        'percentage',
        'status',
    ];

    public function referralTenant()
    {
        return $this->belongsTo(Tenant::class, 'referral_tenant_id');
    }

    public function referredTenant()
    {
        return $this->belongsTo(Tenant::class, 'referred_tenant_id');
    }
}
