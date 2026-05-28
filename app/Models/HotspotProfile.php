<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotProfile extends Model
{
    protected $fillable = [
        'router_id',
        'name',
        'rate_limit',
        'session_timeout',
        'idle_timeout',
        'shared_users',
        'address_pool',
        'transparent_proxy',
        'price',
        'validity',
    ];

    protected $casts = [
        'shared_users' => 'integer',
        'price' => 'decimal:2',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}

