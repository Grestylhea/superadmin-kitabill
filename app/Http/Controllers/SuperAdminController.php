<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class SuperAdminController extends Controller
{
    /**
     * Display superadmin dashboard
     */
    public function index()
    {
        // Stats
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $totalUsers = User::count();
        $totalAdmins = User::where('is_super_admin', false)->count();
        
        // Recent tenants
        $recentTenants = Tenant::latest()->take(10)->get();
        
        // Tenants list
        $tenants = Tenant::withCount(['users' => function($query) {
            $query->where('is_super_admin', false);
        }])->orderBy('created_at', 'desc')->get();
        
        return view('superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'totalUsers',
            'totalAdmins',
            'recentTenants',
            'tenants'
        ));
    }
    
    /**
     * Access specific tenant
     */
    public function accessTenant($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        
        // Redirect to tenant subdomain
        $protocol = request()->secure() ? 'https' : 'http';
        $tenantUrl = "{$protocol}://{$tenant->subdomain}.kitabill.site/dashboard";
        
        return redirect()->away($tenantUrl);
    }
    
    /**
     * Display revenue report with scope support (subscriptions vs customers)
     */
    public function revenue(Request $request)
    {
        // Get scope parameter (default: subscriptions)
        $scope = $request->get('scope', 'subscriptions');
        
        // Get date filters with defaults (last 12 months)
        $endDate = $request->has('end_date') 
            ? Carbon::parse($request->end_date) 
            : Carbon::now();
        
        $startDate = $request->has('start_date') 
            ? Carbon::parse($request->start_date) 
            : $endDate->copy()->subMonths(12);
        
        // Ensure start date is before end date
        if ($startDate->gt($endDate)) {
            $startDate = $endDate->copy()->subMonths(12);
        }
        
        // --- FILTERS ---
        
        // Filter for Invoices
        $invoiceFilter = function($query) use ($scope) {
            $query->where('status', 'paid');
            if ($scope === 'subscriptions') {
                $query->whereNotNull('tenant_id')->whereNull('customer_id');
            } else {
                $query->whereNotNull('customer_id');
            }
        };

        // Filter for Payments that ARE NOT linked to any invoice (e.g. direct renewals)
        $paymentNoInvFilter = function($query) use ($scope) {
            $query->where('status', 'paid')->whereNull('invoice_id');
            if ($scope === 'subscriptions') {
                $query->whereNotNull('tenant_id');
            } else {
                // Skip direct payments for customer scope for now as they should always have an invoice
                $query->whereRaw('1=0'); 
            }
        };

        // --- SUMMARY CARDS ---
        
        // Total Revenue (This Month)
        $invRevenueMonth = Invoice::where($invoiceFilter)
            ->whereBetween('paid_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('total');
            
        $payRevenueNoInvMonth = Payment::where($paymentNoInvFilter)
            ->whereBetween('paid_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');
            
        $totalRevenueMonth = (float)$invRevenueMonth + (float)$payRevenueNoInvMonth;
        
        // Total Revenue (Today)
        $invRevenueToday = Invoice::where($invoiceFilter)
            ->whereDate('paid_at', Carbon::today())
            ->sum('total');
            
        $payRevenueNoInvToday = Payment::where($paymentNoInvFilter)
            ->whereDate('paid_at', Carbon::today())
            ->sum('amount');
            
        $totalRevenueToday = (float)$invRevenueToday + (float)$payRevenueNoInvToday;
        
        // Total Paid Count
        $totalPaidCount = Invoice::where($invoiceFilter)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count() +
            Payment::where($paymentNoInvFilter)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();
        
        // Total Unpaid (Invoices only)
        $unpaidFilter = function($query) use ($scope) {
            $query->whereIn('status', ['unpaid', 'overdue']);
            if ($scope === 'subscriptions') {
                $query->whereNotNull('tenant_id')->whereNull('customer_id');
            } else {
                $query->whereNotNull('customer_id');
            }
        };

        $totalUnpaidInvoices = Invoice::where($unpaidFilter)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->count();
        
        // Active Subscriptions
        $activeSubscriptions = 0;
        $trialSubscriptions = 0;
        if ($scope === 'subscriptions') {
            $activeSubscriptions = Tenant::where('status', 'active')->where('is_active', true)->count();
            $trialSubscriptions = Tenant::where('status', 'trial')->where('is_active', true)->count();
        }
        
        // --- TREND DATA ---
        
        // Get Monthly Revenue from Invoices
        $invMonthly = DB::table('invoices')
            ->select(DB::raw("TO_CHAR(paid_at, 'YYYY-MM') as month"), DB::raw('SUM(total) as revenue'))
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->where(function($q) use ($scope) {
                if ($scope === 'subscriptions') {
                    $q->whereNotNull('tenant_id')->whereNull('customer_id');
                } else {
                    $q->whereNotNull('customer_id');
                }
            })
            ->groupBy(DB::raw("TO_CHAR(paid_at, 'YYYY-MM')"))
            ->get();

        // Get Monthly Revenue from Payments (No Invoice)
        $payMonthly = DB::table('payments')
            ->select(DB::raw("TO_CHAR(paid_at, 'YYYY-MM') as month"), DB::raw('SUM(amount) as revenue'))
            ->where('status', 'paid')
            ->whereNull('invoice_id')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->where(function($q) use ($scope) {
                if ($scope === 'subscriptions') {
                    $q->whereNotNull('tenant_id');
                } else {
                    $q->whereRaw('1=0');
                }
            })
            ->groupBy(DB::raw("TO_CHAR(paid_at, 'YYYY-MM')"))
            ->get();

        // Combine Trend Data
        $monthlyRevenue = [];
        $current = $startDate->copy()->startOfMonth();
        while ($current->lte($endDate)) {
            $monthKey = $current->format('Y-m');
            $label = $current->format('M Y');
            
            $rev = $invMonthly->firstWhere('month', $monthKey)->revenue ?? 0;
            $rev += $payMonthly->firstWhere('month', $monthKey)->revenue ?? 0;
            
            $monthlyRevenue[] = [
                'month' => $label,
                'revenue' => (float) $rev
            ];
            $current->addMonth();
        }
        
        // Base transactions query for breakdowns
        $subRevenue = DB::table('invoices')
            ->select('tenant_id', 'total as amount', 'paid_at')
            ->where('status', 'paid')
            ->whereNotNull('tenant_id')
            ->whereNull('customer_id')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->unionAll(
                DB::table('payments')
                ->select('tenant_id', 'amount', 'paid_at')
                ->where('status', 'paid')
                ->whereNull('invoice_id')
                ->whereNotNull('tenant_id')
                ->whereBetween('paid_at', [$startDate, $endDate])
            );

        // --- OTHER BREAKDOWNS ---
        
        // Revenue by Plan
        if ($scope === 'subscriptions') {
            $revenueByPlan = DB::table('tenants')
                ->joinSub($subRevenue, 'rev', 'tenants.id', '=', 'rev.tenant_id')
                ->select(
                    DB::raw('COALESCE(tenants.subscription_plan, \'No Plan\') as name'),
                    DB::raw('SUM(rev.amount) as revenue'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('tenants.subscription_plan')
                ->orderByDesc('revenue')
                ->get();
        } else {
            // Customer scope remains primarily invoice-based
            $revenueByPlan = DB::table('invoices')
                ->join('customers', 'invoices.customer_id', '=', 'customers.id')
                ->leftJoin('packages', 'customers.package_id', '=', 'packages.id')
                ->select(
                    DB::raw('COALESCE(packages.name, \'No Package\') as name'),
                    DB::raw('SUM(invoices.total) as revenue'),
                    DB::raw('COUNT(invoices.id) as count')
                )
                ->where('invoices.status', 'paid')
                ->whereNotNull('invoices.customer_id')
                ->whereBetween('invoices.paid_at', [$startDate, $endDate])
                ->groupBy('packages.id', 'packages.name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();
        }
        
        // Top Tenants (Subscriptions scope)
        $topTenants = [];
        if ($scope === 'subscriptions') {
            $topTenants = DB::table('tenants')
                ->joinSub($subRevenue, 'rev', 'tenants.id', '=', 'rev.tenant_id')
                ->select(
                    'tenants.id', 'tenants.name', 'tenants.subdomain', 'tenants.subscription_plan',
                    DB::raw('SUM(rev.amount) as total_revenue'),
                    DB::raw('COUNT(*) as invoice_count')
                )
                ->groupBy('tenants.id', 'tenants.name', 'tenants.subdomain', 'tenants.subscription_plan')
                ->orderByDesc('total_revenue')
                ->limit(10)
                ->get();
        }
        
        // Payment Methods (Unified)
        $methodInv = DB::table('payments')
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->select('payments.payment_method', DB::raw('SUM(payments.amount) as total'))
            ->where('payments.status', 'paid')
            ->where(function($q) use ($scope) {
                if ($scope === 'subscriptions') {
                    $q->whereNotNull('invoices.tenant_id')->whereNull('invoices.customer_id');
                } else {
                    $q->whereNotNull('invoices.customer_id');
                }
            })
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->groupBy('payments.payment_method');

        $paymentMethods = DB::table('payments')
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->where('status', 'paid')
            ->whereNull('invoice_id')
            ->where(function($q) use ($scope) {
                if ($scope === 'subscriptions') {
                    $q->whereNotNull('tenant_id');
                } else {
                    $q->whereRaw('1=0');
                }
            })
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->groupBy('payment_method')
            ->union($methodInv)
            ->get()
            ->groupBy('payment_method')
            ->map(function($items, $method) {
                return [
                    'payment_method' => $method,
                    'total' => $items->sum('total'),
                    'count' => $items->count()
                ];
            })->values()->sortByDesc('total');

        // Recent Invoices / Transactions
        $recentInvQuery = DB::table('invoices')
            ->leftJoin('tenants', 'invoices.tenant_id', '=', 'tenants.id')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftJoin('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->select(
                'invoices.id', 
                'invoices.invoice_number',
                'invoices.total',
                'invoices.status',
                'invoices.paid_at',
                'invoices.issue_date',
                DB::raw('COALESCE(tenants.name, customers.name) as entity_name'),
                DB::raw('COALESCE(tenants.subdomain, customers.customer_code) as entity_code'),
                'payments.payment_method'
            )
            ->where('invoices.status', 'paid')
            ->where(function($q) use ($scope) {
                if ($scope === 'subscriptions') {
                    $q->whereNotNull('invoices.tenant_id')->whereNull('invoices.customer_id');
                } else {
                    $q->whereNotNull('invoices.customer_id');
                }
            })
            ->whereBetween('invoices.paid_at', [$startDate, $endDate]);

        $recentInvoices = DB::table('payments')
            ->leftJoin('tenants', 'payments.tenant_id', '=', 'tenants.id')
            ->select(
                'payments.id',
                'payments.invoice_number',
                'payments.amount as total',
                'payments.status',
                'payments.paid_at',
                'payments.created_at as issue_date',
                'tenants.name as entity_name',
                'tenants.subdomain as entity_code',
                'payments.payment_method'
            )
            ->where('payments.status', 'paid')
            ->whereNull('payments.invoice_id')
            ->where(function($q) use ($scope) {
                if ($scope === 'subscriptions') {
                    $q->whereNotNull('payments.tenant_id');
                } else {
                    $q->whereRaw('1=0');
                }
            })
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->union($recentInvQuery)
            ->orderBy('paid_at', 'desc')
            ->limit(50)
            ->get();
        
        return Inertia::render('SuperAdmin/Revenue', [
            'scope' => $scope,
            'stats' => [
                'totalRevenueMonth' => (float) $totalRevenueMonth,
                'totalRevenueToday' => (float) $totalRevenueToday,
                'totalPaidInvoices' => $totalPaidCount,
                'totalUnpaidInvoices' => $totalUnpaidInvoices,
                'activeSubscriptions' => $activeSubscriptions,
                'trialSubscriptions' => $trialSubscriptions,
            ],
            'monthlyRevenue' => $monthlyRevenue,
            'revenueByPlan' => $revenueByPlan,
            'topTenants' => $topTenants,
            'paymentMethods' => $paymentMethods,
            'recentInvoices' => $recentInvoices,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);
    }
}
