<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * Daftar command custom
     */
    protected $commands = [
        //
    ];

    /**
     * ✅ Jadwal cron Laravel - SYSTEM BILLING OTOMATIS
     */
    protected function schedule(Schedule $schedule)
    {
        // ==================== BILLING AUTOMATION ====================
        
        // ✅ 1. Generate invoice bulanan setiap hari jam 00:01
        //    (akan cek apakah hari ini adalah billing date customer)
/*
        $schedule->command('invoices:generate-monthly')
            ->dailyAt('00:01')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Monthly invoices generated successfully');
            })
            ->onFailure(function () {
                \Log::error('❌ Failed to generate monthly invoices');
            });
*/

        // ✅ 2. Auto suspend customer yang overdue (setiap 6 jam)
/*
        $schedule->command('customers:auto-suspend')
            ->everySixHours()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Auto suspend completed');
            })
            ->onFailure(function () {
                \Log::error('❌ Auto suspend failed');
            });
*/

        // ✅ 2.5. Auto isolir customer berdasarkan custom isolir date (setiap 1 menit - lebih responsif)
        // ✅ Gunakan cron expression dengan format Laravel: menit jam hari bulan hari-minggu
        // ✅ * * * * * = setiap menit (pada detik 0)
/*
        $schedule->command('customers:auto-isolir-custom')
            ->cron('* * * * *') // Setiap menit pada detik 0: menit jam hari bulan hari-minggu
            ->withoutOverlapping(2) // Timeout 2 menit - jika command berjalan lebih dari 2 menit, skip
            ->appendOutputTo(storage_path('logs/auto-isolir.log')) // Log output ke file
            ->onSuccess(function () {
                \Log::info('✅ Auto isolir by custom date completed');
            })
            ->onFailure(function () {
                \Log::error('❌ Auto isolir by custom date failed');
            });
*/

        // ✅ 2.6. Sync customer online status dari MikroTik
        // ✅ DIPINDAH KE CRONTAB LANGSUNG untuk reliability (sama seperti auto-isolir-custom)
        // ✅ Command berjalan via crontab: * * * * * php artisan customers:sync-online-status
        // ✅ Log: /var/www/storage/logs/sync-online-cron.log
        // ✅ Alasan: Lebih reliable, lebih cepat, log terpisah untuk debugging

        // ✅ 3. Auto aktivasi customer yang sudah bayar (setiap 30 menit)
        $schedule->command('customers:auto-unsuspend')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Auto unsuspend completed');
            })
            ->onFailure(function () {
                \Log::error('❌ Auto unsuspend failed');
            });

        // ==================== NETWORK MONITORING ====================

        // ✅ 4. Sinkronisasi data Mikrotik tiap 5 menit
        $schedule->command('mikrotik:sync')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // ✅ 5. Check WhatsApp Gateway status tiap 5 menit
        $schedule->command('wa-gateway:check')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // ==================== TENANT SUBSCRIPTION AUTOMATION ====================

        // ✅ SA-1. Auto-suspend tenant yang subscription/trial expired (setiap jam)
        $schedule->command('tenants:suspend-expired')
            ->hourly()
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Tenant expired suspension completed');
            })
            ->onFailure(function () {
                \Log::error('❌ Tenant expired suspension failed');
            });

        // ✅ SA-2. Kirim WA reminder subscription ke tenant (H-7, H-3, H-1) harian jam 09:00 WIB
        $schedule->command('tenants:send-subscription-reminders')
            ->dailyAt('09:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Tenant subscription reminders sent');
            })
            ->onFailure(function () {
                \Log::error('❌ Tenant subscription reminders failed');
            });

        // ==================== REMINDERS & NOTIFICATIONS ====================

        // ✅ 6. Kirim reminder invoice (H-7, H-3, H-1 daily jam 09:00)
        $schedule->command('invoices:send-reminders')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Invoice reminders sent (H-7, H-3, H-1)');
            })
            ->onFailure(function () {
                \Log::error('❌ Failed to send invoice reminders');
            });

        // ==================== MAINTENANCE ====================

        // ✅ 7. Check router uptime (setiap jam)
        $schedule->command('routers:check-uptime')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // ✅ 8. Cleanup old logs (setiap minggu, Minggu jam 02:00)
        $schedule->command('logs:cleanup')
            ->weeklyOn(0, '02:00')
            ->onSuccess(function () {
                \Log::info('✅ Old logs cleaned up');
            });

        // ==================== DATABASE BACKUP ====================

        // ✅ 9. Backup SEMUA database (Master + Tenant + Evolution) jam 22:00
        $schedule->command('backup:all')
            ->dailyAt('22:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ Multi-Database backup completed successfully');
            })
            ->onFailure(function () {
                \Log::error('❌ Multi-Database backup failed');
            });

        // ✅ 10. Cleanup backup lama harian jam 22:30 (Mencakup backup otomatis dan custom)
        $schedule->command('backup:clean')
            ->dailyAt('22:30')
            ->withoutOverlapping();
        
        // Cleanup untuk backup:all (menghapus zip di app/private/KITABILL_SUPERADMIN yang > 7 hari)
        $schedule->call(function () {
            $backupName = config('backup.backup.name', 'KITABILL_SUPERADMIN');
            $files = \Illuminate\Support\Facades\Storage::disk('local')->allFiles($backupName);
            $now = time();
            $maxAge = 7 * 24 * 60 * 60; // 7 days

            foreach ($files as $file) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->lastModified($file) < ($now - $maxAge)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($file);
                    \Log::info("🗑️ Deleted old multi-db backup: {$file}");
                }
            }
        })->dailyAt('23:00');

        // ==================== WA OBSERVABILITY ====================

        // ✅ 11. Aggregate daily WA metrics and cleanup old logs
        $schedule->command('wa:aggregate-daily')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->onSuccess(function () {
                \Log::info('✅ WA aggregation completed');
            });
    }

    /**
     * Muat semua command dari folder app/Console/Commands
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        // Pastikan routing command artisan dikenali
        require base_path('routes/console.php');
    }
}
