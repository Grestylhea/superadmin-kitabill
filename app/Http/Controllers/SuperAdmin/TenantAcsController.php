<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantAcsController extends Controller
{
    /**
     * Get ACS status for a tenant
     */
    public function show(Tenant $tenant)
    {
        return response()->json([
            'enabled' => $tenant->acs_enabled,
            'tenant_id' => $tenant->acs_tenant_id,
            'has_api_key' => !empty($tenant->acs_api_key),
            // Don't send full key, maybe just a hint or masked version if needed in UI
            'api_key' => $tenant->acs_api_key ? substr($tenant->acs_api_key, 0, 5) . '...' . substr($tenant->acs_api_key, -5) : null,
        ]);
    }

    /**
     * Enable ACS for a tenant
     */
    public function enable(Tenant $tenant)
    {
        try {
            DB::beginTransaction();

            // Ensure ACS tenant ID exists (default to ID for consistency)
            if (empty($tenant->acs_tenant_id)) {
                $tenant->acs_tenant_id = (string)$tenant->id;
            }

            // Ensure API Key exists
            if (empty($tenant->acs_api_key)) {
                $tenant->acs_api_key = Str::random(32);
                // Note: In a real scenario, this key needs to be synced to Go-ACS Core DB
                // For now, we assume keys are pre-generated or synced via background job
            }

            $tenant->acs_enabled = true;
            $tenant->save();

            DB::commit();

            return back()->with('success', 'ACS berhasil dıaktifkan untuk tenant ini.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to enable ACS for tenant {$tenant->id}: " . $e->getMessage());
            return back()->with('error', 'Gagal mengaktifkan ACS: ' . $e->getMessage());
        }
    }

    /**
     * Disable ACS for a tenant
     */
    public function disable(Tenant $tenant)
    {
        try {
            $tenant->acs_enabled = false;
            $tenant->save();

            return back()->with('success', 'ACS berhasil dinonaktifkan untuk tenant ini.');
        } catch (\Exception $e) {
            Log::error("Failed to disable ACS for tenant {$tenant->id}: " . $e->getMessage());
            return back()->with('error', 'Gagal menonaktifkan ACS: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate API Key
     */
    public function regenerateKey(Tenant $tenant)
    {
        try {
            $newKey = Str::random(32);
            $tenant->acs_api_key = $newKey;
            $tenant->save();

            // TODO: Sync to Go-ACS Core DB
            
            return back()->with('success', 'API Key berhasil digenerate ulang. (Catatan: Pastikan key disinkronkan ke server ACS)');
        } catch (\Exception $e) {
            Log::error("Failed to regenerate ACS key for tenant {$tenant->id}: " . $e->getMessage());
            return back()->with('error', 'Gagal regenerate key: ' . $e->getMessage());
        }
    }
    /**
     * Bulk Enable ACS
     */
    public function bulkEnable(Request $request)
    {
        $tenantIds = $request->input('ids', []);
        if (empty($tenantIds)) {
            return back()->with('error', 'Pilih minimal satu tenant.');
        }

        try {
            DB::beginTransaction();
            foreach ($tenantIds as $id) {
                $tenant = Tenant::find($id);
                if ($tenant) {
                    if (empty($tenant->acs_tenant_id)) $tenant->acs_tenant_id = (string)$tenant->id;
                    if (empty($tenant->acs_api_key)) $tenant->acs_api_key = Str::random(32);
                    $tenant->acs_enabled = true;
                    $tenant->save();
                }
            }
            DB::commit();
            return back()->with('success', count($tenantIds) . ' tenant berhasil dıaktifkan ACS-nya.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Bulk enable ACS failed: " . $e->getMessage());
            return back()->with('error', 'Gagal aktivasi bulk ACS.');
        }
    }

    /**
     * Bulk Disable ACS
     */
    public function bulkDisable(Request $request)
    {
        $tenantIds = $request->input('ids', []);
        if (empty($tenantIds)) {
            return back()->with('error', 'Pilih minimal satu tenant.');
        }

        try {
            Tenant::whereIn('id', $tenantIds)->update(['acs_enabled' => false]);
            return back()->with('success', count($tenantIds) . ' tenant berhasil dınonaktifkan ACS-nya.');
        } catch (\Exception $e) {
            Log::error("Bulk disable ACS failed: " . $e->getMessage());
            return back()->with('error', 'Gagal deaktivasi bulk ACS.');
        }
    }
}
