<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Services\MikrotikService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoIsolirByCustomDate extends Command
{
    protected $signature = 'customers:auto-isolir-custom';
    protected $description = 'Auto isolir customers based on custom_isolir_date';

    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        parent::__construct();
        $this->whatsapp = $whatsapp;
    }

    public function handle()
    {
        $this->info('🔍 Checking for customers with custom isolir date...');

        // Ambil semua customer yang punya custom_isolir_date
        // dan belum di-execute, dan tanggalnya sudah lewat
        // ✅ HAPUS filter status != 'suspended' agar bisa handle customer yang sudah suspended
        //    tapi custom_isolir_date baru di-set (misalnya untuk extend trial period)
        $now = Carbon::now();
        
        // ✅ Log untuk debugging
        Log::info("Auto isolir check started", [
            'current_time' => $now->format('Y-m-d H:i:s'),
            'timezone' => $now->timezone->getName(),
            'timestamp' => $now->timestamp
        ]);
        
        // ✅ Debug: Cek customer yang seharusnya diisolir
        $debugCustomers = Customer::whereNotNull('custom_isolir_date')
            ->where('custom_isolir_executed', false)
            ->with(['router', 'package'])
            ->get();
        
        Log::info("Auto isolir debug - All customers with custom_isolir_date", [
            'total' => $debugCustomers->count(),
            'customers' => $debugCustomers->map(function($c) use ($now) {
                return [
                    'id' => $c->id,
                    'code' => $c->customer_code,
                    'name' => $c->name,
                    'isolir_date' => $c->custom_isolir_date ? $c->custom_isolir_date->format('Y-m-d H:i:s') : null,
                    'isolir_timestamp' => $c->custom_isolir_date ? $c->custom_isolir_date->timestamp : null,
                    'current_timestamp' => $now->timestamp,
                    'should_isolir' => $c->custom_isolir_date && $c->custom_isolir_date->timestamp <= $now->timestamp,
                    'executed' => $c->custom_isolir_executed,
                    'status' => $c->status
                ];
            })->toArray()
        ]);
        
        $customers = Customer::whereNotNull('custom_isolir_date')
            ->where('custom_isolir_executed', false)
            ->where('custom_isolir_date', '<=', $now)
            ->with(['router', 'package'])
            ->get();
        
        // ✅ Log hasil query
        Log::info("Auto isolir query result", [
            'found_customers' => $customers->count(),
            'customers' => $customers->map(function($c) {
                return [
                    'id' => $c->id,
                    'code' => $c->customer_code,
                    'name' => $c->name,
                    'isolir_date' => $c->custom_isolir_date ? $c->custom_isolir_date->format('Y-m-d H:i:s') : null,
                    'executed' => $c->custom_isolir_executed
                ];
            })->toArray()
        ]);

        $isolated = 0;
        $errors = [];

        foreach ($customers as $customer) {
            try {
                $this->info("Processing: {$customer->name} ({$customer->customer_code})");
                
                // Simpan tanggal isolir dulu sebelum di-update (untuk log & notif)
                $isolirDate = $customer->custom_isolir_date;
                
                // ✅ SISTEM BARU: Update custom_isolir_date ke billing date berikutnya
                // Bukan dikosongkan, tapi di-update ke next_billing_date
                $newIsolirDate = null;
                if ($customer->package && $customer->package->custom_expire_day && $customer->next_billing_date) {
                    // Jika paket punya custom_expire_day, update custom_isolir_date = next_billing_date
                    $newIsolirDate = $customer->next_billing_date->copy();
                } else if ($customer->next_billing_date) {
                    // Jika tidak punya custom_expire_day, tetap update ke next_billing_date
                    $newIsolirDate = $customer->next_billing_date->copy();
                }
                // Jika tidak ada next_billing_date, custom_isolir_date tetap null
                
                // Update status ke suspended dan update custom_isolir_date ke billing date berikutnya
                $customer->update([
                    'status' => 'suspended',
                    'is_online' => false,
                    'custom_isolir_date' => $newIsolirDate, // Update ke billing date berikutnya (bukan dikosongkan)
                    'custom_isolir_executed' => false // Reset agar bisa dijalankan lagi di billing date berikutnya
                ]);

                // Ubah profile Mikrotik ke PROFIL-ISOLIR untuk PPPoE
                if (in_array($customer->connection_type, ['pppoe_direct', 'pppoe_mikrotik']) 
                    && $customer->router 
                    && $customer->customer_mikrotik_username) {
                    
                    $mikrotik = new MikrotikService($customer->router);
                    $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
                    
                    // Ganti profile ke PROFIL-ISOLIR
                    $mikrotik->setUserProfile($customer->customer_mikrotik_username, $isolirProfileName);
                    
                    Log::info("Auto isolir by custom date - Profile changed to ISOLIR", [
                        'customer' => $customer->customer_code,
                        'username' => $customer->customer_mikrotik_username,
                        'profile' => $isolirProfileName,
                        'custom_date' => $isolirDate->format('Y-m-d H:i'),
                        'new_isolir_date' => $newIsolirDate ? $newIsolirDate->format('Y-m-d H:i') : 'NULL'
                    ]);
                    
                    $this->info("✅ Profile Mikrotik changed to {$isolirProfileName}");
                }

                // 📱 Kirim WhatsApp notification (custom isolir)
                // ✅ PASTIKAN customer di-refresh setelah update untuk mendapatkan data terbaru
                $customer->refresh();
                
                try {
                    // ✅ Validasi: Pastikan customer memiliki nomor telepon
                    if (empty($customer->phone)) {
                        Log::warning("Cannot send custom isolir notification: customer phone is empty", [
                            'customer_code' => $customer->customer_code,
                            'customer_id' => $customer->id,
                            'isolir_date' => $isolirDate->format('Y-m-d H:i')
                        ]);
                        $this->warn("⚠️ WhatsApp skipped for {$customer->name} - No phone number");
                    } else {
                        // ✅ Log sebelum kirim untuk debugging
                        // ✅ Cek apakah WhatsApp enabled dengan reflection karena property protected
                        $whatsappEnabled = false;
                        try {
                            $reflection = new \ReflectionClass($this->whatsapp);
                            $property = $reflection->getProperty('isEnabled');
                            $property->setAccessible(true);
                            $whatsappEnabled = $property->getValue($this->whatsapp);
                        } catch (\Exception $e) {
                            // Ignore reflection error
                        }
                        
                        Log::info("Attempting to send custom isolir WhatsApp notification", [
                            'customer_code' => $customer->customer_code,
                            'customer_name' => $customer->name,
                            'phone' => $customer->phone,
                            'isolir_date' => $isolirDate->format('Y-m-d H:i'),
                            'whatsapp_enabled' => $whatsappEnabled ? 'true' : 'false'
                        ]);
                        
                        // ✅ Gunakan method sendCustomIsolirNotification yang sudah dibuat
                        $result = $this->whatsapp->sendCustomIsolirNotification($customer, $isolirDate);
                        
                        if ($result) {
                            $this->info("📱 WhatsApp custom isolir notification sent to {$customer->name}");
                            Log::info("✅ WhatsApp custom isolir notification sent successfully", [
                                'customer' => $customer->customer_code,
                                'customer_name' => $customer->name,
                                'phone' => $customer->phone,
                                'formatted_phone' => $this->whatsapp->formatPhone($customer->phone) ?? 'N/A',
                                'isolir_date' => $isolirDate->format('Y-m-d H:i')
                            ]);
                        } else {
                            $this->warn("⚠️ WhatsApp custom isolir notification failed for {$customer->name} (returned false)");
                            Log::warning("⚠️ WhatsApp custom isolir notification returned false", [
                                'customer' => $customer->customer_code,
                                'customer_name' => $customer->name,
                                'phone' => $customer->phone,
                                'isolir_date' => $isolirDate->format('Y-m-d H:i'),
                                'note' => 'Check WhatsApp service status and gateway availability'
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ WhatsApp custom isolir notification exception", [
                        'customer' => $customer->customer_code,
                        'customer_name' => $customer->name,
                        'customer_id' => $customer->id,
                        'phone' => $customer->phone ?? 'N/A',
                        'isolir_date' => $isolirDate->format('Y-m-d H:i'),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    $this->error("❌ WhatsApp notification failed: " . $e->getMessage());
                }

                $isolated++;
                $this->info("💤 Isolated: {$customer->name} based on custom date");

            } catch (\Exception $e) {
                $errors[] = [
                    'customer' => $customer->name,
                    'error' => $e->getMessage()
                ];
                Log::error("❌ Failed to isolir {$customer->name}: " . $e->getMessage());
                $this->error("❌ Failed: {$customer->name} - {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("Total isolated (custom date): {$isolated}");
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
