<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function financial()
    {
        return view('reports.financial');
    }

    public function exportFinancialPdf()
    {
        // TODO: Implement PDF export
        return back()->with('error', 'Fitur belum tersedia');
    }

    public function exportFinancialExcel()
    {
        // TODO: Implement Excel export
        return back()->with('error', 'Fitur belum tersedia');
    }

    public function customer()
    {
        return view('reports.customer');
    }

    public function exportCustomerExcel()
    {
        // TODO: Implement Excel export
        return back()->with('error', 'Fitur belum tersedia');
    }

    public function support()
    {
        return view('reports.support');
    }

    public function exportSupportExcel()
    {
        // TODO: Implement Excel export
        return back()->with('error', 'Fitur belum tersedia');
    }

    public function capacity()
    {
        return view('reports.capacity');
    }

    public function fiberUsage()
    {
        return view('reports.fiber-usage');
    }

    public function portUtilization()
    {
        return view('reports.port-utilization');
    }
}


