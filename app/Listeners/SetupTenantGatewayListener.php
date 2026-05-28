<?php

namespace App\Listeners;

use App\Events\TenantRegistered;
use App\Jobs\ProvisionWhatsAppGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SetupTenantGatewayListener implements ShouldQueue
{
    public function handle(TenantRegistered $event): void
    {
        Log::info("TenantRegistered event received for Tenant {$event->tenant->id}. Dispatching ProvisionWhatsAppGateway job...");
        
        ProvisionWhatsAppGateway::dispatch($event->tenant);
    }
}
