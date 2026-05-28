<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherTemplate extends Model
{
    protected $fillable = [
        'name',
        'html_content',
        'css_content',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get default template
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->first() 
            ?? static::first();
    }
}
