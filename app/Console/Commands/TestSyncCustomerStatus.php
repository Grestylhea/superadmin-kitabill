<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Router;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;

class TestSyncCustomerStatus extends Command
{
    protected $signature = 'customers:test-sync-status {--customer= : Customer code atau username untuk test spesifik}';
    protected $description = 'Test sync customer status dengan output detail untuk debugging';

    public function handle()
    {
        $this->info('🔍 Testing Customer Status Sync...');
        $this->newLine();

        $customerFilter = $this->option('customer');

        // Ambil nama profile isolir dari settings
        $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');

        $routers = Router::where('is_active', true)->get();
        
        if ($routers->isEmpty()) {
            $this->error('❌ Tidak ada router aktif!');
            return 1;
        }

        foreach ($routers as $router) {
            $this->info("📡 Router: {$router->name} ({$router->ip_address})");
            $this->line("─".str_repeat("─", 60));

            try {
                // Ambil customers
                $query = Customer::where('router_id', $router->id)
                    ->whereIn('connection_type', ['pppoe_direct', 'pppoe_mikrotik'])
                    ->whereNotNull('customer_mikrotik_username');

                if ($customerFilter) {
                    $query->where(function($q) use ($customerFilter) {
                        $q->where('customer_code', 'like', "%{$customerFilter}%")
                          ->orWhere('customer_mikrotik_username', 'like', "%{$customerFilter}%")
                          ->orWhere('name', 'like', "%{$customerFilter}%");
                    });
                }

                $customers = $query->get();

                if ($customers->isEmpty()) {
                    $this->warn("   ⚠️  Tidak ada customer untuk router ini");
                    $this->newLine();
                    continue;
                }

                $this->info("   📋 Total customers: {$customers->count()}");

                // Connect ke MikroTik
                $mikrotik = new MikrotikService($router);
                
                // Ambil active sessions
                $this->info("   🔄 Mengambil active sessions dari MikroTik...");
                $activeSessions = $mikrotik->getActivePPPoESessions();
                $activeUsernames = $activeSessions->map(function($session) {
                    return strtolower(trim($session['name'] ?? ''));
                })->toArray();

                $this->info("   ✅ Active sessions: " . count($activeUsernames));
                if (count($activeUsernames) > 0) {
                    $this->line("   Sample: " . implode(', ', array_slice($activeUsernames, 0, 5)));
                }

                // Ambil secrets untuk cek profile
                $this->info("   🔄 Mengambil PPPoE secrets dari MikroTik...");
                $pppoeSecrets = $mikrotik->getAllPPPoESecrets();
                $userProfiles = [];
                foreach ($pppoeSecrets as $secret) {
                    $secretUsername = strtolower(trim($secret['name'] ?? ''));
                    if (!empty($secretUsername)) {
                        $userProfiles[$secretUsername] = $secret['profile'] ?? '';
                    }
                }
                $this->info("   ✅ Total secrets: " . count($userProfiles));

                $this->newLine();
                $this->info("   📊 Checking customers:");
                $this->newLine();

                $onlineCount = 0;
                $offlineCount = 0;
                $isolirCount = 0;
                $notFoundCount = 0;

                foreach ($customers as $customer) {
                    $username = strtolower(trim($customer->customer_mikrotik_username ?? ''));
                    
                    if (empty($username)) {
                        $this->warn("   ⚠️  {$customer->customer_code} ({$customer->name}): Username kosong!");
                        continue;
                    }

                    $isOnline = in_array($username, $activeUsernames);
                    $customerProfile = $userProfiles[$username] ?? null;
                    $isIsolir = $mikrotik->isIsolirProfile($customerProfile, $isolirProfileName);
                    $hasSecret = isset($userProfiles[$username]);

                    // Status indicator
                    $statusIcon = $isOnline ? '🟢' : '🔴';
                    $statusText = $isOnline ? 'ONLINE' : 'OFFLINE';
                    
                    if ($isIsolir) {
                        $statusIcon = '🟡';
                        $statusText = 'ISOLIR';
                        $isolirCount++;
                    } elseif ($isOnline) {
                        $onlineCount++;
                    } else {
                        $offlineCount++;
                    }

                    // Check if username exists in secrets
                    if (!$hasSecret) {
                        $notFoundCount++;
                        $statusIcon = '❌';
                    }

                    $this->line("   {$statusIcon} {$customer->customer_code} | {$customer->name}");
                    $this->line("      Username DB: '{$customer->customer_mikrotik_username}'");
                    $this->line("      Username Lower: '{$username}'");
                    $this->line("      Status: {$statusText} | Profile: " . ($customerProfile ?? 'NOT FOUND'));
                    $this->line("      In Active: " . ($isOnline ? 'YES' : 'NO') . " | In Secrets: " . ($hasSecret ? 'YES' : 'NO'));
                    
                    if (!$isOnline && !empty($activeUsernames)) {
                        // Cek apakah ada username yang mirip
                        $similar = array_filter($activeUsernames, function($activeUser) use ($username) {
                            return strpos($activeUser, $username) !== false || 
                                   strpos($username, $activeUser) !== false ||
                                   similar_text($activeUser, $username) / max(strlen($activeUser), strlen($username)) > 0.8;
                        });
                        
                        if (!empty($similar)) {
                            $this->warn("      ⚠️  Similar usernames found: " . implode(', ', array_slice($similar, 0, 3)));
                        }
                    }
                    
                    $this->newLine();
                }

                $this->info("   📈 Summary:");
                $this->line("      🟢 Online: {$onlineCount}");
                $this->line("      🔴 Offline: {$offlineCount}");
                $this->line("      🟡 Isolir: {$isolirCount}");
                if ($notFoundCount > 0) {
                    $this->warn("      ❌ Not in secrets: {$notFoundCount}");
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Error: " . $e->getMessage());
                Log::error("Test sync failed for router {$router->name}: " . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info('✅ Test selesai!');
        return 0;
    }
}

