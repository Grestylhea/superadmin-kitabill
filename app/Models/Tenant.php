<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'email',
        'phone',
        'password',
        'database',
        'is_active',
        'address',
        'subscription_plan',
        'status',
        'trial_ends_at',
        'subscription_expires_at',
        'welcome_notified_at',
        'username', // ✅ Add username
        'deletion_status',
        'deletion_requested_at',
        'deletion_requested_by',
        'is_system',
        'acs_enabled',
        'acs_api_key',
        'acs_tenant_id',
        'referral_code',
        'referral_balance',
        'referral_commission_rate',
        'referrer_id',
        'referral_system_enabled',
        'subscription_reminder_h7_sent_at',
        'subscription_reminder_h3_sent_at',
        'subscription_reminder_h1_sent_at',
        'subscription_suspended_notified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'acs_enabled' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'welcome_notified_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'referral_system_enabled' => 'boolean',
        'subscription_reminder_h7_sent_at' => 'datetime',
        'subscription_reminder_h3_sent_at' => 'datetime',
        'subscription_reminder_h1_sent_at' => 'datetime',
        'subscription_suspended_notified_at' => 'datetime',
    ];

    /**
     * Get users belonging to this tenant
     */
    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Get active users
     */
    public function activeUsers()
    {
        return $this->hasMany(User::class, 'tenant_id')
                    ->where('status', 'active');
    }

    public function referralCommissions()
    {
        return $this->hasMany(ReferralCommission::class, 'referral_tenant_id');
    }

    public function referredTenants()
    {
        return $this->hasMany(Tenant::class, 'referrer_id');
    }

    public function referrer()
    {
        return $this->belongsTo(Tenant::class, 'referrer_id');
    }

    public function withdrawalRequests()
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
