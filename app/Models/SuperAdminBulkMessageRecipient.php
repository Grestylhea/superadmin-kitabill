<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuperAdminBulkMessageRecipient extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function bulkMessage()
    {
        return $this->belongsTo(SuperAdminBulkMessage::class, 'super_admin_bulk_message_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
