<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class GlobalMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // Ambil semua tenant untuk mapping nama
        $tenants = Tenant::select('id', 'name', 'subdomain', 'status')->get()->keyBy('id');

        // Agregasi Pelanggan Lintas Tenant menggunakan DB builder (untuk menghindari event booted Eloquent)
        $customerStatsRaw = DB::table('customers')
            ->select('tenant_id', 'status', DB::raw('count(*) as total'))
            ->whereNull('deleted_at') // support SoftDeletes
            ->groupBy('tenant_id', 'status')
            ->get();

        $customerStats = [];
        foreach ($customerStatsRaw as $stat) {
            $tId = $stat->tenant_id;
            if (!isset($customerStats[$tId])) {
                $customerStats[$tId] = ['active' => 0, 'suspended' => 0, 'total' => 0];
            }
            if ($stat->status == 'active') $customerStats[$tId]['active'] += $stat->total;
            if ($stat->status == 'suspended') $customerStats[$tId]['suspended'] += $stat->total;
            $customerStats[$tId]['total'] += $stat->total;
        }

        // Agregasi Keuangan Lintas Tenant (Berdasarkan Bulan & Tahun) menggunakan DB builder
        $invoiceStatsRaw = DB::table('invoices')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year)
            ->select('tenant_id', 'status', DB::raw('sum(total) as total_amount'), DB::raw('count(*) as count'))
            ->groupBy('tenant_id', 'status')
            ->get();

        $financialStats = [];
        foreach ($invoiceStatsRaw as $stat) {
            $tId = $stat->tenant_id;
            if (!isset($financialStats[$tId])) {
                $financialStats[$tId] = ['paid' => 0, 'unpaid' => 0, 'total_revenue' => 0, 'total_receivable' => 0];
            }
            if ($stat->status == 'paid') {
                $financialStats[$tId]['paid'] += $stat->count;
                $financialStats[$tId]['total_revenue'] += $stat->total_amount;
            }
            if ($stat->status == 'unpaid' || $stat->status == 'overdue') {
                $financialStats[$tId]['unpaid'] += $stat->count;
                $financialStats[$tId]['total_receivable'] += $stat->total_amount;
            }
        }

        // Gabungkan data
        $monitoringData = [];
        foreach ($tenants as $id => $tenant) {
            $monitoringData[] = [
                'tenant_id' => $id,
                'tenant_name' => $tenant->name,
                'tenant_subdomain' => $tenant->subdomain,
                'tenant_status' => $tenant->status,
                'customers' => $customerStats[$id] ?? ['active' => 0, 'suspended' => 0, 'total' => 0],
                'finances' => $financialStats[$id] ?? ['paid' => 0, 'unpaid' => 0, 'total_revenue' => 0, 'total_receivable' => 0],
            ];
        }

        // Hitung Grand Total
        $grandTotal = [
            'total_customers' => array_sum(array_column(array_column($monitoringData, 'customers'), 'total')),
            'total_revenue' => array_sum(array_column(array_column($monitoringData, 'finances'), 'total_revenue')),
            'total_receivable' => array_sum(array_column(array_column($monitoringData, 'finances'), 'total_receivable')),
        ];

        return Inertia::render('SuperAdmin/Monitoring/Index', [
            'monitoringData' => $monitoringData,
            'grandTotal' => $grandTotal,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ]
        ]);
    }
}
