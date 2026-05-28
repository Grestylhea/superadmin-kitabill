<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\ActivityLog;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SuspendExpiredTenants
 *
 * Auto-suspend tenant yang subscription / trial-nya telah berakhir.
 * Berjalan setiap jam via Laravel Scheduler.
 *
 * ✅ Fitur:
 * - Deteksi trial expired (status=trial AND trial_ends_at < now)
 * - Deteksi subscription expired (status=active AND subscription_expires_at < now)
 * - Set status=suspended, is_active=false
 * - Kirim WA notifikasi suspend (anti-spam: sekali per siklus)
 * - Catat di activity_logs
 * - Opsi --dry-run untuk testing
 */
class SuspendExpiredTenants extends Command
{
    protected $signature = 'tenants:suspend-expired
                            {--dry-run : Simulasi tanpa perubahan database}';

    protected $description = 'Auto-suspend tenants whose trial or subscription has expired';

    public function __construct(protected WhatsAppService $whatsapp)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔵 DRY-RUN MODE — tidak ada perubahan yang disimpan');
        }

        $this->info('🔍 Mencari tenant dengan subscription/trial expired...');

        $now = Carbon::now();

        // Ambil tenant yang expired tapi belum suspended
        $expiredTenants = Tenant::where(function ($q) use ($now) {
            // Trial expired
            $q->where(function ($q2) use ($now) {
                $q2->where('status', 'trial')
                   ->whereNotNull('trial_ends_at')
                   ->where('trial_ends_at', '<', $now);
            })
            // Subscription expired
            ->orWhere(function ($q2) use ($now) {
                $q2->where('status', 'active')
                   ->whereNotNull('subscription_expires_at')
                   ->where('subscription_expires_at', '<', $now);
            });
        })
        ->where('is_active', true) // Hanya yang masih aktif (cegah double-suspend)
        ->get();

        if ($expiredTenants->isEmpty()) {
            $this->info('✅ Tidak ada tenant expired. Semua aman.');
            Log::info('[SUSPEND_EXPIRED_TENANTS] No expired tenants found.');
            return 0;
        }

        $this->info("📋 Ditemukan {$expiredTenants->count()} tenant expired.");

        $totalSuspended = 0;
        $totalNotified  = 0;
        $totalErrors    = 0;

        foreach ($expiredTenants as $tenant) {
            try {
                // Tentukan tipe expired
                $expiredType = ($tenant->status === 'trial') ? 'trial' : 'subscription';
                $expiredAt = $expiredType === 'trial'
                    ? $tenant->trial_ends_at
                    : $tenant->subscription_expires_at;

                $this->line("  🔴 [{$expiredType}] {$tenant->name} ({$tenant->subdomain}) — expired: {$expiredAt}");

                if (!$dryRun) {
                    $tenant->update([
                        'status'    => 'suspended',
                        'is_active' => false,
                    ]);

                    // Catat di activity log
                    ActivityLog::create([
                        'user_id'     => null, // System action
                        'tenant_id'   => $tenant->id,
                        'action'      => 'tenant.auto_suspended',
                        'model_type'  => 'Tenant',
                        'model_id'    => $tenant->id,
                        'description' => "Tenant {$tenant->name} otomatis disuspend karena {$expiredType} expired sejak {$expiredAt}",
                        'properties'  => [
                            'tenant_id'    => $tenant->id,
                            'subdomain'    => $tenant->subdomain,
                            'expired_type' => $expiredType,
                            'expired_at'   => $expiredAt,
                        ],
                        'ip_address' => null,
                    ]);

                    $totalSuspended++;

                    // Kirim WA notif (anti-spam: sekali per hari, cek subscription_suspended_notified_at)
                    $alreadyNotified = $tenant->subscription_suspended_notified_at
                        && $tenant->subscription_suspended_notified_at->isToday();

                    if (!$alreadyNotified && !empty($tenant->phone)) {
                        $waResult = $this->whatsapp->sendTenantSuspendedNotification($tenant);

                        if ($waResult) {
                            $tenant->update(['subscription_suspended_notified_at' => now()]);
                            $this->info("    📱 WA notif suspend terkirim ke {$tenant->name}");
                            $totalNotified++;
                        } else {
                            $this->warn("    ⚠️ WA notif gagal untuk {$tenant->name}");
                        }
                    } elseif ($alreadyNotified) {
                        $this->line("    ⏭️ WA notif sudah terkirim hari ini (skip)");
                    } else {
                        $this->warn("    ⚠️ No phone number for {$tenant->name}");
                    }
                }
            } catch (\Exception $e) {
                $totalErrors++;
                Log::error("[SUSPEND_EXPIRED_TENANTS] Failed to suspend {$tenant->name}: " . $e->getMessage(), [
                    'tenant_id' => $tenant->id,
                    'trace'     => $e->getTraceAsString(),
                ]);
                $this->error("  ❌ Gagal suspend {$tenant->name}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("📊 SUMMARY:");
        $this->info("  Total expired  : {$expiredTenants->count()}");
        $this->info("  Suspended      : {$totalSuspended}");
        $this->info("  WA terkirim    : {$totalNotified}");
        if ($totalErrors) {
            $this->error("  Errors         : {$totalErrors}");
        }
        if ($dryRun) {
            $this->warn('  Mode: DRY-RUN (tidak ada perubahan)');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('[SUSPEND_EXPIRED_TENANTS] Completed', [
            'found'      => $expiredTenants->count(),
            'suspended'  => $totalSuspended,
            'notified'   => $totalNotified,
            'errors'     => $totalErrors,
            'dry_run'    => $dryRun,
        ]);

        return 0;
    }
}
