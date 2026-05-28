<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Router extends Model
{
    use HasFactory;

    /**
     * Kolom yang boleh diisi secara mass-assignment
     */
    protected $fillable = [
        'name',
        'ip_address',
        'ssh_port',
        'api_port',
        'username',
        'password',
        'ros_version',
        'address',
        'latitude',
        'longitude',
        'coverage_radius',
        'is_active',
        'last_seen',
    ];

    /**
     * Tipe data otomatis yang dikonversi oleh Eloquent
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_seen' => 'datetime',
        'api_port' => 'integer', // memastikan port API selalu bertipe integer
        'ssh_port' => 'integer', // memastikan port SSH selalu bertipe integer
    ];

    /**
     * Relasi: satu router memiliki banyak pelanggan
     */
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Relasi many-to-many dengan Package melalui pivot table package_router
     */
    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_router')
                    ->withPivot('connection_type')
                    ->withTimestamps();
    }

    /**
     * Mengecek apakah router masih online
     * Router dianggap online jika last_seen < 5 menit
     */
    public function isOnline()
    {
        return $this->last_seen && $this->last_seen->diffInMinutes(now()) < 5;
    }
}
