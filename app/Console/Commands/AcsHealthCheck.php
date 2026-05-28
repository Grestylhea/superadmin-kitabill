<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AcsHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'acs:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check connectivity to ACS Core and verify tenant configurations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting ACS Health Check...');

        // 1. Check ACS Core Connectivity
        $acsUrl = env('ACS_CORE_URL', 'http://103.196.154.48:7557');
        $this->info("Checking connectivity to ACS Core at: $acsUrl");

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($acsUrl);
            if ($response->successful() || $response->status() === 404) { // 404 is valid for root of API
                 $this->info('✅ ACS Core is ONLINE.');
            } else {
                 $this->error('❌ ACS Core returned unexpected status: ' . $response->status());
                 \Illuminate\Support\Facades\Log::error('ACS Health Check: Core returned status ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('❌ ACS Core is OFFLINE or Unreachable: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('ACS Health Check: Core unreachable. ' . $e->getMessage());
        }

        // 2. Check Tenant Configurations
        $tenants = \App\Models\Tenant::where('acs_enabled', true)->get();
        $this->info("Checking configuration for {$tenants->count()} enabled tenants...");

        $configIssues = 0;

        foreach ($tenants as $tenant) {
            $issues = [];
            if (empty($tenant->acs_tenant_id)) $issues[] = 'Missing acs_tenant_id';
            if (empty($tenant->acs_api_key)) $issues[] = 'Missing acs_api_key';

            if (!empty($issues)) {
                $configIssues++;
                $this->warn("⚠️ Tenant [{$tenant->id}] {$tenant->name} ({$tenant->subdomain}): " . implode(', ', $issues));
                \Illuminate\Support\Facades\Log::warning("ACS Health Check: Tenant {$tenant->id} configuration issue: " . implode(', ', $issues));
            }
        }

        if ($configIssues === 0) {
            $this->info('✅ All enabled tenants have valid configurations.');
        } else {
            $this->error("❌ found $configIssues tenants with invalid configurations.");
        }

        $this->info('ACS Health Check completed.');
    }
}
