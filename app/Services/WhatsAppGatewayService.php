<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;
use Exception;

class WhatsAppGatewayService
{
    protected string $wakitaBase;
    protected $tenantId;
    protected string $sessionId;
    protected bool $isSuperAdmin = false;
    protected string $username;
    protected string $password;
    protected string $cacheKey;

    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->wakitaBase = config('env.WAKITA_BASE_URL', env('WAKITA_BASE_URL', 'https://wa.kitabill.site'));
        
        // Superadmin credentials for API access
        $this->username = config('env.WAKITA_SUPERADMIN_USERNAME', env('WAKITA_SUPERADMIN_USERNAME', 'admin'));
        $this->password = config('env.WAKITA_SUPERADMIN_PASSWORD', env('WAKITA_SUPERADMIN_PASSWORD', 'user123'));

        if ($this->tenantId && $this->tenantId !== 'superadmin' && $this->tenantId !== -1) {
            $tenant = Tenant::find($this->tenantId);
            $this->sessionId = $tenant?->wa_gateway_session_id ?? 'tenant_' . $this->tenantId;
            $this->isSuperAdmin = false;
        } else {
            // Use configured session ID for Superadmin (default to '1' based on provisioning)
            $this->sessionId = config('env.WAKITA_SUPERADMIN_SESSION_ID', env('WAKITA_SUPERADMIN_SESSION_ID', '1'));
            $this->isSuperAdmin = true;
        }

        $this->cacheKey = 'wakita_jwt_' . md5($this->username);
    }

    /**
     * Get validated JWT client
     */
    protected function jwtClient()
    {
        $jwt = Cache::get($this->cacheKey);

        if (!$jwt) {
            $jwt = $this->login();
            if ($jwt) {
                Cache::put($this->cacheKey, $jwt, 3000); // ~50 min
            }
        }

        // If jwt is still empty (login failed), return client without token
        // The caller should handle 401 responses
        return Http::baseUrl($this->wakitaBase)
            ->withToken($jwt ?: 'invalid')
            ->timeout(10);
    }

    /**
     * Login to WAKita to get JWT
     * Returns token string on success, null on failure (with negative caching)
     */
    protected function login(): ?string
    {
        // Check negative cache to avoid login storms
        $failKey = $this->cacheKey . '_fail';
        if (Cache::has($failKey)) {
            return null;
        }

        try {
            $response = Http::post("{$this->wakitaBase}/api/auth/login", [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            if ($response->successful()) {
                Cache::forget($failKey); // clear negative cache on success
                return $response->json('token');
            }

            Log::error("WAKita Login Failed", ['status' => $response->status(), 'error' => $response->body()]);
        } catch (\Exception $e) {
            Log::error("WAKita Login Exception", ['error' => $e->getMessage()]);
        }

        // ✅ Negative cache: don't retry failed login for 5 minutes
        Cache::put($failKey, true, 5 * 60);
        return null;
    }

    /**
     * Get Status from WAKita (JWT)
     */
    public function getStatus(): array
    {
        try {
            // Check cache for this specific session status (avoid API spam)
            // But for monitoring dashboard, we might want fresh data.
            // Let's use short cache (5s) similar to previous implementation
            $statusCacheKey = 'wakita_status_' . $this->sessionId;
            
            return Cache::remember($statusCacheKey, 5, function () {
                $response = $this->jwtClient()->get("/api/sessions/{$this->sessionId}/status");

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Normalize status
                    $rawStatus = $data['status'] ?? 'disconnected';
                    $stateMap = [
                        'ready'         => 'connected', // WAKita returns 'ready' when session active
                        'authenticated' => 'connected',
                        'connected'     => 'connected',
                        'qr_ready'      => 'waiting_qr',
                        'got qr'        => 'waiting_qr',
                        'initializing'  => 'initializing',
                        'disconnected'  => 'disconnected',
                    ];
                    
                    $finalStatus = $stateMap[strtolower($rawStatus)] ?? $rawStatus;
                    $phone = $data['me']['id'] ?? $data['id'] ?? null;
                    if ($phone) {
                        $phone = explode(':', $phone)[0];
                        $phone = explode('@', $phone)[0];
                    }

                    return [
                        'tenant_id' => $this->tenantId === 'superadmin' ? -1 : (int)$this->tenantId,
                        'gateway_state' => $finalStatus,
                        'phone_number' => $phone,
                        'uptime' => null, // WAKita currently doesn't provide uptime in status
                        'last_updated' => now()->toISOString(),
                        'session' => $this->sessionId,
                        'engine' => 'wakita',
                    ];
                }
                
                return $this->formatDisconnectStatus();
            });

        } catch (Exception $e) {
            Log::error("WAKita getStatus Error: " . $e->getMessage());
            return $this->formatDisconnectStatus();
        }
    }

    private function formatDisconnectStatus(): array
    {
        return [
            'tenant_id' => $this->tenantId === 'superadmin' ? -1 : (int)$this->tenantId,
            'gateway_state' => 'disconnected',
            'phone_number' => null,
            'uptime' => null,
            'last_updated' => now()->toISOString(),
            'session' => $this->sessionId,
            'engine' => 'wakita',
            'error' => 'Unreachable'
        ];
    }

    /**
     * Get QR Code (Base64)
     */
    public function getQrCodeUrl(): ?string
    {
        try {
            // Trigger connect to ensure session exists/starts
            $this->jwtClient()->post("/api/sessions/{$this->sessionId}/connect");

            $response = $this->jwtClient()->get("/api/sessions/{$this->sessionId}/qr");

            if ($response->successful()) {
                $data = $response->json();
                $qrRaw = $data['qr'] ?? null;
                
                if ($qrRaw) {
                    // Generate Base64 from raw string using reliable library
                    return $this->generateQrImage($qrRaw);
                }
            }
        } catch (Exception $e) {
            Log::error("WAKita getQrCodeUrl Error: " . $e->getMessage());
        }
        return null;
    }

    private function generateQrImage(string $qrString): string
    {
        try {
            // Use external API to avoid composer dependency issues in production
            // This returns a direct URL to the QR image, which <img src="..."> handles fine.
            return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrString);
        } catch (\Throwable $e) {
            Log::error("QR Generation Failed: " . $e->getMessage());
            return '';
        }
    }

    public function reconnect(): bool
    {
        try {
            // In WAKita, connect() handles reconnection/startup
            $response = $this->jwtClient()->post("/api/sessions/{$this->sessionId}/connect");
            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }

    public function logout(): bool
    {
        try {
            // Force logout/delete session
            $this->jwtClient()->post("/api/sessions/{$this->sessionId}/logout");
            // Also delete from WAKita memory if needed
            $this->jwtClient()->delete("/api/sessions/{$this->sessionId}");
            
            Cache::forget('wakita_status_' . $this->sessionId);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function sendMessage(string $phone, string $message): array
    {
        try {
            // Get API key from env, falling back to the known superadmin key if not set
            $apiKey = config('env.WAKITA_SUPERADMIN_API_KEY', env('WAKITA_SUPERADMIN_API_KEY', 'wk_915fabcd930f16d66b9865fd2b4555b76b085d1c8fd4b8b34590212a5d9f4bd4'));
            
            // Format phone number
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
            } elseif (str_starts_with($phone, '8')) {
                $phone = '62' . $phone;
            }

            // Use GET /api/sendWA like the tenant app does
            $response = Http::timeout(120)->get("{$this->wakitaBase}/api/sendWA", [
                'to'         => $phone,
                'msg'        => $message,
                'secret'     => $apiKey,
                'session_id' => $this->sessionId,
            ]);

            $data = $response->json();
            
            if ($response->successful() && ($data['ok'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Pesan berhasil dikirim',
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => 'Gagal mengirim pesan: ' . ($data['message'] ?? $response->body()),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    public function isRegistered(string $phone): bool
    {
        // Optional feature
        return true; 
    }

    public function getSession(): string
    {
        return $this->sessionId;
    }
}
