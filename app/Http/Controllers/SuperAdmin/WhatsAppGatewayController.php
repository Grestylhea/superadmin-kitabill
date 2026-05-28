<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\Process\Process;
use App\Services\WhatsAppGatewayService;

class WhatsAppGatewayController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('SuperAdmin/WhatsAppGateways/Index');
    }

    public function status(Request $request)
    {
        // Increase timeout for this request since we might hit WAKita API multiple times
        set_time_limit(60);

        $tenants = Tenant::orderBy('id')->get();
        $statuses = [];

        // Single service status check - Legacy, no longer needed
        $singleServiceActive = true; 

        // SuperAdmin status
        $statuses[] = $this->getTenantGatewayStatus('superadmin', 'SuperAdmin (Exclusive)', 'superadmin');

        // Individual tenants
        foreach ($tenants as $tenant) {
            $statuses[] = $this->getTenantGatewayStatus($tenant->id, $tenant->name, $tenant->subdomain);
        }

        return response()->json([
            'success'   => true,
            'data'      => $statuses,
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function getTenantGatewayStatus($id, $name, $subdomain): array
    {
        $waService = new WhatsAppGatewayService($id);
        $waStatus = $waService->getStatus();
        
        return array_merge([
            'tenant_id'       => $id === 'superadmin' ? -1 : (int)$id,
            'tenant_name'     => $name,
            'subdomain'       => $subdomain,
            'port'            => null, // No longer port-based
            'type'            => $id === 'superadmin' ? 'system' : 'tenant',
            'service_name'    => 'wakita-gateway',
            'service_status'  => 'managed', // Managed externally
            'port_listening'  => true,
            'engine'          => 'wakita',
            'actions_enabled' => true,
        ], $waStatus);
    }

    public function getQr(Request $request, $id)
    {
        $tenantId = ($id === '0' || $id == 0) ? null : ($id == -1 || $id === 'superadmin' ? 'superadmin' : $id);
        $waService = new WhatsAppGatewayService($tenantId);
        
        $qrImage = $waService->getQrCodeUrl();
        $status = $waService->getStatus();

        return response()->json([
            'success' => true,
            'qrImage' => $qrImage,
            'connected' => $status['gateway_state'] === 'connected',
            'phoneNumber' => $status['phone_number'],
            'message' => $qrImage ? 'QR Code ready' : 'Failed to generate QR or already connected'
        ]);
    }

    public function reconnect(Request $request, $id)
    {
        $tenantId = ($id === '0' || $id == 0) ? null : ($id == -1 || $id === 'superadmin' ? 'superadmin' : $id);
        $waService = new WhatsAppGatewayService($tenantId);
        
        $success = $waService->reconnect();

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Reconnection triggered' : 'Failed to trigger reconnection'
        ]);
    }

    public function resetSession(Request $request, $id)
    {
        $tenantId = ($id === '0' || $id == 0) ? null : ($id == -1 || $id === 'superadmin' ? 'superadmin' : $id);
        $waService = new WhatsAppGatewayService($tenantId);
        
        $success = $waService->logout();

        return response()->json([
            'success' => $success,
            'message' => $success ? "Session for {$id} has been reset. You can now scan a new QR code." : "Failed to reset session for {$id}."
        ]);
    }

    public function restartService(Request $request, $id)
    {
        // For WAKita, we just try to reconnect/start the session
        $tenantId = ($id === '0' || $id == 0) ? null : ($id == -1 || $id === 'superadmin' ? 'superadmin' : $id);
        $waService = new WhatsAppGatewayService($tenantId);
        $success = $waService->reconnect();

        return response()->json([
            'success' => $success,
            'message' => 'WhatsApp Session reconnection triggered.'
        ]);
    }

    public function getLogs(Request $request, $id)
    {
        $tenantId = ($id === '0' || $id == 0) ? null : ($id == -1 || $id === 'superadmin' ? 'superadmin' : $id);
        
        // Query wa_message_logs table instead of journalctl
        // Note: logs might be empty if WAKita doesn't write to DB yet.
        
        $query = DB::table('wa_message_logs')
            ->orderBy('created_at', 'desc')
            ->limit(100);

        if ($tenantId === 'superadmin') {
            $query->whereNull('tenant_id'); // Superadmin logs usually have null tenant_id or explicit tenant_id (if we change logic)
            // Or where sender_session = 'superadmin'
             $query->orWhere('sender_session', 'superadmin');
        } else {
             $query->where('tenant_id', $tenantId);
        }

        $logs = $query->get();
        
        // Format as string to match previous expected output
        $output = "";
        foreach ($logs as $log) {
            $output .= "[{$log->created_at}] [{$log->status}] {$log->error_type} - Session: {$log->sender_session}\n";
        }

        if (empty($output)) {
            $output = "No message delivery logs found for " . ($tenantId ?: 'all tenants') . " in recent history (DB).";
        }

        return response()->json([
            'success'   => true,
            'logs'      => $output,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get real-time and aggregated WhatsApp metrics per tenant
     */
    public function metrics(Request $request)
    {
        // Cache metrics for 15 seconds to prevent DB overload from fast UI polls
        return Cache::remember('wa_observability_metrics', 15, function () {
            $todayStart = now()->startOfDay();
            
            // 1. Get today's stats grouped by tenant
            // Use COALESCE to group superadmin (null) as -1
            $logsStats = DB::table('wa_message_logs')
                ->where('created_at', '>=', $todayStart)
                ->select(
                    DB::raw("COALESCE(tenant_id, -1) as tid"),
                    DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success"),
                    DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                    DB::raw("SUM(CASE WHEN status = 'locked' THEN 1 ELSE 0 END) as locked"),
                    DB::raw("MAX(created_at) as last_activity")
                )
                ->groupBy('tid')
                ->get()
                ->keyBy('tid');

            // 2. Get latest bulk campaign status for "paused" reporting
            $latestBulk = DB::table('super_admin_bulk_messages')
                ->orderBy('id', 'desc')
                ->first(['status', 'pause_reason', 'paused_until']);

            // 3. Get all tenants for names/domains
            $tenants = DB::table('tenants')
                ->select('id', 'name', 'subdomain')
                ->orderBy('name')
                ->get();

            // 4. Build combined result array
            $results = [];

            // Add SuperAdmin row
            $saLog = $logsStats->get(-1);
            $results[] = [
                'tenant_id' => -1,
                'tenant_name' => 'SuperAdmin (Exclusive)',
                'tenant_domain' => 'superadmin.kitabill.site',
                'success_today' => (int)($saLog->success ?? 0),
                'failed_today' => (int)($saLog->failed ?? 0),
                'locked_today' => (int)($saLog->locked ?? 0),
                'paused' => $latestBulk && $latestBulk->status === 'paused',
                'pause_reason' => $latestBulk ? $latestBulk->pause_reason : null,
                'paused_until' => $latestBulk ? $latestBulk->paused_until : null,
                'last_activity' => $saLog ? $saLog->last_activity : null,
            ];

            // Add Tenant rows
            foreach ($tenants as $tenant) {
                $tLog = $logsStats->get($tenant->id);
                $results[] = [
                    'tenant_id' => (int)$tenant->id,
                    'tenant_name' => $tenant->name,
                    'tenant_domain' => $tenant->subdomain . '.kitabill.site',
                    'success_today' => (int)($tLog->success ?? 0),
                    'failed_today' => (int)($tLog->failed ?? 0),
                    'locked_today' => (int)($tLog->locked ?? 0),
                    'paused' => false,
                    'pause_reason' => null,
                    'paused_until' => null,
                    'last_activity' => $tLog ? $tLog->last_activity : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'timestamp' => now()->toISOString(),
            ]);
        });
    }
}
