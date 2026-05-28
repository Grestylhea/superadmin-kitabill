@extends('layouts.admin')

@section('title', 'Hotspot Hosts - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-desktop"></i> Hotspot Hosts
                        <span class="badge bg-primary">{{ $router->name }}</span>
                        <span class="badge bg-success">{{ count($hosts) }}</span>
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

    <!-- Hosts Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Connected Devices
                    </h5>
                </div>
                <div class="card-body">
                    @if(empty($hosts))
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>
                            Tidak ada host yang terhubung saat ini.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>MAC Address</th>
                                        <th>IP Address</th>
                                        <th>To Address</th>
                                        <th>Server</th>
                                        <th>Uptime</th>
                                        <th>Idle Time</th>
                                        <th>Download</th>
                                        <th>Upload</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($hosts as $host)
                                        <tr>
                                            <td><code>{{ $host['mac_address'] }}</code></td>
                                            <td><strong>{{ $host['address'] }}</strong></td>
                                            <td>{{ $host['to_address'] ?? '-' }}</td>
                                            <td>{{ $host['server'] }}</td>
                                            <td>{{ $host['uptime'] }}</td>
                                            <td>{{ $host['idle_time'] ?? '-' }}</td>
                                            <td>
                                                <small>{{ formatBytes($host['bytes_in']) }}</small>
                                            </td>
                                            <td>
                                                <small>{{ formatBytes($host['bytes_out']) }}</small>
                                            </td>
                                            <td>
                                                @if(isset($host['authorized']) && $host['authorized'])
                                                    <span class="badge bg-success">Authorized</span>
                                                @else
                                                    <span class="badge bg-warning">Unauthorized</span>
                                                @endif
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

    <!-- Statistics -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Hosts
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ count($hosts) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Authorized
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ collect($hosts)->where('authorized', true)->count() }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Total Download
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ formatBytes(collect($hosts)->sum('bytes_in')) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Total Upload
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ formatBytes(collect($hosts)->sum('bytes_out')) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Tentang Hosts:</strong> Menampilkan semua perangkat yang terhubung ke hotspot, 
                termasuk yang sudah login (authorized) dan yang belum (unauthorized). 
                Data di-cache selama 30 detik untuk performa optimal.
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
    .text-xs { font-size: 0.7rem; }
</style>
@endpush

@php
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
@endphp

