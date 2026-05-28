<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'tenant_id',
        'amount',
        'status',
        'proof_file',
        'admin_notes',
        'notes',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
