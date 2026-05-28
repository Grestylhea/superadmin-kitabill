@extends('layouts.admin')

@section('title', 'User Activity Log - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-history"></i> User Activity Log
                        <span class="badge bg-primary">{{ $router->name }}</span>
                        <span class="badge bg-info">{{ count($logs) }}</span>
                    </h3>
                </div>
                <div>
                    <button class="btn btn-info" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <a href="{{ route('hotspot.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('hotspot.log') }}" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter Topics</label>
                            <select name="topics" class="form-select">
                                <option value="hotspot,account" {{ request('topics') == 'hotspot,account' ? 'selected' : '' }}>
                                    Hotspot & Account
                                </option>
                                <option value="hotspot" {{ request('topics') == 'hotspot' ? 'selected' : '' }}>
                                    Hotspot Only
                                </option>
                                <option value="account" {{ request('topics') == 'account' ? 'selected' : '' }}>
                                    Account Only
                                </option>
                                <option value="system" {{ request('topics') == 'system' ? 'selected' : '' }}>
                                    System
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search Message</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search in log messages..." 
                                   value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Activity Logs (Last 100)
                    </h5>
                </div>
                <div class="card-body">
                    @if(empty($logs))
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>
                            Tidak ada log activity ditemukan.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="15%">Time</th>
                                        <th width="15%">Topics</th>
                                        <th width="70%">Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>
                                                <small>{{ $log['time'] }}</small>
                                            </td>
                                            <td>
                                                @php
                                                    $topics = explode(',', $log['topics']);
                                                    foreach($topics as $topic) {
                                                        $topic = trim($topic);
                                                        $badgeClass = 'secondary';
                                                        if(stripos($topic, 'hotspot') !== false) {
                                                            $badgeClass = 'primary';
                                                        } elseif(stripos($topic, 'account') !== false) {
                                                            $badgeClass = 'success';
                                                        } elseif(stripos($topic, 'error') !== false) {
                                                            $badgeClass = 'danger';
                                                        } elseif(stripos($topic, 'warning') !== false) {
                                                            $badgeClass = 'warning';
                                                        }
                                                        echo '<span class="badge bg-'.$badgeClass.' me-1">'.$topic.'</span>';
                                                    }
                                                @endphp
                                            </td>
                                            <td>
                                                <small>{{ $log['message'] }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Info Box -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <h5><i class="fas fa-info-circle"></i> Tentang Activity Log</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Log Topics:</strong></p>
                            <ul class="small">
                                <li><span class="badge bg-primary">hotspot</span> - Hotspot login/logout activity</li>
                                <li><span class="badge bg-success">account</span> - User account changes</li>
                                <li><span class="badge bg-danger">error</span> - Error messages</li>
                                <li><span class="badge bg-warning">warning</span> - Warning messages</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Useful for:</strong></p>
                            <ul class="small mb-0">
                                <li>Monitoring user login/logout activity</li>
                                <li>Troubleshooting connection issues</li>
                                <li>Security audit</li>
                                <li>Track authentication failures</li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <p class="mb-0 small text-muted">
                        <i class="fas fa-clock"></i> Menampilkan 100 log terakhir. Log di-filter dari MikroTik RouterOS log system.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Logs
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ count($logs) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Hotspot Logs
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ collect($logs)->filter(function($log) { 
                            return stripos($log['topics'], 'hotspot') !== false; 
                        })->count() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Account Logs
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ collect($logs)->filter(function($log) { 
                            return stripos($log['topics'], 'account') !== false; 
                        })->count() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-danger shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Error Logs
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ collect($logs)->filter(function($log) { 
                            return stripos($log['topics'], 'error') !== false; 
                        })->count() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .border-left-danger { border-left: 4px solid #e74a3b !important; }
    .text-xs { font-size: 0.7rem; }
</style>
@endpush

