<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
// Generate invoices setiap tanggal 1 jam 00:00 WITA (Asia/Makassar)
Schedule::command('invoices:generate-monthly')
    ->monthlyOn(1, '00:00')
    ->timezone('Asia/Makassar');

// Send reminders setiap hari jam 09:00
Schedule::command('invoices:send-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Makassar');

// Auto suspend overdue customers
Schedule::command('customers:auto-suspend')
    ->dailyAt('10:00')
    ->timezone('Asia/Makassar');
*/

// Check router uptime (setiap 5 menit)
Schedule::command('routers:check-uptime')
    ->everyFiveMinutes();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==================== DATABASE BACKUP ====================
// ✅ Multi-Database backup setiap hari jam 22:00
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

Schedule::command('backup:all')
    ->dailyAt('22:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('✅ Multi-Database backup completed successfully');
    })
    ->onFailure(function () {
        Log::error('❌ Multi-Database backup failed');
    });

// ✅ Cleanup backup lama harian jam 22:30
Schedule::command('backup:clean')
    ->dailyAt('22:30')
    ->withoutOverlapping();

// ✅ Cleanup file zip lama khusus untuk backup:all (menghapus > 7 hari)
Schedule::call(function () {
    $backupName = config('backup.backup.name', 'KITABILL_SUPERADMIN');
    $files = Storage::disk('local')->allFiles($backupName);
    $now = time();
    $maxAge = 7 * 24 * 60 * 60; // 7 days

    foreach ($files as $file) {
        if (Storage::disk('local')->lastModified($file) < ($now - $maxAge)) {
            Storage::disk('local')->delete($file);
            Log::info("🗑️ Deleted old multi-db backup: {$file}");
        }
    }
})->dailyAt('23:00');


