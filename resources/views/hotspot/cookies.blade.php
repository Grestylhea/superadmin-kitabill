@extends('layouts.admin')

@section('title', 'Hotspot Cookies - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')


<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3>
                        <i class="fas fa-cookie"></i> Hotspot Cookies
                        <span class="badge bg-primary">{{ $router->name }}</span>
                        <span class="badge bg-info">{{ count($cookies) }}</span>
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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Cookies Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Active Cookies
                    </h5>
                </div>
                <div class="card-body">
                    @if(empty($cookies))
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>
                            Tidak ada cookie aktif saat ini.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="30%">MAC Address</th>
                                        <th width="30%">Domain</th>
                                        <th width="20%">Expires In</th>
                                        <th width="15%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cookies as $index => $cookie)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                <code>{{ $cookie['mac_address'] }}</code>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $cookie['domain'] }}</span>
                                            </td>
                                            <td>
                                                @if(!empty($cookie['expires_in']))
                                                    <span class="badge bg-warning">{{ $cookie['expires_in'] }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form action="{{ route('hotspot.cookies.remove', $cookie['id']) }}" method="POST" class="d-inline"
                                                      onsubmit="return confirm('Hapus cookie ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Bulk Actions -->
                        <div class="mt-3">
                            <button class="btn btn-danger" onclick="if(confirm('Hapus SEMUA cookies?')) { document.getElementById('clearAllForm').submit(); }">
                                <i class="fas fa-trash-alt"></i> Clear All Cookies
                            </button>
                            <form id="clearAllForm" action="{{ route('hotspot.cookies') }}" method="POST" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
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
                    <h5><i class="fas fa-info-circle"></i> Tentang Hotspot Cookies</h5>
                    <p class="mb-0">
                        Cookies hotspot digunakan untuk menyimpan informasi login user sehingga mereka tidak perlu 
                        login ulang setelah disconnect (tergantung konfigurasi profile). Cookie tersimpan berdasarkan 
                        MAC address perangkat.
                    </p>
                    <hr>
                    <p class="mb-0 small">
                        <strong>Kapan menghapus cookies:</strong><br>
                        - User tidak bisa login karena cookie expired<br>
                        - Troubleshooting masalah koneksi<br>
                        - Security: paksa user login ulang<br>
                        - Clear setelah ganti password user
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Cookies
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ count($cookies) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Unique MAC Addresses
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        {{ count(array_unique(array_column($cookies, 'mac_address'))) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Cache Status
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        5 min
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
    .text-xs { font-size: 0.7rem; }
</style>
@endpush

