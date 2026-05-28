<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Router;
use App\Plugins\Mikrotik\MikrotikSyncService;
use Illuminate\Support\Facades\Log;

class SyncMikrotikCustomers extends Command
{
    /**
     * Nama command (jalankan dengan: php artisan mikrotik:sync)
     */
    protected $signature = 'mikrotik:sync';

    /**
     * Deskripsi command
     */
    protected $description = 'Sinkronisasi status user PPPoE (online/offline/suspended) dari Mikrotik ke Billing.';

    public function handle()
    {
        $routers = Router::all();

        foreach ($routers as $r) {
            try {
                $this->info("Sinkronisasi router: {$r->name} ({$r->ip_address}) ...");

                $sync = new MikrotikSyncService(
                    $r->ip_address,
                    $r->api_user,
                    $r->api_password,
                    $r->api_port
                );

                $result = $sync->syncFromRouter($r);

                if ($result['success']) {
                    $this->info("✅ {$result['message']}");
                } else {
                    $this->error("❌ {$result['message']}");
                }

            } catch (\Exception $e) {
                Log::error("Gagal sinkron router {$r->name}: " . $e->getMessage());
                $this->error("Error pada router {$r->name}: " . $e->getMessage());
            }
        }

        $this->info("Selesai sinkronisasi semua router.");
        return 0;
    }
}
