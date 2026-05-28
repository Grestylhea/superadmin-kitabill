<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * SendTenantSubscriptionReminders
 *
 * Kirim WA reminder ke tenant sebelum subscription/trial berakhir.
 * Berjalan setiap hari jam 09:00 WIB via Laravel Scheduler.
 *
 * ✅ Fitur:
 * - Reminder H-7 (7 hari sebelum expired)
 * - Reminder H-3 (3 hari sebelum expired)
 * - Reminder H-1 (1 hari sebelum expired)
 * - Anti-spam: setiap reminder hanya dikirim SEKALI per siklus subscription
 * - Flag otomatis direset saat tenant melakukan renewal (subscription_expires_at berubah)
 * - Opsi --dry-run untuk testing
 */
class SendTenantSubscriptionReminders extends Command
{
    protected $signature = 'tenants:send-subscription-reminders
                            {--dry-run : Simulasi tanpa kirim WA dan tanpa update database}';

    protected $description = 'Kirim WA reminder subscription ke tenant H-7, H-3, H-1 sebelum expired';

    public function __construct(protected WhatsAppService $whatsapp)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today  = Carbon::today();

        if ($dryRun) {
            $this->warn('🔵 DRY-RUN MODE — tidak ada WA dikirim & tidak ada perubahan database');
        }

        $this->info('📅 Mengecek tenant yang mendekati subscription expired...');

        // Ambil tenant yang masih aktif (trial atau active) dan ada tanggal expired
        $activeTenants = Tenant::where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('status', 'trial')->whereNotNull('trial_ends_at');
            })->orWhere(function ($q2) {
                $q2->where('status', 'active')->whereNotNull('subscription_expires_at');
            });
        })->where('is_active', true)->get();

        $this->info("📋 Total tenant aktif: {$activeTenants->count()}");

        $sentH7 = 0;
        $sentH3 = 0;
        $sentH1 = 0;
        $skipped = 0;

        foreach ($activeTenants as $tenant) {
            // Tentukan tanggal expired yang relevan
            $expiresAt = ($tenant->status === 'trial')
                ? $tenant->trial_ends_at
                : $tenant->subscription_expires_at;

            if (!$expiresAt) {
                $skipped++;
                continue;
            }

            $expiresAt = Carbon::parse($expiresAt);

            // Hitung sisa hari (negatif = sudah expired)
            $daysRemaining = (int) $today->diffInDays($expiresAt, false);

            // Jika sudah expired, skip (tangani oleh SuspendExpiredTenants command)
            if ($daysRemaining < 0) {
                $skipped++;
                continue;
            }

            $this->line("  🔍 {$tenant->name} — sisa {$daysRemaining} hari (expired: {$expiresAt->format('Y-m-d')})");

            // Cek dan kirim reminder sesuai jarak hari
            if ($daysRemaining <= 7 && $daysRemaining > 5) {
                $this->sendReminderIfNeeded($tenant, $expiresAt, 'h7', $sentH7, $dryRun);
            } elseif ($daysRemaining <= 3 && $daysRemaining > 1) {
                $this->sendReminderIfNeeded($tenant, $expiresAt, 'h3', $sentH3, $dryRun);
            } elseif ($daysRemaining === 1) {
                $this->sendReminderIfNeeded($tenant, $expiresAt, 'h1', $sentH1, $dryRun);
            }
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 SUBSCRIPTION REMINDER SUMMARY:');
        $this->info("  H-7 terkirim   : {$sentH7}");
        $this->info("  H-3 terkirim   : {$sentH3}");
        $this->info("  H-1 terkirim   : {$sentH1}");
        $this->info("  Skip           : {$skipped}");
        if ($dryRun) {
            $this->warn('  Mode: DRY-RUN (tidak ada perubahan)');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('[TENANT_SUBSCRIPTION_REMINDERS] Completed', [
            'h7' => $sentH7, 'h3' => $sentH3, 'h1' => $sentH1, 'skipped' => $skipped,
        ]);

        return 0;
    }

    /**
     * Kirim reminder jika belum pernah dikirim di siklus subscription saat ini.
     * Anti-spam menggunakan flag *_sent_at. Flag direset saat renewal berhasil.
     */
    private function sendReminderIfNeeded(
        Tenant $tenant,
        Carbon $expiresAt,
        string $type,
        int &$counter,
        bool $dryRun
    ): void {
        $flagField  = "subscription_reminder_{$type}_sent_at";
        $alreadySent = !empty($tenant->$flagField);

        if ($alreadySent) {
            $this->line("    ⏭️ [{$type}] {$tenant->name} — sudah terkirim (skip)");
            return;
        }

        if ($dryRun) {
            $this->info("    📱 [DRY-RUN] [{$type}] Akan kirim ke {$tenant->name} ({$tenant->phone})");
            $counter++;
            return;
        }

        // Kirim WA
        $waResult = match ($type) {
            'h7' => $this->whatsapp->sendTenantSubscriptionReminderH7($tenant, $expiresAt),
            'h3' => $this->whatsapp->sendTenantSubscriptionReminderH3($tenant, $expiresAt),
            'h1' => $this->whatsapp->sendTenantSubscriptionReminderH1($tenant, $expiresAt),
            default => false,
        };

        if ($waResult) {
            // Set flag agar tidak dikirim lagi di siklus ini
            $tenant->update([$flagField => now()]);
            $this->info("    ✅ [{$type}] WA terkirim ke {$tenant->name}");
            $counter++;
        } else {
            $this->warn("    ⚠️ [{$type}] WA gagal untuk {$tenant->name}");
            Log::warning("[TENANT_SUBSCRIPTION_REMINDERS] WA gagal ({$type})", [
                'tenant_id' => $tenant->id,
                'phone'     => $tenant->phone,
            ]);
        }
    }
}
