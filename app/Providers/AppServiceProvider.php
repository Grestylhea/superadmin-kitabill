<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register Tenant Registered Event
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\TenantRegistered::class,
            \App\Listeners\SendTenantWelcomeNotification::class
        );

        // Set timezone dinamis dari database
        try {
            // Check if settings table exists before querying (untuk menghindari error saat migration)
            if (\Schema::hasTable('settings')) {
                $timezone = \DB::table('settings')
                    ->where('key', 'app_timezone')
                    ->value('value');
                
                if ($timezone) {
                    config(['app.timezone' => $timezone]);
                    date_default_timezone_set($timezone);
                }
            }
        } catch (\Exception $e) {
            // Jika tabel settings belum ada (saat migration), gunakan default
            // Default sudah di-set di config/app.php
        }

        // Register TenantRegistered Event Listener
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\TenantRegistered::class,
            \App\Listeners\SetupTenantGatewayListener::class
        );
    }
}
