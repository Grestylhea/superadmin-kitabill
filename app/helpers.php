<?php
use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get or set settings
     * 
     * @param string|array $key
     * @param mixed $default
     * @return mixed
     */
    function setting($key = null, $default = null) {
        // If array, set multiple settings
        if (is_array($key)) {
            foreach ($key as $settingKey => $value) {
                Setting::set($settingKey, $value);
            }
            return true;
        }
        
        // If key is provided, get setting
        if ($key !== null) {
            return Setting::get($key, $default);
        }
        
        // If no key, return all settings
        return Setting::all()->pluck('value', 'key')->toArray();
    }
}

if (! function_exists('tenant')) {
    /**
     * Get current tenant context
     * 
     * @return object|null
     */
    function tenant() {
        // Try to get from application container first
        if (app()->bound('tenant')) {
            $tenant = app('tenant');
            // Return null if it's empty tenant object
            if (is_object($tenant) && isset($tenant->id) && $tenant->id !== null) {
                return $tenant;
            }
            // Return null for empty tenant objects
            if (is_object($tenant) && (!isset($tenant->id) || $tenant->id === null)) {
                return null;
            }
            return $tenant;
        }
        
        // Fallback to session
        if (session()->has('tenant')) {
            return session('tenant');
        }
        
        // Fallback to config
        if (config('app.current_tenant')) {
            return config('app.current_tenant');
        }
        
        return null;
    }
}

if (!function_exists('system_setting')) {
    /**
     * Get a system-level setting (Super Admin settings)
     *
     * @param string $key The setting key
     * @param mixed $default Default value if setting not found
     * @return mixed
     */
    function system_setting($key, $default = null)
    {
        return \App\Models\SystemSetting::get($key, $default);
    }
}