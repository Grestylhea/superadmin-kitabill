<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppGatewayStatus extends Model
{
    protected $table = 'whatsapp_gateway_statuses';

    protected $fillable = [
        'tenant_id', 'status', 'uptime', 'phone_number', 'last_checked_at', 'last_notified_at',
    ];

    protected $casts = [
        'last_checked_at'  => 'datetime',
        'last_notified_at' => 'datetime',
    ];
}
