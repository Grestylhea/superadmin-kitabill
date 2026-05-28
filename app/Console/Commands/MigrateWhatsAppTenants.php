<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Services\WhatsAppPortAllocator;
use App\Jobs\ProvisionWhatsAppGateway;
use Illuminate\Support\Facades\DB;

class MigrateWhatsAppTenants extends Command
{
    protected $signature = 'whatsapp:migrate-tenants {--repair : Force re-provision}';
    protected $description = 'Allocate ports and provision WhatsApp Gateways for all tenants';

    public function handle(WhatsAppPortAllocator $allocator)
    {
        $this->info("Starting WhatsApp Tenant Migration...");
        
        $tenants = Tenant::all();
        $headers = ['Tenant ID', 'Port', 'Status', 'Message'];
        $rows = [];

        foreach ($tenants as $tenant) {
            $status = 'PENDING';
            $message = '';
            
            try {
                // 1. Allocate Port
                $port = $allocator->allocate($tenant->id);
                
                // 2. Provision
                // We dispatch synchronously for the migration script to ensure completion
                $this->info("Provisioning Tenant {$tenant->id} on Port {$port}...");
                
                try {
                    ProvisionWhatsAppGateway::dispatchSync($tenant);
                    $status = 'SUCCESS';
                } catch (\Exception $e) {
                     $status = 'ERROR';
                     $message = $e->getMessage();
                }

            } catch (\Exception $e) {
                $status = 'ALLOC_FAIL';
                $message = $e->getMessage();
                $port = 'N/A';
            }

            $rows[] = [$tenant->id, $port, $status, substr($message, 0, 50)];
        }

        $this->table($headers, $rows);
        $this->info("Migration Complete.");
    }
}
