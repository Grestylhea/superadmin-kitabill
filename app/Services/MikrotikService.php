<?php

namespace App\Services;

use App\Models\Router;
use App\Models\Package;
use App\Models\Customer;
use RouterOS\Client;
use RouterOS\Query;

class MikrotikService
{
    protected Client $client;
    protected Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->connect();
    }

    /**
     * Inisialisasi koneksi ke router
     */
    protected function connect(): void
    {
        try {
            $this->client = new Client([
                'host' => $this->router->ip_address,
                'user' => $this->router->username,
                'pass' => $this->router->password,
                'port' => $this->router->api_port ?? 8728,
                // Batasi waktu tunggu agar UI tidak lama loading
                'timeout' => 5,
            ]);
        } catch (\Exception $e) {
            throw new \Exception("Failed to connect to router: " . $e->getMessage());
        }
    }

    /**
     * Create client dengan timeout yang lebih panjang untuk query besar
     */
    protected function getClientWithLongTimeout(): Client
    {
        return new Client([
            'host' => $this->router->ip_address,
            'user' => $this->router->username,
            'pass' => $this->router->password,
            'port' => $this->router->api_port ?? 8728,
            'timeout' => 60, // Timeout lebih panjang untuk query besar (60 detik) - memastikan semua data terbaca
        ]);
    }

    /**
     * Get RouterOS Client (for advanced operations)
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get Hotspot Profiles from Mikrotik
     */
    public function getHotspotProfiles(): array
    {
        try {
            $query = new Query('/ip/hotspot/user/profile/print');
            $profiles = $this->client->query($query)->read();
            
            $result = [];
            foreach ($profiles as $profile) {
                $result[] = [
                    'id' => $profile['.id'] ?? '',
                    'name' => $profile['name'] ?? '',
                    'address_pool' => $profile['address-pool'] ?? '',
                    'session_timeout' => $profile['session-timeout'] ?? '',
                    'idle_timeout' => $profile['idle-timeout'] ?? '',
                    'keepalive_timeout' => $profile['keepalive-timeout'] ?? '',
                    'status_autorefresh' => $profile['status-autorefresh'] ?? '',
                    'shared_users' => $profile['shared-users'] ?? '',
                    'rate_limit' => $profile['rate-limit'] ?? '',
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get hotspot profiles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get All Hotspot Users from Mikrotik
     */
    public function getAllHotspotUsers(): array
    {
        try {
            \Log::info("🔍 getAllHotspotUsers: Starting query to Mikrotik");
            
            // ✅ Query TANPA proplist untuk memastikan password dan server terambil
            // Proplist kadang tidak mengembalikan password, jadi lebih baik ambil semua field
            $query = new Query('/ip/hotspot/user/print');
            
            \Log::info("🔍 getAllHotspotUsers: Executing query without proplist to get all fields (including password and server)...");
            $users = $this->client->query($query)->read();
            
            \Log::info("🔍 getAllHotspotUsers: Got " . count($users) . " users from Mikrotik");
            if (count($users) > 0) {
                $sampleUser = $users[0];
                \Log::info("🔍 getAllHotspotUsers: Sample user fields: " . implode(', ', array_keys($sampleUser)));
                if (isset($sampleUser['password']) && !empty($sampleUser['password'])) {
                    \Log::info("✅ Password field is available in query result");
                } else {
                    \Log::warning("⚠️ Password field NOT found or empty in query result");
                }
                if (isset($sampleUser['server']) && !empty($sampleUser['server']) && $sampleUser['server'] !== 'all') {
                    \Log::info("✅ Server field is available: " . $sampleUser['server']);
                } else {
                    \Log::info("ℹ️ Server field: " . ($sampleUser['server'] ?? 'not set') . " (will use 'all' if empty)");
                }
            }
            
            $result = [];
            foreach ($users as $index => $user) {
                \Log::debug("🔍 Processing user #{$index}: " . ($user['name'] ?? 'no name'));
                // Parse limit-uptime (format: "1d2h3m" atau "86400s")
                $limitUptime = null;
                if (isset($user['limit-uptime']) && !empty($user['limit-uptime'])) {
                    $limitUptime = $this->parseUptimeToSeconds($user['limit-uptime']);
                }
                
                // Parse limit-bytes-total (format: "100M", "1G", dll)
                $limitBytesTotal = null;
                if (isset($user['limit-bytes-total']) && !empty($user['limit-bytes-total'])) {
                    $limitBytesTotal = $this->parseBytes($user['limit-bytes-total']);
                }
                
                // Parse expires_at dari limit-uptime (jika ada)
                $expiresAt = null;
                if ($limitUptime !== null) {
                    try {
                        $expiresAt = now()->addSeconds($limitUptime);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
                
                // ✅ Pastikan password diambil - jika tidak ada, coba ambil dengan query terpisah
                $password = $user['password'] ?? '';
                if (empty($password) && isset($user['.id'])) {
                    // Coba ambil password dengan query khusus menggunakan .proplist
                    try {
                        $pwdQuery = new Query('/ip/hotspot/user/print');
                        $pwdQuery->where('.id', $user['.id']);
                        $pwdQuery->where('.proplist', 'password');
                        $pwdUser = $this->client->query($pwdQuery)->read();
                        if (!empty($pwdUser) && isset($pwdUser[0]['password'])) {
                            $password = $pwdUser[0]['password'];
                            \Log::debug("✅ Password retrieved for user {$user['name']} via separate query");
                        } else {
                            // Coba tanpa proplist
                            $pwdQuery2 = new Query('/ip/hotspot/user/print');
                            $pwdQuery2->where('.id', $user['.id']);
                            $pwdUser2 = $this->client->query($pwdQuery2)->read();
                            if (!empty($pwdUser2) && isset($pwdUser2[0]['password'])) {
                                $password = $pwdUser2[0]['password'];
                                \Log::debug("✅ Password retrieved for user {$user['name']} via query without proplist");
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning("⚠️ Failed to get password for user {$user['name']}: " . $e->getMessage());
                    }
                }
                
                // ✅ Pastikan server diambil dengan benar (bukan hanya default "all")
                $server = $user['server'] ?? 'all';
                if ($server === '' || $server === null) {
                    $server = 'all';
                }
                
                \Log::debug("📝 User {$user['name']}: password=" . (!empty($password) ? '***' : 'EMPTY') . ", server={$server}");
                
                $result[] = [
                    'username' => $user['name'] ?? '',
                    'password' => $password, // ✅ Password diambil dan disimpan
                    'server' => $server, // ✅ Server diambil (all atau spesifik)
                    'profile' => $user['profile'] ?? 'default',
                    'comment' => $user['comment'] ?? null,
                    'disabled' => isset($user['disabled']) && ($user['disabled'] === 'true' || $user['disabled'] === true),
                    'limit_uptime' => $limitUptime,
                    'limit_bytes_total' => $limitBytesTotal,
                    'expires_at' => $expiresAt,
                ];
            }
            
            \Log::info("✅ getAllHotspotUsers: Returning " . count($result) . " processed users");
            return $result;
        } catch (\Exception $e) {
            \Log::error("❌ Failed to get hotspot users: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Parse uptime string to seconds (e.g., "1d" -> 86400, "2h" -> 7200, "30m" -> 1800)
     */
    private function parseUptimeToSeconds(string $uptime): ?int
    {
        if (empty($uptime)) return null;
        
        $uptime = strtolower(trim($uptime));
        $seconds = 0;
        
        // Jika format sudah dalam detik (e.g., "86400s" atau "86400")
        if (preg_match('/^(\d+)s?$/', $uptime, $matches)) {
            return (int)$matches[1];
        }
        
        // Parse format seperti "1d2h3m" atau "1d 2h 3m"
        if (preg_match('/(\d+)d/', $uptime, $matches)) {
            $seconds += (int)$matches[1] * 86400;
        }
        if (preg_match('/(\d+)h/', $uptime, $matches)) {
            $seconds += (int)$matches[1] * 3600;
        }
        if (preg_match('/(\d+)m/', $uptime, $matches)) {
            $seconds += (int)$matches[1] * 60;
        }
        if (preg_match('/(\d+)s/', $uptime, $matches)) {
            $seconds += (int)$matches[1];
        }
        
        return $seconds > 0 ? $seconds : null;
    }

    /**
     * Parse bytes string to integer (e.g., "100M" -> 104857600, "1G" -> 1073741824)
     */
    private function parseBytes(string $bytes): ?int
    {
        if (empty($bytes)) return null;
        
        $bytes = strtolower(trim($bytes));
        
        // Jika sudah dalam bytes (angka saja)
        if (preg_match('/^(\d+)$/', $bytes, $matches)) {
            return (int)$matches[1];
        }
        
        // Parse format seperti "100M", "1G", "500K"
        if (preg_match('/^(\d+)([kmg]?)$/', $bytes, $matches)) {
            $value = (int)$matches[1];
            $unit = $matches[2] ?? '';
            
            switch ($unit) {
                case 'k': return $value * 1024;
                case 'm': return $value * 1024 * 1024;
                case 'g': return $value * 1024 * 1024 * 1024;
                default: return $value;
            }
        }
        
        return null;
    }

    // ==================== HOTSPOT SERVER METHODS ====================

    /**
     * Get All Hotspot Servers from Mikrotik
     */
    public function getHotspotServers(): array
    {
        try {
            \Log::info("🔍 getHotspotServers: Starting query to Mikrotik");
            $query = new Query('/ip/hotspot/print');
            $servers = $this->client->query($query)->read();
            
            \Log::info("🔍 getHotspotServers: Got " . count($servers) . " servers from Mikrotik");
            
            $result = [];
            foreach ($servers as $server) {
                $serverName = $server['name'] ?? '';
                if (!empty($serverName)) {
                    $result[] = [
                        'id' => $server['.id'] ?? '',
                        'name' => $serverName,
                        'interface' => $server['interface'] ?? '',
                        'address_pool' => $server['address-pool'] ?? '',
                        'profile' => $server['profile'] ?? 'default',
                        'disabled' => isset($server['disabled']) && ($server['disabled'] === 'true' || $server['disabled'] === true),
                    ];
                    \Log::debug("📝 Found server: {$serverName}");
                }
            }
            
            \Log::info("✅ getHotspotServers: Returning " . count($result) . " servers");
            return $result;
        } catch (\Exception $e) {
            \Log::error("❌ Failed to get hotspot servers: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Create Hotspot Server
     */
    public function createHotspotServer(array $data): bool
    {
        try {
            $query = new Query('/ip/hotspot/add');
            $query->equal('name', $data['name']);
            $query->equal('interface', $data['interface']);
            
            if (isset($data['address_pool']) && !empty($data['address_pool'])) {
                $query->equal('address-pool', $data['address_pool']);
            }
            
            if (isset($data['profile']) && !empty($data['profile'])) {
                $query->equal('profile', $data['profile']);
            }
            
            if (isset($data['disabled']) && $data['disabled']) {
                $query->equal('disabled', 'yes');
            }
            
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create hotspot server: " . $e->getMessage());
            throw new \Exception("Failed to create hotspot server: " . $e->getMessage());
        }
    }

    /**
     * Update Hotspot Server
     */
    public function updateHotspotServer(string $serverId, array $data): bool
    {
        try {
            $query = new Query('/ip/hotspot/set');
            $query->equal('.id', $serverId);
            
            if (isset($data['name'])) {
                $query->equal('name', $data['name']);
            }
            
            if (isset($data['interface'])) {
                $query->equal('interface', $data['interface']);
            }
            
            if (isset($data['address_pool'])) {
                $query->equal('address-pool', $data['address_pool']);
            }
            
            if (isset($data['profile'])) {
                $query->equal('profile', $data['profile']);
            }
            
            if (isset($data['disabled'])) {
                $query->equal('disabled', $data['disabled'] ? 'yes' : 'no');
            }
            
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to update hotspot server: " . $e->getMessage());
            throw new \Exception("Failed to update hotspot server: " . $e->getMessage());
        }
    }

    /**
     * Delete Hotspot Server
     */
    public function deleteHotspotServer(string $serverId): bool
    {
        try {
            $query = new Query('/ip/hotspot/remove');
            $query->equal('.id', $serverId);
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete hotspot server: " . $e->getMessage());
            throw new \Exception("Failed to delete hotspot server: " . $e->getMessage());
        }
    }

    // ==================== HOTSPOT SERVER PROFILE METHODS ====================

    /**
     * Get All Hotspot Server Profiles from Mikrotik
     */
    public function getHotspotServerProfiles(): array
    {
        try {
            $query = new Query('/ip/hotspot/profile/print');
            $profiles = $this->client->query($query)->read();
            
            $result = [];
            foreach ($profiles as $profile) {
                $result[] = [
                    'id' => $profile['.id'] ?? '',
                    'name' => $profile['name'] ?? '',
                    'dns_name' => $profile['dns-name'] ?? '',
                    'html_directory' => $profile['html-directory'] ?? '',
                    'rate_limit' => $profile['rate-limit'] ?? '',
                    'session_timeout' => $profile['session-timeout'] ?? '',
                    'idle_timeout' => $profile['idle-timeout'] ?? '',
                    'keepalive_timeout' => $profile['keepalive-timeout'] ?? '',
                    'login_timeout' => $profile['login-timeout'] ?? '',
                    'http_chap' => isset($profile['http-chap']) && ($profile['http-chap'] === 'true' || $profile['http-chap'] === true),
                    'http_pap' => isset($profile['http-pap']) && ($profile['http-pap'] === 'true' || $profile['http-pap'] === true),
                    'https' => isset($profile['https']) && ($profile['https'] === 'true' || $profile['https'] === true),
                    'mac_auth' => isset($profile['mac-auth']) && ($profile['mac-auth'] === 'true' || $profile['mac-auth'] === true),
                    'cookie' => isset($profile['cookie']) && ($profile['cookie'] === 'true' || $profile['cookie'] === true),
                    'trial' => isset($profile['trial']) && ($profile['trial'] === 'true' || $profile['trial'] === true),
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get hotspot server profiles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create Hotspot Server Profile
     */
    public function createHotspotServerProfile(array $data): bool
    {
        try {
            $query = new Query('/ip/hotspot/profile/add');
            $query->equal('name', $data['name']);
            
            if (isset($data['dns_name']) && !empty($data['dns_name'])) {
                $query->equal('dns-name', $data['dns_name']);
            }
            
            if (isset($data['html_directory']) && !empty($data['html_directory'])) {
                $query->equal('html-directory', $data['html_directory']);
            }
            
            if (isset($data['rate_limit']) && !empty($data['rate_limit'])) {
                $query->equal('rate-limit', $data['rate_limit']);
            }
            
            if (isset($data['session_timeout']) && !empty($data['session_timeout'])) {
                $query->equal('session-timeout', $data['session_timeout']);
            }
            
            if (isset($data['idle_timeout']) && !empty($data['idle_timeout'])) {
                $query->equal('idle-timeout', $data['idle_timeout']);
            }
            
            if (isset($data['keepalive_timeout']) && !empty($data['keepalive_timeout'])) {
                $query->equal('keepalive-timeout', $data['keepalive_timeout']);
            }
            
            if (isset($data['login_timeout']) && !empty($data['login_timeout'])) {
                $query->equal('login-timeout', $data['login_timeout']);
            }
            
            // Authentication methods
            if (isset($data['http_chap'])) {
                $query->equal('http-chap', $data['http_chap'] ? 'yes' : 'no');
            }
            
            if (isset($data['http_pap'])) {
                $query->equal('http-pap', $data['http_pap'] ? 'yes' : 'no');
            }
            
            if (isset($data['https'])) {
                $query->equal('https', $data['https'] ? 'yes' : 'no');
            }
            
            if (isset($data['mac_auth'])) {
                $query->equal('mac-auth', $data['mac_auth'] ? 'yes' : 'no');
            }
            
            if (isset($data['cookie'])) {
                $query->equal('cookie', $data['cookie'] ? 'yes' : 'no');
            }
            
            if (isset($data['trial'])) {
                $query->equal('trial', $data['trial'] ? 'yes' : 'no');
            }
            
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create hotspot server profile: " . $e->getMessage());
            throw new \Exception("Failed to create hotspot server profile: " . $e->getMessage());
        }
    }

    /**
     * Update Hotspot Server Profile
     */
    public function updateHotspotServerProfile(string $profileId, array $data): bool
    {
        try {
            $query = new Query('/ip/hotspot/profile/set');
            $query->equal('.id', $profileId);
            
            if (isset($data['name'])) {
                $query->equal('name', $data['name']);
            }
            
            if (isset($data['dns_name'])) {
                $query->equal('dns-name', $data['dns_name']);
            }
            
            if (isset($data['html_directory'])) {
                $query->equal('html-directory', $data['html_directory']);
            }
            
            if (isset($data['rate_limit'])) {
                $query->equal('rate-limit', $data['rate_limit']);
            }
            
            if (isset($data['session_timeout'])) {
                $query->equal('session-timeout', $data['session_timeout']);
            }
            
            if (isset($data['idle_timeout'])) {
                $query->equal('idle-timeout', $data['idle_timeout']);
            }
            
            if (isset($data['keepalive_timeout'])) {
                $query->equal('keepalive-timeout', $data['keepalive_timeout']);
            }
            
            if (isset($data['login_timeout'])) {
                $query->equal('login-timeout', $data['login_timeout']);
            }
            
            // Authentication methods
            if (isset($data['http_chap'])) {
                $query->equal('http-chap', $data['http_chap'] ? 'yes' : 'no');
            }
            
            if (isset($data['http_pap'])) {
                $query->equal('http-pap', $data['http_pap'] ? 'yes' : 'no');
            }
            
            if (isset($data['https'])) {
                $query->equal('https', $data['https'] ? 'yes' : 'no');
            }
            
            if (isset($data['mac_auth'])) {
                $query->equal('mac-auth', $data['mac_auth'] ? 'yes' : 'no');
            }
            
            if (isset($data['cookie'])) {
                $query->equal('cookie', $data['cookie'] ? 'yes' : 'no');
            }
            
            if (isset($data['trial'])) {
                $query->equal('trial', $data['trial'] ? 'yes' : 'no');
            }
            
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to update hotspot server profile: " . $e->getMessage());
            throw new \Exception("Failed to update hotspot server profile: " . $e->getMessage());
        }
    }

    /**
     * Delete Hotspot Server Profile
     */
    public function deleteHotspotServerProfile(string $profileId): bool
    {
        try {
            $query = new Query('/ip/hotspot/profile/remove');
            $query->equal('.id', $profileId);
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete hotspot server profile: " . $e->getMessage());
            throw new \Exception("Failed to delete hotspot server profile: " . $e->getMessage());
        }
    }

    /**
     * Test Connection
     */
    public function testConnection(): array
    {
        try {
            $query = new Query('/system/resource/print');
            $response = $this->client->query($query)->read();

            if (empty($response)) {
                return [
                    'success' => false,
                    'message' => 'No response from router',
                ];
            }

            return [
                'success' => true,
                'message' => 'Router is online and responding',
                'data'    => $response[0] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create PPPoE User
     */
    /**
     * ✅ Create atau Update PPPoE User di Mikrotik
     * Jika user sudah ada: UPDATE profile dan password
     * Jika user belum ada: CREATE user baru
     */
    public function createPPPoEUser(string $username, string $password, string $profile = 'default'): bool
    {
        try {
            \Log::info("Creating/Updating PPPoE user in Mikrotik", [
                'username' => $username,
                'profile' => $profile,
                'router' => $this->router->name ?? 'N/A',
                'router_ip' => $this->router->ip_address ?? 'N/A'
            ]);
            
            // ✅ Pastikan profile ada di Mikrotik sebelum create/update user
            // Coba dengan where dulu
            $query = new Query('/ppp/profile/print');
            $query->where('name', $profile);
            $profiles = $this->client->query($query)->read();
            
            // Jika tidak ditemukan dengan where, coba tanpa where dan filter manual
            if (empty($profiles)) {
                $query = new Query('/ppp/profile/print');
                $allProfiles = $this->client->query($query)->read();
                $profiles = array_filter($allProfiles, function($p) use ($profile) {
                    return ($p['name'] ?? '') === $profile;
                });
                $profiles = array_values($profiles);
            }
            
            if (empty($profiles)) {
                \Log::error("Profile not found in Mikrotik", [
                    'profile' => $profile,
                    'username' => $username,
                    'total_profiles' => count($allProfiles ?? [])
                ]);
                throw new \Exception("Profile '{$profile}' tidak ditemukan di Mikrotik. Pastikan profile sudah dibuat terlebih dahulu!");
            }
            
            \Log::info("Profile found in Mikrotik", [
                'profile' => $profile,
                'profile_id' => $profiles[0]['.id'] ?? 'N/A'
            ]);

            // ✅ Cek apakah user sudah ada di Mikrotik
            // Coba dengan where dulu
            $query = new Query('/ppp/secret/print');
            $query->where('name', $username);
            $existing = $this->client->query($query)->read();
            
            // Jika tidak ditemukan dengan where, coba tanpa where dan filter manual
            if (empty($existing)) {
                $query = new Query('/ppp/secret/print');
                $allSecrets = $this->client->query($query)->read();
                $existing = array_filter($allSecrets, function($s) use ($username) {
                    return ($s['name'] ?? '') === $username;
                });
                $existing = array_values($existing);
            }

            if (!empty($existing)) {
                // ✅ User sudah ada: UPDATE profile dan password
                $existingProfile = $existing[0]['profile'] ?? 'N/A';
                $userId = $existing[0]['.id'];
                
                \Log::info("PPPoE user already exists in Mikrotik, updating...", [
                    'username' => $username,
                    'existing_profile' => $existingProfile,
                    'new_profile' => $profile,
                    'user_id' => $userId
                ]);
                
                // Update user dengan profile dan password baru
                $query = new Query('/ppp/secret/set');
                $query->equal('.id', $userId);
                $query->equal('profile', $profile);
                $query->equal('password', $password);
                $query->equal('service', 'pppoe');
                
                $result = $this->client->query($query)->read();
                
                \Log::info("PPPoE user updated in Mikrotik", [
                    'username' => $username,
                    'old_profile' => $existingProfile,
                    'new_profile' => $profile,
                    'result' => $result
                ]);
                
                // ✅ Verifikasi user benar-benar ter-update dengan profile yang benar
                $query = new Query('/ppp/secret/print');
                $query->where('name', $username);
                $verifyUsers = $this->client->query($query)->read();
                
                if (empty($verifyUsers)) {
                    throw new \Exception("User tidak ditemukan setelah update");
                }
                
                $actualProfile = $verifyUsers[0]['profile'] ?? 'N/A';
                if ($actualProfile !== $profile) {
                    \Log::error("Profile mismatch after updating user", [
                        'username' => $username,
                        'expected_profile' => $profile,
                        'actual_profile' => $actualProfile
                    ]);
                    throw new \Exception("Profile tidak sesuai setelah update! Expected: {$profile}, Actual: {$actualProfile}");
                }
                
                \Log::info("✅ PPPoE user updated successfully with correct profile", [
                    'username' => $username,
                    'profile' => $profile,
                    'verified_profile' => $actualProfile
                ]);
                
                return true;
            }

            // ✅ User belum ada: CREATE user baru
            \Log::info("PPPoE user not found in Mikrotik, creating new user...", [
                'username' => $username,
                'profile' => $profile
            ]);

            $query = new Query('/ppp/secret/add');
            $query->equal('name', $username);
            $query->equal('password', $password);
            $query->equal('profile', $profile);
            $query->equal('service', 'pppoe');

            $result = $this->client->query($query)->read();
            
            \Log::info("PPPoE user created in Mikrotik", [
                'username' => $username,
                'profile' => $profile,
                'result' => $result
            ]);
            
            // ✅ Verifikasi user benar-benar dibuat dengan profile yang benar
            $query = new Query('/ppp/secret/print');
            $query->where('name', $username);
            $verifyUsers = $this->client->query($query)->read();
            
            if (empty($verifyUsers)) {
                throw new \Exception("User tidak ditemukan setelah create");
            }
            
            $actualProfile = $verifyUsers[0]['profile'] ?? 'N/A';
            if ($actualProfile !== $profile) {
                \Log::error("Profile mismatch after creating user", [
                    'username' => $username,
                    'expected_profile' => $profile,
                    'actual_profile' => $actualProfile
                ]);
                throw new \Exception("Profile tidak sesuai! Expected: {$profile}, Actual: {$actualProfile}");
            }
            
            \Log::info("✅ PPPoE user created successfully with correct profile", [
                'username' => $username,
                'profile' => $profile,
                'verified_profile' => $actualProfile
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create/update PPPoE user in Mikrotik", [
                'username' => $username,
                'profile' => $profile,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Failed to create/update PPPoE user: " . $e->getMessage());
        }
    }

    /**
     * Delete PPPoE User
     */
    public function deletePPPoEUser(string $username): bool
    {
        try {
            \Log::info("Attempting to delete PPPoE user from Mikrotik", [
                'username' => $username,
                'router_ip' => $this->router->ip_address ?? 'N/A',
                'router_name' => $this->router->name ?? 'N/A'
            ]);
            
            // Find user - coba beberapa cara untuk memastikan menemukan user
            $query = new Query('/ppp/secret/print');
            $query->where('name', $username);
            $users = $this->client->query($query)->read();

            // Jika tidak ditemukan dengan where, coba tanpa where dan filter manual
            if (empty($users)) {
                $query = new Query('/ppp/secret/print');
                $allUsers = $this->client->query($query)->read();
                $users = array_filter($allUsers, function($user) use ($username) {
                    return ($user['name'] ?? '') === $username;
                });
                $users = array_values($users); // Re-index array
            }

            if (empty($users)) {
                \Log::info("PPPoE user not found in Mikrotik (already deleted or never existed)", [
                    'username' => $username,
                    'searched_in' => count($allUsers ?? []) . ' total users'
                ]);
                return true; // Already deleted
            }

            $userId = $users[0]['.id'];
            \Log::info("Found PPPoE user in Mikrotik", [
                'username' => $username,
                'user_id' => $userId,
                'profile' => $users[0]['profile'] ?? 'N/A',
                'service' => $users[0]['service'] ?? 'N/A'
            ]);

            // ✅ Delete user dengan retry jika perlu
            $query = new Query('/ppp/secret/remove');
            $query->equal('.id', $userId);
            $result = $this->client->query($query)->read();
            
            \Log::info("Delete command executed", [
                'username' => $username,
                'user_id' => $userId,
                'result' => $result
            ]);

            // ✅ Verifikasi bahwa user benar-benar terhapus (dengan retry dan multiple methods)
            $maxRetries = 3;
            $retryCount = 0;
            $verifyUsers = [];
            
            while ($retryCount < $maxRetries) {
                if ($retryCount > 0) {
                    sleep(1); // Tunggu 1 detik untuk memastikan delete selesai
                }
                
                // Coba dengan where
                $query = new Query('/ppp/secret/print');
                $query->where('name', $username);
                $verifyUsers = $this->client->query($query)->read();
                
                // Jika tidak ditemukan dengan where, coba tanpa where dan filter manual
                if (empty($verifyUsers)) {
                    $query = new Query('/ppp/secret/print');
                    $allUsers = $this->client->query($query)->read();
                    $verifyUsers = array_filter($allUsers, function($user) use ($username) {
                        return ($user['name'] ?? '') === $username;
                    });
                    $verifyUsers = array_values($verifyUsers);
                }
                
                if (empty($verifyUsers)) {
                    // User sudah terhapus
                    break;
                }
                
                $retryCount++;
                \Log::warning("User still exists after deletion, retrying verification...", [
                    'username' => $username,
                    'retry' => $retryCount,
                    'max_retries' => $maxRetries,
                    'remaining_user_id' => $verifyUsers[0]['.id'] ?? 'N/A'
                ]);
            }

            if (!empty($verifyUsers)) {
                \Log::error("PPPoE user still exists after deletion attempt and verification", [
                    'username' => $username,
                    'user_id' => $userId,
                    'remaining_user_id' => $verifyUsers[0]['.id'] ?? 'N/A',
                    'retries' => $retryCount
                ]);
                throw new \Exception("User masih ada di Mikrotik setelah delete. User ID: " . ($verifyUsers[0]['.id'] ?? 'N/A'));
            }

            \Log::info("✅ PPPoE user successfully deleted from Mikrotik", [
                'username' => $username,
                'user_id' => $userId,
                'verification_retries' => $retryCount
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete PPPoE user from Mikrotik", [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Failed to delete PPPoE user: " . $e->getMessage());
        }
    }

    /**
     * ✅ CREATE profile jika belum ada, TIDAK UPDATE jika sudah ada
     */
    public function ensurePackageProfile(Package $package): bool
    {
        $name = $package->name;
        $rate = "{$package->upload_speed}M/{$package->download_speed}M";

        // Cek apakah profile sudah ada
        $query = new Query('/ppp/profile/print');
        $query->where('name', $name);
        $profiles = $this->client->query($query)->read();

        if (!empty($profiles)) {
            // ✅ Profile sudah ada, TIDAK UPDATE - hanya return true
            \Log::info("Profile already exists in Mikrotik, skipping creation", [
                'profile' => $name
            ]);
            return true;
        }

        // ✅ CREATE profile jika belum ada
        $query = new Query('/ppp/profile/add');
        $query->equal('name', $name);
        $query->equal('rate-limit', $rate);
        $this->client->query($query)->read();

        \Log::info("Profile created in Mikrotik", [
            'profile' => $name,
            'rate_limit' => $rate
        ]);

        return true;
    }

    /**
     * Create/Update PPPoE Profile (for bandwidth control)
     */
    public function createProfile(string $profileName, float $downloadSpeed, float $uploadSpeed): bool
    {
        try {
            // Format rate: upload/download (RouterOS style)
            $downloadLimit = $downloadSpeed . 'M';
            $uploadLimit   = $uploadSpeed . 'M';

            // Check if profile exists
            $query = new Query('/ppp/profile/print');
            $query->where('name', $profileName);
            $existing = $this->client->query($query)->read();

            if (!empty($existing)) {
                // Update existing profile
                $query = new Query('/ppp/profile/set');
                $query->equal('.id', $existing[0]['.id']);
                $query->equal('rate-limit', $uploadLimit . '/' . $downloadLimit);
                $this->client->query($query)->read();
            } else {
                // Create new profile
                $query = new Query('/ppp/profile/add');
                $query->equal('name', $profileName);
                $query->equal('rate-limit', $uploadLimit . '/' . $downloadLimit);
                $this->client->query($query)->read();
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create/update profile: " . $e->getMessage());
        }
    }

    /**
     * Change User Speed
     */
    public function changeUserSpeed(string $username, float $downloadSpeed, float $uploadSpeed): bool
    {
        try {
            $profileName = "profile_" . $downloadSpeed . "M";

            // Create/Update profile
            $this->createProfile($profileName, $downloadSpeed, $uploadSpeed);

            // Update user profile
            $query = new Query('/ppp/secret/print');
            $query->where('name', $username);
            $users = $this->client->query($query)->read();

            if (empty($users)) {
                throw new \Exception("User not found");
            }

            $query = new Query('/ppp/secret/set');
            $query->equal('.id', $users[0]['.id']);
            $query->equal('profile', $profileName);
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to change user speed: " . $e->getMessage());
        }
    }

    /**
     * Get Active PPPoE Sessions
     */
    public function getActiveSessions(): array
    {
        try {
            $query = new Query('/ppp/active/print');
            return $this->client->query($query)->read();
        } catch (\Exception $e) {
            throw new \Exception("Failed to get active sessions: " . $e->getMessage());
        }
    }

    /**
     * Disconnect User
     */
    public function disconnectUser(string $username): bool
    {
        try {
            $query = new Query('/ppp/active/print');
            $query->where('name', $username);
            $sessions = $this->client->query($query)->read();

            if (empty($sessions)) {
                return true; // Not connected
            }

            $query = new Query('/ppp/active/remove');
            $query->equal('.id', $sessions[0]['.id']);
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to disconnect user: " . $e->getMessage());
        }
    }

    /**
     * Disable PPPoE user (suspend)
     */
    public function disablePPPoEUser(string $username): bool
    {
        $users = $this->client->query('/ppp/secret/print', [
            '?name' => $username,
        ])->read();

        if (empty($users)) {
            throw new \Exception("User {$username} not found");
        }

        $userId = $users[0]['.id'];

        $this->client->query('/ppp/secret/set', [
            '.id'      => $userId,
            'disabled' => 'yes',
        ])->read();

        return true;
    }

    /**
     * Enable PPPoE user (activate)
     */
    public function enablePPPoEUser(string $username): bool
    {
        $users = $this->client->query('/ppp/secret/print', [
            '?name' => $username,
        ])->read();

        if (empty($users)) {
            throw new \Exception("User {$username} not found");
        }

        $userId = $users[0]['.id'];

        $this->client->query('/ppp/secret/set', [
            '.id'      => $userId,
            'disabled' => 'no',
        ])->read();

        return true;
    }

    /**
     * Get all PPPoE Profiles from Mikrotik with rate-limit
     */
    public function getPPPoEProfiles(): array
    {
        try {
            $query = new Query('/ppp/profile/print');
            $profiles = $this->client->query($query)->read();
            
            $result = [];
            foreach ($profiles as $profile) {
                $rateLimit = $profile['rate-limit'] ?? '';
                $parsedRate = $this->parseRateLimit($rateLimit);
                
                $result[] = [
                    'name' => $profile['name'] ?? '',
                    'rate_limit' => $rateLimit,
                    'download_speed' => $parsedRate['download'], // Mbps
                    'upload_speed' => $parsedRate['upload'], // Mbps
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get PPPoE profiles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse rate-limit from Mikrotik format to Mbps
     * Format: "10M/5M" (upload/download) or "10240000/5120000" (bytes/sec)
     */
    private function parseRateLimit(string $rateLimit): array
    {
        $default = ['download' => 5, 'upload' => 2]; // Default jika tidak ada
        
        if (empty($rateLimit)) {
            return $default;
        }
        
        // Split by slash: upload/download
        $parts = explode('/', $rateLimit);
        if (count($parts) !== 2) {
            return $default;
        }
        
        $uploadStr = trim($parts[0]);
        $downloadStr = trim($parts[1]);
        
        // Parse upload speed
        $upload = $this->parseSpeedToMbps($uploadStr);
        
        // Parse download speed
        $download = $this->parseSpeedToMbps($downloadStr);
        
        return [
            'download' => $download,
            'upload' => $upload,
        ];
    }

    /**
     * Parse speed string to Mbps
     * Supports: "10M", "10Mbps", "10240000" (bytes/sec), "1G", etc.
     */
    private function parseSpeedToMbps(string $speed): float
    {
        if (empty($speed)) {
            return 0;
        }
        
        $speed = trim($speed);
        
        // Jika format seperti "10M" atau "10Mbps"
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(K|M|G)?(?:bps|BPS)?$/i', $speed, $matches)) {
            $value = (float) $matches[1];
            $unit = strtoupper($matches[2] ?? 'M');
            
            switch ($unit) {
                case 'K':
                    return $value / 1000; // Kbps to Mbps
                case 'M':
                    return $value; // Already Mbps
                case 'G':
                    return $value * 1000; // Gbps to Mbps
                default:
                    return $value;
            }
        }
        
        // Jika format numeric (bytes/sec), convert to Mbps
        if (is_numeric($speed)) {
            $bytesPerSec = (float) $speed;
            $mbps = ($bytesPerSec * 8) / 1000000; // bytes/sec to Mbps
            return round($mbps, 2);
        }
        
        // Default jika tidak bisa di-parse
        return 0;
    }

    /**
     * Ambil semua PPPoE secret (dipakai fitur lain)
     */
    public function getPPPoESecrets(Router $router)
    {
        try {
            $client = new Client([
                'host' => $router->ip_address,
                'user' => $router->username,
                'pass' => $router->password,
                'port' => $router->api_port ?? 8728,
            ]);

            $query    = new Query('/ppp/secret/print');
            $response = $client->query($query)->read();

            return collect($response)->map(function ($item) {
                return [
                    'name'           => $item['name'] ?? '',
                    'password'       => $item['password'] ?? '',
                    'service'        => $item['service'] ?? '',
                    'profile'        => $item['profile'] ?? '',
                    'local_address'  => $item['local-address'] ?? '',
                    'remote_address' => $item['remote-address'] ?? '',
                    'disabled'       => $item['disabled'] ?? 'false',
                    'comment'        => $item['comment'] ?? '',
                ];
            });
        } catch (\Exception $e) {
            \Log::error("Failed to get PPPoE secrets from router {$router->name}: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Get all PPPoE secrets from current router (instance method)
     */
    public function getAllPPPoESecrets()
    {
        try {
            $query = new Query('/ppp/secret/print');
            $response = $this->client->query($query)->read();

            return collect($response)->map(function ($item) {
                return [
                    'name'           => $item['name'] ?? '',
                    'password'       => $item['password'] ?? '',
                    'service'        => $item['service'] ?? '',
                    'profile'        => $item['profile'] ?? '',
                    'local_address'  => $item['local-address'] ?? '',
                    'remote_address' => $item['remote-address'] ?? '',
                    'disabled'       => $item['disabled'] ?? 'false',
                    'comment'        => $item['comment'] ?? '',
                ];
            });
        } catch (\Exception $e) {
            \Log::error("Failed to get PPPoE secrets from router {$this->router->name}: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Get Router System Info
     */
    public function getSystemInfo(): array
    {
        try {
            $query    = new Query('/system/resource/print');
            $resource = $this->client->query($query)->read();

            $query    = new Query('/system/identity/print');
            $identity = $this->client->query($query)->read();

            return [
                'identity'     => $identity[0]['name'] ?? 'Unknown',
                'uptime'       => $resource[0]['uptime'] ?? 'N/A',
                'version'      => $resource[0]['version'] ?? 'N/A',
                'cpu_load'     => $resource[0]['cpu-load'] ?? 0,
                'free_memory'  => $resource[0]['free-memory'] ?? 0,
                'total_memory' => $resource[0]['total-memory'] ?? 0,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to get system info: " . $e->getMessage());
        }
    }

    /**
     * Get active PPPoE sessions dari /ppp/active (active session)
     */
    public function getActivePPPoESessions()
    {
        $maxRetries = 3;
        $retryCount = 0;
        
        while ($retryCount <= $maxRetries) {
            try {
                // ✅ AMBIL DARI /ppp/active - active session
                // ✅ GUNAKAN CLIENT DENGAN TIMEOUT LEBIH PANJANG untuk memastikan semua data terbaca
                $client = $this->getClientWithLongTimeout();
                
                // ✅ JANGAN gunakan proplist - ambil semua field untuk memastikan data lengkap
                // ✅ Query sederhana tanpa filter untuk mendapatkan semua data
                $query = new Query('/ppp/active/print');
                
                $startTime = microtime(true);
                
                // ✅ BACA SEMUA DATA DENGAN KONSISTEN
                // ✅ Pastikan semua data terbaca dengan lengkap - baca langsung semua response
                $activeSessions = $client->query($query)->read();
                
                // ✅ VALIDASI: Pastikan response adalah array
                if (!is_array($activeSessions)) {
                    throw new \Exception("Invalid response from Mikrotik API - expected array, got: " . gettype($activeSessions));
                }
                
                $queryTime = round((microtime(true) - $startTime) * 1000, 2); // dalam ms
                
                // ✅ VALIDASI: Pastikan tidak ada data yang hilang
                // Jika response kosong tapi seharusnya ada data, mungkin ada masalah
                if (empty($activeSessions) && $retryCount < $maxRetries) {
                    \Log::warning("⚠️ Empty response from Mikrotik, retrying...", [
                        'router' => $this->router->name ?? 'unknown',
                        'retry_count' => $retryCount + 1
                    ]);
                    $retryCount++;
                    usleep(500000);
                    continue;
                }
                
                // ✅ VALIDASI KONSISTENSI: Baca ulang untuk memastikan data stabil
                // ✅ Jika data berubah terlalu drastis antara pembacaan, berarti ada masalah
                if ($retryCount < $maxRetries && count($activeSessions) > 0) {
                    // Baca sekali lagi untuk validasi konsistensi
                    usleep(100000); // Tunggu 0.1 detik (dipercepat)
                    $validationStartTime = microtime(true);
                    $validationQuery = new Query('/ppp/active/print');
                    $validationSessions = $client->query($validationQuery)->read();
                    $validationTime = round((microtime(true) - $validationStartTime) * 1000, 2);
                    
                    if (is_array($validationSessions)) {
                        $countDiff = abs(count($activeSessions) - count($validationSessions));
                        $diffPercentage = count($activeSessions) > 0 
                            ? ($countDiff / count($activeSessions)) * 100 
                            : 0;
                        
                        // Jika perbedaan lebih dari 5%, data mungkin tidak konsisten
                        if ($diffPercentage > 5 && count($activeSessions) > 50) {
                            // ✅ Hapus logging - fokus pada akurasi
                            $retryCount++;
                            usleep(200000); // Tunggu 0.2 detik sebelum retry (dipercepat)
                            continue;
                        }
                        
                        // Gunakan data yang lebih lengkap (yang lebih banyak)
                        if (count($validationSessions) > count($activeSessions)) {
                            $activeSessions = $validationSessions;
                            $queryTime = $validationTime;
                        }
                    }
                }
                
                // ✅ Log query time untuk monitoring
                if ($queryTime > 5000) { // Jika lebih dari 5 detik
                    \Log::warning("⚠️ Slow query detected", [
                        'router' => $this->router->name ?? 'unknown',
                        'query_time_ms' => $queryTime,
                        'total_sessions' => count($activeSessions)
                    ]);
                }
                
                // ✅ Filter hanya PPPoE sessions dan normalize dengan lebih ketat
            $pppoeSessions = collect($activeSessions)->filter(function ($session) {
                $service = strtolower(trim($session['service'] ?? ''));
                return $service === 'pppoe';
            })->map(function ($session) {
                $name = trim($session['name'] ?? '');
                // ✅ Pastikan name tidak kosong dan valid
                if (empty($name)) {
                    return null;
                }
                
                return [
                    'name'                => $name,
                    'service'             => $session['service'] ?? 'pppoe',
                    'address'             => $session['address'] ?? '',
                    'uptime'              => $session['uptime'] ?? '',
                    'idle-time'           => $session['idle-time'] ?? '',
                    'bytes'               => $session['bytes'] ?? '',
                    'packets'             => $session['packets'] ?? '',
                    'caller-id'           => $session['caller-id'] ?? '',
                    'profile'             => $session['profile'] ?? '',
                ];
            })->filter(function ($session) {
                // Hanya return yang valid (bukan null)
                return $session !== null && !empty($session['name']);
            })->values(); // Re-index array untuk konsistensi
            
            // ✅ NORMALISASI KONSISTEN: Normalize semua username dengan cara yang sama
            // ✅ Ini memastikan duplicate detection konsisten dan menghindari fluktuasi
            $normalizedSessions = [];
            $usernameCounts = [];
            
            foreach ($pppoeSessions as $session) {
                $name = $session['name'] ?? '';
                if (empty($name)) {
                    continue;
                }
                
                // ✅ NORMALISASI KONSISTEN: lowercase, trim, hapus karakter kontrol
                $normalized = strtolower(trim($name));
                $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized); // Hapus karakter kontrol
                
                // ✅ Gunakan normalized sebagai key untuk menghindari duplicate
                if (!isset($normalizedSessions[$normalized])) {
                    $normalizedSessions[$normalized] = $session;
                    $usernameCounts[$normalized] = 1;
                } else {
                    // Duplicate ditemukan - increment count
                    $usernameCounts[$normalized]++;
                }
            }
            
            // ✅ Convert kembali ke collection dengan data yang sudah di-deduplicate
            $pppoeSessions = collect(array_values($normalizedSessions));
            
            // ✅ Log duplicate jika ada
            $duplicates = array_filter($usernameCounts, function($count) {
                return $count > 1;
            });
            
            if (!empty($duplicates)) {
                \Log::warning("⚠️ Duplicate usernames found in active sessions", [
                    'router' => $this->router->name ?? 'unknown',
                    'duplicate_count' => count($duplicates),
                    'duplicates' => array_keys($duplicates),
                    'total_duplicate_entries' => array_sum($duplicates) - count($duplicates) // Total entries yang dihapus
                ]);
            }
            
            // ✅ Untuk logging, gunakan normalized usernames
            $uniqueUsernames = array_keys($normalizedSessions);
            
            // ✅ Log untuk debugging dengan detail lebih lengkap
            // ✅ VALIDASI: Pastikan data lengkap (jika raw sessions < 50, mungkin ada masalah)
            $isDataComplete = count($activeSessions) >= 50; // Threshold minimal untuk validasi
            
            // ✅ Hash untuk cek konsistensi data - jika hash sama berarti data sama
            $sortedUsernames = $uniqueUsernames;
            sort($sortedUsernames);
            $dataHash = md5(implode(',', $sortedUsernames));
            
            \Log::info("Retrieved active PPPoE sessions from /ppp/active", [
                'router' => $this->router->name ?? 'unknown',
                'total_raw_sessions' => count($activeSessions),
                'total_pppoe_sessions_before_dedup' => collect($activeSessions)->filter(function ($s) {
                    return strtolower(trim($s['service'] ?? '')) === 'pppoe' && !empty(trim($s['name'] ?? ''));
                })->count(),
                'total_pppoe_sessions_after_dedup' => $pppoeSessions->count(),
                'unique_usernames' => count($uniqueUsernames),
                'duplicate_count' => count($duplicates),
                'data_complete' => $isDataComplete,
                'query_time_ms' => $queryTime ?? 0,
                'retry_count' => $retryCount,
                'data_hash' => $dataHash, // ✅ Hash untuk cek konsistensi - bandingkan hash di log untuk melihat apakah data benar-benar berubah
                'sample_sessions' => array_slice($uniqueUsernames, 0, 10)
            ]);
            
            // ✅ WARNING: Jika data terlihat tidak lengkap (terlalu sedikit untuk router besar)
            if (!$isDataComplete && count($activeSessions) > 0) {
                \Log::warning("⚠️ Possible incomplete data from /ppp/active", [
                    'router' => $this->router->name ?? 'unknown',
                    'total_raw_sessions' => count($activeSessions),
                    'note' => 'Data mungkin tidak lengkap karena timeout atau error'
                ]);
            }
            
                return $pppoeSessions;
                
            } catch (\Exception $e) {
                $retryCount++;
                
                // ✅ RETRY jika masih ada kesempatan dan error bukan fatal
                if ($retryCount <= $maxRetries && (
                    strpos($e->getMessage(), 'timeout') !== false ||
                    strpos($e->getMessage(), 'Connection') !== false
                )) {
                    \Log::warning("⚠️ Error getting active sessions, retrying...", [
                        'router' => $this->router->name ?? 'unknown',
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount
                    ]);
                    usleep(1000000 * $retryCount); // Exponential backoff: 1s, 2s
                    continue;
                }
                
                \Log::error("Failed to get active PPPoE sessions from /ppp/active", [
                    'router' => $this->router->name ?? 'unknown',
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount
                ]);
                throw new \Exception('Failed to get active PPPoE sessions from /ppp/active: ' . $e->getMessage());
            }
        }
        
        // Fallback: return empty jika semua retry gagal
        \Log::error("All retries failed for getActivePPPoESessions", [
            'router' => $this->router->name ?? 'unknown'
        ]);
        return collect([]);
    }

    /**
     * Cek apakah PPPoE user sedang online
     * Case-insensitive matching untuk username
     */
    public function isPPPoEUserOnline(string $username): bool
    {
        try {
            $activeSessions = $this->getActivePPPoESessions();
            
            // Case-insensitive matching
            $usernameLower = strtolower(trim($username));
            
            foreach ($activeSessions as $session) {
                $sessionName = strtolower(trim($session['name'] ?? ''));
                if ($sessionName === $usernameLower) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            \Log::error("Failed to check PPPoE user online status: " . $e->getMessage(), [
                'username' => $username,
                'router' => $this->router->name ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Get PPPoE user profile dari MikroTik
     * Return profile name atau null jika tidak ditemukan
     */
    public function getPPPoEUserProfile(string $username): ?string
    {
        try {
            $query = new Query('/ppp/secret/print');
            $query->where('name', $username);
            $users = $this->client->query($query)->read();

            if (empty($users)) {
                return null;
            }

            return $users[0]['profile'] ?? null;
        } catch (\Exception $e) {
            \Log::error("Failed to get PPPoE user profile: " . $e->getMessage(), [
                'username' => $username,
                'router' => $this->router->name ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Get Selling Report dari system script
     * Format data: $date-|-$time-|-$user-|-$price-|-$address-|-$mac-|-$validity-|-$name-|-$comment
     * Filter: comment="mikhmon" untuk semua, owner="dec2025" untuk bulan, source="dec/01/2025" untuk hari
     * OPTIMIZED: Menggunakan cache, limit, dan optimasi query
     */
    public function getSellingReport(?string $date = null, ?string $month = null, ?string $prefix = null, ?int $limit = null): array
    {
        try {
            // Cache key berdasarkan filter
            $cacheKey = 'selling_report_' . $this->router->id . '_' . md5(serialize([$date, $month, $prefix, $limit]));
            
            // ✅ OPTIMASI: Cache lebih lama (15 menit) untuk mengurangi query ke Mikrotik
            return \Cache::remember($cacheKey, 900, function() use ($date, $month, $prefix, $limit) {
                \Log::info("Fetching selling report from Mikrotik", [
                    'router' => $this->router->name,
                    'date' => $date,
                    'month' => $month,
                    'prefix' => $prefix
                ]);
                
                $startTime = microtime(true);
                
                // Gunakan client dengan timeout lebih panjang untuk query besar
                $client = $this->getClientWithLongTimeout();
                
                $query = new Query('/system/script/print');
                
                // Filter berdasarkan parameter
                // Note: Library RouterOS tidak support proplist(), jadi ambil semua field
                // Untuk /system/script/print field-nya tidak terlalu banyak, jadi tidak masalah
                if ($date) {
                    // Filter by date (source field) - format: "dec/01/2025"
                    $query->where('source', $date);
                } elseif ($month) {
                    // Filter by month (owner field) - format: "dec2025"
                    $query->where('owner', $month);
                } else {
                    // Get all dengan comment="mikhmon"
                    $query->where('comment', 'mikhmon');
                }
                
                $scripts = $client->query($query)->read();
                $queryTime = microtime(true) - $startTime;
                
                \Log::info("Selling report query completed", [
                    'router' => $this->router->name,
                    'count' => count($scripts),
                    'query_time' => round($queryTime, 2) . 's'
                ]);
                
                // Jika tidak ada hasil dengan filter, coba tanpa filter comment untuk kompatibilitas
                if (empty($scripts) && !$date && !$month) {
                    $query = new Query('/system/script/print');
                    $scripts = $client->query($query)->read();
                    // Filter manual untuk comment="mikhmon"
                    $scripts = array_filter($scripts, function($script) {
                        return ($script['comment'] ?? '') === 'mikhmon';
                    });
                    $scripts = array_values($scripts);
                }
                
                $reports = [];
                $total = 0;
                $parseStartTime = microtime(true);
                $processedCount = 0;
                $skippedCount = 0;
                
                // ✅ OPTIMASI: Default limit 300 untuk performa lebih cepat
                // Tapi jika limit null, ambil semua data (untuk kompatibilitas)
                $actualLimit = $limit;
                // ✅ OPTIMASI: Batasi max process untuk performa (max 1000 untuk lebih cepat)
                $maxProcess = $actualLimit ? min($actualLimit * 2, 1000) : min(count($scripts), 1000);
                $scriptsToProcess = array_slice($scripts, 0, $maxProcess);
                
                \Log::info("Processing scripts for selling report", [
                    'total_scripts' => count($scripts),
                    'scripts_to_process' => count($scriptsToProcess),
                    'limit' => $actualLimit
                ]);
                
                foreach ($scriptsToProcess as $script) {
                    // ✅ Early exit jika sudah mencapai limit (hanya jika limit di-set)
                    if ($actualLimit && $processedCount >= $actualLimit) {
                        break;
                    }
                    
                    $name = $script['name'] ?? '';
                    
                    // ✅ OPTIMASI: Skip jika name kosong atau terlalu pendek (tidak valid)
                    if (empty($name) || strlen($name) < 20) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // ✅ OPTIMASI: Early check untuk prefix sebelum parsing (jika prefix ada)
                    if ($prefix && strpos($name, $prefix) === false) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Parse format: $date-|-$time-|-$user-|-$price-|-$address-|-$mac-|-$validity-|-$name-|-$comment
                    // ✅ OPTIMASI: Limit explode untuk performa
                    $parts = explode('-|-', $name, 9);
                    
                    if (count($parts) >= 9) {
                        $reportDate = $parts[0] ?? '';
                        $time = $parts[1] ?? '';
                        $username = $parts[2] ?? '';
                        $price = (int)($parts[3] ?? 0);
                        $address = $parts[4] ?? '';
                        $mac = $parts[5] ?? '';
                        $validity = $parts[6] ?? '';
                        $profile = $parts[7] ?? '';
                        $comment = $parts[8] ?? '';
                        
                        // Filter by prefix jika ada (double check setelah parsing)
                        if ($prefix && strpos($username, $prefix) !== 0) {
                            $skippedCount++;
                            continue;
                        }
                        
                        $reports[] = [
                            'date' => $reportDate,
                            'time' => $time,
                            'username' => $username,
                            'price' => $price,
                            'address' => $address,
                            'mac' => $mac,
                            'validity' => $validity,
                            'profile' => $profile,
                            'comment' => $comment,
                            'script_id' => $script['.id'] ?? '',
                        ];
                        
                        $total += $price;
                        $processedCount++;
                        
                    } else {
                        $skippedCount++;
                    }
                }
                
                $parseTime = microtime(true) - $parseStartTime;
                
                // Sort by date and time (newest first) - hanya jika ada data
                // ✅ OPTIMASI: Skip sort jika data kecil untuk performa lebih cepat
                if (!empty($reports) && count($reports) > 100) {
                    // Hanya sort jika data lebih dari 100
                    $dates = [];
                    foreach ($reports as $report) {
                        $dates[] = strtotime($report['date'] . ' ' . $report['time']) ?: 0;
                    }
                    array_multisort($dates, SORT_DESC, $reports);
                }
                // Jika data <= 100, skip sort untuk performa (data sudah terurut dari query)
                
                // Apply limit jika ada (sudah di-handle di loop, tapi double check)
                if ($actualLimit && $actualLimit > 0 && count($reports) > $actualLimit) {
                    $reports = array_slice($reports, 0, $actualLimit);
                }
                
                \Log::info("Selling report parsing completed", [
                    'router' => $this->router->name,
                    'total_scripts' => count($scripts),
                    'scripts_processed' => count($scriptsToProcess),
                    'processed' => $processedCount,
                    'skipped' => $skippedCount,
                    'total_reports' => count($reports),
                    'total_price' => $total,
                    'parse_time' => round($parseTime, 2) . 's',
                    'total_time' => round($queryTime + $parseTime, 2) . 's',
                    'limit_applied' => $actualLimit ?? 'none'
                ]);
                
                return [
                    'reports' => $reports,
                    'total' => $total,
                    'count' => count($reports),
                ];
            });
        } catch (\Exception $e) {
            \Log::error("Failed to get selling report: " . $e->getMessage(), [
                'router' => $this->router->name ?? 'unknown',
                'date' => $date,
                'month' => $month,
                'prefix' => $prefix,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'reports' => [],
                'total' => 0,
                'count' => 0,
            ];
        }
    }

    /**
     * Delete Selling Report berdasarkan date, month, atau semua
     */
    public function deleteSellingReport(?string $date = null, ?string $month = null): bool
    {
        try {
            $query = new Query('/system/script/print');
            
            // Filter berdasarkan parameter
            if ($date) {
                $query->where('source', $date);
            } elseif ($month) {
                $query->where('owner', $month);
            } else {
                $query->where('comment', 'mikhmon');
            }
            
            $scripts = $this->client->query($query)->read();
            
            // Delete semua script yang ditemukan
            foreach ($scripts as $script) {
                $scriptId = $script['.id'] ?? '';
                if ($scriptId) {
                    $deleteQuery = new Query('/system/script/remove');
                    $deleteQuery->equal('.id', $scriptId);
                    $this->client->query($deleteQuery)->read();
                }
            }
            
            \Log::info("Selling report deleted", [
                'date' => $date,
                'month' => $month,
                'deleted_count' => count($scripts)
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete selling report: " . $e->getMessage());
            throw new \Exception("Failed to delete selling report: " . $e->getMessage());
        }
    }

    /**
     * Get detail PPPoE interface untuk customer tertentu
     * Mengembalikan detail lengkap termasuk remote address, uptime, last link up/down
     */
    public function getPPPoEInterfaceDetail(string $username): ?array
    {
        try {
            // ✅ GUNAKAN CLIENT DENGAN TIMEOUT LEBIH PANJANG untuk query interface
            // Interface print bisa lambat jika banyak interface
            $interfaceClient = $this->getClientWithLongTimeout();
            
            // Cari interface PPPoE berdasarkan username
            $interfaces = $interfaceClient->query('/interface/print')->read();
            
            // Cari interface yang sesuai dengan username
            $pppoeInterface = null;
            $usernameLower = strtolower(trim($username));
            
            foreach ($interfaces as $if) {
                $interfaceName = $if['name'] ?? '';
                $type = $if['type'] ?? '';
                
                // Interface PPPoE format: <pppoe-{username}> dengan type pppoe-in
                if ($type === 'pppoe-in' || strpos($interfaceName, 'pppoe-') === 0) {
                    // Extract username dari interface name
                    $cleanName = trim($interfaceName, '<>');
                    if (preg_match('/pppoe-(.+)/', $cleanName, $matches)) {
                        $interfaceUsername = strtolower(trim($matches[1]));
                        if ($interfaceUsername === $usernameLower) {
                            $pppoeInterface = $if;
                            break;
                        }
                    }
                }
            }
            
            if (!$pppoeInterface) {
                return null;
            }
            
            // Ambil remote address dari /ppp/active jika interface running
            $remoteAddress = '';
            $running = $pppoeInterface['running'] ?? false;
            $isRunning = $running === true || $running === 'true' || $running === 'yes';
            
            if ($isRunning) {
                try {
                    // ✅ GUNAKAN CLIENT DENGAN TIMEOUT LEBIH PANJANG untuk /ppp/active
                    $activeClient = $this->getClientWithLongTimeout();
                    
                    // Cari di /ppp/active untuk mendapatkan remote address
                    $query = new Query('/ppp/active/print');
                    $query->where('name', $username);
                    $activeSessions = $activeClient->query($query)->read();
                    
                    if (!empty($activeSessions)) {
                        // Remote address ada di field 'address' di /ppp/active
                        $remoteAddress = $activeSessions[0]['address'] ?? '';
                    } else {
                        // Jika tidak ditemukan di /ppp/active, coba ambil dari interface detail
                        // Beberapa versi RouterOS menyimpan remote address di interface
                        $remoteAddress = $pppoeInterface['address'] ?? '';
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to get remote address from /ppp/active", [
                        'username' => $username,
                        'error' => $e->getMessage()
                    ]);
                    $remoteAddress = '';
                }
            } else {
                $remoteAddress = '';
            }
            
            // Ambil traffic stats jika interface running
            $traffic = null;
            if ($isRunning && isset($pppoeInterface['name'])) {
                try {
                    // Ambil traffic stats dari interface monitoring menggunakan nama interface
                    $interfaceName = $pppoeInterface['name'];
                    // Hapus angle brackets jika ada
                    $cleanInterfaceName = trim($interfaceName, '<>');
                    
                    // ✅ GUNAKAN CLIENT DENGAN TIMEOUT LEBIH PANJANG untuk monitor-traffic
                    // Monitor-traffic bisa lambat, jadi perlu timeout lebih panjang
                    $monitorClient = $this->getClientWithLongTimeout();
                    
                    // Coba ambil dari monitor-traffic dulu dengan retry mechanism
                    $maxRetries = 2;
                    $retryCount = 0;
                    $trafficData = null;
                    
                    while ($retryCount <= $maxRetries && $trafficData === null) {
                        try {
                            $query = new Query('/interface/monitor-traffic');
                            $query->equal('interface', $cleanInterfaceName);
                            $query->equal('once');
                            
                            \Log::debug("Attempting monitor-traffic query", [
                                'username' => $username,
                                'interface' => $cleanInterfaceName,
                                'retry' => $retryCount
                            ]);
                            
                            $trafficData = $monitorClient->query($query)->read();
                            
                            if (!empty($trafficData)) {
                                break; // Berhasil, keluar dari loop
                            }
                        } catch (\Exception $e) {
                            $retryCount++;
                            $isTimeout = strpos(strtolower($e->getMessage()), 'timeout') !== false || 
                                        strpos(strtolower($e->getMessage()), 'timed out') !== false;
                            
                            if ($isTimeout && $retryCount <= $maxRetries) {
                                \Log::warning("Monitor-traffic timeout, retrying...", [
                                    'username' => $username,
                                    'interface' => $cleanInterfaceName,
                                    'retry' => $retryCount,
                                    'max_retries' => $maxRetries
                                ]);
                                usleep(500000); // Wait 0.5 second before retry
                                continue;
                            } else {
                                // Jika bukan timeout atau sudah max retries, throw exception
                                throw $e;
                            }
                        }
                    }
                    
                    // Jika berhasil mendapatkan data
                    if ($trafficData !== null && !empty($trafficData)) {
                        \Log::debug("Monitor-traffic response", [
                            'username' => $username,
                            'interface' => $cleanInterfaceName,
                            'data_count' => count($trafficData),
                            'data_keys' => !empty($trafficData[0]) ? array_keys($trafficData[0]) : []
                        ]);
                        
                        if (!empty($trafficData) && isset($trafficData[0])) {
                            $data = $trafficData[0];
                            
                            // Debug: log semua keys dan values yang tersedia
                            \Log::info("Monitor-traffic data keys dan values", [
                                'username' => $username,
                                'interface' => $cleanInterfaceName,
                                'all_keys' => array_keys($data),
                                'raw_data' => $data // Log semua data untuk debugging
                            ]);
                            
                            // Cari field rate dengan berbagai kemungkinan nama
                            $txRate = 0;
                            $rxRate = 0;
                            
                            // Coba berbagai kemungkinan field name untuk rate
                            $txRateFields = ['tx-bits-per-second', 'tx-bits', 'tx-bits-per-sec', 'tx-bps', 'tx-rate'];
                            $rxRateFields = ['rx-bits-per-second', 'rx-bits', 'rx-bits-per-sec', 'rx-bps', 'rx-rate'];
                            
                            foreach ($txRateFields as $field) {
                                if (isset($data[$field])) {
                                    $txRate = (int)$data[$field];
                                    \Log::info("Found Tx Rate in field: {$field} = {$txRate}");
                                    break;
                                }
                            }
                            
                            foreach ($rxRateFields as $field) {
                                if (isset($data[$field])) {
                                    $rxRate = (int)$data[$field];
                                    \Log::info("Found Rx Rate in field: {$field} = {$rxRate}");
                                    break;
                                }
                            }
                            
                            $traffic = [
                                'tx_bytes' => isset($data['tx-bytes']) ? (int)$data['tx-bytes'] : 0,
                                'rx_bytes' => isset($data['rx-bytes']) ? (int)$data['rx-bytes'] : 0,
                                'tx_packets' => isset($data['tx-packets']) ? (int)$data['tx-packets'] : 0,
                                'rx_packets' => isset($data['rx-packets']) ? (int)$data['rx-packets'] : 0,
                                'tx_drops' => isset($data['tx-drops']) ? (int)$data['tx-drops'] : 0,
                                'rx_drops' => isset($data['rx-drops']) ? (int)$data['rx-drops'] : 0,
                                'tx_errors' => isset($data['tx-errors']) ? (int)$data['tx-errors'] : 0,
                                'rx_errors' => isset($data['rx-errors']) ? (int)$data['rx-errors'] : 0,
                                'tx_rate' => $txRate,
                                'rx_rate' => $rxRate,
                            ];
                            
                            \Log::info("Traffic data extracted", [
                                'username' => $username,
                                'interface' => $cleanInterfaceName,
                                'tx_rate' => $traffic['tx_rate'],
                                'rx_rate' => $traffic['rx_rate'],
                                'tx_bytes' => $traffic['tx_bytes'],
                                'rx_bytes' => $traffic['rx_bytes'],
                                'tx_packets' => $traffic['tx_packets'],
                                'rx_packets' => $traffic['rx_packets']
                            ]);
                        } else {
                            \Log::warning("Monitor-traffic returned empty data", [
                                'username' => $username,
                                'interface' => $cleanInterfaceName,
                                'data_count' => count($trafficData)
                            ]);
                        }
                    } else {
                        // Tidak ada data dari monitor-traffic
                        \Log::info("Monitor-traffic returned no data, will use interface stats", [
                            'username' => $username,
                            'interface' => $cleanInterfaceName
                        ]);
                    }
                    
                    // Jika monitor-traffic tidak berhasil atau tidak ada rate, ambil dari interface stats langsung
                    if (!$traffic) {
                        \Log::info("Using interface stats (monitor-traffic failed or returned no data)", [
                            'username' => $username,
                            'interface' => $cleanInterfaceName
                        ]);
                        
                        $traffic = [
                            'tx_bytes' => isset($pppoeInterface['tx-byte']) ? (int)$pppoeInterface['tx-byte'] : 0,
                            'rx_bytes' => isset($pppoeInterface['rx-byte']) ? (int)$pppoeInterface['rx-byte'] : 0,
                            'tx_packets' => isset($pppoeInterface['tx-packet']) ? (int)$pppoeInterface['tx-packet'] : 0,
                            'rx_packets' => isset($pppoeInterface['rx-packet']) ? (int)$pppoeInterface['rx-packet'] : 0,
                            'tx_drops' => isset($pppoeInterface['tx-drop']) ? (int)$pppoeInterface['tx-drop'] : 0,
                            'rx_drops' => isset($pppoeInterface['rx-drop']) ? (int)$pppoeInterface['rx-drop'] : 0,
                            'tx_errors' => isset($pppoeInterface['tx-error']) ? (int)$pppoeInterface['tx-error'] : 0,
                            'rx_errors' => isset($pppoeInterface['rx-error']) ? (int)$pppoeInterface['rx-error'] : 0,
                            'tx_rate' => 0, // Rate hanya tersedia dari monitor-traffic
                            'rx_rate' => 0, // Rate hanya tersedia dari monitor-traffic
                        ];
                    } else {
                        // Pastikan rate selalu ada, bahkan jika 0
                        if (!isset($traffic['tx_rate']) || $traffic['tx_rate'] === null) {
                            $traffic['tx_rate'] = 0;
                        }
                        if (!isset($traffic['rx_rate']) || $traffic['rx_rate'] === null) {
                            $traffic['rx_rate'] = 0;
                        }
                        
                        \Log::info("Traffic data ready (from monitor-traffic)", [
                            'username' => $username,
                            'tx_rate' => $traffic['tx_rate'],
                            'rx_rate' => $traffic['rx_rate'],
                            'has_rate_data' => ($traffic['tx_rate'] > 0 || $traffic['rx_rate'] > 0)
                        ]);
                    }
                } catch (\Exception $e) {
                    $isTimeout = strpos(strtolower($e->getMessage()), 'timeout') !== false || 
                                strpos(strtolower($e->getMessage()), 'timed out') !== false;
                    
                    \Log::warning("Failed to get traffic stats", [
                        'username' => $username,
                        'interface' => $pppoeInterface['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'is_timeout' => $isTimeout
                    ]);
                    
                    // ✅ Jika timeout, coba ambil dari interface stats sebagai fallback
                    if ($isTimeout) {
                        \Log::info("Timeout occurred, using interface stats as fallback", [
                            'username' => $username,
                            'interface' => $pppoeInterface['name'] ?? 'unknown'
                        ]);
                        
                        $traffic = [
                            'tx_bytes' => isset($pppoeInterface['tx-byte']) ? (int)$pppoeInterface['tx-byte'] : 0,
                            'rx_bytes' => isset($pppoeInterface['rx-byte']) ? (int)$pppoeInterface['rx-byte'] : 0,
                            'tx_packets' => isset($pppoeInterface['tx-packet']) ? (int)$pppoeInterface['tx-packet'] : 0,
                            'rx_packets' => isset($pppoeInterface['rx-packet']) ? (int)$pppoeInterface['rx-packet'] : 0,
                            'tx_drops' => isset($pppoeInterface['tx-drop']) ? (int)$pppoeInterface['tx-drop'] : 0,
                            'rx_drops' => isset($pppoeInterface['rx-drop']) ? (int)$pppoeInterface['rx-drop'] : 0,
                            'tx_errors' => isset($pppoeInterface['tx-error']) ? (int)$pppoeInterface['tx-error'] : 0,
                            'rx_errors' => isset($pppoeInterface['rx-error']) ? (int)$pppoeInterface['rx-error'] : 0,
                            'tx_rate' => 0, // Rate tidak tersedia dari interface stats
                            'rx_rate' => 0, // Rate tidak tersedia dari interface stats
                        ];
                    } else {
                        // Set default traffic dengan nilai 0 untuk error selain timeout
                        $traffic = [
                            'tx_bytes' => 0,
                            'rx_bytes' => 0,
                            'tx_packets' => 0,
                            'rx_packets' => 0,
                            'tx_drops' => 0,
                            'rx_drops' => 0,
                            'tx_errors' => 0,
                            'rx_errors' => 0,
                            'tx_rate' => 0,
                            'rx_rate' => 0,
                        ];
                    }
                }
            } else {
                // Interface tidak running, set traffic null atau default
                $traffic = null;
            }
            
            return [
                'interface_name' => $pppoeInterface['name'] ?? '',
                'uptime' => $pppoeInterface['uptime'] ?? '',
                'remote_address' => $remoteAddress,
                'last_link_up_time' => $pppoeInterface['last-link-up-time'] ?? '',
                'last_link_down_time' => $pppoeInterface['last-link-down-time'] ?? '',
                'running' => $isRunning,
                'status' => $pppoeInterface['status'] ?? '',
                'mtu' => $pppoeInterface['mtu'] ?? '',
                'mru' => $pppoeInterface['mru'] ?? '',
                'traffic' => $traffic,
                'is_online' => $isRunning, // Status online/offline
            ];
        } catch (\Exception $e) {
            \Log::error("Failed to get PPPoE interface detail: " . $e->getMessage(), [
                'username' => $username,
                'router' => $this->router->name ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Cek apakah profile adalah isolir profile
     */
    public function isIsolirProfile(?string $profileName, string $isolirProfileName = 'PROFIL-ISOLIR'): bool
    {
        if (empty($profileName)) {
            return false;
        }
        
        // Case-insensitive check
        $profileLower = strtolower(trim($profileName));
        $isolirLower = strtolower(trim($isolirProfileName));
        
        // Exact match atau contains "isolir"
        return $profileLower === $isolirLower || strpos($profileLower, 'isolir') !== false;
    }

    /**
     * Ganti profil PPPoE user (untuk auto isolir)
     */
    public function setUserProfile(string $username, string $profile): bool
    {
        try {
            // Cari user berdasarkan username
            $query = new Query('/ppp/secret/print');
            $query->where('name', $username);
            $users = $this->client->query($query)->read();

            if (empty($users)) {
                throw new \Exception("User {$username} tidak ditemukan di router");
            }

            // Ambil ID user
            $userId = $users[0]['.id'];

            // Set profil baru
            $query = new Query('/ppp/secret/set');
            $query->equal('.id', $userId);
            $query->equal('profile', $profile);
            $this->client->query($query)->read();

            // Disconnect session aktif agar profil baru langsung aktif
            $this->disconnectUser($username);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Gagal mengganti profil PPPoE untuk {$username}: " . $e->getMessage());
        }
    }

    /**
     * Sinkronisasi Package (Laravel) -> PPP Profile Mikrotik
     * Format Mikrotik rate-limit: upload/download (contoh: "5M/10M")
     */
    /**
     * Sync/Update PPPoE Profile - UPDATE jika sudah ada
     */
    public function syncPackageProfile(Package $package): bool
    {
        $name = $package->name;
        // Format Mikrotik: upload/download (bukan download/upload)
        $rate = "{$package->upload_speed}M/{$package->download_speed}M";

        // cek apakah profile sudah ada
        $query    = new Query('/ppp/profile/print');
        $profiles = $this->client->query($query)->read();

        foreach ($profiles as $p) {
            if (($p['name'] ?? null) === $name) {
                // ✅ UPDATE profile jika sudah ada
                $query = new Query('/ppp/profile/set');
                $query->equal('.id', $p['.id']);
                $query->equal('rate-limit', $rate);
                $this->client->query($query)->read();
                \Log::info("PPPoE profile updated in Mikrotik", [
                    'profile' => $name,
                    'rate' => $rate
                ]);
                return true;
            }
        }

        // add profile kalau belum ada
        $query = new Query('/ppp/profile/add');
        $query->equal('name', $name);
        $query->equal('rate-limit', $rate);
        $this->client->query($query)->read();

        \Log::info("PPPoE profile created in Mikrotik", [
            'profile' => $name,
            'rate' => $rate
        ]);

        return true;
    }


    /**
     * Sinkronisasi Customer PPPoE -> PPP Secret di Mikrotik
     */
    public function syncCustomerPppoe(Customer $customer): bool
    {
        // ✅ Prioritaskan customer_mikrotik_username, fallback ke connection_config
        $config   = is_string($customer->connection_config) 
            ? json_decode($customer->connection_config, true) 
            : ($customer->connection_config ?? []);
        
        $username = $customer->customer_mikrotik_username ?? $config['username'] ?? null;
        $password = $customer->customer_mikrotik_password ?? $config['password'] ?? null;
        $profile  = $customer->package->name ?? 'default';

        if (!$username || !$profile) {
            // tidak cukup data, skip
            \Log::warning("Cannot sync PPPoE customer - missing username or profile", [
                'customer' => $customer->customer_code,
                'has_username' => $username ? 'yes' : 'no',
                'has_password' => $password ? 'yes' : 'no',
                'profile' => $profile
            ]);
            return false;
        }

        // ✅ Cek secret sudah ada
        $query = new Query('/ppp/secret/print');
        $query->where('name', $username);
        $secrets = $this->client->query($query)->read();

        if (!empty($secrets)) {
            // ✅ Update existing secret - SELALU update password dan profile
            $existingSecret = $secrets[0];
            $existingProfile = $existingSecret['profile'] ?? null;
            $needsUpdate = false;
            
            // Cek apakah perlu update
            if ($password && ($existingSecret['password'] ?? null) !== $password) {
                $needsUpdate = true;
            }
            if ($existingProfile !== $profile) {
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $query = new Query('/ppp/secret/set');
                $query->equal('.id', $existingSecret['.id']);
                if ($password) {
                    $query->equal('password', $password);
                }
                $query->equal('profile', $profile);
                $this->client->query($query)->read();
                
                \Log::info("✅ PPPoE secret updated in Mikrotik", [
                    'username' => $username,
                    'profile_changed' => $existingProfile !== $profile ? "{$existingProfile} -> {$profile}" : 'no',
                    'password_changed' => $password ? 'yes' : 'no'
                ]);
            } else {
                \Log::info("PPPoE secret already up-to-date in Mikrotik", [
                    'username' => $username,
                    'profile' => $profile
                ]);
            }
        } else {
            // ✅ Buat secret baru jika belum ada
            $query = new Query('/ppp/secret/add');
            $query->equal('name', $username);
            if ($password) {
                $query->equal('password', $password);
            }
            $query->equal('service', 'pppoe');
            $query->equal('profile', $profile);
            $this->client->query($query)->read();
            
            \Log::info("✅ PPPoE secret created in Mikrotik", [
                'username' => $username,
                'profile' => $profile
            ]);
        }

        return true;
    }

    /**
     * Create Hotspot User
     */
    public function createHotspotUser(string $username, string $password, string $profile = 'default', array $options = []): bool
    {
        try {
            // Check if user exists
            $query = new Query('/ip/hotspot/user/print');
            $query->where('name', $username);
            $existing = $this->client->query($query)->read();

            if (!empty($existing)) {
                throw new \Exception("Hotspot user already exists");
            }

            // Create hotspot user
            $query = new Query('/ip/hotspot/user/add');
            $query->equal('name', $username);
            $query->equal('password', $password);
            $query->equal('profile', $profile);
            
            // Optional fields
            // ⚠️ PRIORITAS: limit_uptime dari Time Limit lebih tinggi dari expires_at
            // Jika limit_uptime sudah ada, JANGAN override dengan expires_at
            if (isset($options['limit_uptime']) && !empty($options['limit_uptime'])) {
                // Time Limit lebih prioritas - langsung set ke limit-uptime
                $query->equal('limit-uptime', $this->formatUptime($options['limit_uptime']));
            } elseif (isset($options['expires_at']) && !empty($options['expires_at'])) {
                // Jika tidak ada Time Limit, baru gunakan expires_at untuk calculate limit-uptime
                // Ini untuk kompatibilitas dengan Mikhmon style
                try {
                    $expiryDate = new \DateTime($options['expires_at']);
                    $now = new \DateTime();
                    $diff = $now->diff($expiryDate);
                    $totalSeconds = ($diff->days * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
                    if ($totalSeconds > 0) {
                        $query->equal('limit-uptime', $this->formatUptime($totalSeconds));
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to parse expires_at: " . $e->getMessage());
                }
            }
            
            if (isset($options['limit_bytes_total']) && !empty($options['limit_bytes_total'])) {
                $query->equal('limit-bytes-total', $this->formatBytesForMikrotik($options['limit_bytes_total']));
            }
            
            // Rate limit (override profile rate limit jika di-set)
            if (isset($options['rate_limit']) && !empty($options['rate_limit'])) {
                $query->equal('rate-limit', $options['rate_limit']);
            }
            
            // ✅ Server (all atau spesifik)
            if (isset($options['server']) && !empty($options['server'])) {
                $query->equal('server', $options['server']);
            }
            
            if (isset($options['comment']) && !empty($options['comment'])) {
                $query->equal('comment', $options['comment']);
            }
            
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Format uptime in seconds to Mikrotik format (e.g., 3600 -> "1h", 86400 -> "1d")
     */
    private function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return intval($seconds / 60) . 'm';
        } elseif ($seconds < 86400) {
            return intval($seconds / 3600) . 'h';
        } else {
            $days = intval($seconds / 86400);
            $hours = intval(($seconds % 86400) / 3600);
            if ($hours > 0) {
                return $days . 'd' . $hours . 'h';
            }
            return $days . 'd';
        }
    }

    /**
     * Format bytes to Mikrotik format (e.g., 104857600 -> "100M")
     */
    private function formatBytesForMikrotik(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        } elseif ($bytes < 1048576) {
            return intval($bytes / 1024) . 'K';
        } elseif ($bytes < 1073741824) {
            return intval($bytes / 1048576) . 'M';
        } else {
            return number_format($bytes / 1073741824, 2) . 'G';
        }
    }

    /**
     * Update Hotspot User
     */
    public function updateHotspotUser(string $username, string $profile = null, array $options = []): bool
    {
        try {
            // Find user
            $query = new Query('/ip/hotspot/user/print');
            $query->where('name', $username);
            $users = $this->client->query($query)->read();

            if (empty($users)) {
                throw new \Exception("Hotspot user not found");
            }

            $userId = $users[0]['.id'];

            // Update hotspot user
            $query = new Query('/ip/hotspot/user/set');
            $query->equal('.id', $userId);
            
            if ($profile !== null) {
                $query->equal('profile', $profile);
            }
            
            if (isset($options['password']) && !empty($options['password'])) {
                $query->equal('password', $options['password']);
            }
            
            // ✅ Server (all atau spesifik)
            if (isset($options['server']) && !empty($options['server'])) {
                $query->equal('server', $options['server']);
            }
            
            if (isset($options['comment'])) {
                $query->equal('comment', $options['comment']);
            }
            
            if (isset($options['limit_uptime']) && !empty($options['limit_uptime'])) {
                $query->equal('limit-uptime', $this->formatUptime($options['limit_uptime']));
            } elseif (isset($options['expires_at']) && !empty($options['expires_at'])) {
                try {
                    $expiryDate = new \DateTime($options['expires_at']);
                    $now = new \DateTime();
                    $diff = $now->diff($expiryDate);
                    $totalSeconds = ($diff->days * 24 * 3600) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
                    if ($totalSeconds > 0) {
                        $query->equal('limit-uptime', $this->formatUptime($totalSeconds));
                    }
                } catch (\Exception $e) {
                    // Ignore date parsing errors
                }
            }
            
            if (isset($options['limit_bytes_total']) && !empty($options['limit_bytes_total'])) {
                $query->equal('limit-bytes-total', (string)$options['limit_bytes_total']);
            }
            
            if (isset($options['disabled']) && $options['disabled']) {
                $query->equal('disabled', 'yes');
            } elseif (isset($options['disabled']) && !$options['disabled']) {
                $query->equal('disabled', 'no');
            }
            
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to update hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Delete Hotspot User
     */
    public function deleteHotspotUser(string $username): bool
    {
        try {
            \Log::info("Attempting to delete Hotspot user from Mikrotik", [
                'username' => $username
            ]);
            
            $query = new Query('/ip/hotspot/user/print');
            $query->where('name', $username);
            $users = $this->client->query($query)->read();

            if (empty($users)) {
                \Log::info("Hotspot user not found in Mikrotik (already deleted or never existed)", [
                    'username' => $username
                ]);
                return true; // Already deleted
            }

            $userId = $users[0]['.id'];
            \Log::info("Found Hotspot user in Mikrotik", [
                'username' => $username,
                'user_id' => $userId,
                'profile' => $users[0]['profile'] ?? 'N/A'
            ]);

            $query = new Query('/ip/hotspot/user/remove');
            $query->equal('.id', $userId);
            $this->client->query($query)->read();

            // ✅ Verifikasi bahwa user benar-benar terhapus
            $query = new Query('/ip/hotspot/user/print');
            $query->where('name', $username);
            $verifyUsers = $this->client->query($query)->read();

            if (!empty($verifyUsers)) {
                \Log::error("Hotspot user still exists after deletion attempt", [
                    'username' => $username,
                    'user_id' => $userId
                ]);
                throw new \Exception("User masih ada di Mikrotik setelah delete");
            }

            \Log::info("✅ Hotspot user successfully deleted from Mikrotik", [
                'username' => $username,
                'user_id' => $userId
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete Hotspot user from Mikrotik", [
                'username' => $username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception("Failed to delete hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Create Static IP Address
     */
    public function createStaticIP(string $ipAddress, string $interface = 'bridge'): bool
    {
        try {
            // Check if IP exists
            $query = new Query('/ip/address/print');
            $query->where('address', $ipAddress);
            $existing = $this->client->query($query)->read();

            if (!empty($existing)) {
                throw new \Exception("IP Address already exists");
            }

            // Add static IP
            $query = new Query('/ip/address/add');
            $query->equal('address', $ipAddress);
            $query->equal('interface', $interface);
            
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create static IP: " . $e->getMessage());
        }
    }

    /**
     * Delete Static IP Address
     */
    public function deleteStaticIP(string $ipAddress): bool
    {
        try {
            $query = new Query('/ip/address/print');
            $query->where('address', $ipAddress);
            $addresses = $this->client->query($query)->read();

            if (empty($addresses)) {
                return true; // Already deleted
            }

            $query = new Query('/ip/address/remove');
            $query->equal('.id', $addresses[0]['.id']);
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to delete static IP: " . $e->getMessage());
        }
    }

    /**
     * Create DHCP Lease (Static Binding)
     */
    public function createDHCPLease(string $macAddress, string $ipAddress, string $comment = ''): bool
    {
        try {
            // Check if lease exists
            $query = new Query('/ip/dhcp-server/lease/print');
            $query->where('mac-address', $macAddress);
            $existing = $this->client->query($query)->read();

            if (!empty($existing)) {
                // Update existing lease
                $query = new Query('/ip/dhcp-server/lease/set');
                $query->equal('.id', $existing[0]['.id']);
                $query->equal('address', $ipAddress);
                if ($comment) {
                    $query->equal('comment', $comment);
                }
                $this->client->query($query)->read();
            } else {
                // Create new lease
                $query = new Query('/ip/dhcp-server/lease/add');
                $query->equal('mac-address', $macAddress);
                $query->equal('address', $ipAddress);
                $query->equal('always-broadcast', 'yes');
                if ($comment) {
                    $query->equal('comment', $comment);
                }
                
                $this->client->query($query)->read();
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create DHCP lease: " . $e->getMessage());
        }
    }

    /**
     * Delete DHCP Lease
     */
    public function deleteDHCPLease(string $macAddress): bool
    {
        try {
            $query = new Query('/ip/dhcp-server/lease/print');
            $query->where('mac-address', $macAddress);
            $leases = $this->client->query($query)->read();

            if (empty($leases)) {
                return true; // Already deleted
            }

            $query = new Query('/ip/dhcp-server/lease/remove');
            $query->equal('.id', $leases[0]['.id']);
            $this->client->query($query)->read();

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to delete DHCP lease: " . $e->getMessage());
        }
    }

    /**
     * Create Hotspot Profile (untuk bandwidth control)
     */
    /**
     * ✅ CREATE hotspot profile jika belum ada, TIDAK UPDATE jika sudah ada
     */
    public function createHotspotProfile(string $profileName, float $downloadSpeed, float $uploadSpeed): bool
    {
        try {
            $downloadLimit = $downloadSpeed . 'M';
            $uploadLimit   = $uploadSpeed . 'M';
            $rateLimit     = $uploadLimit . '/' . $downloadLimit;

            // Check if profile exists
            $query = new Query('/ip/hotspot/user/profile/print');
            $query->where('name', $profileName);
            $existing = $this->client->query($query)->read();

            if (!empty($existing)) {
                // ✅ Profile sudah ada, TIDAK UPDATE - hanya return true
                \Log::info("Hotspot profile already exists in Mikrotik, skipping update", [
                    'profile' => $profileName
                ]);
                return true;
            }

            // ✅ CREATE profile jika belum ada
            $query = new Query('/ip/hotspot/user/profile/add');
            $query->equal('name', $profileName);
            $query->equal('rate-limit', $rateLimit);
            $this->client->query($query)->read();

            \Log::info("Hotspot profile created in Mikrotik", [
                'profile' => $profileName,
                'rate_limit' => $rateLimit
            ]);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to create hotspot profile: " . $e->getMessage());
        }
    }

    /**
     * Get single Hotspot User Profile by name or ID
     */
    public function getHotspotUserProfile(string $profileNameOrId, ?Router $router = null): ?array
    {
        try {
            $routerToUse = $router ?? $this->router;
            if (!$routerToUse) {
                throw new \Exception("Router not specified");
            }
            
            // Buat client baru dengan router yang diberikan
            $client = new Client([
                'host' => $routerToUse->ip_address,
                'user' => $routerToUse->username,
                'pass' => $routerToUse->password,
                'port' => $routerToUse->api_port ?? 8728,
                'timeout' => 5,
            ]);
            
            $query = new Query('/ip/hotspot/user/profile/print');
            // Check if it's an ID (starts with *) or name
            if (substr($profileNameOrId, 0, 1) == '*') {
                $query->where('.id', $profileNameOrId);
            } else {
                $query->where('name', $profileNameOrId);
            }
            $profiles = $client->query($query)->read();

            if (empty($profiles)) {
                \Log::warning("Profile '{$profileNameOrId}' not found on router {$routerToUse->name}");
                return null;
            }

            $profile = $profiles[0];
            $onLogin = $profile['on-login'] ?? '';
            
            // Parse on-login script seperti mikhmon menggunakan explode(",", $ponlogin)
            // Format mikhmon: :put (",rem,1000,8h,2000,,Enable,");
            // Ketika di-explode dengan koma: [0]=:put ("", [1]=rem, [2]=1000, [3]=8h, [4]=2000, [5]="", [6]=Enable, [7]="");
            $expiredMode = '0';
            $validity = '';
            $price = '0';
            $sellingPrice = '0';
            $lockUser = 'Disable';
            
            if (!empty($onLogin)) {
                // Parse seperti mikhmon: explode(",", $ponlogin)
                $parts = explode(',', $onLogin);
                
                // Index sesuai dengan mikhmon:
                // [1] = expired mode (rem, ntf, remc, ntfc, atau 0)
                // [2] = price
                // [3] = validity
                // [4] = selling price
                // [6] = lock user (Enable/Disable)
                if (count($parts) >= 7) {
                    $expiredMode = trim($parts[1] ?? '0');
                    $price = trim($parts[2] ?? '0');
                    $validity = trim($parts[3] ?? '');
                    $sellingPrice = trim($parts[4] ?? '0');
                    $lockUser = trim($parts[6] ?? 'Disable');
                    
                    // Clean up values (remove quotes and extra characters)
                    $expiredMode = trim($expiredMode, '"');
                    $price = trim($price, '"');
                    $validity = trim($validity, '"');
                    $sellingPrice = trim($sellingPrice, '"');
                    $lockUser = trim($lockUser, '"');
                    
                    // Handle empty values like mikhmon
                    if ($price == "0" || $price == "") {
                        $price = "0";
                    }
                    if ($sellingPrice == "0" || $sellingPrice == "") {
                        $sellingPrice = "0";
                    }
                    if ($lockUser == "" || empty($lockUser)) {
                        $lockUser = "Disable";
                    }
                }
            }
            
            // Map expired mode
            $expiredModeText = 'None';
            if ($expiredMode == 'rem') {
                $expiredModeText = 'Remove';
            } elseif ($expiredMode == 'ntf') {
                $expiredModeText = 'Notice';
            } elseif ($expiredMode == 'remc') {
                $expiredModeText = 'Remove & Record';
            } elseif ($expiredMode == 'ntfc') {
                $expiredModeText = 'Notice & Record';
            }
            
            // Check scheduler status
            $scheduler = $this->getScheduler($profile['name'] ?? '', $routerToUse);
            $monColor = 'text-orange';
            if ($scheduler && (!isset($scheduler['disabled']) || $scheduler['disabled'] !== 'true')) {
                $monColor = 'text-green';
            }
            
            return [
                'id' => $profile['.id'] ?? '',
                'name' => $profile['name'] ?? '',
                'address_pool' => $profile['address-pool'] ?? 'none',
                'shared_users' => $profile['shared-users'] ?? '1',
                'rate_limit' => $profile['rate-limit'] ?? '',
                'session_timeout' => $profile['session-timeout'] ?? '',
                'idle_timeout' => $profile['idle-timeout'] ?? 'none',
                'keepalive_timeout' => $profile['keepalive-timeout'] ?? '',
                'status_autorefresh' => $profile['status-autorefresh'] ?? '',
                'parent_queue' => $profile['parent-queue'] ?? 'none',
                'expired_mode' => $expiredMode,
                'expired_mode_text' => $expiredModeText,
                'validity' => $validity,
                'price' => $price,
                'selling_price' => $sellingPrice,
                'lock_user' => $lockUser,
                'monitor_color' => $monColor,
            ];
        } catch (\Exception $e) {
            \Log::error("Failed to get hotspot user profile: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update Hotspot User Profile
     */
    public function updateHotspotUserProfile(string $profileId, array $data): bool
    {
        try {
            $query = new Query('/ip/hotspot/user/profile/set');
            $query->equal('.id', $profileId);

            if (isset($data['name'])) {
                $query->equal('name', $data['name']);
            }

            if (isset($data['address_pool'])) {
                if (empty($data['address_pool'])) {
                    $query->equal('address-pool', '');
                } else {
                    $query->equal('address-pool', $data['address_pool']);
                }
            }

            if (isset($data['shared_users'])) {
                $query->equal('shared-users', (int)$data['shared_users']);
            }

            if (isset($data['rate_limit']) && !empty($data['rate_limit'])) {
                $query->equal('rate-limit', $data['rate_limit']);
            }

            if (isset($data['session_timeout'])) {
                if (empty($data['session_timeout'])) {
                    $query->equal('session-timeout', '');
                } else {
                    $query->equal('session-timeout', $data['session_timeout']);
                }
            }

            if (isset($data['idle_timeout'])) {
                if (empty($data['idle_timeout']) || $data['idle_timeout'] === 'none') {
                    $query->equal('idle-timeout', '');
                } else {
                    $query->equal('idle-timeout', $data['idle_timeout']);
                }
            }

            if (isset($data['keepalive_timeout'])) {
                if (empty($data['keepalive_timeout'])) {
                    $query->equal('keepalive-timeout', '');
                } else {
                    $query->equal('keepalive-timeout', $data['keepalive_timeout']);
                }
            }

            if (isset($data['status_autorefresh'])) {
                if (empty($data['status_autorefresh'])) {
                    $query->equal('status-autorefresh', '');
                } else {
                    $query->equal('status-autorefresh', $data['status_autorefresh']);
                }
            }

            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to update hotspot user profile: " . $e->getMessage());
        }
    }

    /**
     * Get IP Pools from Mikrotik
     */
    public function getIPPools(): array
    {
        try {
            $query = new Query('/ip/pool/print');
            $pools = $this->client->query($query)->read();
            
            $result = [];
            foreach ($pools as $pool) {
                $result[] = [
                    'name' => $pool['name'] ?? '',
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get IP pools: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Queues from Mikrotik
     */
    public function getQueues(): array
    {
        try {
            $query = new Query('/queue/simple/print');
            $query->where('dynamic', 'false');
            $queues = $this->client->query($query)->read();
            
            $result = [];
            foreach ($queues as $queue) {
                $result[] = [
                    'name' => $queue['name'] ?? '',
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get queues: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Scheduler by name
     */
    public function getScheduler(string $name, ?Router $router = null): ?array
    {
        try {
            $routerToUse = $router ?? $this->router;
            if (!$routerToUse) {
                return null;
            }
            
            // Use existing client if same router, otherwise create new client
            if ($routerToUse->id === $this->router->id) {
                $client = $this->client;
            } else {
                $client = new Client([
                    'host' => $routerToUse->ip_address,
                    'user' => $routerToUse->username,
                    'pass' => $routerToUse->password,
                    'port' => $routerToUse->api_port ?? 8728,
                    'timeout' => 5,
                ]);
            }
            
            $query = new Query('/system/scheduler/print');
            $query->where('name', $name);
            $schedulers = $client->query($query)->read();
            
            if (empty($schedulers)) {
                return null;
            }
            
            return $schedulers[0];
        } catch (\Exception $e) {
            \Log::error("Failed to get scheduler: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Hotspot User Profile dengan fitur lengkap Mikhmon
     * Support: expired mode, validity, price, lock user, scheduler, on-login script
     */
    public function createHotspotUserProfile(array $data): string
    {
        try {
            $name = preg_replace('/\s+/', '-', $data['name']);
            $sharedUsers = $data['shared_users'] ?? 1;
            $rateLimit = $data['rate_limit'] ?? '';
            $expMode = $data['expired_mode'] ?? '0';
            $validity = $data['validity'] ?? '';
            $price = $data['price'] ?? '0';
            $sellingPrice = $data['selling_price'] ?? '0';
            $addressPool = $data['address_pool'] ?? 'none';
            $parentQueue = $data['parent_queue'] ?? 'none';
            $lockUser = $data['lock_user'] ?? 'Disable';

            // Check if profile exists
            $query = new Query('/ip/hotspot/user/profile/print');
            $query->where('name', $name);
            $existing = $this->client->query($query)->read();

            if (!empty($existing)) {
                throw new \Exception("Profile '{$name}' sudah ada");
            }

            // Build lock script
            $lock = '';
            if ($lockUser === 'Enable') {
                $lock = '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]';
            }

            // Build record script
            $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; /system script add name="$date-|-$time-|-$user-|-'.$price.'-|-$address-|-$mac-|-' . $validity . '-|-'.$name.'-|-$comment" owner="$month$year" source="$date" comment="mikhmon"';

            // Build on-login script
            $onLogin = ':put (",'.$expMode.',' . $price . ',' . $validity . ','.$sellingPrice.',,' . $lockUser . ',"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pic $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ :local date [ /system clock get date ];:local year [ :pick $date 7 11 ];:local month [ :pick $date 0 3 ]; /sys sch add name="$user" disable=no start-date=$date interval="' . $validity . '"; :delay 5s; :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run]; :local getxp [len $exp]; :if ($getxp = 15) do={ :local d [:pic $exp 0 6]; :local t [:pic $exp 7 16]; :local s ("/"); :local exp ("$d$s$year $t"); /ip hotspot user set comment="$exp" [find where name="$user"];}; :if ($getxp = 8) do={ /ip hotspot user set comment="$date $exp" [find where name="$user"];}; :if ($getxp > 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"];};:delay 5s; /sys sch remove [find where name="$user"]';

            $mode = '';
            if ($expMode == "rem") {
                $onLogin = $onLogin . $lock . "}}";
                $mode = "remove";
            } elseif ($expMode == "ntf") {
                $onLogin = $onLogin . $lock . "}}";
                $mode = "set limit-uptime=1s";
            } elseif ($expMode == "remc") {
                $onLogin = $onLogin . $record . $lock . "}}";
                $mode = "remove";
            } elseif ($expMode == "ntfc") {
                $onLogin = $onLogin . $record . $lock . "}}";
                $mode = "set limit-uptime=1s";
            } elseif ($expMode == "0" && $price != "") {
                $onLogin = ':put (",,' . $price . ',,,noexp,' . $lockUser . ',")' . $lock;
            } else {
                $onLogin = "";
            }

            // Build background service script for scheduler
            $bgService = ':local dateint do={:local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );:local days [ :pick $d 4 6 ];:local month [ :pick $d 0 3 ];:local year [ :pick $d 7 11 ];:local monthint ([ :find $montharray $month]);:local month ($monthint + 1);:if ( [len $month] = 1) do={:local zero ("0");:return [:tonum ("$year$zero$month$days")];} else={:return [:tonum ("$year$month$days")];}}; :local timeint do={ :local hours [ :pick $t 0 2 ]; :local minutes [ :pick $t 3 5 ]; :return ($hours * 60 + $minutes) ; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date] ; :local curtime [$timeint t=$time] ; :foreach i in [ /ip hotspot user find where profile="'.$name.'" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime [:pic $comment 12 20]; :if ([:pic $comment 3] = "/" and [:pic $comment 6] = "/") do={:local expd [$dateint d=$comment] ; :local expt [$timeint t=$gettime] ; :if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user '.$mode.' $i ]; [ /ip hotspot active remove [find where user=$name] ];}}}';

            // Create profile
            $query = new Query('/ip/hotspot/user/profile/add');
            $query->equal('name', $name);
            if ($addressPool && $addressPool !== 'none') {
                $query->equal('address-pool', $addressPool);
            }
            if ($rateLimit) {
                $query->equal('rate-limit', $rateLimit);
            }
            $query->equal('shared-users', (int)$sharedUsers);
            $query->equal('status-autorefresh', '1m');
            if ($onLogin) {
                $query->equal('on-login', $onLogin);
            }
            if ($parentQueue && $parentQueue !== 'none') {
                $query->equal('parent-queue', $parentQueue);
            }
            
            $this->client->query($query)->read();

            // Create scheduler if expired mode is set
            if ($expMode != "0") {
                $randStartTime = "0" . rand(1, 5) . ":" . rand(10, 59) . ":" . rand(10, 59);
                $randInterval = "00:02:" . rand(10, 59);

                $query = new Query('/system/scheduler/add');
                $query->equal('name', $name);
                $query->equal('start-time', $randStartTime);
                $query->equal('interval', $randInterval);
                $query->equal('on-event', $bgService);
                $query->equal('disabled', 'no');
                $query->equal('comment', "Monitor Profile $name");
                $this->client->query($query)->read();
            }

            // Get created profile ID
            $query = new Query('/ip/hotspot/user/profile/print');
            $query->where('name', $name);
            $profiles = $this->client->query($query)->read();
            
            if (empty($profiles)) {
                throw new \Exception("Failed to get created profile ID");
            }

            \Log::info("Hotspot user profile created", [
                'profile' => $name,
                'profile_id' => $profiles[0]['.id']
            ]);

            return $profiles[0]['.id'];
        } catch (\Exception $e) {
            \Log::error("Failed to create hotspot user profile: " . $e->getMessage());
            throw new \Exception("Failed to create hotspot user profile: " . $e->getMessage());
        }
    }

    /**
     * Update Hotspot User Profile dengan fitur lengkap Mikhmon
     */
    public function updateHotspotUserProfileFull(string $profileId, array $data): bool
    {
        try {
            $name = preg_replace('/\s+/', '-', $data['name']);
            $sharedUsers = $data['shared_users'] ?? 1;
            $rateLimit = $data['rate_limit'] ?? '';
            $expMode = $data['expired_mode'] ?? '0';
            $validity = $data['validity'] ?? '';
            $price = $data['price'] ?? '0';
            $sellingPrice = $data['selling_price'] ?? '0';
            $addressPool = $data['address_pool'] ?? 'none';
            $parentQueue = $data['parent_queue'] ?? 'none';
            $lockUser = $data['lock_user'] ?? 'Disable';

            // Get existing profile
            $query = new Query('/ip/hotspot/user/profile/print');
            $query->where('.id', $profileId);
            $existing = $this->client->query($query)->read();
            
            if (empty($existing)) {
                throw new \Exception("Profile not found");
            }

            $oldName = $existing[0]['name'] ?? '';

            // Build lock script
            $lock = '';
            if ($lockUser === 'Enable') {
                $lock = '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]';
            }

            // Build record script
            $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; /system script add name="$date-|-$time-|-$user-|-'.$price.'-|-$address-|-$mac-|-' . $validity . '-|-'.$name.'-|-$comment" owner="$month$year" source="$date" comment="mikhmon"';

            // Build on-login script
            $onLogin = ':put (",'.$expMode.',' . $price . ',' . $validity . ','.$sellingPrice.',,' . $lockUser . ',"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pic $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ :local date [ /system clock get date ];:local year [ :pick $date 7 11 ];:local month [ :pick $date 0 3 ]; /sys sch add name="$user" disable=no start-date=$date interval="' . $validity . '"; :delay 5s; :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run]; :local getxp [len $exp]; :if ($getxp = 15) do={ :local d [:pic $exp 0 6]; :local t [:pic $exp 7 16]; :local s ("/"); :local exp ("$d$s$year $t"); /ip hotspot user set comment="$exp" [find where name="$user"];}; :if ($getxp = 8) do={ /ip hotspot user set comment="$date $exp" [find where name="$user"];}; :if ($getxp > 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"];};:delay 5s; /sys sch remove [find where name="$user"]';

            $mode = '';
            if ($expMode == "rem") {
                $onLogin = $onLogin . $lock . "}}";
                $mode = "remove";
            } elseif ($expMode == "ntf") {
                $onLogin = $onLogin . $lock . "}}";
                $mode = "set limit-uptime=1s";
            } elseif ($expMode == "remc") {
                $onLogin = $onLogin . $record . $lock . "}}";
                $mode = "remove";
            } elseif ($expMode == "ntfc") {
                $onLogin = $onLogin . $record . $lock . "}}";
                $mode = "set limit-uptime=1s";
            } elseif ($expMode == "0" && $price != "") {
                $onLogin = ':put (",,' . $price . ',,,noexp,' . $lockUser . ',")' . $lock;
            } else {
                $onLogin = "";
            }

            // Build background service script
            $bgService = ':local dateint do={:local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );:local days [ :pick $d 4 6 ];:local month [ :pick $d 0 3 ];:local year [ :pick $d 7 11 ];:local monthint ([ :find $montharray $month]);:local month ($monthint + 1);:if ( [len $month] = 1) do={:local zero ("0");:return [:tonum ("$year$zero$month$days")];} else={:return [:tonum ("$year$month$days")];}}; :local timeint do={ :local hours [ :pick $t 0 2 ]; :local minutes [ :pick $t 3 5 ]; :return ($hours * 60 + $minutes) ; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date] ; :local curtime [$timeint t=$time] ; :foreach i in [ /ip hotspot user find where profile="'.$name.'" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime [:pic $comment 12 20]; :if ([:pic $comment 3] = "/" and [:pic $comment 6] = "/") do={:local expd [$dateint d=$comment] ; :local expt [$timeint t=$gettime] ; :if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user '.$mode.' $i ]; [ /ip hotspot active remove [find where user=$name] ];}}}';

            // Update profile
            $query = new Query('/ip/hotspot/user/profile/set');
            $query->equal('.id', $profileId);
            $query->equal('name', $name);
            if ($addressPool && $addressPool !== 'none') {
                $query->equal('address-pool', $addressPool);
            } else {
                $query->equal('address-pool', '');
            }
            if ($rateLimit) {
                $query->equal('rate-limit', $rateLimit);
            } else {
                $query->equal('rate-limit', '');
            }
            $query->equal('shared-users', (int)$sharedUsers);
            $query->equal('status-autorefresh', '1m');
            if ($onLogin) {
                $query->equal('on-login', $onLogin);
            } else {
                $query->equal('on-login', '');
            }
            if ($parentQueue && $parentQueue !== 'none') {
                $query->equal('parent-queue', $parentQueue);
            } else {
                $query->equal('parent-queue', '');
            }
            
            $this->client->query($query)->read();

            // Update or create scheduler
            // Check both old name and new name for scheduler
            $scheduler = $this->getScheduler($oldName);
            if (!$scheduler) {
                $scheduler = $this->getScheduler($name);
            }
            $monId = $scheduler ? ($scheduler['.id'] ?? null) : null;

            if ($expMode != "0") {
                $randStartTime = "0" . rand(1, 5) . ":" . rand(10, 59) . ":" . rand(10, 59);
                $randInterval = "00:02:" . rand(10, 59);

                if (empty($monId)) {
                    // Create new scheduler
                    $query = new Query('/system/scheduler/add');
                    $query->equal('name', $name);
                    $query->equal('start-time', $randStartTime);
                    $query->equal('interval', $randInterval);
                    $query->equal('on-event', $bgService);
                    $query->equal('disabled', 'no');
                    $query->equal('comment', "Monitor Profile $name");
                    $this->client->query($query)->read();
                } else {
                    // Update existing scheduler
                    $query = new Query('/system/scheduler/set');
                    $query->equal('.id', $monId);
                    $query->equal('name', $name);
                    $query->equal('start-time', $randStartTime);
                    $query->equal('interval', $randInterval);
                    $query->equal('on-event', $bgService);
                    $query->equal('disabled', 'no');
                    $query->equal('comment', "Monitor Profile $name");
                    $this->client->query($query)->read();
                }
            } else {
                // Remove scheduler if expired mode is none
                if ($monId) {
                    $query = new Query('/system/scheduler/remove');
                    $query->equal('.id', $monId);
                    $this->client->query($query)->read();
                }
            }

            \Log::info("Hotspot user profile updated", [
                'profile' => $name,
                'profile_id' => $profileId
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to update hotspot user profile: " . $e->getMessage());
            throw new \Exception("Failed to update hotspot user profile: " . $e->getMessage());
        }
    }

    /**
     * Get Hotspot Active Users (Real-time monitoring)
     */
    public function getHotspotActiveUsers(): array
    {
        try {
            $query = new Query('/ip/hotspot/active/print');
            $activeUsers = $this->client->query($query)->read();
            
            $result = [];
            foreach ($activeUsers as $user) {
                $result[] = [
                    'id' => $user['.id'] ?? '',
                    'username' => $user['user'] ?? '',
                    'address' => $user['address'] ?? '',
                    'mac_address' => $user['mac-address'] ?? '',
                    'server' => $user['server'] ?? '',
                    'uptime' => $user['uptime'] ?? '',
                    'session_time_left' => $user['session-time-left'] ?? '',
                    'idle_time' => $user['idle-time'] ?? '',
                    'bytes_in' => $user['bytes-in'] ?? 0,
                    'bytes_out' => $user['bytes-out'] ?? 0,
                    'packets_in' => $user['packets-in'] ?? 0,
                    'packets_out' => $user['packets-out'] ?? 0,
                    'login_by' => $user['login-by'] ?? '',
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get hotspot active users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate Multiple Hotspot Users (Batch Voucher Generation)
     */
    public function generateHotspotUsers(int $count, string $profile, array $options = []): array
    {
        try {
            $generated = [];
            $prefix = $options['prefix'] ?? 'user';
            $passwordLength = $options['password_length'] ?? 6;
            $server = $options['server'] ?? 'all';
                        // Comment: use user input if provided, otherwise generate date-based (voucherMM-DD)
            if (isset($options['comment']) && !empty($options['comment'])) {
                $comment = $options['comment']; // Use user input
            } else {
                $dateFormat = date('m-d'); // Format: MM-DD (bulan-hari)
                $comment = 'voucher' . $dateFormat; // Auto-generate: voucher11-12
            }
            $userMode = $options['user_mode'] ?? 'up'; // 'up' = Username & Password, 'ueqp' = Username = Password
            $nameLength = $options['name_length'] ?? 4;
            $character = $options['character'] ?? 'random-abcd';
            
            // Character sets based on selection
            $charSets = [
                'random-abcd' => 'abcdefghijklmnopqrstuvwxyz',
                'random-1234' => '0123456789',
                'random-ABCD' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                'random-abcd1234' => 'abcdefghijklmnopqrstuvwxyz0123456789',
                'random-ABCD1234' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            ];
            
            $chars = $charSets[$character] ?? $charSets['random-abcd'];
            
            for ($i = 1; $i <= $count; $i++) {
                // Generate username: hanya random abcd1234 (tanpa prefix)
                $username = '';
                for ($j = 0; $j < $nameLength; $j++) {
                    $username .= $chars[rand(0, strlen($chars) - 1)];
                }
                
                // Generate password based on user_mode
                if ($userMode === 'ueqp') {
                    // Username = Password mode: password sama dengan username
                    $password = $username;
                } else {
                    // Username & Password mode: password berbeda (random)
                    $password = '';
                    for ($j = 0; $j < $passwordLength; $j++) {
                        $password .= $chars[rand(0, strlen($chars) - 1)];
                    }
                }
                
                try {
                    $this->createHotspotUser($username, $password, $profile, [
                        'server' => $server,
                        'comment' => $comment,
                        'limit_uptime' => $options['limit_uptime'] ?? null,
                        'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
                    ]);
                    
                    $generated[] = [
                        'username' => $username,
                        'password' => $password,
                        'profile' => $profile,
                        'server' => $server,
                        'comment' => $comment,
                    ];
                } catch (\Exception $e) {
                    \Log::error("Failed to generate user #{$i}: " . $e->getMessage());
                }
            }
            
            return $generated;
        } catch (\Exception $e) {
            \Log::error("Failed to generate hotspot users: " . $e->getMessage());
            return [];
        }
        }
    /**
     * Get Hotspot Hosts (Connected devices)
     */
    public function getHosts(): array
    {
        try {
            
            $result = [];
            foreach ($hosts as $host) {
                $result[] = [
                    'id' => $host['.id'] ?? '',
                    'mac_address' => $host['mac-address'] ?? '',
                    'address' => $host['address'] ?? '',
                    'to_address' => $host['to-address'] ?? '',
                    'server' => $host['server'] ?? '',
                    'bridge_port' => $host['bridge-port'] ?? '',
                    'uptime' => $host['uptime'] ?? '',
                    'idle_time' => $host['idle-time'] ?? '',
                    'bytes_in' => $host['bytes-in'] ?? 0,
                    'bytes_out' => $host['bytes-out'] ?? 0,
                    'packets_in' => $host['packets-in'] ?? 0,
                    'packets_out' => $host['packets-out'] ?? 0,
                    'authorized' => isset($host['authorized']) && ($host['authorized'] === 'true' || $host['authorized'] === true),
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get hotspot hosts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get IP Bindings
     */
    public function getIPBindings(): array
    {
        try {
            $query = new Query('/ip/hotspot/ip-binding/print');
            $bindings = $this->client->query($query)->read();
            
            $result = [];
            foreach ($bindings as $binding) {
                $result[] = [
                    'id' => $binding['.id'] ?? '',
                    'mac_address' => $binding['mac-address'] ?? '',
                    'address' => $binding['address'] ?? '',
                    'to_address' => $binding['to-address'] ?? '',
                    'server' => $binding['server'] ?? 'all',
                    'type' => $binding['type'] ?? 'regular',
                    'comment' => $binding['comment'] ?? null,
                    'disabled' => isset($binding['disabled']) && ($binding['disabled'] === 'true' || $binding['disabled'] === true),
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get IP bindings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add IP Binding
     */
    public function addIPBinding(array $data): bool
    {
        try {
            $query = new Query('/ip/hotspot/ip-binding/add');
            
            if (isset($data['mac_address']) && !empty($data['mac_address'])) {
                $query->equal('mac-address', $data['mac_address']);
            }
            
            if (isset($data['address']) && !empty($data['address'])) {
                $query->equal('address', $data['address']);
            }
            
            if (isset($data['to_address']) && !empty($data['to_address'])) {
                $query->equal('to-address', $data['to_address']);
            }
            
            $query->equal('server', $data['server'] ?? 'all');
            $query->equal('type', $data['type'] ?? 'regular');
            
            if (isset($data['comment']) && !empty($data['comment'])) {
                $query->equal('comment', $data['comment']);
            }
            
            if (isset($data['disabled']) && $data['disabled']) {
                $query->equal('disabled', 'yes');
            }
            
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to add IP binding: " . $e->getMessage());
            throw new \Exception("Failed to add IP binding: " . $e->getMessage());
        }
    }

    /**
     * Remove IP Binding
     */
    public function removeIPBinding(string $bindingId): bool
    {
        try {
            $query = new Query('/ip/hotspot/ip-binding/remove');
            $query->equal('.id', $bindingId);
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to remove IP binding: " . $e->getMessage());
            throw new \Exception("Failed to remove IP binding: " . $e->getMessage());
        }
    }

    /**
     * Get Hotspot Cookies
     */
    public function getCookies(): array
    {
        try {
            $query = new Query('/ip/hotspot/cookie/print');
            $cookies = $this->client->query($query)->read();
            
            $result = [];
            foreach ($cookies as $cookie) {
                $result[] = [
                    'id' => $cookie['.id'] ?? '',
                    'mac_address' => $cookie['mac-address'] ?? '',
                    'domain' => $cookie['domain'] ?? '',
                    'expires_in' => $cookie['expires-in'] ?? '',
                ];
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get hotspot cookies: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Remove Hotspot Cookie
     */
    public function removeCookie(string $cookieId): bool
    {
        try {
            $query = new Query('/ip/hotspot/cookie/remove');
            $query->equal('.id', $cookieId);
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to remove cookie: " . $e->getMessage());
            throw new \Exception("Failed to remove cookie: " . $e->getMessage());
        }
    }

    /**
     * Get User Log (Hotspot activity log)
     */
    public function getUserLog(array $filters = []): array
    {
        try {
            $query = new Query('/log/print');
            
            // Filter by topics related to hotspot
            $topics = $filters['topics'] ?? ['hotspot', 'account'];
            
            $logs = $this->client->query($query)->read();
            
            $result = [];
            foreach ($logs as $log) {
                $message = $log['message'] ?? '';
                $topics_str = $log['topics'] ?? '';
                
                // Filter only hotspot-related logs
                $isHotspotLog = false;
                foreach ($topics as $topic) {
                    if (stripos($topics_str, $topic) !== false || stripos($message, 'hotspot') !== false) {
                        $isHotspotLog = true;
                        break;
                    }
                }
                
                if ($isHotspotLog) {
                    $result[] = [
                        'id' => $log['.id'] ?? '',
                        'time' => $log['time'] ?? '',
                        'topics' => $topics_str,
                        'message' => $message,
                    ];
                }
            }
            
            // Limit to recent logs (last 100)
            if (count($result) > 100) {
                $result = array_slice($result, 0, 100);
            }
            
            return $result;
        } catch (\Exception $e) {
            \Log::error("Failed to get user log: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Disable Hotspot User
     */
    public function disableHotspotUser(string $username): bool
    {
        try {
            return $this->updateHotspotUser($username, null, ['disabled' => true]);
        } catch (\Exception $e) {
            \Log::error("Failed to disable hotspot user: " . $e->getMessage());
            throw new \Exception("Failed to disable hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Enable Hotspot User
     */
    public function enableHotspotUser(string $username): bool
    {
        try {
            return $this->updateHotspotUser($username, null, ['disabled' => false]);
        } catch (\Exception $e) {
            \Log::error("Failed to enable hotspot user: " . $e->getMessage());
            throw new \Exception("Failed to enable hotspot user: " . $e->getMessage());
        }
    }

    /**
     * Remove Hotspot Active Session (Kick user)
     */
    public function removeActiveSession(string $sessionId): bool
    {
        try {
            $query = new Query('/ip/hotspot/active/remove');
            $query->equal('.id', $sessionId);
            $this->client->query($query)->read();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to remove active session: " . $e->getMessage());
            throw new \Exception("Failed to remove active session: " . $e->getMessage());
        }
    }
}

