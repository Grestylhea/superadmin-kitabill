<?php

namespace App\Plugins\Mikrotik;

use App\Models\Router;
use App\Models\Customer;
use App\Models\Package;
use App\Services\MikrotikService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class MikrotikSyncService
{
    protected $ip;
    protected $user;
    protected $pass;
    protected $port;

    public function __construct($ip = null, $user = null, $pass = null, $port = 8728)
    {
        $this->ip   = $ip;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
    }

    /**
     * Sinkronisasi semua user PPPoE dari Mikrotik ke Billing
     */
    public function syncFromRouter(Router $router)
    {
        try {
            $service = new MikrotikService($router);

            // Ambil data dari router
            $secrets        = $service->getPPPoESecrets($router);
            $activeSessions = []; try { $activeSessions = $service->getActivePPPoESessions(); } catch (\Throwable $e) { Log::warning("ActiveSessionFail: " . $e->getMessage()); }
            
            // 📥 Ambil semua PPPoE profiles dari Mikrotik untuk mendapatkan rate-limit
            $mikrotikProfiles = $service->getPPPoEProfiles();
            $profileMap = [];
            foreach ($mikrotikProfiles as $mp) {
                $profileMap[$mp['name']] = [
                    'download_speed' => $mp['download_speed'],
                    'upload_speed' => $mp['upload_speed'],
                ];
            }

            $added   = 0;
            $updated = 0;

            foreach ($secrets as $s) {
                if (empty($s['name'])) {
                    continue;
                }

                $username = trim($s['name']);
                $password = $s['password'] ?? 'default123';
                $profile  = $s['profile'] ?? 'DEFAULT';

                // 📊 Ambil bandwidth dari profile Mikrotik jika ada, jika tidak gunakan default
                $downloadSpeed = $profileMap[$profile]['download_speed'] ?? 5;
                $uploadSpeed = $profileMap[$profile]['upload_speed'] ?? 2;
                
                // Pastikan paket ada, dan update bandwidth jika sudah ada
                $pkg = Package::firstOrCreate(
                    ['name' => $profile],
                    [
                        'download_speed' => $downloadSpeed,
                        'upload_speed'   => $uploadSpeed,
                        'price'          => 0,
                        'type'           => 'PPPoE',
                        'description'    => 'Imported from router ' . $router->name,
                        'available_for'  => ['pppoe'] // ✅ Auto-ceklis PPPoE saat import
                    ]
                );
                
                // ✅ Simpan router dan connection_type ke pivot table (auto-isi router dan tipe koneksi)
                $pkg->routers()->syncWithoutDetaching([
                    $router->id => ['connection_type' => 'pppoe']
                ]);
                
                // 🔄 Update bandwidth dan available_for jika package sudah ada (untuk sync 2 arah nanti)
                if ($pkg->wasRecentlyCreated === false) {
                    $needsUpdate = false;
                    $updateData = [];
                    
                    // Update bandwidth dari Mikrotik jika berbeda
                    if ($pkg->download_speed != $downloadSpeed || $pkg->upload_speed != $uploadSpeed) {
                        $updateData['download_speed'] = $downloadSpeed;
                        $updateData['upload_speed'] = $uploadSpeed;
                        $needsUpdate = true;
                        Log::info("📥 Package '{$profile}' bandwidth updated from Mikrotik: {$downloadSpeed}M/{$uploadSpeed}M");
                    }
                    
                    // ✅ Update available_for: tambahkan 'pppoe' jika belum ada
                    $currentAvailableFor = $pkg->available_for ?? [];
                    if (!in_array('pppoe', $currentAvailableFor)) {
                        $currentAvailableFor[] = 'pppoe';
                        $updateData['available_for'] = $currentAvailableFor;
                        $needsUpdate = true;
                        Log::info("✅ Package '{$profile}' available_for updated: added 'pppoe'");
                    }
                    
                    if ($needsUpdate) {
                        $pkg->update($updateData);
                    }
                }

                                // FIX: Find by Router + Username for Multi-Router Safety
                $cust = Customer::where('router_id', $router->id)
                    ->where('customer_mikrotik_username', $username)
                    ->first();

                if (!$cust) {
                    $cust = new Customer();
                    $cust->router_id = $router->id;
                    $cust->customer_mikrotik_username = $username;
                    $cust->customer_code = 'MTK-' . strtoupper(Str::random(6));
                }

                // --- MAPPING CUSTOMER DARI COMMENT ---
                $comment = trim($s['comment'] ?? '');
                $finalName = !empty($comment) ? $comment : ucfirst(strtolower($username));
                // Email format: nama (remove space/symbol) + @gmail.com
                $sanitizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $finalName));
                if (empty($sanitizedName)) $sanitizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username));
                $finalEmail = $sanitizedName . '@gmail.com';

                // FORCE UPDATE fields
                $cust->name = $finalName;
                $cust->email = $finalEmail;
                $cust->phone = $cust->phone ?? '628000000000'; // Default phone only if empty
                $cust->address = $cust->address ?? 'Belum diatur';
                
                $cust->connection_type = 'pppoe_direct';
                $cust->package_id = $pkg->id;

                // Simpan username & password dari Mikrotik
                $cust->customer_mikrotik_username = $username;
                $cust->customer_mikrotik_password = $password;
                
                // Simpan juga ke connection_config untuk konsistensi dengan form
                $connectionConfig = $cust->connection_config ?? [];
                $connectionConfig['username'] = $username;
                $connectionConfig['password'] = $password;
                $cust->connection_config = $connectionConfig;

                // ✅ Cek apakah profile adalah isolir profile
                $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
                $isIsolir = $service->isIsolirProfile($profile, $isolirProfileName);
                
                // Status: jika profil isolir → suspended, jika tidak → active
                $cust->status = $isIsolir ? 'suspended' : 'active';
                $installationDate = $cust->installation_date ?? now();
                $cust->installation_date = $installationDate;
                
                // 📅 Calculate next_billing_date based on package settings
                if ($pkg->custom_expire_day) {
                    // ✅ LOGIKA: Gunakan tanggal HARI INI sebagai acuan (bukan installation date)
                    // Jika tanggal expire sudah lewat di bulan ini → set ke bulan depan
                    // Jika tanggal expire belum lewat di bulan ini → set ke bulan ini
                    $today = now();
                    $expireDay = (int) $pkg->custom_expire_day; // Pastikan integer
                    $nextBilling = $today->copy();
                    
                    if ($today->day > $expireDay) {
                        // Tanggal expire sudah lewat → set ke bulan depan
                        $nextBilling->addMonth();
                    }
                    // Jika belum lewat, tetap di bulan ini
                    
                    // Set to custom_expire_day (pastikan integer)
                    $nextBilling->day($expireDay);
                    
                    // Set time from package or default 23:59
                    if ($pkg->custom_expire_time) {
                        $time = \Carbon\Carbon::parse($pkg->custom_expire_time);
                        $nextBilling->setTime($time->hour, $time->minute);
                    } else {
                        $nextBilling->setTime(23, 59);
                    }
                    
                    $cust->next_billing_date = $nextBilling;
                } else {
                    // Default: 30 days from installation
                    $cust->next_billing_date = $cust->next_billing_date ?? $installationDate->copy()->addDays(30);
                }

                $cust->save();

                $cust->wasRecentlyCreated ? $added++ : $updated++;
            }

            // Sinkronisasi status (active / terminated)
            $this->syncStatuses($router, $activeSessions);

            return [
                'success' => true,
                'message' => "Sinkronisasi berhasil: {$added} baru, {$updated} diperbarui, total " . count($secrets) . " user."
            ];

        } catch (Exception $e) {
            Log::error("❌ Sync router {$router->name} gagal: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sinkronisasi status pelanggan (active / terminated / suspended)
     */
    public function syncStatuses(Router $router, $activeSessions = null)
    {
        try {
            $service = new MikrotikService($router);
            
            if (!$activeSessions) {
                $activeSessions = []; try { $activeSessions = $service->getActivePPPoESessions(); } catch (\Throwable $e) { Log::warning("ActiveSessionFail: " . $e->getMessage()); }
            }

            // ✅ KONVERSI KE ARRAY jika Collection (sama seperti SyncCustomerOnlineStatus)
            if ($activeSessions instanceof \Illuminate\Support\Collection) {
                $activeSessions = $activeSessions->toArray();
            }

            // Build array of active usernames (lowercase for case-insensitive matching)
            $activeUsernamesLower = [];
            foreach ($activeSessions as $session) {
                $name = $session['name'] ?? '';
                if (!empty($name)) {
                    $activeUsernamesLower[] = strtolower(trim($name));
                }
            }
            $activeUsernamesLower = array_unique($activeUsernamesLower);

            // Ambil PPPoE secrets untuk cek profile
            $pppoeSecrets = $service->getAllPPPoESecrets();
            $userProfiles = [];
            foreach ($pppoeSecrets as $secret) {
                $secretUsername = strtolower(trim($secret['name'] ?? ''));
                if (!empty($secretUsername)) {
                    $userProfiles[$secretUsername] = $secret['profile'] ?? '';
                }
            }
            
            $customers = Customer::where('router_id', $router->id)
                ->whereIn('connection_type', ['pppoe_direct', 'pppoe_mikrotik'])
                ->whereNotNull('customer_mikrotik_username')
                ->get(['id', 'customer_code', 'customer_mikrotik_username', 'status']);

            $isolirProfileName = setting('isolir_profile_name', 'PROFIL-ISOLIR');
            $updated = 0;
            
            foreach ($customers as $c) {
                try {
                    $username = strtolower(trim($c->customer_mikrotik_username ?? ''));
                    if (empty($username)) {
                        continue;
                    }

                    // Check if online - simple in_array check (sama seperti SyncCustomerOnlineStatus)
                    $isOnline = in_array($username, $activeUsernamesLower);

                    // ✅ Cek apakah profile adalah isolir profile
                    $customerProfile = $userProfiles[$username] ?? null;
                    $isIsolir = $service->isIsolirProfile($customerProfile, $isolirProfileName);
                    
                    // Determine new status
                    $newStatus = null;
                    if ($isIsolir) {
                        $newStatus = 'suspended';
                    } elseif ($isOnline) {
                        $newStatus = 'active';
                    } else {
                        // Jika customer sudah suspended, jangan ubah ke terminated
                        if ($c->status === 'suspended') {
                            continue;
                        }
                        $newStatus = 'terminated';
                    }

                    if ($c->status !== $newStatus) {
                        $c->update(['status' => $newStatus]);
                        $updated++;
                    }
                } catch (Exception $e) {
                    Log::warning("⚠️ Error updating customer status", [
                        'customer_code' => $c->customer_code ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            Log::info("🔁 {$router->name}: Updated {$updated} user statuses.");
            return ['updated' => $updated];

        } catch (Exception $e) {
            Log::error("❌ Gagal sync status {$router->name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sinkronisasi Hotspot Users dari Mikrotik (termasuk password)
     */
    public function syncHotspotUsers(Router $router)
    {
        try {
            $service = new MikrotikService($router);
            $mikrotikUsers = $service->getAllHotspotUsers();
            
            $updated = 0;
            $created = 0;
            
            foreach ($mikrotikUsers as $userData) {
                if (empty($userData['username'])) {
                    continue;
                }
                
                // Cari user yang sudah ada
                $existingUser = \App\Models\HotspotUser::where('router_id', $router->id)
                    ->where('username', $userData['username'])
                    ->first();
                
                if ($existingUser) {
                    // ✅ Update user yang sudah ada (termasuk password)
                    $existingUser->update([
                        'password' => $userData['password'] ?? $existingUser->password, // ✅ Update password jika ada
                        'profile' => $userData['profile'] ?? $existingUser->profile,
                        'comment' => $userData['comment'] ?? $existingUser->comment,
                        'disabled' => $userData['disabled'] ?? $existingUser->disabled,
                        'limit_uptime' => $userData['limit_uptime'] ?? $existingUser->limit_uptime,
                        'limit_bytes_total' => $userData['limit_bytes_total'] ?? $existingUser->limit_bytes_total,
                        'expires_at' => $userData['expires_at'] ?? $existingUser->expires_at,
                    ]);
                    $updated++;
                } else {
                    // ✅ Create user baru (termasuk password)
                    \App\Models\HotspotUser::create([
                        'router_id' => $router->id,
                        'username' => $userData['username'],
                        'password' => $userData['password'] ?? '', // ✅ Simpan password
                        'profile' => $userData['profile'] ?? 'default',
                        'comment' => $userData['comment'] ?? null,
                        'disabled' => $userData['disabled'] ?? false,
                        'limit_uptime' => $userData['limit_uptime'] ?? null,
                        'limit_bytes_total' => $userData['limit_bytes_total'] ?? null,
                        'expires_at' => $userData['expires_at'] ?? null,
                    ]);
                    $created++;
                }
            }
            
            Log::info("🔁 {$router->name}: Synced {$created} new hotspot users, {$updated} updated (including passwords).");
            return [
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated
            ];
            
        } catch (Exception $e) {
            Log::error("❌ Gagal sync hotspot users {$router->name}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Import profile PPPoE sebagai paket
     */
    public function importProfiles(Router $router)
    {
        try {
            $service  = new MikrotikService($router);
            $profiles = $service->client->query('/ppp/profile/print')->read();

            $imported = 0;
            foreach ($profiles as $p) {
                if (empty($p['name'])) {
                    continue;
                }

                $pkg = Package::firstOrCreate(
                    ['name' => $p['name']],
                    [
                        'download_speed' => 5,
                        'upload_speed'   => 2,
                        'price'          => 0,
                        'type'           => 'PPPoE',
                        'description'    => 'Imported from ' . $router->name,
                        'available_for'  => ['pppoe'] // ✅ Auto-ceklis PPPoE saat import
                    ]
                );
                
                // ✅ Simpan router dan connection_type ke pivot table (auto-isi router dan tipe koneksi)
                $pkg->routers()->syncWithoutDetaching([
                    $router->id => ['connection_type' => 'pppoe']
                ]);
                
                // ✅ Jika package sudah ada, pastikan 'pppoe' ada di available_for
                if (!$pkg->wasRecentlyCreated) {
                    $currentAvailableFor = $pkg->available_for ?? [];
                    if (!in_array('pppoe', $currentAvailableFor)) {
                        $currentAvailableFor[] = 'pppoe';
                        $pkg->update(['available_for' => $currentAvailableFor]);
                        Log::info("✅ Package '{$p['name']}' available_for updated: added 'pppoe'");
                    }
                }
                
                $imported++;
            }

            Log::info("✅ {$router->name}: Imported {$imported} profiles.");
            return ['imported' => $imported];

        } catch (Exception $e) {
            Log::error("❌ Failed import profiles: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse Mikrotik Rate Limit string (e.g. "1M/2M")
     */
    private function parsePPPRateLimit($limit)
    {
        if (empty($limit)) {
            return ['up' => 0, 'down' => 0];
        }

        // Format is usually rx/tx e.g. 1M/5M
        // rx = upload from router perspective (client download?? No, rx is Receive by Router = Upload by Client)
        // Wait, Mikrotik Simple Queue: Target Upload / Target Download
        // Secret Rate Limit: Limit Bytes In / Limit Bytes Out
        // Usually: UPLOAD/DOWNLOAD from Client Perspective?
        // Let's assume standard: First is UP (Rx), Second is DOWN (Tx) for Simple Queues?
        // Actually Mikrotik Rate Limit on Secret: "rx/tx"
        // rx = server receive = client upload.
        // tx = server transmit = client download.
        
        $parts = explode('/', $limit);
        $up = isset($parts[0]) ? $this->convertToMbps($parts[0]) : 0;
        $down = isset($parts[1]) ? $this->convertToMbps($parts[1]) : 0;

        return ['up' => $up, 'down' => $down];
    }

    private function convertToMbps($value)
    {
        $value = strtolower(trim($value));
        if (str_ends_with($value, 'k')) {
            return (float)str_replace('k', '', $value) / 1024;
        }
        if (str_ends_with($value, 'm')) {
            return (float)str_replace('m', '', $value);
        }
        // If raw number, assume bits? Or bytes? usually bits in mikrotik.
        // If it's just "1000", likely bits. 1000/1000/1000 = very small.
        // Let's assume if no suffix, it's bits.
        if (is_numeric($value)) {
            return (float)$value / 1000000;
        }
        return 0;
    }

    /**
     * Sync Hotspot Profiles
     */
    public function syncHotspotProfiles(Router $router)
    {
        try {
            $service = new MikrotikService($router);
            
            // Check availability of getClient or public client
            if (method_exists($service, 'getClient')) {
                $client = $service->getClient();
            } elseif (property_exists($service, 'client')) {
                $client = $service->client;
            } else {
                 // Fallback reflection or public access
                 $client = $service->client; 
            }

            // Use Query for Safety
            $query = new \RouterOS\Query('/ip/hotspot/user/profile/print');
            $profiles = $client->query($query)->read();

            $stats = ['created' => 0, 'updated' => 0];

            foreach ($profiles as $p) {
                $name = $p['name'] ?? '';
                if (empty($name) || $name === 'default') continue;

                $rateLimit = $p['rate-limit'] ?? '';
                $bw = $this->parsePPPRateLimit($rateLimit);

                $pkg = Package::firstOrCreate(
                    ['name' => $name],
                    [
                        'price' => 0,
                        'type' => 'Hotspot',
                        'description' => 'Imported Hotspot Profile from ' . $router->name,
                        'available_for' => ['hotspot'],
                        'download_speed' => $bw['down'],
                        'upload_speed' => $bw['up']
                    ]
                );

                // Update Pivot
                $pkg->routers()->syncWithoutDetaching([
                    $router->id => ['connection_type' => 'hotspot']
                ]);

                // Update Logic
                $updates = [];
                if (!$pkg->wasRecentlyCreated) {
                    // Update bandwidth if zero or changed? 
                    // Let's force update if Mikrotik has valid data
                    if ($bw['down'] > 0 && $pkg->download_speed != $bw['down']) $updates['download_speed'] = $bw['down'];
                    if ($bw['up'] > 0 && $pkg->upload_speed != $bw['up']) $updates['upload_speed'] = $bw['up'];
                    
                    $avail = $pkg->available_for ?? [];
                    if (!in_array('hotspot', $avail)) {
                        $avail[] = 'hotspot';
                        $updates['available_for'] = $avail;
                    }
                }
                
                if (!empty($updates)) {
                    $pkg->update($updates);
                    $stats['updated']++;
                } elseif ($pkg->wasRecentlyCreated) {
                    $stats['created']++;
                }
            }

            Log::info("Hotspot Profiles Synced: " . json_encode($stats));
            return $stats;

        } catch (\Throwable $e) {
            Log::error("Hotspot Profile Sync Error: " . $e->getMessage());
            throw $e;
        }
    }

}

