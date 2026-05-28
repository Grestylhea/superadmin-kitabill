<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\WhatsAppPortAllocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Exception;
use Illuminate\Support\Facades\Log;

class ProvisionWhatsAppGateway implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tenant;
    public $timeout = 300; // 5 minutes

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(WhatsAppPortAllocator $allocator): void
    {
        $tenantId = $this->tenant->id;
        Log::info("Starting WhatsApp Gateway provisioning for Tenant {$tenantId}...");

        try {
            // 1. Allocate Port
            $port = $allocator->allocate($this->tenant->id);
            Log::info("Allocated Port {$port} for Tenant {$tenantId}");

            // 2. Run Setup Script
            $scriptPath = '/var/isp-managerjs/scripts/setup-tenant-gateway.sh';
            
            // Note: Ensuring we call with sudo. 
            // The worker process must have sudo NOPASSWD for this script.
            $result = Process::run("sudo {$scriptPath} {$tenantId} {$port} --repair");
            
            if ($result->failed()) {
                throw new Exception("Setup script failed: " . $result->errorOutput() . " | " . $result->output());
            }

            Log::info("Setup script finished for Tenant {$tenantId}. Output: " . $result->output());

            // 3. Verify Connectivity
            $baseUrl = "http://127.0.0.1:{$port}";
            $verified = false;
            
            for ($i = 0; $i < 10; $i++) {
                try {
                    $response = Http::timeout(5)->get("{$baseUrl}/api/status");
                    if ($response->successful()) {
                        $verified = true;
                        break;
                    }
                } catch (\Throwable $e) {
                    // Wait and retry
                }
                sleep(3);
            }

            if ($verified) {
                Log::info("WhatsApp Gateway for Tenant {$tenantId} verified at {$baseUrl}");
            } else {
                throw new Exception("WhatsApp Gateway service started but failed health check at {$baseUrl}");
            }

        } catch (Exception $e) {
            Log::error("Provisioning failed for Tenant {$tenantId}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
