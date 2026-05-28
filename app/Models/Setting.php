<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    
    public static function get($key, $default = null)
    {
        try {
            // Check if settings table exists before querying (untuk menghindari error saat migration)
            if (!\Schema::hasTable('settings')) {
                return $default;
            }
            
            $row = self::where('key', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Exception $e) {
            // Jika error (misalnya saat migration), return default
            return $default;
        }
    }
    
    public static function set($key, $value)
    {
        try {
            // Check if settings table exists before querying
            if (!\Schema::hasTable('settings')) {
                return false;
            }
            
            return self::updateOrCreate(['key' => $key], ['value' => $value]);
        } catch (\Exception $e) {
            // Jika error, return false
            return false;
        }
    }
}
