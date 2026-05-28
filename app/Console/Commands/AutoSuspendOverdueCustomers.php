<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\MikrotikService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoSuspendOverdueCustomers extends Command
{
    protected $signature = 'customers:auto-suspend';
    protected $description = 'Auto suspend (isolir) customers with overdue invoices based on package grace period';

    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        parent::__construct();
        $this->whatsapp = $whatsapp;
    }

    public function handle()
    {
        $this->info('🔍 Checking for overdue invoices...');

        // ✅ Ambil semua invoice yang overdue
        $overdueInvoices = Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '<', Carbon::now())
            ->with(['customer.router', 'customer.package'])
            ->get();

        $suspended = 0;
        $skipped = 0;
        $errors = [];

        foreach ($overdueInvoices as $invoice) {
            $customer = $invoice->customer;

            if (!$customer) {
                $skipped++;
                continue;
            }

            // Lewati jika sudah suspended atau terminated
            if (in_array($customer->status, ['suspended', 'terminated'])) {
                $skipped++;
                continue;
            }

            $package = $customer->package;

            // ✅ Gunakan grace period dari package (default 3 hari jika tidak ada)
            $gracePeriod = $package ? ($package->grace_period ?? 3) : 3;

            // ✅ Cek apakah sudah melewati grace period
            $gracePeriodEnd = Carbon::parse($invoice->due_date)->addDays($gracePeriod);

            if (Carbon::now()->lt($gracePeriodEnd)) {
                // Belum melewati grace period
                $skipped++;
                continue;
            }

            try {
                // Update status di Billing
                $customer->update([
                    'status' => 'suspended',
                    'is_online' => false
                ]);
                $suspended++;

                $daysOverdue = Carbon::now()->diffInDays($invoice->due_date);
                $this->info("💤 Suspended: {$customer->name} ({$customer->customer_code}) - {$daysOverdue} days overdue");

                // ✅ Jika pelanggan PPPoE → ubah profilnya di Mikrotik
                if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) && $customer->router) {
                    $router = $customer->router;

                    // Pastikan username ada
                    $username = $customer->customer_mikrotik_username ?? null;
                    if (!$username) {
                        $this->warn("⚠️  Skipped {$customer->name} - No PPPoE username found");
                        continue;
                    }

                    try {
                    // Buat koneksi Mikrotik
                    $mikrotik = new MikrotikService($router);

                    // Ganti profil user ke "PROFIL-ISOLIR"
                    $mikrotik->setUserProfile($username, 'PROFIL-ISOLIR');

                    Log::info("✅ {$customer->name} moved to PROFIL-ISOLIR on router {$router->name}");
                    $this->info("✅ {$customer->name} → Profil changed to PROFIL-ISOLIR");
                    } catch (\Exception $e) {
                        // Log error tapi tetap lanjutkan
                        Log::warning("⚠️ Failed to change profile for {$customer->name}: " . $e->getMessage());
                        $this->warn("⚠️ Profile change failed for {$customer->name}");
                    }
                }

                // ✅ Update invoice status ke overdue
                $invoice->update(['status' => 'overdue']);

                // 📱 Kirim WhatsApp notification (expired)
                try {
                    // ✅ Validasi: Pastikan customer memiliki nomor telepon
                    if (empty($customer->phone)) {
                        Log::warning("Cannot send expired notification: customer phone is empty", [
                            'customer_code' => $customer->customer_code,
                            'invoice_number' => $invoice->invoice_number
                        ]);
                        $this->warn("⚠️ WhatsApp skipped for {$customer->name} - No phone number");
                    } else {
                        if ($this->whatsapp->sendExpiredNotification($customer, $invoice)) {
                            $this->info("📱 WhatsApp expired notification sent to {$customer->name}");
                            Log::info("WhatsApp expired notification sent", [
                                'customer' => $customer->customer_code,
                                'invoice' => $invoice->invoice_number,
                                'phone' => $customer->phone
                            ]);
                        } else {
                            $this->warn("⚠️ WhatsApp expired notification failed for {$customer->name}");
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("WhatsApp expired notification failed", [
                        'customer' => $customer->customer_code,
                        'invoice' => $invoice->invoice_number,
                        'error' => $e->getMessage()
                    ]);
                    $this->warn("⚠️ WhatsApp notification failed: " . $e->getMessage());
                }

            } catch (\Exception $e) {
                $errors[] = [
                    'customer' => $customer->name,
                    'error' => $e->getMessage()
                ];
                Log::error("❌ Failed to suspend {$customer->name}: " . $e->getMessage());
                $this->error("❌ Failed: {$customer->name} - {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("Total suspended (isolir): {$suspended}");
        $this->warn("Total skipped: {$skipped}");
        $this->error("Total errors: " . count($errors));

        if (count($errors) > 0) {
            $this->newLine();
            $this->error("Errors:");
            foreach ($errors as $error) {
                $this->error("- {$error['customer']}: {$error['error']}");
            }
        }

        return 0;
    }
}
