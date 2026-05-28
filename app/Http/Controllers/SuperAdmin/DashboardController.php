<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Payment;
use App\Models\Router;
use App\Models\OLT;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Get all tenants (without tenant scope)
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $trialTenants = Tenant::where('status', 'trial')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();

        // Calculate monthly revenue (from all payments this month)
        $monthlyRevenue = Payment::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('status', 'paid')
            ->sum('amount');

        // Get subscription data from payments
        $activeSubscriptions = Payment::where('status', 'paid')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->distinct('tenant_id')
            ->count();

        $trialSubscriptions = Tenant::where('status', 'trial')->count();

        // Total users across all tenants
        $totalUsers = User::withoutGlobalScope(\App\Scopes\TenantScope::class)->count();

        // Count tenants by subscription plan
        $starterPlanCount = Tenant::where('subscription_plan', 'starter')->count();
        $professionalPlanCount = Tenant::where('subscription_plan', 'professional')->count();
        $enterprisePlanCount = Tenant::where('subscription_plan', 'enterprise')->count();

        // Expired trials
        $expiredTrials = Tenant::where('status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->count();

        // Revenue data for last 6 months
        $revenueData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Payment::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->where('status', 'paid')
                ->sum('amount');
            $revenueData[] = $revenue;
        }

        // Recent tenants (last 5)
        $recentTenants = Tenant::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'subdomain' => $tenant->subdomain,
                    'plan' => $tenant->subscription_plan ?? 'Starter',
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at,
                ];
            });

        // Recent activity
        $recentActivity = $this->getRecentActivity();

        // NOC / Network Core Stats
        $totalRouters = Router::count();
        $onlineRouters = Router::where('last_seen', '>=', now()->subMinutes(5))->count();
        $offlineRouters = $totalRouters - $onlineRouters;

        $totalOlts = OLT::count();
        $onlineOlts = OLT::where('status', 'online')->orWhere('last_seen', '>=', now()->subMinutes(10))->count();
        $offlineOlts = $totalOlts - $onlineOlts;

        $nocStats = [
            'routers_total' => $totalRouters,
            'routers_online' => $onlineRouters,
            'routers_offline' => $offlineRouters,
            'olts_total' => $totalOlts,
            'olts_online' => $onlineOlts,
            'olts_offline' => $offlineOlts,
        ];

        // Financial & Network Performance per Tenant/Mitra
        $tenantPerformance = DB::table('tenants')
            ->leftJoin('invoices', 'tenants.id', '=', 'invoices.tenant_id')
            ->select(
                'tenants.id',
                'tenants.name',
                'tenants.subdomain',
                DB::raw('COALESCE(SUM(CASE WHEN invoices.status = \'paid\' THEN invoices.total ELSE 0 END), 0) as total_paid'),
                DB::raw('COALESCE(SUM(CASE WHEN invoices.status IN (\'unpaid\', \'overdue\') THEN invoices.total ELSE 0 END), 0) as total_unpaid'),
                DB::raw('COUNT(CASE WHEN invoices.status = \'paid\' THEN 1 END) as count_paid'),
                DB::raw('COUNT(CASE WHEN invoices.status IN (\'unpaid\', \'overdue\') THEN 1 END) as count_unpaid')
            )
            ->groupBy('tenants.id', 'tenants.name', 'tenants.subdomain')
            ->get();

        $routerStats = DB::table('routers')
            ->select(
                'tenant_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(CASE WHEN last_seen >= NOW() - INTERVAL \'5 minutes\' THEN 1 END) as online')
            )
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $oltStats = DB::table('olts')
            ->select(
                'tenant_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(CASE WHEN status = \'online\' OR last_seen >= NOW() - INTERVAL \'10 minutes\' THEN 1 END) as online')
            )
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $tenantList = $tenantPerformance->map(function ($tenant) use ($routerStats, $oltStats) {
            $routers = $routerStats->get($tenant->id) ?? (object)['total' => 0, 'online' => 0];
            $olts = $oltStats->get($tenant->id) ?? (object)['total' => 0, 'online' => 0];

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'total_paid' => (float)$tenant->total_paid,
                'total_unpaid' => (float)$tenant->total_unpaid,
                'count_paid' => (int)$tenant->count_paid,
                'count_unpaid' => (int)$tenant->count_unpaid,
                'routers_total' => (int)$routers->total,
                'routers_online' => (int)$routers->online,
                'olts_total' => (int)$olts->total,
                'olts_online' => (int)$olts->online,
            ];
        });

        return Inertia::render('SuperAdmin/Dashboard', [
            'totalTenants' => $totalTenants,
            'activeTenants' => $activeTenants,
            'trialTenants' => $trialTenants,
            'suspendedTenants' => $suspendedTenants,
            'monthlyRevenue' => $monthlyRevenue,
            'activeSubscriptions' => $activeSubscriptions,
            'trialSubscriptions' => $trialSubscriptions,
            'totalUsers' => $totalUsers,
            'starterPlanCount' => $starterPlanCount,
            'professionalPlanCount' => $professionalPlanCount,
            'enterprisePlanCount' => $enterprisePlanCount,
            'expiredTrials' => $expiredTrials,
            'revenueData' => $revenueData,
            'recentTenants' => $recentTenants,
            'recentActivity' => $recentActivity,
            'nocStats' => $nocStats,
            'tenantList' => $tenantList,
        ]);
    }

    private function getRecentActivity()
    {
        // Get recent tenants as "new tenant" activity
        $newTenants = Tenant::orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($tenant) {
                return [
                    'id' => 'tenant-' . $tenant->id,
                    'type' => 'new_tenant',
                    'description' => "New tenant '{$tenant->name}' registered",
                    'created_at' => $tenant->created_at,
                ];
            });

        // Get recent payments as activity
        $recentPayments = Payment::where('status', 'paid')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($payment) {
                $tenant = Tenant::find($payment->tenant_id);
                return [
                    'id' => 'payment-' . $payment->id,
                    'type' => 'payment',
                    'description' => "Payment received from '{$tenant->name}' - Rp " . number_format((float)$payment->amount, 0, ',', '.'),
                    'created_at' => $payment->created_at,
                ];
            });

        // Merge and sort by created_at
        $activities = $newTenants->concat($recentPayments)
            ->sortByDesc('created_at')
            ->take(6)
            ->values();

        return $activities;
    }
}



