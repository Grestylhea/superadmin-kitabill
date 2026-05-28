<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\MikrotikService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

class AutoUnsuspendPaidCustomers extends Command
{
    protected $signature = 'customers:auto-unsuspend';
    protected $description = 'Auto unsuspend customers who have paid invoices and restore PPPoE profile in Mikrotik';

    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        parent::__construct();
        $this->whatsapp = $whatsapp;
    }

    public function handle()
    {
        $this->info('🔍 Checking for paid invoices...');

        // Ambil semua invoice dengan status "paid" dan customer masih "suspended"
        $paidInvoices = Invoice::where('status', 'paid')
            ->whereHas('customer', function ($q) {
                $q->where('status', 'suspended');
            })
            ->with(['customer.router', 'customer.package'])
            ->get();

        $restored = 0;
        $errors = [];

        foreach ($paidInvoices as $invoice) {
            $customer = $invoice->customer;

            if (!$customer) continue;

            try {
                // Update status customer ke "active"
                $customer->update(['status' => 'active']);
                $restored++;

                $this->info("✅ Activated: {$customer->name} ({$customer->customer_mikrotik_username})");

                // Jika PPPoE dan punya router
                if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) && $customer->router) {
                    $router = $customer->router;
                    $username = $customer->customer_mikrotik_username ?? null;
                    $profile = $customer->package->name ?? 'default';

                    if (!$username) {
                        $this->warn("⚠️  Skipped {$customer->name} - No PPPoE username found");
                        continue;
                    }

                    // Hubungkan ke Mikrotik
                    $mikrotik = new MikrotikService($router);

                    // Kembalikan profil ke semula (paket normal)
                    $mikrotik->setUserProfile($username, $profile);

                    Log::info("✅ {$customer->name} profile restored to {$profile} on router {$router->name}");
                    $this->info("🌐 {$customer->name} → Profile restored to {$profile}");
                }

                // 📱 Kirim WhatsApp notification aktivasi
                try {
                    if ($this->whatsapp->sendReactivationNotification($customer)) {
                        $this->info("📱 WhatsApp sent to {$customer->name}");
                        Log::info("WhatsApp reactivation notification sent", [
                            'customer' => $customer->customer_code
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning("WhatsApp notification failed: " . $e->getMessage());
                }

            } catch (\Exception $e) {
                $errors[] = [
                    'customer' => $customer->name,
                    'error' => $e->getMessage()
                ];
                Log::error("❌ Failed to unsuspend {$customer->name}: " . $e->getMessage());
                $this->error("❌ Failed: {$customer->name} - {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("Total reactivated: {$restored}");
        $this->error("Total errors: " . count($errors));

        return 0;
    }
}
