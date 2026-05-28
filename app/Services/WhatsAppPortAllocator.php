<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;

class WhatsAppPortAllocator
{
    private const BASE_PORT = 3100;
    private const MAX_PORT = 3999;
    // Retry limit prevents infinite loops
    private const MAX_RETRIES = 5;

    public function allocate(int $tenantId): int
    {
        // 1. Quick check
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new Exception("Tenant not found: {$tenantId}");
        }
        
        if ($tenant->wa_gateway_port) {
            return $tenant->wa_gateway_port;
        }

        // 2. Transactional Allocation with Retry
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $port = DB::transaction(function () use ($tenantId) {
                    // Lock tenant row
                    $tenant = Tenant::lockForUpdate()->find($tenantId);
                    
                    if ($tenant->wa_gateway_port) {
                        return $tenant->wa_gateway_port;
                    }

                    // Find next available port
                    $port = $this->findNextFreePort();
                    
                    // Assign port
                    $tenant->update(['wa_gateway_port' => $port]);
                    
                    return $port;
                });
                
                return $port;

            } catch (QueryException $e) {
                // Check for Unique Constraint Violation (SQLSTATE 23505)
                if ($e->getCode() == 23505) {
                    if ($attempt === self::MAX_RETRIES) {
                        throw new Exception("Failed to allocate port after " . self::MAX_RETRIES . " attempts due to concurrency.");
                    }
                    usleep(100000); // 100ms
                    continue;
                }
                throw $e;
            } catch (Exception $e) {
                throw $e;
            }
        }
        
        throw new Exception("Unable to allocate port. Unknown error.");
    }

    private function findNextFreePort(): int
    {
        $usedPorts = DB::table('tenants')
            ->whereNotNull('wa_gateway_port')
            ->pluck('wa_gateway_port')
            ->toArray();
        
        for ($port = self::BASE_PORT; $port <= self::MAX_PORT; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }
        
        throw new Exception("No available ports in range " . self::BASE_PORT . " - " . self::MAX_PORT);
    }
}
