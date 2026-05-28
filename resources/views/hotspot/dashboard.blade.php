@extends('layouts.admin')

@section('title', 'Hotspot Dashboard - ' . $router->name)

@section('content')
@include('hotspot.partials.router-selector')

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-wifi text-primary"></i> Hotspot Management
            </h2>
            <p class="text-muted mb-0">
                <i class="bi bi-router"></i> {{ $router->name }} - {{ $router->ip_address }}
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Users -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-gradient text-white rounded-3 p-3">
                                <i class="bi bi-people-fill fs-3"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Total Users</h6>
                            <h2 class="mb-0 fw-bold">{{ number_format($stats['total_users']) }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Sessions -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-gradient text-white rounded-3 p-3">
                                <i class="bi bi-person-check-fill fs-3"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Active Sessions</h6>
                            <h2 class="mb-0 fw-bold text-success">{{ number_format($stats['active_users']) }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Profiles -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-gradient text-white rounded-3 p-3">
                                <i class="bi bi-list-check fs-3"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Profiles</h6>
                            <h2 class="mb-0 fw-bold text-info">{{ number_format($stats['total_profiles']) }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disabled Users -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-gradient text-white rounded-3 p-3">
                                <i class="bi bi-person-x-fill fs-3"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem; font-weight: 600;">Disabled Users</h6>
                            <h2 class="mb-0 fw-bold text-warning">{{ number_format($stats['disabled_users']) }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="{{ route('hotspot.users.index', $router) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body text-center py-4">
                        <div class="bg-primary bg-gradient text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="bi bi-people-fill fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Manage Users</h5>
                        <p class="text-muted small mb-0">View & manage hotspot users</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="{{ route('hotspot.users.create', $router) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body text-center py-4">
                        <div class="bg-success bg-gradient text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="bi bi-person-plus-fill fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Add User</h5>
                        <p class="text-muted small mb-0">Create new hotspot user</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="{{ route('hotspot.generator', $router) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body text-center py-4">
                        <div class="bg-info bg-gradient text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="bi bi-ticket-perforated-fill fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Generate Vouchers</h5>
                        <p class="text-muted small mb-0">Batch create vouchers</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="{{ route('hotspot.active-sessions', $router) }}" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-lift">
                    <div class="card-body text-center py-4">
                        <div class="bg-warning bg-gradient text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="bi bi-activity fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Active Sessions</h5>
                        <p class="text-muted small mb-0">Monitor online users</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Feature Menu -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-grid-3x3-gap-fill text-primary"></i> All Features
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.users.index', $router) }}" class="btn btn-outline-primary w-100 py-3 text-start">
                        <i class="bi bi-people"></i> Users
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.active-sessions', $router) }}" class="btn btn-outline-success w-100 py-3 text-start">
                        <i class="bi bi-activity"></i> Active
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.profiles.index', $router) }}" class="btn btn-outline-info w-100 py-3 text-start">
                        <i class="bi bi-person-badge"></i> Profiles
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.generator', $router) }}" class="btn btn-outline-warning w-100 py-3 text-start">
                        <i class="bi bi-ticket-perforated"></i> Generate
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.hosts', $router) }}" class="btn btn-outline-secondary w-100 py-3 text-start">
                        <i class="bi bi-pc-display"></i> Hosts
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.ip-bindings', $router) }}" class="btn btn-outline-dark w-100 py-3 text-start">
                        <i class="bi bi-link-45deg"></i> Bindings
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.cookies', $router) }}" class="btn btn-outline-primary w-100 py-3 text-start">
                        <i class="bi bi-cookie"></i> Cookies
                    </a>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="{{ route('hotspot.logs', $router) }}" class="btn btn-outline-danger w-100 py-3 text-start">
                        <i class="bi bi-clock-history"></i> Log
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-0 bg-light">
                <div class="card-body py-3">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-info-circle-fill text-info"></i>
                        <strong>Hotspot Management:</strong> Kelola user hotspot, monitor active sessions, generate vouchers, dan berbagai fitur lainnya untuk router <strong>{{ $router->name }}</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .hover-lift {
        transition: all 0.3s ease;
    }
    
    .hover-lift:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    
    .bg-gradient {
        background: linear-gradient(135deg, var(--bs-bg-opacity, 1), rgba(0, 0, 0, 0.2)) !important;
    }
</style>
@endpush

