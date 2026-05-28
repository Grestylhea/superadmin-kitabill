<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TenantTripayGatewayController extends Controller
{
    /**
     * Show Tripay configuration for a specific tenant.
     */
    public function show(Tenant $tenant)
    {
        // Fetch specific Tripay settings for this tenant
        $settings = DB::table('settings')
            ->where('tenant_id', $tenant->id)
            ->whereIn('key', [
                'tripay_merchant_code',
                'tripay_api_key',
                'tripay_private_key',
                'tripay_mode',
                'tripay_enabled'
            ])
            ->pluck('value', 'key');

        $maskedSettings = [
            'merchant_code' => $settings['tripay_merchant_code'] ?? '',
            'api_key' => $this->maskSecret($settings['tripay_api_key'] ?? ''),
            'private_key' => $this->maskSecret($settings['tripay_private_key'] ?? ''),
            'mode' => $settings['tripay_mode'] ?? 'sandbox',
            'enabled' => ($settings['tripay_enabled'] ?? '0') === '1',
        ];

        return Inertia::render('SuperAdmin/Tenants/TripayGateway', [
            'tenant' => $tenant->only('id', 'name', 'subdomain'),
            'settings' => $maskedSettings,
        ]);
    }

    /**
     * Update Tripay configuration for a specific tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'merchant_code' => 'required|string',
            'api_key' => 'required|string',
            'private_key' => 'required|string',
            'mode' => 'required|in:sandbox,production',
            'enabled' => 'boolean',
        ]);

        $keysToUpdate = [
            'tripay_merchant_code' => $validated['merchant_code'],
            'tripay_mode' => $validated['mode'],
            'tripay_enabled' => $validated['enabled'] ? '1' : '0',
        ];

        // Only update secrets if they are not the masked placeholder
        if (!$this->isMasked($validated['api_key'])) {
            $keysToUpdate['tripay_api_key'] = $validated['api_key'];
        }

        if (!$this->isMasked($validated['private_key'])) {
            $keysToUpdate['tripay_private_key'] = $validated['private_key'];
        }

        foreach ($keysToUpdate as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['tenant_id' => $tenant->id, 'key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return redirect()->back()->with('success', 'Tripay Settings Updated Successfully');
    }

    /**
     * Test Tripay Connection for a specific tenant.
     */
    public function test(Request $request, Tenant $tenant)
    {
        // 1. Get credentials (mix of request input + DB for masked values)
        $input = $request->validate([
            'merchant_code' => 'required|string',
            'api_key' => 'required|string',
            'private_key' => 'required|string',
            'mode' => 'required|in:sandbox,production',
        ]);

        $merchantCode = $input['merchant_code'];
        $mode = $input['mode'];
        
        $apiKey = $input['api_key'];
        if ($this->isMasked($apiKey)) {
            $apiKey = DB::table('settings')->where('tenant_id', $tenant->id)->where('key', 'tripay_api_key')->value('value');
        }

        $privateKey = $input['private_key'];
        if ($this->isMasked($privateKey)) {
            $privateKey = DB::table('settings')->where('tenant_id', $tenant->id)->where('key', 'tripay_private_key')->value('value');
        }

        if (!$apiKey || !$privateKey) {
            return response()->json(['success' => false, 'message' => 'Credentials not fully set.'], 400);
        }

        // 2. Perform Test API Call (Get Payment Channels)
        $baseUrl = ($mode === 'production') 
            ? 'https://tripay.co.id/api/merchant/payment-channel' 
            : 'https://tripay.co.id/api-sandbox/merchant/payment-channel';

        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $apiKey])->get($baseUrl);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] ?? false) {
                    return response()->json(['success' => true, 'message' => 'Connection Successful! Channels retrieved.']);
                }
                return response()->json(['success' => false, 'message' => 'Tripay Error: ' . ($data['message'] ?? 'Unknown error')]);
            }

            return response()->json(['success' => false, 'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
        }
    }

    private function maskSecret($value)
    {
        if (empty($value)) return '';
        if (strlen($value) <= 8) return '********';
        return substr($value, 0, 4) . '****' . substr($value, -4);
    }

    private function isMasked($value)
    {
        return str_contains($value, '*');
    }
}
