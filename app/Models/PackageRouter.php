<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PackageRouter extends Model
{
    use HasFactory;

    protected $table = 'package_router';

    protected $fillable = [
        'package_id',
        'router_id',
        'connection_type',
    ];

    protected $casts = [
        'package_id' => 'integer',
        'router_id' => 'integer',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
