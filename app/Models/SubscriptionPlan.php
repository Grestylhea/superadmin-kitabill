<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'setup_fee',
        'max_customers',
        'max_users',
        'max_routers',
        'whatsapp_integration',
        'payment_gateway',
        'priority_support',
        'white_label',
        'trial_days',
        'is_active',
        'is_public',
        'is_featured',
        'sort_order',
        'acs_enabled',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'whatsapp_integration' => 'boolean',
        'payment_gateway' => 'boolean',
        'priority_support' => 'boolean',
        'white_label' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'acs_enabled' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Check if plan is trial (free with trial period)
     */
    public function isTrialPlan(): bool
    {
        return $this->trial_days > 0 
            && $this->price_monthly == 0 
            && $this->price_yearly == 0;
    }
}
