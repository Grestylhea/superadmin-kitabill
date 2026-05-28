<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncCustomerBillingDates extends Command
{
    protected $signature = 'customers:sync-billing-dates {--dry-run : Show what would be changed without actually updating}';
    protected $description = 'Sync customer billing dates dengan setting custom_expire_day di paket mereka';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - Tidak ada perubahan yang disimpan');
            $this->newLine();
        }

        $this->info('🔄 Syncing customer billing dates with package settings...');
        $this->newLine();

        // Ambil semua customer yang paketnya punya custom_expire_day
        $customers = Customer::with('package')
            ->whereHas('package', function($query) {
                $query->whereNotNull('custom_expire_day');
            })
            ->get();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($customers as $customer) {
            try {
                $package = $customer->package;
                
                if (!$package || !$package->custom_expire_day) {
                    $skipped++;
                    continue;
                }

                // ✅ LOGIKA: Gunakan tanggal HARI INI sebagai acuan
                $today = now();
                $expireDay = (int) $package->custom_expire_day; // Pastikan integer
                $newBilling = $today->copy();
                
                // Jika tanggal expire sudah lewat di bulan ini → set ke bulan depan
                if ($today->day > $expireDay) {
                    $newBilling->addMonth();
                }
                // Jika belum lewat, tetap di bulan ini
                
                // Set ke custom_expire_day (pastikan integer)
                $newBilling->day($expireDay);
                
                // Set waktu
                if ($package->custom_expire_time) {
                    $time = Carbon::parse($package->custom_expire_time);
                    $newBilling->setTime($time->hour, $time->minute);
                } else {
                    $newBilling->setTime(23, 59);
                }

                // Cek apakah perlu update
                if ($customer->next_billing_date->format('Y-m-d H:i') !== $newBilling->format('Y-m-d H:i')) {
                    $this->line("📝 {$customer->customer_code} - {$customer->name}");
                    $this->line("   Package: {$package->name}");
                    $this->line("   OLD: " . $customer->next_billing_date->format('d M Y H:i'));
                    $this->line("   NEW: " . $newBilling->format('d M Y H:i'));
                    
                    if (!$isDryRun) {
                        $customer->next_billing_date = $newBilling;
                        $customer->save();
                        
                        Log::info("Billing date synced", [
                            'customer' => $customer->customer_code,
                            'package' => $package->name,
                            'old_date' => $customer->next_billing_date->format('Y-m-d H:i'),
                            'new_date' => $newBilling->format('Y-m-d H:i')
                        ]);
                    }
                    
                    $updated++;
                    $this->newLine();
                } else {
                    $skipped++;
                }

            } catch (\Exception $e) {
                $this->error("❌ Error: {$customer->customer_code} - {$e->getMessage()}");
                Log::error("Failed to sync billing date", [
                    'customer' => $customer->customer_code,
                    'error' => $e->getMessage()
                ]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("Total customers processed: {$customers->count()}");
        $this->info("✅ Updated: {$updated}");
        $this->line("⏭️  Skipped (already correct): {$skipped}");
        
        if ($errors > 0) {
            $this->error("❌ Errors: {$errors}");
        }

        if ($isDryRun && $updated > 0) {
            $this->newLine();
            $this->warn('⚠️  DRY RUN MODE - Jalankan tanpa --dry-run untuk benar-benar update');
        }

        return 0;
    }
}
