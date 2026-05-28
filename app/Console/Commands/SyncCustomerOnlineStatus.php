<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Router;
use App\Services\MikrotikService;
use App\Events\CustomerStatusUpdated;
use App\Events\CustomerStatsUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncCustomerOnlineStatus extends Command
{
    protected $signature = 'customers:sync-online-status {--router= : Router ID spesifik untuk sync}';
    protected $description = 'Sync customer online status dari MikroTik dengan update langsung ke database';

    public function handle()
    {
        // ✅ LOCK MECHANISM - Prevent multiple instances running simultaneously
        $lockKey = 'sync-online-status-running';
        $lockTimeout = 120; // 2 menit timeout
        
        if (Cache::has($lockKey)) {
            $this->warn('⚠️  Sync already running, skipping...');
            Log::info('⚠️  Sync-online-status skipped - already running');
            return 0;
        }
        
        // ✅ THROTTLE MECHANISM - Disabled untuk testing dengan interface
        // Set last sync time untuk tracking
        $lastSyncKey = 'sync-online-status-last-run';
        
        // Set lock
        Cache::put($lockKey, true, now()->addSeconds($lockTimeout));
        Cache::put($lastSyncKey, now(), now()->addMinutes(5));
        
        try {
            $this->info('🔄 Starting Customer Online Status Sync...');
            $this->newLine();

            $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
            $syncedCount = 0;
            $errorCount = 0;

        // Get routers
        $query = Router::where('is_active', true);
        if ($this->option('router')) {
            $query->where('id', $this->option('router'));
        }
        $routers = $query->get();

        if ($routers->isEmpty()) {
            $this->error('❌ Tidak ada router aktif!');
            return 1;
        }

        foreach ($routers as $router) {
            $this->info("📡 Router: {$router->name} ({$router->ip_address})");
            
            try {
                // Get all PPPoE customers for this router
                $customers = Customer::where('router_id', $router->id)
                    ->whereIn('connection_type', ['pppoe_direct', 'pppoe_mikrotik'])
                    ->whereNotNull('customer_mikrotik_username')
                    ->get(['id', 'customer_code', 'name', 'customer_mikrotik_username', 'status']);

                if ($customers->isEmpty()) {
                    $this->warn("   ⚠️  Tidak ada customer untuk router ini");
                    $this->newLine();
                    continue;
                }

                $this->info("   📋 Total customers: {$customers->count()}");

                // Connect to MikroTik
                $mikrotik = new MikrotikService($router);
                
                // Get active sessions
                $this->info("   🔄 Mengambil active sessions...");
                $activeSessions = $mikrotik->getActivePPPoESessions();
                
                // Convert to array if Collection
                if ($activeSessions instanceof \Illuminate\Support\Collection) {
                    $activeSessions = $activeSessions->toArray();
                }
                
                // Build array of active usernames (lowercase for case-insensitive matching)
                // ✅ PASTIKAN normalize dengan benar: trim, lowercase, hapus karakter khusus
                $activeUsernamesLower = [];
                $activeUsernamesOriginal = []; // Simpan original untuk debugging
                foreach ($activeSessions as $session) {
                    $name = $session['name'] ?? '';
                    if (!empty($name)) {
                        // Normalize: trim whitespace, lowercase, hapus karakter kontrol
                        $normalized = strtolower(trim($name));
                        $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized); // Hapus karakter kontrol
                        $activeUsernamesLower[] = $normalized;
                        $activeUsernamesOriginal[$normalized] = $name; // Simpan mapping original
                    }
                }
                $activeUsernamesLower = array_unique($activeUsernamesLower);
                
                $this->info("   ✅ Active sessions: " . count($activeUsernamesLower));
                if (count($activeUsernamesLower) > 0) {
                    $this->line("   Sample: " . implode(', ', array_slice($activeUsernamesLower, 0, 5)));
                }
                
                // ✅ Log sample untuk debugging
                if (count($activeUsernamesLower) > 0) {
                    Log::debug("Active sessions sample", [
                        'router' => $router->name,
                        'total' => count($activeUsernamesLower),
                        'sample_normalized' => array_slice($activeUsernamesLower, 0, 10),
                        'sample_original' => array_slice(array_values($activeUsernamesOriginal), 0, 10)
                    ]);
                }

                // ✅ Cek active sessions yang tidak ada di database
                // ✅ PASTIKAN normalize dengan cara yang sama
                $customerUsernamesLower = $customers->map(function ($c) {
                    $name = $c->customer_mikrotik_username ?? '';
                    $normalized = strtolower(trim($name));
                    $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized);
                    return $normalized;
                })->filter()->toArray();
                
                $orphanSessions = array_diff($activeUsernamesLower, $customerUsernamesLower);
                if (count($orphanSessions) > 0) {
                    $this->warn("   ⚠️  Active sessions yang TIDAK ada di database: " . count($orphanSessions));
                    $this->line("   Sample orphan sessions: " . implode(', ', array_slice($orphanSessions, 0, 5)));
                    Log::warning("Active sessions tidak terdaftar sebagai customer", [
                        'router' => $router->name,
                        'count' => count($orphanSessions),
                        'sessions' => array_slice($orphanSessions, 0, 10)
                    ]);
                }

                // Get secrets untuk cek profile
                $this->info("   🔄 Mengambil PPPoE secrets...");
                $pppoeSecrets = $mikrotik->getAllPPPoESecrets();
                $userProfiles = [];
                foreach ($pppoeSecrets as $secret) {
                    $secretUsername = strtolower(trim($secret['name'] ?? ''));
                    if (!empty($secretUsername)) {
                        $userProfiles[$secretUsername] = $secret['profile'] ?? '';
                    }
                }
                $this->info("   ✅ Total secrets: " . count($userProfiles));

                // Update customers
                $this->newLine();
                $this->info("   🔄 Updating customers...");
                
                $onlineUpdates = 0;
                $offlineUpdates = 0;
                $isolirUpdates = 0;
                $notFoundInActive = [];

                foreach ($customers as $customer) {
                    try {
                        $usernameOriginal = $customer->customer_mikrotik_username ?? '';
                        // ✅ PASTIKAN normalize dengan benar: trim, lowercase, hapus karakter khusus
                        $username = strtolower(trim($usernameOriginal));
                        $username = preg_replace('/[\x00-\x1F\x7F]/', '', $username); // Hapus karakter kontrol
                        
                        if (empty($username)) {
                            continue;
                        }

                        // Check if online - simple in_array check
                        $isOnline = in_array($username, $activeUsernamesLower);
                        
                        // ✅ DEBUG: Jika tidak online, cek apakah ada masalah matching
                        if (!$isOnline) {
                            // Cek apakah ada username yang mirip (fuzzy matching)
                            $similar = array_filter($activeUsernamesLower, function($active) use ($username) {
                                // Exact match setelah normalize
                                if ($active === $username) {
                                    return true;
                                }
                                // Partial match (username ada di active atau sebaliknya)
                                if (strpos($active, $username) !== false || strpos($username, $active) !== false) {
                                    return true;
                                }
                                // Levenshtein distance untuk typo detection (max 2 karakter berbeda)
                                if (strlen($active) > 0 && strlen($username) > 0) {
                                    $distance = levenshtein($active, $username);
                                    if ($distance <= 2 && $distance > 0) {
                                        return true;
                                    }
                                }
                                return false;
                            });
                            
                            if (!empty($similar)) {
                                // Ada username yang mirip tapi tidak exact match
                                Log::warning("⚠️ Username tidak exact match tapi ada yang mirip", [
                                    'customer_code' => $customer->customer_code,
                                    'customer_id' => $customer->id,
                                    'db_username' => $customer->customer_mikrotik_username,
                                    'db_username_normalized' => $username,
                                    'similar_found' => array_values($similar),
                                    'current_status' => $customer->status
                                ]);
                                
                                // ✅ Coba match dengan yang paling mirip
                                $bestMatch = null;
                                $bestDistance = PHP_INT_MAX;
                                foreach ($similar as $activeUsername) {
                                    $distance = levenshtein($username, $activeUsername);
                                    if ($distance < $bestDistance) {
                                        $bestDistance = $distance;
                                        $bestMatch = $activeUsername;
                                    }
                                }
                                
                                // Jika distance <= 2, anggap match (kemungkinan typo)
                                if ($bestMatch && $bestDistance <= 2) {
                                    Log::info("✅ Auto-correct username match", [
                                        'customer_code' => $customer->customer_code,
                                        'db_username' => $customer->customer_mikrotik_username,
                                        'matched_with' => $bestMatch,
                                        'distance' => $bestDistance
                                    ]);
                                    $isOnline = true; // Set sebagai online
                                }
                            } else {
                                // Tidak ada yang mirip - benar-benar tidak ada di active sessions
                                if ($customer->status === 'active') {
                                    $notFoundInActive[] = [
                                        'customer_code' => $customer->customer_code,
                                        'username' => $customer->customer_mikrotik_username,
                                        'username_normalized' => $username
                                    ];
                                    
                                    Log::warning("⚠️ Customer dengan status 'active' tapi TIDAK ada di active sessions", [
                                        'customer_code' => $customer->customer_code,
                                        'customer_id' => $customer->id,
                                        'db_username' => $customer->customer_mikrotik_username,
                                        'username_normalized' => $username,
                                        'router' => $router->name
                                    ]);
                                }
                            }
                        }
                        
                        // ✅ Track customer yang online di Mikrotik tapi status di database bukan 'active'
                        if ($isOnline && $customer->status !== 'active' && $customer->status !== 'suspended') {
                            Log::info("🔄 Customer online di Mikrotik tapi status di database: {$customer->status} → akan di-update ke 'active'", [
                                'customer_code' => $customer->customer_code,
                                'customer_id' => $customer->id,
                                'username' => $customer->customer_mikrotik_username,
                                'current_status' => $customer->status,
                                'should_be' => 'active',
                                'router' => $router->name
                            ]);
                        }
                        
                        // Check profile untuk isolir
                        $customerProfile = $userProfiles[$username] ?? null;
                        $isIsolir = $mikrotik->isIsolirProfile($customerProfile, $isolirProfileName);

                        // Determine new status
                        $newStatus = null;
                        if ($isIsolir) {
                            $newStatus = 'suspended';
                        } elseif ($isOnline) {
                            // ✅ PASTIKAN: Jika online di Mikrotik, HARUS status 'active'
                            $newStatus = 'active';
                        } else {
                            // Jika customer sudah suspended, jangan ubah ke terminated
                            if ($customer->status === 'suspended') {
                                continue;
                            }
                            $newStatus = 'terminated';
                        }

                        // ✅ PASTIKAN UPDATE - Update jika status berbeda
                        // ✅ PRIORITAS: Jika customer online di Mikrotik, HARUS langsung update ke 'active'
                        // ✅ TERMASUK jika customer online di Mikrotik tapi status di database bukan 'active'
                        if ($customer->status !== $newStatus) {
                            $oldStatus = $customer->status;
                            
                            // ✅ PRIORITAS TINGGI: Log jika customer online di Mikrotik tapi status di database bukan 'active'
                            if ($isOnline && $newStatus === 'active' && $oldStatus !== 'active') {
                                Log::info("✅ Customer online di Mikrotik - update langsung ke 'active'", [
                                    'customer_code' => $customer->customer_code,
                                    'customer_id' => $customer->id,
                                    'username' => $customer->customer_mikrotik_username,
                                    'old_status' => $oldStatus,
                                    'new_status' => $newStatus,
                                    'router' => $router->name
                                ]);
                            }
                            
                            // ✅ PRIORITAS TINGGI: Log jika customer disconnect (dari active ke terminated)
                            if ($oldStatus === 'active' && $newStatus === 'terminated') {
                                Log::info("✅ Customer disconnect - update langsung ke 'terminated'", [
                                    'customer_code' => $customer->customer_code,
                                    'customer_id' => $customer->id,
                                    'username' => $customer->customer_mikrotik_username,
                                    'old_status' => $oldStatus,
                                    'new_status' => $newStatus,
                                    'router' => $router->name
                                ]);
                            }
                            
                            // ✅ Batch update untuk mengurangi query database
                            // Kumpulkan dulu semua update, baru execute sekaligus
                            DB::table('customers')
                                ->where('id', $customer->id)
                                ->update([
                                    'status' => $newStatus,
                                    'updated_at' => now()
                                ]);
                            
                            // ✅ Reload customer untuk mendapatkan data terbaru
                            $customer->refresh();
                            
                            // ✅ Broadcast event untuk real-time update via WebSocket
                            try {
                                event(new CustomerStatusUpdated($customer, $oldStatus, $newStatus));
                                
                                // ✅ Broadcast stats update juga setiap kali ada perubahan status
                                // ✅ Ini memastikan jumlah online/offline selalu update real-time
                                $stats = [
                                    'total' => Customer::count(),
                                    'online' => Customer::where('status', 'active')->count(),
                                    'offline' => Customer::where('status', 'terminated')->count(),
                                    'suspended' => Customer::where('status', 'suspended')->count(),
                                ];
                                event(new CustomerStatsUpdated($stats));
                            } catch (\Exception $e) {
                                Log::warning("Failed to broadcast customer status update: " . $e->getMessage());
                            }
                            
                            $syncedCount++;
                            
                            // Count updates
                            if ($newStatus === 'active') {
                                $onlineUpdates++;
                            } elseif ($newStatus === 'terminated') {
                                $offlineUpdates++;
                            } elseif ($newStatus === 'suspended') {
                                $isolirUpdates++;
                            }
                            
                            // ✅ Log hanya untuk perubahan penting (reduce log noise)
                            // Hanya log jika perubahan dari active ke terminated atau sebaliknya
                            if (($customer->status === 'active' && $newStatus === 'terminated') || 
                                ($customer->status === 'terminated' && $newStatus === 'active')) {
                                Log::info("🔄 Customer status updated", [
                                    'customer_code' => $customer->customer_code,
                                    'username' => $customer->customer_mikrotik_username,
                                    'old_status' => $oldStatus,
                                    'new_status' => $newStatus,
                                    'router' => $router->name
                                ]);
                            }
                        }

                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error("❌ Failed to sync customer {$customer->customer_code}: " . $e->getMessage());
                        $this->warn("   ⚠️  Error: {$customer->customer_code} - " . $e->getMessage());
                    }
                }

                $this->info("   ✅ Summary:");
                $this->line("      🟢 Online updates: {$onlineUpdates}");
                $this->line("      🔴 Offline updates: {$offlineUpdates}");
                $this->line("      🟡 Isolir updates: {$isolirUpdates}");
                
                // ✅ Log perbandingan untuk debugging akurasi
                $actualOnlineCount = Customer::where('router_id', $router->id)
                    ->whereIn('connection_type', ['pppoe_direct', 'pppoe_mikrotik'])
                    ->where('status', 'active')
                    ->count();
                
                $this->line("      📊 Active sessions di Mikrotik: " . count($activeUsernamesLower));
                $this->line("      📊 Online customers di database: {$actualOnlineCount}");
                $this->line("      📊 Selisih: " . (count($activeUsernamesLower) - $actualOnlineCount));
                
                if (count($orphanSessions) > 0) {
                    $this->warn("      ⚠️  Active sessions yang TIDAK terdaftar sebagai customer: " . count($orphanSessions));
                }
                
                // ✅ Log customer yang seharusnya online tapi tidak ada di active sessions
                if (count($notFoundInActive) > 0) {
                    $this->error("      ❌ Customer dengan status 'active' tapi TIDAK ada di active sessions: " . count($notFoundInActive));
                    $this->line("      Sample: " . implode(', ', array_slice(array_column($notFoundInActive, 'customer_code'), 0, 5)));
                    
                    Log::error("❌ Customer online di database tapi offline di Mikrotik", [
                        'router' => $router->name,
                        'count' => count($notFoundInActive),
                        'customers' => array_slice($notFoundInActive, 0, 10)
                    ]);
                }
                
                // Log untuk monitoring
                Log::info("📊 Sync comparison", [
                    'router' => $router->name,
                    'active_sessions_mikrotik' => count($activeUsernamesLower),
                    'online_customers_database' => $actualOnlineCount,
                    'difference' => count($activeUsernamesLower) - $actualOnlineCount,
                    'orphan_sessions_count' => count($orphanSessions),
                    'not_found_in_active_count' => count($notFoundInActive),
                ]);
                
                $this->newLine();

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("   ❌ Error connecting to router: " . $e->getMessage());
                Log::error("❌ Failed to sync router {$router->name}: " . $e->getMessage());
            }
        }

        $this->info("✅ Sync selesai!");
        $this->line("   Total updated: {$syncedCount}");
        if ($errorCount > 0) {
            $this->warn("   Errors: {$errorCount}");
        }

        // ✅ Broadcast stats update untuk real-time update stats di frontend
        // ✅ PASTIKAN selalu broadcast stats setiap kali sync selesai
        try {
            $stats = [
                'total' => Customer::count(),
                'online' => Customer::where('status', 'active')->count(),
                'offline' => Customer::where('status', 'terminated')->count(),
                'suspended' => Customer::where('status', 'suspended')->count(),
            ];
            event(new CustomerStatsUpdated($stats));
            
            Log::info("📊 Customer stats broadcasted", [
                'total' => $stats['total'],
                'online' => $stats['online'],
                'offline' => $stats['offline'],
                'suspended' => $stats['suspended']
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to broadcast customer stats update: " . $e->getMessage());
        }

        return 0;
        } finally {
            // Release lock
            Cache::forget($lockKey);
        }
    }
}
