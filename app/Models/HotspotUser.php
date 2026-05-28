<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotUser extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'router_id',
        'server',
        'username',
        'password',
        'profile',
        'comment',
        'disabled',
        'limit_uptime',
        'limit_bytes_in',
        'limit_bytes_out',
        'limit_bytes_total',
        'bytes_in',
        'bytes_out',
        'bytes_total',
        'uptime',
        'packets_in',
        'packets_out',
        'voucher_code',
        'batch_id',
        'price',
        'last_seen',
        'expires_at',
        'synced_at',
    ];

    protected $casts = [
        'disabled' => 'boolean',
        'limit_uptime' => 'integer',
        'limit_bytes_in' => 'integer',
        'limit_bytes_out' => 'integer',
        'limit_bytes_total' => 'integer',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'bytes_total' => 'integer',
        'uptime' => 'integer',
        'packets_in' => 'integer',
        'packets_out' => 'integer',
        'price' => 'decimal:2',
        'last_seen' => 'datetime',
        'expires_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    // Helper: Check if user is active
    public function isActive(): bool
    {
        return !$this->disabled && (!$this->expires_at || $this->expires_at->isFuture());
    }

    // Helper: Format bytes to human readable
    public function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // Helper: Get usage percentage
    public function getUsagePercentage(): float
    {
        if (!$this->limit_bytes_total || $this->limit_bytes_total == 0) {
            return 0;
        }
        return ($this->bytes_total / $this->limit_bytes_total) * 100;
    }
}

